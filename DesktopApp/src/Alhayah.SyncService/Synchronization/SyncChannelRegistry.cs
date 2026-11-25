using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;

namespace Alhayah.SyncService.Synchronization;

public interface ISyncChannel
{
    string SourceId { get; }

    Task<ConnectionCheckResult> CheckConnectionAsync(CancellationToken cancellationToken);

    Task<SynchronizeNowResult> SynchronizeNowAsync(CancellationToken cancellationToken);
}

public sealed class SyncChannelRegistry
{
    private readonly IReadOnlyDictionary<string, ISyncChannel> _channels;

    public SyncChannelRegistry(IEnumerable<ISyncChannel> channels)
    {
        ArgumentNullException.ThrowIfNull(channels);
        Dictionary<string, ISyncChannel> registered = new(StringComparer.Ordinal);
        foreach (ISyncChannel channel in channels)
        {
            ArgumentNullException.ThrowIfNull(channel);
            string sourceId = new SyncSourceId(channel.SourceId).Value;
            if (!registered.TryAdd(sourceId, channel))
            {
                throw new ArgumentException("A sync channel can be registered only once for a source.", nameof(channels));
            }
        }

        _channels = registered;
    }

    public Task<ConnectionCheckResult> CheckConnectionAsync(
        string sourceId,
        CancellationToken cancellationToken)
    {
        string validatedSourceId = new SyncSourceId(sourceId).Value;
        if (_channels.TryGetValue(validatedSourceId, out ISyncChannel? channel))
        {
            return channel.CheckConnectionAsync(cancellationToken);
        }

        string[] stages = ["DNS", "TCP", "TLS", "Auth", "Compatibility"];
        return Task.FromResult(new ConnectionCheckResult
        {
            SourceId = validatedSourceId,
            Stages = stages.Select(stage => new ConnectionCheckStageResult
            {
                Stage = stage,
                Succeeded = false,
                ErrorCode = "connector_unavailable"
            }).ToArray()
        });
    }

    public Task<SynchronizeNowResult> SynchronizeNowAsync(
        string sourceId,
        CancellationToken cancellationToken)
    {
        string validatedSourceId = new SyncSourceId(sourceId).Value;
        if (_channels.TryGetValue(validatedSourceId, out ISyncChannel? channel))
        {
            return channel.SynchronizeNowAsync(cancellationToken);
        }

        return Task.FromResult(new SynchronizeNowResult
        {
            SourceId = validatedSourceId,
            Status = "Pending"
        });
    }
}
