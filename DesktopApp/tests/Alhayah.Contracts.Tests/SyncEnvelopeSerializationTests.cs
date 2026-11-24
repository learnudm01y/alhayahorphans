using System.Text.Json;
using Alhayah.Contracts.Domain;
using Xunit;

namespace Alhayah.Contracts.Tests;

public sealed class SyncEnvelopeSerializationTests
{
    [Fact]
    public void RoundTripPreservesKnownFieldsAndUnknownFieldsAtEveryLevel()
    {
        const string json = """
            {
              "operationId": "4c7c2c18-0893-4d4d-9f50-26e1af23f001",
              "sourceId": "laravel:production",
              "deviceId": "device-test-001",
              "entity": {
                "entityType": "laravel.data",
                "clientUuid": "17b46f66-53db-45b7-a06b-f387f8d6f002",
                "canonicalId": null,
                "references": [
                  {
                    "fieldName": "family_id",
                    "targetEntityType": "laravel.families",
                    "targetClientUuid": "334c03f5-9c97-45e9-8f50-47a1eb6bf003",
                    "targetCanonicalId": null,
                    "kind": "Field",
                    "isDeleted": false,
                    "futureReference": "keep-reference"
                  }
                ],
                "futureEntity": { "enabled": true }
              },
              "operationType": "Update",
              "baseVersion": 41,
              "rowVersion": "AAAAAAAAAAA=",
              "cursor": "cursor-41",
              "occurredAtUtc": "2026-11-24T07:30:00.0000000+00:00",
              "tombstone": null,
              "patches": [
                {
                  "fieldName": "notes",
                  "baseValue": "base",
                  "localValue": "local",
                  "remoteValue": "remote",
                  "criticality": "Mergeable",
                  "futurePatch": [1, 2, 3]
                }
              ],
              "futureEnvelope": { "retryClass": "safe" }
            }
            """;

        var envelope = JsonSerializer.Deserialize<SyncEnvelope>(json);
        var roundTripped = JsonSerializer.Serialize(envelope);
        using var document = JsonDocument.Parse(roundTripped);
        var root = document.RootElement;

        Assert.Equal(Guid.Parse("4c7c2c18-0893-4d4d-9f50-26e1af23f001"), envelope!.OperationId);
        Assert.Equal(new SyncSourceId("laravel:production"), envelope.SourceId);
        Assert.Equal(41UL, envelope.BaseVersion);
        Assert.Equal("AAAAAAAAAAA=", envelope.RowVersion);
        Assert.Equal("cursor-41", envelope.Cursor);
        Assert.Equal("safe", root.GetProperty("futureEnvelope").GetProperty("retryClass").GetString());
        Assert.True(root.GetProperty("entity").GetProperty("futureEntity").GetProperty("enabled").GetBoolean());
        Assert.Equal("keep-reference", root.GetProperty("entity").GetProperty("references")[0].GetProperty("futureReference").GetString());
        Assert.Equal(3, root.GetProperty("patches")[0].GetProperty("futurePatch").GetArrayLength());
        Assert.Equal("17b46f66-53db-45b7-a06b-f387f8d6f002", root.GetProperty("entity").GetProperty("clientUuid").GetString());
        Assert.Equal("334c03f5-9c97-45e9-8f50-47a1eb6bf003", root.GetProperty("entity").GetProperty("references")[0].GetProperty("targetClientUuid").GetString());
    }

    [Fact]
    public void DeleteRoundTripPreservesTombstoneAndExplicitNullValues()
    {
        var envelope = new SyncEnvelope
        {
            OperationId = Guid.Parse("35fc8a20-bf2b-45a6-86b1-f479ef681010"),
            SourceId = new SyncSourceId("local:main-office"),
            DeviceId = "device-test-002",
            Entity = new EntityDescriptor
            {
                EntityType = "local.disbursements",
                ClientUuid = Guid.Parse("5f0c8de0-ef2d-472b-af5f-cbe987e33011"),
                CanonicalId = "77",
                References = []
            },
            OperationType = SyncOperationType.Delete,
            BaseVersion = 12,
            RowVersion = "opaque-row-version",
            Cursor = null,
            OccurredAtUtc = DateTimeOffset.Parse("2026-11-24T08:00:00Z"),
            Tombstone = new Tombstone
            {
                DeletedAtUtc = DateTimeOffset.Parse("2026-11-24T08:00:00Z"),
                DeletedByDeviceId = "device-test-002",
                Reason = "test-deletion"
            },
            Patches = []
        };

        var copy = JsonSerializer.Deserialize<SyncEnvelope>(JsonSerializer.Serialize(envelope));

        Assert.NotNull(copy);
        Assert.Equal(envelope.OperationId, copy.OperationId);
        Assert.Equal(envelope.Entity.ClientUuid, copy.Entity.ClientUuid);
        Assert.NotNull(copy.Tombstone);
        Assert.Equal(envelope.Tombstone.DeletedByDeviceId, copy.Tombstone.DeletedByDeviceId);
        Assert.Equal(envelope.Tombstone.Reason, copy.Tombstone.Reason);
        Assert.Null(copy.Cursor);
        Assert.Equal(DateTimeOffset.Parse("2026-11-24T08:00:00Z"), copy.Tombstone.DeletedAtUtc);
        Assert.Equal("77", copy.Entity.CanonicalId);
    }
}
