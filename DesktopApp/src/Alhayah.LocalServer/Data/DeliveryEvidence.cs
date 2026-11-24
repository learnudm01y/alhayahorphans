namespace Alhayah.LocalServer.Data;

public sealed class DeliveryEvidence
{
    public Guid Id { get; set; }

    public Guid DisbursementId { get; set; }

    public Disbursement? Disbursement { get; set; }

    public long RowVersion { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }

    public DateTimeOffset? DeletedAtUtc { get; set; }

    public string EvidenceType { get; set; } = string.Empty;

    public string StoragePath { get; set; } = string.Empty;

    public string Sha256 { get; set; } = string.Empty;

    public DateTimeOffset CapturedAtUtc { get; set; }

    public long CapturedByLaravelUserId { get; set; }

    public string? SignatureMetadataJson { get; set; }

    public string VerificationStatus { get; set; } = string.Empty;

    public string? Notes { get; set; }
}
