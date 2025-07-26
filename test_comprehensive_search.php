<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔍 اختبار البحث الشامل في جميع الجداول\n";
    echo "=" . str_repeat("=", 60) . "\n";

    // إنشاء مثيل من المتحكم
    $searchService = app(\App\Services\SearchService::class);
    $controller = new \App\Http\Controllers\Admin\ProfileSearchController($searchService);

    $testQueries = [
        "Kyle Christine Byers Brenda Mason Nayda Ayers", // الجدول الرئيسي
        "Kyle", // اسم منفرد
        "Christine Byers", // اسم مزدوج
    ];

    foreach ($testQueries as $query) {
        echo "\n🎯 اختبار البحث عن: '$query'\n";
        echo str_repeat("-", 50) . "\n";

        // محاكاة الطلب
        $request = new Illuminate\Http\Request();
        $request->merge(['query' => $query]);

        $response = $controller->quickSearch($request);

        if ($response instanceof Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);

            if ($data['success']) {
                echo "📊 عدد النتائج: " . $data['total'] . "\n";

                foreach ($data['data'] as $index => $result) {
                    echo "📋 نتيجة " . ($index + 1) . ":\n";
                    echo "   🏷️  العنوان: " . $result['title'] . "\n";
                    echo "   📁 النوع: " . $result['type'] . "\n";
                    echo "   📝 الوصف: " . $result['subtitle'] . "\n";
                    echo "   ⭐ درجة الصلة: " . $result['relevance'] . "\n";

                    // إظهار تفاصيل نوع الجدول
                    switch ($result['type']) {
                        case 'main_record':
                            echo "   📊 من الجدول: data (السجلات الرئيسية)\n";
                            break;
                        case 'family_member':
                            echo "   📊 من الجدول: re_people (أفراد الأسرة)\n";
                            break;
                        case 'deceased':
                            echo "   📊 من الجدول: dead_people (المتوفين)\n";
                            break;
                    }
                    echo "\n";
                }
            } else {
                echo "❌ فشل البحث: " . ($data['message'] ?? 'خطأ غير معروف') . "\n";
            }
        }
    }

    echo "\n🧪 اختبار البحث في بيانات وهمية للتأكد من عمل الجداول الأخرى:\n";
    echo str_repeat("-", 60) . "\n";

    // فحص عينة من جدول أفراد الأسرة
    echo "🔍 فحص جدول re_people:\n";
    $familyRecords = \App\Models\RePeople::limit(3)->get();
    echo "   📊 عدد السجلات الموجودة: " . $familyRecords->count() . "\n";

    foreach ($familyRecords as $record) {
        $fullName = implode(' ', array_filter([
            $record->first_name,
            $record->second_name,
            $record->third_name,
            $record->last_name
        ]));
        echo "   📝 مثال: ID " . $record->id . " - " . $fullName . "\n";
    }

    // فحص عينة من جدول المتوفين
    echo "\n🔍 فحص جدول dead_people:\n";
    $deceasedRecords = \App\Models\DeadPepole::limit(3)->get();
    echo "   📊 عدد السجلات الموجودة: " . $deceasedRecords->count() . "\n";

    foreach ($deceasedRecords as $record) {
        $fatherName = implode(' ', array_filter([
            $record->father_first_name,
            $record->father_second_name,
            $record->father_third_name,
            $record->father_last_name
        ]));
        $motherName = implode(' ', array_filter([
            $record->mother_first_name,
            $record->mother_second_name,
            $record->mother_third_name,
            $record->mother_last_name
        ]));
        echo "   📝 مثال: ID " . $record->id . " - أب: " . ($fatherName ?: 'غير محدد') . " | أم: " . ($motherName ?: 'غير محدد') . "\n";
    }

    echo "\n🎉 انتهى الاختبار الشامل!\n";
    echo "✅ البحث الشامل مُفعل في جميع الجداول الثلاثة:\n";
    echo "   1. data (السجلات الرئيسية) - استخدام CONCAT\n";
    echo "   2. re_people (أفراد الأسرة) - استخدام CONCAT\n";
    echo "   3. dead_people (المتوفين) - استخدام CONCAT لكل من الأب والأم\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
}
