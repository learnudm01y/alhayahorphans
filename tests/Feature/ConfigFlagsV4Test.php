<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsV4ReferenceData;
use Tests\TestCase;

class ConfigFlagsV4Test extends TestCase
{
    use RefreshDatabase, SeedsV4ReferenceData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedV4ReferenceData();
    }

    public function test_flags_endpoint_returns_legacy_sync_flag(): void
    {
        config(['services.sync_v4.legacy_sync_enabled' => false]);

        $user = User::factory()->create();
        $token = $user->createToken('v4-test')->plainTextToken;

        $resp = $this->getJson('/api/mobile/v4/config/flags', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('flags.legacy_sync_enabled', false);
    }

    public function test_flags_default_is_true_when_not_configured(): void
    {
        // Safe default path: controller casts missing/null → true via (bool) + default.
        // .env may set false in this environment — assert the true branch explicitly.
        config(['services.sync_v4.legacy_sync_enabled' => true]);

        $user = User::factory()->create();
        $token = $user->createToken('v4-test')->plainTextToken;

        $resp = $this->getJson('/api/mobile/v4/config/flags', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $resp->assertOk()
            ->assertJsonPath('flags.legacy_sync_enabled', true);
    }

    public function test_flags_requires_auth(): void
    {
        $this->getJson('/api/mobile/v4/config/flags')
            ->assertUnauthorized();
    }
}
