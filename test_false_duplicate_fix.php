<?php

/**
 * اختبار إصلاح مشكلة الملفات المكررة الوهمية
 * يقوم بتحليل وفحص نظام كشف التكرار المحدث
 */

require_once __DIR__ . '/vendor/autoload.php';

// إعداد البيئة
$_ENV['APP_ENV'] = 'local';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FolderDuplicateDetectionService;
use Illuminate\Support\Facades\Log;

class DuplicateDetectionTester
{
    protected $service;
    protected $testResults = [];

    public function __construct()
    {
        $this->service = new FolderDuplicateDetectionService();
    }

    /**
     * تشغيل جميع الاختبارات
     */
    public function runAllTests()
    {
        echo "=== بدء اختبار إصلاح مشكلة الملفات المكررة الوهمية ===\n\n";

        $this->testFileInfoExtraction();
        $this->testExactMatching();
        $this->testRealWorldScenarios();
        $this->printSummary();
    }

    /**
     * اختبار استخراج معلومات الملف
     */
    public function testFileInfoExtraction()
    {
        echo "1. اختبار استخراج معلومات الملف:\n";
        echo "================================\n";

        $testFiles = [
            'D_800214157_1.jpg' => [
                'expected_pattern' => 'old_format',
                'expected_identity' => '800214157',
                'expected_doc_type' => '1',
                'expected_prefix' => 'D'
            ],
            'D_800214157_15.jpg' => [
                'expected_pattern' => 'old_format',
                'expected_identity' => '800214157',
                'expected_doc_type' => '15',
                'expected_prefix' => 'D'
            ],
            '1_001550_800214157.jpg' => [
                'expected_pattern' => 'new_format',
                'expected_identity' => '800214157',
                'expected_prefix' => '1',
                'expected_folder' => '001550'
            ],
            '15_001550_800214157.jpg' => [
                'expected_pattern' => 'new_format',
                'expected_identity' => '800214157',
                'expected_prefix' => '15',
                'expected_folder' => '001550'
            ]
        ];

        foreach ($testFiles as $filename => $expected) {
            $info = $this->extractFileInfo($filename);

            echo "الملف: $filename\n";
            echo "  النمط: {$info['file_pattern']} (متوقع: {$expected['expected_pattern']})\n";
            echo "  رقم الهوية: {$info['identity_number']} (متوقع: {$expected['expected_identity']})\n";
            echo "  البادئة: {$info['prefix']} (متوقع: {$expected['expected_prefix']})\n";

            if ($info['file_pattern'] === 'old_format') {
                echo "  نوع الوثيقة: {$info['document_type']} (متوقع: {$expected['expected_doc_type']})\n";
            } else {
                echo "  رقم المجلد: {$info['folder_number']} (متوقع: {$expected['expected_folder']})\n";
            }

            echo "  التوقيع الفريد: {$info['unique_signature']}\n";

            $passed = ($info['file_pattern'] === $expected['expected_pattern'] &&
                      $info['identity_number'] === $expected['expected_identity'] &&
                      $info['prefix'] === $expected['expected_prefix']);

            echo "  النتيجة: " . ($passed ? "✅ نجح" : "❌ فشل") . "\n\n";

            $this->testResults['file_info'][$filename] = $passed;
        }
    }

    /**
     * اختبار المطابقة الدقيقة
     */
    public function testExactMatching()
    {
        echo "2. اختبار المطابقة الدقيقة:\n";
        echo "==========================\n";

        $testCases = [
            [
                'file1' => 'D_800214157_1.jpg',
                'file2' => 'D_800214157_15.jpg',
                'should_match' => false,
                'reason' => 'نفس الهوية، أنواع وثائق مختلفة'
            ],
            [
                'file1' => 'D_800214157_1.jpg',
                'file2' => '1_001550_800214157.jpg',
                'should_match' => true,
                'reason' => 'نفس الهوية ونوع الوثيقة (1)'
            ],
            [
                'file1' => 'D_800214157_15.jpg',
                'file2' => '1_001550_800214157.jpg',
                'should_match' => false,
                'reason' => 'نفس الهوية، أنواع وثائق مختلفة (15 مقابل 1)'
            ],
            [
                'file1' => 'D_800214157_15.jpg',
                'file2' => '15_001550_800214157.jpg',
                'should_match' => true,
                'reason' => 'نفس الهوية ونوع الوثيقة (15)'
            ],
            [
                'file1' => '1_001550_800214157.jpg',
                'file2' => '1_001549_800214157.jpg',
                'should_match' => true,
                'reason' => 'نفس نوع الوثيقة والهوية، مجلدات مختلفة'
            ]
        ];

        foreach ($testCases as $index => $case) {
            $info1 = $this->extractFileInfo($case['file1']);
            $info2 = $this->extractFileInfo($case['file2']);

            $matches = $this->areFilesExactMatch($info1, $info2);

            echo "الحالة " . ($index + 1) . ": {$case['reason']}\n";
            echo "  الملف الأول: {$case['file1']}\n";
            echo "  الملف الثاني: {$case['file2']}\n";
            echo "  النتيجة المتوقعة: " . ($case['should_match'] ? 'متطابق' : 'غير متطابق') . "\n";
            echo "  النتيجة الفعلية: " . ($matches ? 'متطابق' : 'غير متطابق') . "\n";

            $passed = ($matches === $case['should_match']);
            echo "  الاختبار: " . ($passed ? "✅ نجح" : "❌ فشل") . "\n\n";

            $this->testResults['exact_matching'][$index] = $passed;
        }
    }

    /**
     * اختبار سيناريوهات من العالم الحقيقي
     */
    public function testRealWorldScenarios()
    {
        echo "3. اختبار سيناريوهات من العالم الحقيقي:\n";
        echo "====================================\n";

        // السيناريو الأول: من logs الفعلية
        echo "السيناريو الأول - مشكلة من الـ logs:\n";
        echo "--------------------------------\n";

        $originalFile = $this->extractFileInfo('D_800214157_15.jpg');
        $existingFile = $this->extractFileInfo('1_001550_800214157.jpg');

        echo "الملف الأصلي: D_800214157_15.jpg (نوع وثيقة: 15)\n";
        echo "الملف الموجود: 1_001550_800214157.jpg (نوع وثيقة: 1)\n";

        $shouldBeDetectedAsDuplicate = $this->areFilesExactMatch($originalFile, $existingFile);

        echo "هل يجب اعتبارهما مكررين؟ لا\n";
        echo "هل يعتبرهما النظام مكررين؟ " . ($shouldBeDetectedAsDuplicate ? 'نعم' : 'لا') . "\n";

        $passed1 = (!$shouldBeDetectedAsDuplicate);
        echo "النتيجة: " . ($passed1 ? "✅ نجح - لا يعتبرهما مكررين" : "❌ فشل - يعتبرهما مكررين خطأً") . "\n\n";

        // السيناريو الثاني: ملفات مكررة حقيقية
        echo "السيناريو الثاني - ملفات مكررة حقيقية:\n";
        echo "------------------------------------\n";

        $realDuplicate1 = $this->extractFileInfo('D_800214157_1.jpg');
        $realDuplicate2 = $this->extractFileInfo('1_001550_800214157.jpg');

        echo "الملف الأول: D_800214157_1.jpg (نوع وثيقة: 1)\n";
        echo "الملف الثاني: 1_001550_800214157.jpg (نوع وثيقة: 1)\n";

        $shouldBeDetectedAsDuplicate2 = $this->areFilesExactMatch($realDuplicate1, $realDuplicate2);

        echo "هل يجب اعتبارهما مكررين؟ نعم\n";
        echo "هل يعتبرهما النظام مكررين؟ " . ($shouldBeDetectedAsDuplicate2 ? 'نعم' : 'لا') . "\n";

        $passed2 = ($shouldBeDetectedAsDuplicate2);
        echo "النتيجة: " . ($passed2 ? "✅ نجح - يعتبرهما مكررين بشكل صحيح" : "❌ فشل - لا يعتبرهما مكررين") . "\n\n";

        $this->testResults['real_world'][1] = $passed1;
        $this->testResults['real_world'][2] = $passed2;
    }

    /**
     * طباعة ملخص النتائج
     */
    public function printSummary()
    {
        echo "=== ملخص نتائج الاختبار ===\n";
        echo "===========================\n";

        $totalTests = 0;
        $passedTests = 0;

        foreach ($this->testResults as $category => $tests) {
            $categoryPassed = array_sum($tests);
            $categoryTotal = count($tests);
            $totalTests += $categoryTotal;
            $passedTests += $categoryPassed;

            echo ucfirst($category) . ": $categoryPassed/$categoryTotal\n";
        }

        echo "\nالنتيجة الإجمالية: $passedTests/$totalTests\n";

        if ($passedTests === $totalTests) {
            echo "🎉 جميع الاختبارات نجحت! تم إصلاح مشكلة الملفات المكررة الوهمية.\n";
        } else {
            echo "⚠️  بعض الاختبارات فشلت. قد تحتاج لمراجعة إضافية.\n";
        }
    }

    // === دوال مساعدة ===

    protected function extractFileInfo($filename)
    {
        $originalName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $parts = explode('_', $originalName);

        $info = [
            'original_name' => $filename,
            'extension' => $extension,
            'parts' => $parts,
            'parts_count' => count($parts),
            'prefix' => null,
            'identity_number' => null,
            'document_type' => null,
            'file_pattern' => 'unknown'
        ];

        // تحديد نمط الملف وتحليله
        if (count($parts) >= 3) {
            // النمط الجديد: PREFIX_FOLDER_IDENTITY
            if (preg_match('/^\d{8,10}$/', $parts[2])) {
                $info['file_pattern'] = 'new_format';
                $info['prefix'] = $parts[0];
                $info['folder_number'] = $parts[1];
                $info['identity_number'] = $parts[2];
                $info['unique_signature'] = $info['prefix'] . '_' . $info['identity_number'] . '_' . $extension;
            }
            // النمط القديم: PREFIX_IDENTITY_DOCTYPE
            elseif (preg_match('/^\d{8,10}$/', $parts[1])) {
                $info['file_pattern'] = 'old_format';
                $info['prefix'] = $parts[0];
                $info['identity_number'] = $parts[1];
                $info['document_type'] = $parts[2];
                $info['unique_signature'] = $info['document_type'] . '_' . $info['identity_number'] . '_' . $extension;
            }
        }

        // إذا لم نجد النمط المعروف، ابحث عن رقم هوية في أي مكان
        if (!$info['identity_number']) {
            foreach ($parts as $part) {
                if (preg_match('/^\d{8,10}$/', $part)) {
                    $info['identity_number'] = $part;
                    $info['file_pattern'] = 'custom_format';
                    break;
                }
            }
        }

        // إنشاء توقيع فريد للملف
        if (!isset($info['unique_signature'])) {
            $info['unique_signature'] = md5($filename) . '_' . $extension;
        }

        return $info;
    }

    protected function areFilesExactMatch(array $fileInfo1, array $fileInfo2): bool
    {
        // الشروط الأساسية للتطابق
        $basicMatch = (
            $fileInfo1['identity_number'] === $fileInfo2['identity_number'] &&
            $fileInfo1['extension'] === $fileInfo2['extension']
        );

        if (!$basicMatch) {
            return false;
        }

        // للنمط الجديد: تحقق من البادئة أيضاً
        if ($fileInfo1['file_pattern'] === 'new_format' && $fileInfo2['file_pattern'] === 'new_format') {
            return $fileInfo1['prefix'] === $fileInfo2['prefix'];
        }

        // للنمط القديم: تحقق من البادئة ونوع الوثيقة
        if ($fileInfo1['file_pattern'] === 'old_format' && $fileInfo2['file_pattern'] === 'old_format') {
            return (
                $fileInfo1['prefix'] === $fileInfo2['prefix'] &&
                $fileInfo1['document_type'] === $fileInfo2['document_type']
            );
        }

        // للأنماط المختلطة: مقارنة دقيقة بناء على نوع الوثيقة
        // يجب أن يكون نوع الوثيقة متطابق بين الأنماط المختلفة
        if ($fileInfo1['file_pattern'] !== $fileInfo2['file_pattern']) {
            // استخراج نوع الوثيقة من كلا الملفين
            $docType1 = isset($fileInfo1['document_type']) ? $fileInfo1['document_type'] :
                       (isset($fileInfo1['prefix']) ? $fileInfo1['prefix'] : null);
            $docType2 = isset($fileInfo2['document_type']) ? $fileInfo2['document_type'] :
                       (isset($fileInfo2['prefix']) ? $fileInfo2['prefix'] : null);

            // يجب أن يكون نوع الوثيقة متطابق
            return $docType1 === $docType2 && !empty($docType1);
        }

        // إذا لم تطابق أي من الحالات أعلاه، فالملفات غير متطابقة
        return false;
    }
}

// تشغيل الاختبار
$tester = new DuplicateDetectionTester();
$tester->runAllTests();
