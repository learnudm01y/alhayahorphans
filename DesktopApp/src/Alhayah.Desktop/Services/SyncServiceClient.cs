using System.Buffers.Binary;
using System.ComponentModel;
using System.IO.Pipes;
using System.Runtime.InteropServices;
using System.Security.Principal;
using System.Text.Json;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Models;
using Alhayah.Desktop.Services.Interfaces;
using Microsoft.Win32.SafeHandles;

namespace Alhayah.Desktop.Services;

public interface ISyncServiceClient : IDesktopIpcClient
{
    LocalSession? CurrentSession { get; }

    Task<LocalSession> LoginAsync(LoginRequest request, CancellationToken cancellationToken = default);

    Task<LocalSession> BootstrapAdministratorAsync(
        BootstrapAdministratorRequest request,
        CancellationToken cancellationToken = default);

    Task LogoutAsync(CancellationToken cancellationToken = default);

    Task<ConnectionSettingsData> GetConnectionSettingsAsync(CancellationToken cancellationToken = default);

    Task SaveConnectionSettingsAsync(
        SaveConnectionSettingsRequest request,
        CancellationToken cancellationToken = default);

    Task<ConnectionCheckResult> CheckConnectionAsync(
        ConnectionCheckRequest request,
        CancellationToken cancellationToken = default);

    Task<SynchronizeNowResult> SynchronizeNowAsync(
        SynchronizeNowRequest request,
        CancellationToken cancellationToken = default);

    Task<ConflictListResponse> GetConflictsAsync(CancellationToken cancellationToken = default);

    Task<ConflictResolutionResponse> ResolveConflictAsync(
        ConflictResolutionRequest request,
        CancellationToken cancellationToken = default);

    Task WriteAuditAsync(AuditEventRequest request, CancellationToken cancellationToken = default);

    Task<ReadEntityResponse> ReadEntityAsync(ReadEntityRequest request, CancellationToken cancellationToken = default);

    Task<EntityListResponse> SearchEntitiesAsync(SearchEntitiesRequest request, CancellationToken cancellationToken = default);

    Task<SaveEntityResponse> SaveEntityAsync(SaveEntityRequest request, CancellationToken cancellationToken = default);

    Task<EntityListResponse> QueryEntitiesAsync(QueryEntitiesRequest request, CancellationToken cancellationToken = default);

    Task<BackupResponse> CreateBackupAsync(BackupRequest request, CancellationToken cancellationToken = default);

    Task<RestoreResponse> RestoreBackupAsync(RestoreRequest request, CancellationToken cancellationToken = default);
}

public sealed record SyncServiceClientOptions
{
    public string PipeName { get; init; } = SyncServiceProtocol.DefaultPipeName;

    public string ServiceName { get; init; } = SyncServiceProtocol.DefaultServiceName;

    public TimeSpan ConnectTimeout { get; init; } = TimeSpan.FromSeconds(5);

    public TimeSpan RequestTimeout { get; init; } = TimeSpan.FromSeconds(30);

    public int MaxFrameBytes { get; init; } = SyncServiceProtocol.MaxFrameBytes;
}

public interface ISyncServiceConnectionFactory
{
    Task<Stream> ConnectAsync(SyncServiceClientOptions options, CancellationToken cancellationToken);
}

public interface ISyncServiceServerIdentityValidator
{
    Task ValidateAsync(
        Stream connection,
        SyncServiceClientOptions options,
        CancellationToken cancellationToken);
}

public sealed class SyncServiceException : Exception
{
    public SyncServiceException(string command, string errorCode)
        : base($"The local synchronization service rejected '{command}' with error code '{errorCode}'.")
    {
        Command = command;
        ErrorCode = errorCode;
    }

    public string Command { get; }

    public string ErrorCode { get; }
}

public sealed class SyncServiceClient : ISyncServiceClient
{
    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        PropertyNameCaseInsensitive = false,
        MaxDepth = 32,
        Converters = { new JsonStringEnumConverter() }
    };

    private readonly SyncServiceClientOptions _options;
    private readonly ISyncServiceConnectionFactory _connectionFactory;
    private readonly ISyncServiceServerIdentityValidator _serverIdentityValidator;
    private LocalSession? _currentSession;

    public SyncServiceClient()
        : this(
            new SyncServiceClientOptions(),
            new NamedPipeConnectionFactory(),
            new WindowsServiceIdentityValidator())
    {
    }

    public SyncServiceClient(
        SyncServiceClientOptions options,
        ISyncServiceConnectionFactory connectionFactory,
        ISyncServiceServerIdentityValidator serverIdentityValidator)
    {
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _connectionFactory = connectionFactory ?? throw new ArgumentNullException(nameof(connectionFactory));
        _serverIdentityValidator = serverIdentityValidator ?? throw new ArgumentNullException(nameof(serverIdentityValidator));
        ValidateOptions(options);
    }

    public LocalSession? CurrentSession => _currentSession;

    public async Task<LocalSession> LoginAsync(LoginRequest request, CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        LocalSession session = await SendAsync<LoginRequest, LocalSession>(
            SyncServiceCommands.Login,
            request,
            cancellationToken).ConfigureAwait(false);
        SetCurrentSession(session);
        return session;
    }

    public async Task<LocalSession> BootstrapAdministratorAsync(
        BootstrapAdministratorRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        LocalSession session = await SendAsync<BootstrapAdministratorRequest, LocalSession>(
            SyncServiceCommands.BootstrapAdministrator,
            request,
            cancellationToken).ConfigureAwait(false);
        SetCurrentSession(session);
        return session;
    }

    public async Task LogoutAsync(CancellationToken cancellationToken = default)
    {
        try
        {
            AcknowledgedResponse response = await SendAsync<EmptyRequest, AcknowledgedResponse>(
                SyncServiceCommands.Logout,
                new EmptyRequest(),
                cancellationToken).ConfigureAwait(false);
            EnsureAcknowledged(response);
        }
        finally
        {
            _currentSession = null;
        }
    }

    public async Task<DesktopSettings> GetSettingsAsync(CancellationToken cancellationToken = default)
    {
        ConnectionSettingsData settings = await GetConnectionSettingsAsync(cancellationToken).ConfigureAwait(false);
        return new DesktopSettings { ConnectionName = settings.ConnectionName };
    }

    public async Task SaveSettingsAsync(DesktopSettings settings, CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(settings);
        ConnectionSettingsData current = await GetConnectionSettingsAsync(cancellationToken).ConfigureAwait(false);
        await SaveConnectionSettingsAsync(
            new SaveConnectionSettingsRequest
            {
                Settings = current with { ConnectionName = settings.ConnectionName }
            },
            cancellationToken).ConfigureAwait(false);
    }

    public Task<ConnectionSettingsData> GetConnectionSettingsAsync(CancellationToken cancellationToken = default) =>
        SendAsync<EmptyRequest, ConnectionSettingsData>(
            SyncServiceCommands.GetSettings,
            new EmptyRequest(),
            cancellationToken);

    public async Task SaveConnectionSettingsAsync(
        SaveConnectionSettingsRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        AcknowledgedResponse response = await SendAsync<SaveConnectionSettingsRequest, AcknowledgedResponse>(
            SyncServiceCommands.SaveSettings,
            request,
            cancellationToken).ConfigureAwait(false);
        EnsureAcknowledged(response);
    }

    public Task<ConnectionCheckResult> CheckConnectionAsync(
        ConnectionCheckRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<ConnectionCheckRequest, ConnectionCheckResult>(
            SyncServiceCommands.CheckConnection,
            request,
            cancellationToken);
    }

    public Task<SynchronizeNowResult> SynchronizeNowAsync(
        SynchronizeNowRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<SynchronizeNowRequest, SynchronizeNowResult>(
            SyncServiceCommands.SynchronizeNow,
            request,
            cancellationToken);
    }

    public Task<ConflictListResponse> GetConflictsAsync(CancellationToken cancellationToken = default) =>
        SendAsync<EmptyRequest, ConflictListResponse>(
            SyncServiceCommands.ListConflicts,
            new EmptyRequest(),
            cancellationToken);

    public Task<ConflictResolutionResponse> ResolveConflictAsync(
        ConflictResolutionRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<ConflictResolutionRequest, ConflictResolutionResponse>(
            SyncServiceCommands.ResolveConflict,
            request,
            cancellationToken);
    }

    public async Task WriteAuditAsync(AuditEventRequest request, CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        AcknowledgedResponse response = await SendAsync<AuditEventRequest, AcknowledgedResponse>(
            SyncServiceCommands.WriteAudit,
            request,
            cancellationToken).ConfigureAwait(false);
        EnsureAcknowledged(response);
    }

    public Task<ReadEntityResponse> ReadEntityAsync(
        ReadEntityRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<ReadEntityRequest, ReadEntityResponse>(
            RepositoryCommands.Read,
            request,
            cancellationToken);
    }

    public Task<EntityListResponse> SearchEntitiesAsync(
        SearchEntitiesRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<SearchEntitiesRequest, EntityListResponse>(
            RepositoryCommands.Search,
            request,
            cancellationToken);
    }

    public Task<SaveEntityResponse> SaveEntityAsync(
        SaveEntityRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<SaveEntityRequest, SaveEntityResponse>(
            RepositoryCommands.Save,
            request,
            cancellationToken);
    }

    public Task<EntityListResponse> QueryEntitiesAsync(
        QueryEntitiesRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<QueryEntitiesRequest, EntityListResponse>(
            RepositoryCommands.Query,
            request,
            cancellationToken);
    }

    public Task<BackupResponse> CreateBackupAsync(
        BackupRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<BackupRequest, BackupResponse>(
            RepositoryCommands.Backup,
            request,
            cancellationToken);
    }

    public Task<RestoreResponse> RestoreBackupAsync(
        RestoreRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return SendAsync<RestoreRequest, RestoreResponse>(
            RepositoryCommands.Restore,
            request,
            cancellationToken);
    }

    private async Task<TResponse> SendAsync<TRequest, TResponse>(
        string command,
        TRequest request,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(request);
        cancellationToken.ThrowIfCancellationRequested();
        using CancellationTokenSource timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(_options.RequestTimeout);
        try
        {
            await using Stream connection = await _connectionFactory
                .ConnectAsync(_options, timeout.Token)
                .ConfigureAwait(false);
            await _serverIdentityValidator
                .ValidateAsync(connection, _options, timeout.Token)
                .ConfigureAwait(false);

            Guid requestId = Guid.NewGuid();
            LocalSession? session = command is SyncServiceCommands.Login or SyncServiceCommands.BootstrapAdministrator
                ? null
                : _currentSession;
            SyncServiceRequest envelope = new()
            {
                RequestId = requestId,
                ProtocolVersion = SyncServiceProtocol.Version,
                Command = command,
                Payload = JsonSerializer.SerializeToElement(request, JsonOptions),
                SessionId = session?.SessionId,
                SessionProof = session?.SessionProof
            };
            byte[] requestFrame = JsonSerializer.SerializeToUtf8Bytes(envelope, JsonOptions);
            await WriteFrameAsync(connection, requestFrame, timeout.Token).ConfigureAwait(false);
            byte[] responseFrame = await ReadFrameAsync(connection, timeout.Token).ConfigureAwait(false);
            SyncServiceResponse response = DeserializeResponse(responseFrame);
            ValidateResponse(response, requestId);
            if (!response.Success)
            {
                throw CreateServiceException(command, response.Error!.Code);
            }

            try
            {
                return response.Payload!.Value.Deserialize<TResponse>(JsonOptions)
                    ?? throw new InvalidDataException("The local synchronization service returned an empty command payload.");
            }
            catch (JsonException exception)
            {
                throw new InvalidDataException("The local synchronization service returned an invalid command payload.", exception);
            }
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"The local synchronization service command '{command}' exceeded its timeout.");
        }
    }

    private static SyncServiceResponse DeserializeResponse(byte[] responseFrame)
    {
        try
        {
            return JsonSerializer.Deserialize<SyncServiceResponse>(responseFrame, JsonOptions)
                ?? throw new InvalidDataException("The local synchronization service returned an empty response.");
        }
        catch (JsonException exception)
        {
            throw new InvalidDataException("The local synchronization service returned malformed JSON.", exception);
        }
    }

    private static void ValidateResponse(SyncServiceResponse response, Guid requestId)
    {
        if (response.RequestId != requestId || response.ProtocolVersion != SyncServiceProtocol.Version)
        {
            throw new InvalidDataException("The local synchronization service response does not match the request.");
        }

        if (response.Success)
        {
            if (response.Error is not null ||
                response.Payload is not JsonElement payload ||
                payload.ValueKind is JsonValueKind.Undefined or JsonValueKind.Null)
            {
                throw new InvalidDataException("The local synchronization service returned an ambiguous success response.");
            }

            return;
        }

        if (response.Payload is not null || response.Error is null || !IsValidErrorCode(response.Error.Code))
        {
            throw new InvalidDataException("The local synchronization service returned an invalid error response.");
        }
    }

    private static bool IsValidErrorCode(string? code) =>
        !string.IsNullOrWhiteSpace(code) &&
        code.Length <= 64 &&
        code.All(character => char.IsAsciiLetterOrDigit(character) || character is '_' or '-');

    private static Exception CreateServiceException(string command, string errorCode) => errorCode switch
    {
        "unauthorized" or "permission_denied" => new UnauthorizedAccessException(
            $"The local synchronization service denied '{command}'."),
        "not_found" => new KeyNotFoundException($"The requested entity was not found for '{command}'."),
        _ => new SyncServiceException(command, errorCode)
    };

    private async Task WriteFrameAsync(
        Stream connection,
        byte[] payload,
        CancellationToken cancellationToken)
    {
        if (payload.Length is < 1 || payload.Length > _options.MaxFrameBytes)
        {
            throw new InvalidDataException("The IPC request exceeds the configured frame limit.");
        }

        byte[] header = new byte[4];
        BinaryPrimitives.WriteInt32LittleEndian(header, payload.Length);
        await connection.WriteAsync(header, cancellationToken).ConfigureAwait(false);
        await connection.WriteAsync(payload, cancellationToken).ConfigureAwait(false);
        await connection.FlushAsync(cancellationToken).ConfigureAwait(false);
    }

    private async Task<byte[]> ReadFrameAsync(Stream connection, CancellationToken cancellationToken)
    {
        try
        {
            byte[] header = new byte[4];
            await connection.ReadExactlyAsync(header, cancellationToken).ConfigureAwait(false);
            int payloadLength = BinaryPrimitives.ReadInt32LittleEndian(header);
            if (payloadLength is < 1 || payloadLength > _options.MaxFrameBytes)
            {
                throw new InvalidDataException("The IPC response exceeds the configured frame limit.");
            }

            byte[] payload = new byte[payloadLength];
            await connection.ReadExactlyAsync(payload, cancellationToken).ConfigureAwait(false);
            return payload;
        }
        catch (EndOfStreamException exception)
        {
            throw new InvalidDataException("The IPC response ended before its frame was complete.", exception);
        }
    }

    private void SetCurrentSession(LocalSession session)
    {
        if (session.SessionId == Guid.Empty ||
            session.UserId == Guid.Empty ||
            string.IsNullOrWhiteSpace(session.SessionProof) ||
            string.IsNullOrWhiteSpace(session.DeviceId) ||
            string.IsNullOrWhiteSpace(session.SourceId) ||
            session.Permissions is null ||
            session.ExpiresAtUtc <= session.ManifestIssuedAtUtc)
        {
            throw new InvalidDataException("The local synchronization service returned an invalid local session.");
        }

        _currentSession = session;
    }

    private static void EnsureAcknowledged(AcknowledgedResponse response)
    {
        if (!response.Acknowledged)
        {
            throw new InvalidDataException("The local synchronization service did not acknowledge the command.");
        }
    }

    private static void ValidateOptions(SyncServiceClientOptions options)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(options.PipeName);
        ArgumentException.ThrowIfNullOrWhiteSpace(options.ServiceName);
        if (options.ConnectTimeout <= TimeSpan.Zero || options.RequestTimeout <= TimeSpan.Zero)
        {
            throw new ArgumentOutOfRangeException(nameof(options), "IPC timeouts must be positive.");
        }

        if (options.MaxFrameBytes is < 1 || options.MaxFrameBytes > SyncServiceProtocol.MaxFrameBytes)
        {
            throw new ArgumentOutOfRangeException(nameof(options), "The IPC frame limit is invalid.");
        }
    }
}

public sealed class NamedPipeConnectionFactory : ISyncServiceConnectionFactory
{
    public async Task<Stream> ConnectAsync(
        SyncServiceClientOptions options,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(options);
        NamedPipeClientStream connection = new(
            ".",
            options.PipeName,
            PipeDirection.InOut,
            PipeOptions.Asynchronous,
            TokenImpersonationLevel.Identification);
        try
        {
            await connection.ConnectAsync(
                checked((int)options.ConnectTimeout.TotalMilliseconds),
                cancellationToken).ConfigureAwait(false);
            connection.ReadMode = PipeTransmissionMode.Byte;
            return connection;
        }
        catch
        {
            await connection.DisposeAsync().ConfigureAwait(false);
            throw;
        }
    }
}

public sealed class WindowsServiceIdentityValidator : ISyncServiceServerIdentityValidator
{
    public Task ValidateAsync(
        Stream connection,
        SyncServiceClientOptions options,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(connection);
        ArgumentNullException.ThrowIfNull(options);
        cancellationToken.ThrowIfCancellationRequested();
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("Windows service identity validation requires Windows.");
        }

        if (connection is not NamedPipeClientStream pipe)
        {
            throw new ArgumentException("Windows service identity validation requires a named pipe connection.", nameof(connection));
        }

        uint serverProcessId = WindowsServiceNativeMethods.GetNamedPipeServerProcessId(pipe.SafePipeHandle);
        uint serviceProcessId = WindowsServiceNativeMethods.GetServiceProcessId(options.ServiceName);
        if (serverProcessId == 0 || serverProcessId != serviceProcessId)
        {
            throw new UnauthorizedAccessException("The named pipe server is not the configured Windows service process.");
        }

        return Task.CompletedTask;
    }
}

internal static class WindowsServiceNativeMethods
{
    private const uint ScManagerConnect = 0x0001;
    private const uint ServiceQueryStatus = 0x0004;
    private const int ScStatusProcessInfo = 0;

    public static uint GetNamedPipeServerProcessId(SafePipeHandle pipeHandle)
    {
        if (!GetNamedPipeServerProcessIdNative(pipeHandle, out uint processId))
        {
            throw new Win32Exception(Marshal.GetLastWin32Error(), "The named pipe server process could not be identified.");
        }

        return processId;
    }

    public static uint GetServiceProcessId(string serviceName)
    {
        using SafeServiceHandle manager = OpenSCManager(null, null, ScManagerConnect);
        if (manager.IsInvalid)
        {
            throw new Win32Exception(Marshal.GetLastWin32Error(), "The Windows service manager could not be opened.");
        }

        using SafeServiceHandle service = OpenService(manager, serviceName, ServiceQueryStatus);
        if (service.IsInvalid)
        {
            throw new Win32Exception(Marshal.GetLastWin32Error(), "The configured Windows service could not be opened.");
        }

        int bufferSize = Marshal.SizeOf<ServiceStatusProcess>();
        IntPtr buffer = Marshal.AllocHGlobal(bufferSize);
        try
        {
            if (!QueryServiceStatusEx(service, ScStatusProcessInfo, buffer, bufferSize, out _))
            {
                throw new Win32Exception(Marshal.GetLastWin32Error(), "The configured Windows service status could not be read.");
            }

            ServiceStatusProcess status = Marshal.PtrToStructure<ServiceStatusProcess>(buffer);
            return status.ProcessId;
        }
        finally
        {
            Marshal.FreeHGlobal(buffer);
        }
    }

    [DllImport("kernel32.dll", EntryPoint = "GetNamedPipeServerProcessId", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool GetNamedPipeServerProcessIdNative(SafePipeHandle pipeHandle, out uint processId);

    [DllImport("advapi32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern SafeServiceHandle OpenSCManager(string? machineName, string? databaseName, uint desiredAccess);

    [DllImport("advapi32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern SafeServiceHandle OpenService(SafeServiceHandle manager, string serviceName, uint desiredAccess);

    [DllImport("advapi32.dll", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool QueryServiceStatusEx(
        SafeServiceHandle service,
        int infoLevel,
        IntPtr buffer,
        int bufferSize,
        out int bytesNeeded);

    [DllImport("advapi32.dll", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool CloseServiceHandle(IntPtr serviceHandle);

    [StructLayout(LayoutKind.Sequential)]
    private struct ServiceStatusProcess
    {
        public uint ServiceType;
        public uint CurrentState;
        public uint ControlsAccepted;
        public uint Win32ExitCode;
        public uint ServiceSpecificExitCode;
        public uint CheckPoint;
        public uint WaitHint;
        public uint ProcessId;
        public uint ServiceFlags;
    }

    private sealed class SafeServiceHandle : SafeHandleZeroOrMinusOneIsInvalid
    {
        public SafeServiceHandle()
            : base(true)
        {
        }

        protected override bool ReleaseHandle() => CloseServiceHandle(handle);
    }
}
