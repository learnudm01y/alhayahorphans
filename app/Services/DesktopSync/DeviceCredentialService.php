<?php

namespace App\Services\DesktopSync;

use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class DeviceCredentialService
{
    private const DEVICE_TABLE = 'desktop_devices_v1';

    private const TOKEN_TABLE = 'personal_access_tokens';

    private const ENROLLMENT_CODE_TTL_SECONDS = 600;

    public function __construct(
        private readonly SchemaGuard $schema,
        private readonly CapabilityService $capabilities
    ) {
    }

    public function createEnrollmentCode(User $administrator, array $data): array
    {
        $this->ensureSchema();

        $enrollmentCode = bin2hex(random_bytes(32));
        $now = now('UTC');
        $expiresAt = $now->copy()->addSeconds(self::ENROLLMENT_CODE_TTL_SECONDS);
        $sourceId = $this->normalizeSourceId($data['sourceId']);

        $device = DB::transaction(function () use ($administrator, $data, $sourceId, $enrollmentCode, $now, $expiresAt): object {
            $device = DB::table(self::DEVICE_TABLE)
                ->where('device_id', $data['deviceId'])
                ->lockForUpdate()
                ->first();

            if ($device !== null && $device->source_id !== $sourceId) {
                throw new DomainException('The registered source identity cannot be changed by enrollment.');
            }

            $values = [
                'source_id' => $sourceId,
                'user_id' => $administrator->getKey(),
                'device_label' => $data['deviceLabel'] ?? null,
                'app_version' => $data['appVersion'],
                'enrollment_code_hash' => hash('sha256', $enrollmentCode),
                'enrollment_code_expires_at' => $expiresAt,
                'updated_at' => $now,
            ];

            if ($device === null) {
                DB::table(self::DEVICE_TABLE)->insert(array_merge($values, [
                    'device_id' => $data['deviceId'],
                    'status' => 'pending',
                    'created_at' => $now,
                ]));
            } else {
                if ($device->status === 'revoked') {
                    $values['status'] = 'pending';
                }

                DB::table(self::DEVICE_TABLE)->where('id', $device->id)->update($values);
            }

            return DB::table(self::DEVICE_TABLE)->where('device_id', $data['deviceId'])->first();
        });

        Log::info('desktop.enrollment_code_created', [
            'device_id' => $device->device_id,
            'source_id' => $device->source_id,
            'user_id' => $administrator->getKey(),
        ]);

        return [
            'enrollmentCode' => $enrollmentCode,
            'enrollmentCodeExpiresAt' => $expiresAt->toISOString(),
            'device' => $this->devicePayload($device),
        ];
    }

    public function register(array $data): array
    {
        $this->ensureSchema();

        $now = now('UTC');
        $sourceId = $this->normalizeSourceId($data['sourceId']);
        $result = DB::transaction(function () use ($data, $sourceId, $now): array {
            $device = DB::table(self::DEVICE_TABLE)
                ->where('device_id', $data['deviceId'])
                ->lockForUpdate()
                ->first();

            if (!$this->hasValidEnrollmentCode($device, $sourceId, $data['enrollmentCode'])) {
                throw new InvalidArgumentException('The enrollment credential is invalid or expired.');
            }

            $user = User::query()->find($device->user_id);
            if ($user === null) {
                throw new InvalidArgumentException('The enrollment credential is invalid or expired.');
            }

            $previousTokenId = (int) $device->personal_access_token_id;
            $newAccessToken = $user->createToken('desktop-sync-v1', $this->capabilities->syncAbilities());
            $accessToken = $newAccessToken->accessToken;

            DB::table(self::DEVICE_TABLE)->where('id', $device->id)->update([
                'source_id' => $sourceId,
                'device_label' => $data['deviceLabel'] ?? null,
                'app_version' => $data['appVersion'],
                'personal_access_token_id' => $accessToken->getKey(),
                'token_hash' => $accessToken->token,
                'enrollment_code_hash' => null,
                'enrollment_code_expires_at' => null,
                'status' => 'active',
                'last_seen_at' => $now,
                'revoked_at' => null,
                'updated_at' => $now,
            ]);

            if ($previousTokenId > 0 && $previousTokenId !== (int) $accessToken->getKey()) {
                DB::table(self::TOKEN_TABLE)->where('id', $previousTokenId)->delete();
            }

            return [
                'token' => $newAccessToken->plainTextToken,
                'token_id' => (int) $accessToken->getKey(),
                'device' => DB::table(self::DEVICE_TABLE)->where('id', $device->id)->first(),
            ];
        });

        Log::info('desktop.device_enrolled', [
            'device_id' => $result['device']->device_id,
            'source_id' => $result['device']->source_id,
            'user_id' => $result['device']->user_id,
            'personal_access_token_id' => $result['token_id'],
        ]);

        return $this->tokenPayload($result['token'], $result['device']);
    }

    public function registeredDevice(
        User $user,
        string $deviceId,
        string $sourceId,
        ?string $appVersion
    ): ?object {
        $this->ensureSchema();

        $accessToken = $user->currentAccessToken();
        if (!is_object($accessToken)
            || !method_exists($accessToken, 'getKey')
            || !isset($accessToken->token)
            || $deviceId === ''
            || ($appVersion !== null && strlen($appVersion) > 32)) {
            return null;
        }

        $device = DB::table(self::DEVICE_TABLE)
            ->where('device_id', $deviceId)
            ->where('source_id', $this->normalizeSourceId($sourceId))
            ->where('personal_access_token_id', $accessToken->getKey())
            ->where('token_hash', $accessToken->token)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if ($device === null) {
            return null;
        }

        $now = now('UTC');
        $values = [
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];
        if ($appVersion !== null) {
            $values['app_version'] = $appVersion;
            $device->app_version = $appVersion;
        }

        DB::table(self::DEVICE_TABLE)->where('id', $device->id)->update($values);
        $device->last_seen_at = $now->toDateTimeString();

        return $device;
    }

    public function rotate(User $user, object $authorizedDevice, ?string $appVersion): array
    {
        $this->ensureSchema();

        $accessToken = $user->currentAccessToken();
        $now = now('UTC');
        $result = DB::transaction(function () use ($user, $accessToken, $authorizedDevice, $appVersion, $now): array {
            $device = $this->lockActiveDevice($authorizedDevice->id, $accessToken);
            $newAccessToken = $user->createToken('desktop-sync-v1', $this->capabilities->syncAbilities());
            $newToken = $newAccessToken->accessToken;
            $values = [
                'personal_access_token_id' => $newToken->getKey(),
                'token_hash' => $newToken->token,
                'last_seen_at' => $now,
                'updated_at' => $now,
            ];
            if ($appVersion !== null) {
                $values['app_version'] = $appVersion;
            }

            DB::table(self::DEVICE_TABLE)->where('id', $device->id)->update($values);
            DB::table(self::TOKEN_TABLE)->where('id', $accessToken->getKey())->delete();

            return [
                'token' => $newAccessToken->plainTextToken,
                'token_id' => (int) $newToken->getKey(),
                'device' => DB::table(self::DEVICE_TABLE)->where('id', $device->id)->first(),
            ];
        });

        Log::info('desktop.token_rotated', [
            'device_id' => $result['device']->device_id,
            'source_id' => $result['device']->source_id,
            'user_id' => $user->getKey(),
            'personal_access_token_id' => $result['token_id'],
        ]);

        return $this->tokenPayload($result['token'], $result['device']);
    }

    public function revoke(User $user, object $authorizedDevice): object
    {
        $this->ensureSchema();

        $accessToken = $user->currentAccessToken();
        $now = now('UTC');
        $device = DB::transaction(function () use ($accessToken, $authorizedDevice, $now): object {
            $device = $this->lockActiveDevice($authorizedDevice->id, $accessToken);

            DB::table(self::DEVICE_TABLE)->where('id', $device->id)->update([
                'personal_access_token_id' => null,
                'token_hash' => null,
                'enrollment_code_hash' => null,
                'enrollment_code_expires_at' => null,
                'status' => 'revoked',
                'last_seen_at' => $now,
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table(self::TOKEN_TABLE)->where('id', $accessToken->getKey())->delete();

            return DB::table(self::DEVICE_TABLE)->where('id', $device->id)->first();
        });

        Log::info('desktop.token_revoked', [
            'device_id' => $device->device_id,
            'source_id' => $device->source_id,
            'user_id' => $user->getKey(),
        ]);

        return $device;
    }

    public function devicePayload(object $device): array
    {
        return [
            'deviceId' => $device->device_id,
            'sourceId' => $device->source_id,
            'appVersion' => $device->app_version,
            'lastSeen' => $device->last_seen_at === null
                ? null
                : Carbon::parse($device->last_seen_at, 'UTC')->toISOString(),
            'status' => $device->status,
        ];
    }

    private function tokenPayload(string $plaintextToken, object $device): array
    {
        return [
            'token' => $plaintextToken,
            'token_type' => 'Bearer',
            'abilities' => $this->capabilities->syncAbilities(),
            'device' => $this->devicePayload($device),
        ];
    }

    private function hasValidEnrollmentCode(?object $device, string $sourceId, string $enrollmentCode): bool
    {
        if ($device === null
            || $device->source_id !== $sourceId
            || $device->enrollment_code_hash === null
            || $device->enrollment_code_expires_at === null
            || !Carbon::parse($device->enrollment_code_expires_at, 'UTC')->isFuture()) {
            return false;
        }

        return hash_equals(
            $device->enrollment_code_hash,
            hash('sha256', $enrollmentCode)
        );
    }

    private function lockActiveDevice(int $deviceId, object $accessToken): object
    {
        $device = DB::table(self::DEVICE_TABLE)->where('id', $deviceId)->lockForUpdate()->first();

        if ($device === null
            || $device->status !== 'active'
            || $device->revoked_at !== null
            || (int) $device->personal_access_token_id !== (int) $accessToken->getKey()
            || !hash_equals((string) $device->token_hash, (string) $accessToken->token)) {
            throw new DomainException('The device credential is no longer active.');
        }

        return $device;
    }

    private function normalizeSourceId(string $sourceId): string
    {
        return strtolower(trim($sourceId));
    }

    private function ensureSchema(): void
    {
        if (!$this->schema->hasColumns(self::DEVICE_TABLE, [
            'id',
            'device_id',
            'source_id',
            'user_id',
            'device_label',
            'app_version',
            'personal_access_token_id',
            'token_hash',
            'enrollment_code_hash',
            'enrollment_code_expires_at',
            'status',
            'last_seen_at',
            'revoked_at',
            'created_at',
            'updated_at',
        ]) || !$this->schema->hasColumns(self::TOKEN_TABLE, [
            'id',
            'tokenable_type',
            'tokenable_id',
            'name',
            'token',
            'abilities',
            'expires_at',
            'created_at',
            'updated_at',
        ])) {
            throw new RuntimeException('Desktop device authentication is unavailable.');
        }
    }
}
