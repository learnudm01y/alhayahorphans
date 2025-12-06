<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\BankName;
use Illuminate\Support\Facades\Log;

// دالة normalization
function normalizeArabicText($text)
{
    $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
    $text = str_replace(['ة'], 'ه', $text);
    return trim($text);
}

echo "🔍 اختبار البحث عن البنوك:\n\n";

$testBankNames = [
    'محفظة Palpay بال بي',
    'بال بي',
    'Palpay',
    'محفظة Palpay',
];

foreach ($testBankNames as $bankName) {
    echo "البحث عن: '{$bankName}'\n";
    $normalizedBankName = normalizeArabicText($bankName);
    echo "بعد normalization: '{$normalizedBankName}'\n";

    $bank = BankName::where(function($query) use ($bankName, $normalizedBankName) {
        // البحث الدقيق أولاً
        $query->where('description', $bankName)
              // ثم البحث مع normalization للعربي
              ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(description, "أ", "ا"), "إ", "ا"), "آ", "ا"), "ة", "ه") = ?',
                  [$normalizedBankName])
              // ثم البحث الجزئي
              ->orWhere('description', 'LIKE', "%{$bankName}%")
              ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(description, "أ", "ا"), "إ", "ا"), "آ", "ا"), "ة", "ه") LIKE ?',
                  ["%{$normalizedBankName}%"]);
    })->first();

    if ($bank) {
        echo "✅ تم العثور على البنك:\n";
        echo "   - ID: {$bank->id}\n";
        echo "   - الاسم: {$bank->description}\n";
    } else {
        echo "❌ لم يتم العثور على البنك\n";
    }
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

// عرض جميع البنوك المتاحة
echo "📋 جميع البنوك الموجودة في النظام:\n";
$allBanks = BankName::select('id', 'description')->get();
foreach ($allBanks as $bank) {
    echo "   {$bank->id}. {$bank->description}\n";
}
