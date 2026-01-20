<?php
/**
 * اختبار خوارزمية تقسيم الأسماء العربية
 * Arabic Name Segmentation Test
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Helpers/NameSegmentation.php';

use App\Helpers\NameSegmentation;

// تأكد من ترميز UTF-8
mb_internal_encoding('UTF-8');

echo "═══════════════════════════════════════════════════════════════\n";
echo "         🧪 اختبار خوارزمية تقسيم الأسماء العربية\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$testNames = [
    'عمر احمد نظمي سعدة',
    'ريم عبد الجليل محمود خميسي',
    'راما عبد الكريم ناصر ابو جرمي',
    'سارة عبد الله عبد الله ابو فول',
    'صلاح الدين يوسف محمد ابو حسين',
    'منة الله محمد راضي ابو ريدة',
    'عمر بن الخطاب حسن شعبان خميسي',
    'غيث عدي عوني عبدالفتاح عطا الله',
    'محمد',
    'أحمد علي',
    'محمد أحمد علي',
    'عبد الرحمن محمد أحمد الشيخ',
    'أبو بكر محمد علي حسن الصديق',
];

foreach ($testNames as $name) {
    echo "📛 الاسم: $name\n";
    $result = NameSegmentation::segment($name);

    echo "   ├─ المقاطع الأصلية (" . count($result['original_segments']) . "): " . implode(' | ', $result['original_segments']) . "\n";
    echo "   ├─ المقاطع المدمجة (" . $result['segments_count'] . "): " . implode(' | ', $result['merged_segments']) . "\n";
    echo "   └─ التوزيع:\n";
    echo "      • الاسم الأول: [{$result['first_name']}]\n";
    echo "      • اسم الأب: [{$result['father_name']}]\n";
    echo "      • اسم الجد: [{$result['grand_father_name']}]\n";
    echo "      • اسم العائلة: [{$result['family_name']}]\n";

    // إعادة تجميع الاسم للتحقق
    $reconstructed = trim(implode(' ', array_filter([
        $result['first_name'],
        $result['father_name'],
        $result['grand_father_name'],
        $result['family_name']
    ])));

    $match = ($reconstructed === $result['full_name']) ? '✅' : '❌';
    echo "      • المطابقة: $match (المُعاد: $reconstructed)\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "                        ✅ انتهى الاختبار\n";
echo "═══════════════════════════════════════════════════════════════\n";
