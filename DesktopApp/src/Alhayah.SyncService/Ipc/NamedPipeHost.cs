using System.Buffers.Binary;
using System.Collections.Concurrent;
using System.IO.Pipes;
using System.Security.AccessControl;
using System.Security.Principal;
using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Ipc;

public static class SyncServiceJson
{
    public static JsonSerializerOptions Options { get; } = new()
    {
        PropertyNameCaseInsensitive = false,
        MaxDepth = 32,
        ReadCommentHandling = JsonCommentHandling.Disallow,
        AllowTrailingCommas = false
    };
}

public sealed class SyncServiceException(string code) : Exception
{
    public string Code { get; } = string.IsNullOrWhiteSpace(code)
        ? throw new ArgumentException("A safe IPC error code is required.", nameof(code))
        : code;
}

public interface ILocalStoreCommandHandler
{
    Task<object> HandleAsync(SyncServiceRequest request, CancellationToken cancellationToken);
}

public sealed class NamedPipeHostOptions
{
    public string PipeName { get; init; } = SyncServiceProtocol.DefaultPipeName;

    public IReadOnlyList<string> AllowedSids { get; init; } =
        [new SecurityIdentifier(WellKnownSidType.BuiltinUsersSid, null).Value];

    public IReadOnlyList<string> DeniedSids { get; init; } = [];

    public TimeSpan RequestTimeout { get; init; } = TimeSpan.FromSeconds(30);

    public int MaxConcurrentConnections { get; init; } = 32;

    public void Validate()
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(PipeName);
        if (PipeName.Length > 240 || PipeName.IndexOfAny(new[] { '\\', '/', '\0' }) >= 0)
        {
            throw new ArgumentException("The named pipe name is invalid.", nameof(PipeName));
        }

        if (RequestTimeout <= TimeSpan.Zero || RequestTimeout > TimeSpan.FromMinutes(10))
        {
            throw new ArgumentOutOfRangeException(nameof(RequestTimeout));
        }

        if (MaxConcurrentConnections is < 1 or > 256 || AllowedSids.Count == 0)
        {
            throw new ArgumentOutOfRangeException(nameof(MaxConcurrentConnections));
        }

        foreach (string sid in AllowedSids.Concat(DeniedSids))
        {
            _ = new SecurityIdentifier(sid);
        }
    }
}

public sealed class NamedPipeConnection(NamedPipeServerStream stream) : IAsyncDisposable
{
    public NamedPipeServerStream Stream { get; } = stream ?? throw new ArgumentNullException(nameof(stream));

    public ValueTask DisposeAsync() => Stream.DisposeAsync();
}

public interface INamedPipeConnectionAccepter
{
    Task<NamedPipeConnection> AcceptAsync(CancellationToken cancellationToken);
}

public sealed class WindowsNamedPipeAccepter(NamedPipeHostOptions options) : INamedPipeConnectionAccepter
{
    private readonly NamedPipeHostOptions _options = options ?? throw new ArgumentNullException(nameof(options));

    public async Task<NamedPipeConnection> AcceptAsync(CancellationToken cancellationToken)
    {
        _options.Validate();
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("The secured sync-service named pipe requires Windows.");
        }

        NamedPipeServerStream stream = NamedPipeServerStreamAcl.Create(
            _options.PipeName,
            PipeDirection.InOut,
            _options.MaxConcurrentConnections,
            PipeTransmissionMode.Byte,
            PipeOptions.Asynchronous,
            inBufferSize: 4096,
            outBufferSize: 4096,
            CreateSecurity(_options));
        try
        {
            await stream.WaitForConnectionAsync(cancellationToken).ConfigureAwait(false);
            return new NamedPipeConnection(stream);
        }
        catch
        {
            await stream.DisposeAsync().ConfigureAwait(false);
            throw;
        }
    }

    private static PipeSecurity CreateSecurity(NamedPipeHostOptions options)
    {
        PipeSecurity security = new();
        security.SetAccessRuleProtection(isProtected: true, preserveInheritance: false);
        foreach (string sid in options.DeniedSids.Distinct(StringComparer.Ordinal))
        {
            security.AddAccessRule(new PipeAccessRule(
                new SecurityIdentifier(sid),
                PipeAccessRights.FullControl,
                AccessControlType.Deny));
        }

        string? currentUserSid = WindowsIdentity.GetCurrent().User?.Value;
        IEnumerable<string> ownerSids = new[]
            {
                new SecurityIdentifier(WellKnownSidType.LocalSystemSid, null).Value,
                new SecurityIdentifier(WellKnownSidType.BuiltinAdministratorsSid, null).Value
            }
            .Concat(currentUserSid is null ? Enumerable.Empty<string>() : new[] { currentUserSid });
        foreach (string sid in ownerSids.Distinct(StringComparer.Ordinal))
        {
            security.AddAccessRule(new PipeAccessRule(
                new SecurityIdentifier(sid),
                PipeAccessRights.FullControl,
                AccessControlType.Allow));
        }

        foreach (string sid in options.AllowedSids.Distinct(StringComparer.Ordinal))
        {
            security.AddAccessRule(new PipeAccessRule(
                new SecurityIdentifier(sid),
                PipeAccessRights.ReadWrite,
                AccessControlType.Allow));
        }

        return security;
    }
}

public sealed class NamedPipeHost : IAsyncDisposable
{
    private readonly NamedPipeHostOptions _options;
    private readonly ILocalStoreCommandHandler _handler;
    private readonly INamedPipeConnectionAccepter _accepter;
    private readonly ILogger<NamedPipeHost> _logger;
    private readonly CancellationTokenSource _lifetime = new();
    private readonly ConcurrentDictionary<int, Task> _activeHandlers = new();
    private SemaphoreSlim? _connectionSlots;
    private Task _acceptLoop = Task.CompletedTask;
    private int _nextHandlerId;
    private volatile bool _isRunning;

    public NamedPipeHost(
        NamedPipeHostOptions options,
        ILocalStoreCommandHandler handler,
        INamedPipeConnectionAccepter accepter,
        ILogger<NamedPipeHost> logger)
    {
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _handler = handler ?? throw new ArgumentNullException(nameof(handler));
        _accepter = accepter ?? throw new ArgumentNullException(nameof(accepter));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        _options.Validate();
    }

    public bool IsRunning => _isRunning;

    public Task StartAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        if (_isRunning)
        {
            throw new InvalidOperationException("The named pipe host is already running.");
        }

        _connectionSlots = new SemaphoreSlim(_options.MaxConcurrentConnections);
        _isRunning = true;
        _acceptLoop = Task.Run(() => AcceptLoopAsync(_lifetime.Token), CancellationToken.None);
        _logger.LogInformation(new EventId(27001), "The sync-service IPC host started.");
        return Task.CompletedTask;
    }

    public async Task StopAsync(CancellationToken cancellationToken)
    {
        if (!_isRunning)
        {
            return;
        }

        await _lifetime.CancelAsync().ConfigureAwait(false);
        try
        {
            try
            {
                await _acceptLoop.WaitAsync(cancellationToken).ConfigureAwait(false);
            }
            catch (OperationCanceledException) when (_lifetime.IsCancellationRequested)
            {
            }

            await Task.WhenAll(_activeHandlers.Values).WaitAsync(cancellationToken).ConfigureAwait(false);
        }
        finally
        {
            _isRunning = false;
            _connectionSlots?.Dispose();
            _connectionSlots = null;
            _logger.LogInformation(new EventId(27002), "The sync-service IPC host stopped.");
        }
    }

    public async ValueTask DisposeAsync()
    {
        await StopAsync(CancellationToken.None).ConfigureAwait(false);
        _lifetime.Dispose();
    }

    private async Task AcceptLoopAsync(CancellationToken cancellationToken)
    {
        while (!cancellationToken.IsCancellationRequested)
        {
            SemaphoreSlim slots = _connectionSlots!;
            await slots.WaitAsync(cancellationToken).ConfigureAwait(false);
            try
            {
                NamedPipeConnection connection = await _accepter.AcceptAsync(cancellationToken).ConfigureAwait(false);
                int handlerId = Interlocked.Increment(ref _nextHandlerId);
                Task handler = HandleConnectionAndReleaseAsync(connection, slots, cancellationToken);
                _activeHandlers[handlerId] = handler;
                _ = handler.ContinueWith(
                    _ => _activeHandlers.TryRemove(handlerId, out _),
                    CancellationToken.None,
                    TaskContinuationOptions.ExecuteSynchronously,
                    TaskScheduler.Default);
            }
            catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
            {
                slots.Release();
                break;
            }
            catch (Exception exception)
            {
                slots.Release();
                _logger.LogError(
                    new EventId(27003),
                    "The sync-service IPC accept operation failed with {ErrorType}.",
                    exception.GetType().Name);
                if (!cancellationToken.IsCancellationRequested)
                {
                    await Task.Delay(TimeSpan.FromMilliseconds(100), cancellationToken).ConfigureAwait(false);
                }
            }
        }
    }

    private async Task HandleConnectionAndReleaseAsync(
        NamedPipeConnection connection,
        SemaphoreSlim slots,
        CancellationToken cancellationToken)
    {
        try
        {
            await using (connection)
            {
                await HandleConnectionAsync(connection.Stream, cancellationToken).ConfigureAwait(false);
            }
        }
        finally
        {
            slots.Release();
        }
    }

    private async Task HandleConnectionAsync(
        NamedPipeServerStream stream,
        CancellationToken cancellationToken)
    {
        Guid requestId = Guid.Empty;
        using CancellationTokenSource timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(_options.RequestTimeout);
        CancellationToken requestToken = timeout.Token;
        try
        {
            byte[] frame = await ReadFrameAsync(stream, requestToken).ConfigureAwait(false);
            SyncServiceRequest request = JsonSerializer.Deserialize<SyncServiceRequest>(frame, SyncServiceJson.Options)
                ?? throw new JsonException("The request is empty.");
            requestId = request.RequestId;
            if (requestId == Guid.Empty || string.IsNullOrWhiteSpace(request.Command))
            {
                throw new SyncServiceException("invalid_request");
            }

            if (request.ProtocolVersion != SyncServiceProtocol.Version)
            {
                throw new SyncServiceException("unsupported_protocol");
            }

            object payload = await _handler.HandleAsync(request, requestToken).ConfigureAwait(false);
            await WriteResponseAsync(stream, Success(requestId, payload), requestToken).ConfigureAwait(false);
        }
        catch (InvalidDataException)
        {
            await TryWriteResponseAsync(stream, Failure(requestId, "invalid_frame"), cancellationToken).ConfigureAwait(false);
        }
        catch (JsonException)
        {
            await TryWriteResponseAsync(stream, Failure(requestId, "malformed_json"), cancellationToken).ConfigureAwait(false);
        }
        catch (NotSupportedException)
        {
            await TryWriteResponseAsync(stream, Failure(requestId, "malformed_json"), cancellationToken).ConfigureAwait(false);
        }
        catch (SyncServiceException exception)
        {
            await TryWriteResponseAsync(stream, Failure(requestId, exception.Code), cancellationToken).ConfigureAwait(false);
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested && timeout.IsCancellationRequested)
        {
            await TryWriteResponseAsync(stream, Failure(requestId, "timeout"), cancellationToken).ConfigureAwait(false);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
        }
        catch (IOException)
        {
            _logger.LogInformation(new EventId(27004), "An IPC client disconnected before its request completed.");
        }
        catch (Exception exception)
        {
            _logger.LogError(
                new EventId(27005),
                "An IPC request failed with {ErrorType}.",
                exception.GetType().Name);
            await TryWriteResponseAsync(stream, Failure(requestId, "internal_error"), cancellationToken).ConfigureAwait(false);
        }
    }

    private static async Task<byte[]> ReadFrameAsync(Stream stream, CancellationToken cancellationToken)
    {
        byte[] prefix = await ReadExactAsync(stream, 4, cancellationToken).ConfigureAwait(false);
        int length = BinaryPrimitives.ReadInt32LittleEndian(prefix);
        if (length <= 0 || length > SyncServiceProtocol.MaxFrameBytes)
        {
            throw new InvalidDataException("The IPC frame length is outside the protocol boundary.");
        }

        return await ReadExactAsync(stream, length, cancellationToken).ConfigureAwait(false);
    }

    private static async Task<byte[]> ReadExactAsync(
        Stream stream,
        int length,
        CancellationToken cancellationToken)
    {
        byte[] buffer = new byte[length];
        int offset = 0;
        while (offset < length)
        {
            int read = await stream.ReadAsync(buffer.AsMemory(offset, length - offset), cancellationToken)
                .ConfigureAwait(false);
            if (read == 0)
            {
                throw new InvalidDataException("The IPC frame ended before its declared length.");
            }

            offset += read;
        }

        return buffer;
    }

    private static SyncServiceResponse Success(Guid requestId, object payload) => new()
    {
        RequestId = requestId,
        ProtocolVersion = SyncServiceProtocol.Version,
        Success = true,
        Payload = JsonSerializer.SerializeToElement(payload, payload.GetType(), SyncServiceJson.Options)
    };

    private static SyncServiceResponse Failure(Guid requestId, string code) => new()
    {
        RequestId = requestId,
        ProtocolVersion = SyncServiceProtocol.Version,
        Success = false,
        Error = new SyncServiceError { Code = code }
    };

    private static async Task WriteResponseAsync(
        Stream stream,
        SyncServiceResponse response,
        CancellationToken cancellationToken)
    {
        byte[] frame = JsonSerializer.SerializeToUtf8Bytes(response, SyncServiceJson.Options);
        if (frame.Length > SyncServiceProtocol.MaxFrameBytes)
        {
            frame = JsonSerializer.SerializeToUtf8Bytes(
                Failure(response.RequestId, "response_too_large"),
                SyncServiceJson.Options);
        }

        byte[] prefix = new byte[4];
        BinaryPrimitives.WriteInt32LittleEndian(prefix, frame.Length);
        await stream.WriteAsync(prefix, cancellationToken).ConfigureAwait(false);
        await stream.WriteAsync(frame, cancellationToken).ConfigureAwait(false);
        await stream.FlushAsync(cancellationToken).ConfigureAwait(false);
    }

    private async Task TryWriteResponseAsync(
        Stream stream,
        SyncServiceResponse response,
        CancellationToken cancellationToken)
    {
        try
        {
            await WriteResponseAsync(stream, response, cancellationToken).ConfigureAwait(false);
        }
        catch (Exception exception) when (exception is IOException or OperationCanceledException or ObjectDisposedException)
        {
            _logger.LogInformation(new EventId(27006), "An IPC error response could not be delivered.");
        }
    }
}
