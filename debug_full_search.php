<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔍 اختبار البحث التفصيلي\n";
    echo "=" . str_repeat("=", 50) . "\n";

    $query = "Kyle Christine Byers Brenda Mason Nayda Ayers";
    $url = "http://127.0.0.1:8000/admin/profile-search?query=" . urlencode($query);

    // إعداد cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "📤 حالة HTTP: $httpCode\n";
    echo "📦 الاستجابة: " . $response . "\n\n";

    // فحص مباشر في قاعدة البيانات للاسم الكامل
    $fullSearchQuery = "Kyle Christine Byers Brenda Mason Nayda Ayers";

    echo "🔍 البحث المباشر في قاعدة البيانات:\n";
    echo "البحث عن: '$fullSearchQuery'\n\n";

    // البحث بطرق مختلفة
    echo "1️⃣ البحث عن تطابق كامل:\n";
    $exactMatch = \App\Models\Data::whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) = ?", [$fullSearchQuery])->first();
    echo "النتيجة: " . ($exactMatch ? "موجود ID: " . $exactMatch->id : "غير موجود") . "\n\n";

    echo "2️⃣ البحث عن تطابق جزئي (LIKE):\n";
    $partialMatch = \App\Models\Data::whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%$fullSearchQuery%"])->first();
    echo "النتيجة: " . ($partialMatch ? "موجود ID: " . $partialMatch->id : "غير موجود") . "\n\n";

    echo "3️⃣ البحث بتحويل إلى أحرف صغيرة:\n";
    $lowerMatch = \App\Models\Data::whereRaw("LOWER(CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name)) = LOWER(?)", [$fullSearchQuery])->first();
    echo "النتيجة: " . ($lowerMatch ? "موجود ID: " . $lowerMatch->id : "غير موجود") . "\n\n";

    echo "4️⃣ فحص السجل المطلوب:\n";
    $targetRecord = \App\Models\Data::find(24);
    if ($targetRecord) {
        $formattedName = implode(' ', array_filter([
            $targetRecord->data_first_name,
            $targetRecord->data_father_name,
            $targetRecord->data_grand_father_name,
            $targetRecord->data_family_name
        ]));
        echo "الاسم المُنسق: '$formattedName'\n";
        echo "الاسم المطلوب: '$fullSearchQuery'\n";
        echo "التطابق: " . ($formattedName === $fullSearchQuery ? "✅ متطابق" : "❌ غير متطابق") . "\n";
        echo "طول الاسم المُنسق: " . strlen($formattedName) . "\n";
        echo "طول الاسم المطلوب: " . strlen($fullSearchQuery) . "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
