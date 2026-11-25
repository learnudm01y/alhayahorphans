using System.Security.Cryptography;
using System.Text;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;

namespace Alhayah.Desktop.Services;

public sealed record AuditEventContext
{
    public required Guid OperationId { get; init; }

    public Guid? OfflineUserId { get; init; }

    public required string DeviceId { get; init; }

    public required string SourceId { get; init; }

    public required string PermissionUsed { get; init; }

    public long? ManifestVersion { get; init; }

    public required DateTimeOffset OccurredAtUtc { get; init; }

    public required AuditResult Result { get; init; }
}

public interface IAuditService
{
    Task RecordAsync(
        AuditEventContext context,
        string payloadJson,
        CancellationToken cancellationToken = default);
}

public sealed class AuditService : IAuditService
{
    private readonly ISyncServiceClient _client;

    public AuditService(ISyncServiceClient client)
    {
        _client = client ?? throw new ArgumentNullException(nameof(client));
    }

    public Task RecordAsync(
        AuditEventContext context,
        string payloadJson,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        ArgumentNullException.ThrowIfNull(payloadJson);
        ArgumentException.ThrowIfNullOrWhiteSpace(context.DeviceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(context.SourceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(context.PermissionUsed);
        if (context.OperationId == Guid.Empty)
        {
            throw new ArgumentException("An audit operation identifier is required.", nameof(context));
        }

        if (!IsKnownPermission(context.PermissionUsed))
        {
            throw new ArgumentException("The audit permission must be an approved local permission.", nameof(context));
        }

        if (context.DeviceId.Length > 128 ||
            context.DeviceId.Any(character => !char.IsAsciiLetterOrDigit(character) && character is not ('.' or '_' or '-')))
        {
            throw new ArgumentException("The audit device identifier has an invalid format.", nameof(context));
        }

        SyncSourceId sourceId = new(context.SourceId);
        AuditEventRequest request = new()
        {
            OperationId = context.OperationId,
            OfflineUserId = context.OfflineUserId,
            DeviceId = context.DeviceId,
            SourceId = sourceId.Value,
            PermissionUsed = context.PermissionUsed,
            ManifestVersion = context.ManifestVersion,
            OccurredAtUtc = context.OccurredAtUtc,
            PayloadSha256 = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(payloadJson))),
            Result = context.Result
        };
        return _client.WriteAuditAsync(request, cancellationToken);
    }

    private static bool IsKnownPermission(string permission) => permission is
        LocalPermissions.Read or
        LocalPermissions.Search or
        LocalPermissions.Save or
        LocalPermissions.Query or
        LocalPermissions.Backup or
        LocalPermissions.Restore or
        LocalPermissions.ManageSettings or
        LocalPermissions.ResolveConflicts or
        LocalPermissions.Synchronize or
        LocalPermissions.WriteAudit;
}
