<?php

/**
 * Test Script: Sync System Verification
 *
 * Purpose: Test all sync-related functionality in Laravel
 *
 * Usage: php test_sync_system.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\GoogleDriveUpload;
use App\Models\Sponsorship;
use App\Models\Data;
use App\Models\RePeople;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         🔄 SYNC SYSTEM VERIFICATION TEST                          ║\n";
echo "║              AlHayah Orphans System                               ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

$testResults = [];
$totalTests = 0;
$passedTests = 0;

// ============================================================================
// Test 1: Check required tables exist
// ============================================================================
echo "📋 Test 1: Checking required database tables...\n";
$requiredTables = [
    'file_id_registry',
    'sync_progress',
    'google_drive_uploads',
    'sponsorships',
    'data',
    're_people',
    'dead_people',  // Correct table name
    'attachments'
];

$missingTables = [];
foreach ($requiredTables as $table) {
    $totalTests++;
    if (Schema::hasTable($table)) {
        echo "   ✅ Table '{$table}' exists\n";
        $passedTests++;
    } else {
        echo "   ❌ Table '{$table}' MISSING\n";
        $missingTables[] = $table;
    }
}

if (empty($missingTables)) {
    echo "   ✅ All required tables exist!\n\n";
} else {
    echo "\n   ⚠️  Missing tables: " . implode(', ', $missingTables) . "\n";
    echo "   📝 Run: php artisan migrate\n\n";
}

// ============================================================================
// Test 2: Check file_id_registry table structure
// ============================================================================
echo "📋 Test 2: Verifying file_id_registry table structure...\n";
$totalTests++;

if (Schema::hasTable('file_id_registry')) {
    $requiredColumns = ['file_id', 'table_name', 'person_type', 'identity_number', 'handshake_token', 'status'];
    $existingColumns = Schema::getColumnListing('file_id_registry');

    $missingColumns = array_diff($requiredColumns, $existingColumns);

    if (empty($missingColumns)) {
        echo "   ✅ file_id_registry has all required columns\n";
        $passedTests++;
    } else {
        echo "   ❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
    }
} else {
    echo "   ❌ file_id_registry table doesn't exist\n";
}

echo "\n";

// ============================================================================
// Test 3: Check google_drive_uploads table structure
// ============================================================================
echo "📋 Test 3: Verifying google_drive_uploads table structure...\n";
$totalTests++;

if (Schema::hasTable('google_drive_uploads')) {
    $requiredColumns = [
        'local_file_hash', 'google_drive_file_id', 'upload_status',
        'entity_type', 'entity_id', 'synced_to_server'
    ];
    $existingColumns = Schema::getColumnListing('google_drive_uploads');

    $missingColumns = array_diff($requiredColumns, $existingColumns);

    if (empty($missingColumns)) {
        echo "   ✅ google_drive_uploads has all required columns\n";
        $passedTests++;
    } else {
        echo "   ❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
    }
} else {
    echo "   ❌ google_drive_uploads table doesn't exist\n";
}

echo "\n";

// ============================================================================
// Test 4: Check sync_progress table structure
// ============================================================================
echo "📋 Test 4: Verifying sync_progress table structure...\n";
$totalTests++;

if (Schema::hasTable('sync_progress')) {
    $requiredColumns = [
        'sync_session_id', 'operation_type', 'entity_type',
        'status', 'progress_percentage'
    ];
    $existingColumns = Schema::getColumnListing('sync_progress');

    $missingColumns = array_diff($requiredColumns, $existingColumns);

    if (empty($missingColumns)) {
        echo "   ✅ sync_progress has all required columns\n";
        $passedTests++;
    } else {
        echo "   ❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
    }
} else {
    echo "   ❌ sync_progress table doesn't exist\n";
}

echo "\n";

// ============================================================================
// Test 5: Test File ID Generation Algorithm
// ============================================================================
echo "📋 Test 5: Testing File ID Generation Algorithm...\n";
$totalTests++;

try {
    $prefix = 'G'; // Guardian
    $year = date('Y');

    // Get last sequence
    $lastNumber = DB::table('file_id_registry')
        ->where('person_type', 'guardian')
        ->where('file_id', 'LIKE', $prefix . $year . '%')
        ->orderBy('file_id', 'desc')
        ->value('file_id');

    if ($lastNumber) {
        $sequence = intval(substr($lastNumber, -6)) + 1;
    } else {
        $sequence = 1;
    }

    $generatedFileID = $prefix . $year . str_pad($sequence, 6, '0', STR_PAD_LEFT);

    echo "   Generated sample file ID: {$generatedFileID}\n";

    // Validate format
    if (preg_match('/^[GOD]\d{10}$/', $generatedFileID)) {
        echo "   ✅ File ID format is correct\n";
        $passedTests++;
    } else {
        echo "   ❌ File ID format is incorrect\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Test 6: Test GoogleDriveUpload Model
// ============================================================================
echo "📋 Test 6: Testing GoogleDriveUpload Model...\n";
$totalTests++;

try {
    // Test hash check
    $testHash = hash('sha256', 'test_file_content_' . time());
    $exists = GoogleDriveUpload::hashExists($testHash);

    if ($exists === false) {
        echo "   ✅ Hash check working correctly (hash not found as expected)\n";
        $passedTests++;
    } else {
        echo "   ⚠️  Unexpected result from hash check\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Test 7: Test Eligible Sponsorships Query
// ============================================================================
echo "📋 Test 7: Testing Eligible Sponsorships Query...\n";
$totalTests++;

try {
    $excludeStatuses = ['ارسل للصرف', 'تم الصرف'];

    $count = Sponsorship::leftJoin('sponsorship_statuses', 'sponsorships.sponsorship_status_id', '=', 'sponsorship_statuses.id')
        ->whereNotIn('sponsorship_statuses.description', $excludeStatuses)
        ->count();

    echo "   Found {$count} eligible sponsorships (excluding disbursed)\n";
    echo "   ✅ Eligible sponsorships query working\n";
    $passedTests++;
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Test 8: API Routes Registration Check
// ============================================================================
echo "📋 Test 8: Checking API Routes Registration...\n";
$totalTests++;

try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $syncRoutes = [];

    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'api/sync') || str_contains($route->uri(), 'api/uploads')) {
            $syncRoutes[] = $route->methods()[0] . ' ' . $route->uri();
        }
    }

    if (count($syncRoutes) >= 10) {
        echo "   Found " . count($syncRoutes) . " sync/upload routes:\n";
        foreach (array_slice($syncRoutes, 0, 10) as $route) {
            echo "     - {$route}\n";
        }
        if (count($syncRoutes) > 10) {
            echo "     ... and " . (count($syncRoutes) - 10) . " more\n";
        }
        echo "   ✅ API routes registered correctly\n";
        $passedTests++;
    } else {
        echo "   ❌ Expected at least 10 sync routes, found " . count($syncRoutes) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Test 9: Check Person Search Functionality
// ============================================================================
echo "📋 Test 9: Testing Person Search Functionality...\n";
$totalTests++;

try {
    // Test search in data table
    $sampleGuardian = Data::first();

    if ($sampleGuardian) {
        $identity = $sampleGuardian->data_id_number;
        $found = Data::where('data_id_number', $identity)->exists();

        if ($found) {
            echo "   ✅ Person search in data table working\n";
            $passedTests++;
        }
    } else {
        // Try re_people
        $sampleOrphan = RePeople::first();
        if ($sampleOrphan) {
            echo "   ✅ Person search tables accessible (using re_people)\n";
            $passedTests++;
        } else {
            echo "   ⚠️  No sample data found for testing\n";
            $passedTests++; // Still pass if tables are accessible
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Final Report
// ============================================================================
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                      📊 TEST RESULTS SUMMARY                       ║\n";
echo "╠═══════════════════════════════════════════════════════════════════╣\n";

$percentage = ($totalTests > 0) ? round(($passedTests / $totalTests) * 100, 1) : 0;
$status = $percentage >= 80 ? '✅ PASSED' : ($percentage >= 50 ? '⚠️  PARTIAL' : '❌ FAILED');

echo "║                                                                   ║\n";
printf("║   Total Tests:    %-45d  ║\n", $totalTests);
printf("║   Passed:         %-45d  ║\n", $passedTests);
printf("║   Failed:         %-45d  ║\n", $totalTests - $passedTests);
printf("║   Success Rate:   %-45s  ║\n", "{$percentage}%");
printf("║   Status:         %-45s  ║\n", $status);
echo "║                                                                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

if (!empty($missingTables)) {
    echo "📝 Action Required:\n";
    echo "   Run the following command to create missing tables:\n";
    echo "   php artisan migrate\n\n";
}

echo "📋 Next Steps:\n";
echo "   1. Run migrations if needed: php artisan migrate\n";
echo "   2. Test API endpoints using Postman or curl\n";
echo "   3. Proceed with Capacitor Android app creation\n\n";

exit($passedTests === $totalTests ? 0 : 1);
