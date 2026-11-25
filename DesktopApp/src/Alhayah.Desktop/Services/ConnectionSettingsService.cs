using System.Collections.Concurrent;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;

namespace Alhayah.Desktop.Services;

public interface IConnectionSettingsService
{
    Task<ConnectionSettingsData> GetSettingsAsync(CancellationToken cancellationToken = default);

    Task<IReadOnlyList<ConflictReviewData>> GetConflictsAsync(CancellationToken cancellationToken = default);

    Task SaveAsync(
        ConnectionSettingsData settings,
        ConnectionSecrets? secrets,
        CancellationToken cancellationToken = default);

    Task RestoreDefaultsAsync(CancellationToken cancellationToken = default);

    Task<ConnectionCheckResult> CheckConnectionAsync(
        string sourceId,
        CancellationToken cancellationToken = default);

    Task<SynchronizeNowResult> SynchronizeNowAsync(
        string sourceId,
        CancellationToken cancellationToken = default);

    DateTimeOffset? GetLastSynchronizedAtUtc(string sourceId);
}

public sealed class ConnectionSettingsService : IConnectionSettingsService
{
    public const string DefaultLaravelEndpoint = "https://localhost";
    public const string DefaultLocalServerHost = "localhost";
    public const int DefaultLocalServerPort = 8443;
    public const int DefaultRequestTimeoutSeconds = 30;

    private readonly ISyncServiceClient _client;
    private readonly ILocalAuthService _authentication;
    private readonly TimeProvider _timeProvider;
    private readonly ConcurrentDictionary<string, DateTimeOffset> _lastSynchronizedAtUtc =
        new(StringComparer.Ordinal);

    public ConnectionSettingsService(
        ISyncServiceClient client,
        ILocalAuthService authentication,
        TimeProvider? timeProvider = null)
    {
        _client = client ?? throw new ArgumentNullException(nameof(client));
        _authentication = authentication ?? throw new ArgumentNullException(nameof(authentication));
        _timeProvider = timeProvider ?? TimeProvider.System;
    }

    public async Task<ConnectionSettingsData> GetSettingsAsync(CancellationToken cancellationToken = default)
    {
        EnsurePermission(_authentication.CanManageSettings);
        ConnectionSettingsData settings = await _client
            .GetConnectionSettingsAsync(cancellationToken)
            .ConfigureAwait(false);
        ValidateSettings(settings);
        return settings;
    }

    public async Task<IReadOnlyList<ConflictReviewData>> GetConflictsAsync(
        CancellationToken cancellationToken = default)
    {
        EnsurePermission(_authentication.CanResolveConflicts);
        ConflictListResponse response = await _client
            .GetConflictsAsync(cancellationToken)
            .ConfigureAwait(false);
        return response.Items;
    }

    public Task SaveAsync(
        ConnectionSettingsData settings,
        ConnectionSecrets? secrets,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(settings);
        EnsurePermission(_authentication.CanManageSettings);
        ValidateSettings(settings);
        SaveConnectionSettingsRequest request = new()
        {
            Settings = settings,
            Secrets = HasSecrets(secrets) ? secrets : null
        };
        return _client.SaveConnectionSettingsAsync(request, cancellationToken);
    }

    public async Task RestoreDefaultsAsync(CancellationToken cancellationToken = default)
    {
        EnsurePermission(_authentication.CanManageSettings);
        ConnectionSettingsData current = await _client
            .GetConnectionSettingsAsync(cancellationToken)
            .ConfigureAwait(false);
        ConnectionSettingsData restored = current with
        {
            ConnectionName = "الاتصال الافتراضي",
            LaravelEndpoint = DefaultLaravelEndpoint,
            LocalServerEndpoint = $"https://{DefaultLocalServerHost}:{DefaultLocalServerPort}",
            RequestTimeoutSeconds = DefaultRequestTimeoutSeconds,
            SynchronizationEnabled = true
        };
        ValidateSettings(restored);
        await _client.SaveConnectionSettingsAsync(
            new SaveConnectionSettingsRequest { Settings = restored },
            cancellationToken).ConfigureAwait(false);
    }

    public Task<ConnectionCheckResult> CheckConnectionAsync(
        string sourceId,
        CancellationToken cancellationToken = default)
    {
        EnsurePermission(_authentication.CanManageSettings);
        SyncSourceId validatedSourceId = new(sourceId);
        return _client.CheckConnectionAsync(
            new ConnectionCheckRequest { SourceId = validatedSourceId.Value },
            cancellationToken);
    }

    public async Task<SynchronizeNowResult> SynchronizeNowAsync(
        string sourceId,
        CancellationToken cancellationToken = default)
    {
        EnsurePermission(_authentication.HasPermission(LocalPermissions.Synchronize));
        SyncSourceId validatedSourceId = new(sourceId);
        SynchronizeNowResult result = await _client.SynchronizeNowAsync(
            new SynchronizeNowRequest { SourceId = validatedSourceId.Value },
            cancellationToken).ConfigureAwait(false);
        if (result.Status.Equals("Completed", StringComparison.OrdinalIgnoreCase) ||
            result.Status.Equals("Succeeded", StringComparison.OrdinalIgnoreCase))
        {
            _lastSynchronizedAtUtc[validatedSourceId.Value] = _timeProvider.GetUtcNow();
        }

        return result;
    }

    public DateTimeOffset? GetLastSynchronizedAtUtc(string sourceId)
    {
        SyncSourceId validatedSourceId = new(sourceId);
        return _lastSynchronizedAtUtc.TryGetValue(validatedSourceId.Value, out DateTimeOffset value)
            ? value
            : null;
    }

    public static bool IsValidSpkiSha256Pin(string? value)
    {
        if (value is null || value.Length != 64)
        {
            return false;
        }

        return value.All(character => character is >= '0' and <= '9' or >= 'a' and <= 'f' or >= 'A' and <= 'F');
    }

    private static bool HasSecrets(ConnectionSecrets? secrets) => secrets is not null &&
        (!string.IsNullOrWhiteSpace(secrets.LaravelDeviceToken) ||
         !string.IsNullOrWhiteSpace(secrets.LocalServerDeviceCredential));

    private static void EnsurePermission(bool hasPermission)
    {
        if (!hasPermission)
        {
            throw new UnauthorizedAccessException("The active offline session does not grant this administrative operation.");
        }
    }

    private static void ValidateSettings(ConnectionSettingsData settings)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(settings.ConnectionName);
        if (settings.ConnectionName.Length > 200)
        {
            throw new ArgumentException("The connection name is too long.", nameof(settings));
        }

        ValidateHttpsEndpoint(settings.LaravelEndpoint, allowPath: true);
        ValidateHttpsEndpoint(settings.LocalServerEndpoint, allowPath: false);
        ValidatePin(settings.LaravelSpkiSha256Pin);
        ValidatePin(settings.LocalServerSpkiSha256Pin);
        ValidatePreviousPin(settings.PreviousLaravelSpkiSha256Pin, settings.LaravelSpkiSha256Pin);
        ValidatePreviousPin(settings.PreviousLocalServerSpkiSha256Pin, settings.LocalServerSpkiSha256Pin);
        if (settings.RequestTimeoutSeconds is < 1 or > 300)
        {
            throw new ArgumentOutOfRangeException(nameof(settings), "The request timeout must be between 1 and 300 seconds.");
        }
    }

    private static void ValidateHttpsEndpoint(string value, bool allowPath)
    {
        if (!Uri.TryCreate(value, UriKind.Absolute, out Uri? endpoint) ||
            !string.Equals(endpoint.Scheme, Uri.UriSchemeHttps, StringComparison.Ordinal) ||
            string.IsNullOrWhiteSpace(endpoint.Host) ||
            !string.IsNullOrEmpty(endpoint.UserInfo) ||
            !string.IsNullOrEmpty(endpoint.Query) ||
            !string.IsNullOrEmpty(endpoint.Fragment) ||
            (!allowPath && endpoint.AbsolutePath != "/"))
        {
            throw new ArgumentException("Connection endpoints must be absolute HTTPS URIs without credentials, query, or fragment.");
        }
    }

    private static void ValidatePin(string value)
    {
        if (!IsValidSpkiSha256Pin(value))
        {
            throw new ArgumentException("An SPKI SHA-256 pin must contain exactly 64 hexadecimal characters.");
        }
    }

    private static void ValidatePreviousPin(string? previousPin, string currentPin)
    {
        if (string.IsNullOrWhiteSpace(previousPin))
        {
            return;
        }

        ValidatePin(previousPin);
        if (string.Equals(previousPin, currentPin, StringComparison.OrdinalIgnoreCase))
        {
            throw new ArgumentException("The previous SPKI pin must differ from the current SPKI pin.");
        }
    }
}
