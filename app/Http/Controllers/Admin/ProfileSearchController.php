<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NormalizedSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProfileSearchController extends Controller
{
    protected $normalizedSearchService;

    public function __construct(NormalizedSearchService $normalizedSearchService)
    {
        $this->normalizedSearchService = $normalizedSearchService;
    }

    /**
     * البحث السريع في الملفات مع الاقتراحات
     */
    public function quickSearch(Request $request)
    {
        $query = $request->input('query', '');

        // التحقق من الحد الأدنى لطول البحث
        if (strlen(trim($query)) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'يجب أن يكون النص أكثر من حرفين'
            ]);
        }

        try {
            // استخدام الكاش للنتائج السريعة مع مدة أقصر
            $cacheKey = 'profile_search_v2_' . md5($query);

            $results = Cache::remember($cacheKey, 60, function () use ($query) { // دقيقة واحدة فقط
                return $this->performQuickSearch($query);
            });

            return response()->json([
                'success' => true,
                'data' => $results,
                'total' => count($results)
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('خطأ في البحث السريع: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث'
            ]);
        }
    }

    /**
     * تنفيذ البحث السريع باستخدام NormalizedSearchService
     */
    private function performQuickSearch($query)
    {
        $results = [];

        // البحث في الجدول الرئيسي باستخدام NormalizedSearchService
        $mainRecords = $this->searchMainRecordsNormalized($query);
        $results = array_merge($results, $mainRecords);

        // البحث في أفراد الأسرة (فقط إذا لم نجد تطابق كامل في الجدول الرئيسي)
        $hasExactMatch = $this->hasExactMatch($results, $query);
        if (!$hasExactMatch) {
            $familyMembers = $this->searchFamilyMembersNormalized($query);
            $results = array_merge($results, $familyMembers);

            // البحث في المتوفين (فقط إذا لم نجد تطابق)
            $deceased = $this->searchDeceasedNormalized($query);
            $results = array_merge($results, $deceased);
        }

        // ترتيب النتائج حسب الصلة وتحديد العدد
        $results = $this->sortAndLimitResults($results, $query);

        return $results;
    }

    /**
     * فحص وجود تطابق كامل في النتائج
     */
    private function hasExactMatch($results, $query)
    {
        $queryLower = strtolower(trim($query));

        foreach ($results as $result) {
            if ($result['relevance'] >= 80) { // درجة صلة عالية جداً
                return true;
            }

            $titleLower = strtolower($result['title']);
            if ($titleLower === $queryLower || strpos($titleLower, $queryLower) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * البحث في السجلات الرئيسية باستخدام التطبيع
     */
    private function searchMainRecordsNormalized($query)
    {
        // تقسيم النص إلى كلمات للبحث المتقدم
        $words = $this->extractSearchWords($query);

        // تطبيع كلمة البحث
        $normalizedSearches = normalizeArabicForFlexibleSearch($query);
        $normalizedQuery = $normalizedSearches['with_spaces'];
        $noSpacesQuery = $normalizedSearches['without_spaces'];

        // البحث في جدول Data باستخدام Eloquent مع العلاقات
        $records = \App\Models\Data::where(function ($q) use ($query, $words, $normalizedQuery, $noSpacesQuery) {
            // بناء SQL expressions للتطبيع
            $fullName = "CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name)";

            // البحث بالنص الكامل في الاسم المجمع - مع التطبيع
            $q->whereRaw("({$this->buildNormSqlInline($fullName)}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline($fullName)}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأعمدة المنفصلة - مع التطبيع
            $q->orWhereRaw("({$this->buildNormSqlInline('data_first_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_first_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('data_father_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_father_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('data_grand_father_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_grand_father_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('data_family_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_family_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأرقام (بدون تطبيع)
            $q->orWhere('data_id_number', 'LIKE', "%{$query}%");
            $q->orWhere('file_id_number', 'LIKE', "%{$query}%");

            // البحث بالكلمات المنفصلة فقط إذا كان النص قصير (أقل من 4 كلمات)
            if (count($words) <= 3 && count($words) > 1) {
                foreach ($words as $word) {
                    if (strlen(trim($word)) >= 3) {
                        $wordSearches = normalizeArabicForFlexibleSearch($word);
                        $normalizedWord = $wordSearches['with_spaces'];
                        $noSpacesWord = $wordSearches['without_spaces'];

                        $q->orWhereRaw("({$this->buildNormSqlInline('data_first_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_first_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('data_father_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_father_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('data_grand_father_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_grand_father_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('data_family_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('data_family_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                    }
                }
            }
        })
        ->with(['city', 'province'])
        ->limit(5)
        ->get();

        return $records->map(function ($record) use ($query) {
            return [
                'id' => $record->id,
                'type' => 'main_record',
                'title' => $this->formatFullName($record),
                'subtitle' => 'ملف رقم: ' . ($record->file_id_number ?? 'غير محدد'),
                'description' => $this->formatRecordDescription($record),
                'url' => route('admin.records.management.show', $record->id),
                'relevance' => $this->calculateRelevance($query, $record)
            ];
        })->toArray();
    }

    /**
     * البحث في أفراد الأسرة باستخدام التطبيع
     */
    private function searchFamilyMembersNormalized($query)
    {
        $words = $this->extractSearchWords($query);

        // تطبيع كلمة البحث
        $normalizedSearches = normalizeArabicForFlexibleSearch($query);
        $normalizedQuery = $normalizedSearches['with_spaces'];
        $noSpacesQuery = $normalizedSearches['without_spaces'];

        // البحث في جدول RePeople باستخدام Eloquent
        $records = \App\Models\RePeople::where(function ($q) use ($query, $words, $normalizedQuery, $noSpacesQuery) {
            // بناء SQL expressions للتطبيع
            $fullName = "CONCAT(first_name, ' ', second_name, ' ', third_name, ' ', last_name)";

            // البحث بالنص الكامل في الاسم المجمع - مع التطبيع
            $q->whereRaw("({$this->buildNormSqlInline($fullName)}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline($fullName)}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأعمدة المنفصلة - مع التطبيع
            $q->orWhereRaw("({$this->buildNormSqlInline('first_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('first_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('second_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('second_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('third_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('third_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('last_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('last_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأرقام (بدون تطبيع)
            $q->orWhere('person_id', 'LIKE', "%{$query}%");
            $q->orWhere('registration_id', 'LIKE', "%{$query}%");

            // البحث بالكلمات المنفصلة فقط للنصوص القصيرة
            if (count($words) <= 3 && count($words) > 1) {
                foreach ($words as $word) {
                    if (strlen(trim($word)) >= 3) {
                        $wordSearches = normalizeArabicForFlexibleSearch($word);
                        $normalizedWord = $wordSearches['with_spaces'];
                        $noSpacesWord = $wordSearches['without_spaces'];

                        $q->orWhereRaw("({$this->buildNormSqlInline('first_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('first_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('second_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('second_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('third_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('third_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('last_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('last_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                    }
                }
            }
        })
        ->limit(3)
        ->get();

        return $records->map(function ($record) use ($query) {
            // البحث عن السجل الرئيسي للحصول على الرابط
            $mainRecord = \App\Models\Data::where('file_id_number', $record->registration_id)->first();

            return [
                'id' => $record->id,
                'type' => 'family_member',
                'title' => $this->formatFamilyMemberName($record),
                'subtitle' => 'فرد أسرة - ملف رقم: ' . ($record->registration_id ?? 'غير محدد'),
                'description' => 'صلة القرابة: ' . ($record->sponsorshipStatus->sponsorship_description ?? 'غير محدد'),
                'url' => $mainRecord ? route('admin.records.management.show', $mainRecord->id) : '#',
                'relevance' => $this->calculateFamilyRelevance($query, $record)
            ];
        })->toArray();
    }

    /**
     * البحث في المتوفين باستخدام التطبيع
     */
    private function searchDeceasedNormalized($query)
    {
        $words = $this->extractSearchWords($query);

        // تطبيع كلمة البحث
        $normalizedSearches = normalizeArabicForFlexibleSearch($query);
        $normalizedQuery = $normalizedSearches['with_spaces'];
        $noSpacesQuery = $normalizedSearches['without_spaces'];

        $records = \App\Models\DeadPepole::where(function ($q) use ($query, $words, $normalizedQuery, $noSpacesQuery) {
            // بناء SQL expressions للتطبيع
            $fatherFullName = "CONCAT(father_first_name, ' ', father_second_name, ' ', father_third_name, ' ', father_last_name)";
            $motherFullName = "CONCAT(mother_first_name, ' ', mother_second_name, ' ', mother_third_name, ' ', mother_last_name)";

            // البحث بالنص الكامل في اسم الأب المجمع - مع التطبيع
            $q->whereRaw("({$this->buildNormSqlInline($fatherFullName)}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline($fatherFullName)}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث بالنص الكامل في اسم الأم المجمع - مع التطبيع
            $q->orWhereRaw("({$this->buildNormSqlInline($motherFullName)}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline($motherFullName)}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأعمدة المنفصلة للأب - مع التطبيع
            $q->orWhereRaw("({$this->buildNormSqlInline('father_first_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_first_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('father_second_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_second_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('father_third_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_third_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('father_last_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_last_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأعمدة المنفصلة للأم - مع التطبيع
            $q->orWhereRaw("({$this->buildNormSqlInline('mother_first_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_first_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('mother_second_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_second_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('mother_third_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_third_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);
            $q->orWhereRaw("({$this->buildNormSqlInline('mother_last_name')}) LIKE ?", ["%{$normalizedQuery}%"]);
            $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_last_name')}) LIKE ?", ["%{$noSpacesQuery}%"]);

            // البحث في الأرقام (بدون تطبيع)
            $q->orWhere('father_id', 'LIKE', "%{$query}%");
            $q->orWhere('mother_id', 'LIKE', "%{$query}%");
            $q->orWhere('re_file_id', 'LIKE', "%{$query}%");

            // البحث بالكلمات المنفصلة فقط للنصوص القصيرة
            if (count($words) <= 3 && count($words) > 1) {
                foreach ($words as $word) {
                    if (strlen(trim($word)) >= 3) {
                        $wordSearches = normalizeArabicForFlexibleSearch($word);
                        $normalizedWord = $wordSearches['with_spaces'];
                        $noSpacesWord = $wordSearches['without_spaces'];

                        // بحث الكلمة في أعمدة الأب
                        $q->orWhereRaw("({$this->buildNormSqlInline('father_first_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_first_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('father_second_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_second_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('father_third_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_third_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('father_last_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('father_last_name')}) LIKE ?", ["%{$noSpacesWord}%"]);

                        // بحث الكلمة في أعمدة الأم
                        $q->orWhereRaw("({$this->buildNormSqlInline('mother_first_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_first_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('mother_second_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_second_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('mother_third_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_third_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                        $q->orWhereRaw("({$this->buildNormSqlInline('mother_last_name')}) LIKE ?", ["%{$normalizedWord}%"]);
                        $q->orWhereRaw("({$this->buildNoSpacesSqlInline('mother_last_name')}) LIKE ?", ["%{$noSpacesWord}%"]);
                    }
                }
            }
        })
        ->limit(2) // تقليل عدد النتائج
        ->get();

        return $records->map(function ($record) use ($query) {
            // البحث عن السجل الرئيسي للحصول على الرابط
            $mainRecord = \App\Models\Data::where('file_id_number', $record->re_file_id)->first();

            // تحديد ما إذا كان الأب أم الأم هو المطابق للبحث
            $personType = $this->determineDeceasedPerson($record, $query);

            return [
                'id' => $record->re_file_id,
                'type' => 'deceased',
                'title' => $this->formatDeceasedName($record, $personType),
                'subtitle' => 'متوفي (' . $personType . ') - ملف رقم: ' . ($record->re_file_id ?? 'غير محدد'),
                'description' => 'تاريخ الوفاة: ' . $this->getDeathDate($record, $personType),
                'url' => $mainRecord ? route('admin.records.management.show', $mainRecord->id) : '#',
                'relevance' => $this->calculateDeceasedRelevance($query, $record)
            ];
        })->toArray();
    }

    /**
     * بناء SQL inline للتطبيع مع المسافات
     */
    private function buildNormSqlInline($column)
    {
        return "TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', ''),
                '  ', ' '), '   ', ' '))";
    }

    /**
     * بناء SQL inline لإزالة كل المسافات
     */
    private function buildNoSpacesSqlInline($column)
    {
        return "REPLACE(TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', '')),
                ' ', '')";
    }

    /**
     * تنسيق الاسم الكامل للسجل الرئيسي
     */
    private function formatFullName($record)
    {
        $names = array_filter([
            $record->data_first_name,
            $record->data_father_name,
            $record->data_grand_father_name,
            $record->data_family_name
        ]);

        return implode(' ', $names) ?: 'اسم غير محدد';
    }

    /**
     * تنسيق وصف السجل
     */
    private function formatRecordDescription($record)
    {
        $parts = [];

        if ($record->city && $record->city->city) {
            $parts[] = $record->city->city;
        }

        if ($record->province && $record->province->description) {
            $parts[] = $record->province->description;
        }

        if ($record->data_id_number) {
            $parts[] = 'هوية: ' . $record->data_id_number;
        }

        return implode(' - ', $parts) ?: 'لا توجد تفاصيل إضافية';
    }

    /**
     * تنسيق اسم فرد الأسرة
     */
    private function formatFamilyMemberName($record)
    {
        $names = array_filter([
            $record->first_name,
            $record->second_name,
            $record->third_name,
            $record->last_name
        ]);

        return implode(' ', $names) ?: 'اسم غير محدد';
    }

    /**
     * تنسيق اسم المتوفي
     */
    private function formatDeceasedName($record, $personType = null)
    {
        if ($personType === 'أم' || (!$personType && $this->hasMotherData($record))) {
            $names = array_filter([
                $record->mother_first_name,
                $record->mother_second_name,
                $record->mother_third_name,
                $record->mother_last_name
            ]);
        } else {
            $names = array_filter([
                $record->father_first_name,
                $record->father_second_name,
                $record->father_third_name,
                $record->father_last_name
            ]);
        }

        return implode(' ', $names) ?: 'اسم غير محدد';
    }

    /**
     * تحديد نوع الشخص المتوفي (أب أم أم)
     */
    private function determineDeceasedPerson($record, $query)
    {
        $queryLower = strtolower($query);

        // فحص بيانات الأم
        $motherName = strtolower(implode(' ', array_filter([
            $record->mother_first_name,
            $record->mother_second_name,
            $record->mother_third_name,
            $record->mother_last_name
        ])));

        // فحص بيانات الأب
        $fatherName = strtolower(implode(' ', array_filter([
            $record->father_first_name,
            $record->father_second_name,
            $record->father_third_name,
            $record->father_last_name
        ])));

        // إذا كان البحث يطابق بيانات الأم أكثر
        if (strpos($motherName, $queryLower) !== false && strpos($fatherName, $queryLower) === false) {
            return 'أم';
        }

        // إذا كان البحث يطابق رقم هوية الأم
        if (strpos($record->mother_id ?? '', $query) !== false) {
            return 'أم';
        }

        return 'أب'; // افتراضي
    }

    /**
     * الحصول على تاريخ الوفاة
     */
    private function getDeathDate($record, $personType)
    {
        if ($personType === 'أم') {
            return $record->mother_death_date ?? 'غير محدد';
        } else {
            return $record->father_death_date ?? 'غير محدد';
        }
    }

    /**
     * فحص وجود بيانات الأم
     */
    private function hasMotherData($record)
    {
        return !empty($record->mother_first_name) || !empty($record->mother_id);
    }

    /**
     * استخراج الكلمات من نص البحث
     */
    private function extractSearchWords($query)
    {
        // تنظيف النص وتقسيمه إلى كلمات
        $words = preg_split('/\s+/', trim($query));

        // للبحث الدقيق، نحتفظ بجميع الكلمات حتى لو كانت قصيرة
        $words = array_filter($words, function($word) {
            return strlen(trim($word)) >= 2; // تقليل الحد الأدنى لطول الكلمة
        });

        // للبحث الدقيق، نأخذ كل الكلمات إذا كانت أقل من 8 كلمات
        if (count($words) <= 8) {
            return $words;
        }

        // إذا كان النص طويل جداً، خذ فقط أول 8 كلمات
        return array_slice($words, 0, 8);
    }

    /**
     * حساب درجة الصلة للسجل الرئيسي
     */
    private function calculateRelevance($query, $record)
    {
        $score = 0;
        $queryLower = strtolower($query);
        $words = $this->extractSearchWords($query);

        // البحث في الاسم الكامل
        $fullName = strtolower($this->formatFullName($record));

        // نقاط عالية جداً للتطابق الكامل أو شبه الكامل
        if ($fullName === $queryLower) {
            $score += 100; // تطابق كامل
        } elseif (strpos($fullName, $queryLower) === 0) {
            $score += 80; // يبدأ بالنص
        } elseif (strpos($fullName, $queryLower) !== false) {
            $score += 60; // يحتوي على النص
        }

        // فحص التطابق في كل عمود منفصل
        $individualColumns = [
            $record->data_first_name ?? '',
            $record->data_father_name ?? '',
            $record->data_grand_father_name ?? '',
            $record->data_family_name ?? ''
        ];

        foreach ($individualColumns as $column) {
            $columnLower = strtolower($column);
            if ($columnLower === $queryLower) {
                $score += 50; // تطابق كامل في عمود واحد
            }
        }

        // نقاط للكلمات المنفصلة (فقط إذا لم يكن هناك تطابق كامل)
        if ($score < 50) {
            $foundWords = 0;
            foreach ($words as $word) {
                $wordLower = strtolower($word);
                if (strpos($fullName, $wordLower) !== false) {
                    $foundWords++;
                    $score += 8;
                }
            }

            // مكافأة إضافية إذا وجدت معظم الكلمات
            if (count($words) > 0 && $foundWords >= count($words) * 0.7) {
                $score += 15;
            }
        }

        // نقاط عالية للأرقام (تطابق دقيق)
        if (strpos($record->data_id_number ?? '', $query) !== false) {
            $score += 90;
        }
        if (strpos($record->file_id_number ?? '', $query) !== false) {
            $score += 90;
        }

        return $score;
    }

    /**
     * حساب درجة الصلة لأفراد الأسرة
     */
    private function calculateFamilyRelevance($query, $record)
    {
        $score = 0;
        $queryLower = strtolower($query);
        $words = $this->extractSearchWords($query);

        $fullName = strtolower($this->formatFamilyMemberName($record));

        // نقاط عالية للتطابق الكامل أو شبه الكامل
        if ($fullName === $queryLower) {
            $score += 100; // تطابق كامل
        } elseif (strpos($fullName, $queryLower) === 0) {
            $score += 80; // يبدأ بالنص
        } elseif (strpos($fullName, $queryLower) !== false) {
            $score += 60; // يحتوي على النص
        }

        // نقاط للكلمات المنفصلة
        foreach ($words as $word) {
            $wordLower = strtolower($word);
            if (strpos($fullName, $wordLower) !== false) {
                $score += 30;
            }

            // فحص كل عمود منفصل
            if (strpos(strtolower($record->first_name ?? ''), $wordLower) !== false) {
                $score += 8;
            }
            if (strpos(strtolower($record->second_name ?? ''), $wordLower) !== false) {
                $score += 8;
            }
            if (strpos(strtolower($record->third_name ?? ''), $wordLower) !== false) {
                $score += 8;
            }
            if (strpos(strtolower($record->last_name ?? ''), $wordLower) !== false) {
                $score += 8;
            }
        }

        // نقاط عالية للأرقام (تطابق دقيق)
        if (strpos($record->registration_id ?? '', $query) !== false) {
            $score += 90;
        }

        if (strpos($record->person_id ?? '', $query) !== false) {
            $score += 90;
        }

        return $score;
    }

    /**
     * حساب درجة الصلة للمتوفين
     */
    private function calculateDeceasedRelevance($query, $record)
    {
        $score = 0;
        $queryLower = strtolower($query);
        $words = $this->extractSearchWords($query);

        // فحص اسم الأب الكامل
        $fatherName = strtolower($this->formatDeceasedName($record, 'أب'));
        if ($fatherName === $queryLower) {
            $score += 100; // تطابق كامل
        } elseif (strpos($fatherName, $queryLower) === 0) {
            $score += 80; // يبدأ بالنص
        } elseif (strpos($fatherName, $queryLower) !== false) {
            $score += 60; // يحتوي على النص
        }

        // فحص اسم الأم الكامل
        $motherName = strtolower($this->formatDeceasedName($record, 'أم'));
        if ($motherName === $queryLower) {
            $score += 100; // تطابق كامل
        } elseif (strpos($motherName, $queryLower) === 0) {
            $score += 80; // يبدأ بالنص
        } elseif (strpos($motherName, $queryLower) !== false) {
            $score += 60; // يحتوي على النص
        }

        // نقاط للكلمات المنفصلة
        foreach ($words as $word) {
            $wordLower = strtolower($word);

            // فحص أعمدة الأب
            if (strpos(strtolower($record->father_first_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
            if (strpos(strtolower($record->father_second_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
            if (strpos(strtolower($record->father_third_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
            if (strpos(strtolower($record->father_last_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }

            // فحص أعمدة الأم
            if (strpos(strtolower($record->mother_first_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
            if (strpos(strtolower($record->mother_second_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
            if (strpos(strtolower($record->mother_third_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
            if (strpos(strtolower($record->mother_last_name ?? ''), $wordLower) !== false) {
                $score += 25;
            }
        }

        // نقاط عالية للأرقام (تطابق دقيق)
        if (strpos($record->re_file_id ?? '', $query) !== false) {
            $score += 90;
        }

        if (strpos($record->father_id ?? '', $query) !== false) {
            $score += 90;
        }

        if (strpos($record->mother_id ?? '', $query) !== false) {
            $score += 90;
        }

        return $score;
    }

    /**
     * ترتيب وتحديد النتائج
     */
    private function sortAndLimitResults($results, $query)
    {
        // ترتيب حسب درجة الصلة
        usort($results, function ($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });

        // إذا كان النص طويل (أكثر من 3 كلمات)، كن أكثر انتقائية
        $words = $this->extractSearchWords($query);
        $minRelevance = count($words) > 3 ? 50 : 30;

        // فلترة النتائج ذات الصلة العالية فقط
        $highRelevanceResults = array_filter($results, function($result) use ($minRelevance) {
            return $result['relevance'] >= $minRelevance;
        });

        // إذا لم توجد نتائج ذات صلة عالية، خذ أفضل نتيجة واحدة فقط
        if (empty($highRelevanceResults) && !empty($results)) {
            $highRelevanceResults = array_slice($results, 0, 1);
        }

        // تحديد النتائج إلى 5 نتائج كحد أقصى للنصوص الطويلة، 3 للقصيرة
        $maxResults = count($words) > 3 ? 3 : 5;
        return array_slice($highRelevanceResults, 0, $maxResults);
    }
}
