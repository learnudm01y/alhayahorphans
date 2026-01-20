<?php
/**
 * تشخيص مشكلة تقطيع الأسماء
 */

mb_internal_encoding('UTF-8');

$name = 'عمر احمد نظمي سعدة';

echo "=== تشخيص المشكلة ===\n\n";
echo "الاسم الأصلي: [$name]\n";
echo "طول الاسم: " . mb_strlen($name) . " حرف\n\n";

// الخطوة 1: تقسيم بسيط
echo "1. التقسيم البسيط:\n";
$words = explode(' ', $name);
print_r($words);

// الخطوة 2: preg_split بدون u flag
echo "\n2. preg_split بدون u flag:\n";
$words2 = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
print_r($words2);

// الخطوة 3: preg_split مع u flag
echo "\n3. preg_split مع u flag:\n";
$words3 = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
print_r($words3);

// الخطوة 4: فحص كل حرف
echo "\n4. فحص كل حرف:\n";
for ($i = 0; $i < mb_strlen($name); $i++) {
    $char = mb_substr($name, $i, 1);
    $ord = mb_ord($char);
    echo "[$i] '$char' (U+$ord)\n";
}

// الخطوة 5: فحص preg_replace
echo "\n5. اختبار preg_replace:\n";
$cleaned = preg_replace('/\s+/', ' ', $name);
echo "بعد إزالة الفراغات المتعددة: [$cleaned]\n";

$cleaned2 = preg_replace('/\s+/u', ' ', $name);
echo "مع u flag: [$cleaned2]\n";
