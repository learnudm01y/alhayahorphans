<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sponsorship;

echo "=== اختبار البحث الذكي في الكفالات ===\n\n";

// اختبار 1: Sandra Medina
echo "1. البحث عن: Sandra Medina\n";
$results1 = Sponsorship::where(function ($q) {
    $searchTerm = 'Sandra Medina';
    $searchWords = array_filter(array_map('trim', explode(' ', $searchTerm)));

    $q->where(function ($subQ) use ($searchWords, $searchTerm) {
        $subQ->where('orphan_name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('guardian_name', 'LIKE', "%{$searchTerm}%");

        foreach ($searchWords as $word) {
            $subQ->orWhere('orphan_name', 'LIKE', "%{$word}%")
                ->orWhere('guardian_name', 'LIKE', "%{$word}%");
        }
    });
})->get();

echo "   النتائج من sponsorships: " . $results1->count() . "\n";
foreach ($results1 as $r) {
    echo "   - {$r->orphan_name} (ملف: {$r->internal_file_number})\n";
}

// اختبار 2: نصرالله الفرا
echo "\n2. البحث عن: نصرالله الفرا\n";
$results2 = Sponsorship::where(function ($q) {
    $searchTerm = 'نصرالله الفرا';
    $searchWords = array_filter(array_map('trim', explode(' ', $searchTerm)));

    $q->where(function ($subQ) use ($searchWords, $searchTerm) {
        $subQ->where('orphan_name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('guardian_name', 'LIKE', "%{$searchTerm}%");

        foreach ($searchWords as $word) {
            $subQ->orWhere('orphan_name', 'LIKE', "%{$word}%")
                ->orWhere('guardian_name', 'LIKE', "%{$word}%");
        }
    });
})->get();

echo "   النتائج من sponsorships: " . $results2->count() . "\n";
foreach ($results2 as $r) {
    echo "   - {$r->orphan_name} (ملف: {$r->internal_file_number})\n";
}

// اختبار 3: البحث في جدول data
echo "\n3. البحث في جدول data عن أشخاص بأسماء مشابهة:\n";
$dataResults = \App\Models\Data::where(function ($q) {
    $q->where('data_first_name', 'LIKE', '%Sandra%')
      ->orWhere('data_first_name', 'LIKE', '%Medina%')
      ->orWhere('data_family_name', 'LIKE', '%Sandra%')
      ->orWhere('data_family_name', 'LIKE', '%Medina%')
      ->orWhere('data_first_name', 'LIKE', '%نصرالله%')
      ->orWhere('data_family_name', 'LIKE', '%الفرا%');
})->limit(5)->get();

echo "   النتائج: " . $dataResults->count() . "\n";
foreach ($dataResults as $d) {
    $fullName = trim($d->data_first_name . ' ' . $d->data_father_name . ' ' . $d->data_grand_father_name . ' ' . $d->data_family_name);
    echo "   - {$fullName} (ملف: {$d->file_id_number})\n";
}

// اختبار 4: البحث في جدول re_people
echo "\n4. البحث في جدول re_people:\n";
$rePeopleResults = \App\Models\RePeople::where(function ($q) {
    $q->where('first_name', 'LIKE', '%Sandra%')
      ->orWhere('last_name', 'LIKE', '%Medina%')
      ->orWhere('first_name', 'LIKE', '%نصرالله%')
      ->orWhere('last_name', 'LIKE', '%الفرا%');
})->limit(5)->get();

echo "   النتائج: " . $rePeopleResults->count() . "\n";
foreach ($rePeopleResults as $rp) {
    $fullName = trim($rp->first_name . ' ' . $rp->second_name . ' ' . $rp->third_name . ' ' . $rp->last_name);
    echo "   - {$fullName} (هوية: {$rp->person_id})\n";
}

echo "\n=== انتهى الاختبار ===\n";
