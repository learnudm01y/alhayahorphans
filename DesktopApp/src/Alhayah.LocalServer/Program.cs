using System.Security.Cryptography.X509Certificates;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Authentication;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Hubs;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Server.Kestrel.Https;
using Microsoft.EntityFrameworkCore;

SQLitePCL.Batteries_V2.Init();

WebApplicationOptions webOptions = new()
{
    Args = args,
    ContentRootPath = AppContext.BaseDirectory
};
WebApplicationBuilder builder = WebApplication.CreateBuilder(webOptions);
builder.Host.UseWindowsService();

string defaultDataDirectory = Path.Combine(
    Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
    "Alhayah",
    "LocalServer");
string dataDirectory = LocalPathGuard.EnsureLocalPath(
    builder.Configuration["LocalStorage:DataDirectory"] ?? defaultDataDirectory);
LocalStorageOptions storage = new()
{
    DatabasePath = LocalPathGuard.EnsureLocalPath(
        builder.Configuration["LocalStorage:DatabasePath"] ?? Path.Combine(dataDirectory, "alhayah-local.db")),
    BackupDirectory = LocalPathGuard.EnsureLocalPath(
        builder.Configuration["LocalStorage:BackupDirectory"] ?? Path.Combine(dataDirectory, "backups")),
    AttachmentDirectory = LocalPathGuard.EnsureLocalPath(
        builder.Configuration["LocalStorage:AttachmentDirectory"] ?? Path.Combine(dataDirectory, "attachments")),
    DatabaseCredentialTarget = builder.Configuration["LocalStorage:DatabaseCredentialTarget"]
        ?? "Alhayah/LocalServer/Service/DatabaseEncryptionKey",
    AttachmentCredentialTarget = builder.Configuration["LocalStorage:AttachmentCredentialTarget"]
        ?? "Alhayah/LocalServer/Service/AttachmentEncryptionKey"
};
LocalServerSecurityOptions security = new()
{
    SourceId = new SyncSourceId(builder.Configuration["LocalServer:SourceId"]
        ?? throw new InvalidOperationException("LocalServer:SourceId is required.")),
    ApiVersion = builder.Configuration["LocalServer:ApiVersion"] ?? "1",
    HttpsPort = builder.Configuration.GetValue<int?>("LocalServer:HttpsPort") ?? 8443,
    ServerCertificateThumbprint = builder.Configuration["LocalServer:ServerCertificateThumbprint"]
        ?? throw new InvalidOperationException("LocalServer:ServerCertificateThumbprint is required."),
    TrustedRootCertificateThumbprint = builder.Configuration["LocalServer:TrustedRootCertificateThumbprint"]
        ?? throw new InvalidOperationException("LocalServer:TrustedRootCertificateThumbprint is required."),
    AllowedClientSpkiPins = builder.Configuration.GetSection("LocalServer:AllowedClientSpkiPins").Get<string[]>()
        ?? throw new InvalidOperationException("LocalServer:AllowedClientSpkiPins is required.")
};
security.Validate();
LocalServerCertificateMaterials certificateMaterials = LocalServerCertificateMaterials.Load(security);
X509Certificate2 serverCertificate = certificateMaterials.CreateServerCertificate();
using X509Certificate2 trustedRootCertificate = certificateMaterials.CreateTrustedRootCertificate();
DeviceCertificateValidator certificateValidator = new(
    trustedRootCertificate,
    security.AllowedClientSpkiPins,
    TimeProvider.System);

builder.WebHost.ConfigureKestrel(options =>
{
    options.ListenAnyIP(security.HttpsPort, endpoint =>
    {
        endpoint.UseHttps(https =>
        {
            https.ServerCertificate = serverCertificate;
            https.ClientCertificateMode = ClientCertificateMode.RequireCertificate;
            https.ClientCertificateValidation = (certificate, _, _) => certificateValidator.Validate(certificate);
        });
    });
});

builder.Services.AddSingleton(storage);
builder.Services.AddSingleton(security);
builder.Services.AddSingleton(certificateMaterials);
builder.Services.AddSingleton(certificateValidator);
builder.Services.AddSingleton(TimeProvider.System);
builder.Services.AddSingleton<IDatabaseKeyProvider>(
    new WindowsCredentialKeyProvider(storage.DatabaseCredentialTarget));
builder.Services.AddSingleton<IAttachmentKeyProvider>(
    new WindowsCredentialKeyProvider(storage.AttachmentCredentialTarget));
builder.Services.AddSingleton<LocalDbConnectionFactory>();
builder.Services.AddDbContext<LocalDbContext>((provider, options) =>
    provider.GetRequiredService<LocalDbConnectionFactory>().Configure(options));
builder.Services.AddSingleton<LocalBackupService>();
builder.Services.AddScoped<LocalMigrationService>();
builder.Services.AddSingleton<EncryptedAttachmentStore>();
builder.Services.AddScoped<DeviceRegistryService>();
builder.Services.AddScoped<LocalChangeFeedService>();
builder.Services.AddScoped<ConflictService>();
builder.Services.AddScoped<IdempotencyService>();
builder.Services.AddScoped<VoucherNumberAllocator>();
builder.Services.AddSingleton<ILocalSyncNotifier, SignalRLocalSyncNotifier>();
builder.Services.AddAuthentication(DeviceAuthHandler.SchemeName)
    .AddScheme<DeviceAuthenticationOptions, DeviceAuthHandler>(DeviceAuthHandler.SchemeName, _ => { });
builder.Services.AddAuthorization(options =>
{
    options.FallbackPolicy = new AuthorizationPolicyBuilder()
        .AddAuthenticationSchemes(DeviceAuthHandler.SchemeName)
        .RequireAuthenticatedUser()
        .RequireClaim(DeviceClaimTypes.Permission, LocalPermissionNames.Read)
        .Build();
    options.AddPolicy(DevicePolicies.Read, policy => policy
        .AddAuthenticationSchemes(DeviceAuthHandler.SchemeName)
        .RequireAuthenticatedUser()
        .RequireClaim(DeviceClaimTypes.Permission, LocalPermissionNames.Read));
    options.AddPolicy(DevicePolicies.Operations, policy => policy
        .AddAuthenticationSchemes(DeviceAuthHandler.SchemeName)
        .RequireAuthenticatedUser()
        .RequireClaim(DeviceClaimTypes.Permission, LocalPermissionNames.Operations));
    options.AddPolicy(DevicePolicies.Admin, policy => policy
        .AddAuthenticationSchemes(DeviceAuthHandler.SchemeName)
        .RequireAuthenticatedUser()
        .RequireClaim(DeviceClaimTypes.Permission, LocalPermissionNames.Admin));
});
builder.Services.AddControllers();
builder.Services.AddSignalR();

WebApplication app = builder.Build();

await using (AsyncServiceScope scope = app.Services.CreateAsyncScope())
{
    await scope.ServiceProvider
        .GetRequiredService<LocalMigrationService>()
        .MigrateAsync();
}

app.Use(async (context, next) =>
{
    if (!context.Request.IsHttps)
    {
        context.Response.StatusCode = StatusCodes.Status400BadRequest;
        return;
    }

    await next();
});
app.UseAuthentication();
app.UseAuthorization();
app.MapControllers();
app.MapHub<LocalSyncHub>("/local/hubs/sync");

try
{
    await app.RunAsync();
}
finally
{
    certificateValidator.Dispose();
    certificateMaterials.Dispose();
    serverCertificate.Dispose();
}
