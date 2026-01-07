<?php

/**
 * اختبار جلب البيانات البنكية للكفالة
 * يختبر أن البيانات البنكية يتم جلبها بشكل صحيح باستخدام file_id_number
 */

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\GuardianBankAccount;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==============================================\n";
echo "🏦 اختبار جلب البيانات البنكية للكفالة\n";
echo "==============================================\n\n";

// الحصول على الكفالة المذكورة في اللوج
$sponsorship = DB::table('sponsorships')
    ->where('relation_id_number', '002682')
    ->orWhere('internal_file_number', '002682')
    ->first();

if (!$sponsorship) {
    // البحث عن أي كفالة لها حسابات بنكية
    $accountWithSponsorship = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', '002682')
        ->first();

    if ($accountWithSponsorship) {
        echo "✅ تم العثور على حساب بنكي مرتبط بـ guardian_registration = 002682\n";
        echo "   - ID: {$accountWithSponsorship->id}\n";
        echo "   - bank_name: {$accountWithSponsorship->bank_name}\n";
        echo "   - re_guardian_name: {$accountWithSponsorship->re_guardian_name}\n";
        echo "   - re_id_number: {$accountWithSponsorship->re_id_number}\n";
        echo "   - check_account: {$accountWithSponsorship->check_account}\n\n";

        // البحث عن الكفالة المرتبطة
        $sponsorship = DB::table('sponsorships')
            ->where(function($q) {
                $q->where('relation_id_number', '002682')
                  ->orWhere('internal_file_number', '002682');
            })
            ->first();
    }
}

if ($sponsorship) {
    echo "📋 بيانات الكفالة:\n";
    echo "   - ID: {$sponsorship->id}\n";
    echo "   - orphan_name: {$sponsorship->orphan_name}\n";
    echo "   - guardian_identity_number: " . ($sponsorship->guardian_identity_number ?? 'NULL') . "\n";
    echo "   - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
    echo "   - internal_file_number: " . ($sponsorship->internal_file_number ?? 'NULL') . "\n\n";

    // محاكاة منطق البحث الجديد
    echo "🔍 محاكاة منطق البحث الجديد:\n";

    $guardianFileId = null;

    // 1. البحث باستخدام guardian_identity_number
    if (!empty($sponsorship->guardian_identity_number)) {
        $guardianData = Data::where('data_id_number', $sponsorship->guardian_identity_number)->first();
        if ($guardianData) {
            $guardianFileId = $guardianData->file_id_number;
            echo "   ✅ تم العثور على file_id_number من data عبر guardian_identity_number: {$guardianFileId}\n";
        }
    }

    // 2. استخدام relation_id_number
    if (!$guardianFileId && !empty($sponsorship->relation_id_number)) {
        $guardianFileId = $sponsorship->relation_id_number;
        echo "   ✅ استخدام relation_id_number مباشرة: {$guardianFileId}\n";
    }

    // 3. استخدام internal_file_number
    if (!$guardianFileId && !empty($sponsorship->internal_file_number)) {
        $guardianFileId = $sponsorship->internal_file_number;
        echo "   ✅ استخدام internal_file_number: {$guardianFileId}\n";
    }

    if ($guardianFileId) {
        echo "\n🏦 البحث عن الحسابات البنكية:\n";
        $accounts = GuardianBankAccount::with('bank')
            ->where('guardian_registration', $guardianFileId)
            ->get();

        echo "   - عدد الحسابات: " . $accounts->count() . "\n\n";

        foreach ($accounts as $index => $account) {
            echo "   📌 حساب " . ($index + 1) . ":\n";
            echo "      - ID: {$account->id}\n";
            echo "      - bank_name ID: {$account->bank_name}\n";
            echo "      - bank description: " . ($account->bank ? $account->bank->description : 'N/A') . "\n";
            echo "      - re_guardian_name: {$account->re_guardian_name}\n";
            echo "      - check_account: " . ($account->check_account ? 'معتمد' : 'غير معتمد') . "\n\n";
        }
    } else {
        echo "   ❌ لم يتم العثور على guardian_file_id\n";
    }
} else {
    echo "⚠️ لم يتم العثور على كفالة مرتبطة بـ 002682\n\n";

    // عرض جميع الحسابات البنكية المتاحة
    echo "📋 عرض بعض الحسابات البنكية الموجودة:\n";
    $accounts = DB::table('guardian_bank_accounts')->limit(5)->get();
    foreach ($accounts as $account) {
        echo "   - guardian_registration: {$account->guardian_registration}, bank_name: {$account->bank_name}\n";
    }
}

echo "\n==============================================\n";
echo "✅ انتهى الاختبار\n";
echo "==============================================\n";
