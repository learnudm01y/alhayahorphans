<?php

/**
 * اختبار الإصلاحات الثلاثة:
 * 1. bank_name cannot be null
 * 2. guardian_relationship column not found
 * 3. عرض البيانات البنكية في sponsorships
 */

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==============================================\n";
echo "🔍 اختبار الإصلاحات للمشاكل الثلاثة\n";
echo "==============================================\n\n";

// الاختبار 1: التحقق من أن guardian_relationship ليس في قائمة حقول sponsorships
echo "📋 الاختبار 1: التحقق من إزالة guardian_relationship من sponsorshipFields\n";
echo "----------------------------------------------\n";

$sponsorshipColumns = Schema::getColumnListing('sponsorships');
$hasGuardianRelationship = in_array('guardian_relationship', $sponsorshipColumns);

if ($hasGuardianRelationship) {
    echo "⚠️ العمود guardian_relationship موجود في جدول sponsorships\n";
} else {
    echo "✅ العمود guardian_relationship غير موجود في جدول sponsorships (كما هو متوقع)\n";
}

// التحقق من الكود
$controllerContent = file_get_contents('app/Http/Controllers/Users/ShowGeneralRegisrationController.php');
if (strpos($controllerContent, "'guardian_relationship']") !== false &&
    strpos($controllerContent, "'guardian_name', 'guardian_phone', 'guardian_relationship'") !== false) {
    echo "❌ الكود لا يزال يحتوي على guardian_relationship في sponsorshipFields\n";
} else {
    echo "✅ تم إزالة guardian_relationship من sponsorshipFields في الكود\n";
}

echo "\n";

// الاختبار 2: التحقق من وجود التحقق على bank_name قبل الإدراج
echo "📋 الاختبار 2: التحقق من إضافة التحقق على bank_name\n";
echo "----------------------------------------------\n";

if (strpos($controllerContent, "if (!empty(\$bankNameValue))") !== false) {
    echo "✅ تم إضافة التحقق على bank_name قبل إنشاء حساب جديد\n";
} else {
    echo "❌ لم يتم إضافة التحقق على bank_name\n";
}

if (strpos($controllerContent, "BANK_ACCOUNT_SKIPPED_NO_BANK_NAME") !== false) {
    echo "✅ تم إضافة تسجيل log عند تخطي إنشاء حساب بدون bank_name\n";
} else {
    echo "❌ لم يتم إضافة تسجيل log\n";
}

echo "\n";

// الاختبار 3: التحقق من تحسين البحث عن الحسابات البنكية
echo "📋 الاختبار 3: التحقق من تحسين البحث عن الحسابات البنكية\n";
echo "----------------------------------------------\n";

$editControllerContent = file_get_contents('app/Http/Controllers/Admin/RecordsManagementEditController.php');

if (strpos($editControllerContent, "dead_people") !== false &&
    strpos($editControllerContent, "father_id") !== false) {
    echo "✅ تم إضافة البحث في جدول dead_people للمتوفين\n";
} else {
    echo "❌ لم يتم إضافة البحث في جدول dead_people\n";
}

if (strpos($editControllerContent, "re_id_number") !== false &&
    strpos($editControllerContent, "directAccount") !== false) {
    echo "✅ تم إضافة البحث المباشر في guardian_bank_accounts\n";
} else {
    echo "❌ لم يتم إضافة البحث المباشر في guardian_bank_accounts\n";
}

echo "\n";

// الاختبار 4: اختبار عملي لجلب الحسابات البنكية
echo "📋 الاختبار 4: اختبار عملي لجلب الحسابات البنكية\n";
echo "----------------------------------------------\n";

// جلب كفالة عشوائية للاختبار
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('guardian_identity_number')
    ->first();

if ($sponsorship) {
    echo "🔍 اختبار مع الكفالة ID: {$sponsorship->id}\n";
    echo "   - رقم هوية المعيل: {$sponsorship->guardian_identity_number}\n";

    // البحث عن file_id_number من data
    $guardianFileId = DB::table('data')
        ->where('data_id_number', $sponsorship->guardian_identity_number)
        ->value('file_id_number');

    if (!$guardianFileId) {
        $guardianFileId = DB::table('data')
            ->where('file_id_number', $sponsorship->guardian_identity_number)
            ->value('file_id_number');
    }

    // البحث في dead_people
    if (!$guardianFileId) {
        $deadRecord = DB::table('dead_people')
            ->where('father_id', $sponsorship->guardian_identity_number)
            ->orWhere('mother_id', $sponsorship->guardian_identity_number)
            ->first();

        if ($deadRecord) {
            $guardianFileId = $deadRecord->re_file_id;
            echo "   - تم العثور على file_id من dead_people: {$guardianFileId}\n";
        }
    }

    // البحث المباشر في guardian_bank_accounts
    if (!$guardianFileId) {
        $directAccount = DB::table('guardian_bank_accounts')
            ->where('re_id_number', $sponsorship->guardian_identity_number)
            ->first();

        if ($directAccount) {
            $guardianFileId = $directAccount->guardian_registration;
            echo "   - تم العثور على file_id من guardian_bank_accounts: {$guardianFileId}\n";
        }
    }

    if ($guardianFileId) {
        echo "   - رقم الملف: {$guardianFileId}\n";

        // جلب الحسابات البنكية
        $accounts = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $guardianFileId)
            ->get();

        echo "   - عدد الحسابات البنكية: " . count($accounts) . "\n";

        foreach ($accounts as $index => $account) {
            echo "     حساب " . ($index + 1) . ": ";
            echo "بنك={$account->bank_name}, ";
            echo "معتمد=" . ($account->check_account ? 'نعم' : 'لا') . "\n";
        }
    } else {
        echo "   ⚠️ لم يتم العثور على رقم الملف\n";
    }
} else {
    echo "⚠️ لا توجد كفالات للاختبار\n";
}

echo "\n";
echo "==============================================\n";
echo "✅ انتهى الاختبار\n";
echo "==============================================\n";
