using System.Buffers.Binary;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class IpcTests
{
    [Fact]
    public async Task LoginSearchAndSave_WithFakeHost_WorkWithoutNetwork()
    {
        FakeSyncServiceHost host = new() { EnforcePermissions = true };
        host.Handler = (request, _) => Task.FromResult(request.Command switch
        {
            SyncServiceCommands.Login => host.Ok(request, IpcTestData.NewSession(TestPermissions.All)),
            RepositoryCommands.Search => host.Ok(request, new EntityListResponse
            {
                Items = [IpcTestData.NewEntity()],
                TotalCount = 1
            }),
            RepositoryCommands.Save => host.Ok(request, new SaveEntityResponse { Entity = IpcTestData.NewEntity() }),
            _ => host.Error(request, "unknown_command")
        });
        SyncServiceClient client = IpcTestData.CreateClient(host);
        LocalAuthService authentication = new(client);

        await authentication.LoginAsync(IpcTestData.NewLoginRequest());
        EntityListResponse search = await client.SearchEntitiesAsync(new SearchEntitiesRequest
        {
            SourceId = "laravel:synthetic",
            EntityType = "synthetic.entity",
            Query = "synthetic-query",
            Limit = 20
        });
        SaveEntityResponse saved = await client.SaveEntityAsync(new SaveEntityRequest
        {
            Envelope = IpcTestData.NewEnvelope(),
            PayloadJson = "{\"displayName\":\"synthetic-value\"}",
            SearchText = "synthetic-value"
        });

        Assert.Single(search.Items);
        Assert.Equal(IpcTestData.NewEntity().ClientUuid, saved.Entity.ClientUuid);
        Assert.Equal(
            [SyncServiceCommands.Login, RepositoryCommands.Search, RepositoryCommands.Save],
            host.Commands);
    }

    [Fact]
    public async Task ReadEntity_WithUnauthorizedClient_IsRejectedBeforeCommandHandling()
    {
        FakeSyncServiceHost host = new()
        {
            IsClientAuthorized = false,
            EnforcePermissions = false
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);

        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => client.ReadEntityAsync(new ReadEntityRequest
        {
            SourceId = "laravel:synthetic",
            EntityType = "synthetic.entity",
            ClientUuid = IpcTestData.NewEntity().ClientUuid
        }));

        Assert.Empty(host.Commands);
    }

    [Fact]
    public async Task Login_WithUnauthorizedServer_DoesNotWriteCredentials()
    {
        FakeSyncServiceHost host = new();
        FakeHostConnectionFactory factory = new(host);
        FakeServerIdentityValidator validator = new() { IsAuthorized = false };
        SyncServiceClient client = IpcTestData.CreateClient(host, factory, validator);

        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => client.LoginAsync(IpcTestData.NewLoginRequest()));

        Assert.Empty(host.Commands);
        Assert.NotNull(factory.LastConnection);
        Assert.Equal(0, factory.LastConnection.BytesWritten);
    }

    [Theory]
    [InlineData(-1)]
    [InlineData(0)]
    [InlineData(SyncServiceProtocol.MaxFrameBytes + 1)]
    public async Task ReadEntity_WithInvalidFrameLength_IsRejected(int frameLength)
    {
        FakeSyncServiceHost host = new()
        {
            EnforcePermissions = false,
            RawFrameFactory = _ => IpcTestFrame.LengthOnly(frameLength)
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);

        await Assert.ThrowsAsync<InvalidDataException>(() => client.ReadEntityAsync(IpcTestData.NewReadRequest()));
    }

    [Fact]
    public async Task ReadEntity_WithMalformedResponse_IsRejected()
    {
        FakeSyncServiceHost host = new()
        {
            EnforcePermissions = false,
            RawFrameFactory = _ => IpcTestFrame.Utf8Json("{}")
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);

        await Assert.ThrowsAsync<InvalidDataException>(() => client.ReadEntityAsync(IpcTestData.NewReadRequest()));
    }

    [Fact]
    public async Task ReadEntity_WithMismatchedRequestId_IsRejected()
    {
        FakeSyncServiceHost host = new()
        {
            EnforcePermissions = false,
            RawFrameFactory = request => IpcTestFrame.Message(new SyncServiceResponse
            {
                RequestId = request.RequestId == Guid.Empty
                    ? Guid.Parse("7e0a0bd0-3119-49ed-b23c-48c82f071009")
                    : Guid.Empty,
                ProtocolVersion = SyncServiceProtocol.Version,
                Success = true,
                Payload = JsonSerializer.SerializeToElement(new ReadEntityResponse { Entity = null })
            })
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);

        await Assert.ThrowsAsync<InvalidDataException>(() => client.ReadEntityAsync(IpcTestData.NewReadRequest()));
    }

    [Fact]
    public async Task ReadEntity_WithWrongProtocolVersion_IsRejected()
    {
        FakeSyncServiceHost host = new()
        {
            EnforcePermissions = false,
            RawFrameFactory = request => IpcTestFrame.Message(new SyncServiceResponse
            {
                RequestId = request.RequestId,
                ProtocolVersion = SyncServiceProtocol.Version + 1,
                Success = true,
                Payload = JsonSerializer.SerializeToElement(new ReadEntityResponse { Entity = null })
            })
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);

        await Assert.ThrowsAsync<InvalidDataException>(() => client.ReadEntityAsync(IpcTestData.NewReadRequest()));
    }

    [Fact]
    public async Task ReadEntity_WhenResponseExceedsTimeout_ThrowsTimeoutException()
    {
        FakeSyncServiceHost host = new()
        {
            EnforcePermissions = false,
            SuppressResponses = true
        };
        SyncServiceClient client = IpcTestData.CreateClient(host, requestTimeout: TimeSpan.FromMilliseconds(20));

        await Assert.ThrowsAsync<TimeoutException>(() => client.ReadEntityAsync(IpcTestData.NewReadRequest()));
    }

    [Fact]
    public async Task ReadEntity_WithCancelledToken_DoesNotConnect()
    {
        FakeSyncServiceHost host = new();
        FakeHostConnectionFactory factory = new(host);
        SyncServiceClient client = IpcTestData.CreateClient(host, factory);
        using CancellationTokenSource cancellation = new();
        await cancellation.CancelAsync();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() =>
            client.ReadEntityAsync(IpcTestData.NewReadRequest(), cancellation.Token));

        Assert.Equal(0, factory.ConnectCalls);
    }
}

internal static class IpcTestData
{
    private static readonly Guid UserId = Guid.Parse("f3c8de62-2740-4f58-a209-91ea5a0a6c01");
    private static readonly Guid SessionId = Guid.Parse("d544ce9d-1f02-46af-9eb2-65de16d2a002");
    private static readonly Guid EntityId = Guid.Parse("cb2e52c2-22f6-49de-a460-bf07f63db003");
    private static readonly Guid OperationId = Guid.Parse("a35199c0-96e0-438d-bf5d-931087b58004");

    public static LoginRequest NewLoginRequest() => new()
    {
        UserName = "synthetic-user",
        Password = "synthetic-password-not-a-hash",
        DeviceId = "synthetic-device",
        SourceId = "laravel:synthetic"
    };

    public static LocalSession NewSession(params string[] permissions) => new()
    {
        SessionId = SessionId,
        SessionProof = "synthetic-session-proof",
        UserId = UserId,
        DeviceId = "synthetic-device",
        SourceId = "laravel:synthetic",
        Permissions = permissions,
        ManifestVersion = 7,
        ManifestIssuedAtUtc = DateTimeOffset.Parse("2026-11-25T00:00:00Z"),
        ExpiresAtUtc = DateTimeOffset.Parse("2096-11-25T12:00:00Z")
    };

    public static SyncEnvelope NewEnvelope() => new()
    {
        OperationId = OperationId,
        SourceId = new SyncSourceId("laravel:synthetic"),
        DeviceId = "synthetic-device",
        Entity = new EntityDescriptor
        {
            EntityType = "synthetic.entity",
            ClientUuid = EntityId,
            CanonicalId = "synthetic-canonical-id",
            References = []
        },
        OperationType = SyncOperationType.Create,
        OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T01:00:00Z"),
        Patches = []
    };

    public static RepositoryEntity NewEntity() => new()
    {
        ClientUuid = EntityId,
        SourceId = "laravel:synthetic",
        EntityType = "synthetic.entity",
        CanonicalId = "synthetic-canonical-id",
        PayloadJson = "{\"displayName\":\"synthetic-value\"}",
        BaseVersion = "4",
        RowVersion = "synthetic-row-version",
        CreatedAtUtc = DateTimeOffset.Parse("2026-11-25T01:00:00Z"),
        UpdatedAtUtc = DateTimeOffset.Parse("2026-11-25T01:00:00Z")
    };

    public static ReadEntityRequest NewReadRequest() => new()
    {
        SourceId = "laravel:synthetic",
        EntityType = "synthetic.entity",
        ClientUuid = EntityId
    };

    public static SaveConnectionSettingsRequest NewSettingsRequest() => new()
    {
        Settings = new ConnectionSettingsData
        {
            ConnectionName = "synthetic-connection",
            LaravelEndpoint = "https://laravel.synthetic.invalid",
            LocalServerEndpoint = "https://local.synthetic.invalid",
            LaravelSpkiSha256Pin = Convert.ToHexString(new byte[32]),
            LocalServerSpkiSha256Pin = Convert.ToHexString(new byte[32]),
            RequestTimeoutSeconds = 30,
            SynchronizationEnabled = true
        }
    };

    public static SyncServiceClient CreateClient(
        FakeSyncServiceHost host,
        FakeHostConnectionFactory? factory = null,
        FakeServerIdentityValidator? validator = null,
        TimeSpan? requestTimeout = null)
    {
        SyncServiceClientOptions options = new()
        {
            PipeName = "synthetic-pipe",
            ServiceName = "SyntheticService",
            ConnectTimeout = TimeSpan.FromMilliseconds(100),
            RequestTimeout = requestTimeout ?? TimeSpan.FromSeconds(2),
            MaxFrameBytes = SyncServiceProtocol.MaxFrameBytes
        };
        return new SyncServiceClient(
            options,
            factory ?? new FakeHostConnectionFactory(host),
            validator ?? new FakeServerIdentityValidator());
    }
}

internal static class TestPermissions
{
    public static readonly string[] All =
    [
        Alhayah.Contracts.Ipc.LocalPermissions.Read,
        Alhayah.Contracts.Ipc.LocalPermissions.Search,
        Alhayah.Contracts.Ipc.LocalPermissions.Save,
        Alhayah.Contracts.Ipc.LocalPermissions.Query,
        Alhayah.Contracts.Ipc.LocalPermissions.Backup,
        Alhayah.Contracts.Ipc.LocalPermissions.Restore,
        Alhayah.Contracts.Ipc.LocalPermissions.ManageSettings,
        Alhayah.Contracts.Ipc.LocalPermissions.ResolveConflicts,
        Alhayah.Contracts.Ipc.LocalPermissions.Synchronize,
        Alhayah.Contracts.Ipc.LocalPermissions.WriteAudit
    ];
}

internal static class IpcTestFrame
{
    public static byte[] LengthOnly(int length)
    {
        byte[] frame = new byte[4];
        BinaryPrimitives.WriteInt32LittleEndian(frame, length);
        return frame;
    }

    public static byte[] Utf8Json(string json)
    {
        byte[] payload = Encoding.UTF8.GetBytes(json);
        byte[] frame = new byte[4 + payload.Length];
        BinaryPrimitives.WriteInt32LittleEndian(frame, payload.Length);
        payload.CopyTo(frame, 4);
        return frame;
    }

    public static byte[] Message<T>(T message) => Utf8Json(JsonSerializer.Serialize(message));
}

internal sealed class FakeServerIdentityValidator : ISyncServiceServerIdentityValidator
{
    public bool IsAuthorized { get; init; } = true;

    public Task ValidateAsync(
        Stream connection,
        SyncServiceClientOptions options,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        if (!IsAuthorized)
        {
            throw new UnauthorizedAccessException("The synthetic server identity was rejected.");
        }

        return Task.CompletedTask;
    }
}

internal sealed class FakeHostConnectionFactory(FakeSyncServiceHost host) : ISyncServiceConnectionFactory
{
    private readonly FakeSyncServiceHost _host = host;

    public int ConnectCalls { get; private set; }

    public FakeHostStream? LastConnection { get; private set; }

    public Task<Stream> ConnectAsync(SyncServiceClientOptions options, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        ConnectCalls++;
        LastConnection = new FakeHostStream(_host);
        return Task.FromResult<Stream>(LastConnection);
    }
}

internal sealed class FakeSyncServiceHost
{
    private readonly Dictionary<Guid, LocalSession> _sessions = [];

    public List<string> Commands { get; } = [];

    public bool IsClientAuthorized { get; init; } = true;

    public bool EnforcePermissions { get; init; }

    public bool SuppressResponses { get; init; }

    public Func<SyncServiceRequest, byte[]>? RawFrameFactory { get; init; }

    public Func<SyncServiceRequest, CancellationToken, Task<SyncServiceResponse>> Handler { get; set; } =
        (request, _) => Task.FromResult(new SyncServiceResponse
        {
            RequestId = request.RequestId,
            ProtocolVersion = SyncServiceProtocol.Version,
            Success = false,
            Error = new SyncServiceError { Code = "missing_handler" }
        });

    public SyncServiceResponse Ok<T>(SyncServiceRequest request, T payload) => new()
    {
        RequestId = request.RequestId,
        ProtocolVersion = SyncServiceProtocol.Version,
        Success = true,
        Payload = JsonSerializer.SerializeToElement(payload)
    };

    public SyncServiceResponse Error(SyncServiceRequest request, string code) => new()
    {
        RequestId = request.RequestId,
        ProtocolVersion = SyncServiceProtocol.Version,
        Success = false,
        Error = new SyncServiceError { Code = code }
    };

    public async Task<SyncServiceResponse> DispatchAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        Commands.Add(request.Command);
        if (EnforcePermissions && request.Command is not (SyncServiceCommands.Login or SyncServiceCommands.BootstrapAdministrator))
        {
            if (request.SessionId is not Guid sessionId ||
                string.IsNullOrEmpty(request.SessionProof) ||
                !_sessions.TryGetValue(sessionId, out LocalSession? session) ||
                !string.Equals(session.SessionProof, request.SessionProof, StringComparison.Ordinal))
            {
                return Error(request, "unauthorized");
            }

            string? permission = RequiredPermission(request.Command);
            if (permission is not null && !session.Permissions.Contains(permission, StringComparer.Ordinal))
            {
                return Error(request, "permission_denied");
            }
        }

        SyncServiceResponse response = await Handler(request, cancellationToken);
        if (response.Success &&
            request.Command is SyncServiceCommands.Login or SyncServiceCommands.BootstrapAdministrator &&
            response.Payload is JsonElement payload)
        {
            LocalSession session = payload.Deserialize<LocalSession>()
                ?? throw new InvalidDataException("The synthetic login response did not contain a session.");
            _sessions[session.SessionId] = session;
        }

        if (response.Success && request.Command == SyncServiceCommands.Logout && request.SessionId is Guid logoutId)
        {
            _sessions.Remove(logoutId);
        }

        return response;
    }

    private static string? RequiredPermission(string command) => command switch
    {
        RepositoryCommands.Read => Alhayah.Contracts.Ipc.LocalPermissions.Read,
        RepositoryCommands.Search => Alhayah.Contracts.Ipc.LocalPermissions.Search,
        RepositoryCommands.Save => Alhayah.Contracts.Ipc.LocalPermissions.Save,
        RepositoryCommands.Query => Alhayah.Contracts.Ipc.LocalPermissions.Query,
        RepositoryCommands.Backup => Alhayah.Contracts.Ipc.LocalPermissions.Backup,
        RepositoryCommands.Restore => Alhayah.Contracts.Ipc.LocalPermissions.Restore,
        SyncServiceCommands.GetSettings or SyncServiceCommands.SaveSettings or SyncServiceCommands.CheckConnection =>
            Alhayah.Contracts.Ipc.LocalPermissions.ManageSettings,
        SyncServiceCommands.SynchronizeNow => Alhayah.Contracts.Ipc.LocalPermissions.Synchronize,
        SyncServiceCommands.ListConflicts or SyncServiceCommands.ResolveConflict =>
            Alhayah.Contracts.Ipc.LocalPermissions.ResolveConflicts,
        SyncServiceCommands.WriteAudit => Alhayah.Contracts.Ipc.LocalPermissions.WriteAudit,
        _ => null
    };
}

internal sealed class FakeHostStream(FakeSyncServiceHost host) : Stream
{
    private readonly FakeSyncServiceHost _host = host;
    private readonly List<byte> _incoming = [];
    private readonly TaskCompletionSource _responseReady = new(TaskCreationOptions.RunContinuationsAsynchronously);
    private byte[]? _response;
    private int _responseOffset;

    public int BytesWritten { get; private set; }

    public override bool CanRead => true;

    public override bool CanSeek => false;

    public override bool CanWrite => true;

    public override long Length => throw new NotSupportedException();

    public override long Position
    {
        get => throw new NotSupportedException();
        set => throw new NotSupportedException();
    }

    public override async ValueTask WriteAsync(
        ReadOnlyMemory<byte> buffer,
        CancellationToken cancellationToken = default)
    {
        cancellationToken.ThrowIfCancellationRequested();
        if (!_host.IsClientAuthorized)
        {
            throw new UnauthorizedAccessException("The synthetic client identity was rejected by pipe ACL.");
        }

        BytesWritten += buffer.Length;
        _incoming.AddRange(buffer.ToArray());
        if (_incoming.Count < 4)
        {
            return;
        }

        int payloadLength = BinaryPrimitives.ReadInt32LittleEndian(CollectionsMarshal.AsSpan(_incoming)[..4]);
        int frameLength = checked(4 + payloadLength);
        if (_incoming.Count < frameLength)
        {
            return;
        }

        if (_incoming.Count != frameLength)
        {
            throw new InvalidDataException("The synthetic client sent more than one frame on a connection.");
        }

        SyncServiceRequest request = JsonSerializer.Deserialize<SyncServiceRequest>(
            CollectionsMarshal.AsSpan(_incoming).Slice(4, payloadLength)) ?? throw new InvalidDataException("The synthetic request was empty.");
        if (_host.SuppressResponses)
        {
            return;
        }

        if (_host.RawFrameFactory is not null)
        {
            _response = _host.RawFrameFactory(request);
        }
        else
        {
            SyncServiceResponse response = await _host.DispatchAsync(request, cancellationToken);
            _response = IpcTestFrame.Message(response);
        }

        _responseReady.TrySetResult();
    }

    public override async ValueTask<int> ReadAsync(
        Memory<byte> buffer,
        CancellationToken cancellationToken = default)
    {
        if (_host.SuppressResponses)
        {
            await Task.Delay(Timeout.InfiniteTimeSpan, cancellationToken);
        }

        await _responseReady.Task.WaitAsync(cancellationToken);
        int count = Math.Min(buffer.Length, _response!.Length - _responseOffset);
        _response.AsMemory(_responseOffset, count).CopyTo(buffer);
        _responseOffset += count;
        return count;
    }

    public override Task FlushAsync(CancellationToken cancellationToken) => Task.CompletedTask;

    public override void Flush()
    {
    }

    public override int Read(byte[] buffer, int offset, int count) => throw new NotSupportedException();

    public override long Seek(long offset, SeekOrigin origin) => throw new NotSupportedException();

    public override void SetLength(long value) => throw new NotSupportedException();

    public override void Write(byte[] buffer, int offset, int count) => throw new NotSupportedException();
}
