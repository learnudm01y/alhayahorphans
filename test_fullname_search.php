<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;
use Illuminate\Support\Facades\DB;

echo "اختبار البحث بالاسم الكامل في السجل المدني:\n";
echo str_repeat("=", 60) . "\n";

$service = new CivilRegistryScoutSearchService();

// أولاً، دعنا نجد بعض الأسماء الكاملة من قاعدة البيانات للاختبار
echo "🔍 البحث عن أسماء عينة من قاعدة البيانات...\n";

try {
    $sampleNames = DB::connection('civilregistry')
        ->table('persons')
        ->selectRaw("CONCAT_WS(' ', CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB) as full_name, CI_ID_NUM")
        ->whereNotNull('CI_FIRST_ARB')
        ->whereNotNull('CI_FATHER_ARB')
        ->whereNotNull('CI_FAMILY_ARB')
        ->where('CI_FIRST_ARB', '!=', '')
        ->where('CI_FATHER_ARB', '!=', '')
        ->limit(5)
        ->get();

    echo "أسماء العينة المأخوذة من قاعدة البيانات:\n";
    foreach ($sampleNames as $sample) {
        echo "  - {$sample->full_name} (رقم الهوية: {$sample->CI_ID_NUM})\n";
    }
    echo "\n";

    // اختبار البحث بالأسماء الكاملة
    foreach ($sampleNames as $index => $sample) {
        $fullName = trim($sample->full_name);
        if (strlen($fullName) > 10) { // اختبار الأسماء الطويلة فقط
            echo "اختبار " . ($index + 1) . ": البحث بالاسم الكامل: '$fullName'\n";
            echo str_repeat("-", 50) . "\n";

            $start = microtime(true);
            $result = $service->quickScoutSearch($fullName, 20);
            $time = (microtime(true) - $start) * 1000;

            if ($result['success']) {
                echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
                echo "⚡ الوقت: " . round($time, 2) . " ms (الخدمة: " . $result['execution_time'] . ")\n";

                if ($result['total_count'] > 0) {
                    echo "📋 النتائج:\n";
                    foreach ($result['data'] as $i => $person) {
                        $person = (array)$person;
                        $foundFullName = trim(($person['CI_FIRST_ARB'] ?? '') . " " .
                                            ($person['CI_FATHER_ARB'] ?? '') . " " .
                                            ($person['CI_GRAND_FATHER_ARB'] ?? '') . " " .
                                            ($person['CI_FAMILY_ARB'] ?? ''));

                        echo "   " . ($i + 1) . ". رقم الهوية: " . ($person['CI_ID_NUM'] ?? 'غير محدد') . "\n";
                        echo "      الاسم الكامل: $foundFullName\n";

                        // التحقق من التطابق
                        if (strpos($foundFullName, $fullName) !== false || strpos($fullName, $foundFullName) !== false) {
                            echo "      🎯 مطابقة مباشرة!\n";
                        } else {
                            // تحقق من التطابق الجزئي
                            $searchWords = explode(' ', $fullName);
                            $foundWords = explode(' ', $foundFullName);
                            $matchCount = 0;

                            foreach ($searchWords as $searchWord) {
                                foreach ($foundWords as $foundWord) {
                                    if (strlen($searchWord) >= 3 && strpos($foundWord, $searchWord) !== false) {
                                        $matchCount++;
                                        break;
                                    }
                                }
                            }

                            if ($matchCount >= 2) {
                                echo "      ✅ مطابقة جزئية ({$matchCount} كلمات)\n";
                            } else {
                                echo "      ⚠️ مطابقة ضعيفة\n";
                            }
                        }

                        if ($i >= 2) break; // عرض أول 3 نتائج فقط
                    }
                } else {
                    echo "❌ لا توجد نتائج!\n";
                }
            } else {
                echo "❌ خطأ: " . $result['error'] . "\n";
            }
            echo "\n";
        }
    }

    // اختبار البحث بالاسم المُحدد من المستخدم
    echo str_repeat("=", 60) . "\n";
    echo "اختبار خاص: البحث بالاسم 'لمياء ابراهيم زياد ابو دحيل'\n";
    echo str_repeat("-", 60) . "\n";

    $userQuery = 'لمياء ابراهيم زياد ابو دحيل';
    $start = microtime(true);
    $result = $service->quickScoutSearch($userQuery, 50);
    $time = (microtime(true) - $start) * 1000;

    if ($result['success']) {
        echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
        echo "⚡ الوقت: " . round($time, 2) . " ms\n";

        if ($result['total_count'] > 0) {
            echo "📋 جميع النتائج:\n";
            foreach ($result['data'] as $i => $person) {
                $person = (array)$person;
                $foundFullName = trim(($person['CI_FIRST_ARB'] ?? '') . " " .
                                    ($person['CI_FATHER_ARB'] ?? '') . " " .
                                    ($person['CI_GRAND_FATHER_ARB'] ?? '') . " " .
                                    ($person['CI_FAMILY_ARB'] ?? ''));

                echo "   " . ($i + 1) . ". رقم الهوية: " . ($person['CI_ID_NUM'] ?? 'غير محدد') . "\n";
                echo "      الاسم الكامل: $foundFullName\n";

                if (!empty($person['MOTHER_NAME1'])) {
                    echo "      اسم الأم: " . $person['MOTHER_NAME1'] . "\n";
                }

                // تحليل المطابقة
                $userWords = explode(' ', $userQuery);
                $foundWords = explode(' ', $foundFullName);
                $matches = [];

                foreach ($userWords as $userWord) {
                    foreach ($foundWords as $foundWord) {
                        if (strlen($userWord) >= 3 && strpos($foundWord, $userWord) !== false) {
                            $matches[] = "'{$userWord}' في '{$foundWord}'";
                        }
                    }
                }

                if (!empty($matches)) {
                    echo "      🔍 المطابقات: " . implode(', ', $matches) . "\n";
                }
                echo "\n";
            }
        } else {
            echo "❌ لا توجد نتائج للاسم المحدد!\n";
            echo "💡 جرب البحث بأجزاء من الاسم:\n";

            $testParts = ['لمياء', 'ابراهيم', 'زياد', 'ابو دحيل'];
            foreach ($testParts as $part) {
                $partResult = $service->quickScoutSearch($part, 3);
                if ($partResult['success'] && $partResult['total_count'] > 0) {
                    echo "   - '$part': {$partResult['total_count']} نتيجة\n";
                } else {
                    echo "   - '$part': لا توجد نتائج\n";
                }
            }
        }
    } else {
        echo "❌ خطأ: " . $result['error'] . "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "انتهى اختبار البحث بالاسم الكامل! 🎯\n";
