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
        // تقسيم النص إلى كلمات للبحث الذكي
        $searchWords = array_filter(array_map('trim', explode(' ', $query)));

        // تحديد إذا كان البحث برقم (رقم ملف أو هوية أو هاتف)
        $isNumericSearch = is_numeric(str_replace(' ', '', $query));

        return Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus',
            'city'
        ])
        ->where(function($q) use ($query, $searchWords, $isNumericSearch) {

            // إذا كان البحث برقم فقط
            if ($isNumericSearch) {
                $numericQuery = str_replace(' ', '', $query);
                $q->where('file_id_number', $numericQuery)
                  ->orWhere('data_id_number', $numericQuery)
                  ->orWhere('data_phone_number', 'LIKE', "%{$numericQuery}%")
                  ->orWhere('data_alt_phone_number', 'LIKE', "%{$numericQuery}%");
            }
            // إذا كان البحث بنص (اسم)
            else {
                // البحث الدقيق بالاسم الكامل
                $q->whereRaw("CONCAT_WS(' ', data_first_name, data_father_name, data_grand_father_name, data_family_name) LIKE ?", ["%{$query}%"]);

                // البحث بكلمة واحدة - يجب أن تطابق كلمة كاملة من الاسم
                if (count($searchWords) == 1) {
                    $word = $searchWords[0];
                    $q->orWhere(function($subQ) use ($word) {
                        $subQ->where('data_first_name', $word)
                             ->orWhere('data_father_name', $word)
                             ->orWhere('data_grand_father_name', $word)
                             ->orWhere('data_family_name', $word);
                    });
                }

                // البحث بكلمتين - تطابق دقيق
                elseif (count($searchWords) == 2) {
                    $word1 = $searchWords[0];
                    $word2 = $searchWords[1];

                    $q->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('data_first_name', $word1)
                              ->where('data_father_name', $word2);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('data_first_name', $word1)
                              ->where('data_family_name', $word2);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('data_father_name', $word1)
                              ->where('data_family_name', $word2);
                    });
                }

                // البحث بثلاث كلمات أو أكثر
                elseif (count($searchWords) >= 3) {
                    $word1 = $searchWords[0];
                    $word2 = $searchWords[1];
                    $word3 = $searchWords[2];
                    $word4 = isset($searchWords[3]) ? $searchWords[3] : null;

                    // تطابق 3 كلمات
                    $q->orWhere(function($nameQ) use ($word1, $word2, $word3) {
                        $nameQ->where('data_first_name', $word1)
                              ->where('data_father_name', $word2)
                              ->where('data_grand_father_name', $word3);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2, $word3) {
                        $nameQ->where('data_first_name', $word1)
                              ->where('data_father_name', $word2)
                              ->where('data_family_name', $word3);
                    });

                    // تطابق 4 كلمات (الاسم الكامل)
                    if ($word4) {
                        $q->orWhere(function($nameQ) use ($word1, $word2, $word3, $word4) {
                            $nameQ->where('data_first_name', $word1)
                                  ->where('data_father_name', $word2)
                                  ->where('data_grand_father_name', $word3)
                                  ->where('data_family_name', $word4);
                        });
                    }
                }
            }
        })
        ->whereHas('requestStatus', function($q) {
            $q->where('description', 'مقبول');
        })
        ->limit(20) // تقليل عدد النتائج لـ 20 فقط
        ->get();
    }

    /**
     * البحث في جدول الأيتام وأفراد الأسرة
     */
    private function searchInRePeopleTable($query)
    {
        // تقسيم النص إلى كلمات للبحث الذكي
        $searchWords = array_filter(array_map('trim', explode(' ', $query)));

        // تحديد إذا كان البحث برقم
        $isNumericSearch = is_numeric(str_replace(' ', '', $query));

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
        ->where(function($q) use ($query, $searchWords, $isNumericSearch) {

            // إذا كان البحث برقم فقط
            if ($isNumericSearch) {
                $numericQuery = str_replace(' ', '', $query);
                $q->where('registration_id', $numericQuery)
                  ->orWhere('person_id', $numericQuery);
            }
            // إذا كان البحث بنص (اسم)
            else {
                // البحث الدقيق بالاسم الكامل
                $q->whereRaw("CONCAT_WS(' ', first_name, second_name, third_name, last_name) LIKE ?", ["%{$query}%"]);

                // البحث بكلمة واحدة - يجب أن تطابق كلمة كاملة
                if (count($searchWords) == 1) {
                    $word = $searchWords[0];
                    $q->orWhere(function($subQ) use ($word) {
                        $subQ->where('first_name', $word)
                             ->orWhere('second_name', $word)
                             ->orWhere('third_name', $word)
                             ->orWhere('last_name', $word);
                    });
                }

                // البحث بكلمتين - تطابق دقيق
                elseif (count($searchWords) == 2) {
                    $word1 = $searchWords[0];
                    $word2 = $searchWords[1];

                    $q->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('first_name', $word1)
                              ->where('second_name', $word2);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('first_name', $word1)
                              ->where('last_name', $word2);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('second_name', $word1)
                              ->where('last_name', $word2);
                    });
                }

                // البحث بثلاث كلمات أو أكثر
                elseif (count($searchWords) >= 3) {
                    $word1 = $searchWords[0];
                    $word2 = $searchWords[1];
                    $word3 = $searchWords[2];
                    $word4 = isset($searchWords[3]) ? $searchWords[3] : null;

                    // تطابق 3 كلمات
                    $q->orWhere(function($nameQ) use ($word1, $word2, $word3) {
                        $nameQ->where('first_name', $word1)
                              ->where('second_name', $word2)
                              ->where('third_name', $word3);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2, $word3) {
                        $nameQ->where('first_name', $word1)
                              ->where('second_name', $word2)
                              ->where('last_name', $word3);
                    });

                    // تطابق 4 كلمات (الاسم الكامل)
                    if ($word4) {
                        $q->orWhere(function($nameQ) use ($word1, $word2, $word3, $word4) {
                            $nameQ->where('first_name', $word1)
                                  ->where('second_name', $word2)
                                  ->where('third_name', $word3)
                                  ->where('last_name', $word4);
                        });
                    }
                }
            }
        })
        ->limit(20) // تقليل عدد النتائج لـ 20 فقط
        ->get();
    }

    /**
     * البحث في جدول المتوفين - فقط للملفات المقبولة
     */
    private function searchInDeadPeopleTable($query)
    {
        // تقسيم النص إلى كلمات للبحث الذكي
        $searchWords = array_filter(array_map('trim', explode(' ', $query)));

        // تحديد إذا كان البحث برقم
        $isNumericSearch = is_numeric(str_replace(' ', '', $query));

        return DeadPepole::whereHas('dataRecord', function($q) {
            $q->whereHas('requestStatus', function($subq) {
                $subq->where('description', 'مقبول');
            });
        })
        ->where(function($q) use ($query, $searchWords, $isNumericSearch) {

            // إذا كان البحث برقم فقط
            if ($isNumericSearch) {
                $numericQuery = str_replace(' ', '', $query);
                $q->where('re_file_id', $numericQuery)
                  ->orWhere('father_id', $numericQuery)
                  ->orWhere('mother_id', $numericQuery);
            }
            // إذا كان البحث بنص (اسم)
            else {
                // البحث الدقيق في اسم الأب الكامل
                $q->whereRaw("CONCAT_WS(' ', father_first_name, father_second_name, father_third_name, father_last_name) LIKE ?", ["%{$query}%"])
                  // البحث الدقيق في اسم الأم الكامل
                  ->orWhereRaw("CONCAT_WS(' ', mother_first_name, mother_second_name, mother_third_name, mother_last_name) LIKE ?", ["%{$query}%"]);

                // البحث بكلمة واحدة للأب
                if (count($searchWords) == 1) {
                    $word = $searchWords[0];
                    $q->orWhere(function($subQ) use ($word) {
                        $subQ->where('father_first_name', $word)
                             ->orWhere('father_second_name', $word)
                             ->orWhere('father_third_name', $word)
                             ->orWhere('father_last_name', $word)
                             ->orWhere('mother_first_name', $word)
                             ->orWhere('mother_second_name', $word)
                             ->orWhere('mother_third_name', $word)
                             ->orWhere('mother_last_name', $word);
                    });
                }

                // البحث بكلمتين للأب والأم
                elseif (count($searchWords) == 2) {
                    $word1 = $searchWords[0];
                    $word2 = $searchWords[1];

                    // للأب
                    $q->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('father_first_name', $word1)
                              ->where('father_second_name', $word2);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('father_first_name', $word1)
                              ->where('father_last_name', $word2);
                    })
                    // للأم
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('mother_first_name', $word1)
                              ->where('mother_second_name', $word2);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2) {
                        $nameQ->where('mother_first_name', $word1)
                              ->where('mother_last_name', $word2);
                    });
                }

                // البحث بثلاث كلمات أو أكثر
                elseif (count($searchWords) >= 3) {
                    $word1 = $searchWords[0];
                    $word2 = $searchWords[1];
                    $word3 = $searchWords[2];
                    $lastName = end($searchWords);

                    // للأب - 3 كلمات
                    $q->orWhere(function($nameQ) use ($word1, $word2, $word3) {
                        $nameQ->where('father_first_name', $word1)
                              ->where('father_second_name', $word2)
                              ->where('father_third_name', $word3);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2, $lastName) {
                        $nameQ->where('father_first_name', $word1)
                              ->where('father_second_name', $word2)
                              ->where('father_last_name', $lastName);
                    })
                    // للأم - 3 كلمات
                    ->orWhere(function($nameQ) use ($word1, $word2, $word3) {
                        $nameQ->where('mother_first_name', $word1)
                              ->where('mother_second_name', $word2)
                              ->where('mother_third_name', $word3);
                    })
                    ->orWhere(function($nameQ) use ($word1, $word2, $lastName) {
                        $nameQ->where('mother_first_name', $word1)
                              ->where('mother_second_name', $word2)
                              ->where('mother_last_name', $lastName);
                    });

                    // إذا كان هناك 4 كلمات - اسم كامل
                    if (count($searchWords) >= 4) {
                        $word4 = count($searchWords) >= 4 ? $searchWords[3] : $lastName;

                        $q->orWhere(function($nameQ) use ($word1, $word2, $word3, $word4) {
                            $nameQ->where('father_first_name', $word1)
                                  ->where('father_second_name', $word2)
                                  ->where('father_third_name', $word3)
                                  ->where('father_last_name', $word4);
                        })
                        ->orWhere(function($nameQ) use ($word1, $word2, $word3, $word4) {
                            $nameQ->where('mother_first_name', $word1)
                                  ->where('mother_second_name', $word2)
                                  ->where('mother_third_name', $word3)
                                  ->where('mother_last_name', $word4);
                        });
                    }
                }
            }
        })
        ->limit(20)
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
