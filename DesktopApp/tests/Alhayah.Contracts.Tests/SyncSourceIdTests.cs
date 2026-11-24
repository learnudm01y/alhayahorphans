using System.Text.Json;
using Alhayah.Contracts.Domain;
using Xunit;

namespace Alhayah.Contracts.Tests;

public sealed class SyncSourceIdTests
{
    [Fact]
    public void JsonRepresentationIsAStableStringRatherThanAnEndpoint()
    {
        var sourceId = new SyncSourceId("LARAVEL:Production");

        var json = JsonSerializer.Serialize(sourceId);
        var copy = JsonSerializer.Deserialize<SyncSourceId>(json);

        Assert.Equal("laravel:production", sourceId.Value);
        Assert.Equal("\"laravel:production\"", json);
        Assert.Equal(sourceId, copy);
    }

    [Fact]
    public void UrlCannotBecomeASourceIdentity()
    {
        Assert.Throws<ArgumentException>(() => new SyncSourceId("https://sync.example.test/api"));
    }

    [Fact]
    public void TransportChangesDoNotChangeTheSourceIdentityValue()
    {
        var beforeCertificateAndAddressChange = new SyncSourceId("local:main-office");
        var afterCertificateAndAddressChange = new SyncSourceId(beforeCertificateAndAddressChange.Value);

        Assert.Equal(beforeCertificateAndAddressChange, afterCertificateAndAddressChange);
        Assert.Equal(
            JsonSerializer.Serialize(beforeCertificateAndAddressChange),
            JsonSerializer.Serialize(afterCertificateAndAddressChange));
    }
}
