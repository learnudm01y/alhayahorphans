<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "=== فحص بيانات المعيل (الأم) في الصورة ===\n\n";

// البحث عن الكفالة بناءً على اسم المعيل
$sponsorships = Sponsorship::where('guardian_name', 'LIKE', '%عبد المعطي%')
    ->orWhere('guardian_name', 'LIKE', '%ابراهيم التعيزي%')
    ->get();

if ($sponsorships->isEmpty()) {
    echo "❌ لم يتم العثور على كفالة\n";

    // البحث باسم اليتيم
    echo "\n🔍 البحث باسم اليتيم...\n";
    $sponsorships = Sponsorship::where('orphan_name', 'LIKE', '%سارة%')
        ->orWhere('orphan_name', 'LIKE', '%يوسف%')
        ->orWhere('orphan_name', 'LIKE', '%حمزة%')
        ->get();
}

if ($sponsorships->isEmpty()) {
    echo "❌ لم يتم العثور على أي كفالة مطابقة\n";
    exit;
}

foreach ($sponsorships as $sponsorship) {
    echo "✅ عثر على كفالة:\n";
    echo str_repeat('-', 80) . "\n";
    echo "ID: {$sponsorship->id}\n";
    echo "اسم اليتيم: {$sponsorship->orphan_name}\n";
    echo "اسم المعيل: {$sponsorship->guardian_name}\n";
    echo "رقم هوية المعيل: {$sponsorship->guardian_identity_number}\n";
    echo "علاقة المعيل: {$sponsorship->guardian_relation_id}\n";
    echo "رقم الملف الداخلي: {$sponsorship->internal_file_number}\n";
    echo "\n";

    // فحص جدول data
    if ($sponsorship->guardian_identity_number) {
        echo "🔍 البحث في جدول data:\n";
        $dataRecord = DB::table('data')
            ->where('data_id_number', $sponsorship->guardian_identity_number)
            ->first();

        if ($dataRecord) {
            echo "   ✅ تم العثور على سجل في جدول data\n";
            echo "   - الاسم: {$dataRecord->data_first_name} {$dataRecord->data_father_name} {$dataRecord->data_grand_father_name} {$dataRecord->data_family_name}\n";
            echo "   - الحالة الصحية (data_health_status): " . ($dataRecord->data_health_status ?? 'NULL') . "\n";
            echo "   - الوظيفة (data_employment_status_breadwinner): " . ($dataRecord->data_employment_status_breadwinner ?? 'NULL') . "\n";
            echo "   - عدد الإناث (data_number_female): " . ($dataRecord->data_number_female ?? 'NULL') . "\n";
            echo "   - عدد الذكور (data_number_mail): " . ($dataRecord->data_number_mail ?? 'NULL') . "\n";
            echo "   - عدد الأفراد (data_number_of_individuals): " . ($dataRecord->data_number_of_individuals ?? 'NULL') . "\n";
        } else {
            echo "   ❌ لا يوجد سجل في جدول data\n";
        }
        echo "\n";
    }

    // فحص portal_general_registration_field_values
    echo "🔍 البحث في portal_general_registration_field_values:\n";
    $portalFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorship->id)
        ->whereIn('field_key', [
            'field_guardian_health',
            'field_guardian_job',
            'field_guardian_job_text',
            'field_dependents_male',
            'field_dependents_female',
            'field_mother_status',
            'field_living_mother_first_name'
        ])
        ->get();

    if ($portalFields->isNotEmpty()) {
        echo "   ✅ عثر على " . $portalFields->count() . " حقل:\n";
        foreach ($portalFields as $field) {
            echo "   - {$field->field_key}: {$field->field_value}\n";
        }
    } else {
        echo "   ❌ لا توجد حقول في portal\n";
    }
    echo "\n";

    // فحص dead_people
    echo "🔍 البحث في dead_people:\n";
    $deadPeople = DB::table('dead_people')
        ->where('re_file_id', $sponsorship->internal_file_number)
        ->first();

    if ($deadPeople) {
        echo "   ✅ عثر على سجل في dead_people\n";
        echo "   - father_id: " . ($deadPeople->father_id ?? 'NULL') . "\n";
        echo "   - mother_id: " . ($deadPeople->mother_id ?? 'NULL') . "\n";
        echo "   - relationship_id: " . ($deadPeople->relationship_id ?? 'NULL') . "\n";
    } else {
        echo "   ❌ لا يوجد سجل في dead_people\n";
    }
    echo "\n";

    // فحص عدد الأطفال في العائلة
    echo "📊 عدد الأطفال في هذه العائلة (من sponsorships):\n";
    $familyCount = Sponsorship::where('guardian_identity_number', $sponsorship->guardian_identity_number)
        ->orWhere('internal_file_number', $sponsorship->internal_file_number)
        ->count();
    echo "   - عدد الأطفال: {$familyCount}\n";

    echo "\n" . str_repeat('=', 80) . "\n\n";
}
