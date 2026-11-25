using System.Collections.Concurrent;
using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class LoadTests
{
    private const string SourceId = "local:synthetic-load-021";
    private static readonly TimeSpan LoadTimeout = TimeSpan.FromSeconds(90);

    [Fact]
    public async Task ConcurrentDevices_WithIdempotentRetries_PreserveEveryRowTotalVersionAndFeed()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        const int deviceCount = 4;
        const int operationsPerDevice = 5;
        const int expectedCount = deviceCount * operationsPerDevice;
        const decimal expectedTotal = 345.00m;
        List<SyntheticRequest> requests = CreateRequests(deviceCount, operationsPerDevice);

        await using (LocalDbContext setup = harness.CreateContext())
        {
            await setup.Database.MigrateAsync();
            (_, IdempotencyService registrar) = OperationsTests.CreateServices(setup, time, new RecordingSyncNotifier());
            foreach (SyntheticRequest request in requests)
            {
                await OperationsTests.RegisterResolvedIdentityAsync(registrar, request.Envelope.Entity);
            }
        }

        using CancellationTokenSource timeout = new(LoadTimeout);
        using Barrier startBarrier = new(deviceCount);
        Task[] devices = requests
            .GroupBy(request => request.DeviceId)
            .Select(group => Task.Run(async () =>
            {
                startBarrier.SignalAndWait(timeout.Token);
                await RunDeviceAsync(harness, time, group.ToArray(), timeout.Token);
            }, timeout.Token))
            .ToArray();
        await Task.WhenAll(devices).WaitAsync(LoadTimeout, timeout.Token);

        await using LocalDbContext verification = harness.CreateContext();
        AidOperation[] rows = await verification.AidOperations
            .AsNoTracking()
            .OrderBy(row => row.Id)
            .ToArrayAsync(timeout.Token);
        LocalChangeFeedItem[] feed = await ReadAllFeedAsync(verification, time, timeout.Token);

        Assert.Equal(expectedCount, rows.Length);
        Assert.Equal(expectedTotal, rows.Sum(row => row.Amount));
        Assert.Equal(expectedCount, rows.Select(row => row.OperationId).Distinct().Count());
        Assert.All(rows, row => Assert.Equal(1, row.RowVersion));
        Assert.Equal(expectedCount, await verification.IdempotencyRecords.CountAsync(timeout.Token));
        Assert.Equal(expectedCount, feed.Length);
        Assert.Equal(expectedCount, feed.Select(change => change.EntityId).Distinct().Count());
        Assert.Equal(rows.Select(row => row.Id).OrderBy(id => id), feed.Select(change => change.EntityId).OrderBy(id => id));
        Assert.All(feed, change => Assert.Equal(1, change.RowVersion));
        Assert.Equal("ok", await ReadIntegrityCheckAsync(harness, harness.Storage.DatabasePath, timeout.Token));
    }

    [Fact]
    public async Task ClientDisconnect_BeforeCommit_RollsBackThenIdempotentRetryCommitsOnce()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        SyntheticRequest request = CreateRequests(1, 1)[0];
        using CancellationTokenSource timeout = new(TimeSpan.FromSeconds(30));

        await using (LocalDbContext setup = harness.CreateContext())
        {
            await setup.Database.MigrateAsync(timeout.Token);
            (_, IdempotencyService registrar) = OperationsTests.CreateServices(setup, time, new RecordingSyncNotifier());
            await OperationsTests.RegisterResolvedIdentityAsync(registrar, request.Envelope.Entity);
        }

        using (CancellationTokenSource disconnect = CancellationTokenSource.CreateLinkedTokenSource(timeout.Token))
        {
            await using LocalDbContext disconnectedContext = harness.CreateContext();
            (_, IdempotencyService disconnectedService) = OperationsTests.CreateServices(
                disconnectedContext,
                time,
                new RecordingSyncNotifier());

            await Assert.ThrowsAnyAsync<OperationCanceledException>(() => disconnectedService.ExecuteAsync(
                request.OperationId,
                request.IdempotencyKey,
                request.DeviceId,
                "aid.create",
                request.Envelope,
                (db, envelope, _) =>
                {
                    db.AidOperations.Add(CreateAid(envelope, request.DeviceId, request.Amount));
                    disconnect.Cancel();
                    return Task.FromResult(LocalBusinessResult.Completed(envelope.Entity.ClientUuid, 1));
                },
                disconnect.Token));
        }

        await using (LocalDbContext afterDisconnect = harness.CreateContext())
        {
            Assert.Equal(0, await afterDisconnect.AidOperations.CountAsync(timeout.Token));
            Assert.Equal(0, await afterDisconnect.IdempotencyRecords.CountAsync(timeout.Token));
            Assert.Equal(0, await afterDisconnect.LocalChangeLogs.CountAsync(timeout.Token));
        }

        LocalOperationResult committed;
        await using (LocalDbContext retryContext = harness.CreateContext())
        {
            (_, IdempotencyService retryService) = OperationsTests.CreateServices(retryContext, time, new RecordingSyncNotifier());
            committed = await retryService.ExecuteAsync(
                request.OperationId,
                request.IdempotencyKey,
                request.DeviceId,
                "aid.create",
                request.Envelope,
                (db, envelope, _) =>
                {
                    db.AidOperations.Add(CreateAid(envelope, request.DeviceId, request.Amount));
                    return Task.FromResult(LocalBusinessResult.Completed(envelope.Entity.ClientUuid, 1));
                },
                timeout.Token);
        }

        await using LocalDbContext verification = harness.CreateContext();
        (_, IdempotencyService replayService) = OperationsTests.CreateServices(verification, time, new RecordingSyncNotifier());
        LocalOperationResult replay = await replayService.ExecuteAsync(
            request.OperationId,
            request.IdempotencyKey,
            request.DeviceId,
            "aid.create",
            request.Envelope,
            (_, _, _) => throw new InvalidOperationException("A replay must not execute the business action."),
            timeout.Token);

        Assert.Equal(committed, replay);
        Assert.Equal(1, await verification.AidOperations.CountAsync(timeout.Token));
        Assert.Equal(1, await verification.IdempotencyRecords.CountAsync(timeout.Token));
        Assert.Single(await ReadAllFeedAsync(verification, time, timeout.Token));
    }

    [Fact]
    public async Task ServiceRestart_WithPersistedOperationAndCursor_PreservesChecksumAndDoesNotDuplicateWork()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        SyntheticRequest request = CreateRequests(1, 1)[0];
        LocalOperationResult original;
        string checksumBeforeRestart;
        string cursor;
        using CancellationTokenSource timeout = new(TimeSpan.FromSeconds(30));

        await using (LocalDbContext initialContext = harness.CreateContext())
        {
            await initialContext.Database.MigrateAsync(timeout.Token);
            (_, IdempotencyService initialService) = OperationsTests.CreateServices(initialContext, time, new RecordingSyncNotifier());
            await OperationsTests.RegisterResolvedIdentityAsync(initialService, request.Envelope.Entity);
            original = await initialService.ExecuteAsync(
                request.OperationId,
                request.IdempotencyKey,
                request.DeviceId,
                "aid.create",
                request.Envelope,
                (db, envelope, _) =>
                {
                    db.AidOperations.Add(CreateAid(envelope, request.DeviceId, request.Amount));
                    return Task.FromResult(LocalBusinessResult.Completed(envelope.Entity.ClientUuid, 1));
                },
                timeout.Token);
            checksumBeforeRestart = await ComputeOperationalChecksumAsync(initialContext, timeout.Token);
            LocalChangeFeedResponse initialFeed = await CreateFeedService(initialContext, time)
                .GetChangesAsync(null, 10, timeout.Token);
            cursor = initialFeed.NextCursor;
            Assert.Single(initialFeed.Changes);
        }

        LocalDbConnectionFactory restartedConnections = new(harness.Storage, harness.KeyProvider);
        await using LocalDbContext restartedContext = new(restartedConnections.CreateOptions(), time);
        (_, IdempotencyService restartedService) = OperationsTests.CreateServices(
            restartedContext,
            time,
            new RecordingSyncNotifier());

        LocalOperationResult replay = await restartedService.ExecuteAsync(
            request.OperationId,
            request.IdempotencyKey,
            request.DeviceId,
            "aid.create",
            request.Envelope,
            (_, _, _) => throw new InvalidOperationException("A replay must not execute the business action."),
            timeout.Token);
        string checksumAfterRestart = await ComputeOperationalChecksumAsync(restartedContext, timeout.Token);
        LocalChangeFeedResponse changesAfterCursor = await CreateFeedService(restartedContext, time)
            .GetChangesAsync(cursor, 10, timeout.Token);

        Assert.Equal(original, replay);
        Assert.Equal(checksumBeforeRestart, checksumAfterRestart);
        Assert.Empty(changesAfterCursor.Changes);
        Assert.False(changesAfterCursor.HasMore);
        Assert.Equal(1, await restartedContext.AidOperations.CountAsync(timeout.Token));
        Assert.Equal(1, await restartedContext.LocalChangeLogs.CountAsync(timeout.Token));
    }

    [Fact]
    public async Task DeviceLifecycleLogging_WithSyntheticSecrets_OmitsCredentialsAndUserIdentifiers()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        CapturingLoggerProvider provider = new();
        using ILoggerFactory loggerFactory = LoggerFactory.Create(builder => builder
            .SetMinimumLevel(LogLevel.Information)
            .AddProvider(provider));
        DeviceRegistryService service = new(
            context,
            new TestTimeProvider(),
            loggerFactory.CreateLogger<DeviceRegistryService>());
        const string syntheticUserId = "synthetic-private-user-021";

        DeviceEnrollmentIssue enrollment = await service.CreateEnrollmentAsync(new DeviceEnrollmentCommand(
            syntheticUserId,
            LocalAccess.Read | LocalAccess.Operations,
            LocalAccess.Read));
        DeviceCredentialIssue credential = (await service.CompleteEnrollmentAsync(
            new DeviceEnrollmentRedemption(enrollment.DeviceId, enrollment.EnrollmentCode)))!;
        Assert.NotNull(await service.AuthenticateAsync(new DeviceAuthenticationRequest(
            enrollment.DeviceId,
            syntheticUserId,
            credential.Credential)));

        string[] messages = provider.Messages.ToArray();
        Assert.NotEmpty(messages);
        Assert.All(messages, message =>
        {
            Assert.DoesNotContain(enrollment.EnrollmentCode, message, StringComparison.Ordinal);
            Assert.DoesNotContain(credential.Credential, message, StringComparison.Ordinal);
            Assert.DoesNotContain(syntheticUserId, message, StringComparison.Ordinal);
            Assert.DoesNotContain(harness.SyntheticKeyHex, message, StringComparison.Ordinal);
        });
    }

    private static async Task RunDeviceAsync(
        LocalServerTestHarness harness,
        TimeProvider time,
        IReadOnlyList<SyntheticRequest> requests,
        CancellationToken cancellationToken)
    {
        await using LocalDbContext context = harness.CreateContext();
        (_, IdempotencyService service) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());

        foreach (SyntheticRequest request in requests)
        {
            Task<LocalOperationResult> Execute() => service.ExecuteAsync(
                request.OperationId,
                request.IdempotencyKey,
                request.DeviceId,
                "aid.create",
                request.Envelope,
                (db, envelope, _) =>
                {
                    db.AidOperations.Add(CreateAid(envelope, request.DeviceId, request.Amount));
                    return Task.FromResult(LocalBusinessResult.Completed(envelope.Entity.ClientUuid, 1));
                },
                cancellationToken);

            LocalOperationResult first = await Execute();
            LocalOperationResult replay = await Execute();
            Assert.Equal(LocalOperationStatus.Completed, first.Status);
            Assert.Equal(first, replay);
        }
    }

    private static List<SyntheticRequest> CreateRequests(int deviceCount, int operationsPerDevice)
    {
        List<SyntheticRequest> requests = new();
        for (int deviceIndex = 0; deviceIndex < deviceCount; deviceIndex++)
        {
            string deviceId = $"synthetic-load-device-{deviceIndex:D2}";
            for (int operationIndex = 0; operationIndex < operationsPerDevice; operationIndex++)
            {
                Guid operationId = Guid.NewGuid();
                Guid entityId = Guid.NewGuid();
                requests.Add(new SyntheticRequest(
                    operationId,
                    $"synthetic-load-key-{deviceIndex:D2}-{operationIndex:D2}",
                    deviceId,
                    LocalOperationTestFactory.CreateEnvelope(operationId, entityId),
                    deviceIndex * 10 + operationIndex + 0.25m));
            }
        }

        return requests;
    }

    private static AidOperation CreateAid(SyncEnvelope envelope, string deviceId, decimal amount) => new()
    {
        Id = envelope.Entity.ClientUuid,
        OperationId = envelope.OperationId,
        AidType = "SyntheticLoad",
        EntitlementId = Guid.NewGuid(),
        Amount = amount,
        CurrencyCode = "USD",
        Status = "Requested",
        OccurredAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        LastModifiedByDeviceId = deviceId
    };

    private static async Task<LocalChangeFeedItem[]> ReadAllFeedAsync(
        LocalDbContext context,
        TimeProvider time,
        CancellationToken cancellationToken)
    {
        LocalChangeFeedService service = CreateFeedService(context, time);
        List<LocalChangeFeedItem> changes = new();
        string? cursor = null;
        int pageCount = 0;

        do
        {
            LocalChangeFeedResponse page = await service.GetChangesAsync(cursor, 7, cancellationToken);
            changes.AddRange(page.Changes);
            cursor = page.NextCursor;
            pageCount++;
            Assert.True(pageCount <= 100, "The change feed did not terminate within the page budget.");
        }
        while (pageCount <= 100 && changes.Count < await context.LocalChangeLogs.CountAsync(cancellationToken));

        LocalChangeFeedResponse finalPage = await service.GetChangesAsync(cursor, 7, cancellationToken);
        Assert.Empty(finalPage.Changes);
        return changes.ToArray();
    }

    private static LocalChangeFeedService CreateFeedService(LocalDbContext context, TimeProvider time) => new(
        context,
        new LocalServerSecurityOptions
        {
            SourceId = new SyncSourceId(SourceId),
            ApiVersion = "1",
            HttpsPort = 8443,
            ServerCertificateThumbprint = new string('A', 40),
            TrustedRootCertificateThumbprint = new string('B', 40),
            AllowedClientSpkiPins = new[] { Convert.ToBase64String(new byte[32]) }
        },
        time);

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

    private static async Task<string> ComputeOperationalChecksumAsync(
        LocalDbContext context,
        CancellationToken cancellationToken)
    {
        AidOperation[] rows = await context.AidOperations
            .AsNoTracking()
            .OrderBy(row => row.Id)
            .ToArrayAsync(cancellationToken);
        LocalChangeLog[] changes = await context.LocalChangeLogs
            .AsNoTracking()
            .OrderBy(change => change.ChangeLogId)
            .ToArrayAsync(cancellationToken);
        string canonical = string.Join("\n", rows.Select(row => string.Join(
            '|',
            row.Id.ToString("N"),
            row.OperationId.ToString("N"),
            row.RowVersion.ToString(CultureInfo.InvariantCulture),
            row.ConcurrencyToken.ToString("N"),
            row.Amount.ToString(CultureInfo.InvariantCulture),
            row.Status,
            row.LastModifiedByDeviceId)));
        canonical += "\n--changes--\n" + string.Join("\n", changes.Select(change => string.Join(
            '|',
            change.ChangeLogId.ToString(CultureInfo.InvariantCulture),
            change.EntityId.ToString("N"),
            change.OperationType,
            change.RowVersion.ToString(CultureInfo.InvariantCulture),
            change.ConcurrencyToken.ToString("N"),
            change.PayloadJson)));
        return Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(canonical)));
    }

    private sealed record SyntheticRequest(
        Guid OperationId,
        string IdempotencyKey,
        string DeviceId,
        SyncEnvelope Envelope,
        decimal Amount);

    private sealed class CapturingLoggerProvider : ILoggerProvider
    {
        private readonly ConcurrentQueue<string> _messages = new();

        public IEnumerable<string> Messages => _messages;

        public ILogger CreateLogger(string categoryName) => new CapturingLogger(this);

        public void Dispose()
        {
        }

        private void Add(string message) => _messages.Enqueue(message);

        private sealed class CapturingLogger(CapturingLoggerProvider provider) : ILogger
        {
            public IDisposable BeginScope<TState>(TState state) where TState : notnull => NullScope.Instance;

            public bool IsEnabled(LogLevel logLevel) => logLevel >= LogLevel.Information;

            public void Log<TState>(
                LogLevel logLevel,
                EventId eventId,
                TState state,
                Exception? exception,
                Func<TState, Exception?, string> formatter)
            {
                if (IsEnabled(logLevel))
                {
                    provider.Add(formatter(state, exception) + exception?.ToString());
                }
            }
        }

        private sealed class NullScope : IDisposable
        {
            public static readonly NullScope Instance = new();

            public void Dispose()
            {
            }
        }
    }
}
