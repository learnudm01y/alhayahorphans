using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Domain;

[JsonConverter(typeof(JsonStringEnumConverter<ClientIdentityResolution>))]
public enum ClientIdentityResolution
{
    Ready,
    PendingUnresolvedReference,
    BlockedDeletedReference
}

public sealed record ClientIdentityMapping(
    string EntityType,
    Guid ClientUuid,
    string? CanonicalId);

public sealed class ClientIdentityMap
{
    private readonly IReadOnlyDictionary<Guid, ClientIdentityMapping> _mappings;

    private ClientIdentityMap(IReadOnlyDictionary<Guid, ClientIdentityMapping> mappings)
    {
        _mappings = mappings;
    }

    public static ClientIdentityMap Empty { get; } = new(new Dictionary<Guid, ClientIdentityMapping>());

    public ClientIdentityMap AddPending(string entityType, Guid clientUuid)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(entityType);
        if (clientUuid == Guid.Empty)
        {
            throw new ArgumentException("Client UUID must not be empty.", nameof(clientUuid));
        }

        if (_mappings.TryGetValue(clientUuid, out var existing))
        {
            if (!string.Equals(existing.EntityType, entityType, StringComparison.Ordinal))
            {
                throw new InvalidOperationException("Client UUID is already assigned to another entity type.");
            }

            return this;
        }

        var mappings = new Dictionary<Guid, ClientIdentityMapping>(_mappings)
        {
            [clientUuid] = new ClientIdentityMapping(entityType, clientUuid, null)
        };
        return new ClientIdentityMap(mappings);
    }

    public ClientIdentityMap Resolve(Guid clientUuid, string canonicalId)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(canonicalId);
        if (!_mappings.TryGetValue(clientUuid, out var existing))
        {
            throw new KeyNotFoundException("Client UUID must be registered as pending before canonical resolution.");
        }

        if (existing.CanonicalId is not null)
        {
            if (string.Equals(existing.CanonicalId, canonicalId, StringComparison.Ordinal))
            {
                return this;
            }

            throw new InvalidOperationException("Client UUID already has a different canonical identifier.");
        }

        if (_mappings.Values.Any(mapping =>
                mapping.ClientUuid != clientUuid &&
                string.Equals(mapping.EntityType, existing.EntityType, StringComparison.Ordinal) &&
                string.Equals(mapping.CanonicalId, canonicalId, StringComparison.Ordinal)))
        {
            throw new InvalidOperationException("Canonical identifier is already mapped to another client UUID.");
        }

        var mappings = new Dictionary<Guid, ClientIdentityMapping>(_mappings)
        {
            [clientUuid] = existing with { CanonicalId = canonicalId }
        };
        return new ClientIdentityMap(mappings);
    }

    public bool TryGetMapping(Guid clientUuid, out ClientIdentityMapping? mapping) =>
        _mappings.TryGetValue(clientUuid, out mapping);

    public SyncEnvelope ResolvePendingReferences(SyncEnvelope envelope)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        return envelope with { Entity = ResolveDescriptor(envelope.Entity) };
    }

    public ClientIdentityResolution Evaluate(EntityDescriptor entity)
    {
        ArgumentNullException.ThrowIfNull(entity);
        var resolved = ResolveDescriptor(entity);

        if (resolved.References.Any(reference => reference.IsDeleted))
        {
            return ClientIdentityResolution.BlockedDeletedReference;
        }

        if (string.IsNullOrWhiteSpace(resolved.CanonicalId) ||
            resolved.References.Any(reference => string.IsNullOrWhiteSpace(reference.TargetCanonicalId)))
        {
            return ClientIdentityResolution.PendingUnresolvedReference;
        }

        return ClientIdentityResolution.Ready;
    }

    private EntityDescriptor ResolveDescriptor(EntityDescriptor entity)
    {
        var canonicalId = entity.CanonicalId;
        if (_mappings.TryGetValue(entity.ClientUuid, out var entityMapping))
        {
            EnsureEntityType(entity.EntityType, entityMapping);
            canonicalId = MergeCanonicalId(canonicalId, entityMapping.CanonicalId);
        }

        var references = entity.References.Select(reference =>
        {
            if (reference.TargetClientUuid is not Guid clientUuid ||
                !_mappings.TryGetValue(clientUuid, out var mapping))
            {
                return reference;
            }

            EnsureEntityType(reference.TargetEntityType, mapping);
            return reference with
            {
                TargetCanonicalId = MergeCanonicalId(reference.TargetCanonicalId, mapping.CanonicalId)
            };
        }).ToList();

        return entity with { CanonicalId = canonicalId, References = references };
    }

    private static string? MergeCanonicalId(string? current, string? resolved)
    {
        if (current is not null && resolved is not null &&
            !string.Equals(current, resolved, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("A reference contains a canonical identifier that conflicts with the identity map.");
        }

        return current ?? resolved;
    }

    private static void EnsureEntityType(string expected, ClientIdentityMapping mapping)
    {
        if (!string.Equals(expected, mapping.EntityType, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("Client UUID is mapped to a different entity type.");
        }
    }
}
