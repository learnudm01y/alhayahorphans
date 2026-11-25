using System.Data.Common;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class LocalChangeFeedTests
{
    private const string SourceId = "local:synthetic-change-feed";
    private const string DeviceId = "synthetic-device-019";

    [Fact]
    public async Task GetChangesAsync_WithCursorAndLimit_ReturnsStableOpaquePages()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        context.AidOperations.AddRange(
            CreateAidOperation("SYNTHETIC-AID-1"),
            CreateAidOperation("SYNTHETIC-AID-2"),
            CreateAidOperation("SYNTHETIC-AID-3"));
        await context.SaveChangesAsync();
        LocalChangeFeedService service = CreateService(context, time);

        LocalChangeFeedResponse first = await service.GetChangesAsync(null, 2);
        LocalChangeFeedResponse replay = await service.GetChangesAsync(null, 2);
        LocalChangeFeedResponse second = await service.GetChangesAsync(first.NextCursor, 2);
        string json = JsonSerializer.Serialize(first);

        Assert.Equal(2, first.Changes.Count);
        Assert.True(first.HasMore);
        Assert.Single(second.Changes);
        Assert.False(second.HasMore);
        Assert.Equal(first.NextCursor, replay.NextCursor);
        Assert.Equal(
            first.Changes.Select(item => item.ChangeLogId),
            replay.Changes.Select(item => item.ChangeLogId));
        Assert.DoesNotContain(SourceId, first.NextCursor, StringComparison.Ordinal);
        Assert.Equal(
            first.Changes.Select(item => item.ChangeLogId)
                .Concat(second.Changes.Select(item => item.ChangeLogId))
                .OrderBy(id => id),
            first.Changes.Select(item => item.ChangeLogId)
                .Concat(second.Changes.Select(item => item.ChangeLogId)));
        Assert.Equal(3, first.Changes.Concat(second.Changes).Select(item => item.EntityId).Distinct().Count());
        Assert.Equal(SourceId, first.SourceId);
        Assert.Equal(time.GetUtcNow(), first.ServerTime);
        Assert.Contains("\"source_id\":\"local:synthetic-change-feed\"", json, StringComparison.Ordinal);
        Assert.Contains("\"server_time\":", json, StringComparison.Ordinal);
        Assert.Contains("\"next_cursor\":", json, StringComparison.Ordinal);
        Assert.Contains("\"has_more\":true", json, StringComparison.Ordinal);
    }

    [Fact]
    public async Task GetChangesAsync_WithIdenticalTimestamps_OrdersByChangeLogId()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        context.Expenses.AddRange(
            CreateExpense("SYNTHETIC-EXPENSE-1"),
            CreateExpense("SYNTHETIC-EXPENSE-2"),
            CreateExpense("SYNTHETIC-EXPENSE-3"));
        await context.SaveChangesAsync();
        LocalChangeFeedService service = CreateService(context, time);

        LocalChangeFeedResponse response = await service.GetChangesAsync(null, 10);

        Assert.Equal(3, response.Changes.Count);
        Assert.Equal(
            response.Changes.Select(item => item.ChangeLogId).OrderBy(id => id),
            response.Changes.Select(item => item.ChangeLogId));
        Assert.Single(response.Changes.Select(item => item.ChangedAtUtc).Distinct());
    }

    [Fact]
    public async Task GetChangesAsync_WithSoftDelete_ReturnsTombstoneWithoutLiveEntity()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        Guid voucherId = Guid.NewGuid();

        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            context.Vouchers.Add(CreateVoucher(voucherId));
            await context.SaveChangesAsync();
        }

        await using (LocalDbContext context = harness.CreateContext())
        {
            Voucher voucher = await context.Vouchers.SingleAsync(item => item.Id == voucherId);
            voucher.LastModifiedByDeviceId = "synthetic-device-delete";
            context.Vouchers.Remove(voucher);
            await context.SaveChangesAsync();
        }

        await using LocalDbContext readContext = harness.CreateContext();
        LocalChangeFeedService service = CreateService(readContext, time);
        LocalChangeFeedResponse response = await service.GetChangesAsync(null, 10);
        LocalChangeFeedItem deletion = response.Changes.Last();
        string deletionJson = JsonSerializer.Serialize(deletion);

        Assert.False(await readContext.Vouchers.AnyAsync(item => item.Id == voucherId));
        Assert.True(await readContext.Vouchers.IgnoreQueryFilters().AnyAsync(item => item.Id == voucherId));
        Assert.Equal(SyncOperationType.Delete, deletion.OperationType);
        Assert.Null(deletion.Entity);
        Assert.NotNull(deletion.Tombstone);
        Assert.Equal("synthetic-device-delete", deletion.Tombstone.DeletedByDeviceId);
        Assert.Equal(deletion.ChangedAtUtc, deletion.Tombstone.DeletedAtUtc);
        Assert.Contains("\"operation_type\":\"Delete\"", deletionJson, StringComparison.Ordinal);
        Assert.Contains("\"entity\":null", deletionJson, StringComparison.Ordinal);
        Assert.Contains("\"tombstone\":", deletionJson, StringComparison.Ordinal);
    }

    [Fact]
    public async Task SaveChangesAsync_WithStaleRowVersion_RejectsWithoutLosingCurrentUpdateOrAddingLog()
    {
        using LocalServerTestHarness harness = new();
        Guid operationId = Guid.NewGuid();

        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            context.AidOperations.Add(CreateAidOperation("SYNTHETIC-STALE", operationId));
            await context.SaveChangesAsync();
        }

        await using LocalDbContext staleContext = harness.CreateContext();
        await using LocalDbContext currentContext = harness.CreateContext();
        AidOperation stale = await staleContext.AidOperations.SingleAsync(item => item.Id == operationId);
        AidOperation current = await currentContext.AidOperations.SingleAsync(item => item.Id == operationId);
        current.Status = "CurrentUpdate";
        current.LastModifiedByDeviceId = "synthetic-device-current";
        await currentContext.SaveChangesAsync();

        stale.Status = "StaleOverwrite";
        stale.LastModifiedByDeviceId = "synthetic-device-stale";

        await Assert.ThrowsAsync<DbUpdateConcurrencyException>(() => staleContext.SaveChangesAsync());

        await using LocalDbContext verificationContext = harness.CreateContext();
        AidOperation stored = await verificationContext.AidOperations.SingleAsync(item => item.Id == operationId);
        List<LocalChangeLog> logs = await verificationContext.LocalChangeLogs
            .Where(item => item.EntityId == operationId)
            .OrderBy(item => item.ChangeLogId)
            .ToListAsync();

        Assert.Equal("CurrentUpdate", stored.Status);
        Assert.Equal(2, stored.RowVersion);
        Assert.Equal(2, logs.Count);
        Assert.DoesNotContain(logs, item => item.PayloadJson?.Contains("StaleOverwrite", StringComparison.Ordinal) == true);
        Assert.Contains(logs, item => item.PayloadJson?.Contains("CurrentUpdate", StringComparison.Ordinal) == true);
    }

    [Fact]
    public async Task SaveChangesAsync_WhenChangeLogInsertFails_RollsBackBusinessEntity()
    {
        using LocalServerTestHarness harness = new();
        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        DbContextOptionsBuilder<LocalDbContext> optionsBuilder = new();
        harness.Connections.Configure(optionsBuilder);
        optionsBuilder.AddInterceptors(new FailChangeLogInsertInterceptor());
        await using (LocalDbContext failingContext = new(optionsBuilder.Options, new TestTimeProvider()))
        {
            failingContext.Expenses.Add(CreateExpense("SYNTHETIC-ATOMIC"));
            await Assert.ThrowsAsync<InvalidOperationException>(() => failingContext.SaveChangesAsync());
        }

        await using LocalDbContext verificationContext = harness.CreateContext();

        Assert.Equal(0, await verificationContext.Expenses.CountAsync());
        Assert.Equal(0, await verificationContext.LocalChangeLogs.CountAsync());
    }

    [Fact]
    public async Task GetChangesAsync_WithCursorFromDifferentSource_RejectsCursor()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        context.AidOperations.Add(CreateAidOperation("SYNTHETIC-CURSOR"));
        await context.SaveChangesAsync();
        LocalChangeFeedResponse initial = await CreateService(context, time).GetChangesAsync(null, 1);
        LocalChangeFeedService otherSource = new(
            context,
            CreateSecurity("local:synthetic-other-source"),
            time);

        await Assert.ThrowsAsync<InvalidChangeCursorException>(
            () => otherSource.GetChangesAsync(initial.NextCursor, 1));
    }

    private static AidOperation CreateAidOperation(string syntheticReference, Guid? id = null) => new()
    {
        Id = id ?? Guid.NewGuid(),
        OperationId = Guid.NewGuid(),
        AidType = syntheticReference,
        EntitlementId = Guid.NewGuid(),
        Amount = 10.25m,
        CurrencyCode = "USD",
        Status = "Requested",
        OccurredAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        LastModifiedByDeviceId = DeviceId
    };

    private static Expense CreateExpense(string syntheticReference) => new()
    {
        Id = Guid.NewGuid(),
        OperationId = Guid.NewGuid(),
        Category = syntheticReference,
        SupplierId = Guid.NewGuid(),
        Amount = 5.50m,
        CurrencyCode = "USD",
        IncurredAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        LastModifiedByDeviceId = DeviceId
    };

    private static Voucher CreateVoucher(Guid id) => new()
    {
        Id = id,
        SequenceId = Guid.NewGuid(),
        SupplierId = Guid.NewGuid(),
        SerialNumber = null,
        BeneficiaryNameSnapshot = "Synthetic beneficiary",
        BeneficiaryIdentityNumberSnapshot = "SYNTHETIC-IDENTITY",
        Amount = 15m,
        CurrencyCode = "USD",
        Status = "Issued",
        IssuedAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        LastModifiedByDeviceId = DeviceId
    };

    private static LocalChangeFeedService CreateService(LocalDbContext context, TimeProvider time) =>
        new(context, CreateSecurity(SourceId), time);

    private static LocalServerSecurityOptions CreateSecurity(string sourceId) => new()
    {
        SourceId = new SyncSourceId(sourceId),
        ApiVersion = "1",
        HttpsPort = 8443,
        ServerCertificateThumbprint = "synthetic-server-thumbprint",
        TrustedRootCertificateThumbprint = "synthetic-root-thumbprint",
        AllowedClientSpkiPins = new[] { "synthetic-client-pin" }
    };

    private sealed class FailChangeLogInsertInterceptor : DbCommandInterceptor
    {
        public override InterceptionResult<int> CommandExecuting(
            DbCommand command,
            CommandEventData eventData,
            InterceptionResult<int> result)
        {
            ThrowForChangeLog(command);
            return base.CommandExecuting(command, eventData, result);
        }

        public override ValueTask<InterceptionResult<int>> CommandExecutingAsync(
            DbCommand command,
            CommandEventData eventData,
            InterceptionResult<int> result,
            CancellationToken cancellationToken = default)
        {
            ThrowForChangeLog(command);
            return base.CommandExecutingAsync(command, eventData, result, cancellationToken);
        }

        private static void ThrowForChangeLog(DbCommand command)
        {
            if (command.CommandText.Contains("INSERT INTO \"local_change_logs\"", StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException("Synthetic change-log insertion failure.");
            }
        }
    }
}
