<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        إصلاح جميع Foreign Keys لجدول data\n";
echo "=================================================================\n\n";

// تعطيل Foreign Key Checks مؤقتاً
DB::statement('SET FOREIGN_KEY_CHECKS=0;');

$tablesFixed = 0;
$errors = [];

// 1. marital_status
echo "1️⃣ إصلاح marital_status\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('marital_status')->count();
    if ($count == 0) {
        DB::table('marital_status')->insert([
            ['id' => 1, 'name' => 'أعزب/عزباء'],
            ['id' => 2, 'name' => 'متزوج/متزوجة'],
            ['id' => 3, 'name' => 'مطلق/مطلقة'],
            ['id' => 4, 'name' => 'أرمل/أرملة'],
        ]);
        echo "   ✅ تم إضافة 4 حالات اجتماعية\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "marital_status: " . $e->getMessage();
}

// 2. category_of_relations (data_relationship)
echo "2️⃣ إصلاح category_of_relations\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('category_of_relations')->count();
    if ($count == 0) {
        DB::table('category_of_relations')->insert([
            ['id' => 1, 'attribute' => 'أب'],
            ['id' => 2, 'attribute' => 'أم'],
            ['id' => 3, 'attribute' => 'ابن'],
            ['id' => 4, 'attribute' => 'ابنة'],
            ['id' => 5, 'attribute' => 'أخ'],
            ['id' => 6, 'attribute' => 'أخت'],
            ['id' => 7, 'attribute' => 'زوج/زوجة'],
        ]);
        echo "   ✅ تم إضافة 7 علاقات\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "category_of_relations: " . $e->getMessage();
}

// 3. request_status
echo "3️⃣ إصلاح request_status\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('request_status')->count();
    if ($count == 0) {
        DB::table('request_status')->insert([
            ['id' => 1, 'name' => 'قيد الانتظار'],
            ['id' => 2, 'name' => 'قيد المراجعة'],
            ['id' => 3, 'name' => 'مقبول'],
            ['id' => 4, 'name' => 'مرفوض'],
            ['id' => 5, 'name' => 'محذوف'],
        ]);
        echo "   ✅ تم إضافة 5 حالات طلب\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "request_status: " . $e->getMessage();
}

// 4. academic_degrees
echo "4️⃣ إصلاح academic_degrees\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('academic_degrees')->count();
    if ($count == 0) {
        DB::table('academic_degrees')->insert([
            ['id' => 1, 'name' => 'أمي'],
            ['id' => 2, 'name' => 'ابتدائي'],
            ['id' => 3, 'name' => 'إعدادي'],
            ['id' => 4, 'name' => 'ثانوي'],
            ['id' => 5, 'name' => 'دبلوم'],
            ['id' => 6, 'name' => 'بكالوريوس'],
            ['id' => 7, 'name' => 'ماجستير'],
            ['id' => 8, 'name' => 'دكتوراه'],
        ]);
        echo "   ✅ تم إضافة 8 مؤهلات علمية\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "academic_degrees: " . $e->getMessage();
}

// 5. health_status
echo "5️⃣ إصلاح health_status\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('health_status')->count();
    if ($count == 0) {
        DB::table('health_status')->insert([
            ['id' => 1, 'name' => 'سليم'],
            ['id' => 2, 'name' => 'مريض'],
            ['id' => 3, 'name' => 'إعاقة'],
            ['id' => 4, 'name' => 'مرض مزمن'],
        ]);
        echo "   ✅ تم إضافة 4 حالات صحية\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "health_status: " . $e->getMessage();
}

// 6. employments
echo "6️⃣ إصلاح employments\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('employments')->count();
    if ($count == 0) {
        DB::table('employments')->insert([
            ['id' => 1, 'name' => 'موظف'],
            ['id' => 2, 'name' => 'عاطل عن العمل'],
            ['id' => 3, 'name' => 'طالب'],
            ['id' => 4, 'name' => 'متقاعد'],
            ['id' => 5, 'name' => 'أعمال حرة'],
        ]);
        echo "   ✅ تم إضافة 5 حالات توظيف\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "employments: " . $e->getMessage();
}

// 7. housing_status
echo "7️⃣ إصلاح housing_status\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('housing_status')->count();
    if ($count == 0) {
        DB::table('housing_status')->insert([
            ['id' => 1, 'name' => 'ملك'],
            ['id' => 2, 'name' => 'إيجار'],
            ['id' => 3, 'name' => 'مع العائلة'],
            ['id' => 4, 'name' => 'مأوى مؤقت'],
        ]);
        echo "   ✅ تم إضافة 4 حالات سكن\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "housing_status: " . $e->getMessage();
}

// 8. type_of_accommodation
echo "8️⃣ إصلاح type_of_accommodation\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('type_of_accommodation')->count();
    if ($count == 0) {
        DB::table('type_of_accommodation')->insert([
            ['id' => 1, 'name' => 'بيت'],
            ['id' => 2, 'name' => 'شقة'],
            ['id' => 3, 'name' => 'غرفة'],
            ['id' => 4, 'name' => 'خيمة'],
            ['id' => 5, 'name' => 'كرفان'],
        ]);
        echo "   ✅ تم إضافة 5 أنواع سكن\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "type_of_accommodation: " . $e->getMessage();
}

// 9. general_categories (data_section_id & data_displacement_status)
echo "9️⃣ إصلاح general_categories\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('general_categories')->count();
    if ($count == 0) {
        DB::table('general_categories')->insert([
            ['id' => 1, 'name' => 'أيتام', 'type' => 'section'],
            ['id' => 2, 'name' => 'نازح', 'type' => 'displacement'],
            ['id' => 3, 'name' => 'غير نازح', 'type' => 'displacement'],
        ]);
        echo "   ✅ تم إضافة 3 فئات عامة\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "general_categories: " . $e->getMessage();
}

// 10. cities
echo "🔟 إصلاح cities\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('cities')->count();
    if ($count == 0) {
        DB::table('cities')->insert([
            ['id' => 1, 'name' => 'غزة'],
            ['id' => 2, 'name' => 'رفح'],
            ['id' => 3, 'name' => 'دير البلح'],
            ['id' => 4, 'name' => 'خان يونس'],
            ['id' => 5, 'name' => 'شمال غزة'],
        ]);
        echo "   ✅ تم إضافة 5 مدن\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "cities: " . $e->getMessage();
}

// 11. provinces
echo "1️⃣1️⃣ إصلاح provinces\n";
echo "-----------------------------------------------------------------\n";
try {
    $count = DB::table('provinces')->count();
    if ($count == 0) {
        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'شمال غزة'],
            ['id' => 2, 'name' => 'غزة'],
            ['id' => 3, 'name' => 'دير البلح'],
            ['id' => 4, 'name' => 'خان يونس'],
            ['id' => 5, 'name' => 'رفح'],
        ]);
        echo "   ✅ تم إضافة 5 محافظات\n\n";
    } else {
        echo "   ✅ الجدول يحتوي على {$count} سجل\n\n";
    }
    $tablesFixed++;
} catch (\Exception $e) {
    echo "   ❌ خطأ: {$e->getMessage()}\n\n";
    $errors[] = "provinces: " . $e->getMessage();
}

// إعادة تفعيل Foreign Key Checks
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "\n=================================================================\n";
echo "✅ النتيجة النهائية\n";
echo "=================================================================\n";
echo "   ✅ عدد الجداول التي تم إصلاحها: {$tablesFixed}/11\n";

if (!empty($errors)) {
    echo "\n   ⚠️ الأخطاء التي حدثت:\n";
    foreach ($errors as $error) {
        echo "      • {$error}\n";
    }
}

echo "\n=================================================================\n";
echo "✅ انتهى الإصلاح - يمكنك الآن رفع ملفات Excel بدون مشاكل\n";
echo "=================================================================\n\n";
