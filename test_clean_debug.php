<?php
/**
 * تشخيص دقيق لمشكلة cleanName
 */

mb_internal_encoding('UTF-8');

$name = 'عمر احمد نظمي سعدة';

echo "=== تشخيص دقيق ===\n\n";
echo "الاسم الأصلي: [$name]\n";
echo "الطول: " . mb_strlen($name) . " حرف\n\n";

// الخطوة 1: preg_replace لإزالة الفراغات المتعددة
echo "1. إزالة الفراغات المتعددة:\n";
$step1 = preg_replace('/\s+/', ' ', $name);
echo "   النتيجة: [$step1]\n";
echo "   الطول: " . mb_strlen($step1) . " حرف\n\n";

// مع u flag
$step1u = preg_replace('/\s+/u', ' ', $name);
echo "   مع u flag: [$step1u]\n\n";

// الخطوة 2: preg_replace لإزالة الأقواس
echo "2. إزالة الأقواس:\n";
$step2 = preg_replace('/\([^)]*\)/', '', $step1);
echo "   النتيجة: [$step2]\n\n";

// مع u flag
$step2u = preg_replace('/\([^)]*\)/u', '', $step1u);
echo "   مع u flag: [$step2u]\n\n";

// الخطوة 3: إزالة علامات الترقيم
echo "3. إزالة علامات الترقيم:\n";
$step3 = preg_replace('/[،,؛;:!?\.]+/', '', $step2);
echo "   النتيجة: [$step3]\n\n";

// مع u flag
$step3u = preg_replace('/[،,؛;:!?\.]+/u', '', $step2u);
echo "   مع u flag: [$step3u]\n\n";

// فحص الأحرف العربية
echo "4. فحص الأحرف العربية:\n";
$arabicRegex = '/[\x{0600}-\x{06FF}]/u';
preg_match_all($arabicRegex, $name, $matches);
echo "   الأحرف العربية في الاسم: " . implode('', $matches[0]) . "\n";
echo "   عددها: " . count($matches[0]) . "\n\n";

// اختبار preg_split
echo "5. preg_split:\n";
$words = preg_split('/\s+/', $step3, -1, PREG_SPLIT_NO_EMPTY);
echo "   بدون u flag: [" . implode('] [', $words) . "]\n";

$wordsu = preg_split('/\s+/u', $step3u, -1, PREG_SPLIT_NO_EMPTY);
echo "   مع u flag: [" . implode('] [', $wordsu) . "]\n\n";

// الحل: استخدام u flag في كل مكان
echo "=== الحل المقترح ===\n";
echo "استخدام /u flag (Unicode) في جميع regex patterns\n";
