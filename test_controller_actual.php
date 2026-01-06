<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;
use App\Http\Controllers\Users\ShowGeneralRegisrationController;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           اختبار الكونترولر الفعلي\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// إنشاء instance من الكونترولر
$controller = new ShowGeneralRegisrationController();

// استدعاء الدالة extractFieldValues عبر Reflection
$sponsorship = Sponsorship::where('identity_number', '42979087')->first();

$reflectionClass = new ReflectionClass($controller);
$method = $reflectionClass->getMethod('extractFieldValues');
$method->setAccessible(true);

$values = $method->invoke($controller, $sponsorship);

echo "1️⃣  متغيرات التحكم:\n";
echo "   - _guardian_data_source: " . ($values['_guardian_data_source'] ?? 'فارغ') . "\n";
echo "   - _guardian_show_4_fields: " . ($values['_guardian_show_4_fields'] ? 'نعم' : 'لا') . "\n";
echo "   - _guardian_needs_civil_search: " . ($values['_guardian_needs_civil_search'] ? 'نعم' : 'لا') . "\n";

echo "\n2️⃣  حقول المعيل التفصيلية (field_data_*):\n";
echo "   - field_data_id_number: " . ($values['field_data_id_number'] ?? '❌ فارغ') . "\n";
echo "   - field_data_first_name: " . ($values['field_data_first_name'] ?? '❌ فارغ') . "\n";
echo "   - field_data_father_name: " . ($values['field_data_father_name'] ?? '❌ فارغ') . "\n";
echo "   - field_data_grand_father_name: " . ($values['field_data_grand_father_name'] ?? '❌ فارغ') . "\n";
echo "   - field_data_family_name: " . ($values['field_data_family_name'] ?? '❌ فارغ') . "\n";
echo "   - field_data_birth_date: " . ($values['field_data_birth_date'] ?? '❌ فارغ') . "\n";
echo "   - field_data_phone_number: " . ($values['field_data_phone_number'] ?? '❌ فارغ') . "\n";

echo "\n3️⃣  حقول المعيل الأساسية:\n";
echo "   - field_guardian_name: " . ($values['field_guardian_name'] ?? '❌ فارغ') . "\n";
echo "   - field_guardian_identity_number: " . ($values['field_guardian_identity_number'] ?? '❌ فارغ') . "\n";

// التحقق من النتيجة
echo "\n";
if (!empty($values['field_data_first_name'])) {
    echo "✅ نجاح! بيانات المعيل التفصيلية تم استخراجها بشكل صحيح\n";
} else {
    echo "❌ فشل! بيانات المعيل التفصيلية فارغة\n";
}

echo "\n";
