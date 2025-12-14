<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار نهائي للمحافظة والمدينة ===\n\n";

// 1. Check sponsorship 71
$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
echo "1. الكفالة 71:\n";
echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: {$sponsorship->relation_id_number}\n\n";

// 2. Check data record
$data = DB::table('data')->where('file_id_number', $sponsorship->relation_id_number)->first();
echo "2. سجل المعيل (data):\n";
if ($data) {
    echo "   ✓ موجود (ID: {$data->id})\n";
    echo "   - file_id_number: {$data->file_id_number}\n";
    echo "   - data_province: {$data->data_province}\n";
    echo "   - data_city: {$data->data_city}\n\n";

    // 3. Check province
    $province = DB::table('provinces')->where('id', $data->data_province)->first();
    echo "3. المحافظة:\n";
    if ($province) {
        echo "   ✓ موجودة\n";
        echo "   - ID: {$province->id}\n";
        echo "   - description: {$province->description}\n\n";
    } else {
        echo "   ✗ غير موجودة\n\n";
    }

    // 4. Check city
    $city = DB::table('city')->where('id', $data->data_city)->first();
    echo "4. المدينة:\n";
    if ($city) {
        echo "   ✓ موجودة\n";
        echo "   - ID: {$city->id}\n";
        echo "   - city: {$city->city}\n\n";
    } else {
        echo "   ✗ غير موجودة\n\n";
    }
} else {
    echo "   ✗ غير موجود\n\n";
}

// 5. Test using Eloquent (like Controller does)
echo "5. اختبار باستخدام Eloquent:\n";
$sponsorshipModel = App\Models\Sponsorship::with('relationData.province', 'relationData.city')
    ->where('id', 71)
    ->first();

if ($sponsorshipModel && $sponsorshipModel->relationData) {
    echo "   ✓ relationData موجود\n";
    echo "   - Province: " . ($sponsorshipModel->relationData->province->description ?? 'NULL') . "\n";
    echo "   - City: " . ($sponsorshipModel->relationData->city->city ?? 'NULL') . "\n";
} else {
    echo "   ✗ relationData غير موجود\n";
}

echo "\n=== جاهز للاختبار في المتصفح! ===\n";
echo "البريد: 666665457\n";
echo "كلمة المرور: 002622\n";
