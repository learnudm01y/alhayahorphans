using System.Security.Claims;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Authentication;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Controllers;

[ApiController]
[Authorize(Policy = DevicePolicies.Operations)]
[Route("local/operations")]
public sealed class OperationsController(
    IdempotencyService idempotencyService,
    ConflictService conflictService,
    LocalDbContext dbContext) : ControllerBase
{
    private readonly IdempotencyService _idempotencyService = idempotencyService
        ?? throw new ArgumentNullException(nameof(idempotencyService));
    private readonly ConflictService _conflictService = conflictService ?? throw new ArgumentNullException(nameof(conflictService));
    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));

    [HttpPost("aid")]
    public async Task<ActionResult<LocalOperationResult>> CreateAid(
        CreateAidOperationRequest request,
        CancellationToken cancellationToken)
    {
        string deviceId = GetDeviceId();
        LocalOperationResult result = await _idempotencyService.ExecuteAsync(
            request.OperationId,
            request.IdempotencyKey,
            deviceId,
            "aid.create",
            request.Envelope,
            (db, envelope, _) =>
            {
                AidOperation aid = new()
                {
                    Id = envelope.Entity.ClientUuid,
                    OperationId = request.OperationId,
                    AidType = request.AidType,
                    EntitlementId = request.EntitlementId,
                    Amount = request.Amount,
                    CurrencyCode = request.CurrencyCode,
                    Status = "Requested",
                    OccurredAtUtc = request.OccurredAtUtc,
                    Notes = request.Notes,
                    LastModifiedByDeviceId = deviceId
                };
                db.AidOperations.Add(aid);
                return Task.FromResult(LocalBusinessResult.Completed(aid.Id, 1));
            },
            cancellationToken).ConfigureAwait(false);
        return ToActionResult(result);
    }

    [HttpPost("expenses")]
    public async Task<ActionResult<LocalOperationResult>> CreateExpense(
        CreateExpenseOperationRequest request,
        CancellationToken cancellationToken)
    {
        string deviceId = GetDeviceId();
        LocalOperationResult result = await _idempotencyService.ExecuteAsync(
            request.OperationId,
            request.IdempotencyKey,
            deviceId,
            "expense.create",
            request.Envelope,
            (db, envelope, _) =>
            {
                Expense expense = new()
                {
                    Id = envelope.Entity.ClientUuid,
                    OperationId = request.OperationId,
                    Category = request.Category,
                    SupplierId = request.SupplierId,
                    Amount = request.Amount,
                    CurrencyCode = request.CurrencyCode,
                    IncurredAtUtc = request.IncurredAtUtc,
                    Description = request.Description,
                    LastModifiedByDeviceId = deviceId
                };
                db.Expenses.Add(expense);
                return Task.FromResult(LocalBusinessResult.Completed(expense.Id, 1));
            },
            cancellationToken).ConfigureAwait(false);
        return ToActionResult(result);
    }

    [HttpGet("{operationId:guid}")]
    public async Task<ActionResult<LocalOperationResult>> GetResult(
        Guid operationId,
        CancellationToken cancellationToken)
    {
        LocalOperationResult? result = await _idempotencyService.GetResultAsync(operationId, cancellationToken)
            .ConfigureAwait(false);
        return result is null ? NotFound() : Ok(result);
    }

    [HttpPut("identities/{clientUuid:guid}")]
    public async Task<ActionResult<ClientIdentityMappingView>> ResolveIdentity(
        Guid clientUuid,
        ClientIdentityResolutionRequest request,
        CancellationToken cancellationToken)
    {
        await _idempotencyService.RegisterClientUuidAsync(
            request.EntityType,
            clientUuid,
            request.CanonicalId,
            cancellationToken).ConfigureAwait(false);
        ClientIdentityMappingView? mapping = await _idempotencyService.GetClientIdentityAsync(
            request.EntityType,
            clientUuid,
            cancellationToken).ConfigureAwait(false);
        return Ok(mapping);
    }

    [HttpGet("retention-warnings")]
    public async Task<ActionResult<IReadOnlyList<IdempotencyRetentionWarning>>> GetRetentionWarnings(
        CancellationToken cancellationToken) =>
        Ok(await _idempotencyService.GetRetentionWarningsAsync(cancellationToken).ConfigureAwait(false));

    [HttpGet("conflicts")]
    public async Task<ActionResult<IReadOnlyList<CriticalConflict>>> GetConflicts(
        CancellationToken cancellationToken) =>
        Ok(await _dbContext.CriticalConflicts.AsNoTracking()
            .OrderBy(item => item.CreatedAtUtc)
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false));

    [Authorize(Policy = DevicePolicies.Admin)]
    [HttpPost("conflicts/{conflictId:guid}/decision")]
    public async Task<IActionResult> RecordConflictDecision(
        Guid conflictId,
        ConflictDecisionRequest request,
        CancellationToken cancellationToken)
    {
        bool recorded = await _conflictService.RecordDecisionAsync(
            conflictId,
            request.Decision,
            GetDeviceId(),
            cancellationToken).ConfigureAwait(false);
        return recorded ? NoContent() : NotFound();
    }

    [Authorize(Policy = DevicePolicies.Admin)]
    [HttpPost("cleanup-expired")]
    public async Task<ActionResult<int>> CleanupExpired(CancellationToken cancellationToken) =>
        Ok(await _idempotencyService.CleanupExpiredAsync(cancellationToken).ConfigureAwait(false));

    private ActionResult<LocalOperationResult> ToActionResult(LocalOperationResult result) => result.Status switch
    {
        LocalOperationStatus.Pending => Accepted(result),
        LocalOperationStatus.Conflict => Conflict(result),
        _ => Ok(result)
    };

    private string GetDeviceId() => User.FindFirstValue(DeviceClaimTypes.DeviceId)
        ?? throw new InvalidOperationException("The authenticated device claim is missing.");
}

public sealed class CreateAidOperationRequest
{
    public required Guid OperationId { get; init; }

    public string? IdempotencyKey { get; init; }

    public required SyncEnvelope Envelope { get; init; }

    public required string AidType { get; init; }

    public Guid? EntitlementId { get; init; }

    public required decimal Amount { get; init; }

    public required string CurrencyCode { get; init; }

    public required DateTimeOffset OccurredAtUtc { get; init; }

    public string? Notes { get; init; }
}

public sealed class CreateExpenseOperationRequest
{
    public required Guid OperationId { get; init; }

    public string? IdempotencyKey { get; init; }

    public required SyncEnvelope Envelope { get; init; }

    public required string Category { get; init; }

    public Guid? SupplierId { get; init; }

    public required decimal Amount { get; init; }

    public required string CurrencyCode { get; init; }

    public required DateTimeOffset IncurredAtUtc { get; init; }

    public string? Description { get; init; }
}

public sealed class ClientIdentityResolutionRequest
{
    public required string EntityType { get; init; }

    public string? CanonicalId { get; init; }
}

public sealed class ConflictDecisionRequest
{
    public required string Decision { get; init; }
}
