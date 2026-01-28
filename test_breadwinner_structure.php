<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "📋 فهم بنية تخزين بيانات المعيل (Breadwinner)\n";
echo "=============================================\n\n";

// البحث عن كفالة من نوع breadwinner
echo "1️⃣ البحث عن كفالة من نوع breadwinner...\n";
$breadwinnerSponsorship = DB::table('sponsorships')
    ->where('person_type', 'breadwinner')
    ->first();

if (!$breadwinnerSponsorship) {
    echo "   ❌ لم يتم العثور على كفالة من نوع breadwinner\n";
    echo "\n📝 ملاحظة: سأبحث عن أي كفالة لفهم البنية...\n\n";

    $breadwinnerSponsorship = DB::table('sponsorships')
        ->whereNotNull('identity_number')
        ->first();
}

if ($breadwinnerSponsorship) {
    echo "   ✅ تم العثور على كفالة:\n";
    echo "   - Sponsorship ID: {$breadwinnerSponsorship->id}\n";
    echo "   - Person Type: " . ($breadwinnerSponsorship->person_type ?? 'NULL') . "\n";
    echo "   - Identity Number: {$breadwinnerSponsorship->identity_number}\n";
    echo "   - Orphan Name: {$breadwinnerSponsorship->orphan_name}\n";
    echo "   - Guardian Name: " . ($breadwinnerSponsorship->guardian_name ?? 'NULL') . "\n\n";

    // البحث في جدول data
    echo "2️⃣ البحث في جدول data...\n";
    $dataRecord = DB::table('data')
        ->where('data_id_number', $breadwinnerSponsorship->identity_number)
        ->first();

    if ($dataRecord) {
        echo "   ✅ تم العثور على سجل في جدول data:\n";
        echo "   - Data ID: {$dataRecord->id}\n";
        echo "   - File ID: {$dataRecord->file_id_number}\n";
        echo "   - Name: {$dataRecord->data_first_name} {$dataRecord->data_father_name} {$dataRecord->data_grand_father_name} {$dataRecord->data_family_name}\n";
        echo "   - Relationship: " . ($dataRecord->data_relationship ?? 'NULL') . "\n";

        if ($dataRecord->data_relationship) {
            $relation = DB::table('category_of_relations')
                ->where('id', $dataRecord->data_relationship)
                ->first();
            echo "   - Relationship Description: " . ($relation->attribute ?? 'N/A') . "\n";
        }

        echo "   - Health Status ID: " . ($dataRecord->data_health_status ?? 'NULL') . "\n";

        if ($dataRecord->data_health_status) {
            $health = DB::table('health_statuses')
                ->where('id', $dataRecord->data_health_status)
                ->first();
            echo "   - Health Status Description: " . ($health->description ?? 'N/A') . "\n";
        }

        echo "   - Employment Status ID: " . ($dataRecord->data_emloyment_status_breadwinner ?? 'NULL') . "\n";
        echo "   - Dependents (Female): " . ($dataRecord->data_number_female ?? 0) . "\n";
        echo "   - Dependents (Male): " . ($dataRecord->data_number_mail ?? 0) . "\n";
    } else {
        echo "   ❌ لم يتم العثور على سجل في جدول data\n";
    }

    // البحث في جدول dead_people
    echo "\n3️⃣ البحث في جدول dead_people...\n";
    $deadRecord = DB::table('dead_people')
        ->where('father_id', $breadwinnerSponsorship->identity_number)
        ->orWhere('mother_id', $breadwinnerSponsorship->identity_number)
        ->first();

    if ($deadRecord) {
        echo "   ✅ تم العثور على سجل في جدول dead_people:\n";
        echo "   - Dead People ID: {$deadRecord->id}\n";
        echo "   - Father Name: " . ($deadRecord->father_first_name ?? '') . " " . ($deadRecord->father_second_name ?? '') . " " . ($deadRecord->father_third_name ?? '') . " " . ($deadRecord->father_last_name ?? '') . "\n";
        echo "   - Father ID: " . ($deadRecord->father_id ?? 'NULL') . "\n";
        echo "   - Father Death Date: " . ($deadRecord->father_death_date ?? 'NULL') . "\n";
        echo "   - Mother Name: " . ($deadRecord->mother_first_name ?? '') . " " . ($deadRecord->mother_second_name ?? '') . " " . ($deadRecord->mother_third_name ?? '') . " " . ($deadRecord->mother_last_name ?? '') . "\n";
        echo "   - Mother ID: " . ($deadRecord->mother_id ?? 'NULL') . "\n";
        echo "   - Mother Death Date: " . ($deadRecord->mother_death_date ?? 'NULL') . "\n";
    } else {
        echo "   ⚠️  لم يتم العثور على سجل في جدول dead_people\n";
    }

    // البحث في جدول re_people
    echo "\n4️⃣ البحث في جدول re_people...\n";
    $reRecord = DB::table('re_people')
        ->where('person_id', $breadwinnerSponsorship->identity_number)
        ->first();

    if ($reRecord) {
        echo "   ✅ تم العثور على سجل في جدول re_people:\n";
        echo "   - Re People ID: {$reRecord->id}\n";
        echo "   - Name: {$reRecord->first_name} {$reRecord->second_name} {$reRecord->third_name} {$reRecord->last_name}\n";
        echo "   - Health Status: " . ($reRecord->person_health_status ?? 'NULL') . "\n";
    } else {
        echo "   ⚠️  لم يتم العثور على سجل في جدول re_people\n";
    }

} else {
    echo "   ❌ لم يتم العثور على أي كفالة\n";
}

echo "\n=============================================\n";
echo "📝 ملخص البنية:\n";
echo "- person_type = 'breadwinner' → البيانات في جدول data\n";
echo "- person_type = 'family_member' → البيانات في جدول re_people\n";
echo "- person_type = 'deceased_father' أو 'deceased_mother' → البيانات في dead_people\n";
echo "\n✅ الاختبار مكتمل\n";
