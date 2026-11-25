using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Services;

public sealed class ConflictService(
    LocalDbContext dbContext,
    TimeProvider timeProvider)
{
    public const string DuplicateRedemption = "DuplicateRedemption";

    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));

    public CriticalConflict? EvaluateUpdate(
        LocalEntity entity,
        Guid operationId,
        string deviceId,
        long expectedRowVersion,
        Guid expectedConcurrencyToken)
    {
        ArgumentNullException.ThrowIfNull(entity);
        if (entity.DeletedAtUtc is not null)
        {
            return AddCriticalConflict(
                operationId,
                deviceId,
                entity.GetType().Name,
                entity.Id,
                ConflictClass.DeleteAgainstUpdate.ToString(),
                JsonSerializer.Serialize(new
                {
                    expectedRowVersion,
                    expectedConcurrencyToken,
                    currentRowVersion = entity.RowVersion,
                    currentConcurrencyToken = entity.ConcurrencyToken,
                    deletedAtUtc = entity.DeletedAtUtc
                }));
        }

        if (entity.RowVersion != expectedRowVersion || entity.ConcurrencyToken != expectedConcurrencyToken)
        {
            return AddCriticalConflict(
                operationId,
                deviceId,
                entity.GetType().Name,
                entity.Id,
                ConflictClass.VersionMismatch.ToString(),
                JsonSerializer.Serialize(new
                {
                    expectedRowVersion,
                    expectedConcurrencyToken,
                    currentRowVersion = entity.RowVersion,
                    currentConcurrencyToken = entity.ConcurrencyToken
                }));
        }

        return null;
    }

    public CriticalConflict AddCriticalConflict(
        Guid operationId,
        string deviceId,
        string entityType,
        Guid entityId,
        string conflictType,
        string detailsJson = "{}")
    {
        if (operationId == Guid.Empty)
        {
            throw new ArgumentException("An operation id is required.", nameof(operationId));
        }

        ArgumentException.ThrowIfNullOrWhiteSpace(deviceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(entityType);
        ArgumentException.ThrowIfNullOrWhiteSpace(conflictType);
        ArgumentException.ThrowIfNullOrWhiteSpace(detailsJson);

        CriticalConflict conflict = new()
        {
            Id = Guid.NewGuid(),
            OperationId = operationId,
            DeviceId = deviceId,
            EntityType = entityType,
            EntityId = entityId,
            ConflictType = conflictType,
            DetailsJson = detailsJson,
            Decision = "PendingReview",
            CreatedAtUtc = _timeProvider.GetUtcNow()
        };
        _dbContext.CriticalConflicts.Add(conflict);
        return conflict;
    }

    public async Task<bool> RecordDecisionAsync(
        Guid conflictId,
        string decision,
        string deviceId,
        CancellationToken cancellationToken = default)
    {
        if (decision is not ("Confirmed" or "Rejected"))
        {
            throw new ArgumentException("A conflict decision must be Confirmed or Rejected.", nameof(decision));
        }

        ArgumentException.ThrowIfNullOrWhiteSpace(deviceId);
        CriticalConflict? conflict = await _dbContext.CriticalConflicts
            .SingleOrDefaultAsync(item => item.Id == conflictId, cancellationToken)
            .ConfigureAwait(false);
        if (conflict is null)
        {
            return false;
        }

        if (!string.Equals(conflict.Decision, "PendingReview", StringComparison.Ordinal))
        {
            throw new InvalidOperationException("The critical conflict already has an audited decision.");
        }

        conflict.Decision = decision;
        conflict.DecisionAtUtc = _timeProvider.GetUtcNow();
        conflict.DecisionByDeviceId = deviceId;
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        return true;
    }
}
