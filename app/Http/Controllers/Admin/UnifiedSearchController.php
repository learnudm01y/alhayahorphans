<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnifiedSearchController extends Controller
{
    /**
     * البحث الموحد في جميع الجداول
     */
    public function search(Request $request)
    {
        try {
            $searchQuery = trim($request->input('query'));

            if (empty($searchQuery)) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى إدخال نص للبحث',
                    'results' => []
                ]);
            }

            $results = [];
            $totalCount = 0;

            // 1. البحث في جدول المعيلين (data)
            $breadwinners = $this->searchInDataTable($searchQuery);
            if ($breadwinners->count() > 0) {
                $results['breadwinners'] = $breadwinners->map(function($record) {
                    return $this->formatDataRecord($record);
                });
                $totalCount += $breadwinners->count();
            }

            // 2. البحث في جدول الأيتام وأفراد الأسرة (re_people)
            $familyMembers = $this->searchInRePeopleTable($searchQuery);
            if ($familyMembers->count() > 0) {
                $results['family_members'] = $familyMembers->map(function($record) {
                    return $this->formatRePeopleRecord($record);
                });
                $totalCount += $familyMembers->count();
            }

            // 3. البحث في جدول المتوفين (dead_people)
            $deceased = $this->searchInDeadPeopleTable($searchQuery);
            if ($deceased->count() > 0) {
                $results['deceased'] = $deceased->map(function($record) {
                    return $this->formatDeadPeopleRecord($record);
                });
                $totalCount += $deceased->count();
            }

            return response()->json([
                'success' => true,
                'total_count' => $totalCount,
                'query' => $searchQuery,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث الموحد:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث: ' . $e->getMessage(),
                'results' => []
            ], 500);
        }
    }

    /**
     * البحث في جدول المعيلين
     */
    private function searchInDataTable($query)
    {
        return Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus',
            'city'
        ])
        ->where(function($q) use ($query) {
            // البحث في رقم الملف
            $q->where('file_id_number', 'LIKE', "%{$query}%")
              // البحث في رقم الهوية
              ->orWhere('data_id_number', 'LIKE', "%{$query}%")
              // البحث في رقم الجوال
              ->orWhere('data_phone_number', 'LIKE', "%{$query}%")
              ->orWhere('data_alt_phone_number', 'LIKE', "%{$query}%")
              // البحث في الأسماء
              ->orWhere('data_first_name', 'LIKE', "%{$query}%")
              ->orWhere('data_father_name', 'LIKE', "%{$query}%")
              ->orWhere('data_grand_father_name', 'LIKE', "%{$query}%")
              ->orWhere('data_family_name', 'LIKE', "%{$query}%")
              // البحث في الاسم الكامل (concatenated)
              ->orWhereRaw("CONCAT_WS(' ', data_first_name, data_father_name, data_grand_father_name, data_family_name) LIKE ?", ["%{$query}%"])
              // البحث بدون مسافات
              ->orWhereRaw("REPLACE(CONCAT(data_first_name, data_father_name, data_grand_father_name, data_family_name), ' ', '') LIKE ?", ["%{$query}%"]);
        })
        ->whereHas('requestStatus', function($q) {
            $q->where('description', 'مقبول');
        })
        ->limit(50)
        ->get();
    }

    /**
     * البحث في جدول الأيتام وأفراد الأسرة
     */
    private function searchInRePeopleTable($query)
    {
        return RePeople::with([
            'healthStatus',
            'guaranteeType',
            'dataRecord'
        ])
        ->whereHas('dataRecord', function($q) {
            $q->whereHas('requestStatus', function($subq) {
                $subq->where('description', 'مقبول');
            });
        })
        ->where(function($q) use ($query) {
            // البحث في رقم الملف
            $q->where('registration_id', 'LIKE', "%{$query}%")
              // البحث في رقم الهوية
              ->orWhere('person_id', 'LIKE', "%{$query}%")
              // البحث في الأسماء
              ->orWhere('first_name', 'LIKE', "%{$query}%")
              ->orWhere('second_name', 'LIKE', "%{$query}%")
              ->orWhere('third_name', 'LIKE', "%{$query}%")
              ->orWhere('last_name', 'LIKE', "%{$query}%")
              // البحث في الاسم الكامل
              ->orWhereRaw("CONCAT_WS(' ', first_name, second_name, third_name, last_name) LIKE ?", ["%{$query}%"])
              // البحث بدون مسافات
              ->orWhereRaw("REPLACE(CONCAT(first_name, second_name, third_name, last_name), ' ', '') LIKE ?", ["%{$query}%"]);
        })
        ->limit(50)
        ->get();
    }

    /**
     * البحث في جدول المتوفين - فقط للملفات المقبولة
     */
    private function searchInDeadPeopleTable($query)
    {
        return DeadPepole::whereHas('dataRecord', function($q) {
            $q->whereHas('requestStatus', function($subq) {
                $subq->where('description', 'مقبول');
            });
        })
        ->where(function($q) use ($query) {
            // البحث في رقم الملف
            $q->where('re_file_id', 'LIKE', "%{$query}%")
              // البحث في رقم هوية الأب
              ->orWhere('father_id', 'LIKE', "%{$query}%")
              // البحث في أسماء الأب
              ->orWhere('father_first_name', 'LIKE', "%{$query}%")
              ->orWhere('father_second_name', 'LIKE', "%{$query}%")
              ->orWhere('father_third_name', 'LIKE', "%{$query}%")
              ->orWhere('father_last_name', 'LIKE', "%{$query}%")
              // البحث في اسم الأب الكامل
              ->orWhereRaw("CONCAT_WS(' ', father_first_name, father_second_name, father_third_name, father_last_name) LIKE ?", ["%{$query}%"])
              // البحث في رقم هوية الأم
              ->orWhere('mother_id', 'LIKE', "%{$query}%")
              // البحث في أسماء الأم
              ->orWhere('mother_first_name', 'LIKE', "%{$query}%")
              ->orWhere('mother_second_name', 'LIKE', "%{$query}%")
              ->orWhere('mother_third_name', 'LIKE', "%{$query}%")
              ->orWhere('mother_last_name', 'LIKE', "%{$query}%")
              // البحث في اسم الأم الكامل
              ->orWhereRaw("CONCAT_WS(' ', mother_first_name, mother_second_name, mother_third_name, mother_last_name) LIKE ?", ["%{$query}%"]);
        })
        ->limit(50)
        ->get();
    }

    /**
     * تنسيق سجل من جدول data
     */
    private function formatDataRecord($record)
    {
        return [
            'id' => $record->id,
            'record_type' => 'data',
            'person_type' => 'breadwinner',
            'file_id' => $record->file_id_number,
            'identity_number' => $record->data_id_number,
            'full_name' => trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}"),
            'birth_date' => $record->data_birth_date,
            'phone' => $record->data_phone_number,
            'section' => optional($record->section)->description,
            'health_status' => optional($record->healthStatus)->description,
            'city' => optional($record->city)->city,
            'guardian_name' => null,
            'guardian_id' => null,
        ];
    }

    /**
     * تنسيق سجل من جدول re_people
     */
    private function formatRePeopleRecord($record)
    {
        // تحديد نوع الشخص
        $personType = 'family_member';
        if ($record->person_type_of_guarantee) {
            $guaranteeType = optional($record->guaranteeType)->description ?? '';
            if (stripos($guaranteeType, 'يتيم') !== false) {
                $personType = 'orphan';
            }
        }

        // جلب معلومات المعيل
        $guardianName = null;
        $guardianId = null;
        if ($record->dataRecord) {
            $guardianName = trim("{$record->dataRecord->data_first_name} {$record->dataRecord->data_father_name} {$record->dataRecord->data_grand_father_name} {$record->dataRecord->data_family_name}");
            $guardianId = $record->dataRecord->data_id_number;
        }

        return [
            'id' => $record->person_id ?? $record->registration_id,
            'record_type' => 're_people',
            'person_type' => $personType,
            'file_id' => $record->registration_id,
            'identity_number' => $record->person_id,
            'full_name' => trim("{$record->first_name} {$record->second_name} {$record->third_name} {$record->last_name}"),
            'birth_date' => $record->person_birth_date,
            'phone' => null,
            'section' => null,
            'health_status' => optional($record->healthStatus)->description,
            'city' => null,
            'guardian_name' => $guardianName,
            'guardian_id' => $guardianId,
        ];
    }

    /**
     * تنسيق سجل من جدول dead_people
     */
    private function formatDeadPeopleRecord($record)
    {
        $results = [];

        // إضافة الأب
        if (!empty($record->father_id)) {
            $results[] = [
                'id' => 'father_' . $record->re_file_id,
                'record_type' => 'dead_people',
                'person_type' => 'deceased_father',
                'file_id' => $record->re_file_id,
                'identity_number' => $record->father_id,
                'full_name' => trim("{$record->father_first_name} {$record->father_second_name} {$record->father_third_name} {$record->father_last_name}"),
                'birth_date' => null,
                'phone' => null,
                'section' => null,
                'health_status' => 'متوفي',
                'city' => null,
                'guardian_name' => null,
                'guardian_id' => null,
                'death_date' => $record->father_death_date,
            ];
        }

        // إضافة الأم
        if (!empty($record->mother_id)) {
            $results[] = [
                'id' => 'mother_' . $record->re_file_id,
                'record_type' => 'dead_people',
                'person_type' => 'deceased_mother',
                'file_id' => $record->re_file_id,
                'identity_number' => $record->mother_id,
                'full_name' => trim("{$record->mother_first_name} {$record->mother_second_name} {$record->mother_third_name} {$record->mother_last_name}"),
                'birth_date' => null,
                'phone' => null,
                'section' => null,
                'health_status' => 'متوفية',
                'city' => null,
                'guardian_name' => null,
                'guardian_id' => null,
                'death_date' => $record->mother_death_date,
            ];
        }

        return $results;
    }
}
