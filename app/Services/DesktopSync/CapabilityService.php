<?php

namespace App\Services\DesktopSync;

use App\Models\User;

class CapabilityService
{
    public const API_VERSION = '1.0.0';

    public const SYNC_ABILITIES = [
        'sync:read',
        'sync:write',
        'sync:attachments:read',
        'sync:attachments:write',
        'identity:resolve',
    ];

    public function syncAbilities(): array
    {
        return self::SYNC_ABILITIES;
    }

    public function allowedFor(User $user): array
    {
        return array_values(array_filter(
            self::SYNC_ABILITIES,
            static fn (string $ability): bool => $user->tokenCan($ability)
        ));
    }

    public function health(): array
    {
        return array_merge(['status' => 'ok'], $this->metadata());
    }

    public function metadata(): array
    {
        return [
            'api_version' => self::API_VERSION,
            'server_time' => now('UTC')->toISOString(),
        ];
    }
}
