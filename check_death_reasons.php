<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "فحص جدول dead_people وأسباب الوفاة...\n\n";

// فحص هيكل الجدول
$dead = DB::table('dead_people')->first();
if ($dead) {
    echo "=== أعمدة جدول dead_people ===\n";
    foreach (get_object_vars($dead) as $key => $value) {
        echo "  - {$key}: " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) . "\n";
    }
}

// جلب أسباب الوفاة
echo "\n=== أسباب الوفاة المتاحة ===\n";
$reasons = DB::table('death_reasons')->get();
foreach ($reasons as $reason) {
    echo "  - ID: {$reason->id} - {$reason->description}\n";
}

// فحص بيانات لشخص محدد
echo "\n=== بيانات الوفاة للمعيل 002190 ===\n";
$data = DB::table('data')->where('file_id_number', '002190')->first();
if ($data) {
    $deadPeople = DB::table('dead_people')->where('re_file_id', $data->file_id_number)->first();
    if ($deadPeople) {
        echo "✓ تم العثور على بيانات الوفاة:\n";

        // فحص father_death_reason
        if (isset($deadPeople->father_death_reason)) {
            echo "  - father_death_reason: {$deadPeople->father_death_reason}\n";

            // البحث عن الوصف
            $fatherReason = DB::table('death_reasons')
                ->where('id', $deadPeople->father_death_reason)
                ->orWhere('description', $deadPeople->father_death_reason)
                ->first();
            if ($fatherReason) {
                echo "    → الوصف: {$fatherReason->description}\n";
            }
        }

        // فحص mother_death_reason
        if (isset($deadPeople->mother_death_reason)) {
            echo "  - mother_death_reason: {$deadPeople->mother_death_reason}\n";

            // البحث عن الوصف
            $motherReason = DB::table('death_reasons')
                ->where('id', $deadPeople->mother_death_reason)
                ->orWhere('description', $deadPeople->mother_death_reason)
                ->first();
            if ($motherReason) {
                echo "    → الوصف: {$motherReason->description}\n";
            }
        }
    } else {
        echo "✗ لا توجد بيانات وفاة\n";
    }
}
