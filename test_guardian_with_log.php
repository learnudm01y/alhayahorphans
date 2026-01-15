<?php
/**
 * اختبار حفظ المعيل مع تسجيل في Laravel Log
 * سيتم تسجيل كل خطوة في storage/logs/laravel.log
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "===========================================\n";
echo "🧪 اختبار حفظ المعيل مع Laravel Log\n";
echo "===========================================\n\n";

Log::info('========================================');
Log::info('🧪 بدء اختبار حفظ المعيل في جدول data');
Log::info('========================================');

// ======================================
// 1. توليد رقم ملف جديد
// ======================================
echo "📋 1. توليد رقم ملف جديد...\n";

$maxFileIdBefore = DB::table('data')
    ->whereNotNull('file_id_number')
    ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
    ->orderByRaw('CAST(file_id_number as UNSIGNED) DESC')
    ->value('file_id_number');

Log::info('📋 أعلى رقم ملف حالي في جدول data', [
    'max_file_id' => $maxFileIdBefore
]);

$newFileIdNumber = generateFileIdFromDataTable();

Log::info('📋 تم توليد رقم ملف جديد', [
    'new_file_id_number' => $newFileIdNumber
]);

echo "   - أعلى رقم ملف: {$maxFileIdBefore}\n";
echo "   - الرقم الجديد: {$newFileIdNumber}\n";

// ======================================
// 2. إعداد بيانات المعيل للاختبار
// ======================================
echo "\n📋 2. إعداد بيانات المعيل...\n";

// رقم هوية رقمي فقط (bigint)
$testIdentityNumber = rand(100000000, 999999999);

// تحويل أرقام الهاتف لأرقام صحيحة (bigint)
$phoneNumber = 599111222;
$altPhoneNumber = 588333444;

$insertData = [
    'file_id_number' => $newFileIdNumber,
    'data_id_number' => $testIdentityNumber, // bigint
    'data_first_name' => 'اختبار_الاسم',
    'data_father_name' => 'اختبار_الأب',
    'data_grand_father_name' => 'اختبار_الجد',
    'data_family_name' => 'اختبار_العائلة',
    'data_phone_number' => $phoneNumber, // bigint
    'data_alt_phone_number' => $altPhoneNumber, // bigint
    'data_current_address' => 'غزة - اختبار - ' . date('Y-m-d H:i:s'),
    'created_at' => now(),
    'updated_at' => now()
];

Log::info('📋 بيانات المعيل المعدة للإدراج', [
    'insert_data' => $insertData
]);

echo "   - رقم الهوية: {$testIdentityNumber}\n";
echo "   - رقم الملف: {$newFileIdNumber}\n";

// ======================================
// 3. إدراج السجل في جدول data
// ======================================
echo "\n📋 3. محاولة إدراج السجل في جدول data...\n";

Log::info('🔄 بدء إدراج السجل في جدول data');

try {
    // عد السجلات قبل الإدراج
    $countBefore = DB::table('data')->count();
    Log::info('📊 عدد السجلات قبل الإدراج', ['count' => $countBefore]);

    // تنفيذ الإدراج
    $newId = DB::table('data')->insertGetId($insertData);

    Log::info('✅ تم إدراج السجل بنجاح', [
        'new_id' => $newId,
        'file_id_number' => $newFileIdNumber,
        'data_id_number' => $testIdentityNumber
    ]);

    echo "✅ تم الإدراج! ID: {$newId}\n";

    // عد السجلات بعد الإدراج
    $countAfter = DB::table('data')->count();
    Log::info('📊 عدد السجلات بعد الإدراج', [
        'count_before' => $countBefore,
        'count_after' => $countAfter,
        'difference' => $countAfter - $countBefore
    ]);

    echo "   - عدد السجلات قبل: {$countBefore}\n";
    echo "   - عدد السجلات بعد: {$countAfter}\n";

    // ======================================
    // 4. التحقق من وجود السجل
    // ======================================
    echo "\n📋 4. التحقق من وجود السجل في قاعدة البيانات...\n";

    $verifyRecord = DB::table('data')->where('id', $newId)->first();

    if ($verifyRecord) {
        Log::info('✅ تم التحقق من وجود السجل', [
            'id' => $verifyRecord->id,
            'file_id_number' => $verifyRecord->file_id_number,
            'data_id_number' => $verifyRecord->data_id_number,
            'data_first_name' => $verifyRecord->data_first_name,
            'data_father_name' => $verifyRecord->data_father_name,
            'data_grand_father_name' => $verifyRecord->data_grand_father_name,
            'data_family_name' => $verifyRecord->data_family_name,
            'data_phone_number' => $verifyRecord->data_phone_number,
            'data_alt_phone_number' => $verifyRecord->data_alt_phone_number,
            'data_current_address' => $verifyRecord->data_current_address
        ]);

        echo "✅ السجل موجود في قاعدة البيانات!\n";
        echo "   - ID: {$verifyRecord->id}\n";
        echo "   - file_id_number: {$verifyRecord->file_id_number}\n";
        echo "   - data_id_number: {$verifyRecord->data_id_number}\n";
        echo "   - data_first_name: {$verifyRecord->data_first_name}\n";
        echo "   - data_phone_number: {$verifyRecord->data_phone_number}\n";
    } else {
        Log::error('❌ السجل غير موجود بعد الإدراج!', [
            'searched_id' => $newId
        ]);
        echo "❌ السجل غير موجود!\n";
    }

    // ======================================
    // 5. البحث برقم الهوية
    // ======================================
    echo "\n📋 5. البحث عن السجل برقم الهوية...\n";

    $searchResult = DB::table('data')
        ->where('data_id_number', $testIdentityNumber)
        ->first();

    if ($searchResult) {
        Log::info('✅ تم إيجاد السجل بالبحث برقم الهوية', [
            'identity_number' => $testIdentityNumber,
            'found_id' => $searchResult->id,
            'file_id_number' => $searchResult->file_id_number
        ]);
        echo "✅ تم إيجاد السجل برقم الهوية: {$testIdentityNumber}\n";
    } else {
        Log::error('❌ لم يتم إيجاد السجل بالبحث برقم الهوية', [
            'identity_number' => $testIdentityNumber
        ]);
        echo "❌ لم يتم إيجاد السجل!\n";
    }

    // ======================================
    // 6. لن نحذف السجل - سيبقى للمراجعة
    // ======================================
    echo "\n📋 6. السجل محفوظ للمراجعة (لن يتم حذفه):\n";
    echo str_repeat("-", 50) . "\n";

    Log::info('📋 السجل محفوظ للمراجعة - لم يتم حذفه', [
        'id' => $newId,
        'file_id_number' => $insertData['file_id_number'],
        'data_id_number' => $insertData['data_id_number']
    ]);

    echo "✅ السجل محفوظ في قاعدة البيانات:\n";
    echo "   - ID: {$newId}\n";
    echo "   - file_id_number: {$insertData['file_id_number']}\n";
    echo "   - data_id_number: {$insertData['data_id_number']}\n";
    echo "\n⚠️ يمكنك مراجعة السجل في جدول data ثم حذفه يدوياً\n";

} catch (\Exception $e) {
    Log::error('❌ خطأ أثناء إدراج السجل', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

Log::info('========================================');
Log::info('🏁 انتهى اختبار حفظ المعيل');
Log::info('========================================');

echo "\n===========================================\n";
echo "✅ انتهى الاختبار - راجع storage/logs/laravel.log\n";
echo "===========================================\n";
