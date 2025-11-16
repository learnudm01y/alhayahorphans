<?php

/**
 * Script to insert default values (zero = Unknown) 
 * into all reference tables
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=================================================\n";
echo "   Insert Default Values into Reference Tables\n";
echo "=================================================\n\n";

// Define tables and default values
// Each table has a different structure based on its columns
$tables = [
    'marital_status' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'academic_degrees' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'general_category' => [
        'id' => 0,
        'description' => 'Unknown',
        'status' => 1,
    ],
    'employment' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'housing_status' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'type_of_accommodation' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'city' => [
        'id' => 0,
        'city' => 'Unknown',
    ],
    'provinces' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'category_of_relations' => [
        'id' => 0,
        'attribute' => 'Unknown',
    ],
    'health_statuses' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    // New tables
    'bank_names' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'aid_status' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'association_employees' => [
        'id' => 0,
        'employee_name' => 'Unknown',
    ],
    'currency_types' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'death_reasons' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'displacement_statuses' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'document_types' => [
        'id' => 0,
        'description' => 'Unknown',
        'pref' => 0,
        'basic_enabled' => 0,
        'deceased_enabled' => 0,
        'family_enabled' => 0,
        'basic_required' => 0,
        'deceased_required' => 0,
        'family_required' => 0,
    ],
    'sponsorship_statuses' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
    'type_of_guarantee' => [
        'id' => 0,
        'description' => 'Unknown',
    ],
];

$successCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($tables as $tableName => $defaultData) {
    echo "📋 Processing table: {$tableName}\n";
    
    try {
        // Check if table exists
        if (!Schema::hasTable($tableName)) {
            echo "   ⚠️  Table does not exist - Skipped\n\n";
            $skippedCount++;
            continue;
        }

        // Check if record with id 0 exists
        $exists = DB::table($tableName)->where('id', 0)->exists();
        
        if ($exists) {
            echo "   ℹ️  Default value already exists\n";
            echo "   🔄 Updating data...\n";
            
            // Update existing data
            // Prepare data for update (without id)
            $updateData = $defaultData;
            unset($updateData['id']);
            
            DB::table($tableName)
                ->where('id', 0)
                ->update($updateData);
            
            echo "   ✅ Default value updated successfully\n\n";
            $successCount++;
        } else {
            echo "   ➕ Adding new default value...\n";
            
            // Disable foreign key checks temporarily to allow inserting ID = 0
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            // Enable mode to allow inserting 0 in AUTO_INCREMENT
            DB::statement('SET SESSION sql_mode="NO_AUTO_VALUE_ON_ZERO";');
            
            // Get column names
            $columns = Schema::getColumnListing($tableName);
            
            // Prepare data for insertion
            $insertData = [];
            foreach ($defaultData as $key => $value) {
                if (in_array($key, $columns)) {
                    $insertData[$key] = $value;
                }
            }
            
            // Add timestamp fields if they exist
            if (in_array('created_at', $columns)) {
                $insertData['created_at'] = now();
            }
            if (in_array('updated_at', $columns)) {
                $insertData['updated_at'] = now();
            }
            
            // Build direct INSERT query to ensure id = 0 is inserted
            $columnsStr = implode(', ', array_keys($insertData));
            $valuesStr = implode(', ', array_map(function($value) {
                if (is_null($value)) return 'NULL';
                if ($value instanceof \DateTime) return "'" . $value->format('Y-m-d H:i:s') . "'";
                if (is_numeric($value)) return $value;
                return "'" . addslashes($value) . "'";
            }, array_values($insertData)));
            
            // Insert record using direct SQL
            DB::statement("INSERT INTO `{$tableName}` ({$columnsStr}) VALUES ({$valuesStr})");
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            echo "   ✅ Default value added successfully\n\n";
            $successCount++;
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n\n";
        $errorCount++;
        
        // Re-enable foreign key checks in case of error
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Exception $e2) {
            // Ignore error
        }
    }
}

echo "=================================================\n";
echo "                  Final Summary\n";
echo "=================================================\n";
echo "✅ Successful operations: {$successCount}\n";
echo "❌ Failed operations: {$errorCount}\n";
echo "⚠️  Skipped tables: {$skippedCount}\n";
echo "📊 Total tables: " . count($tables) . "\n";
echo "=================================================\n\n";

// Display tables report
echo "📋 Tables Report:\n";
echo "-------------------\n";
foreach ($tables as $tableName => $data) {
    if (Schema::hasTable($tableName)) {
        $count = DB::table($tableName)->where('id', 0)->count();
        $status = $count > 0 ? '✅ Exists' : '❌ Not found';
        echo "• {$tableName}: {$status}\n";
    } else {
        echo "• {$tableName}: ⚠️ Table does not exist\n";
    }
}

echo "\n✨ Script completed successfully!\n";
