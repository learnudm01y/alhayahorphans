using System.Text.Json;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class DeviceLifecycleTests
{
    [Fact]
    public async Task CreateEnrollmentAsync_WithSyntheticCommand_PersistsOnlyEnrollmentCodeHash()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        Assert.Contains(
            "20261124000200_DeviceRegistry",
            await context.Database.GetAppliedMigrationsAsync());
        TestTimeProvider time = new();
        DeviceRegistryService service = CreateService(context, time);

        DeviceEnrollmentIssue issue = await service.CreateEnrollmentAsync(EnrollmentCommand());

        DeviceRegistration stored = await context.DeviceRegistrations.SingleAsync();
        string serialized = JsonSerializer.Serialize(new { stored.CredentialHash, stored.EnrollmentCodeHash });
        Assert.NotNull(stored.EnrollmentCodeHash);
        Assert.Null(stored.CredentialHash);
        Assert.Equal(time.GetUtcNow().AddMinutes(10), stored.EnrollmentCodeExpiresAtUtc);
        Assert.DoesNotContain(issue.EnrollmentCode, serialized, StringComparison.Ordinal);
    }

    [Fact]
    public async Task CompleteEnrollmentAsync_WithValidCode_PersistsOnlyCredentialHashAndConsumesCode()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService service = CreateService(context, new TestTimeProvider());
        DeviceEnrollmentIssue enrollment = await service.CreateEnrollmentAsync(EnrollmentCommand());

        DeviceCredentialIssue? credential = await service.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode));

        Assert.NotNull(credential);
        DeviceRegistration stored = await context.DeviceRegistrations.SingleAsync();
        string serialized = JsonSerializer.Serialize(new { stored.CredentialHash, stored.EnrollmentCodeHash });
        Assert.NotNull(stored.CredentialHash);
        Assert.Null(stored.EnrollmentCodeHash);
        Assert.Null(stored.EnrollmentCodeExpiresAtUtc);
        Assert.Equal(1, stored.CredentialVersion);
        Assert.DoesNotContain(credential.Credential, serialized, StringComparison.Ordinal);
        Assert.DoesNotContain(enrollment.EnrollmentCode, serialized, StringComparison.Ordinal);
    }

    [Fact]
    public async Task CompleteEnrollmentAsync_WithUsedCode_RejectsReplay()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService service = CreateService(context, new TestTimeProvider());
        DeviceEnrollmentIssue enrollment = await service.CreateEnrollmentAsync(EnrollmentCommand());
        DeviceEnrollmentRedemption redemption = new(enrollment.DeviceId, enrollment.EnrollmentCode);
        Assert.NotNull(await service.CompleteEnrollmentAsync(redemption));

        DeviceCredentialIssue? replay = await service.CompleteEnrollmentAsync(redemption);

        Assert.Null(replay);
        Assert.Equal(1, await context.DeviceRegistrations.Select(item => item.CredentialVersion).SingleAsync());
    }

    [Fact]
    public async Task CompleteEnrollmentAsync_AfterExpiry_DoesNotIssueCredential()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        TestTimeProvider time = new();
        DeviceRegistryService service = CreateService(context, time);
        DeviceEnrollmentIssue enrollment = await service.CreateEnrollmentAsync(EnrollmentCommand());
        time.Advance(TimeSpan.FromMinutes(11));

        DeviceCredentialIssue? credential = await service.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode));

        Assert.Null(credential);
        Assert.Null(await context.DeviceRegistrations.Select(item => item.CredentialHash).SingleAsync());
    }

    [Fact]
    public async Task AuthenticateAsync_WithValidCredential_UpdatesLastSeenAndIntersectsPermissions()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        TestTimeProvider time = new();
        DeviceRegistryService service = CreateService(context, time);
        DeviceEnrollmentIssue enrollment = await service.CreateEnrollmentAsync(new DeviceEnrollmentCommand(
            "synthetic-user-018",
            LocalAccess.Read | LocalAccess.Operations | LocalAccess.Admin,
            LocalAccess.Read));
        DeviceCredentialIssue credential = (await service.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode)))!;
        time.Advance(TimeSpan.FromMinutes(2));

        DeviceAuthenticationResult? authentication = await service.AuthenticateAsync(
            new DeviceAuthenticationRequest(enrollment.DeviceId, "synthetic-user-018", credential.Credential));

        Assert.NotNull(authentication);
        Assert.Equal(LocalAccess.Read, authentication.Permissions);
        Assert.Equal(time.GetUtcNow(), await context.DeviceRegistrations.Select(item => item.LastSeenAtUtc).SingleAsync());
    }

    [Fact]
    public async Task RotateCredentialAsync_WithActiveDevice_InvalidatesOldCredentialAndPreservesIdentity()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService service = CreateService(context, new TestTimeProvider());
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue original) = await CreateActiveDeviceAsync(service);

        DeviceCredentialIssue rotated = await service.RotateCredentialAsync(enrollment.DeviceId);

        Assert.Equal(enrollment.DeviceId, rotated.DeviceId);
        Assert.Equal(2, rotated.Version);
        Assert.Null(await AuthenticateAsync(service, enrollment.DeviceId, original.Credential));
        Assert.NotNull(await AuthenticateAsync(service, enrollment.DeviceId, rotated.Credential));
        DeviceRegistration stored = await context.DeviceRegistrations.SingleAsync();
        Assert.DoesNotContain(rotated.Credential, JsonSerializer.Serialize(new { stored.CredentialHash, stored.EnrollmentCodeHash }), StringComparison.Ordinal);
    }

    [Fact]
    public async Task RevokeAsync_WithActiveDevice_RejectsSubsequentAuthentication()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService service = CreateService(context, new TestTimeProvider());
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue credential) = await CreateActiveDeviceAsync(service);

        await service.RevokeAsync(enrollment.DeviceId);

        Assert.Null(await AuthenticateAsync(service, enrollment.DeviceId, credential.Credential));
        DeviceRegistration stored = await context.DeviceRegistrations.SingleAsync();
        Assert.NotNull(stored.RevokedAtUtc);
        Assert.Null(stored.CredentialHash);
        Assert.Null(stored.EnrollmentCodeHash);
    }

    [Fact]
    public async Task BeginReRegistrationAsync_WithRevokedDevice_IssuesNewCredentialForSameDeviceId()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService service = CreateService(context, new TestTimeProvider());
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue original) = await CreateActiveDeviceAsync(service);
        await service.RevokeAsync(enrollment.DeviceId);

        DeviceEnrollmentIssue reRegistration = await service.BeginReRegistrationAsync(enrollment.DeviceId);
        DeviceCredentialIssue? replacement = await service.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, reRegistration.EnrollmentCode));

        Assert.NotNull(replacement);
        Assert.Equal(enrollment.DeviceId, replacement.DeviceId);
        Assert.NotEqual(original.Credential, replacement.Credential);
        Assert.Null(await AuthenticateAsync(service, enrollment.DeviceId, original.Credential));
        Assert.NotNull(await AuthenticateAsync(service, enrollment.DeviceId, replacement.Credential));
    }

    [Fact]
    public async Task AuthenticateAsync_WithUnregisteredDeviceOrUser_ReturnsNull()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService service = CreateService(context, new TestTimeProvider());
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue credential) = await CreateActiveDeviceAsync(service);

        DeviceAuthenticationResult? unknownDevice = await service.AuthenticateAsync(
            new DeviceAuthenticationRequest("synthetic-unknown-device", "synthetic-user-018", credential.Credential));
        DeviceAuthenticationResult? unknownUser = await service.AuthenticateAsync(
            new DeviceAuthenticationRequest(enrollment.DeviceId, "synthetic-unknown-user", credential.Credential));

        Assert.Null(unknownDevice);
        Assert.Null(unknownUser);
    }

    private static DeviceRegistryService CreateService(LocalDbContext context, TimeProvider timeProvider) =>
        new(context, timeProvider, NullLogger<DeviceRegistryService>.Instance);

    private static DeviceEnrollmentCommand EnrollmentCommand() =>
        new("synthetic-user-018", LocalAccess.Read | LocalAccess.Operations, LocalAccess.Read);

    private static async Task<(DeviceEnrollmentIssue Enrollment, DeviceCredentialIssue Credential)> CreateActiveDeviceAsync(
        DeviceRegistryService service)
    {
        DeviceEnrollmentIssue enrollment = await service.CreateEnrollmentAsync(EnrollmentCommand());
        DeviceCredentialIssue credential = (await service.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode)))!;
        return (enrollment, credential);
    }

    private static Task<DeviceAuthenticationResult?> AuthenticateAsync(
        DeviceRegistryService service,
        string deviceId,
        string credential) =>
        service.AuthenticateAsync(new DeviceAuthenticationRequest(deviceId, "synthetic-user-018", credential));
}

internal sealed class TestTimeProvider : TimeProvider
{
    private DateTimeOffset _utcNow = new(2026, 11, 24, 12, 0, 0, TimeSpan.Zero);

    public override DateTimeOffset GetUtcNow() => _utcNow;

    public void Advance(TimeSpan duration) => _utcNow = _utcNow.Add(duration);
}
