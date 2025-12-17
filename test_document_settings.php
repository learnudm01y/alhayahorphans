<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Document Settings ===\n\n";

$sponsorId = 5; // جمعية الهلال الأحمر

// 1. جلب الجمعية
$sponsor = App\Models\Sponsor::find($sponsorId);
echo "Sponsor: {$sponsor->sponsor_name}\n";

// 2. جلب إعدادات الحقول
$fieldSettings = $sponsor->fieldSettings;
if ($fieldSettings) {
    echo "Field Settings ID: {$fieldSettings->id}\n";
    echo "Enabled Documents (raw): " . json_encode($fieldSettings->enabled_documents) . "\n";
    echo "Enabled Documents (type): " . gettype($fieldSettings->enabled_documents) . "\n";
} else {
    echo "No field settings found!\n";
}

// 3. اختبار الحفظ
echo "\n=== Testing Save ===\n";
$testDocuments = [1, 3, 5, 7];

if (!$fieldSettings) {
    $fieldSettings = new App\Models\SponsorFieldSetting();
    $fieldSettings->sponsor_id = $sponsorId;
}

$fieldSettings->enabled_documents = $testDocuments;
$saved = $fieldSettings->save();

echo "Save result: " . ($saved ? 'SUCCESS' : 'FAILED') . "\n";

// 4. إعادة جلب البيانات للتحقق
$fieldSettings->refresh();
echo "After save - Enabled Documents: " . json_encode($fieldSettings->enabled_documents) . "\n";

// 5. جلب جميع أنواع الوثائق
echo "\n=== All Document Types ===\n";
$documents = App\Models\DocumentType::all();
foreach ($documents as $doc) {
    $isEnabled = in_array($doc->id, $fieldSettings->enabled_documents ?? []);
    echo "ID: {$doc->id} - {$doc->description} - " . ($isEnabled ? 'ENABLED' : 'disabled') . "\n";
}
