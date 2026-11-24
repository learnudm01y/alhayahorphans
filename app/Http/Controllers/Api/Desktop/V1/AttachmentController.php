<?php

namespace App\Http\Controllers\Api\Desktop\V1;

use App\Http\Controllers\Controller;
use App\Services\DesktopSync\AttachmentSyncService;
use App\Services\DesktopSync\DeviceCredentialService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentSyncService $attachments,
        private readonly DeviceCredentialService $credentials
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'entityType' => ['sometimes', 'nullable', 'string', 'max:128'],
            'primaryKey' => ['sometimes', 'nullable', 'string', 'max:191'],
            'clientUuid' => ['sometimes', 'nullable', 'uuid'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            return response()->json($this->attachments->list($device, $validated));
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function store(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'operationId' => ['required', 'uuid'],
            'attachmentUuid' => ['required', 'uuid'],
            'entityType' => ['required', 'string', 'max:128'],
            'primaryKey' => ['sometimes', 'nullable', 'string', 'max:191'],
            'clientUuid' => ['sometimes', 'nullable', 'uuid'],
            'attachmentReferenceKind' => ['sometimes', 'nullable', 'string', 'max:64'],
            'canonicalAttachmentId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'originalFileName' => ['required', 'string', 'max:255'],
            'mimeType' => ['required', 'string', 'max:191'],
            'fileSize' => ['required', 'integer', 'min:1'],
            'sha256' => ['required', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'chunkSize' => ['required', 'integer', 'min:1'],
            'totalChunks' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            return response()->json($this->attachments->initialize($device, $validated));
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function status(Request $request, string $attachmentUuid): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        try {
            return response()->json($this->attachments->status($device, $attachmentUuid));
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function chunk(Request $request, string $attachmentUuid, string $chunkIndex): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $validated = validator([
            'operationId' => $request->header('X-Operation-Id'),
            'chunkIndex' => $chunkIndex,
            'checksum' => $request->header('X-Chunk-Sha256'),
        ], [
            'operationId' => ['required', 'uuid'],
            'chunkIndex' => ['required', 'integer', 'min:0', 'max:9999'],
            'checksum' => ['required', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ])->validate();

        try {
            return response()->json($this->attachments->uploadChunk(
                $device,
                $attachmentUuid,
                (int) $validated['chunkIndex'],
                $validated['operationId'],
                $validated['checksum'],
                $request->getContent()
            ));
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function complete(Request $request, string $attachmentUuid): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'operationId' => ['required', 'uuid'],
        ]);

        try {
            return response()->json($this->attachments->complete(
                $device,
                $attachmentUuid,
                $validated['operationId']
            ));
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function download(Request $request, string $attachmentUuid): StreamedResponse|JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        try {
            $attachment = $this->attachments->download($device, $attachmentUuid);

            return response()->streamDownload(
                static function () use ($attachment): void {
                    $stream = fopen($attachment['path'], 'rb');
                    if ($stream === false) {
                        throw new RuntimeException('The validated attachment content could not be read.');
                    }

                    try {
                        fpassthru($stream);
                    } finally {
                        fclose($stream);
                    }
                },
                $attachment['name'],
                ['Content-Type' => $attachment['mimeType']]
            );
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    private function authorizedDevice(Request $request): ?object
    {
        $deviceId = $request->header('X-Device-Id');
        $sourceId = $request->header('X-Source-Id');
        $appVersion = $request->header('X-App-Version');
        if (!is_string($deviceId) || !is_string($sourceId) || ($appVersion !== null && !is_string($appVersion))) {
            return null;
        }

        try {
            return $this->credentials->registeredDevice($request->user(), $deviceId, $sourceId, $appVersion);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function invalid(InvalidArgumentException $exception): JsonResponse
    {
        return response()->json([
            'error' => $exception->getMessage(),
            'message' => 'The attachment request is invalid.',
        ], 422);
    }

    private function rejected(DomainException $exception): JsonResponse
    {
        $error = $exception->getMessage();
        $status = match ($error) {
            'attachment_not_found' => 404,
            'attachment_forbidden' => 403,
            default => 409,
        };

        return response()->json([
            'error' => $error,
            'message' => 'The attachment operation was rejected.',
        ], $status);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'error' => 'device_not_registered',
            'message' => 'The device credential is not registered or is no longer active.',
        ], 403);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'desktop_sync_unavailable',
            'message' => 'Desktop attachment synchronization is temporarily unavailable.',
        ], 503);
    }
}
