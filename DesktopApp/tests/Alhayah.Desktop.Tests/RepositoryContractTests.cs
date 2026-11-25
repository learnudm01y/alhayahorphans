using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class RepositoryContractTests
{
    [Theory]
    [InlineData(RepositoryCommands.Read, "repository.read")]
    [InlineData(RepositoryCommands.Search, "repository.search")]
    [InlineData(RepositoryCommands.Save, "repository.save")]
    [InlineData(RepositoryCommands.Query, "repository.query")]
    [InlineData(RepositoryCommands.Backup, "repository.backup")]
    [InlineData(RepositoryCommands.Restore, "repository.restore")]
    public void RepositoryCommands_ContractNames_AreStable(string actual, string expected)
    {
        Assert.Equal(expected, actual);
    }

    [Fact]
    public void SaveEntityRequest_JsonRoundTrip_PreservesSyncEnvelopeAndPayload()
    {
        SaveEntityRequest request = new()
        {
            Envelope = IpcTestData.NewEnvelope(),
            PayloadJson = "{\"displayName\":\"synthetic-value\",\"futureField\":42}",
            SearchText = "synthetic-value"
        };

        SaveEntityRequest copy = JsonSerializer.Deserialize<SaveEntityRequest>(JsonSerializer.Serialize(request))
            ?? throw new InvalidDataException();

        Assert.Equal(request.Envelope.OperationId, copy.Envelope.OperationId);
        Assert.Equal(request.Envelope.Entity.ClientUuid, copy.Envelope.Entity.ClientUuid);
        Assert.Equal(request.Envelope.SourceId, copy.Envelope.SourceId);
        Assert.Equal(request.PayloadJson, copy.PayloadJson);
        Assert.Equal(request.SearchText, copy.SearchText);
    }

    [Fact]
    public void RepositoryEntity_JsonRoundTrip_PreservesConcurrencyAndSoftDeleteFields()
    {
        RepositoryEntity entity = IpcTestData.NewEntity() with
        {
            DeletedAtUtc = DateTimeOffset.Parse("2026-11-25T03:00:00Z"),
            DeletedByDeviceId = "synthetic-device"
        };

        RepositoryEntity copy = JsonSerializer.Deserialize<RepositoryEntity>(JsonSerializer.Serialize(entity))
            ?? throw new InvalidDataException();

        Assert.Equal(entity.RowVersion, copy.RowVersion);
        Assert.Equal(entity.BaseVersion, copy.BaseVersion);
        Assert.Equal(entity.DeletedAtUtc, copy.DeletedAtUtc);
        Assert.Equal(entity.DeletedByDeviceId, copy.DeletedByDeviceId);
    }

    [Fact]
    public async Task ReadQueryBackupAndRestore_WithFakeHost_UseRepositoryContracts()
    {
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) => Task.FromResult(request.Command switch
        {
            RepositoryCommands.Read => host.Ok(request, new ReadEntityResponse { Entity = IpcTestData.NewEntity() }),
            RepositoryCommands.Query => host.Ok(request, new EntityListResponse
            {
                Items = [IpcTestData.NewEntity()],
                TotalCount = 1
            }),
            RepositoryCommands.Backup => host.Ok(request, new BackupResponse
            {
                DirectoryPath = "C:\\synthetic-backup",
                DatabasePath = "C:\\synthetic-backup\\device.db",
                ManifestPath = "C:\\synthetic-backup\\manifest.json"
            }),
            RepositoryCommands.Restore => host.Ok(request, new RestoreResponse
            {
                DestinationDatabasePath = "C:\\synthetic-restore\\device.db",
                DestinationAttachmentDirectory = "C:\\synthetic-restore\\attachments"
            }),
            _ => host.Error(request, "unknown_command")
        });
        SyncServiceClient client = IpcTestData.CreateClient(host);

        ReadEntityResponse read = await client.ReadEntityAsync(IpcTestData.NewReadRequest());
        EntityListResponse query = await client.QueryEntitiesAsync(new QueryEntitiesRequest
        {
            SourceId = "laravel:synthetic",
            EntityType = "synthetic.entity",
            Skip = 0,
            Take = 50,
            IncludeDeleted = true
        });
        BackupResponse backup = await client.CreateBackupAsync(new BackupRequest
        {
            DestinationDirectory = "C:\\synthetic-backup"
        });
        RestoreResponse restore = await client.RestoreBackupAsync(new RestoreRequest
        {
            BackupDirectory = "C:\\synthetic-backup",
            DestinationDatabasePath = "C:\\synthetic-restore\\device.db",
            DestinationAttachmentDirectory = "C:\\synthetic-restore\\attachments"
        });

        Assert.NotNull(read.Entity);
        Assert.Single(query.Items);
        Assert.Equal("C:\\synthetic-backup\\manifest.json", backup.ManifestPath);
        Assert.Equal("C:\\synthetic-restore\\device.db", restore.DestinationDatabasePath);
        Assert.Equal(
            [RepositoryCommands.Read, RepositoryCommands.Query, RepositoryCommands.Backup, RepositoryCommands.Restore],
            host.Commands);
    }

    [Fact]
    public void SearchAndQueryContracts_JsonRoundTrip_PreserveLimitsAndFilters()
    {
        SearchEntitiesRequest search = new()
        {
            SourceId = "laravel:synthetic",
            EntityType = "synthetic.entity",
            Query = "synthetic-query",
            Limit = 125
        };
        QueryEntitiesRequest query = new()
        {
            SourceId = "local:synthetic",
            EntityType = "synthetic.operation",
            Skip = 25,
            Take = 75,
            IncludeDeleted = true
        };

        SearchEntitiesRequest searchCopy = JsonSerializer.Deserialize<SearchEntitiesRequest>(JsonSerializer.Serialize(search))!;
        QueryEntitiesRequest queryCopy = JsonSerializer.Deserialize<QueryEntitiesRequest>(JsonSerializer.Serialize(query))!;

        Assert.Equal(125, searchCopy.Limit);
        Assert.Equal("synthetic-query", searchCopy.Query);
        Assert.Equal(25, queryCopy.Skip);
        Assert.Equal(75, queryCopy.Take);
        Assert.True(queryCopy.IncludeDeleted);
    }
}
