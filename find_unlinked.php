<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== البحث عن كفالات غير مربوطة ===\n\n";

$unlinked = DB::table('sponsorships')
    ->where(function($q) {
        $q->whereNull('relation_id_number')
          ->orWhere('relation_id_number', '');
    })
    ->where('person_type', 'family_member')
    ->limit(5)
    ->get();

if ($unlinked->count() > 0) {
    echo "كفالات غير مربوطة (family_member):\n";
    foreach ($unlinked as $s) {
        echo "ID: {$s->id} | internal: {$s->internal_file_number} | relation: [" . ($s->relation_id_number ?: 'EMPTY') . "]\n";
    }
} else {
    echo "لا توجد كفالات غير مربوطة من نوع family_member\n\n";

    // البحث عن أي كفالة غير مربوطة
    $anyUnlinked = DB::table('sponsorships')
        ->where(function($q) {
            $q->whereNull('relation_id_number')
              ->orWhere('relation_id_number', '');
        })
        ->limit(5)
        ->get();

    if ($anyUnlinked->count() > 0) {
        echo "كفالات غير مربوطة (أي نوع):\n";
        foreach ($anyUnlinked as $s) {
            echo "ID: {$s->id} | type: {$s->person_type} | internal: {$s->internal_file_number}\n";
        }
    } else {
        echo "جميع الكفالات مربوطة!\n";
    }
}
