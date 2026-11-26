using System.Net;
using System.Net.Security;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Alhayah.SyncService.Configuration;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Identity;
using Alhayah.SyncService.Outbox;
using Alhayah.SyncService.Realtime;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class LocalConnectorTests
{
    private const string SourceId = "local:synthetic-a";
    private const string LaravelSourceId = "laravel:synthetic-a";
    private const string DeviceId = "synthetic-local-device";
    private const string UserId = "synthetic-local-user";
    private const string Credential = "synthetic-local-credential-that-must-not-leak";

    [Fact]
    public async Task HealthCapabilitiesAndConnectionCheck_UseActualLocalRoutesAndAuthentication()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery == "/health")
            {
                return JsonResponse(HealthJson());
            }

            if (request.PathAndQuery == "/capabilities")
            {
                return JsonResponse(CapabilitiesJson());
            }

            if (request.Method == HttpMethod.Get && request.PathAndQuery == "/local/changes?limit=1")
            {
                return JsonResponse(FeedJson("probe-cursor", false));
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });

        LocalServerHealthResponse health = await fixture.Connector.GetHealthAsync(CancellationToken.None);
        LocalServerCapabilitiesResponse capabilities = await fixture.Connector.GetCapabilitiesAsync(CancellationToken.None);
        ConnectionCheckResult check = await fixture.Connector.CheckConnectionAsync(CancellationToken.None);

        Assert.Equal("ok", health.Status);
        Assert.Equal(SourceId, health.SourceId);
        Assert.Equal("independent-device-credential", capabilities.CredentialType);
        Assert.Equal("https-required", capabilities.Transport);
        Assert.Equal(["DNS", "TCP", "TLS", "Auth", "Compatibility"], check.Stages.Select(stage => stage.Stage));
        Assert.All(check.Stages, stage => Assert.True(stage.Succeeded));
        Assert.All(fixture.Handler.Requests.Where(request =>
            request.PathAndQuery is "/health" or "/capabilities"), request =>
        {
            Assert.False(request.Headers.ContainsKey("Authorization"));
            Assert.False(request.Headers.ContainsKey("X-Alhayah-Device-Id"));
            Assert.False(request.Headers.ContainsKey("X-Alhayah-User-Id"));
        });
        RecordedRequest authenticated = Assert.Single(fixture.Handler.Requests.Where(request =>
            request.PathAndQuery == "/local/changes?limit=1"));
        Assert.Equal($"Device {Credential}", authenticated.Header("Authorization"));
        Assert.Equal(DeviceId, authenticated.Header("X-Alhayah-Device-Id"));
        Assert.Equal(UserId, authenticated.Header("X-Alhayah-User-Id"));
    }

    [Fact]
    public async Task SignalRFailure_LeavesPollingFallbackAbleToPushAndPull()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.Method == HttpMethod.Post && request.PathAndQuery == "/local/operations/aid")
            {
                return JsonResponse(OperationResultJson(operationId, clientUuid, "Completed"));
            }

            if (request.Method == HttpMethod.Get && request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson("cursor-20", false));
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);
        fixture.Hub.StartException = new InvalidOperationException("Synthetic SignalR outage.");

        await fixture.Coordinator.StartAsync(CancellationToken.None);
        SynchronizeNowResult result = await fixture.Coordinator.PollOnceAsync(CancellationToken.None);

        Assert.True(fixture.Coordinator.UsingPollingFallback);
        Assert.Equal("Completed", result.Status);
        Assert.Equal("Sent", await OutboxStatusAsync(fixture, operationId));
        Assert.Equal("cursor-20", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        Assert.Contains(fixture.Handler.Requests, request => request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal));
    }

    [Fact]
    public async Task SignalRNotification_IsOnlyPollingHintAndNotCommitEvidence()
    {
        Guid clientUuid = Guid.NewGuid();
        bool feedAvailable = false;
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal) && feedAvailable)
            {
                return JsonResponse(FeedJson(
                    "cursor-31",
                    false,
                    ChangeJson(31, clientUuid, "Create", "{\"aidType\":\"Synthetic remote aid\"}")));
            }

            if (request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse("{\"message\":\"synthetic unavailable\"}", HttpStatusCode.ServiceUnavailable);
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });
        await fixture.Coordinator.StartAsync(CancellationToken.None);

        await fixture.Hub.EmitAsync();

        Assert.Null((await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            Assert.False(await context.CachedEntities.AnyAsync(entity => entity.ClientUuid == clientUuid));
        }

        feedAvailable = true;
        await fixture.Hub.EmitAsync();

        Assert.Equal("cursor-31", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        Assert.Single(await verification.CachedEntities.Where(entity => entity.ClientUuid == clientUuid).ToArrayAsync());
    }

    [Fact]
    public async Task PullAsync_WithCursorPagesAndTombstone_CommitsPagesAndReplayHasNoDuplicateEffect()
    {
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.Contains("cursor=cursor-10", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson(
                    "cursor-11",
                    true,
                    ChangeJson(11, clientUuid, "Create", "{\"aidType\":\"Synthetic remote aid\",\"currencyCode\":\"USD\"}")));
            }

            if (request.PathAndQuery.Contains("cursor=cursor-11", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson(
                    "cursor-12",
                    false,
                    ChangeJson(12, clientUuid, "Delete", null, includeTombstone: true)));
            }

            return JsonResponse("{}", HttpStatusCode.BadRequest);
        });
        await fixture.Cursors.CommitPageAsync(
            SourceId,
            "local-operations",
            EmptyPage(10, "cursor-10"),
            CancellationToken.None);

        LocalPullResult result = await fixture.Connector.PullAsync(CancellationToken.None);
        CursorCommitResult replay = await fixture.Cursors.CommitPageAsync(
            SourceId,
            "local-operations",
            EmptyPage(12, "cursor-12"),
            CancellationToken.None);

        Assert.Equal(2, result.PageCount);
        Assert.Equal(2, result.Changes.Count);
        Assert.Equal(CursorCommitResult.AlreadyCommitted, replay);
        Assert.Equal("remote-device", result.Changes[1].Tombstone!.DeletedByDeviceId);
        Assert.Equal("cursor-12", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        Assert.Equal(2, fixture.Handler.Requests.Count);
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        CachedEntity deleted = await context.CachedEntities.IgnoreQueryFilters().SingleAsync(entity => entity.ClientUuid == clientUuid);
        Assert.Equal("12", deleted.RowVersion);
        Assert.Equal("remote-device", deleted.DeletedByDeviceId);
        Assert.NotNull(deleted.DeletedAtUtc);
    }

    [Fact]
    public async Task PullAsync_WithCanonicalIdUsedByLaravel_KeepsLocalIdentitySourceQualified()
    {
        Guid localUuid = Guid.NewGuid();
        Guid laravelUuid = Guid.NewGuid();
        string sharedCanonicalId = localUuid.ToString("D");
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse(FeedJson(
            "cursor-40",
            false,
            ChangeJson(40, localUuid, "Create", "{\"aidType\":\"Synthetic isolated aid\"}"))));
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            context.CachedEntities.Add(new CachedEntity
            {
                ClientUuid = laravelUuid,
                SourceId = LaravelSourceId,
                EntityType = "AidOperation",
                CanonicalId = sharedCanonicalId,
                PayloadJson = "{\"aidType\":\"Synthetic Laravel aid\"}",
                SearchTextNormalized = "SYNTHETIC LARAVEL AID",
                RowVersion = "laravel-row",
                CreatedAtUtc = DateTimeOffset.Parse("2026-11-25T09:00:00Z"),
                UpdatedAtUtc = DateTimeOffset.Parse("2026-11-25T09:00:00Z")
            });
            context.ClientIdentityMap.Add(new ClientIdentityMappingEntity
            {
                ClientUuid = laravelUuid,
                EntityType = "AidOperation",
                CanonicalId = sharedCanonicalId,
                CreatedAtUtc = DateTimeOffset.Parse("2026-11-25T09:00:00Z"),
                ResolvedAtUtc = DateTimeOffset.Parse("2026-11-25T09:00:00Z")
            });
            await context.SaveChangesAsync();
        }

        await fixture.Connector.PullAsync(CancellationToken.None);

        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        Assert.Equal(
            $"local:{localUuid:D}",
            (await verification.CachedEntities.SingleAsync(entity =>
                entity.SourceId == SourceId && entity.ClientUuid == localUuid)).CanonicalId);
        Assert.Equal(
            sharedCanonicalId,
            (await verification.CachedEntities.SingleAsync(entity =>
                entity.SourceId == LaravelSourceId && entity.ClientUuid == laravelUuid)).CanonicalId);
        Assert.Equal(2, await verification.ClientIdentityMap.CountAsync());
    }

    [Fact]
    public async Task PullAsync_WithRejectedCursor_RestartsFullChangeFeedReplay()
    {
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.Contains("cursor=expired-cursor", StringComparison.Ordinal))
            {
                return JsonResponse("{\"message\":\"full reconciliation required\"}", HttpStatusCode.BadRequest);
            }

            if (request.PathAndQuery == "/local/changes?limit=2")
            {
                return JsonResponse(FeedJson(
                    "replay-31",
                    false,
                    ChangeJson(31, clientUuid, "Create", "{\"aidType\":\"Synthetic replayed aid\"}")));
            }

            return JsonResponse("{}", HttpStatusCode.BadRequest);
        });
        await fixture.Cursors.CommitPageAsync(
            SourceId,
            "local-operations",
            EmptyPage(5, "expired-cursor"),
            CancellationToken.None);

        LocalPullResult result = await fixture.Connector.PullAsync(CancellationToken.None);

        Assert.Single(result.Changes);
        Assert.Equal(1, result.PageCount);
        Assert.Equal("replay-31", result.Cursor.Cursor);
        Assert.Equal(ReconciliationPhase.Reconciled, result.Cursor.Phase);
        Assert.False(result.Cursor.ReconciliationRequired);
        Assert.Contains(fixture.Handler.Requests, request =>
            request.PathAndQuery.Contains("cursor=expired-cursor", StringComparison.Ordinal));
        Assert.Contains(fixture.Handler.Requests, request => request.PathAndQuery == "/local/changes?limit=2");
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        Assert.Single(await context.CachedEntities.Where(entity => entity.ClientUuid == clientUuid).ToArrayAsync());
    }

    [Fact]
    public async Task PullAsync_WhenLaterCursorRejected_DiscardsEarlierResultDuplicates()
    {
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.Contains("cursor=cursor-10", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson(
                    "expired-next",
                    true,
                    ChangeJson(11, clientUuid, "Create", "{\"aidType\":\"Synthetic partial replay\"}")));
            }

            if (request.PathAndQuery.Contains("cursor=expired-next", StringComparison.Ordinal))
            {
                return JsonResponse("{\"message\":\"full reconciliation required\"}", HttpStatusCode.BadRequest);
            }

            return JsonResponse(FeedJson(
                "replay-complete",
                false,
                ChangeJson(11, clientUuid, "Create", "{\"aidType\":\"Synthetic partial replay\"}")));
        });
        await fixture.Cursors.CommitPageAsync(
            SourceId,
            "local-operations",
            new RemoteChangePage
            {
                NextCursor = "cursor-10",
                LastChangeLogId = 10,
                CompletesReconciliation = false,
                Changes = []
            },
            CancellationToken.None);

        LocalPullResult result = await fixture.Connector.PullAsync(CancellationToken.None);

        Assert.Single(result.Changes);
        Assert.Equal(1, result.PageCount);
        Assert.Equal("replay-complete", result.Cursor.Cursor);
        Assert.Equal(3, fixture.Handler.Requests.Count);
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        Assert.Single(await context.CachedEntities.Where(entity => entity.ClientUuid == clientUuid).ToArrayAsync());
    }

    [Fact]
    public async Task PullAsync_WhenPageCommitFails_LeavesCursorForRetryWithoutDuplicateEffect()
    {
        CommitCrashInterceptor crash = new();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(
            _ => JsonResponse(FeedJson(
                "cursor-21",
                false,
                ChangeJson(21, Guid.Empty, "Create", "{\"aidType\":\"Synthetic committed aid\"}"))),
            crash);
        Guid clientUuid = Guid.NewGuid();
        fixture.Handler.Responder = _ => JsonResponse(FeedJson(
            "cursor-21",
            false,
            ChangeJson(21, clientUuid, "Create", "{\"aidType\":\"Synthetic committed aid\"}")));
        crash.ArmBeforeCommit();

        await Assert.ThrowsAsync<IOException>(() => fixture.Connector.PullAsync(CancellationToken.None));

        Assert.Null((await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            Assert.False(await context.CachedEntities.AnyAsync(entity => entity.ClientUuid == clientUuid));
        }

        await fixture.Connector.PullAsync(CancellationToken.None);

        Assert.Equal("cursor-21", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        Assert.Single(await verification.CachedEntities.Where(entity => entity.ClientUuid == clientUuid).ToArrayAsync());
    }

    [Fact]
    public async Task PushAsync_WithRateLimitThenSuccess_ReplaysIdenticalOperationWithoutDuplicateEffect()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        int calls = 0;
        int effectCount = 0;
        HashSet<Guid> applied = [];
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
        {
            calls++;
            if (applied.Add(operationId))
            {
                effectCount++;
            }

            if (calls == 1)
            {
                HttpResponseMessage response = JsonResponse("{\"retry_after\":0}", (HttpStatusCode)429);
                response.Headers.RetryAfter = new System.Net.Http.Headers.RetryConditionHeaderValue(TimeSpan.Zero);
                return response;
            }

            return JsonResponse(OperationResultJson(operationId, clientUuid, "Completed"));
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);

        LocalOperationResult result = await fixture.Connector.PushAsync(
            await LoadEnvelopeAsync(fixture, operationId),
            CancellationToken.None);

        Assert.Equal(LocalOperationStatus.Completed, result.Status);
        Assert.Equal(2, calls);
        Assert.Equal(1, effectCount);
        Assert.Equal(fixture.Handler.Requests[0].BodyText, fixture.Handler.Requests[1].BodyText);
        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests[0].BodyText!);
        Assert.Equal(operationId, body.RootElement.GetProperty("operationId").GetGuid());
        Assert.Equal($"desktop:{operationId:N}", body.RootElement.GetProperty("idempotencyKey").GetString());
        Assert.Equal(operationId, body.RootElement.GetProperty("envelope").GetProperty("operationId").GetGuid());
        Assert.Equal(
            $"local:{clientUuid:D}",
            body.RootElement.GetProperty("envelope").GetProperty("entity").GetProperty("canonicalId").GetString());
        Assert.Equal("/local/operations/aid", fixture.Handler.Requests[0].PathAndQuery);
    }

    [Fact]
    public async Task PushAsync_WhenCachedPayloadChangesAfterFailure_RetriesOriginalRequestSnapshot()
    {
        Guid operationId = Guid.NewGuid();
        Guid patchOperationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        bool retryAvailable = false;
        int completedEffects = 0;
        HashSet<Guid> completedOperations = [];
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
        {
            if (!retryAvailable)
            {
                return JsonResponse("{\"message\":\"synthetic unavailable\"}", HttpStatusCode.ServiceUnavailable);
            }

            if (completedOperations.Add(operationId))
            {
                completedEffects++;
            }

            return JsonResponse(OperationResultJson(operationId, clientUuid, "Completed"));
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);
        SyncEnvelope createEnvelope = await LoadEnvelopeAsync(fixture, operationId);

        await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(createEnvelope, CancellationToken.None));

        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            CachedEntity cached = await context.CachedEntities.SingleAsync(entity => entity.ClientUuid == clientUuid);
            SyncEnvelope patchEnvelope = LocalEnvelope(patchOperationId, clientUuid, "AidOperation") with
            {
                OperationType = SyncOperationType.Update,
                RowVersion = cached.RowVersion,
                OccurredAtUtc = cached.UpdatedAtUtc.AddSeconds(1),
                Patches =
                [
                    new FieldPatch
                    {
                        FieldName = "aidType",
                        LocalValue = JsonSerializer.SerializeToElement("SyntheticAidPatched"),
                        Criticality = FieldCriticality.Mergeable
                    }
                ]
            };
            await new UnitOfWork(context).PatchAsync(
                patchEnvelope,
                "SyntheticAidPatched",
                new LocalAuditContext(null, "synthetic.write", 1));
        }

        retryAvailable = true;
        LocalOperationResult result = await fixture.Connector.PushAsync(createEnvelope, CancellationToken.None);

        Assert.Equal(LocalOperationStatus.Completed, result.Status);
        Assert.Equal(1, completedEffects);
        Assert.Equal(4, fixture.Handler.Requests.Count);
        Assert.All(fixture.Handler.Requests, request =>
            Assert.Equal(fixture.Handler.Requests[0].BodyText, request.BodyText));
        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests[0].BodyText!);
        Assert.Equal("SyntheticAid", body.RootElement.GetProperty("aidType").GetString());
        Assert.Equal(operationId, body.RootElement.GetProperty("operationId").GetGuid());
        Assert.Equal($"desktop:{operationId:N}", body.RootElement.GetProperty("idempotencyKey").GetString());
    }

    [Fact]
    public async Task PushAsync_WithVoucherRedemption_SendsVersionReferencesAndStableIdentity()
    {
        Guid operationId = Guid.NewGuid();
        Guid redemptionId = Guid.NewGuid();
        Guid voucherId = Guid.NewGuid();
        Guid concurrencyToken = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse(OperationResultJson(operationId, redemptionId, "Completed")));
        SyncEnvelope envelope = LocalEnvelope(operationId, redemptionId, "VoucherRedemption") with
        {
            BaseVersion = 7,
            RowVersion = concurrencyToken.ToString("D"),
            Entity = new EntityDescriptor
            {
                EntityType = "VoucherRedemption",
                ClientUuid = redemptionId,
                CanonicalId = null,
                References =
                [
                    new EntityReference
                    {
                        FieldName = "voucherId",
                        TargetEntityType = "Voucher",
                        TargetClientUuid = voucherId,
                        TargetCanonicalId = $"local:{voucherId:D}",
                        Kind = EntityReferenceKind.Field
                    }
                ]
            }
        };
        string payload = JsonSerializer.Serialize(new
        {
            voucherId,
            expectedRowVersion = 7,
            expectedConcurrencyToken = concurrencyToken,
            amount = 15.25m,
            currencyCode = "USD",
            redeemedAtUtc = DateTimeOffset.Parse("2026-11-25T10:00:00Z"),
            redemptionReference = "synthetic-redemption"
        });
        await SeedLocalEntityAsync(fixture.Harness, envelope, payload, "synthetic-redemption");

        await fixture.Connector.PushAsync(envelope, CancellationToken.None);

        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests.Single().BodyText!);
        JsonElement root = body.RootElement;
        Assert.Equal("/local/vouchers/redeem", fixture.Handler.Requests.Single().PathAndQuery);
        Assert.Equal(operationId, root.GetProperty("operationId").GetGuid());
        Assert.Equal(voucherId, root.GetProperty("voucherId").GetGuid());
        Assert.Equal(7, root.GetProperty("expectedRowVersion").GetInt64());
        Assert.Equal(concurrencyToken, root.GetProperty("expectedConcurrencyToken").GetGuid());
        Assert.Equal(voucherId, root.GetProperty("envelope").GetProperty("entity").GetProperty("references")[0]
            .GetProperty("targetClientUuid").GetGuid());
        Assert.Equal(7, root.GetProperty("envelope").GetProperty("baseVersion").GetUInt64());
    }

    [Theory]
    [InlineData(HttpStatusCode.Unauthorized, "unauthorized")]
    [InlineData(HttpStatusCode.Forbidden, "forbidden")]
    public async Task PushAsync_WithAuthenticationFailure_DoesNotRetryAndLeavesOutboxPending(
        HttpStatusCode statusCode,
        string expectedError)
    {
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse("{\"error\":\"synthetic-auth-failure\"}", statusCode));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalSyncException exception = await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(envelope, CancellationToken.None));

        Assert.Equal(expectedError, exception.ErrorCode);
        Assert.False(exception.IsTransient);
        Assert.Single(fixture.Handler.Requests);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
    }

    [Theory]
    [InlineData("Pending", HttpStatusCode.Accepted)]
    [InlineData("Conflict", HttpStatusCode.Conflict)]
    public async Task PushAsync_WithNonCompletedServerResult_DoesNotAcknowledgeOutbox(
        string status,
        HttpStatusCode statusCode)
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.Method == HttpMethod.Get && request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson("after-pending", false));
            }

            return JsonResponse(OperationResultJson(operationId, clientUuid, status), statusCode);
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalOperationResult result = await fixture.Connector.PushAsync(envelope, CancellationToken.None);
        SynchronizeNowResult synchronized = await fixture.Connector.SynchronizeNowAsync(CancellationToken.None);

        Assert.Equal(status, result.Status.ToString());
        Assert.Equal("Pending", synchronized.Status);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
        Assert.Equal(2, fixture.Handler.Requests.Count(request => request.Method == HttpMethod.Post));
    }

    [Fact]
    public async Task PushPendingAsync_WithUnsupportedOperation_ContinuesToLaterSupportedOperation()
    {
        Guid unsupportedOperationId = Guid.NewGuid();
        Guid unsupportedUuid = Guid.NewGuid();
        Guid supportedOperationId = Guid.NewGuid();
        Guid supportedUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.Method == HttpMethod.Get && request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson("after-supported", false));
            }

            return JsonResponse(OperationResultJson(supportedOperationId, supportedUuid, "Completed"));
        });
        await SeedAidAsync(fixture.Harness, unsupportedOperationId, unsupportedUuid);
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            OutboxOperation create = await context.OutboxOperations.SingleAsync(operation =>
                operation.OperationId == unsupportedOperationId);
            create.Status = "Sent";
            CachedEntity cached = await context.CachedEntities.SingleAsync(entity => entity.ClientUuid == unsupportedUuid);
            SyncEnvelope patch = LocalEnvelope(Guid.NewGuid(), unsupportedUuid, "AidOperation") with
            {
                OperationType = SyncOperationType.Update,
                RowVersion = cached.RowVersion,
                OccurredAtUtc = cached.UpdatedAtUtc.AddSeconds(1),
                Patches =
                [
                    new FieldPatch
                    {
                        FieldName = "notes",
                        LocalValue = JsonSerializer.SerializeToElement("Synthetic unsupported patch"),
                        Criticality = FieldCriticality.Mergeable
                    }
                ]
            };
            await new UnitOfWork(context).PatchAsync(
                patch,
                "Synthetic unsupported patch",
                new LocalAuditContext(null, "synthetic.write", 1));
            await context.SaveChangesAsync();
        }
        await SeedAidAsync(
            fixture.Harness,
            supportedOperationId,
            supportedUuid,
            DateTimeOffset.Parse("2026-11-25T10:00:02Z"));

        SynchronizeNowResult result = await fixture.Connector.SynchronizeNowAsync(CancellationToken.None);

        Assert.Equal("Pending", result.Status);
        Assert.Equal("Sent", await OutboxStatusAsync(fixture, supportedOperationId));
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        OutboxOperation[] pending = await verification.OutboxOperations
            .Where(operation => operation.Status == "Pending")
            .ToArrayAsync();
        OutboxOperation unsupported = Assert.Single(pending);
        Assert.Equal(SyncOperationType.Update.ToString(), unsupported.OperationType);
        Assert.Single(fixture.Handler.Requests.Where(request => request.Method == HttpMethod.Post));
    }

    [Fact]
    public async Task PushAsync_WithIncompleteCompletedResult_DoesNotAcknowledgeOutbox()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.Method == HttpMethod.Get && request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson("after-invalid-result", false));
            }

            return JsonResponse(
                $"{{\"operation_id\":\"{operationId}\",\"operation_type\":\"aid.create\",\"status\":\"Completed\",\"entity_id\":null,\"row_version\":null,\"conflict_type\":null,\"expires_at\":\"2027-02-23T10:00:00Z\"}}");
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);

        SynchronizeNowResult result = await fixture.Connector.SynchronizeNowAsync(CancellationToken.None);

        Assert.Equal("Pending", result.Status);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
        Assert.Single(fixture.Handler.Requests.Where(request => request.Method == HttpMethod.Post));
    }

    [Fact]
    public async Task PushAsync_WithTimeout_RetriesSameOperationAndLeavesOutboxPending()
    {
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            throw new TimeoutException("synthetic timeout"));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalSyncException exception = await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(envelope, CancellationToken.None));

        Assert.True(exception.IsTransient);
        Assert.Equal("timeout", exception.ErrorCode);
        Assert.Equal(3, fixture.Handler.Requests.Count);
        Assert.All(fixture.Handler.Requests, request => Assert.Equal(fixture.Handler.Requests[0].BodyText, request.BodyText));
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
    }

    [Fact]
    public async Task PushAsync_WithCancellation_DoesNotRetryOrAcknowledge()
    {
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse("{}"));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);
        using CancellationTokenSource cancellation = new();
        cancellation.Cancel();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() =>
            fixture.Connector.PushAsync(envelope, cancellation.Token));

        Assert.Empty(fixture.Handler.Requests);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
    }

    [Fact]
    public async Task LocalFailure_DoesNotChangeLaravelCursorOrOutboxState()
    {
        Guid localOperationId = Guid.NewGuid();
        Guid laravelOperationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse("{\"error\":\"synthetic-local-auth-failure\"}", HttpStatusCode.Unauthorized));
        await SeedAidAsync(fixture.Harness, localOperationId, Guid.NewGuid());
        await fixture.Harness.SeedCreateAsync(LaravelSourceId, laravelOperationId, Guid.NewGuid());
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            OutboxOperation laravel = await context.OutboxOperations.SingleAsync(operation =>
                operation.OperationId == laravelOperationId);
            laravel.Status = "Sent";
            await context.SaveChangesAsync();
        }

        await fixture.Cursors.CommitPageAsync(
            LaravelSourceId,
            "changes",
            EmptyPage(50, "laravel-cursor"),
            CancellationToken.None);
        CursorState laravelBefore = await fixture.Cursors.GetAsync(LaravelSourceId, "changes");

        SynchronizeNowResult result = await fixture.Connector.SynchronizeNowAsync(CancellationToken.None);

        Assert.Equal("Pending", result.Status);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, localOperationId));
        Assert.Equal("Sent", await OutboxStatusAsync(fixture, laravelOperationId));
        Assert.Equal(laravelBefore, await fixture.Cursors.GetAsync(LaravelSourceId, "changes"));
    }

    [Fact]
    public async Task LaravelOutboxProcessor_WithPendingLocalOperation_SendsOnlyLaravelAndPreservesLocalRetryState()
    {
        Guid localOperationId = Guid.NewGuid();
        Guid laravelOperationId = Guid.NewGuid();
        DateTimeOffset localAttempt = DateTimeOffset.Parse("2026-11-25T09:00:00Z");
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse("{}", HttpStatusCode.Unauthorized));
        await SeedAidAsync(fixture.Harness, localOperationId, Guid.NewGuid());
        await fixture.Harness.SeedCreateAsync(LaravelSourceId, laravelOperationId, Guid.NewGuid());
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            OutboxOperation local = await context.OutboxOperations.SingleAsync(operation =>
                operation.OperationId == localOperationId);
            local.AttemptCount = 7;
            local.LastAttemptAtUtc = localAttempt;
            await context.SaveChangesAsync();
        }

        FakeOutboxTransport transport = new();
        OutboxProcessor processor = new(
            fixture.Harness.Store,
            transport,
            new RetryPolicy(
                new RetryOptions
                {
                    InitialDelay = TimeSpan.FromMilliseconds(1),
                    MaximumDelay = TimeSpan.FromMilliseconds(1)
                },
                () => 0),
            TimeProvider.System,
            new RecordingLogger<OutboxProcessor>());

        OutboxPassResult result = await processor.ProcessPendingAsync(CancellationToken.None);

        Assert.Equal(1, result.SentCount);
        Assert.Equal(laravelOperationId, Assert.Single(transport.SendOperationIds));
        Assert.Equal("Sent", await OutboxStatusAsync(fixture, laravelOperationId));
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, localOperationId));
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        OutboxOperation localAfter = await verification.OutboxOperations.SingleAsync(operation =>
            operation.OperationId == localOperationId);
        Assert.Equal(7, localAfter.AttemptCount);
        Assert.Equal(localAttempt, localAfter.LastAttemptAtUtc);
    }

    [Fact]
    public async Task LaravelIdentityResolution_DoesNotRewritePendingLocalEnvelopeOrRetryState()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        Guid targetUuid = Guid.NewGuid();
        DateTimeOffset localAttempt = DateTimeOffset.Parse("2026-11-25T09:30:00Z");
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse("{}"));
        SyncEnvelope envelope = LocalEnvelope(operationId, clientUuid, "AidOperation") with
        {
            Entity = new EntityDescriptor
            {
                EntityType = "AidOperation",
                ClientUuid = clientUuid,
                CanonicalId = null,
                References =
                [
                    new EntityReference
                    {
                        FieldName = "entitlementId",
                        TargetEntityType = "Entitlement",
                        TargetClientUuid = targetUuid,
                        TargetCanonicalId = null,
                        Kind = EntityReferenceKind.Field
                    }
                ]
            }
        };
        await SeedLocalEntityAsync(
            fixture.Harness,
            envelope,
            "{\"aidType\":\"SyntheticAid\",\"amount\":12.50,\"currencyCode\":\"USD\",\"occurredAtUtc\":\"2026-11-25T10:00:00Z\"}",
            "SyntheticAid");
        string originalEnvelopeJson;
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            context.ClientIdentityMap.Add(new ClientIdentityMappingEntity
            {
                ClientUuid = targetUuid,
                EntityType = "Entitlement",
                CanonicalId = "synthetic-laravel-entitlement",
                CreatedAtUtc = localAttempt,
                ResolvedAtUtc = localAttempt
            });
            OutboxOperation local = await context.OutboxOperations.SingleAsync(operation =>
                operation.OperationId == operationId);
            local.AttemptCount = 3;
            local.LastAttemptAtUtc = localAttempt;
            originalEnvelopeJson = local.EnvelopeJson;
            await context.SaveChangesAsync();
        }

        ClientIdentityMapStore identities = new(fixture.Harness.Store, TimeProvider.System);
        int changed = await identities.ResolvePendingReferencesAsync(CancellationToken.None);

        Assert.Equal(0, changed);
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        OutboxOperation localAfter = await verification.OutboxOperations.SingleAsync(operation =>
            operation.OperationId == operationId);
        Assert.Equal(originalEnvelopeJson, localAfter.EnvelopeJson);
        Assert.Equal(3, localAfter.AttemptCount);
        Assert.Equal(localAttempt, localAfter.LastAttemptAtUtc);
    }

    [Fact]
    public async Task SensitiveFailure_DoesNotLogCredentialPayloadOrResponseSecrets()
    {
        const string syntheticPii = "synthetic-private-local-beneficiary";
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse($"{{\"message\":\"{Credential} {syntheticPii}\"}}", HttpStatusCode.InternalServerError));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalSyncException exception = await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(envelope, CancellationToken.None));

        Assert.NotEmpty(fixture.Logger.Messages);
        Assert.All(fixture.Logger.Messages, message =>
        {
            Assert.DoesNotContain(Credential, message, StringComparison.Ordinal);
            Assert.DoesNotContain(syntheticPii, message, StringComparison.Ordinal);
            Assert.DoesNotContain("SyntheticAid", message, StringComparison.Ordinal);
        });
        Assert.DoesNotContain(Credential, exception.Message, StringComparison.Ordinal);
        Assert.DoesNotContain(syntheticPii, exception.Message, StringComparison.Ordinal);
    }

    [Theory]
    [InlineData(true)]
    [InlineData(false)]
    public async Task LocalHttpClientFactory_AcceptsCurrentOrPreviousPinAndAttachesClientCertificate(
        bool useCurrentPin)
    {
        using CertificateFixture certificates = new();
        ConnectionSettingsData settings = SyncSettingsStore.Defaults() with
        {
            LocalServerSpkiSha256Pin = useCurrentPin ? certificates.CertificatePin : certificates.OtherPin,
            PreviousLocalServerSpkiSha256Pin = useCurrentPin ? certificates.OtherPin : certificates.CertificatePin
        };
        (SyncSettingsStore store, string path) = await SaveSyntheticSettingsAsync(settings);
        try
        {
            LocalHttpClientFactory factory = new(store, LocalOptions(), new SyntheticCertificateProvider(certificates.Certificate));

            using SocketsHttpHandler handler = Assert.IsAssignableFrom<SocketsHttpHandler>(
                await factory.CreateHandlerAsync(CancellationToken.None));

            Assert.Contains(handler.SslOptions.ClientCertificates, certificate =>
                string.Equals(certificate.Thumbprint, certificates.Certificate.Thumbprint, StringComparison.OrdinalIgnoreCase));
            Assert.NotNull(handler.SslOptions.RemoteCertificateValidationCallback);
            Assert.True(handler.SslOptions.RemoteCertificateValidationCallback(
                null,
                certificates.Certificate,
                null,
                SslPolicyErrors.None));
            Assert.False(handler.AllowAutoRedirect);
        }
        finally
        {
            File.Delete(path);
        }
    }

    [Fact]
    public async Task LocalHttpClientFactory_RejectsPinMismatchAndNormalChainFailure()
    {
        using CertificateFixture certificates = new();
        ConnectionSettingsData settings = SyncSettingsStore.Defaults() with
        {
            LocalServerSpkiSha256Pin = certificates.OtherPin
        };
        (SyncSettingsStore store, string path) = await SaveSyntheticSettingsAsync(settings);
        try
        {
            LocalHttpClientFactory factory = new(store, LocalOptions(), new SyntheticCertificateProvider(certificates.Certificate));
            using SocketsHttpHandler handler = Assert.IsAssignableFrom<SocketsHttpHandler>(
                await factory.CreateHandlerAsync(CancellationToken.None));
            RemoteCertificateValidationCallback validate =
                handler.SslOptions.RemoteCertificateValidationCallback!;

            Assert.ThrowsAny<AuthenticationException>(() => validate(
                null,
                certificates.Certificate,
                null,
                SslPolicyErrors.None));
            Assert.False(validate(
                null,
                certificates.Certificate,
                null,
                SslPolicyErrors.RemoteCertificateChainErrors));
        }
        finally
        {
            File.Delete(path);
        }
    }

    [Fact]
    public void LocalCertificatePinValidation_RejectsMismatchAndTrustFailure()
    {
        using CertificateFixture certificates = new();
        CertificatePinValidator validator = new(certificates.OtherPin);

        Assert.False(validator.Validate(certificates.Certificate, SslPolicyErrors.None));
        Assert.False(validator.Validate(certificates.Certificate, SslPolicyErrors.RemoteCertificateChainErrors));
    }

    [Fact]
    public void LocalCertificatePinValidation_AcceptsCurrentOrPreviousPinDuringRotation()
    {
        using CertificateFixture certificates = new();
        CertificatePinValidator current = new(certificates.CertificatePin, certificates.OtherPin);
        CertificatePinValidator previous = new(certificates.OtherPin, certificates.CertificatePin);

        Assert.True(current.Validate(certificates.Certificate, SslPolicyErrors.None));
        Assert.True(previous.Validate(certificates.Certificate, SslPolicyErrors.None));
    }

    private static LocalConnectorOptions LocalOptions() => new()
    {
        SourceId = SourceId,
        DeviceId = DeviceId,
        UserId = UserId,
        ClientCertificateThumbprint = "synthetic-client-thumbprint"
    };

    private static async Task<(SyncSettingsStore Store, string Path)> SaveSyntheticSettingsAsync(
        ConnectionSettingsData settings)
    {
        string path = Path.Combine(Path.GetTempPath(), $"alhayah-local-settings-{Guid.NewGuid():N}.json");
        SyncSettingsStore store = new(path);
        await store.SaveAsync(settings, CancellationToken.None);
        return (store, path);
    }

    private static RemoteChangePage EmptyPage(long changeId, string cursor) => new()
    {
        NextCursor = cursor,
        LastChangeLogId = changeId,
        CompletesReconciliation = true,
        Changes = []
    };

    private static SyncEnvelope LocalEnvelope(Guid operationId, Guid clientUuid, string entityType) => new()
    {
        OperationId = operationId,
        SourceId = new SyncSourceId(SourceId),
        DeviceId = DeviceId,
        Entity = new EntityDescriptor
        {
            EntityType = entityType,
            ClientUuid = clientUuid,
            CanonicalId = null,
            References = []
        },
        OperationType = SyncOperationType.Create,
        OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T10:00:00Z"),
        Patches = []
    };

    private static Task SeedAidAsync(
        SynchronizationTestHarness harness,
        Guid operationId,
        Guid clientUuid,
        DateTimeOffset? occurredAtUtc = null)
    {
        SyncEnvelope envelope = LocalEnvelope(operationId, clientUuid, "AidOperation") with
        {
            OccurredAtUtc = occurredAtUtc ?? DateTimeOffset.Parse("2026-11-25T10:00:00Z")
        };
        string payload = JsonSerializer.Serialize(new
        {
            aidType = "SyntheticAid",
            entitlementId = (Guid?)null,
            amount = 12.50m,
            currencyCode = "USD",
            occurredAtUtc = DateTimeOffset.Parse("2026-11-25T10:00:00Z"),
            notes = (string?)null
        });
        return SeedLocalEntityAsync(harness, envelope, payload, "SyntheticAid");
    }

    private static async Task SeedLocalEntityAsync(
        SynchronizationTestHarness harness,
        SyncEnvelope envelope,
        string payload,
        string searchText)
    {
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        await new UnitOfWork(context).CreateAsync(
            envelope,
            payload,
            searchText,
            new LocalAuditContext(null, "synthetic.write", 1));
    }

    private static async Task<SyncEnvelope> LoadEnvelopeAsync(ConnectorFixture fixture, Guid operationId)
    {
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        string json = (await context.OutboxOperations.SingleAsync(operation => operation.OperationId == operationId)).EnvelopeJson;
        return JsonSerializer.Deserialize<SyncEnvelope>(json)!;
    }

    private static async Task<string> OutboxStatusAsync(ConnectorFixture fixture, Guid operationId)
    {
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        return (await context.OutboxOperations.SingleAsync(operation => operation.OperationId == operationId)).Status;
    }

    private static string HealthJson() =>
        $"{{\"status\":\"ok\",\"api_version\":\"1\",\"source_id\":\"{SourceId}\",\"server_time\":\"2026-11-25T10:00:00Z\",\"certificate\":{{\"sha256_spki_pin\":\"{Convert.ToBase64String(new byte[32])}\",\"not_before\":\"2026-11-24T00:00:00Z\",\"not_after\":\"2027-11-24T00:00:00Z\"}}}}";

    private static string CapabilitiesJson() =>
        $"{{\"api_version\":\"1\",\"source_id\":\"{SourceId}\",\"server_time\":\"2026-11-25T10:00:01Z\",\"certificate\":{{\"sha256_spki_pin\":\"{Convert.ToBase64String(new byte[32])}\",\"not_before\":\"2026-11-24T00:00:00Z\",\"not_after\":\"2027-11-24T00:00:00Z\"}},\"credential_type\":\"independent-device-credential\",\"transport\":\"https-required\",\"client_certificate\":\"trusted-chain-and-sha256-spki-pin-required\",\"permissions\":[\"read\",\"operations\",\"admin\"]}}";

    private static string OperationResultJson(Guid operationId, Guid entityId, string status) =>
        $"{{\"operation_id\":\"{operationId}\",\"operation_type\":\"aid.create\",\"status\":\"{status}\",\"entity_id\":\"{entityId}\",\"row_version\":1,\"conflict_type\":null,\"expires_at\":\"2027-02-23T10:00:00Z\"}}";

    private static string FeedJson(string nextCursor, bool hasMore, params string[] changes) =>
        $"{{\"source_id\":\"{SourceId}\",\"server_time\":\"2026-11-25T10:01:00Z\",\"next_cursor\":\"{nextCursor}\",\"has_more\":{hasMore.ToString().ToLowerInvariant()},\"changes\":[{string.Join(',', changes)}]}}";

    private static string ChangeJson(
        long changeId,
        Guid clientUuid,
        string operation,
        string? entity,
        bool includeTombstone = false)
    {
        string tombstone = includeTombstone
            ? "{\"deleted_at\":\"2026-11-25T10:01:00Z\",\"deleted_by_device_id\":\"remote-device\"}"
            : "null";
        return $"{{\"change_log_id\":{changeId},\"entity_type\":\"AidOperation\",\"entity_id\":\"{clientUuid}\",\"operation_type\":\"{operation}\",\"row_version\":{changeId},\"concurrency_token\":\"{Guid.NewGuid()}\",\"changed_at\":\"2026-11-25T10:01:00Z\",\"device_id\":\"remote-device\",\"entity\":{entity ?? "null"},\"tombstone\":{tombstone}}}";
    }

    private static HttpResponseMessage JsonResponse(
        string json,
        HttpStatusCode statusCode = HttpStatusCode.OK) => new(statusCode)
    {
        Content = new StringContent(json, Encoding.UTF8, "application/json")
    };

    private sealed class ConnectorFixture : IAsyncDisposable
    {
        private ConnectorFixture(
            SynchronizationTestHarness harness,
            RecordingHttpMessageHandler handler,
            RecordingLogger<LocalServerSyncConnector> logger,
            LocalServerSyncConnector connector,
            CursorStore cursors,
            FakeLocalSyncHubClient hub,
            LocalSyncRealtimeCoordinator coordinator)
        {
            Harness = harness;
            Handler = handler;
            Logger = logger;
            Connector = connector;
            Cursors = cursors;
            Hub = hub;
            Coordinator = coordinator;
        }

        public SynchronizationTestHarness Harness { get; }

        public RecordingHttpMessageHandler Handler { get; }

        public RecordingLogger<LocalServerSyncConnector> Logger { get; }

        public LocalServerSyncConnector Connector { get; }

        public CursorStore Cursors { get; }

        public FakeLocalSyncHubClient Hub { get; }

        public LocalSyncRealtimeCoordinator Coordinator { get; }

        public static async Task<ConnectorFixture> CreateAsync(
            Func<RecordedRequest, HttpResponseMessage> responder,
            CommitCrashInterceptor? commitInterceptor = null)
        {
            SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync(commitInterceptor);
            RecordingHttpMessageHandler handler = new(responder);
            FakeSecretStore secrets = new();
            secrets.SetUtf8(SecretTargets.LocalServerDeviceCredential(SourceId), Credential);
            CursorStore cursors = new(harness.Store, new LocalChangePageApplier(), TimeProvider.System);
            RecordingLogger<LocalServerSyncConnector> logger = new();
            LocalServerSyncConnector connector = new(
                new FakeHttpClientFactory(handler),
                new LocalConnectorOptions
                {
                    SourceId = SourceId,
                    DeviceId = DeviceId,
                    UserId = UserId,
                    ClientCertificateThumbprint = "synthetic-client-thumbprint",
                    MaxAttempts = 3,
                    PullPageSize = 2
                },
                harness.Store,
                cursors,
                secrets,
                new RetryPolicy(
                    new RetryOptions
                    {
                        InitialDelay = TimeSpan.FromMilliseconds(1),
                        MaximumDelay = TimeSpan.FromMilliseconds(1)
                    },
                    () => 0),
                TimeProvider.System,
                logger);
            FakeLocalSyncHubClient hub = new();
            LocalSyncRealtimeCoordinator coordinator = new(
                hub,
                connector,
                new RecordingLogger<LocalSyncRealtimeCoordinator>());
            return new ConnectorFixture(harness, handler, logger, connector, cursors, hub, coordinator);
        }

        public ValueTask DisposeAsync() => Harness.DisposeAsync();
    }

    private sealed class FakeHttpClientFactory(RecordingHttpMessageHandler handler) : ILocalHttpClientFactory
    {
        public Task<HttpClient> CreateAsync(CancellationToken cancellationToken) => Task.FromResult(new HttpClient(
            handler,
            disposeHandler: false)
        {
            BaseAddress = new Uri("https://synthetic.invalid/", UriKind.Absolute),
            Timeout = TimeSpan.FromSeconds(5)
        });

        public Task<HttpMessageHandler> CreateHandlerAsync(CancellationToken cancellationToken) =>
            Task.FromResult<HttpMessageHandler>(handler);
    }

    private sealed class SyntheticCertificateProvider(X509Certificate2 certificate) : ILocalClientCertificateProvider
    {
        public X509Certificate2 LoadCertificate(string thumbprint) => certificate;
    }

    private sealed class RecordingHttpMessageHandler : HttpMessageHandler
    {
        public RecordingHttpMessageHandler(Func<RecordedRequest, HttpResponseMessage> responder)
        {
            Responder = responder;
        }

        public Func<RecordedRequest, HttpResponseMessage> Responder { get; set; }

        public List<RecordedRequest> Requests { get; } = [];

        protected override async Task<HttpResponseMessage> SendAsync(
            HttpRequestMessage request,
            CancellationToken cancellationToken)
        {
            byte[] body = request.Content is null
                ? []
                : await request.Content.ReadAsByteArrayAsync(cancellationToken);
            Dictionary<string, string> headers = request.Headers.ToDictionary(
                header => header.Key,
                header => string.Join(',', header.Value));
            RecordedRequest recorded = new(
                request.Method,
                request.RequestUri?.PathAndQuery ?? string.Empty,
                headers,
                body);
            Requests.Add(recorded);
            return Responder(recorded);
        }
    }

    private sealed record RecordedRequest(
        HttpMethod Method,
        string PathAndQuery,
        IReadOnlyDictionary<string, string> Headers,
        byte[] Body)
    {
        public string? BodyText => Body.Length == 0 ? null : Encoding.UTF8.GetString(Body);

        public string Header(string name) => Headers.TryGetValue(name, out string? value)
            ? value
            : throw new KeyNotFoundException($"The synthetic request header {name} was not recorded.");
    }

    private sealed class FakeSecretStore : ISecretStore
    {
        private readonly Dictionary<string, byte[]> _values = new(StringComparer.Ordinal);

        public void SetUtf8(string target, string value) => _values[target] = Encoding.UTF8.GetBytes(value);

        public void SetBytes(string target, byte[] value) => _values[target] = value.ToArray();

        public byte[]? Read(string target) => _values.TryGetValue(target, out byte[]? value) ? value.ToArray() : null;

        public void Remove(string target) => _values.Remove(target);
    }

    private sealed class FakeLocalSyncHubClient : ILocalSyncHubClient
    {
        private Func<CancellationToken, Task>? _onChanged;

        public bool IsConnected { get; private set; }

        public Exception? StartException { get; set; }

        public Task StartAsync(Func<CancellationToken, Task> onChanged, CancellationToken cancellationToken)
        {
            if (StartException is not null)
            {
                return Task.FromException(StartException);
            }

            _onChanged = onChanged;
            IsConnected = true;
            return Task.CompletedTask;
        }

        public Task EmitAsync() => _onChanged?.Invoke(CancellationToken.None) ?? Task.CompletedTask;

        public Task StopAsync(CancellationToken cancellationToken)
        {
            _onChanged = null;
            IsConnected = false;
            return Task.CompletedTask;
        }

        public ValueTask DisposeAsync() => ValueTask.CompletedTask;
    }

    private sealed class RecordingLogger<T> : ILogger<T>
    {
        public List<string> Messages { get; } = [];

        public IDisposable? BeginScope<TState>(TState state) where TState : notnull => null;

        public bool IsEnabled(LogLevel logLevel) => true;

        public void Log<TState>(
            LogLevel logLevel,
            EventId eventId,
            TState state,
            Exception? exception,
            Func<TState, Exception?, string> formatter) => Messages.Add(formatter(state, exception));
    }

    private sealed class CertificateFixture : IDisposable
    {
        private readonly RSA _key;

        public CertificateFixture()
        {
            _key = RSA.Create(2048);
            CertificatePin = Convert.ToHexString(SHA256.HashData(_key.ExportSubjectPublicKeyInfo()));
            CertificateRequest request = new(
                "CN=synthetic-local-server-pin-test",
                _key,
                HashAlgorithmName.SHA256,
                RSASignaturePadding.Pkcs1);
            Certificate = request.CreateSelfSigned(
                DateTimeOffset.Parse("2026-11-24T00:00:00Z"),
                DateTimeOffset.Parse("2027-11-24T00:00:00Z"));
            using RSA otherKey = RSA.Create(2048);
            OtherPin = Convert.ToHexString(SHA256.HashData(otherKey.ExportSubjectPublicKeyInfo()));
        }

        public X509Certificate2 Certificate { get; }

        public string CertificatePin { get; }

        public string OtherPin { get; }

        public void Dispose()
        {
            Certificate.Dispose();
            _key.Dispose();
        }
    }
}
