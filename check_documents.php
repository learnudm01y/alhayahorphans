<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== فحص جدول الوثائق ===\n\n";

// فحص جدول document_types
$docs = App\Models\DocumentType::all();
echo "عدد الوثائق في الجدول: " . $docs->count() . "\n\n";

if ($docs->count() > 0) {
    echo "أول 10 وثائق:\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($docs->take(10) as $doc) {
        echo sprintf(
            "ID: %-3s | البادئة: %-10s | الوصف: %s\n",
            $doc->id,
            $doc->pref,
            $doc->description
        );
    }

    echo str_repeat("-", 80) . "\n";
} else {
    echo "❌ لا توجد وثائق في الجدول!\n";
}

echo "\n=== فحص إعدادات الجمعية ===\n\n";

$sponsor = App\Models\Sponsor::first();
if ($sponsor) {
    echo "الجمعية: {$sponsor->sponsor_name} (ID: {$sponsor->id})\n";

    $fieldSettings = $sponsor->fieldSettings;
    if ($fieldSettings) {
        echo "إعدادات الحقول موجودة (ID: {$fieldSettings->id})\n";

        $enabledDocs = $fieldSettings->enabled_documents;
        if ($enabledDocs) {
            if (is_string($enabledDocs)) {
                $enabledDocs = json_decode($enabledDocs, true);
            }
            echo "الوثائق المفعلة: " . json_encode($enabledDocs) . "\n";
            echo "عدد الوثائق المفعلة: " . count($enabledDocs) . "\n";
        } else {
            echo "لا توجد وثائق مفعلة\n";
        }
    } else {
        echo "❌ لا توجد إعدادات حقول لهذه الجمعية\n";
    }
} else {
    echo "❌ لا توجد جمعيات في قاعدة البيانات\n";
}

echo "\n=== اختبار API Endpoint ===\n\n";

if ($sponsor) {
    echo "محاولة الحصول على الوثائق للجمعية {$sponsor->id}...\n";

    try {
        $controller = new App\Http\Controllers\Admin\SponsorController();
        $response = $controller->getDocumentSettings($sponsor->id);
        $data = json_decode($response->getContent(), true);

        if ($data['success']) {
            echo "✅ API تعمل بنجاح!\n";
            echo "عدد الوثائق المرجعة: " . count($data['data']) . "\n";
            echo "عدد الوثائق المفعلة: " . collect($data['data'])->where('is_enabled', true)->count() . "\n";
        } else {
            echo "❌ API فشلت: " . ($data['message'] ?? 'unknown error') . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ خطأ في API: " . $e->getMessage() . "\n";
    }
}

echo "\n=== انتهى الفحص ===\n";
