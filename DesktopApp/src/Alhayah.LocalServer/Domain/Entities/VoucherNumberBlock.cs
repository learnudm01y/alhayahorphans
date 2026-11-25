namespace Alhayah.LocalServer.Domain.Entities;

public sealed class VoucherNumberBlock
{
    public Guid Id { get; set; }

    public Guid SequenceId { get; set; }

    public string DeviceId { get; set; } = string.Empty;

    public long FirstNumber { get; set; }

    public long LastNumber { get; set; }

    public long NextNumber { get; set; }
}
