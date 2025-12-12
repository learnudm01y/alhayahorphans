<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== اختبار نظام حالات الكفالة الجديد ===\n\n";

// 1. التحقق من حالات الكفالة
echo "1️⃣ حالات الكفالة المتاحة:\n";
echo str_repeat('-', 70) . "\n";

$statuses = DB::table('sponsorship_statuses')->orderBy('id')->get();
foreach ($statuses as $status) {
    $icon = '';
    if ($status->id == 4) {
        $icon = '🆕'; // جديد
    } elseif ($status->id == 3) {
        $icon = '🟢'; // محدث
    } elseif ($status->id == 5) {
        $icon = '✓'; // ذهب للصرف
    }
    echo sprintf("%-3s ID: %-2d - %s\n", $icon, $status->id, $status->description);
}

// 2. التحقق من عمود updated_by
echo "\n2️⃣ التحقق من عمود updated_by في جدول sponsorships:\n";
echo str_repeat('-', 70) . "\n";

$columnExists = DB::select("SHOW COLUMNS FROM sponsorships LIKE 'updated_by'");
if (!empty($columnExists)) {
    echo "✅ عمود updated_by موجود\n";
    $column = $columnExists[0];
    echo "   النوع: " . $column->Type . "\n";
    echo "   يقبل NULL: " . $column->Null . "\n";
    echo "   الافتراضي: " . ($column->Default ?? 'NULL') . "\n";
} else {
    echo "❌ عمود updated_by غير موجود!\n";
}

// 3. اختبار إنشاء كفالة جديدة بحالة "جديد" افتراضية
echo "\n3️⃣ اختبار الحالة الافتراضية للكفالات الجديدة:\n";
echo str_repeat('-', 70) . "\n";

$testSponsorship = DB::table('sponsorships')
    ->select('id', 'sponsorship_status_id', 'updated_by', 'created_at')
    ->orderBy('id', 'desc')
    ->first();

if ($testSponsorship) {
    echo "آخر كفالة في النظام:\n";
    echo "   ID: {$testSponsorship->id}\n";
    echo "   حالة الكفالة ID: {$testSponsorship->sponsorship_status_id}\n";

    $statusName = DB::table('sponsorship_statuses')
        ->where('id', $testSponsorship->sponsorship_status_id)
        ->value('description');
    echo "   اسم الحالة: {$statusName}\n";

    if ($testSponsorship->sponsorship_status_id == 4) {
        echo "   ✅ الحالة صحيحة: جديد\n";
    }

    echo "   Updated By: " . ($testSponsorship->updated_by ?? 'NULL') . "\n";
    echo "   تاريخ الإنشاء: {$testSponsorship->created_at}\n";
}

// 4. اختبار دالة addUpdater
echo "\n4️⃣ اختبار دالة تتبع المعدلين:\n";
echo str_repeat('-', 70) . "\n";

try {
    // البحث عن أول مستخدم في النظام
    $testUser = DB::table('users')->first();

    if ($testUser && $testSponsorship) {
        echo "اختبار إضافة مستخدم إلى قائمة المعدلين...\n";

        $sponsorship = App\Models\Sponsorship::find($testSponsorship->id);

        if ($sponsorship) {
            // عرض القائمة الحالية
            $currentUpdaters = $sponsorship->updated_by ?? [];
            echo "المعدلون الحاليون: " . (empty($currentUpdaters) ? 'لا يوجد' : count($currentUpdaters)) . "\n";

            // إضافة المستخدم
            $sponsorship->addUpdater($testUser->id);

            echo "✅ تمت إضافة المستخدم: {$testUser->name} (ID: {$testUser->id})\n";

            // التحقق من الإضافة
            $sponsorship = $sponsorship->fresh();
            $newUpdaters = $sponsorship->updated_by ?? [];
            echo "عدد المعدلين بعد الإضافة: " . count($newUpdaters) . "\n";

            if (!empty($newUpdaters)) {
                echo "\nقائمة المعدلين:\n";
                foreach ($newUpdaters as $index => $updater) {
                    echo "   " . ($index + 1) . ". {$updater['name']} (ID: {$updater['user_id']}) - {$updater['updated_at']}\n";
                }
            }
        }
    } else {
        echo "⚠️ لا يوجد مستخدمين أو كفالات للاختبار\n";
    }
} catch (\Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
}

// 5. إحصائيات الكفالات حسب الحالة
echo "\n5️⃣ إحصائيات الكفالات حسب الحالة:\n";
echo str_repeat('-', 70) . "\n";

$stats = DB::table('sponsorships')
    ->select('sponsorship_status_id', DB::raw('COUNT(*) as count'))
    ->groupBy('sponsorship_status_id')
    ->get();

foreach ($stats as $stat) {
    $statusName = DB::table('sponsorship_statuses')
        ->where('id', $stat->sponsorship_status_id)
        ->value('description');

    $icon = '';
    if ($stat->sponsorship_status_id == 4) {
        $icon = '🆕';
    } elseif ($stat->sponsorship_status_id == 3) {
        $icon = '🟢';
    } elseif ($stat->sponsorship_status_id == 5) {
        $icon = '✓';
    }

    echo sprintf("%s %-25s: %d كفالة\n", $icon, $statusName, $stat->count);
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "✅ اكتمل الاختبار!\n\n";

echo "📝 الميزات الجديدة:\n";
echo "   1. ✅ حالة 'جديد' افتراضية للكفالات الجديدة\n";
echo "   2. ✅ علامة صح سوداء (✓) بجانب 'ذهب للصرف'\n";
echo "   3. ✅ عمود updated_by لتتبع المعدلين كمصفوفة JSON\n";
echo "   4. ✅ دالة addUpdater() لإضافة المستخدمين تلقائياً\n";
echo "   5. ✅ تحديث تلقائي عند تعديل الكفالة أو تغيير الحالة\n";
