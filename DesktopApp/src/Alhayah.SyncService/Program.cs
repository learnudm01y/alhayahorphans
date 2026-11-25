using Alhayah.Contracts.Ipc;
using Alhayah.SyncService;
using Alhayah.SyncService.Configuration;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Identity;
using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Outbox;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Services;
using Alhayah.SyncService.Storage;
using Alhayah.SyncService.Synchronization;

HostApplicationBuilder builder = Host.CreateApplicationBuilder(args);
string configurationPath = Path.Combine(
    Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
    "Alhayah",
    "SyncService",
    "service-host.json");
builder.Configuration.AddJsonFile(configurationPath, optional: true, reloadOnChange: false);
builder.Services.AddWindowsService(options => options.ServiceName = SyncServiceProtocol.DefaultServiceName);
builder.Services.AddSingleton(TimeProvider.System);
builder.Services.AddSingleton<ISecretStore, SecretStore>();
builder.Services.AddSingleton(_ => SyncServiceStore.DefaultOptions());
builder.Services.AddSingleton<SyncServiceStore>();
builder.Services.AddSingleton<ISyncServiceStore>(services => services.GetRequiredService<SyncServiceStore>());
builder.Services.AddSingleton<ILocalStoreInitializer>(services => services.GetRequiredService<SyncServiceStore>());
builder.Services.AddSingleton(_ => new SyncSettingsStore(SyncSettingsStore.GetDefaultPath()));
builder.Services.AddSingleton(_ =>
{
    string publicKey = builder.Configuration["SyncService:PermissionAuthorityPublicKey"]
        ?? throw new InvalidOperationException("A signed permission authority public key is required.");
    return new SignedPermissionManifestValidator(Convert.FromBase64String(publicKey));
});
builder.Services.AddSingleton<Argon2PasswordHasher>();
builder.Services.AddSingleton<LocalAuthenticationService>();
builder.Services.AddSingleton<IAdministratorBootstrapService, UnavailableAdministratorBootstrapService>();
builder.Services.AddSingleton<SyncChannelRegistry>();
builder.Services.AddSingleton<RetryPolicy>();
builder.Services.AddSingleton<IChangePageApplier, LaravelChangePageApplier>();
builder.Services.AddSingleton<CursorStore>();
builder.Services.AddSingleton<ClientIdentityMapStore>();
builder.Services.AddSingleton<ILaravelHttpClientFactory, LaravelHttpClientFactory>();
builder.Services.AddSingleton(_ => new LaravelConnectorOptions
{
    SourceId = builder.Configuration["SyncService:LaravelSourceId"] ?? "laravel:default",
    DeviceId = builder.Configuration["SyncService:DeviceId"]
        ?? throw new InvalidOperationException("A stable Laravel device identifier is required."),
    AppVersion = typeof(LaravelSyncConnector).Assembly.GetName().Version?.ToString(3) ?? "1.0.0",
    MaxAttempts = builder.Configuration.GetValue("SyncService:LaravelMaxAttempts", 3),
    PullPageSize = builder.Configuration.GetValue("SyncService:LaravelPullPageSize", 200)
});
builder.Services.AddSingleton<LaravelSyncConnector>();
builder.Services.AddSingleton<ISyncChannel>(services => services.GetRequiredService<LaravelSyncConnector>());
builder.Services.AddSingleton<IOutboxTransport>(services => services.GetRequiredService<LaravelSyncConnector>());
builder.Services.AddSingleton<OutboxProcessor>();
builder.Services.AddSingleton(_ => new SyncServiceHostOptions
{
    LaravelSourceId = builder.Configuration["SyncService:LaravelSourceId"] ?? "laravel:default",
    LocalServerSourceId = builder.Configuration["SyncService:LocalServerSourceId"] ?? "local:default"
});
builder.Services.AddSingleton(_ =>
{
    int timeoutSeconds = builder.Configuration.GetValue("SyncService:RequestTimeoutSeconds", 30);
    int maximumConnections = builder.Configuration.GetValue("SyncService:MaxConcurrentConnections", 32);
    return new NamedPipeHostOptions
    {
        PipeName = builder.Configuration["SyncService:PipeName"] ?? SyncServiceProtocol.DefaultPipeName,
        AllowedSids = builder.Configuration.GetSection("SyncService:AllowedSids").Get<string[]>()
            ?? new NamedPipeHostOptions().AllowedSids,
        DeniedSids = builder.Configuration.GetSection("SyncService:DeniedSids").Get<string[]>() ?? [],
        RequestTimeout = TimeSpan.FromSeconds(timeoutSeconds),
        MaxConcurrentConnections = maximumConnections
    };
});
builder.Services.AddSingleton<INamedPipeConnectionAccepter>(services =>
    new WindowsNamedPipeAccepter(services.GetRequiredService<NamedPipeHostOptions>()));
builder.Services.AddSingleton<LocalStoreCommandHandlers>();
builder.Services.AddSingleton<ILocalStoreCommandHandler>(services =>
    services.GetRequiredService<LocalStoreCommandHandlers>());
builder.Services.AddSingleton<NamedPipeHost>();
builder.Services.AddHostedService<Worker>();

using IHost host = builder.Build();
DeviceSecretInitializer.EnsureEncryptionKeys(
    host.Services.GetRequiredService<ISecretStore>(),
    host.Services.GetRequiredService<Alhayah.LocalData.Data.LocalStoreOptions>());
await host.RunAsync();
