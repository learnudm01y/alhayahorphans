<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\MobileSyncController;
use Illuminate\Http\Request;

echo "=== Testing Mobile Sync API ===\n\n";

$controller = new MobileSyncController();

// Test 1: Get Associations
echo "1. Testing getAssociations():\n";
$response = $controller->getAssociations();
$data = json_decode($response->getContent(), true);
echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "   Count: " . $data['count'] . "\n";
if ($data['count'] > 0) {
    echo "   First 3:\n";
    foreach (array_slice($data['associations'], 0, 3) as $a) {
        echo "     - ID: {$a['id']} | Name: {$a['name']}\n";
    }
}

// Test 2: Get Sponsorship Statuses
echo "\n2. Testing getSponsorshipStatuses():\n";
$response = $controller->getSponsorshipStatuses();
$data = json_decode($response->getContent(), true);
echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "   Count: " . $data['count'] . "\n";
if ($data['count'] > 0) {
    echo "   Statuses:\n";
    foreach ($data['statuses'] as $s) {
        echo "     - ID: {$s['id']} | Name: {$s['name']}\n";
    }
}

// Test 3: Get Orphans
echo "\n3. Testing getOrphans():\n";
$request = new Request();
$response = $controller->getOrphans($request);
$data = json_decode($response->getContent(), true);
echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "   Total: " . $data['pagination']['total'] . "\n";
if (count($data['data']) > 0) {
    echo "   First 3:\n";
    foreach (array_slice($data['data'], 0, 3) as $o) {
        echo "     - Reg: {$o['registration_id']} | Name: {$o['full_name']} | ID: " . ($o['identity_number'] ?? 'N/A') . "\n";
    }
}

// Test 4: Search Orphans
echo "\n4. Testing getOrphans() with search:\n";
$request = new Request(['search' => '002190']);
$response = $controller->getOrphans($request);
$data = json_decode($response->getContent(), true);
echo "   Search term: 002190\n";
echo "   Results: " . $data['pagination']['total'] . "\n";
if (count($data['data']) > 0) {
    foreach ($data['data'] as $o) {
        echo "     - Reg: {$o['registration_id']} | Name: {$o['full_name']}\n";
    }
}

// Test 5: Get Initial Sync
echo "\n5. Testing getInitialSync():\n";
$request = new Request();
$response = $controller->getInitialSync($request);
$data = json_decode($response->getContent(), true);
echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "   Timestamp: " . $data['sync_timestamp'] . "\n";
echo "   Associations: " . count($data['data']['associations']) . "\n";
echo "   Statuses: " . count($data['data']['sponsorship_statuses']) . "\n";
echo "   Orphan Counts: " . count($data['data']['orphan_counts']) . "\n";

echo "\n=== All tests completed ===\n";
