using System.Buffers.Binary;
using System.IO.Pipes;
using System.Security.Principal;
using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.SyncService.Ipc;

namespace Alhayah.SyncService.Tests;

internal sealed class TestIpcClient(string pipeName)
{
    private readonly string _pipeName = pipeName;

    public async Task<SyncServiceResponse> SendAsync<TPayload>(
        string command,
        TPayload payload,
        LocalSession? session,
        CancellationToken cancellationToken = default)
    {
        SyncServiceRequest request = new()
        {
            RequestId = Guid.NewGuid(),
            ProtocolVersion = SyncServiceProtocol.Version,
            Command = command,
            Payload = JsonSerializer.SerializeToElement(payload, SyncServiceJson.Options),
            SessionId = session?.SessionId,
            SessionProof = session?.SessionProof
        };
        return await SendEnvelopeAsync(request, cancellationToken);
    }

    public async Task<SyncServiceResponse> SendEnvelopeAsync(
        SyncServiceRequest request,
        CancellationToken cancellationToken = default)
    {
        byte[] frame = JsonSerializer.SerializeToUtf8Bytes(request, SyncServiceJson.Options);
        return await SendRawFrameAsync(frame, cancellationToken);
    }

    public async Task<SyncServiceResponse> SendRawFrameAsync(
        byte[] frame,
        CancellationToken cancellationToken = default)
    {
        byte[] prefix = new byte[4];
        BinaryPrimitives.WriteInt32LittleEndian(prefix, frame.Length);
        return await SendBytesAsync(prefix, frame, cancellationToken);
    }

    public async Task<SyncServiceResponse> SendLengthOnlyAsync(
        int length,
        CancellationToken cancellationToken = default)
    {
        byte[] prefix = new byte[4];
        BinaryPrimitives.WriteInt32LittleEndian(prefix, length);
        return await SendBytesAsync(prefix, [], cancellationToken);
    }

    private async Task<SyncServiceResponse> SendBytesAsync(
        byte[] prefix,
        byte[] frame,
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        await using NamedPipeClientStream client = new(
            ".",
            _pipeName,
            PipeDirection.InOut,
            PipeOptions.Asynchronous,
            TokenImpersonationLevel.Identification);
        await client.ConnectAsync(2_000, cancellationToken);
        await client.WriteAsync(prefix, cancellationToken);
        if (frame.Length > 0)
        {
            await client.WriteAsync(frame, cancellationToken);
        }

        await client.FlushAsync(cancellationToken);
        byte[] responsePrefix = await ReadExactAsync(client, 4, cancellationToken);
        int responseLength = BinaryPrimitives.ReadInt32LittleEndian(responsePrefix);
        if (responseLength <= 0 || responseLength > SyncServiceProtocol.MaxFrameBytes)
        {
            throw new InvalidDataException("The synthetic IPC response frame is invalid.");
        }

        byte[] responseFrame = await ReadExactAsync(client, responseLength, cancellationToken);
        return JsonSerializer.Deserialize<SyncServiceResponse>(responseFrame, SyncServiceJson.Options)
            ?? throw new InvalidDataException("The synthetic IPC response is empty.");
    }

    private static async Task<byte[]> ReadExactAsync(
        Stream stream,
        int length,
        CancellationToken cancellationToken)
    {
        byte[] buffer = new byte[length];
        int offset = 0;
        while (offset < length)
        {
            int read = await stream.ReadAsync(buffer.AsMemory(offset, length - offset), cancellationToken);
            if (read == 0)
            {
                throw new EndOfStreamException("The synthetic IPC frame ended early.");
            }

            offset += read;
        }

        return buffer;
    }
}
