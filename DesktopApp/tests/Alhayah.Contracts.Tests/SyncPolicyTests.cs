using System.Text.Json;
using Xunit;

namespace Alhayah.Contracts.Tests;

public sealed class SyncPolicyTests
{
    [Fact]
    public void AuthenticationAndOfflineAuthorizationAreFinalAndExplicit()
    {
        using var policy = ReadPolicy();
        var root = policy.RootElement;

        Assert.Equal("final", root.GetProperty("status").GetString());
        Assert.Equal("laravel-sanctum-opaque-device-token", root.GetProperty("authentication").GetProperty("laravel").GetProperty("tokenType").GetString());
        Assert.False(root.GetProperty("authentication").GetProperty("laravel").GetProperty("jwt").GetBoolean());
        Assert.True(root.GetProperty("authentication").GetProperty("laravel").GetProperty("revocable").GetBoolean());
        Assert.Equal("independent-device-credential", root.GetProperty("authentication").GetProperty("localServer").GetProperty("credentialType").GetString());
        Assert.Equal("windows-credential-manager", root.GetProperty("credentialStorage").GetProperty("store").GetString());
        Assert.Equal("dedicated-sync-service-account", root.GetProperty("credentialStorage").GetProperty("windowsAccount").GetString());
        Assert.False(root.GetProperty("credentialStorage").GetProperty("distributeLaravelAppKey").GetBoolean());
        Assert.Equal("last-approved-manifest-until-next-sync", root.GetProperty("offlineAuthorization").GetProperty("effectivePermissions").GetString());
        Assert.Equal("deny", root.GetProperty("offlineAuthorization").GetProperty("localPermissionExpansion").GetString());
        Assert.Equal("every-operation", root.GetProperty("offlineAuthorization").GetProperty("audit").GetString());
    }

    [Fact]
    public void SourceChangesPreservePendingWorkAndRequireFullReconciliationForIdentityChanges()
    {
        using var policy = ReadPolicy();
        var sources = policy.RootElement.GetProperty("sources");

        Assert.Equal("stable-source-id", sources.GetProperty("checkpointKey").GetString());
        Assert.Equal("preserve-outbox-and-checkpoints", sources.GetProperty("addressOrCertificateChange").GetString());
        Assert.Equal("full-reconciliation-before-sending", sources.GetProperty("sourceIdentityChange").GetString());
        Assert.Equal("preserve-with-original-operation-ids", sources.GetProperty("pendingOperationsOnIdentityChange").GetString());
    }

    [Fact]
    public void TimeCursorAndTemporaryReferenceRulesAreExplicit()
    {
        using var policy = ReadPolicy();
        var root = policy.RootElement;

        Assert.Equal("UTC", root.GetProperty("time").GetProperty("storageAndTransport").GetString());
        Assert.Equal(300, root.GetProperty("time").GetProperty("maximumRequestClockSkewSeconds").GetInt32());
        Assert.Equal("source-id-and-channel", root.GetProperty("cursors").GetProperty("scope").GetString());
        Assert.Equal("after-durable-local-commit", root.GetProperty("cursors").GetProperty("advance").GetString());
        Assert.Equal("pending-unresolved-reference", root.GetProperty("clientIdentity").GetProperty("unresolvedReferenceState").GetString());
        Assert.Equal("preserve-client-uuid-and-add-canonical-id", root.GetProperty("clientIdentity").GetProperty("resolution").GetString());
        Assert.Equal("critical-conflict-deleted-reference", root.GetProperty("clientIdentity").GetProperty("deletedReferencedEntity").GetString());
    }

    private static JsonDocument ReadPolicy()
    {
        var path = Path.Combine(AppContext.BaseDirectory, "spec", "domain", "sync-policy.json");
        return JsonDocument.Parse(File.ReadAllText(path));
    }
}
