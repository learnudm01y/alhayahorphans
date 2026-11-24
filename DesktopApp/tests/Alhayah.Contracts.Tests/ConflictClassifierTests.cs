using System.Text.Json;
using Alhayah.Contracts.Domain;
using Xunit;

namespace Alhayah.Contracts.Tests;

public sealed class ConflictClassifierTests
{
    [Fact]
    public void DisjointFieldChangesAreSafeToMerge()
    {
        var envelope = CreateEnvelope(
        [
            CreatePatch("notes", "base", "local", "base"),
            CreatePatch("display_name", "base", "base", "remote")
        ]);

        Assert.Equal(ConflictClass.NonOverlapping, ConflictClassifier.Classify(envelope));
    }

    [Fact]
    public void SameCriticalFieldChangedDifferentlyRequiresReview()
    {
        var envelope = CreateEnvelope(
        [
            CreatePatch("amount", "10", "20", "30", FieldCriticality.Critical)
        ]);

        Assert.Equal(ConflictClass.CriticalField, ConflictClassifier.Classify(envelope));
    }

    [Fact]
    public void SameMergeableFieldChangedDifferentlyNeverUsesLastWriteWins()
    {
        var envelope = CreateEnvelope(
        [
            CreatePatch("notes", "base", "local", "remote")
        ]);

        Assert.Equal(ConflictClass.ConcurrentField, ConflictClassifier.Classify(envelope));
    }

    [Fact]
    public void DeleteAgainstUpdateIsHeldForReview()
    {
        var envelope = CreateEnvelope(
        [
            CreatePatch("notes", "base", "local", "base")
        ], SyncOperationType.Delete);

        Assert.Equal(ConflictClass.DeleteAgainstUpdate, ConflictClassifier.Classify(envelope));
    }

    [Fact]
    public void UnresolvedReferenceKeepsOperationPending()
    {
        var envelope = CreateEnvelope([]);
        envelope.Entity.References.Add(new EntityReference
        {
            FieldName = "family_id",
            TargetEntityType = "laravel.families",
            TargetClientUuid = Guid.Parse("7cd66c07-f9a1-418a-a558-c328f8f54020"),
            Kind = EntityReferenceKind.Field
        });

        Assert.Equal(ConflictClass.UnresolvedReference, ConflictClassifier.Classify(envelope));
    }

    [Fact]
    public void DeletedReferenceTakesPrecedenceOverOtherCriticalConflicts()
    {
        var envelope = CreateEnvelope(
        [
            CreatePatch("amount", "10", "20", "30", FieldCriticality.Critical)
        ]);
        envelope.Entity.References.Add(new EntityReference
        {
            FieldName = "family_id",
            TargetEntityType = "laravel.families",
            TargetCanonicalId = "19",
            Kind = EntityReferenceKind.Field,
            IsDeleted = true
        });

        Assert.Equal(ConflictClass.DeletedReference, ConflictClassifier.Classify(envelope));
    }

    [Fact]
    public void SourceIdentityMismatchIsNeverATransportChange()
    {
        var expected = new SyncSourceId("laravel:production");
        var actual = new SyncSourceId("laravel:disaster-recovery");

        Assert.Equal(ConflictClass.SourceIdentityMismatch, ConflictClassifier.ClassifySource(expected, actual));
        Assert.Equal(ConflictClass.None, ConflictClassifier.ClassifySource(expected, new SyncSourceId("LARAVEL:PRODUCTION")));
    }

    private static SyncEnvelope CreateEnvelope(
        IReadOnlyList<FieldPatch> patches,
        SyncOperationType operationType = SyncOperationType.Update) => new()
        {
            OperationId = Guid.Parse("0873851e-b99d-4f53-b66c-f6498bda3021"),
            SourceId = new SyncSourceId("laravel:production"),
            DeviceId = "device-conflict-test",
            Entity = new EntityDescriptor
            {
                EntityType = "laravel.data",
                ClientUuid = Guid.Parse("7e09ae8c-aa78-4e53-982d-f12a2f673022"),
                CanonicalId = "42",
                References = []
            },
            OperationType = operationType,
            BaseVersion = 9,
            RowVersion = "row-9",
            Cursor = "cursor-9",
            OccurredAtUtc = DateTimeOffset.Parse("2026-11-24T08:00:00Z"),
            Patches = patches
        };

    private static FieldPatch CreatePatch(
        string fieldName,
        string baseValue,
        string localValue,
        string remoteValue,
        FieldCriticality criticality = FieldCriticality.Mergeable) => new()
        {
            FieldName = fieldName,
            BaseValue = JsonSerializer.SerializeToElement(baseValue),
            LocalValue = JsonSerializer.SerializeToElement(localValue),
            RemoteValue = JsonSerializer.SerializeToElement(remoteValue),
            Criticality = criticality
        };
}
