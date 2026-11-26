using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Repositories;
using Alhayah.LocalData.Services;
using Alhayah.SyncService.Configuration;
using Alhayah.SyncService.Conflicts;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Storage;
using Alhayah.SyncService.Synchronization;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Ipc;

public sealed class SyncServiceHostOptions
{
    public required string LaravelSourceId { get; init; }

    public required string LocalServerSourceId { get; init; }

    public void Validate()
    {
        _ = new SyncSourceId(LaravelSourceId);
        _ = new SyncSourceId(LocalServerSourceId);
        if (!LaravelSourceId.StartsWith("laravel:", StringComparison.Ordinal) ||
            !LocalServerSourceId.StartsWith("local:", StringComparison.Ordinal))
        {
            throw new InvalidOperationException("The configured source identities have invalid owner prefixes.");
        }
    }
}

public sealed class LocalStoreCommandHandlers : ILocalStoreCommandHandler
{
    private readonly ISyncServiceStore _store;
    private readonly SyncSettingsStore _settings;
    private readonly ISecretStore _secrets;
    private readonly LocalAuthenticationService _authentication;
    private readonly IAdministratorBootstrapService _bootstrap;
    private readonly SyncChannelRegistry _channels;
    private readonly SyncServiceHostOptions _hostOptions;
    private readonly TimeProvider _timeProvider;
    private readonly ILogger<LocalStoreCommandHandlers> _logger;
    private readonly ConflictQueue _conflictQueue;

    public LocalStoreCommandHandlers(
        ISyncServiceStore store,
        SyncSettingsStore settings,
        ISecretStore secrets,
        LocalAuthenticationService authentication,
        IAdministratorBootstrapService bootstrap,
        SyncChannelRegistry channels,
        SyncServiceHostOptions hostOptions,
        TimeProvider timeProvider,
        ILogger<LocalStoreCommandHandlers> logger,
        ConflictQueue? conflictQueue = null)
    {
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _settings = settings ?? throw new ArgumentNullException(nameof(settings));
        _secrets = secrets ?? throw new ArgumentNullException(nameof(secrets));
        _authentication = authentication ?? throw new ArgumentNullException(nameof(authentication));
        _bootstrap = bootstrap ?? throw new ArgumentNullException(nameof(bootstrap));
        _channels = channels ?? throw new ArgumentNullException(nameof(channels));
        _hostOptions = hostOptions ?? throw new ArgumentNullException(nameof(hostOptions));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        _conflictQueue = conflictQueue ?? new ConflictQueue(timeProvider);
        _hostOptions.Validate();
    }

    public async Task<object> HandleAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(request);
        try
        {
            return request.Command switch
            {
                SyncServiceCommands.Login => await LoginAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.BootstrapAdministrator => await _bootstrap.BootstrapAsync(
                    Deserialize<BootstrapAdministratorRequest>(request),
                    cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.Logout => await LogoutAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.GetSettings => await GetSettingsAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.SaveSettings => await SaveSettingsAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.CheckConnection => await CheckConnectionAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.SynchronizeNow => await SynchronizeNowAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.ListConflicts => await ListConflictsAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.ResolveConflict => await ResolveConflictAsync(request, cancellationToken).ConfigureAwait(false),
                SyncServiceCommands.WriteAudit => await WriteAuditAsync(request, cancellationToken).ConfigureAwait(false),
                RepositoryCommands.Read => await ReadAsync(request, cancellationToken).ConfigureAwait(false),
                RepositoryCommands.Search => await SearchAsync(request, cancellationToken).ConfigureAwait(false),
                RepositoryCommands.Save => await SaveAsync(request, cancellationToken).ConfigureAwait(false),
                RepositoryCommands.Query => await QueryAsync(request, cancellationToken).ConfigureAwait(false),
                RepositoryCommands.Backup => await BackupAsync(request, cancellationToken).ConfigureAwait(false),
                RepositoryCommands.Restore => await RestoreAsync(request, cancellationToken).ConfigureAwait(false),
                _ => throw new SyncServiceException("unknown_command")
            };
        }
        catch (Exception exception) when (exception is JsonException or ArgumentException)
        {
            throw new SyncServiceException("invalid_request");
        }
        catch (DbUpdateConcurrencyException)
        {
            throw new SyncServiceException("version_mismatch");
        }
        catch (KeyNotFoundException)
        {
            throw new SyncServiceException("not_found");
        }
        catch (InvalidDataException)
        {
            throw new SyncServiceException("invalid_data");
        }
        catch (IOException)
        {
            throw new SyncServiceException("storage_unavailable");
        }
        catch (InvalidOperationException)
        {
            throw new SyncServiceException("operation_rejected");
        }
    }

    private async Task<LocalSession> LoginAsync(SyncServiceRequest request, CancellationToken cancellationToken)
    {
        LoginRequest login = Deserialize<LoginRequest>(request);
        LocalSession session = await _authentication.LoginAsync(login, cancellationToken).ConfigureAwait(false);
        await RecordAuditAsync(
            request,
            session.UserId,
            session.DeviceId,
            session.SourceId,
            SyncServiceCommands.Login,
            session.ManifestVersion,
            AuditResult.Committed,
            cancellationToken).ConfigureAwait(false);
        return session;
    }

    private async Task<AcknowledgedResponse> LogoutAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await _authentication.AuthenticateAsync(request, cancellationToken)
            .ConfigureAwait(false);
        await _authentication.LogoutAsync(request, cancellationToken).ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, "auth.logout", cancellationToken).ConfigureAwait(false);
        return Acknowledged();
    }

    private async Task<ConnectionSettingsData> GetSettingsAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.ManageSettings, cancellationToken)
            .ConfigureAwait(false);
        ConnectionSettingsData settings = await _settings.LoadAsync(cancellationToken).ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.ManageSettings, cancellationToken)
            .ConfigureAwait(false);
        return settings;
    }

    private async Task<AcknowledgedResponse> SaveSettingsAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.ManageSettings, cancellationToken)
            .ConfigureAwait(false);
        SaveConnectionSettingsRequest save = Deserialize<SaveConnectionSettingsRequest>(request);
        if (save.Secrets?.LaravelDeviceToken is not null)
        {
            StoreSecret(SecretTargets.LaravelDeviceToken(_hostOptions.LaravelSourceId), save.Secrets.LaravelDeviceToken);
        }

        if (save.Secrets?.LocalServerDeviceCredential is not null)
        {
            StoreSecret(
                SecretTargets.LocalServerDeviceCredential(_hostOptions.LocalServerSourceId),
                save.Secrets.LocalServerDeviceCredential);
        }

        await _settings.SaveAsync(save.Settings, cancellationToken).ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.ManageSettings, cancellationToken)
            .ConfigureAwait(false);
        _logger.LogInformation(new EventId(27101), "Connection settings were saved without a connection check.");
        return Acknowledged();
    }

    private async Task<ConnectionCheckResult> CheckConnectionAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.ManageSettings, cancellationToken)
            .ConfigureAwait(false);
        ConnectionCheckRequest check = Deserialize<ConnectionCheckRequest>(request);
        ConnectionCheckResult result = await _channels.CheckConnectionAsync(check.SourceId, cancellationToken)
            .ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.ManageSettings, cancellationToken)
            .ConfigureAwait(false);
        return result;
    }

    private async Task<SynchronizeNowResult> SynchronizeNowAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Synchronize, cancellationToken)
            .ConfigureAwait(false);
        SynchronizeNowRequest synchronize = Deserialize<SynchronizeNowRequest>(request);
        EnsureSource(authenticated, synchronize.SourceId);
        SynchronizeNowResult result = await _channels.SynchronizeNowAsync(synchronize.SourceId, cancellationToken)
            .ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.Synchronize, cancellationToken)
            .ConfigureAwait(false);
        return result;
    }

    private async Task<ReadEntityResponse> ReadAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Read, cancellationToken)
            .ConfigureAwait(false);
        ReadEntityRequest read = Deserialize<ReadEntityRequest>(request);
        EnsureSource(authenticated, read.SourceId);
        await using LocalStoreDbContext context = _store.CreateContext();
        CachedEntity? entity = await new LocalCacheRepository(context).GetAsync(
            read.SourceId,
            read.EntityType,
            read.ClientUuid,
            cancellationToken).ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.Read, cancellationToken).ConfigureAwait(false);
        return new ReadEntityResponse { Entity = entity is null ? null : Map(entity) };
    }

    private async Task<EntityListResponse> SearchAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Search, cancellationToken)
            .ConfigureAwait(false);
        SearchEntitiesRequest search = Deserialize<SearchEntitiesRequest>(request);
        EnsureSource(authenticated, search.SourceId);
        if (search.Limit is < 1 or > 200)
        {
            throw new ArgumentOutOfRangeException(nameof(search));
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        IReadOnlyList<CachedEntity> entities = await new LocalCacheRepository(context).SearchAsync(
            search.SourceId,
            search.EntityType,
            search.Query,
            cancellationToken).ConfigureAwait(false);
        RepositoryEntity[] items = entities.Take(search.Limit).Select(Map).ToArray();
        await RecordAuditAsync(request, authenticated, LocalPermissions.Search, cancellationToken).ConfigureAwait(false);
        return new EntityListResponse { Items = items, TotalCount = items.Length };
    }

    private async Task<EntityListResponse> QueryAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Query, cancellationToken)
            .ConfigureAwait(false);
        QueryEntitiesRequest query = Deserialize<QueryEntitiesRequest>(request);
        EnsureSource(authenticated, query.SourceId);
        if (query.Skip < 0 || query.Take is < 1 or > 200)
        {
            throw new ArgumentOutOfRangeException(nameof(query));
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        IQueryable<CachedEntity> entities = context.CachedEntities;
        if (query.IncludeDeleted)
        {
            entities = entities.IgnoreQueryFilters();
        }

        IQueryable<CachedEntity> filtered = entities.Where(item =>
            item.SourceId == query.SourceId && item.EntityType == query.EntityType);
        int totalCount = await filtered.CountAsync(cancellationToken).ConfigureAwait(false);
        RepositoryEntity[] items = await filtered
            .OrderBy(item => item.CanonicalId)
            .ThenBy(item => item.ClientUuid)
            .Skip(query.Skip)
            .Take(query.Take)
            .Select(item => new RepositoryEntity
            {
                ClientUuid = item.ClientUuid,
                SourceId = item.SourceId,
                EntityType = item.EntityType,
                CanonicalId = item.CanonicalId,
                PayloadJson = item.PayloadJson,
                BaseVersion = item.BaseVersion,
                RowVersion = item.RowVersion,
                CreatedAtUtc = item.CreatedAtUtc,
                UpdatedAtUtc = item.UpdatedAtUtc,
                DeletedAtUtc = item.DeletedAtUtc,
                DeletedByDeviceId = item.DeletedByDeviceId
            })
            .ToArrayAsync(cancellationToken)
            .ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.Query, cancellationToken).ConfigureAwait(false);
        return new EntityListResponse { Items = items, TotalCount = totalCount };
    }

    private async Task<SaveEntityResponse> SaveAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Save, cancellationToken)
            .ConfigureAwait(false);
        SaveEntityRequest save = Deserialize<SaveEntityRequest>(request);
        EnsureSource(authenticated, save.Envelope.SourceId.Value);
        if (!string.Equals(authenticated.Session.DeviceId, save.Envelope.DeviceId, StringComparison.Ordinal))
        {
            throw new SyncServiceException("unauthorized");
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        UnitOfWork unitOfWork = new(context);
        LocalAuditContext audit = new(
            authenticated.User.Id,
            LocalPermissions.Save,
            authenticated.Manifest.ManifestVersion);
        CachedEntity entity = save.Envelope.OperationType switch
        {
            SyncOperationType.Create => await unitOfWork.CreateAsync(
                save.Envelope,
                save.PayloadJson ?? throw new ArgumentException("A create payload is required."),
                save.SearchText ?? throw new ArgumentException("Search text is required."),
                audit,
                cancellationToken).ConfigureAwait(false),
            SyncOperationType.Update => await unitOfWork.PatchAsync(
                save.Envelope,
                save.SearchText ?? throw new ArgumentException("Search text is required."),
                audit,
                cancellationToken).ConfigureAwait(false),
            SyncOperationType.Delete => await unitOfWork.SoftDeleteAsync(save.Envelope, audit, cancellationToken)
                .ConfigureAwait(false),
            _ => throw new ArgumentException("The synchronization operation is unsupported.")
        };
        return new SaveEntityResponse { Entity = Map(entity) };
    }

    private async Task<BackupResponse> BackupAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Backup, cancellationToken)
            .ConfigureAwait(false);
        BackupRequest backup = Deserialize<BackupRequest>(request);
        LocalStoreBackup result = await _store.CreateBackupAsync(backup.DestinationDirectory, cancellationToken)
            .ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.Backup, cancellationToken).ConfigureAwait(false);
        return new BackupResponse
        {
            DirectoryPath = result.DirectoryPath,
            DatabasePath = result.DatabasePath,
            ManifestPath = result.ManifestPath
        };
    }

    private async Task<RestoreResponse> RestoreAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.Restore, cancellationToken)
            .ConfigureAwait(false);
        RestoreRequest restore = Deserialize<RestoreRequest>(request);
        await _store.RestoreBackupAsync(
            restore.BackupDirectory,
            restore.DestinationDatabasePath,
            restore.DestinationAttachmentDirectory,
            cancellationToken).ConfigureAwait(false);
        await RecordAuditAsync(request, authenticated, LocalPermissions.Restore, cancellationToken).ConfigureAwait(false);
        return new RestoreResponse
        {
            DestinationDatabasePath = restore.DestinationDatabasePath,
            DestinationAttachmentDirectory = restore.DestinationAttachmentDirectory
        };
    }

    private async Task<ConflictListResponse> ListConflictsAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.ResolveConflicts, cancellationToken)
            .ConfigureAwait(false);
        _ = Deserialize<EmptyRequest>(request);
        await using LocalStoreDbContext context = _store.CreateContext();
        ConflictReviewData[] items = [.. await _conflictQueue.ListAsync(
            context, authenticated.Manifest.SourceId, cancellationToken).ConfigureAwait(false)];
        await RecordAuditAsync(request, authenticated, LocalPermissions.ResolveConflicts, cancellationToken)
            .ConfigureAwait(false);
        return new ConflictListResponse { Items = items };
    }

    private async Task<ConflictResolutionResponse> ResolveConflictAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.ResolveConflicts, cancellationToken)
            .ConfigureAwait(false);
        ConflictResolutionRequest resolution = Deserialize<ConflictResolutionRequest>(request);
        await using LocalStoreDbContext context = _store.CreateContext();
        _ = await context.SyncConflicts.AsNoTracking().SingleOrDefaultAsync(
            item => item.Id == resolution.ConflictId && item.SourceId == authenticated.Manifest.SourceId,
            cancellationToken).ConfigureAwait(false)
            ?? throw new KeyNotFoundException();
        ConflictResolutionResponse response = await _conflictQueue.ResolveAsync(
            context,
            resolution,
            authenticated.User.Id,
            authenticated.Session.DeviceId,
            authenticated.Session.ManifestVersion,
            cancellationToken).ConfigureAwait(false);
        _logger.LogInformation(new EventId(30102), "A manager conflict decision was saved locally.");
        return response;
    }

    private async Task<AcknowledgedResponse> WriteAuditAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken)
    {
        AuthenticatedSession authenticated = await AuthorizeAsync(request, LocalPermissions.WriteAudit, cancellationToken)
            .ConfigureAwait(false);
        AuditEventRequest audit = Deserialize<AuditEventRequest>(request);
        EnsureSource(authenticated, audit.SourceId);
        if (!string.Equals(authenticated.Session.DeviceId, audit.DeviceId, StringComparison.Ordinal) ||
            (audit.OfflineUserId is not null && audit.OfflineUserId != authenticated.User.Id) ||
            !authenticated.HasPermission(audit.PermissionUsed) ||
            audit.OperationId == Guid.Empty ||
            !IsSha256(audit.PayloadSha256))
        {
            throw new SyncServiceException("permission_denied");
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        context.AuditEntries.Add(new AuditEntry
        {
            OperationId = audit.OperationId,
            OfflineUserId = audit.OfflineUserId,
            DeviceId = audit.DeviceId,
            SourceId = audit.SourceId,
            PermissionUsed = audit.PermissionUsed,
            ManifestVersion = audit.ManifestVersion,
            OccurredAtUtc = audit.OccurredAtUtc,
            PayloadSha256 = audit.PayloadSha256,
            Result = audit.Result.ToString()
        });
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        return Acknowledged();
    }

    private Task<AuthenticatedSession> AuthorizeAsync(
        SyncServiceRequest request,
        string permission,
        CancellationToken cancellationToken) =>
        _authentication.AuthorizeAsync(request, permission, cancellationToken);

    private async Task RecordAuditAsync(
        SyncServiceRequest request,
        AuthenticatedSession authenticated,
        string permission,
        CancellationToken cancellationToken) =>
        await RecordAuditAsync(
            request,
            authenticated.User.Id,
            authenticated.Session.DeviceId,
            authenticated.Manifest.SourceId,
            permission,
            authenticated.Manifest.ManifestVersion,
            AuditResult.Committed,
            cancellationToken).ConfigureAwait(false);

    private async Task RecordAuditAsync(
        SyncServiceRequest request,
        Guid? userId,
        string deviceId,
        string sourceId,
        string permission,
        long manifestVersion,
        AuditResult result,
        CancellationToken cancellationToken)
    {
        byte[] payload = Encoding.UTF8.GetBytes(request.Payload.GetRawText());
        string payloadHash;
        try
        {
            payloadHash = Convert.ToHexString(SHA256.HashData(payload));
        }
        finally
        {
            CryptographicOperations.ZeroMemory(payload);
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        context.AuditEntries.Add(new AuditEntry
        {
            OperationId = request.RequestId,
            OfflineUserId = userId,
            DeviceId = deviceId,
            SourceId = sourceId,
            PermissionUsed = permission,
            ManifestVersion = manifestVersion,
            OccurredAtUtc = _timeProvider.GetUtcNow(),
            PayloadSha256 = payloadHash,
            Result = result.ToString()
        });
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }

    private void StoreSecret(string target, string value)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(value);
        _secrets.SetUtf8(target, value);
    }

    private static void EnsureSource(AuthenticatedSession authenticated, string sourceId)
    {
        string validated = new SyncSourceId(sourceId).Value;
        if (!string.Equals(authenticated.Manifest.SourceId, validated, StringComparison.Ordinal))
        {
            throw new SyncServiceException("unauthorized");
        }
    }

    private static T Deserialize<T>(SyncServiceRequest request) =>
        request.Payload.Deserialize<T>(SyncServiceJson.Options)
        ?? throw new JsonException("The IPC command payload is empty.");

    private static RepositoryEntity Map(CachedEntity entity) => new()
    {
        ClientUuid = entity.ClientUuid,
        SourceId = entity.SourceId,
        EntityType = entity.EntityType,
        CanonicalId = entity.CanonicalId,
        PayloadJson = entity.PayloadJson,
        BaseVersion = entity.BaseVersion,
        RowVersion = entity.RowVersion,
        CreatedAtUtc = entity.CreatedAtUtc,
        UpdatedAtUtc = entity.UpdatedAtUtc,
        DeletedAtUtc = entity.DeletedAtUtc,
        DeletedByDeviceId = entity.DeletedByDeviceId
    };

    private static AcknowledgedResponse Acknowledged() => new() { Acknowledged = true };

    private static bool IsSha256(string value) =>
        value.Length == 64 && value.All(Uri.IsHexDigit);
}
