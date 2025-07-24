<?php

require_once __DIR__ . '/vendor/autoload.php';

// إعداد البيئة
$_ENV['APP_ENV'] = 'local';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Attachment;
use App\Models\Data;

echo "=== فحص مشكلة عدم ظهور المرفقات ===\n\n";

// فحص المرفقات الحديثة
echo "1. فحص المرفقات الحديثة:\n";
echo "========================\n";
$recentAttachments = Attachment::latest()->take(10)->get(['id', 'person_identity_number', 'stored_file_name', 'file_path']);

if ($recentAttachments->count() > 0) {
    foreach ($recentAttachments as $att) {
        echo "ID: {$att->id}, Person: {$att->person_identity_number}, Stored: {$att->stored_file_name}, Path: {$att->file_path}\n";
    }
} else {
    echo "لا توجد مرفقات في قاعدة البيانات\n";
}

echo "\n2. فحص سجل محدد وعلاقاته:\n";
echo "==========================\n";

// أخذ أول سجل موجود
$firstData = Data::first();
if ($firstData) {
    echo "السجل ID: {$firstData->id}, رقم الهوية: {$firstData->data_id_number}\n";

    // فحص المرفقات المرتبطة مباشرة
    $directAttachments = $firstData->attachments;
    echo "المرفقات المباشرة: " . $directAttachments->count() . "\n";

    if ($directAttachments->count() > 0) {
        foreach ($directAttachments as $att) {
            echo "  - Attachment ID: {$att->id}, File: {$att->stored_file_name}\n";
        }
    }

    // فحص أفراد الأسرة ومرفقاتهم
    $familyMembers = $firstData->rePeople;
    echo "أفراد الأسرة: " . $familyMembers->count() . "\n";

    foreach ($familyMembers as $member) {
        echo "  - فرد الأسرة ID: {$member->id}, رقم الهوية: {$member->person_id}\n";
        $memberAttachments = $member->attachments;
        echo "    مرفقاته: " . $memberAttachments->count() . "\n";

        foreach ($memberAttachments as $att) {
            echo "      - Attachment ID: {$att->id}, File: {$att->stored_file_name}\n";
        }
    }

    // فحص المتوفين ومرفقاتهم
    if ($firstData->deadPepole) {
        echo "بيانات المتوفين موجودة\n";
        $deadAttachments = $firstData->deadPepole->attachments;
        echo "مرفقات المتوفين: " . $deadAttachments->count() . "\n";

        foreach ($deadAttachments as $att) {
            echo "  - Attachment ID: {$att->id}, File: {$att->stored_file_name}\n";
        }
    }
} else {
    echo "لا توجد سجلات في قاعدة البيانات\n";
}

echo "\n3. فحص أرقام الهوية في جدول attachments:\n";
echo "==========================================\n";

$uniquePersonIds = Attachment::distinct()->pluck('person_identity_number');
echo "أرقام الهوية الفريدة في جدول attachments: " . $uniquePersonIds->count() . "\n";

foreach ($uniquePersonIds->take(10) as $personId) {
    $count = Attachment::where('person_identity_number', $personId)->count();
    echo "رقم الهوية: {$personId} - عدد المرفقات: {$count}\n";
}

echo "\n4. فحص أرقام الهوية في جدول data:\n";
echo "================================\n";

$dataIds = Data::pluck('data_id_number');
echo "أرقام الهوية في جدول data: " . $dataIds->count() . "\n";

// البحث عن تطابق
$matchingIds = $uniquePersonIds->intersect($dataIds);
echo "أرقام الهوية المطابقة بين الجدولين: " . $matchingIds->count() . "\n";

if ($matchingIds->count() > 0) {
    echo "أمثلة على التطابقات:\n";
    foreach ($matchingIds->take(5) as $id) {
        echo "  - {$id}\n";
    }
}

echo "\n=== انتهاء الفحص ===\n";
