using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;

namespace Alhayah.LocalServer.Authentication;

public sealed class DeviceCertificateValidator : IDisposable
{
    private readonly X509Certificate2 _trustedRootCertificate;
    private readonly IReadOnlyList<byte[]> _allowedSpkiPins;
    private readonly TimeProvider _timeProvider;

    public DeviceCertificateValidator(
        X509Certificate2 trustedRootCertificate,
        IEnumerable<string> allowedSpkiPins,
        TimeProvider timeProvider)
    {
        _trustedRootCertificate = new X509Certificate2(
            trustedRootCertificate ?? throw new ArgumentNullException(nameof(trustedRootCertificate)));
        ArgumentNullException.ThrowIfNull(allowedSpkiPins);
        _allowedSpkiPins = allowedSpkiPins.Select(ParsePin).ToArray();
        if (_allowedSpkiPins.Count == 0)
        {
            throw new ArgumentException("At least one SHA-256 SPKI pin is required.", nameof(allowedSpkiPins));
        }

        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
    }

    public bool Validate(X509Certificate2? certificate)
    {
        if (certificate is null)
        {
            return false;
        }

        try
        {
            using X509Chain chain = new();
            chain.ChainPolicy.TrustMode = X509ChainTrustMode.CustomRootTrust;
            chain.ChainPolicy.CustomTrustStore.Add(_trustedRootCertificate);
            chain.ChainPolicy.RevocationMode = X509RevocationMode.NoCheck;
            chain.ChainPolicy.VerificationTime = _timeProvider.GetUtcNow().UtcDateTime;
            chain.ChainPolicy.DisableCertificateDownloads = true;
            if (!chain.Build(certificate))
            {
                return false;
            }

            byte[] presentedPin = ComputeSpkiPinHash(certificate);
            return _allowedSpkiPins.Any(pin => CryptographicOperations.FixedTimeEquals(pin, presentedPin));
        }
        catch (CryptographicException)
        {
            return false;
        }
    }

    public static string ComputeSpkiPin(X509Certificate2 certificate)
    {
        ArgumentNullException.ThrowIfNull(certificate);
        return Convert.ToBase64String(ComputeSpkiPinHash(certificate));
    }

    public void Dispose() => _trustedRootCertificate.Dispose();

    private static byte[] ComputeSpkiPinHash(X509Certificate2 certificate) =>
        SHA256.HashData(certificate.PublicKey.GetSubjectPublicKeyInfo());

    private static byte[] ParsePin(string pin)
    {
        byte[] bytes = Convert.FromBase64String(pin);
        if (bytes.Length != 32)
        {
            throw new ArgumentException("An SPKI pin must contain a SHA-256 hash.", nameof(pin));
        }

        return bytes;
    }
}
