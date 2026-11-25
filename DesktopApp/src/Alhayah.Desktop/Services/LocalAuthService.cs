using Alhayah.Contracts.Ipc;

namespace Alhayah.Desktop.Services;

public interface ILocalAuthService
{
    LocalSession? CurrentSession { get; }

    bool CanManageSettings { get; }

    bool CanResolveConflicts { get; }

    bool HasPermission(string permission);

    Task<LocalSession> LoginAsync(LoginRequest request, CancellationToken cancellationToken = default);

    Task<LocalSession> BootstrapAdministratorAsync(
        BootstrapAdministratorRequest request,
        CancellationToken cancellationToken = default);

    Task LogoutAsync(CancellationToken cancellationToken = default);
}

public sealed class LocalAuthService : ILocalAuthService
{
    private readonly ISyncServiceClient _client;
    private readonly TimeProvider _timeProvider;

    public LocalAuthService(ISyncServiceClient client, TimeProvider? timeProvider = null)
    {
        _client = client ?? throw new ArgumentNullException(nameof(client));
        _timeProvider = timeProvider ?? TimeProvider.System;
    }

    public LocalSession? CurrentSession => _client.CurrentSession;

    public bool CanManageSettings => HasPermission(LocalPermissions.ManageSettings);

    public bool CanResolveConflicts => HasPermission(LocalPermissions.ResolveConflicts);

    public bool HasPermission(string permission)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(permission);
        LocalSession? session = CurrentSession;
        return session is not null &&
               session.ExpiresAtUtc > _timeProvider.GetUtcNow() &&
               session.Permissions.Contains(permission, StringComparer.Ordinal);
    }

    public Task<LocalSession> LoginAsync(LoginRequest request, CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return _client.LoginAsync(request, cancellationToken);
    }

    public Task<LocalSession> BootstrapAdministratorAsync(
        BootstrapAdministratorRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        return _client.BootstrapAdministratorAsync(request, cancellationToken);
    }

    public Task LogoutAsync(CancellationToken cancellationToken = default) =>
        _client.LogoutAsync(cancellationToken);
}
