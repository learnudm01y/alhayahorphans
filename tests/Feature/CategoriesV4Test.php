<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsV4ReferenceData;
use Tests\TestCase;

class CategoriesV4Test extends TestCase
{
    use RefreshDatabase, SeedsV4ReferenceData;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedV4ReferenceData();
        $this->user = User::factory()->create();
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->user->createToken('v4-admin-test')->plainTextToken,
            'X-Device-Id' => 'device-categories-test',
            'X-Sync-Source' => 'app_v4',
            'Accept' => 'application/json',
        ];
    }

    public function test_aid_status_table_is_whitelisted_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('aid_status'));

        $res = $this->getJson('/api/mobile/v4/categories/aid_status', $this->headers());
        $res->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_aid_statuses_plural_and_data_request_status_are_forbidden(): void
    {
        foreach (['aid_statuses', 'data_request_status'] as $bad) {
            $res = $this->getJson("/api/mobile/v4/categories/{$bad}", $this->headers());
            $res->assertStatus(403);
        }
    }

    public function test_description_column_alias_as_name_on_index(): void
    {
        // request_status uses `description`, not `name`
        $res = $this->getJson('/api/mobile/v4/categories/request_status', $this->headers());
        $res->assertStatus(200)->assertJsonPath('success', true);

        foreach ($res->json('data') as $row) {
            $this->assertArrayHasKey('name', $row, 'each row must expose name alias');
            $this->assertArrayHasKey('description', $row, 'raw description must remain visible');
            $this->assertEquals($row['description'], $row['name']);
        }
    }

    public function test_store_maps_name_to_description_for_description_tables(): void
    {
        $res = $this->postJson('/api/mobile/v4/categories/request_status', [
            'name' => 'قيد المراجعة المطور',
        ], $this->headers());

        $res->assertStatus(201)->assertJsonPath('success', true);
        $id = $res->json('id');

        $row = DB::table('request_status')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('قيد المراجعة المطور', $row->description);
        $this->assertObjectNotHasProperty('name', $row, 'raw row has no name column');
    }

    public function test_store_maps_name_to_attribute_for_category_of_relations(): void
    {
        $res = $this->postJson('/api/mobile/v4/categories/category_of_relations', [
            'name' => 'جدّ الأب',
        ], $this->headers());

        $res->assertStatus(201)->assertJsonPath('success', true);
        $id = $res->json('id');

        $row = DB::table('category_of_relations')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('جدّ الأب', $row->attribute);
    }

    public function test_update_maps_name_to_description(): void
    {
        $existing = DB::table('request_status')->orderBy('id')->value('id');
        $this->assertNotNull($existing);

        $res = $this->putJson("/api/mobile/v4/categories/request_status/{$existing}", [
            'name' => 'الحالة المحدثة',
        ], $this->headers());

        $res->assertStatus(200)->assertJsonPath('success', true);

        $row = DB::table('request_status')->where('id', $existing)->first();
        $this->assertSame('الحالة المحدثة', $row->description);
    }

    public function test_search_uses_label_column(): void
    {
        $this->postJson('/api/mobile/v4/categories/request_status', [
            'name' => 'نص بحث فريد جداً',
        ], $this->headers());

        $res = $this->getJson(
            '/api/mobile/v4/categories/request_status?q=' . urlencode('فريد جداً'),
            $this->headers()
        );

        $res->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('data'));
        $this->assertSame('نص بحث فريد جداً', $res->json('data.0.name'));
    }

    public function test_city_table_uses_city_column_alias(): void
    {
        $this->assertTrue(Schema::hasTable('city'));

        $res = $this->postJson('/api/mobile/v4/categories/city', [
            'name' => 'نابلس',
        ], $this->headers());

        $res->assertStatus(201)->assertJsonPath('success', true);
        $id = $res->json('id');

        $row = DB::table('city')->where('id', $id)->first();
        $this->assertSame('نابلس', $row->city);

        $list = $this->getJson('/api/mobile/v4/categories/city?q=' . urlencode('نابلس'), $this->headers());
        $list->assertStatus(200);
        $found = false;
        foreach ($list->json('data') as $r) {
            if (($r['name'] ?? null) === 'نابلس') {
                $found = true;
            }
        }
        $this->assertTrue($found, 'city rows must expose name alias for frontend');
    }

    public function test_forbidden_table_rejected_on_store(): void
    {
        $res = $this->postJson('/api/mobile/v4/categories/users', ['name' => 'x'], $this->headers());
        $res->assertStatus(403);
    }
}
