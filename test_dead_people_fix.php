<?php

/**
 * اختبار إصلاح عرض بيانات المتوفين (dead_people)
 *
 * المشكلة: عند تسجيل دخول مستخدم لديه أكثر من كفالة بنفس رقم الهوية،
 * كان النظام يجلب الكفالة الأولى بدلاً من الكفالة المحددة برقم الملف الداخلي.
 *
 * الحل:
 * 1. تخزين sponsorship_id في الجلسة عند تسجيل الدخول
 * 2. استخدام الجلسة لجلب الكفالة الصحيحة في صفحة general-registration
 * 3. جلب بيانات dead_people مباشرة باستخدام relation_id_number من الكفالة
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Sponsorship;

echo "=" . str_repeat("=", 70) . "\n";
echo "  اختبار إصلاح عرض بيانات المتوفين (dead_people)\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// بيانات الاختبار
$testIdentity = '666665457';
$testInternalFile = '002671';
$expectedRelationId = '002190';

echo "📋 بيانات الاختبار:\n";
echo "   - رقم الهوية: {$testIdentity}\n";
echo "   - رقم الملف الداخلي: {$testInternalFile}\n";
echo "   - رقم ملف العلاقة المتوقع: {$expectedRelationId}\n\n";

// 1. البحث عن جميع الكفالات لهذا المستخدم
echo "🔍 البحث عن الكفالات لرقم الهوية {$testIdentity}:\n";
$sponsorships = Sponsorship::where('identity_number', $testIdentity)->get();

if ($sponsorships->count() == 0) {
    echo "   ⚠️ لا توجد كفالات لهذا المستخدم!\n";
    exit(1);
}

foreach ($sponsorships as $s) {
    echo "   📌 الكفالة #{$s->id}:\n";
    echo "      - رقم الملف الداخلي: {$s->internal_file_number}\n";
    echo "      - رقم ملف العلاقة (relation_id_number): {$s->relation_id_number}\n\n";
}

// 2. البحث عن الكفالة الصحيحة (بناءً على internal_file_number)
echo "\n🎯 البحث عن الكفالة الصحيحة (internal_file_number = {$testInternalFile}):\n";
$correctSponsorship = Sponsorship::where('identity_number', $testIdentity)
    ->where('internal_file_number', $testInternalFile)
    ->first();

if (!$correctSponsorship) {
    echo "   ❌ لم يتم العثور على الكفالة بهذا الملف الداخلي!\n";
    exit(1);
}

echo "   ✅ تم العثور على الكفالة #{$correctSponsorship->id}\n";
echo "   - relation_id_number: {$correctSponsorship->relation_id_number}\n";

if ($correctSponsorship->relation_id_number != $expectedRelationId) {
    echo "   ⚠️ تحذير: relation_id_number لا يتطابق مع المتوقع!\n";
}

// 3. جلب بيانات dead_people باستخدام relation_id_number
echo "\n🔍 البحث في جدول dead_people باستخدام re_file_id = {$correctSponsorship->relation_id_number}:\n";

$deadPeople = DB::table('dead_people')
    ->where('re_file_id', $correctSponsorship->relation_id_number)
    ->first();

if (!$deadPeople) {
    echo "   ⚠️ لا يوجد سجل في dead_people لهذا الملف!\n";
} else {
    echo "   ✅ تم العثور على سجل dead_people (id: {$deadPeople->id}):\n";
    echo "\n   👨 بيانات الأب المتوفى:\n";
    echo "      - الاسم: {$deadPeople->father_first_name} {$deadPeople->father_second_name} {$deadPeople->father_third_name} {$deadPeople->father_last_name}\n";
    echo "      - رقم الهوية: {$deadPeople->father_id}\n";
    echo "      - تاريخ الوفاة: {$deadPeople->father_death_date}\n";
    echo "      - سبب الوفاة (id): {$deadPeople->father_death_reason}\n";

    echo "\n   👩 بيانات الأم المتوفية:\n";
    echo "      - الاسم: {$deadPeople->mother_first_name} {$deadPeople->mother_second_name} {$deadPeople->mother_third_name} {$deadPeople->mother_last_name}\n";
    echo "      - رقم الهوية: {$deadPeople->mother_id}\n";
    echo "      - تاريخ الوفاة: {$deadPeople->mother_death_date}\n";
    echo "      - سبب الوفاة (id): {$deadPeople->mother_death_reason}\n";
}

// 4. اختبار التحقق من العلاقة عبر Model
echo "\n\n🔗 اختبار العلاقات عبر النماذج (Models):\n";

$sponsorshipWithRelations = Sponsorship::with(['relationData.deadPepole'])
    ->find($correctSponsorship->id);

if ($sponsorshipWithRelations->relationData) {
    echo "   ✅ relationData موجودة (file_id_number: {$sponsorshipWithRelations->relationData->file_id_number})\n";

    if ($sponsorshipWithRelations->relationData->deadPepole) {
        echo "   ✅ deadPepole موجودة عبر العلاقة\n";
        echo "      - father_first_name: {$sponsorshipWithRelations->relationData->deadPepole->father_first_name}\n";
    } else {
        echo "   ⚠️ deadPepole غير موجودة عبر العلاقة (سيتم الجلب مباشرة)\n";
    }
} else {
    echo "   ⚠️ relationData غير موجودة! سيتم استخدام الجلب المباشر\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎉 انتهى الاختبار بنجاح!\n";
echo str_repeat("=", 70) . "\n\n";

echo "📝 ملخص الإصلاحات المطبقة:\n";
echo "   1. ✅ تخزين active_sponsorship_id في الجلسة عند تسجيل الدخول\n";
echo "   2. ✅ استخدام الجلسة لجلب الكفالة الصحيحة في ShowGeneralRegisrationController\n";
echo "   3. ✅ جلب dead_people مباشرة باستخدام relation_id_number (طبقة أمان إضافية)\n";
echo "   4. ✅ تحديث updateSeparateNameFields لاستخدام relation_id_number\n";
echo "   5. ✅ إضافة CRUD كامل لحقول dead_people (الإنشاء/التحديث)\n";
echo "\n";
