using Alhayah.LocalData.Data;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalData.Repositories;

public sealed class OutboxRepository(LocalStoreDbContext context)
{
    private readonly LocalStoreDbContext _context = context ?? throw new ArgumentNullException(nameof(context));

    public async Task<IReadOnlyList<OutboxOperation>> GetPendingAsync(CancellationToken cancellationToken = default)
    {
        OutboxOperation[] operations = await _context.OutboxOperations
            .Where(item => item.Status == "Pending")
            .OrderBy(item => item.CreatedAtUtc)
            .ThenBy(item => item.OperationId)
            .ToArrayAsync(cancellationToken)
            .ConfigureAwait(false);
        return operations;
    }

    internal void Add(OutboxOperation operation) => _context.OutboxOperations.Add(operation);
}
