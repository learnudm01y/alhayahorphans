<?php
/**
 * فحص تفصيلي لجدول dead_people - حقول الأب والأم
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           فحص تفصيلي لجدول dead_people - حقول الأب والأم                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$columns = DB::select("SHOW COLUMNS FROM dead_people");

echo "┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 👨 حقول الأب (Father Fields):                                              │\n";
echo "├─────────────────────────────────────────────────────────────────────────────┤\n";

$fatherFields = [];
$motherFields = [];
$otherFields = [];

foreach($columns as $col) {
    if (strpos($col->Field, 'father_') === 0) {
        $fatherFields[] = $col;
        $nullable = $col->Null === 'YES' ? 'nullable' : 'required';
        echo sprintf("│  %-30s │ %-15s │ %-10s │\n", $col->Field, $col->Type, $nullable);
    }
}

echo "├─────────────────────────────────────────────────────────────────────────────┤\n";
echo "│ 👩 حقول الأم (Mother Fields):                                              │\n";
echo "├─────────────────────────────────────────────────────────────────────────────┤\n";

foreach($columns as $col) {
    if (strpos($col->Field, 'mother_') === 0) {
        $motherFields[] = $col;
        $nullable = $col->Null === 'YES' ? 'nullable' : 'required';
        echo sprintf("│  %-30s │ %-15s │ %-10s │\n", $col->Field, $col->Type, $nullable);
    }
}

echo "├─────────────────────────────────────────────────────────────────────────────┤\n";
echo "│ 🔗 حقول الربط (Link Fields):                                               │\n";
echo "├─────────────────────────────────────────────────────────────────────────────┤\n";

foreach($columns as $col) {
    if ($col->Field === 'id' || $col->Field === 're_file_id' || strpos($col->Field, '_at') !== false) {
        $otherFields[] = $col;
        $nullable = $col->Null === 'YES' ? 'nullable' : 'required';
        echo sprintf("│  %-30s │ %-15s │ %-10s │\n", $col->Field, $col->Type, $nullable);
    }
}

echo "└─────────────────────────────────────────────────────────────────────────────┘\n";
echo "\n";

// عرض عينة من البيانات
echo "┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 📊 عينة من البيانات الفعلية:                                                │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";

$samples = DB::table('dead_people')->select(
    're_file_id',
    'father_first_name', 'father_second_name', 'father_third_name', 'father_last_name', 'father_id',
    'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name', 'mother_id'
)->limit(3)->get();

foreach($samples as $i => $s) {
    $fatherName = trim("{$s->father_first_name} {$s->father_second_name} {$s->father_third_name} {$s->father_last_name}");
    $motherName = trim("{$s->mother_first_name} {$s->mother_second_name} {$s->mother_third_name} {$s->mother_last_name}");

    echo "\n   📁 سجل رقم " . ($i+1) . " (re_file_id: {$s->re_file_id})\n";
    echo "   ┌─────────────────────────────────────────────────────────────────────┐\n";
    echo "   │ 👨 الأب: " . ($fatherName ?: 'غير محدد') . "\n";
    echo "   │    رقم الهوية: " . ($s->father_id ?: 'غير محدد') . "\n";
    echo "   │ 👩 الأم: " . ($motherName ?: 'غير محدد') . "\n";
    echo "   │    رقم الهوية: " . ($s->mother_id ?: 'غير محدد') . "\n";
    echo "   └─────────────────────────────────────────────────────────────────────┘\n";
}

echo "\n";
echo "════════════════════════════════════════════════════════════════════════════\n";
echo "                           خلاصة حقول dead_people                           \n";
echo "════════════════════════════════════════════════════════════════════════════\n";
echo "\n";
echo "   👨 الأب (person_type = 'deceased_father'):\n";
echo "      ┌─────────────────────────────────────────────────────────────────┐\n";
echo "      │ الحقل                  │ الوصف                │ النوع          │\n";
echo "      ├─────────────────────────────────────────────────────────────────┤\n";
echo "      │ father_first_name      │ الاسم الأول          │ varchar(255)   │\n";
echo "      │ father_second_name     │ اسم الجد             │ varchar(255)   │\n";
echo "      │ father_third_name      │ اسم الجد الثاني      │ varchar(255)   │\n";
echo "      │ father_last_name       │ اسم العائلة          │ varchar(255)   │\n";
echo "      │ father_id              │ رقم الهوية           │ bigint         │\n";
echo "      │ father_death_date      │ تاريخ الوفاة         │ date           │\n";
echo "      │ father_death_reason    │ سبب الوفاة (FK)      │ bigint         │\n";
echo "      └─────────────────────────────────────────────────────────────────┘\n";
echo "\n";
echo "   👩 الأم (person_type = 'deceased_mother'):\n";
echo "      ┌─────────────────────────────────────────────────────────────────┐\n";
echo "      │ الحقل                  │ الوصف                │ النوع          │\n";
echo "      ├─────────────────────────────────────────────────────────────────┤\n";
echo "      │ mother_first_name      │ الاسم الأول          │ varchar(255)   │\n";
echo "      │ mother_second_name     │ اسم الجد             │ varchar(255)   │\n";
echo "      │ mother_third_name      │ اسم الجد الثاني      │ varchar(255)   │\n";
echo "      │ mother_last_name       │ اسم العائلة          │ varchar(255)   │\n";
echo "      │ mother_id              │ رقم الهوية           │ bigint         │\n";
echo "      │ mother_death_reason    │ سبب الوفاة (FK)      │ bigint         │\n";
echo "      │ ⚠️ mother_death_date   │ غير موجود!          │ -              │\n";
echo "      └─────────────────────────────────────────────────────────────────┘\n";
echo "\n";
echo "   🔗 الربط:\n";
echo "      • re_file_id ──────────→ data.file_id_number\n";
echo "      • sponsorships.relation_id_number ──→ dead_people.re_file_id\n";
echo "      • sponsorships.identity_number ──→ father_id أو mother_id\n";
echo "\n";
echo "════════════════════════════════════════════════════════════════════════════\n";
echo "\n";

// مقارنة الحقول بين الأب والأم
echo "┌─────────────────────────────────────────────────────────────────────────────┐\n";
echo "│ 📋 مقارنة الحقول بين الأب والأم:                                            │\n";
echo "├─────────────────────────────────────────────────────────────────────────────┤\n";
echo "│ الحقل             │ الأب (father_)      │ الأم (mother_)      │ متطابق؟   │\n";
echo "├─────────────────────────────────────────────────────────────────────────────┤\n";
echo "│ الاسم الأول       │ ✅ father_first_name │ ✅ mother_first_name │ ✅ نعم    │\n";
echo "│ الاسم الثاني      │ ✅ father_second_name│ ✅ mother_second_name│ ✅ نعم    │\n";
echo "│ الاسم الثالث      │ ✅ father_third_name │ ✅ mother_third_name │ ✅ نعم    │\n";
echo "│ اسم العائلة       │ ✅ father_last_name  │ ✅ mother_last_name  │ ✅ نعم    │\n";
echo "│ رقم الهوية        │ ✅ father_id         │ ✅ mother_id         │ ✅ نعم    │\n";
echo "│ تاريخ الوفاة      │ ✅ father_death_date │ ❌ غير موجود        │ ❌ لا     │\n";
echo "│ سبب الوفاة        │ ✅ father_death_reason│ ✅ mother_death_reason│ ✅ نعم   │\n";
echo "└─────────────────────────────────────────────────────────────────────────────┘\n";
echo "\n";

echo "════════════════════════════════════════════════════════════════════════════\n";
echo "                    🎯 خريطة التحديث المقترحة                               \n";
echo "════════════════════════════════════════════════════════════════════════════\n";
echo "\n";
echo "   عند تحديث المكفول:\n";
echo "\n";
echo "   if (person_type === 'deceased_father') {\n";
echo "       البحث: WHERE re_file_id = relation_id_number AND father_id = identity_number\n";
echo "       التحديث:\n";
echo "         first_name  → father_first_name\n";
echo "         second_name → father_second_name\n";
echo "         third_name  → father_third_name\n";
echo "         last_name   → father_last_name\n";
echo "         identity_number → father_id\n";
echo "         birth_date  → father_death_date (تاريخ الوفاة وليس الميلاد!)\n";
echo "         الجنس: محدد تلقائياً = ذكر\n";
echo "   }\n";
echo "\n";
echo "   if (person_type === 'deceased_mother') {\n";
echo "       البحث: WHERE re_file_id = relation_id_number AND mother_id = identity_number\n";
echo "       التحديث:\n";
echo "         first_name  → mother_first_name\n";
echo "         second_name → mother_second_name\n";
echo "         third_name  → mother_third_name\n";
echo "         last_name   → mother_last_name\n";
echo "         identity_number → mother_id\n";
echo "         birth_date  → ❌ لا يوجد حقل mother_death_date!\n";
echo "         الجنس: محدد تلقائياً = أنثى\n";
echo "   }\n";
echo "\n";
echo "════════════════════════════════════════════════════════════════════════════\n";
echo "\n";
echo "✅ الفحص مكتمل - جاهز للتعديلات بعد موافقتك\n";
echo "\n";
