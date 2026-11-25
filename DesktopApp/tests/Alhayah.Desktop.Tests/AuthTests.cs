using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class AuthTests
{
    [Fact]
    public async Task Login_WithValidCredentials_SendsPasswordOverIpcAndReturnsSession()
    {
        FakeSyncServiceHost host = new() { EnforcePermissions = true };
        LoginRequest? received = null;
        host.Handler = (request, _) =>
        {
            received = request.Payload.Deserialize<LoginRequest>();
            return Task.FromResult(host.Ok(request, IpcTestData.NewSession(
                Alhayah.Contracts.Ipc.LocalPermissions.Read,
                Alhayah.Contracts.Ipc.LocalPermissions.WriteAudit)));
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);
        LocalAuthService authentication = new(client);

        LocalSession session = await authentication.LoginAsync(IpcTestData.NewLoginRequest());

        Assert.NotNull(received);
        Assert.Equal("synthetic-password-not-a-hash", received.Password);
        Assert.Equal(session.SessionProof, authentication.CurrentSession?.SessionProof);
        Assert.True(authentication.HasPermission(Alhayah.Contracts.Ipc.LocalPermissions.Read));
        Assert.False(authentication.CanManageSettings);
        Assert.False(authentication.CanResolveConflicts);
    }

    [Fact]
    public async Task BootstrapAdministrator_WithSingleUseNonce_SecondAttemptIsRejected()
    {
        FakeSyncServiceHost host = new();
        bool nonceConsumed = false;
        host.Handler = (request, _) =>
        {
            BootstrapAdministratorRequest bootstrap = request.Payload.Deserialize<BootstrapAdministratorRequest>()
                ?? throw new InvalidDataException();
            if (nonceConsumed || bootstrap.Nonce != "synthetic-single-use-nonce")
            {
                return Task.FromResult(host.Error(request, "bootstrap_unavailable"));
            }

            nonceConsumed = true;
            return Task.FromResult(host.Ok(request, IpcTestData.NewSession(TestPermissions.All)));
        };
        SyncServiceClient client = IpcTestData.CreateClient(host);
        LocalAuthService authentication = new(client);
        BootstrapAdministratorRequest request = new()
        {
            UserName = "synthetic-first-admin",
            DisplayName = "Synthetic Administrator",
            Password = "synthetic-admin-password",
            ConfirmationPassword = "synthetic-admin-password",
            Nonce = "synthetic-single-use-nonce",
            DeviceId = "synthetic-device",
            SourceId = "laravel:synthetic"
        };

        LocalSession session = await authentication.BootstrapAdministratorAsync(request);

        Assert.True(authentication.CanManageSettings);
        Assert.True(authentication.CanResolveConflicts);
        Assert.Contains(Alhayah.Contracts.Ipc.LocalPermissions.Backup, session.Permissions);
        await Assert.ThrowsAsync<SyncServiceException>(() => authentication.BootstrapAdministratorAsync(request));
    }

    [Fact]
    public async Task NonAdministrator_SettingsAndConflictCommands_AreDeniedByFakeHost()
    {
        FakeSyncServiceHost host = new() { EnforcePermissions = true };
        host.Handler = (request, _) => Task.FromResult(request.Command switch
        {
            SyncServiceCommands.Login => host.Ok(request, IpcTestData.NewSession(
                Alhayah.Contracts.Ipc.LocalPermissions.Read,
                Alhayah.Contracts.Ipc.LocalPermissions.WriteAudit)),
            _ => host.Error(request, "unexpected_admin_command")
        });
        SyncServiceClient client = IpcTestData.CreateClient(host);
        LocalAuthService authentication = new(client);
        await authentication.LoginAsync(IpcTestData.NewLoginRequest());

        await Assert.ThrowsAsync<UnauthorizedAccessException>(() =>
            client.SaveConnectionSettingsAsync(IpcTestData.NewSettingsRequest()));
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => client.ResolveConflictAsync(new ConflictResolutionRequest
        {
            ConflictId = Guid.Parse("8d5e10bc-f7be-4c6f-a9ea-e80ea4f6a005"),
            ExpectedRowVersion = "synthetic-conflict-version",
            Decision = "Hold"
        }));

        Assert.False(authentication.CanManageSettings);
        Assert.False(authentication.CanResolveConflicts);
        Assert.Contains(SyncServiceCommands.SaveSettings, host.Commands);
        Assert.Contains(SyncServiceCommands.ResolveConflict, host.Commands);
    }

    [Fact]
    public async Task HasPermission_WithExpiredSession_ReturnsFalse()
    {
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) => Task.FromResult(host.Ok(request, IpcTestData.NewSession(
            Alhayah.Contracts.Ipc.LocalPermissions.ManageSettings) with
        {
            ExpiresAtUtc = DateTimeOffset.Parse("2026-11-25T12:00:00Z")
        }));
        SyncServiceClient client = IpcTestData.CreateClient(host);
        TestTimeProvider timeProvider = new(DateTimeOffset.Parse("2026-11-25T13:00:00Z"));
        LocalAuthService authentication = new(client, timeProvider);

        await authentication.LoginAsync(IpcTestData.NewLoginRequest());

        Assert.False(authentication.CanManageSettings);
        Assert.False(authentication.HasPermission(Alhayah.Contracts.Ipc.LocalPermissions.ManageSettings));
    }

    [Fact]
    public void DesktopSource_StaticInspection_DoesNotContainPasswordHashesSqliteOrHttpClient()
    {
        string projectDirectory = FindProjectDirectory();
        string desktopAppDirectory = Directory.GetParent(Directory.GetParent(projectDirectory)!.FullName)!.FullName;
        string desktopDirectory = Path.Combine(desktopAppDirectory, "src", "Alhayah.Desktop");
        string combinedSource = string.Join(
            Environment.NewLine,
            Directory.EnumerateFiles(desktopDirectory, "*.cs", SearchOption.AllDirectories)
                .Where(path => !path.Contains($"{Path.DirectorySeparatorChar}obj{Path.DirectorySeparatorChar}", StringComparison.OrdinalIgnoreCase))
                .Where(path => !path.Contains($"{Path.DirectorySeparatorChar}bin{Path.DirectorySeparatorChar}", StringComparison.OrdinalIgnoreCase))
                .Select(File.ReadAllText));

        Assert.DoesNotContain("PasswordHash", combinedSource, StringComparison.Ordinal);
        Assert.DoesNotContain("Argon2", combinedSource, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("Microsoft.Data.Sqlite", combinedSource, StringComparison.Ordinal);
        Assert.DoesNotContain("SqliteConnection", combinedSource, StringComparison.Ordinal);
        Assert.DoesNotContain("Alhayah.LocalData", combinedSource, StringComparison.Ordinal);
        Assert.DoesNotContain("HttpClient", combinedSource, StringComparison.Ordinal);
    }

    private static string FindProjectDirectory()
    {
        DirectoryInfo? directory = new(AppContext.BaseDirectory);
        while (directory is not null)
        {
            if (File.Exists(Path.Combine(directory.FullName, "Alhayah.Desktop.Tests.csproj")))
            {
                return directory.FullName;
            }

            directory = directory.Parent;
        }

        throw new DirectoryNotFoundException("The Desktop test project directory was not found.");
    }

    private sealed class TestTimeProvider(DateTimeOffset utcNow) : TimeProvider
    {
        private readonly DateTimeOffset _utcNow = utcNow;

        public override DateTimeOffset GetUtcNow() => _utcNow;
    }
}
