using System.Net.Security;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;

namespace Alhayah.SyncService.Services;

public sealed class CertificatePinValidator
{
    private readonly IReadOnlyList<byte[]> _allowedPins;

    public CertificatePinValidator(string currentPin, string? previousPin = null)
    {
        _allowedPins = new[] { currentPin, previousPin }
            .Where(pin => !string.IsNullOrWhiteSpace(pin))
            .Select(pin => ParsePin(pin!))
            .Distinct(ByteArrayComparer.Instance)
            .ToArray();
    }

    public bool Validate(X509Certificate2? certificate, SslPolicyErrors sslPolicyErrors)
    {
        if (certificate is null || sslPolicyErrors != SslPolicyErrors.None)
        {
            return false;
        }

        try
        {
            byte[] presentedPin = SHA256.HashData(certificate.PublicKey.GetSubjectPublicKeyInfo());
            return _allowedPins.Any(pin => CryptographicOperations.FixedTimeEquals(pin, presentedPin));
        }
        catch (CryptographicException)
        {
            return false;
        }
    }

    public static string ComputeSpkiPin(X509Certificate2 certificate)
    {
        ArgumentNullException.ThrowIfNull(certificate);
        return Convert.ToHexString(SHA256.HashData(certificate.PublicKey.GetSubjectPublicKeyInfo()));
    }

    private static byte[] ParsePin(string pin)
    {
        if (pin.Length != 64 || !pin.All(Uri.IsHexDigit))
        {
            throw new ArgumentException("An SPKI SHA-256 pin must contain exactly 64 hexadecimal characters.", nameof(pin));
        }

        return Convert.FromHexString(pin);
    }

    private sealed class ByteArrayComparer : IEqualityComparer<byte[]>
    {
        public static ByteArrayComparer Instance { get; } = new();

        public bool Equals(byte[]? first, byte[]? second) => first is null || second is null
            ? ReferenceEquals(first, second)
            : CryptographicOperations.FixedTimeEquals(first, second);

        public int GetHashCode(byte[] value) => Convert.ToHexString(value).GetHashCode(StringComparison.Ordinal);
    }
}

internal sealed class LaravelCertificatePinException()
    : System.Security.Authentication.AuthenticationException("The server certificate SPKI pin does not match.");
