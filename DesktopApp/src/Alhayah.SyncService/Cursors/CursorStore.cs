using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Storage;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace Alhayah.SyncService.Cursors;

public enum ReconciliationPhase
{
    FullReconciliation,
    Reconciling,
    Reconciled
}

public sealed record CursorState
{
    public required string SourceId { get; init; }

    public required string Channel { get; init; }

    public string? Cursor { get; init; }

    public string? SnapshotId { get; init; }

    public long? LastChangeLogId { get; init; }

    public required ReconciliationPhase Phase { get; init; }

    public bool ReconciliationRequired { get; init; }

    public DateTimeOffset? UpdatedAtUtc { get; init; }
}

public sealed record RemoteChange
{
    public long ChangeId { get; init; }

    public Guid ClientUuid { get; init; }

    public required string EntityType { get; init; }

    public required string PayloadJson { get; init; }

    public required string SearchText { get; init; }

    public string? CanonicalId { get; init; }

    public string Operation { get; init; } = "create";

    public string? RowVersion { get; init; }

    public string? ConcurrencyToken { get; init; }

    public DateTimeOffset? OccurredAtUtc { get; init; }

    public DateTimeOffset? DeletedAtUtc { get; init; }

    public string? DeletedByDeviceId { get; init; }
}

public sealed record RemoteChangePage
{
    public string? SnapshotId { get; init; }

    public string? NextCursor { get; init; }

    public long LastChangeLogId { get; init; }

    public bool CompletesReconciliation { get; init; }

    public IReadOnlyList<RemoteChange> Changes { get; init; } = [];
}

public interface IChangePageApplier
{
    Task ApplyAsync(
        LocalStoreDbContext context,
        string sourceId,
        string channel,
        RemoteChangePage page,
        CancellationToken cancellationToken);
}

public enum CursorCommitResult
{
    Applied,
    AlreadyCommitted
}

public sealed record SourceConnectionRegistration
{
    public required string SourceId { get; init; }

    public required string Endpoint { get; init; }

    public required string CertificatePin { get; init; }

    public required string CredentialReference { get; init; }
}

public sealed class CursorStore
{
    private const string StoredCursorPrefix = "alhayah.sync-cursor.v1.";
    private readonly ISyncServiceStore _store;
    private readonly IChangePageApplier _pageApplier;
    private readonly TimeProvider _timeProvider;

    public CursorStore(
        ISyncServiceStore store,
        IChangePageApplier pageApplier,
        TimeProvider timeProvider)
    {
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _pageApplier = pageApplier ?? throw new ArgumentNullException(nameof(pageApplier));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
    }

    public async Task RegisterSourceAsync(
        SourceConnectionRegistration registration,
        string channel = "changes",
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(registration);
        string sourceId = new SyncSourceId(registration.SourceId).Value;
        ValidateChannel(channel);
        await using LocalStoreDbContext context = _store.CreateContext();
        bool exists = await context.SyncCheckpoints
            .AnyAsync(item => item.SourceId == sourceId && item.Channel == channel, cancellationToken)
            .ConfigureAwait(false);
        if (exists)
        {
            return;
        }

        context.SyncCheckpoints.Add(new SyncCheckpoint
        {
            SourceId = sourceId,
            Channel = channel,
            Cursor = null,
            LastCommittedChangeId = null,
            ReconciliationRequired = true,
            UpdatedAtUtc = _timeProvider.GetUtcNow()
        });
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }

    public async Task<CursorState> GetAsync(
        string sourceId,
        string channel = "changes",
        CancellationToken cancellationToken = default)
    {
        sourceId = new SyncSourceId(sourceId).Value;
        ValidateChannel(channel);
        await using LocalStoreDbContext context = _store.CreateContext();
        SyncCheckpoint? checkpoint = await context.SyncCheckpoints
            .AsNoTracking()
            .SingleOrDefaultAsync(item => item.SourceId == sourceId && item.Channel == channel, cancellationToken)
            .ConfigureAwait(false);
        return checkpoint is null ? InitialState(sourceId, channel) : ToState(checkpoint);
    }

    public async Task<CursorCommitResult> CommitPageAsync(
        string sourceId,
        string channel,
        RemoteChangePage page,
        CancellationToken cancellationToken = default)
    {
        sourceId = new SyncSourceId(sourceId).Value;
        ValidateChannel(channel);
        ArgumentNullException.ThrowIfNull(page);
        if (page.LastChangeLogId < 0)
        {
            throw new ArgumentOutOfRangeException(nameof(page));
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        await using IDbContextTransaction transaction = await context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        SyncCheckpoint? checkpoint = await context.SyncCheckpoints
            .SingleOrDefaultAsync(item => item.SourceId == sourceId && item.Channel == channel, cancellationToken)
            .ConfigureAwait(false);
        if (checkpoint is not null && IsCommittedPage(checkpoint, page))
        {
            return CursorCommitResult.AlreadyCommitted;
        }

        await _pageApplier.ApplyAsync(context, sourceId, channel, page, cancellationToken).ConfigureAwait(false);
        CursorState previous = checkpoint is null ? InitialState(sourceId, channel) : ToState(checkpoint);
        ReconciliationPhase phase = page.CompletesReconciliation
            ? ReconciliationPhase.Reconciled
            : previous.Phase == ReconciliationPhase.Reconciled
                ? ReconciliationPhase.Reconciled
                : ReconciliationPhase.Reconciling;
        StoredCursor stored = new()
        {
            Version = 1,
            Cursor = page.NextCursor,
            SnapshotId = page.SnapshotId,
            Phase = phase
        };
        if (checkpoint is null)
        {
            checkpoint = new SyncCheckpoint
            {
                SourceId = sourceId,
                Channel = channel
            };
            context.SyncCheckpoints.Add(checkpoint);
        }

        checkpoint.Cursor = Encode(stored);
        checkpoint.LastCommittedChangeId = page.LastChangeLogId;
        checkpoint.ReconciliationRequired = phase != ReconciliationPhase.Reconciled;
        checkpoint.UpdatedAtUtc = _timeProvider.GetUtcNow();
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
        return CursorCommitResult.Applied;
    }

    private static CursorState InitialState(string sourceId, string channel) => new()
    {
        SourceId = sourceId,
        Channel = channel,
        Phase = ReconciliationPhase.FullReconciliation,
        ReconciliationRequired = true
    };

    private static CursorState ToState(SyncCheckpoint checkpoint)
    {
        StoredCursor? stored = Decode(checkpoint.Cursor);
        return new CursorState
        {
            SourceId = checkpoint.SourceId,
            Channel = checkpoint.Channel,
            Cursor = stored?.Cursor ?? (stored is null ? checkpoint.Cursor : null),
            SnapshotId = stored?.SnapshotId,
            LastChangeLogId = checkpoint.LastCommittedChangeId,
            Phase = stored?.Phase ?? (checkpoint.ReconciliationRequired
                ? ReconciliationPhase.FullReconciliation
                : ReconciliationPhase.Reconciled),
            ReconciliationRequired = checkpoint.ReconciliationRequired,
            UpdatedAtUtc = checkpoint.UpdatedAtUtc
        };
    }

    private static bool IsCommittedPage(SyncCheckpoint checkpoint, RemoteChangePage page)
    {
        StoredCursor? stored = Decode(checkpoint.Cursor);
        string? cursor = stored?.Cursor ?? (stored is null ? checkpoint.Cursor : null);
        return checkpoint.LastCommittedChangeId == page.LastChangeLogId &&
            string.Equals(cursor, page.NextCursor, StringComparison.Ordinal) &&
            string.Equals(stored?.SnapshotId, page.SnapshotId, StringComparison.Ordinal);
    }

    private static string Encode(StoredCursor cursor)
    {
        byte[] json = JsonSerializer.SerializeToUtf8Bytes(cursor);
        return StoredCursorPrefix + Convert.ToBase64String(json)
            .TrimEnd('=')
            .Replace('+', '-')
            .Replace('/', '_');
    }

    private static StoredCursor? Decode(string? value)
    {
        if (value is null || !value.StartsWith(StoredCursorPrefix, StringComparison.Ordinal))
        {
            return null;
        }

        string encoded = value[StoredCursorPrefix.Length..].Replace('-', '+').Replace('_', '/');
        encoded = encoded.PadRight(encoded.Length + ((4 - encoded.Length % 4) % 4), '=');
        StoredCursor cursor = JsonSerializer.Deserialize<StoredCursor>(Convert.FromBase64String(encoded))
            ?? throw new InvalidDataException("The stored synchronization cursor is empty.");
        if (cursor.Version != 1)
        {
            throw new InvalidDataException("The stored synchronization cursor version is unsupported.");
        }

        return cursor;
    }

    private static void ValidateChannel(string channel)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(channel);
        if (channel.Length > 120)
        {
            throw new ArgumentException("The synchronization channel is too long.", nameof(channel));
        }
    }

    private sealed record StoredCursor
    {
        public int Version { get; init; }

        public string? Cursor { get; init; }

        public string? SnapshotId { get; init; }

        public ReconciliationPhase Phase { get; init; }
    }
}
