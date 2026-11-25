using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class RestoreTests
{
    private const string SourceId = "local:synthetic-restore-021";
    private const string DeviceId = "synthetic-restore-device";

    [Fact]
    public async Task CreateBackupAsync_WithOpenReader_RestoresCountsTotalsVersionsChecksumsAndFeed()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        Guid firstId = Guid.NewGuid();
        Guid secondId = Guid.NewGuid();
        Guid deletedId = Guid.NewGuid();
        Guid firstOperationId = Guid.NewGuid();
        const string idempotencyKey = "synthetic-restore-operation";
        SyncEnvelope firstEnvelope = LocalOperationTestFactory.CreateEnvelope(firstOperationId, firstId);
        LocalOperationResult originalResult;
        RestoreSnapshot before;
        using CancellationTokenSource timeout = new(TimeSpan.FromSeconds(45));

        await using (LocalDbContext setup = harness.CreateContext())
        {
            await setup.Database.MigrateAsync(timeout.Token);
            (_, IdempotencyService service) = OperationsTests.CreateServices(setup, time, new RecordingSyncNotifier());
            await OperationsTests.RegisterResolvedIdentityAsync(service, firstEnvelope.Entity);
            originalResult = await service.ExecuteAsync(
                firstOperationId,
                idempotencyKey,
                DeviceId,
                "aid.create",
                firstEnvelope,
                (db, envelope, _) =>
                {
                    db.AidOperations.Add(CreateAid(envelope.Entity.ClientUuid, envelope.OperationId, 10.25m));
                    return Task.FromResult(LocalBusinessResult.Completed(envelope.Entity.ClientUuid, 1));
                },
                timeout.Token);

            AidOperation second = CreateAid(secondId, Guid.NewGuid(), 4.75m);
            AidOperation deleted = CreateAid(deletedId, Guid.NewGuid(), 8.00m);
            setup.AidOperations.AddRange(second, deleted);
            await setup.SaveChangesAsync(timeout.Token);
            second.Amount = 5.00m;
            await setup.SaveChangesAsync(timeout.Token);
            setup.AidOperations.Remove(deleted);
            await setup.SaveChangesAsync(timeout.Token);

            AidOperation[] fixture = await setup.AidOperations
                .IgnoreQueryFilters()
                .AsNoTracking()
                .ToArrayAsync(timeout.Token);
            Assert.Equal(3, fixture.Length);
            Assert.Equal(1, fixture.Single(row => row.Id == firstId).RowVersion);
            Assert.Equal(2, fixture.Single(row => row.Id == secondId).RowVersion);
            Assert.Equal(2, fixture.Single(row => row.Id == deletedId).RowVersion);
            before = await CaptureSnapshotAsync(setup, time, timeout.Token);
            Assert.Equal(3, before.AllRowCount);
            Assert.Equal(2, before.LiveRowCount);
            Assert.Equal(15.25m, before.LiveAmountTotal);
            Assert.Equal(5, before.ChangeCount);
            Assert.Equal(1, before.IdempotencyCount);
        }

        LocalBackupService backupService = new(harness.Connections, harness.Storage);
        await using (SqliteConnection reader = await harness.Connections.OpenConnectionAsync(
            cancellationToken: timeout.Token))
        await using (SqliteCommand countCommand = reader.CreateCommand())
        {
            countCommand.CommandText = "SELECT COUNT(*) FROM aid_operations;";
            Assert.Equal(3L, await countCommand.ExecuteScalarAsync(timeout.Token));

            string backupPath = await backupService.CreateBackupAsync(timeout.Token);
            string backupChecksum = await ComputeFileChecksumAsync(backupPath, timeout.Token);
            string restoredPath = Path.Combine(harness.RootPath, "restored-full", "alhayah-local.db");
            await backupService.RestoreBackupAsync(backupPath, restoredPath, timeout.Token);

            Assert.Equal(backupChecksum, await ComputeFileChecksumAsync(backupPath, timeout.Token));
            Assert.Equal("ok", await ReadIntegrityCheckAsync(harness, restoredPath, timeout.Token));

            await using LocalDbContext restored = harness.CreateContext(restoredPath);
            RestoreSnapshot after = await CaptureSnapshotAsync(restored, time, timeout.Token);
            Assert.Equal(before, after);

            (_, IdempotencyService replayService) = OperationsTests.CreateServices(
                restored,
                time,
                new RecordingSyncNotifier());
            LocalOperationResult replay = await replayService.ExecuteAsync(
                firstOperationId,
                idempotencyKey,
                DeviceId,
                "aid.create",
                firstEnvelope,
                (_, _, _) => throw new InvalidOperationException("A restored idempotency record must prevent another business effect."),
                timeout.Token);

            Assert.Equal(originalResult, replay);
            Assert.Equal(after, await CaptureSnapshotAsync(restored, time, timeout.Token));
        }
    }

    [Fact]
    public async Task RestoreBackupAsync_WhenClientCancels_LeavesNoPartialDestinationAndPreservesBackup()
    {
        using LocalServerTestHarness harness = new();
        using CancellationTokenSource timeout = new(TimeSpan.FromSeconds(30));

        await using (LocalDbContext setup = harness.CreateContext())
        {
            await setup.Database.MigrateAsync(timeout.Token);
            setup.AidOperations.Add(CreateAid(Guid.NewGuid(), Guid.NewGuid(), 12.50m));
            await setup.SaveChangesAsync(timeout.Token);
        }

        LocalBackupService backupService = new(harness.Connections, harness.Storage);
        string backupPath = await backupService.CreateBackupAsync(timeout.Token);
        string backupChecksum = await ComputeFileChecksumAsync(backupPath, timeout.Token);
        string destinationPath = Path.Combine(harness.RootPath, "cancelled-restore", "alhayah-local.db");
        using CancellationTokenSource cancelled = CancellationTokenSource.CreateLinkedTokenSource(timeout.Token);
        cancelled.Cancel();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => backupService.RestoreBackupAsync(
            backupPath,
            destinationPath,
            cancelled.Token));

        Assert.False(File.Exists(destinationPath));
        Assert.Equal(backupChecksum, await ComputeFileChecksumAsync(backupPath, timeout.Token));
        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(1, await verification.AidOperations.CountAsync(timeout.Token));
        Assert.Equal(12.50m, await verification.AidOperations.SumAsync(row => row.Amount, timeout.Token));
    }

    private static AidOperation CreateAid(Guid id, Guid operationId, decimal amount) => new()
    {
        Id = id,
        OperationId = operationId,
        AidType = "SyntheticRestore",
        EntitlementId = Guid.NewGuid(),
        Amount = amount,
        CurrencyCode = "USD",
        Status = "Requested",
        OccurredAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        LastModifiedByDeviceId = DeviceId
    };

    private static async Task<RestoreSnapshot> CaptureSnapshotAsync(
        LocalDbContext context,
        TimeProvider time,
        CancellationToken cancellationToken)
    {
        AidOperation[] allRows = await context.AidOperations
            .IgnoreQueryFilters()
            .AsNoTracking()
            .OrderBy(row => row.Id)
            .ToArrayAsync(cancellationToken);
        AidOperation[] liveRows = allRows.Where(row => row.DeletedAtUtc is null).ToArray();
        LocalChangeFeedItem[] feed = await ReadAllFeedAsync(context, time, cancellationToken);
        IdempotencyRecord[] idempotencyRecords = await context.IdempotencyRecords
            .AsNoTracking()
            .OrderBy(record => record.OperationId)
            .ToArrayAsync(cancellationToken);

        string rowJson = JsonSerializer.Serialize(allRows.Select(row => new
        {
            row.Id,
            row.OperationId,
            row.ConcurrencyToken,
            row.RowVersion,
            row.Amount,
            row.Status,
            row.CreatedAtUtc,
            row.UpdatedAtUtc,
            row.DeletedAtUtc,
            row.DeletedByDeviceId,
            row.LastModifiedByDeviceId
        }).ToArray());
        string idempotencyJson = JsonSerializer.Serialize(idempotencyRecords.Select(record => new
        {
            record.OperationId,
            record.DeviceId,
            record.IdempotencyKey,
            record.RequestHash,
            record.RequestJson,
            record.Status,
            record.ResultJson,
            record.ExpiresAtUtc
        }).ToArray());

        return new RestoreSnapshot(
            allRows.Length,
            liveRows.Length,
            liveRows.Sum(row => row.Amount),
            ComputeTextChecksum(rowJson),
            string.Join(",", allRows.Select(row => $"{row.Id:N}:{row.RowVersion.ToString(CultureInfo.InvariantCulture)}:{row.ConcurrencyToken:N}")),
            feed.Length,
            string.Join(",", feed.Select(change => change.ChangeLogId.ToString(CultureInfo.InvariantCulture))),
            ComputeTextChecksum(JsonSerializer.Serialize(feed)),
            idempotencyRecords.Length,
            ComputeTextChecksum(idempotencyJson));
    }

    private static async Task<LocalChangeFeedItem[]> ReadAllFeedAsync(
        LocalDbContext context,
        TimeProvider time,
        CancellationToken cancellationToken)
    {
        LocalChangeFeedService service = new(
            context,
            new LocalServerSecurityOptions
            {
                SourceId = new SyncSourceId(SourceId),
                ApiVersion = "1",
                HttpsPort = 8443,
                ServerCertificateThumbprint = new string('C', 40),
                TrustedRootCertificateThumbprint = new string('D', 40),
                AllowedClientSpkiPins = new[] { Convert.ToBase64String(new byte[32]) }
            },
            time);
        List<LocalChangeFeedItem> changes = new();
        string? cursor = null;
        bool hasMore;
        int pageCount = 0;

        do
        {
            LocalChangeFeedResponse page = await service.GetChangesAsync(cursor, 2, cancellationToken);
            changes.AddRange(page.Changes);
            cursor = page.NextCursor;
            hasMore = page.HasMore;
            pageCount++;
            Assert.True(pageCount <= 100, "The restored change feed did not terminate within the page budget.");
        }
        while (hasMore);

        return changes.ToArray();
    }

    private static async Task<string> ReadIntegrityCheckAsync(
        LocalServerTestHarness harness,
        string databasePath,
        CancellationToken cancellationToken)
    {
        await using SqliteConnection connection = await harness.Connections.OpenConnectionAsync(
            databasePath,
            cancellationToken,
            enableWal: false);
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = "PRAGMA integrity_check;";
        return Convert.ToString(await command.ExecuteScalarAsync(cancellationToken), CultureInfo.InvariantCulture)
            ?? throw new InvalidOperationException("SQLite integrity_check returned no result.");
    }

    private static string ComputeTextChecksum(string value) =>
        Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(value)));

    private static async Task<string> ComputeFileChecksumAsync(
        string path,
        CancellationToken cancellationToken)
    {
        await using FileStream stream = File.OpenRead(path);
        return Convert.ToHexString(await SHA256.HashDataAsync(stream, cancellationToken));
    }

    private sealed record RestoreSnapshot(
        int AllRowCount,
        int LiveRowCount,
        decimal LiveAmountTotal,
        string RowChecksum,
        string RowVersionsAndTokens,
        int ChangeCount,
        string ChangeIds,
        string ChangeFeedChecksum,
        int IdempotencyCount,
        string IdempotencyChecksum);
}
