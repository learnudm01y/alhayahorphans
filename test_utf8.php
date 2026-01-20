<?php
/**
 * اختبار UTF-8 للأسماء العربية
 */

// تأكد من ترميز UTF-8
mb_internal_encoding('UTF-8');

echo "=== اختبار UTF-8 للأسماء العربية ===\n\n";

$testNames = [
    'عمر احمد نظمي سعدة',
    'عبد الرحمن محمد أحمد الشيخ',
    'صلاح الدين يوسف محمد',
    'أبو بكر محمد علي',
];

foreach ($testNames as $name) {
    echo "الاسم: $name\n";

    // تقسيم بدون u flag
    $words1 = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    echo "بدون u flag: " . implode(' | ', $words1) . "\n";

    // تقسيم مع u flag
    $words2 = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    echo "مع u flag: " . implode(' | ', $words2) . "\n";

    // تقسيم باستخدام explode
    $words3 = array_filter(explode(' ', $name));
    echo "باستخدام explode: " . implode(' | ', $words3) . "\n";

    echo "\n";
}
