using System.Text.Json;
using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Domain;

[JsonConverter(typeof(JsonStringEnumConverter<ConflictClass>))]
public enum ConflictClass
{
    None,
    LocalOnly,
    RemoteOnly,
    Equivalent,
    NonOverlapping,
    ConcurrentField,
    CriticalField,
    VersionMismatch,
    DeleteAgainstUpdate,
    UnresolvedReference,
    DeletedReference,
    SourceIdentityMismatch
}

public static class ConflictClassifier
{
    public static ConflictClass Classify(FieldPatch patch)
    {
        ArgumentNullException.ThrowIfNull(patch);

        var localChanged = !JsonElement.DeepEquals(patch.BaseValue, patch.LocalValue);
        var remoteChanged = !JsonElement.DeepEquals(patch.BaseValue, patch.RemoteValue);

        if (!localChanged && !remoteChanged)
        {
            return ConflictClass.None;
        }

        if (localChanged && !remoteChanged)
        {
            return ConflictClass.LocalOnly;
        }

        if (!localChanged)
        {
            return ConflictClass.RemoteOnly;
        }

        if (JsonElement.DeepEquals(patch.LocalValue, patch.RemoteValue))
        {
            return ConflictClass.Equivalent;
        }

        return patch.Criticality == FieldCriticality.Mergeable
            ? ConflictClass.ConcurrentField
            : ConflictClass.CriticalField;
    }

    public static ConflictClass Classify(SyncEnvelope envelope)
    {
        ArgumentNullException.ThrowIfNull(envelope);

        if (envelope.Entity.References.Any(reference => reference.IsDeleted))
        {
            return ConflictClass.DeletedReference;
        }

        if (envelope.Entity.References.Any(reference => string.IsNullOrWhiteSpace(reference.TargetCanonicalId)))
        {
            return ConflictClass.UnresolvedReference;
        }

        var fieldClasses = envelope.Patches
            .Select(Classify)
            .Where(conflict => conflict != ConflictClass.None)
            .ToArray();

        if (envelope.OperationType == SyncOperationType.Delete && fieldClasses.Length > 0)
        {
            return ConflictClass.DeleteAgainstUpdate;
        }

        if (fieldClasses.Contains(ConflictClass.CriticalField))
        {
            return ConflictClass.CriticalField;
        }

        if (fieldClasses.Contains(ConflictClass.ConcurrentField))
        {
            return ConflictClass.ConcurrentField;
        }

        if (fieldClasses.Length == 0)
        {
            return ConflictClass.None;
        }

        if (fieldClasses.All(conflict => conflict == ConflictClass.Equivalent))
        {
            return ConflictClass.Equivalent;
        }

        var hasLocalOnly = fieldClasses.Contains(ConflictClass.LocalOnly);
        var hasRemoteOnly = fieldClasses.Contains(ConflictClass.RemoteOnly);
        if (hasLocalOnly && hasRemoteOnly)
        {
            return ConflictClass.NonOverlapping;
        }

        return hasLocalOnly ? ConflictClass.LocalOnly : ConflictClass.RemoteOnly;
    }

    public static ConflictClass ClassifySource(SyncSourceId expected, SyncSourceId actual)
    {
        ArgumentNullException.ThrowIfNull(expected);
        ArgumentNullException.ThrowIfNull(actual);

        return expected == actual ? ConflictClass.None : ConflictClass.SourceIdentityMismatch;
    }
}
