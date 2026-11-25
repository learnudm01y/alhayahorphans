using System.Data.Common;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Alhayah.SyncService.Outbox;
using Alhayah.SyncService.Storage;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Alhayah.SyncService.Tests;

internal sealed class SynchronizationTestHarness : IAsyncDisposable
{
    private readonly string _root;

    private SynchronizationTestHarness(string root, DbTransactionInterceptor? commitInterceptor)
    {
        _root = root;
        Store = new SynchronizationTestStore(root, commitInterceptor);
    }

    public SynchronizationTestStore Store { get; }

    public static async Task<SynchronizationTestHarness> CreateAsync(
        DbTransactionInterceptor? commitInterceptor = null)
    {
        string root = Path.Combine(Path.GetTempPath(), $"Alhayah.Synchronization.Tests.{Guid.NewGuid():N}");
        SynchronizationTestHarness harness = new(root, commitInterceptor);
        try
        {
            await harness.Store.InitializeAsync(CancellationToken.None);
            return harness;
        }
        catch
        {
            await harness.DisposeAsync();
            throw;
        }
    }

    public async Task<SyncEnvelope> SeedCreateAsync(
        string sourceId,
        Guid operationId,
        Guid clientUuid,
        DateTimeOffset? occurredAtUtc = null)
    {
        SyncEnvelope envelope = CreateEnvelope(sourceId, operationId, clientUuid, occurredAtUtc);
        await using LocalStoreDbContext context = Store.CreateContext();
        await new UnitOfWork(context).CreateAsync(
            envelope,
            "{\"displayName\":\"Synthetic record\"}",
            "Synthetic record",
            new LocalAuditContext(null, "synthetic.write", 1));
        return envelope;
    }

    public async Task<SyncEnvelope> SeedPatchAsync(
        Guid operationId,
        Guid clientUuid,
        string rowVersion,
        DateTimeOffset occurredAtUtc)
    {
        SyncEnvelope envelope = new()
        {
            OperationId = operationId,
            SourceId = new SyncSourceId("laravel:synthetic-a"),
            DeviceId = "synthetic-device",
            Entity = new EntityDescriptor
            {
                EntityType = "synthetic.records",
                ClientUuid = clientUuid,
                CanonicalId = null,
                References = []
            },
            OperationType = SyncOperationType.Update,
            RowVersion = rowVersion,
            OccurredAtUtc = occurredAtUtc,
            Patches =
            [
                new FieldPatch
                {
                    FieldName = "displayName",
                    BaseValue = null,
                    LocalValue = System.Text.Json.JsonSerializer.SerializeToElement("Synthetic patched"),
                    Criticality = FieldCriticality.Mergeable
                }
            ]
        };
        await using LocalStoreDbContext context = Store.CreateContext();
        await new UnitOfWork(context).PatchAsync(
            envelope,
            "Synthetic patched",
            new LocalAuditContext(null, "synthetic.write", 1));
        return envelope;
    }

    public static SyncEnvelope CreateEnvelope(
        string sourceId,
        Guid operationId,
        Guid clientUuid,
        DateTimeOffset? occurredAtUtc = null) => new()
    {
        OperationId = operationId,
        SourceId = new SyncSourceId(sourceId),
        DeviceId = "synthetic-device",
        Entity = new EntityDescriptor
        {
            EntityType = "synthetic.records",
            ClientUuid = clientUuid,
            CanonicalId = null,
            References = []
        },
        OperationType = SyncOperationType.Create,
        OccurredAtUtc = occurredAtUtc ?? DateTimeOffset.Parse("2026-11-25T01:00:00Z"),
        Patches = []
    };

    public ValueTask DisposeAsync()
    {
        if (Directory.Exists(_root))
        {
            Directory.Delete(_root, recursive: true);
        }

        return ValueTask.CompletedTask;
    }
}

internal sealed class SynchronizationTestStore : ISyncServiceStore
{
    private readonly string _databasePath;
    private readonly DbTransactionInterceptor? _commitInterceptor;

    public SynchronizationTestStore(string root, DbTransactionInterceptor? commitInterceptor)
    {
        _databasePath = Path.Combine(root, "store", "device.db");
        _commitInterceptor = commitInterceptor;
    }

    public async Task InitializeAsync(CancellationToken cancellationToken)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(_databasePath)!);
        await using LocalStoreDbContext context = CreateContext();
        await context.Database.MigrateAsync(cancellationToken);
    }

    public LocalStoreDbContext CreateContext(string? databasePath = null)
    {
        SqliteConnectionStringBuilder connection = new()
        {
            DataSource = databasePath ?? _databasePath,
            Mode = SqliteOpenMode.ReadWriteCreate,
            Cache = SqliteCacheMode.Private,
            Pooling = false,
            ForeignKeys = true
        };
        DbContextOptionsBuilder<LocalStoreDbContext> builder = new DbContextOptionsBuilder<LocalStoreDbContext>()
            .UseSqlite(connection.ToString());
        if (_commitInterceptor is not null)
        {
            builder.AddInterceptors(_commitInterceptor);
        }

        return new LocalStoreDbContext(builder.Options);
    }

    public Task<LocalStoreBackup> CreateBackupAsync(
        string? destinationDirectory,
        CancellationToken cancellationToken) =>
        throw new NotSupportedException();

    public Task RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken) =>
        throw new NotSupportedException();
}

internal sealed class ManualTimeProvider(DateTimeOffset initialUtcNow) : TimeProvider
{
    public override DateTimeOffset GetUtcNow() => UtcNow;

    public DateTimeOffset UtcNow { get; private set; } = initialUtcNow;

    public void Advance(TimeSpan duration) => UtcNow = UtcNow.Add(duration);
}

internal sealed class FakeOutboxTransport : IOutboxTransport
{
    private readonly Queue<Func<OutboxDelivery, CancellationToken, Task>> _steps = new();

    public List<Guid> SendOperationIds { get; } = [];

    public HashSet<Guid> AppliedOperationIds { get; } = [];

    public void Enqueue(Func<OutboxDelivery, CancellationToken, Task> step) => _steps.Enqueue(step);

    public async Task SendAsync(OutboxDelivery delivery, CancellationToken cancellationToken)
    {
        SendOperationIds.Add(delivery.OperationId);
        if (_steps.Count > 0)
        {
            await _steps.Dequeue()(delivery, cancellationToken);
            return;
        }

        AppliedOperationIds.Add(delivery.OperationId);
    }
}

internal sealed class CommitCrashInterceptor : DbTransactionInterceptor
{
    private bool _armed;

    public void ArmBeforeCommit()
    {
        AfterCommit = false;
        _armed = true;
    }

    public void ArmAfterCommit()
    {
        AfterCommit = true;
        _armed = true;
    }

    public bool AfterCommit { get; private set; }

    public override InterceptionResult TransactionCommitting(
        DbTransaction transaction,
        TransactionEventData eventData,
        InterceptionResult result)
    {
        ThrowIfArmed(afterCommit: false);
        return base.TransactionCommitting(transaction, eventData, result);
    }

    public override ValueTask<InterceptionResult> TransactionCommittingAsync(
        DbTransaction transaction,
        TransactionEventData eventData,
        InterceptionResult result,
        CancellationToken cancellationToken = default)
    {
        ThrowIfArmed(afterCommit: false);
        return ValueTask.FromResult(result);
    }

    public override void TransactionCommitted(
        DbTransaction transaction,
        TransactionEndEventData eventData)
    {
        ThrowIfArmed(afterCommit: true);
        base.TransactionCommitted(transaction, eventData);
    }

    public override Task TransactionCommittedAsync(
        DbTransaction transaction,
        TransactionEndEventData eventData,
        CancellationToken cancellationToken = default)
    {
        ThrowIfArmed(afterCommit: true);
        return Task.CompletedTask;
    }

    private void ThrowIfArmed(bool afterCommit)
    {
        if (!_armed || AfterCommit != afterCommit)
        {
            return;
        }

        _armed = false;
        string timing = afterCommit ? "after" : "before";
        throw new IOException($"Synthetic crash {timing} commit.");
    }
}
