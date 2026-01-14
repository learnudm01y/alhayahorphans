<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== التحقق من هيكل جدول data ===\n\n";

try {
    $dataColumns = DB::select("SHOW COLUMNS FROM data");
    
    echo "الأعمدة في جدول data:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-30s %-20s %-10s %-10s\n", "اسم العمود", "النوع", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($dataColumns as $column) {
        printf("%-30s %-20s %-10s %-10s\n", 
            $column->Field, 
            $column->Type, 
            $column->Null, 
            $column->Key
        );
    }
    
    echo "\n\n=== التحقق من هيكل جدول dead_people ===\n\n";
    
    $deadPeopleColumns = DB::select("SHOW COLUMNS FROM dead_people");
    
    echo "الأعمدة في جدول dead_people:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-30s %-20s %-10s %-10s\n", "اسم العمود", "النوع", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($deadPeopleColumns as $column) {
        printf("%-30s %-20s %-10s %-10s\n", 
            $column->Field, 
            $column->Type, 
            $column->Null, 
            $column->Key
        );
    }
    
    echo "\n\n=== البحث عن أعمدة الاسم الكامل ===\n\n";
    
    // البحث في data
    $personalNameExists = false;
    foreach ($dataColumns as $column) {
        if (stripos($column->Field, 'personal_name') !== false || 
            stripos($column->Field, 'full_name') !== false) {
            echo "✓ وجدنا في data: {$column->Field} ({$column->Type})\n";
            $personalNameExists = true;
        }
    }
    if (!$personalNameExists) {
        echo "✗ لا يوجد عمود personal_name أو full_name في جدول data\n";
    }
    
    // البحث في dead_people
    $deadFullNameExists = false;
    foreach ($deadPeopleColumns as $column) {
        if (stripos($column->Field, 'full_name') !== false) {
            echo "✓ وجدنا في dead_people: {$column->Field} ({$column->Type})\n";
            $deadFullNameExists = true;
        }
    }
    if (!$deadFullNameExists) {
        echo "✗ لا يوجد عمود full_name في جدول dead_people\n";
    }
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n\n=== انتهى الفحص ===\n";
