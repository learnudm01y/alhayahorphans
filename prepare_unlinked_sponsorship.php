<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// البحث عن أي كفالة غير مربوطة
$count = DB::table('sponsorships')
    ->where(function($q) {
        $q->whereNull('relation_id_number')
          ->orWhere('relation_id_number', '');
    })
    ->count();

echo "عدد الكفالات غير المربوطة: {$count}\n";

// إذا لم توجد، نفك ربط واحدة للاختبار
if ($count == 0) {
    echo "\nسيتم فك ربط كفالة للاختبار...\n";

    $sponsorship = DB::table('sponsorships')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->orderBy('id', 'desc')
        ->skip(5)
        ->first();

    if ($sponsorship) {
        echo "تم العثور على كفالة: ID {$sponsorship->id}\n";
        echo "relation_id_number الحالي: {$sponsorship->relation_id_number}\n";

        // حفظ القيمة القديمة
        $oldValue = $sponsorship->relation_id_number;

        // فك الربط
        DB::table('sponsorships')
            ->where('id', $sponsorship->id)
            ->update([
                'relation_id_number' => null,
                'person_type' => 'family_member'
            ]);

        echo "✅ تم فك الربط بنجاح!\n";
        echo "الآن يمكن اختبار السيناريو 1\n";
        echo "\n⚠️ لإعادة الربط: UPDATE sponsorships SET relation_id_number = '{$oldValue}' WHERE id = {$sponsorship->id}\n";
    } else {
        echo "❌ لم يتم العثور على كفالة مناسبة\n";
    }
}
