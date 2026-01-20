<?php
/**
 * 📛 خوارزمية تقسيم الأسماء العربية (Arabic Name Segmentation)
 *
 * تقوم بتوزيع الاسم الكامل على 4 حقول:
 * - first_name: الاسم الأول (قد يكون مركباً مثل: عبد الرحمن، صلاح الدين)
 * - second_name/father_name: اسم الأب
 * - third_name/grand_father_name: اسم الجد
 * - last_name/family_name: اسم العائلة (قد يكون مركباً مثل: أبو جرمي، آل سعود)
 *
 * @author Hayah Orphans System
 * @version 2.1
 */

namespace App\Helpers;

class NameSegmentation
{
    /**
     * 📋 قائمة البادئات المركبة (Compound Prefixes)
     */
    private static $compoundPrefixes = [
        'عبد', 'أبو', 'ابو', 'أبا', 'ابا', 'أم', 'ام', 'بن', 'ابن', 'بنت', 'آل', 'ال',
        'عز', 'صلاح', 'نور', 'سيف', 'شمس', 'بدر', 'نجم', 'علاء', 'ضياء', 'محي', 'تاج',
        'حسام', 'صدر', 'فخر', 'جمال', 'كمال', 'زين', 'سعد', 'علم', 'خير', 'ركن', 'عماد',
        'غياث', 'عفيف', 'بهاء', 'منة', 'آية', 'اية', 'عطا', 'فتح', 'نصر', 'حب', 'روح',
        'ذكر', 'حمد', 'هبة', 'هبه', 'عوض', 'توفيق'
    ];

    /**
     * 📋 قائمة الكلمات المرتبطة (اللاحقات)
     */
    private static $compoundSuffixes = [
        'الله', 'الرحمن', 'الرحيم', 'الكريم', 'الدين', 'الإسلام', 'الاسلام', 'العابدين',
        'الحق', 'الملك', 'القادر', 'الخالق', 'الجليل', 'الجواد', 'المنعم', 'المولى',
        'اللطيف', 'الفتاح', 'الرازق', 'الغفور', 'الحكيم', 'العزيز', 'السلام', 'الهادي',
        'الباري', 'المصور', 'الوهاب', 'الحسن', 'الحسين', 'الخطاب'
    ];

    /**
     * 🔍 تقسيم الاسم الكامل إلى 4 أجزاء
     */
    public static function segment($fullName)
    {
        // القيمة الافتراضية للنتيجة الفارغة
        $emptyResult = [
            'first_name' => '',
            'father_name' => '',
            'grand_father_name' => '',
            'family_name' => '',
            'full_name' => '',
            'segments_count' => 0,
            'original_segments' => [],
            'merged_segments' => []
        ];

        // التحقق من القيمة الفارغة
        if ($fullName === null || $fullName === '' || !is_string($fullName)) {
            return $emptyResult;
        }

        // تنظيف الاسم
        $cleanedName = self::cleanName($fullName);

        if (empty($cleanedName)) {
            return $emptyResult;
        }

        // تقسيم الاسم إلى كلمات (مع u flag لدعم Unicode/العربية)
        $words = preg_split('/\s+/u', $cleanedName, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return $emptyResult;
        }

        // دمج الكلمات المركبة
        $mergedSegments = self::mergeCompoundNames($words);

        // توزيع المقاطع على 4 حقول
        $result = self::distributeSegments($mergedSegments);

        // إضافة معلومات إضافية
        $result['full_name'] = $cleanedName;
        $result['segments_count'] = count($mergedSegments);
        $result['original_segments'] = $words;
        $result['merged_segments'] = $mergedSegments;

        return $result;
    }

    /**
     * 🧹 تنظيف الاسم من الأحرف الزائدة
     * متوافق مع PHP 8.3+ - لا يتم تمرير null إلى preg_replace()
     * 🔧 جميع الـ regex patterns تستخدم /u flag للتعامل الصحيح مع Unicode/العربية
     */
    private static function cleanName($name): string
    {
        // التحقق الصارم من نوع المدخل - تحويل إلى string إذا لزم الأمر
        if ($name === null) {
            return '';
        }

        // تحويل أي قيمة إلى string بشكل آمن
        $name = (string) $name;

        if ($name === '') {
            return '';
        }

        // إزالة الفراغات المتعددة (مع u flag لدعم Unicode)
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        // إزالة الأقواس ومحتوياتها (مثل: رحمه الله)
        $name = preg_replace('/\([^)]*\)/u', '', $name) ?? $name;

        // إزالة علامات الترقيم غير الضرورية (مع u flag للتعامل الصحيح مع الفاصلة العربية)
        $name = preg_replace('/[،,؛;:!?\.]+/u', '', $name) ?? $name;

        // إزالة الألقاب الشائعة في البداية
        $titlesToRemove = ['الدكتور', 'الأستاذ', 'الشيخ', 'المهندس', 'الحاج', 'الحاجة', 'السيد', 'السيدة'];
        foreach ($titlesToRemove as $title) {
            $pattern = '/^' . preg_quote($title, '/') . '\s+/u';
            $name = preg_replace($pattern, '', $name) ?? $name;
        }

        return trim($name);
    }

    /**
     * 🔗 دمج الكلمات المركبة
     */
    private static function mergeCompoundNames(array $words)
    {
        $merged = [];
        $i = 0;
        $count = count($words);

        while ($i < $count) {
            $currentWord = $words[$i];

            // التحقق إذا كانت الكلمة الحالية بادئة مركبة
            if (self::isCompoundPrefix($currentWord) && $i + 1 < $count) {
                // دمج الكلمة الحالية مع التالية
                $compoundName = $currentWord . ' ' . $words[$i + 1];

                // التحقق إذا كانت الكلمة الثالثة أيضاً جزءاً من الاسم المركب
                if ($i + 2 < $count && self::isCompoundSuffix($words[$i + 2])) {
                    $compoundName .= ' ' . $words[$i + 2];
                    $i += 3;
                } else {
                    $i += 2;
                }

                $merged[] = $compoundName;
            }
            // التحقق إذا كانت الكلمة التالية لاحقة مركبة
            elseif ($i + 1 < $count && self::isCompoundSuffix($words[$i + 1]) && !self::isCompoundPrefix($currentWord)) {
                $merged[] = $currentWord . ' ' . $words[$i + 1];
                $i += 2;
            }
            else {
                $merged[] = $currentWord;
                $i++;
            }
        }

        return $merged;
    }

    /**
     * ✅ التحقق إذا كانت الكلمة بادئة مركبة
     */
    private static function isCompoundPrefix($word)
    {
        $word = mb_strtolower(trim($word), 'UTF-8');
        foreach (self::$compoundPrefixes as $prefix) {
            if (mb_strtolower($prefix, 'UTF-8') === $word) {
                return true;
            }
        }
        return false;
    }

    /**
     * ✅ التحقق إذا كانت الكلمة لاحقة مركبة
     */
    private static function isCompoundSuffix($word)
    {
        $word = mb_strtolower(trim($word), 'UTF-8');
        foreach (self::$compoundSuffixes as $suffix) {
            if (mb_strtolower($suffix, 'UTF-8') === $word) {
                return true;
            }
        }
        return false;
    }

    /**
     * 📦 توزيع المقاطع على 4 حقول
     */
    private static function distributeSegments(array $segments)
    {
        $count = count($segments);

        if ($count === 0) {
            return [
                'first_name' => '',
                'father_name' => '',
                'grand_father_name' => '',
                'family_name' => ''
            ];
        }

        if ($count === 1) {
            return [
                'first_name' => $segments[0],
                'father_name' => '',
                'grand_father_name' => '',
                'family_name' => ''
            ];
        }

        if ($count === 2) {
            return [
                'first_name' => $segments[0],
                'father_name' => '',
                'grand_father_name' => '',
                'family_name' => $segments[1]
            ];
        }

        if ($count === 3) {
            return [
                'first_name' => $segments[0],
                'father_name' => $segments[1],
                'grand_father_name' => '',
                'family_name' => $segments[2]
            ];
        }

        if ($count === 4) {
            return [
                'first_name' => $segments[0],
                'father_name' => $segments[1],
                'grand_father_name' => $segments[2],
                'family_name' => $segments[3]
            ];
        }

        // أكثر من 4 مقاطع - دمج المقاطع الوسطى
        $firstName = $segments[0];
        $fatherName = $segments[1];
        $familyName = $segments[$count - 1];

        // دمج المقاطع الوسطى في grand_father_name
        $middleSegments = array_slice($segments, 2, $count - 3);
        $grandFatherName = implode(' ', $middleSegments);

        return [
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'grand_father_name' => $grandFatherName,
            'family_name' => $familyName
        ];
    }

    /**
     * 📊 اختبار الخوارزمية مع مجموعة أسماء
     */
    public static function testBatch(array $names)
    {
        $results = [];

        foreach ($names as $name) {
            $segmented = self::segment($name);
            $results[] = [
                'input' => $name,
                'output' => $segmented,
                'reconstructed' => implode(' ', array_filter([
                    $segmented['first_name'],
                    $segmented['father_name'],
                    $segmented['grand_father_name'],
                    $segmented['family_name']
                ])),
                'segments_count' => $segmented['segments_count']
            ];
        }

        return $results;
    }
}
