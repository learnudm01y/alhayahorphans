using Alhayah.LocalServer.Authentication;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;

namespace Alhayah.LocalServer.Controllers;

[ApiController]
[Authorize(Policy = DevicePolicies.Read)]
[Route("local/changes")]
public sealed class ChangeFeedController(LocalChangeFeedService changeFeedService) : ControllerBase
{
    private readonly LocalChangeFeedService _changeFeedService = changeFeedService
        ?? throw new ArgumentNullException(nameof(changeFeedService));

    [HttpGet]
    public async Task<ActionResult<LocalChangeFeedResponse>> GetChanges(
        [FromQuery] string? cursor,
        [FromQuery] int limit = LocalChangeFeedService.DefaultLimit,
        CancellationToken cancellationToken = default)
    {
        try
        {
            LocalChangeFeedResponse response = await _changeFeedService.GetChangesAsync(
                cursor,
                limit,
                cancellationToken).ConfigureAwait(false);
            return Ok(response);
        }
        catch (InvalidChangeCursorException)
        {
            return BadRequest(new ProblemDetails
            {
                Status = StatusCodes.Status400BadRequest,
                Title = "Invalid change-feed cursor",
                Detail = "Perform full reconciliation with the current source before resuming the feed."
            });
        }
        catch (ArgumentOutOfRangeException)
        {
            return BadRequest(new ProblemDetails
            {
                Status = StatusCodes.Status400BadRequest,
                Title = "Invalid change-feed limit",
                Detail = $"The limit must be between 1 and {LocalChangeFeedService.MaximumLimit}."
            });
        }
    }
}
