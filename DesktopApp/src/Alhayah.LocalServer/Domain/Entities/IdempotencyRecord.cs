using System.Text.Json.Serialization;

namespace Alhayah.LocalServer.Domain.Entities;

[JsonConverter(typeof(JsonStringEnumConverter<LocalOperationStatus>))]
public enum LocalOperationStatus
{
    Pending,
    Completed,
    Conflict
}

public sealed class IdempotencyRecord
{
    public Guid Id { get; set; }

    public Guid OperationId { get; set; }

    public string? IdempotencyKey { get; set; }

    public string DeviceId { get; set; } = string.Empty;

    public string OperationType { get; set; } = string.Empty;

    public string RequestHash { get; set; } = string.Empty;

    public string RequestJson { get; set; } = string.Empty;

    public LocalOperationStatus Status { get; set; }

    public string? ResultJson { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset ExpiresAtUtc { get; set; }
}
