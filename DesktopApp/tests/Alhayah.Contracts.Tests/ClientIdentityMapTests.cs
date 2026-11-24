using Alhayah.Contracts.Domain;
using Xunit;

namespace Alhayah.Contracts.Tests;

public sealed class ClientIdentityMapTests
{
    private static readonly Guid OfflineEntityUuid = Guid.Parse("657a3d3b-df0d-45ca-906c-98477c6a4030");

    [Fact]
    public void CanonicalResolutionRewritesFieldAndAttachmentReferencesWithoutLosingClientUuid()
    {
        var pendingMap = ClientIdentityMap.Empty.AddPending("laravel.data", OfflineEntityUuid);
        var map = pendingMap.Resolve(OfflineEntityUuid, "90210");
        var fieldOperation = CreateDependentOperation(EntityReferenceKind.Field);
        var attachmentOperation = CreateDependentOperation(EntityReferenceKind.Attachment);

        var resolvedFieldOperation = map.ResolvePendingReferences(fieldOperation);
        var resolvedAttachmentOperation = map.ResolvePendingReferences(attachmentOperation);

        Assert.Equal("90210", resolvedFieldOperation.Entity.References[0].TargetCanonicalId);
        Assert.Equal(OfflineEntityUuid, resolvedFieldOperation.Entity.References[0].TargetClientUuid);
        Assert.Equal(fieldOperation.OperationId, resolvedFieldOperation.OperationId);
        Assert.Equal("90210", resolvedAttachmentOperation.Entity.References[0].TargetCanonicalId);
        Assert.Equal(OfflineEntityUuid, resolvedAttachmentOperation.Entity.References[0].TargetClientUuid);
        Assert.Equal(EntityReferenceKind.Attachment, resolvedAttachmentOperation.Entity.References[0].Kind);
        Assert.Equal(ClientIdentityResolution.Ready, map.Evaluate(resolvedFieldOperation.Entity));
        Assert.Equal(ClientIdentityResolution.PendingUnresolvedReference, pendingMap.Evaluate(fieldOperation.Entity));
    }

    [Fact]
    public void UnresolvedReferenceKeepsOriginalOperationPendingAndIntact()
    {
        var operation = CreateDependentOperation(EntityReferenceKind.Field);

        var resolved = ClientIdentityMap.Empty.ResolvePendingReferences(operation);

        Assert.Equal(operation.OperationId, resolved.OperationId);
        Assert.Equal(OfflineEntityUuid, resolved.Entity.References[0].TargetClientUuid);
        Assert.Null(resolved.Entity.References[0].TargetCanonicalId);
        Assert.Equal(ClientIdentityResolution.PendingUnresolvedReference, ClientIdentityMap.Empty.Evaluate(resolved.Entity));
    }

    [Fact]
    public void DeletedReferenceBlocksEvenWhenCanonicalIdIsKnown()
    {
        var map = ClientIdentityMap.Empty
            .AddPending("laravel.data", OfflineEntityUuid)
            .Resolve(OfflineEntityUuid, "90210");
        var operation = CreateDependentOperation(EntityReferenceKind.Field);
        operation.Entity.References[0].IsDeleted = true;

        var resolved = map.ResolvePendingReferences(operation);

        Assert.Equal("90210", resolved.Entity.References[0].TargetCanonicalId);
        Assert.Equal(ClientIdentityResolution.BlockedDeletedReference, map.Evaluate(resolved.Entity));
        Assert.Equal(ConflictClass.DeletedReference, ConflictClassifier.Classify(resolved));
    }

    [Fact]
    public void CanonicalResolutionIsImmutableAndCannotBeReassigned()
    {
        var pending = ClientIdentityMap.Empty.AddPending("laravel.data", OfflineEntityUuid);
        var resolved = pending.Resolve(OfflineEntityUuid, "90210");

        Assert.Equal(ClientIdentityResolution.PendingUnresolvedReference, pending.Evaluate(CreateDependentOperation(EntityReferenceKind.Field).Entity));
        Assert.Throws<InvalidOperationException>(() => resolved.Resolve(OfflineEntityUuid, "90211"));
    }

    [Fact]
    public void CanonicalIdCannotBeSharedByTwoClientIdentitiesOfTheSameEntityType()
    {
        var secondUuid = Guid.Parse("b3d63dc1-e4a4-4b3b-995c-43e3f2ee4031");
        var map = ClientIdentityMap.Empty
            .AddPending("laravel.data", OfflineEntityUuid)
            .AddPending("laravel.data", secondUuid)
            .Resolve(OfflineEntityUuid, "90210");

        Assert.Throws<InvalidOperationException>(() => map.Resolve(secondUuid, "90210"));
    }

    private static SyncEnvelope CreateDependentOperation(EntityReferenceKind kind) => new()
    {
        OperationId = kind == EntityReferenceKind.Field
            ? Guid.Parse("39557ed3-2bc7-4c53-8556-8fe4e0b64032")
            : Guid.Parse("f03e91a0-0bb1-4c24-bf0c-c0736e744033"),
        SourceId = new SyncSourceId("laravel:production"),
        DeviceId = "device-identity-test",
        Entity = new EntityDescriptor
        {
            EntityType = kind == EntityReferenceKind.Field ? "laravel.guardians" : "laravel.attachments",
            ClientUuid = kind == EntityReferenceKind.Field
                ? Guid.Parse("8a09a0c7-22a9-44e5-898f-474143e34034")
                : Guid.Parse("e14b8849-e579-48de-b7c6-981f6d234035"),
            CanonicalId = kind == EntityReferenceKind.Field ? "501" : "attachment-701",
            References =
            [
                new EntityReference
                {
                    FieldName = kind == EntityReferenceKind.Field ? "person_id" : "person_identity_number",
                    TargetEntityType = "laravel.data",
                    TargetClientUuid = OfflineEntityUuid,
                    Kind = kind
                }
            ]
        },
        OperationType = SyncOperationType.Create,
        BaseVersion = null,
        RowVersion = null,
        Cursor = null,
        OccurredAtUtc = DateTimeOffset.Parse("2026-11-24T08:00:00Z"),
        Patches = []
    };
}
