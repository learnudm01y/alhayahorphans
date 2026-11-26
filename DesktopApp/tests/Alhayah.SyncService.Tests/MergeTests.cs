using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.SyncService.Merge;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class MergeTests
{
    [Fact]
    public void Merge_WithNonOverlappingChanges_CombinesBothSidesWithoutLosingLocalValues()
    {
        FieldPatch patch = Patch("displayName", "base", "local", FieldCriticality.Mergeable);

        SafeMergeResult result = new SafeMergeService().Merge(
            Payload("base", "old-note"),
            Payload("local", "old-note"),
            Payload("base", "remote-note"),
            [patch]);

        Assert.False(result.ShouldHold);
        Assert.Equal(ConflictClass.NonOverlapping, result.Classification);
        Assert.Equal("local", Read(result.PayloadJson, "displayName"));
        Assert.Equal("remote-note", Read(result.PayloadJson, "note"));
    }

    [Fact]
    public void Merge_WithConcurrentEdits_HoldsRemoteAndPreservesNewerLocalPayload()
    {
        FieldPatch patch = Patch("displayName", "base", "local-newer", FieldCriticality.Mergeable);

        SafeMergeResult result = new SafeMergeService().Merge(
            Payload("base"), Payload("local-newer"), Payload("remote-newer"), [patch]);

        Assert.True(result.ShouldHold);
        Assert.Equal(ConflictClass.ConcurrentField, result.Classification);
        Assert.Equal("local-newer", Read(result.PayloadJson, "displayName"));
    }

    [Theory]
    [InlineData("amount")]
    [InlineData("currency")]
    [InlineData("status")]
    [InlineData("canonicalId")]
    [InlineData("nationalId")]
    public void Merge_WithCriticalFieldConflict_HoldsEvenWhenCriticalityWasNotExplicit(string fieldName)
    {
        FieldPatch patch = Patch(fieldName, "base", "local", FieldCriticality.Mergeable);

        SafeMergeResult result = new SafeMergeService().Merge(
            Payload(fieldName, "base"), Payload(fieldName, "local"), Payload(fieldName, "remote"), [patch]);

        Assert.True(result.ShouldHold);
        Assert.Equal(ConflictClass.CriticalField, result.Classification);
    }

    [Fact]
    public void Merge_WithRemoteDeleteAndPendingLocalEdit_HoldsDeleteAgainstUpdate()
    {
        SafeMergeResult result = new SafeMergeService().Merge(
            Payload("note", "base"), Payload("note", "local"), "{}", [Patch("note", "base", "local")], remoteDeleted: true);

        Assert.True(result.ShouldHold);
        Assert.Equal(ConflictClass.DeleteAgainstUpdate, result.Classification);
        Assert.Equal("local", Read(result.PayloadJson, "note"));
        Assert.Equal("local", Assert.Single(result.Patches).LocalValue?.GetString());
        Assert.Null(Assert.Single(result.Patches).RemoteValue);
    }

    [Fact]
    public void Merge_WithDifferentCanonicalIdentity_HoldsWithoutChangingLocalPayload()
    {
        SafeMergeResult result = new SafeMergeService().Merge(
            Payload("displayName", "base"), Payload("displayName", "local"), Payload("displayName", "remote"), [],
            localCanonicalId: "synthetic-local-id", remoteCanonicalId: "synthetic-other-id");

        Assert.True(result.ShouldHold);
        Assert.Equal(ConflictClass.SourceIdentityMismatch, result.Classification);
        Assert.Equal("local", Read(result.PayloadJson, "displayName"));
        Assert.Equal("local", Assert.Single(result.Patches).LocalValue?.GetString());
        Assert.Equal("remote", Assert.Single(result.Patches).RemoteValue?.GetString());
    }

    [Fact]
    public void Merge_WithEquivalentChanges_DoesNotCreateAConflict()
    {
        SafeMergeResult result = new SafeMergeService().Merge(
            Payload("note", "base"), Payload("note", "same"), Payload("note", "same"), [Patch("note", "base", "same")]);

        Assert.False(result.ShouldHold);
        Assert.Equal(ConflictClass.Equivalent, result.Classification);
    }

    private static FieldPatch Patch(string field, string? baseValue, string? localValue, FieldCriticality criticality = FieldCriticality.Mergeable) => new()
    {
        FieldName = field,
        BaseValue = Value(baseValue),
        LocalValue = Value(localValue),
        Criticality = criticality
    };

    private static JsonElement? Value(string? value) => value is null ? null : JsonSerializer.SerializeToElement(value);

    private static string Payload(string field, string value) => JsonSerializer.Serialize(new Dictionary<string, string> { [field] = value });

    private static string? Read(string payload, string field)
    {
        using JsonDocument document = JsonDocument.Parse(payload);
        return document.RootElement.GetProperty(field).GetString();
    }
}
