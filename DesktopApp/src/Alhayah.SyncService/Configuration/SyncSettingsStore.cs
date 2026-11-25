using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Ipc;

namespace Alhayah.SyncService.Configuration;

public sealed class SyncSettingsStore
{
    private readonly string _filePath;
    private readonly SemaphoreSlim _writer = new(1, 1);

    public SyncSettingsStore(string filePath)
    {
        _filePath = LocalStorePathGuard.EnsureLocalPath(filePath);
    }

    public static string GetDefaultPath() => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
        "Alhayah",
        "SyncService",
        "connection-settings.json");

    public async Task<ConnectionSettingsData> LoadAsync(CancellationToken cancellationToken = default)
    {
        if (!File.Exists(_filePath))
        {
            return Defaults();
        }

        await using FileStream input = new(
            _filePath,
            FileMode.Open,
            FileAccess.Read,
            FileShare.Read,
            81920,
            FileOptions.Asynchronous | FileOptions.SequentialScan);
        ConnectionSettingsData settings = await JsonSerializer.DeserializeAsync<ConnectionSettingsData>(
            input,
            SyncServiceJson.Options,
            cancellationToken).ConfigureAwait(false)
            ?? throw new InvalidDataException("The connection settings file is empty.");
        Validate(settings);
        return settings;
    }

    public async Task SaveAsync(
        ConnectionSettingsData settings,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(settings);
        Validate(settings);
        await _writer.WaitAsync(cancellationToken).ConfigureAwait(false);
        string temporaryPath = _filePath + $".{Guid.NewGuid():N}.tmp";
        try
        {
            string? directory = Path.GetDirectoryName(_filePath);
            Directory.CreateDirectory(directory ?? throw new InvalidOperationException("A settings directory is required."));
            await using (FileStream output = new(
                temporaryPath,
                FileMode.CreateNew,
                FileAccess.Write,
                FileShare.None,
                81920,
                FileOptions.Asynchronous | FileOptions.WriteThrough))
            {
                await JsonSerializer.SerializeAsync(output, settings, SyncServiceJson.Options, cancellationToken)
                    .ConfigureAwait(false);
                await output.FlushAsync(cancellationToken).ConfigureAwait(false);
                output.Flush(flushToDisk: true);
            }

            if (File.Exists(_filePath))
            {
                File.Replace(temporaryPath, _filePath, destinationBackupFileName: null);
            }
            else
            {
                File.Move(temporaryPath, _filePath);
            }
        }
        finally
        {
            if (File.Exists(temporaryPath))
            {
                File.Delete(temporaryPath);
            }

            _writer.Release();
        }
    }

    public static ConnectionSettingsData Defaults() => new()
    {
        ConnectionName = "الاتصال الافتراضي",
        LaravelEndpoint = "https://localhost",
        LocalServerEndpoint = "https://localhost:8443",
        LaravelSpkiSha256Pin = new string('0', 64),
        PreviousLaravelSpkiSha256Pin = null,
        LocalServerSpkiSha256Pin = new string('0', 64),
        PreviousLocalServerSpkiSha256Pin = null,
        RequestTimeoutSeconds = 30,
        SynchronizationEnabled = true
    };

    private static void Validate(ConnectionSettingsData settings)
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
            throw new ArgumentOutOfRangeException(nameof(settings));
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
        if (value is null || value.Length != 64 || !value.All(Uri.IsHexDigit))
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
