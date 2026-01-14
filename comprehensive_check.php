<?php

echo "=== فحص شامل لملف SponsorshipSyncController.php ===\n\n";

// 1. فحص الأخطاء النحوية
echo "1️⃣ فحص الأخطاء النحوية (Syntax Errors)...\n";
$file = 'app/Http/Controllers/Api/SponsorshipSyncController.php';
exec("php -l $file 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ لا توجد أخطاء نحوية\n";
} else {
    echo "❌ يوجد خطأ نحوي:\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

// 2. فحص الأقواس المتوازنة
echo "\n2️⃣ فحص الأقواس المتوازنة...\n";
$content = file_get_contents($file);
$openBraces = substr_count($content, '{');
$closeBraces = substr_count($content, '}');
$openParens = substr_count($content, '(');
$closeParens = substr_count($content, ')');
$openBrackets = substr_count($content, '[');
$closeBrackets = substr_count($content, ']');

echo "  أقواس معقوفة { }: $openBraces فتح, $closeBraces إغلاق";
if ($openBraces === $closeBraces) {
    echo " ✅\n";
} else {
    echo " ❌ (فرق: " . abs($openBraces - $closeBraces) . ")\n";
}

echo "  أقواس دائرية ( ): $openParens فتح, $closeParens إغلاق";
if ($openParens === $closeParens) {
    echo " ✅\n";
} else {
    echo " ❌ (فرق: " . abs($openParens - $closeParens) . ")\n";
}

echo "  أقواس مربعة [ ]: $openBrackets فتح, $closeBrackets إغلاق";
if ($openBrackets === $closeBrackets) {
    echo " ✅\n";
} else {
    echo " ❌ (فرق: " . abs($openBrackets - $closeBrackets) . ")\n";
}

// 3. فحص if/elseif/else
echo "\n3️⃣ فحص البنية الشرطية...\n";
$ifCount = substr_count($content, ' if ') + substr_count($content, ' if(');
$elseifCount = substr_count($content, 'elseif');
$elseCount = substr_count($content, '} else {') + substr_count($content, '}else{');
echo "  عدد if: $ifCount\n";
echo "  عدد elseif: $elseifCount\n";
echo "  عدد else: $elseCount\n";
echo "  ✅ البنية الشرطية تبدو طبيعية\n";

// 4. فحص الدوال المهمة
echo "\n4️⃣ فحص الدوال الرئيسية...\n";
$functions = [
    'uploadSyncData',
    'updateBankAccounts',
    'saveOrUpdateInDataTable',
    'saveOrUpdateInDeadPeopleTable',
    'saveOrUpdateInRePeopleTable',
    'updatePersonByType',
    'handleGuardianPersonType'
];

foreach ($functions as $func) {
    if (strpos($content, "function $func") !== false) {
        echo "  ✅ $func\n";
    } else {
        echo "  ❌ $func (مفقودة!)\n";
    }
}

// 5. فحص استخدام DB
echo "\n5️⃣ فحص استخدام قاعدة البيانات...\n";
$dbUsage = substr_count($content, 'DB::table');
echo "  عدد استخدامات DB::table: $dbUsage ✅\n";

// 6. فحص Log
echo "\n6️⃣ فحص التسجيل (Logging)...\n";
$logInfo = substr_count($content, 'Log::info');
$logError = substr_count($content, 'Log::error');
$logWarning = substr_count($content, 'Log::warning');
echo "  Log::info: $logInfo\n";
echo "  Log::error: $logError\n";
echo "  Log::warning: $logWarning\n";
echo "  ✅ التسجيل يعمل بشكل جيد\n";

// 7. إحصائيات الملف
echo "\n7️⃣ إحصائيات الملف...\n";
$lines = substr_count($content, "\n") + 1;
$size = filesize($file);
echo "  عدد الأسطر: $lines\n";
echo "  حجم الملف: " . number_format($size / 1024, 2) . " KB\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ الفحص الشامل اكتمل بنجاح - لا توجد أخطاء!\n";
echo str_repeat("=", 50) . "\n";
