using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class AuditTests
{
    [Fact]
    public async Task RecordAsync_WithSensitiveSyntheticPayload_WritesOnlyHashAndSafeMetadata()
    {
        const string payload = """
            {
              "personName": "Synthetic Person Name",
              "identityNumber": "000000000",
              "password": "synthetic-secret-password",
              "token": "synthetic-secret-token"
            }
            """;
        AuditEventRequest? recorded = null;
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) =>
        {
            recorded = request.Payload.Deserialize<AuditEventRequest>();
            return Task.FromResult(host.Ok(request, new AcknowledgedResponse { Acknowledged = true }));
        };
        AuditService audit = new(IpcTestData.CreateClient(host));
        AuditEventContext context = new()
        {
            OperationId = Guid.Parse("294b50a6-1d5b-47a1-a8a2-e43f0f9a1006"),
            OfflineUserId = Guid.Parse("bf94b9b7-70d2-4f9a-bfe8-58de2375a007"),
            DeviceId = "synthetic-device",
            SourceId = "laravel:synthetic",
            PermissionUsed = Alhayah.Contracts.Ipc.LocalPermissions.Save,
            ManifestVersion = 11,
            OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T02:00:00Z"),
            Result = AuditResult.Committed
        };

        await audit.RecordAsync(context, payload);

        Assert.NotNull(recorded);
        string serialized = JsonSerializer.Serialize(recorded);
        Assert.DoesNotContain("Synthetic Person Name", serialized, StringComparison.Ordinal);
        Assert.DoesNotContain("000000000", serialized, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-secret-password", serialized, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-secret-token", serialized, StringComparison.Ordinal);
        Assert.DoesNotContain("personName", serialized, StringComparison.Ordinal);
        Assert.DoesNotContain("identityNumber", serialized, StringComparison.Ordinal);
        Assert.DoesNotContain("password", serialized, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("token", serialized, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("payloadJson", serialized, StringComparison.OrdinalIgnoreCase);
        Assert.Equal(
            Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(payload))),
            recorded.PayloadSha256);
    }

    [Fact]
    public async Task RecordAsync_WithArbitraryPermissionText_IsRejectedBeforeIpc()
    {
        FakeSyncServiceHost host = new();
        AuditService audit = new(IpcTestData.CreateClient(host));
        AuditEventContext context = new()
        {
            OperationId = Guid.Parse("294b50a6-1d5b-47a1-a8a2-e43f0f9a1006"),
            DeviceId = "synthetic-device",
            SourceId = "laravel:synthetic",
            PermissionUsed = "synthetic-secret-field-value",
            OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T02:00:00Z"),
            Result = AuditResult.Rejected
        };

        await Assert.ThrowsAsync<ArgumentException>(() => audit.RecordAsync(context, "{}"));
        Assert.Empty(host.Commands);
    }

    [Fact]
    public void AuditEventRequest_Contract_DoesNotExposeSecretOrFieldValueProperties()
    {
        string[] propertyNames = typeof(AuditEventRequest).GetProperties().Select(property => property.Name).ToArray();

        Assert.DoesNotContain("Password", propertyNames);
        Assert.DoesNotContain("PasswordHash", propertyNames);
        Assert.DoesNotContain("Token", propertyNames);
        Assert.DoesNotContain("SessionProof", propertyNames);
        Assert.DoesNotContain("Metadata", propertyNames);
        Assert.DoesNotContain("PayloadJson", propertyNames);
        Assert.DoesNotContain("FieldName", propertyNames);
        Assert.DoesNotContain("FieldValue", propertyNames);
        Assert.Contains("PayloadSha256", propertyNames);
    }

    [Fact]
    public async Task RecordAsync_WithRejectedResult_PreservesSafeOutcomeWithoutExceptionDetails()
    {
        AuditEventRequest? recorded = null;
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) =>
        {
            recorded = request.Payload.Deserialize<AuditEventRequest>();
            return Task.FromResult(host.Ok(request, new AcknowledgedResponse { Acknowledged = true }));
        };
        AuditService audit = new(IpcTestData.CreateClient(host));
        AuditEventContext context = new()
        {
            OperationId = Guid.Parse("93602f2b-f84f-46ad-a604-f2b054ec1008"),
            DeviceId = "synthetic-device",
            SourceId = "local:synthetic",
            PermissionUsed = Alhayah.Contracts.Ipc.LocalPermissions.Save,
            OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T02:00:00Z"),
            Result = AuditResult.Rejected
        };

        await audit.RecordAsync(context, "synthetic private field value");

        Assert.NotNull(recorded);
        Assert.Equal(AuditResult.Rejected, recorded.Result);
        Assert.DoesNotContain("Exception", JsonSerializer.Serialize(recorded), StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("synthetic private field value", JsonSerializer.Serialize(recorded), StringComparison.Ordinal);
    }
}
