using Alhayah.Contracts.Ipc;
using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Storage;
using Alhayah.SyncService.Synchronization;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class ServiceHostTests
{
    [Fact]
    public async Task Worker_WithoutUserInterface_InitializesStoreAndStartsPipeHost()
    {
        FakeStoreInitializer initializer = new();
        FakeConnectionAccepter accepter = new();
        NamedPipeHostOptions options = new()
        {
            PipeName = $"Alhayah.Tests.Host.{Guid.NewGuid():N}",
            RequestTimeout = TimeSpan.FromSeconds(2)
        };
        await using NamedPipeHost pipeHost = new(
            options,
            new FakeCommandHandler(),
            accepter,
            NullLogger<NamedPipeHost>.Instance);
        Worker worker = new(initializer, pipeHost, NullLogger<Worker>.Instance);

        await worker.StartAsync(CancellationToken.None);
        await initializer.Initialized.Task.WaitAsync(TimeSpan.FromSeconds(2));

        Assert.True(pipeHost.IsRunning);
        await worker.StopAsync(CancellationToken.None);
        Assert.False(pipeHost.IsRunning);
        Assert.True(accepter.AcceptCalled);
    }

    [Fact]
    public async Task NamedPipeHost_WhenHandlerExceedsDeadline_ReturnsTimeoutWithoutExceptionDetails()
    {
        string pipeName = $"Alhayah.Tests.Timeout.{Guid.NewGuid():N}";
        NamedPipeHostOptions options = new()
        {
            PipeName = pipeName,
            RequestTimeout = TimeSpan.FromMilliseconds(30)
        };
        await using NamedPipeHost host = new(
            options,
            new BlockingCommandHandler(),
            new WindowsNamedPipeAccepter(options),
            NullLogger<NamedPipeHost>.Instance);
        await host.StartAsync(CancellationToken.None);
        TestIpcClient client = new(pipeName);

        SyncServiceResponse response = await client.SendAsync(
            RepositoryCommands.Read,
            new ReadEntityRequest
            {
                SourceId = "laravel:synthetic",
                EntityType = "synthetic.entity",
                ClientUuid = Guid.Parse("d84a99d5-df12-471f-a4f5-c15f82664c02")
            },
            session: null);

        Assert.False(response.Success);
        Assert.Equal("timeout", response.Error?.Code);
        Assert.DoesNotContain("Exception", response.Error?.Code, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public async Task SyncChannels_BeforeConnectorRegistration_AreUnavailableAndSynchronizationStaysPending()
    {
        SyncChannelRegistry channels = new(Array.Empty<ISyncChannel>());
        const string sourceId = "laravel:synthetic";

        ConnectionCheckResult check = await channels.CheckConnectionAsync(sourceId, CancellationToken.None);
        SynchronizeNowResult synchronization = await channels.SynchronizeNowAsync(sourceId, CancellationToken.None);

        Assert.Equal(sourceId, check.SourceId);
        Assert.Equal(["DNS", "TCP", "TLS", "Auth", "Compatibility"], check.Stages.Select(stage => stage.Stage));
        Assert.All(check.Stages, stage =>
        {
            Assert.False(stage.Succeeded);
            Assert.Equal("connector_unavailable", stage.ErrorCode);
        });
        Assert.Equal(sourceId, synchronization.SourceId);
        Assert.Equal("Pending", synchronization.Status);
    }

    private sealed class FakeStoreInitializer : ILocalStoreInitializer
    {
        public TaskCompletionSource Initialized { get; } = new(TaskCreationOptions.RunContinuationsAsynchronously);

        public Task InitializeAsync(CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            Initialized.TrySetResult();
            return Task.CompletedTask;
        }
    }

    private sealed class FakeConnectionAccepter : INamedPipeConnectionAccepter
    {
        public bool AcceptCalled { get; private set; }

        public async Task<NamedPipeConnection> AcceptAsync(CancellationToken cancellationToken)
        {
            AcceptCalled = true;
            await Task.Delay(Timeout.InfiniteTimeSpan, cancellationToken);
            throw new OperationCanceledException(cancellationToken);
        }
    }

    private sealed class FakeCommandHandler : ILocalStoreCommandHandler
    {
        public Task<object> HandleAsync(SyncServiceRequest request, CancellationToken cancellationToken) =>
            Task.FromResult<object>(new AcknowledgedResponse { Acknowledged = true });
    }

    private sealed class BlockingCommandHandler : ILocalStoreCommandHandler
    {
        public async Task<object> HandleAsync(SyncServiceRequest request, CancellationToken cancellationToken)
        {
            await Task.Delay(Timeout.InfiniteTimeSpan, cancellationToken);
            throw new OperationCanceledException(cancellationToken);
        }
    }
}
