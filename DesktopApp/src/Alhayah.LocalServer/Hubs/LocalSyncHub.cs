using Alhayah.LocalServer.Authentication;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.SignalR;

namespace Alhayah.LocalServer.Hubs;

[Authorize(Policy = DevicePolicies.Read)]
public sealed class LocalSyncHub : Hub
{
}

public interface ILocalSyncNotifier
{
    Task NotifyOperationAsync(
        LocalOperationResult result,
        CancellationToken cancellationToken = default);
}

public sealed class SignalRLocalSyncNotifier(IHubContext<LocalSyncHub> hubContext) : ILocalSyncNotifier
{
    private readonly IHubContext<LocalSyncHub> _hubContext = hubContext ?? throw new ArgumentNullException(nameof(hubContext));

    public Task NotifyOperationAsync(
        LocalOperationResult result,
        CancellationToken cancellationToken = default) =>
        _hubContext.Clients.All.SendAsync("localOperationChanged", result, cancellationToken);
}
