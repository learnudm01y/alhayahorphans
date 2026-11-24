using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Authentication;
using Alhayah.LocalServer.Controllers;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Authorization;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Logging.Abstractions;
using Microsoft.Extensions.Options;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class SecurityTests
{
    [Fact]
    public void Validate_WithTrustedAndPinnedCertificate_ReturnsTrue()
    {
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.TrustedClientPin);

        bool valid = validator.Validate(certificates.TrustedClient);

        Assert.True(valid);
    }

    [Fact]
    public void Validate_WithPinnedButUntrustedCertificate_ReturnsFalse()
    {
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.UntrustedClientPin);

        bool valid = validator.Validate(certificates.UntrustedClient);

        Assert.False(valid);
    }

    [Fact]
    public void Validate_WithTrustedButMismatchedPin_ReturnsFalse()
    {
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.UntrustedClientPin);

        bool valid = validator.Validate(certificates.TrustedClient);

        Assert.False(valid);
    }

    [Fact]
    public async Task AuthenticateAsync_WithHttpRequest_ChallengeReturns401()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService registry = CreateRegistry(context);
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.TrustedClientPin);
        DefaultHttpContext httpContext = new();
        httpContext.Request.IsHttps = false;
        DeviceAuthHandler handler = await CreateHandlerAsync(httpContext, registry, validator);

        AuthenticateResult result = await handler.AuthenticateAsync();
        await handler.ChallengeAsync();

        Assert.False(result.Succeeded);
        Assert.Equal(StatusCodes.Status401Unauthorized, httpContext.Response.StatusCode);
    }

    [Fact]
    public async Task AuthenticateAsync_WithRejectedClientCertificate_ChallengeReturns401()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService registry = CreateRegistry(context);
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue credential) = await CreateActiveDeviceAsync(registry);
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.TrustedClientPin);
        DefaultHttpContext httpContext = CreateAuthenticatedContext(
            certificates.UntrustedClient,
            enrollment.DeviceId,
            "synthetic-user-018",
            credential.Credential);
        DeviceAuthHandler handler = await CreateHandlerAsync(httpContext, registry, validator);

        AuthenticateResult result = await handler.AuthenticateAsync();
        await handler.ChallengeAsync();

        Assert.False(result.Succeeded);
        Assert.Equal(StatusCodes.Status401Unauthorized, httpContext.Response.StatusCode);
    }

    [Fact]
    public async Task AuthenticateAsync_WithUnregisteredDevice_ChallengeReturns401()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService registry = CreateRegistry(context);
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.TrustedClientPin);
        DefaultHttpContext httpContext = CreateAuthenticatedContext(
            certificates.TrustedClient,
            "synthetic-unregistered-device",
            "synthetic-user-018",
            "synthetic-unregistered-credential");
        DeviceAuthHandler handler = await CreateHandlerAsync(httpContext, registry, validator);

        AuthenticateResult result = await handler.AuthenticateAsync();
        await handler.ChallengeAsync();

        Assert.False(result.Succeeded);
        Assert.Equal(StatusCodes.Status401Unauthorized, httpContext.Response.StatusCode);
    }

    [Fact]
    public async Task AuthorizeAsync_WithReadOnlyPrincipal_OperationsAndAdminReturn403()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService registry = CreateRegistry(context);
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue credential) = await CreateActiveDeviceAsync(registry);
        using CertificateFixture certificates = new();
        using DeviceCertificateValidator validator = CreateValidator(certificates, certificates.TrustedClientPin);
        DefaultHttpContext httpContext = CreateAuthenticatedContext(
            certificates.TrustedClient,
            enrollment.DeviceId,
            "synthetic-user-018",
            credential.Credential);
        DeviceAuthHandler handler = await CreateHandlerAsync(httpContext, registry, validator);
        AuthenticateResult authentication = await handler.AuthenticateAsync();
        Assert.True(authentication.Succeeded);
        ServiceCollection services = new();
        services.AddLogging();
        services.AddAuthorization(options =>
        {
            options.AddPolicy(DevicePolicies.Operations, policy => policy
                .RequireAuthenticatedUser()
                .RequireClaim(DeviceClaimTypes.Permission, LocalPermissionNames.Operations));
            options.AddPolicy(DevicePolicies.Admin, policy => policy
                .RequireAuthenticatedUser()
                .RequireClaim(DeviceClaimTypes.Permission, LocalPermissionNames.Admin));
        });
        await using ServiceProvider provider = services.BuildServiceProvider();
        IAuthorizationService authorization = provider.GetRequiredService<IAuthorizationService>();

        bool operationsAllowed = await authorization.AuthorizeAsync(
            authentication.Ticket!.Principal,
            DevicePolicies.Operations);
        bool adminAllowed = await authorization.AuthorizeAsync(authentication.Ticket.Principal, DevicePolicies.Admin);
        await handler.ForbidAsync();

        Assert.False(operationsAllowed);
        Assert.False(adminAllowed);
        Assert.Equal(StatusCodes.Status403Forbidden, httpContext.Response.StatusCode);
    }

    [Fact]
    public async Task HealthAndCapabilities_WithSyntheticSecrets_ReturnOnlyPublicCompatibilityMetadata()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        DeviceRegistryService registry = CreateRegistry(context);
        (DeviceEnrollmentIssue enrollment, DeviceCredentialIssue credential) = await CreateActiveDeviceAsync(registry);
        using CertificateFixture certificates = new();
        using LocalServerCertificateMaterials materials = new(certificates.Root, certificates.TrustedRoot);
        LocalServerSecurityOptions options = new()
        {
            SourceId = new SyncSourceId("local:synthetic-server"),
            ApiVersion = "1",
            HttpsPort = 8443,
            ServerCertificateThumbprint = "synthetic-server-thumbprint",
            TrustedRootCertificateThumbprint = "synthetic-root-thumbprint",
            AllowedClientSpkiPins = new[] { certificates.TrustedClientPin }
        };
        TestTimeProvider time = new();
        HealthController controller = new(options, materials, time);

        LocalServerHealthResponse health = controller.Health();
        LocalServerCapabilitiesResponse capabilities = controller.Capabilities();
        string json = JsonSerializer.Serialize(new object[] { health, capabilities });

        Assert.Equal("1", health.ApiVersion);
        Assert.Equal("local:synthetic-server", health.SourceId);
        Assert.Equal(time.GetUtcNow(), health.ServerTime);
        Assert.Equal(certificates.RootPin, health.Certificate.Sha256SpkiPin);
        Assert.DoesNotContain(credential.Credential, json, StringComparison.Ordinal);
        Assert.DoesNotContain(enrollment.EnrollmentCode, json, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-user-018", json, StringComparison.Ordinal);
        Assert.DoesNotContain("privateKey", json, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("recipient", json, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("identity_number", json, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public async Task RegistryLogs_WithSyntheticSecrets_ContainDeviceIdButNoCredentialCodeOrUserId()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        RecordingLogger<DeviceRegistryService> logger = new();
        DeviceRegistryService registry = new(context, new TestTimeProvider(), logger);
        DeviceEnrollmentIssue enrollment = await registry.CreateEnrollmentAsync(new DeviceEnrollmentCommand(
            "synthetic-sensitive-user",
            LocalAccess.Read,
            LocalAccess.Read));
        DeviceCredentialIssue credential = (await registry.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode)))!;

        await registry.AuthenticateAsync(new DeviceAuthenticationRequest(
            enrollment.DeviceId,
            "synthetic-sensitive-user",
            credential.Credential));
        string logs = string.Join(Environment.NewLine, logger.Messages);

        Assert.Contains(enrollment.DeviceId, logs, StringComparison.Ordinal);
        Assert.DoesNotContain(enrollment.EnrollmentCode, logs, StringComparison.Ordinal);
        Assert.DoesNotContain(credential.Credential, logs, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-sensitive-user", logs, StringComparison.Ordinal);
    }

    private static DeviceRegistryService CreateRegistry(LocalDbContext context) =>
        new(context, new TestTimeProvider(), NullLogger<DeviceRegistryService>.Instance);

    private static DeviceCertificateValidator CreateValidator(CertificateFixture certificates, string allowedPin) =>
        new(certificates.TrustedRoot, new[] { allowedPin }, TimeProvider.System);

    private static async Task<(DeviceEnrollmentIssue Enrollment, DeviceCredentialIssue Credential)> CreateActiveDeviceAsync(
        DeviceRegistryService registry)
    {
        DeviceEnrollmentIssue enrollment = await registry.CreateEnrollmentAsync(new DeviceEnrollmentCommand(
            "synthetic-user-018",
            LocalAccess.Read,
            LocalAccess.Read));
        DeviceCredentialIssue credential = (await registry.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode)))!;
        return (enrollment, credential);
    }

    private static DefaultHttpContext CreateAuthenticatedContext(
        X509Certificate2 certificate,
        string deviceId,
        string userId,
        string credential)
    {
        DefaultHttpContext context = new();
        context.Request.IsHttps = true;
        context.Connection.ClientCertificate = certificate;
        context.Request.Headers.Authorization = $"{DeviceAuthHandler.SchemeName} {credential}";
        context.Request.Headers[DeviceAuthHandler.DeviceIdHeader] = deviceId;
        context.Request.Headers[DeviceAuthHandler.UserIdHeader] = userId;
        return context;
    }

    private static async Task<DeviceAuthHandler> CreateHandlerAsync(
        DefaultHttpContext context,
        DeviceRegistryService registry,
        DeviceCertificateValidator validator)
    {
        DeviceAuthHandler handler = new(
            new TestOptionsMonitor<DeviceAuthenticationOptions>(new DeviceAuthenticationOptions()),
            NullLoggerFactory.Instance,
            UrlEncoder.Default,
            registry,
            validator);
        AuthenticationScheme scheme = new(
            DeviceAuthHandler.SchemeName,
            DeviceAuthHandler.SchemeName,
            typeof(DeviceAuthHandler));
        await handler.InitializeAsync(scheme, context);
        return handler;
    }
}

internal sealed class TestOptionsMonitor<TOptions>(TOptions options) : IOptionsMonitor<TOptions>
{
    public TOptions CurrentValue { get; } = options;

    public TOptions Get(string? name) => CurrentValue;

    public IDisposable? OnChange(Action<TOptions, string?> listener) => null;
}

internal sealed class RecordingLogger<T> : ILogger<T>
{
    public List<string> Messages { get; } = new();

    public IDisposable? BeginScope<TState>(TState state) where TState : notnull => null;

    public bool IsEnabled(LogLevel logLevel) => true;

    public void Log<TState>(
        LogLevel logLevel,
        EventId eventId,
        TState state,
        Exception? exception,
        Func<TState, Exception?, string> formatter) =>
        Messages.Add(formatter(state, exception));
}

internal sealed class CertificateFixture : IDisposable
{
    public CertificateFixture()
    {
        RSA rootKey = RSA.Create(2048);
        RootKey = rootKey;
        Root = CreateRoot(rootKey);
        RootPin = ComputePin(rootKey);
        TrustedRoot = new X509Certificate2(Root);
        TrustedClient = CreateIssuedClient(Root, out string trustedPin, "synthetic-trusted-client");
        TrustedClientPin = trustedPin;
        RSA untrustedKey = RSA.Create(2048);
        UntrustedKey = untrustedKey;
        UntrustedClient = CreateSelfSignedClient(untrustedKey, "synthetic-untrusted-client");
        UntrustedClientPin = ComputePin(untrustedKey);
    }

    private RSA RootKey { get; }

    private RSA UntrustedKey { get; }

    public X509Certificate2 Root { get; }

    public X509Certificate2 TrustedRoot { get; }

    public X509Certificate2 TrustedClient { get; }

    public X509Certificate2 UntrustedClient { get; }

    public string RootPin { get; }

    public string TrustedClientPin { get; }

    public string UntrustedClientPin { get; }

    public void Dispose()
    {
        Root.Dispose();
        TrustedRoot.Dispose();
        TrustedClient.Dispose();
        UntrustedClient.Dispose();
        RootKey.Dispose();
        UntrustedKey.Dispose();
    }

    private static X509Certificate2 CreateRoot(RSA key)
    {
        CertificateRequest request = new(
            "CN=Synthetic Alhayah Test Root",
            key,
            HashAlgorithmName.SHA256,
            RSASignaturePadding.Pkcs1);
        request.CertificateExtensions.Add(new X509BasicConstraintsExtension(true, false, 0, true));
        request.CertificateExtensions.Add(new X509KeyUsageExtension(X509KeyUsageFlags.KeyCertSign, true));
        return request.CreateSelfSigned(DateTimeOffset.UtcNow.AddDays(-1), DateTimeOffset.UtcNow.AddYears(1));
    }

    private static X509Certificate2 CreateIssuedClient(
        X509Certificate2 issuer,
        out string pin,
        string subject)
    {
        RSA clientKey = RSA.Create(2048);
        pin = ComputePin(clientKey);
        CertificateRequest request = new(
            $"CN={subject}",
            clientKey,
            HashAlgorithmName.SHA256,
            RSASignaturePadding.Pkcs1);
        AddClientExtensions(request);
        byte[] serial = SHA256.HashData(Encoding.UTF8.GetBytes(subject)).Take(16).ToArray();
        X509Certificate2 publicCertificate = request.Create(
            issuer,
            DateTimeOffset.UtcNow.AddHours(-1),
            DateTimeOffset.UtcNow.AddMonths(3),
            serial);
        X509Certificate2 client = publicCertificate.CopyWithPrivateKey(clientKey);
        publicCertificate.Dispose();
        clientKey.Dispose();
        return client;
    }

    private static X509Certificate2 CreateSelfSignedClient(RSA key, string subject)
    {
        CertificateRequest request = new(
            $"CN={subject}",
            key,
            HashAlgorithmName.SHA256,
            RSASignaturePadding.Pkcs1);
        AddClientExtensions(request);
        return request.CreateSelfSigned(DateTimeOffset.UtcNow.AddHours(-1), DateTimeOffset.UtcNow.AddMonths(3));
    }

    private static void AddClientExtensions(CertificateRequest request)
    {
        request.CertificateExtensions.Add(new X509BasicConstraintsExtension(false, false, 0, true));
        request.CertificateExtensions.Add(new X509EnhancedKeyUsageExtension(
            new OidCollection { new Oid("1.3.6.1.5.5.7.3.2") },
            true));
    }

    private static string ComputePin(RSA key) =>
        Convert.ToBase64String(SHA256.HashData(key.ExportSubjectPublicKeyInfo()));
}
