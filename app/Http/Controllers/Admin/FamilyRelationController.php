<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relation;
use App\Models\Persons;
use App\Models\CategoryOfRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\NormalizedSearchService;

class FamilyRelationController extends Controller
{
    protected $searchService;

    /**
     * مدة التخزين المؤقت (Cache) بالدقائق
     */
    private const CACHE_DURATION = 60;

    public function __construct(NormalizedSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * البحث عن العلاقات العائلية برقم الهوية
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchFamilyRelations(Request $request)
    {
        try {
            $request->validate([
                'id_number' => 'required|string|min:1',
            ]);

            $idNumber = trim($request->input('id_number'));

            // التحقق من صحة رقم الهوية
            $person = $this->findPerson($idNumber);

            if (!$person) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على شخص بهذا الرقم في السجل المدني',
                    'data' => null
                ], 404);
            }

            // الحصول على العلاقات العائلية
            $familyData = $this->getFamilyRelationsOptimized($idNumber);

            return response()->json([
                'success' => true,
                'message' => 'تم العثور على العلاقات العائلية بنجاح',
                'data' => [
                    'person' => $person,
                    'relations' => $familyData['relations'],
                    'family_members' => $familyData['family_members'],
                    'statistics' => $familyData['statistics']
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن العلاقات العائلية: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث عن العلاقات العائلية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن العلاقات العائلية بالاسم
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchFamilyRelationsByName(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|min:2',
            ]);

            $name = trim($request->input('name'));

            // البحث عن الأشخاص المطابقين للاسم
            $persons = $this->searchPersonsByName($name);

            if ($persons->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على أي شخص بهذا الاسم في السجل المدني',
                    'data' => null
                ], 404);
            }

            // إذا كان هناك شخص واحد فقط، جلب علاقاته مباشرة
            if ($persons->count() === 1) {
                $person = $persons->first();
                $familyData = $this->getFamilyRelationsOptimized($person['id_number']);

                return response()->json([
                    'success' => true,
                    'message' => 'تم العثور على العلاقات العائلية بنجاح',
                    'data' => [
                        'person' => $person,
                        'relations' => $familyData['relations'],
                        'family_members' => $familyData['family_members'],
                        'statistics' => $familyData['statistics']
                    ]
                ], 200);
            }

            // إذا كان هناك أكثر من شخص، إرجاع القائمة للاختيار
            return response()->json([
                'success' => true,
                'message' => 'تم العثور على ' . $persons->count() . ' أشخاص مطابقين',
                'data' => [
                    'multiple_results' => true,
                    'persons' => $persons->toArray(),
                    'count' => $persons->count()
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن العلاقات العائلية بالاسم: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث عن العلاقات العائلية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن أشخاص بالاسم (مع التطبيع)
     *
     * @param string $name
     * @return \Illuminate\Support\Collection
     */
    private function searchPersonsByName($name)
    {
        // استخدام خدمة البحث المطبع
        return $this->searchService->searchCivilRegistry($name, 50);
    }

    /**
     * الحصول على العلاقات العائلية بطريقة محسّنة باستخدام الفهارس
     * (بدون بحث عكسي - فقط العلاقات المباشرة)
     *
     * @param string $idNumber
     * @return array
     */
    private function getFamilyRelationsOptimized($idNumber)
    {
        $cacheKey = "family_relations_{$idNumber}";

        return Cache::remember($cacheKey, self::CACHE_DURATION * 60, function () use ($idNumber) {
            $startTime = microtime(true);

            // استعلام محسّن - فقط العلاقات المباشرة (بدون عكسية)
            // استخدام LEFT JOIN لأن بعض CF_ID_RELATIVE قد لا يكون موجوداً في persons (25% من البيانات)
            $directRelations = DB::connection('civilregistry')
                ->table('relations as r')
                ->select(
                    'r.CF_ID_NUM',
                    'r.CF_ID_RELATIVE',
                    'r.CF_RELATIVE_CD',
                    DB::raw('COALESCE(cat.attribute, CONCAT("علاقة ", r.CF_RELATIVE_CD)) as relation_type'),
                    'p.CI_FIRST_ARB',
                    'p.CI_FATHER_ARB',
                    'p.CI_GRAND_FATHER_ARB',
                    'p.CI_FAMILY_ARB',
                    'p.CI_BIRTH_DT',
                    'p.CI_SEX_CD',
                    'p.CI_DEAD_DT'
                )
                ->leftJoin('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
                ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
                ->where('r.CF_ID_NUM', $idNumber)
                ->get();

            // تنظيم البيانات بشكل هرمي
            $familyMembers = [];
            $relationTypes = [];

            foreach ($directRelations as $relation) {
                $memberId = $relation->CF_ID_RELATIVE;

                // إذا كانت البيانات موجودة في persons، استخدمها
                // إذا كانت مفقودة، اعرض رقم الهوية فقط مع ملاحظة
                if (!empty($relation->CI_FIRST_ARB)) {
                    $fullName = trim(implode(' ', [
                        $relation->CI_FIRST_ARB ?? '',
                        $relation->CI_FATHER_ARB ?? '',
                        $relation->CI_GRAND_FATHER_ARB ?? '',
                        $relation->CI_FAMILY_ARB ?? ''
                    ]));
                } else {
                    // البيانات مفقودة من persons
                    $fullName = "رقم الهوية: {$memberId} (بيانات غير متوفرة)";
                }

                $age = $this->calculateAge($relation->CI_BIRTH_DT);
                $isAlive = empty($relation->CI_DEAD_DT);

                $member = [
                    'id_number' => $memberId,
                    'full_name' => $fullName,
                    'first_name' => $relation->CI_FIRST_ARB ?? null,
                    'father_name' => $relation->CI_FATHER_ARB ?? null,
                    'grand_father_name' => $relation->CI_GRAND_FATHER_ARB ?? null,
                    'family_name' => $relation->CI_FAMILY_ARB ?? null,
                    'relation_type' => $relation->relation_type,
                    'relation_code' => $relation->CF_RELATIVE_CD,
                    'birth_date' => $relation->CI_BIRTH_DT,
                    'age' => $age,
                    'gender' => $this->getGenderText($relation->CI_SEX_CD),
                    'is_alive' => $isAlive,
                    'status' => $isAlive ? 'حي' : 'متوفي',
                    'data_available' => !empty($relation->CI_FIRST_ARB), // علامة توفر البيانات
                    'children' => [] // سيتم ملؤها بأبناء هذا الشخص
                ];

                // جلب أبناء هذا الشخص بشكل هرمي (فقط إذا كانت بياناته متوفرة)
                if (!empty($relation->CI_FIRST_ARB)) {
                    $member['children'] = $this->getDirectChildren($memberId);
                }

                $familyMembers[] = $member;

                // إحصائيات أنواع العلاقات
                $relationType = $relation->relation_type;
                if (!isset($relationTypes[$relationType])) {
                    $relationTypes[$relationType] = 0;
                }
                $relationTypes[$relationType]++;
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'relations' => $directRelations->toArray(),
                'family_members' => $familyMembers,
                'statistics' => [
                    'total_relations' => count($directRelations),
                    'direct_relations' => count($directRelations),
                    'relation_types' => $relationTypes,
                    'execution_time_ms' => $executionTime
                ]
            ];
        });
    }

    /**
     * الحصول على الأبناء المباشرين لشخص معين
     *
     * @param string $idNumber
     * @return array
     */
    private function getDirectChildren($idNumber)
    {
        $children = DB::connection('civilregistry')
            ->table('relations as r')
            ->select(
                'r.CF_ID_RELATIVE',
                'r.CF_RELATIVE_CD',
                DB::raw('COALESCE(cat.attribute, CONCAT("علاقة ", r.CF_RELATIVE_CD)) as relation_type'),
                'p.CI_FIRST_ARB',
                'p.CI_FATHER_ARB',
                'p.CI_GRAND_FATHER_ARB',
                'p.CI_FAMILY_ARB',
                'p.CI_BIRTH_DT',
                'p.CI_SEX_CD',
                'p.CI_DEAD_DT'
            )
            ->leftJoin('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
            ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
            ->where('r.CF_ID_NUM', $idNumber)
            ->get();

        $childrenArray = [];
        foreach ($children as $child) {
            // التعامل مع البيانات المفقودة
            if (!empty($child->CI_FIRST_ARB)) {
                $fullName = trim(implode(' ', [
                    $child->CI_FIRST_ARB ?? '',
                    $child->CI_FATHER_ARB ?? '',
                    $child->CI_GRAND_FATHER_ARB ?? '',
                    $child->CI_FAMILY_ARB ?? ''
                ]));
            } else {
                $fullName = "رقم الهوية: {$child->CF_ID_RELATIVE} (بيانات غير متوفرة)";
            }

            $childrenArray[] = [
                'id_number' => $child->CF_ID_RELATIVE,
                'full_name' => $fullName,
                'relation_type' => $child->relation_type,
                'age' => $this->calculateAge($child->CI_BIRTH_DT),
                'gender' => $this->getGenderText($child->CI_SEX_CD),
                'is_alive' => empty($child->CI_DEAD_DT),
                'status' => empty($child->CI_DEAD_DT) ? 'حي' : 'متوفي',
                'data_available' => !empty($child->CI_FIRST_ARB)
            ];
        }

        return $childrenArray;
    }

    /**
     * البحث عن شخص برقم الهوية
     *
     * @param string $idNumber
     * @return object|null
     */
    private function findPerson($idNumber)
    {
        $cacheKey = "person_{$idNumber}";

        return Cache::remember($cacheKey, self::CACHE_DURATION * 60, function () use ($idNumber) {
            $person = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $idNumber)
                ->first();

            if ($person) {
                $fullName = trim(implode(' ', [
                    $person->CI_FIRST_ARB ?? '',
                    $person->CI_FATHER_ARB ?? '',
                    $person->CI_GRAND_FATHER_ARB ?? '',
                    $person->CI_FAMILY_ARB ?? ''
                ]));

                return [
                    'id_number' => $person->CI_ID_NUM,
                    'full_name' => $fullName,
                    'first_name' => $person->CI_FIRST_ARB,
                    'father_name' => $person->CI_FATHER_ARB,
                    'grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                    'family_name' => $person->CI_FAMILY_ARB,
                    'birth_date' => $person->CI_BIRTH_DT,
                    'age' => $this->calculateAge($person->CI_BIRTH_DT),
                    'gender' => $this->getGenderText($person->CI_SEX_CD),
                    'is_alive' => empty($person->CI_DEAD_DT),
                    'status' => empty($person->CI_DEAD_DT) ? 'حي' : 'متوفي'
                ];
            }

            return null;
        });
    }

    /**
     * الحصول على شجرة العائلة الكاملة
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFamilyTree(Request $request)
    {
        try {
            $request->validate([
                'id_number' => 'required|string|min:1',
                'depth' => 'nullable|integer|min:1|max:5'
            ]);

            $idNumber = trim($request->input('id_number'));
            $depth = $request->input('depth', 2);

            $familyTree = $this->buildFamilyTree($idNumber, $depth);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء شجرة العائلة بنجاح',
                'data' => $familyTree
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في إنشاء شجرة العائلة: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء شجرة العائلة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * بناء شجرة العائلة بعمق محدد
     *
     * @param string $idNumber
     * @param int $depth
     * @param int $currentDepth
     * @param array $visited
     * @return array
     */
    private function buildFamilyTree($idNumber, $depth, $currentDepth = 0, &$visited = [])
    {
        if ($currentDepth >= $depth || in_array($idNumber, $visited)) {
            return [];
        }

        $visited[] = $idNumber;

        $person = $this->findPerson($idNumber);
        if (!$person) {
            return [];
        }

        $familyData = $this->getFamilyRelationsOptimized($idNumber);

        $tree = [
            'person' => $person,
            'children' => []
        ];

        foreach ($familyData['family_members'] as $member) {
            $tree['children'][] = $this->buildFamilyTree(
                $member['id_number'],
                $depth,
                $currentDepth + 1,
                $visited
            );
        }

        return $tree;
    }

    /**
     * الحصول على إحصائيات العلاقات
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRelationsStatistics()
    {
        try {
            $cacheKey = "relations_statistics";

            $stats = Cache::remember($cacheKey, self::CACHE_DURATION * 60, function () {
                return [
                    'total_relations' => DB::connection('civilregistry')->table('relations')->count(),
                    'total_persons' => DB::connection('civilregistry')->table('persons')->count(),
                    'relation_types' => DB::connection('civilregistry')
                        ->table('category_of_relations')
                        ->select('id', 'attribute')
                        ->get()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب إحصائيات العلاقات: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات'
            ], 500);
        }
    }

    /**
     * مسح الكاش الخاص بالعلاقات
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearRelationsCache()
    {
        try {
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'تم مسح الكاش بنجاح'
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في مسح الكاش: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء مسح الكاش'
            ], 500);
        }
    }

    /**
     * حساب العمر من تاريخ الميلاد
     *
     * @param string|null $birthDate
     * @return string
     */
    private function calculateAge($birthDate)
    {
        if (empty($birthDate)) {
            return 'غير محدد';
        }

        try {
            $birth = new \DateTime($birthDate);
            $today = new \DateTime();
            $age = $today->diff($birth)->y;
            return $age . ' سنة';
        } catch (\Exception $e) {
            return 'غير محدد';
        }
    }

    /**
     * تحويل رمز الجنس إلى نص
     *
     * @param int|null $sexCode
     * @return string
     */
    private function getGenderText($sexCode)
    {
        switch ($sexCode) {
            case 1:
                return 'ذكر';
            case 2:
                return 'أنثى';
            default:
                return 'غير محدد';
        }
    }
}
