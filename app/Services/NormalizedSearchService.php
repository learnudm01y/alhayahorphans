<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class NormalizedSearchService
{
    /**
     * بناء SQL expression لتطبيع النص العربي مع المسافات
     * متوافق مع MySQL 5.7+
     */
    public function buildNormalizationSQL(string $column): string
    {
        // تطبيع الحروف العربية والمسافات في SQL
        // استخدام TRIM و REPLACE لتوافق أفضل مع MySQL
        return "TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', ''),
                '  ', ' '), '   ', ' '))";
    }

    /**
     * بناء SQL expression لإزالة كل المسافات (للكلمات المركبة)
     */
    public function buildNoSpacesSQL(string $column): string
    {
        return "REPLACE(TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', '')),
                ' ', '')";
    }

    /**
     * البحث في السجل المدني مع التطبيع - محسّن للسرعة والدقة
     */
    public function searchCivilRegistry(string $searchTerm, int $limit = 50)
    {
        // إذا كان رقم هوية، البحث المباشر (أسرع)
        if (is_numeric($searchTerm) && strlen($searchTerm) >= 9) {
            return $this->searchByIdNumberOptimized($searchTerm, $limit);
        }

        $normalizedTerm = normalizeArabicText($searchTerm);
        $words = preg_split('/\s+/', trim($normalizedTerm));

        $query = DB::connection('civilregistry')->table('persons');

        // استخدام البحث المحسّن للكلمات المتعددة
        if (count($words) >= 2) {
            return $this->multiWordCivilRegistrySearchOptimized($query, $normalizedTerm, $words, $limit);
        }

        // البحث العادي للكلمة الواحدة
        return $this->singleWordCivilRegistrySearchOptimized($query, $normalizedTerm, $limit);
    }

    /**
     * البحث برقم الهوية المحسّن (يستخدم INDEX)
     */
    private function searchByIdNumberOptimized(string $idNumber, int $limit)
    {
        // البحث الدقيق أولاً (أسرع مع UNIQUE INDEX)
        $exact = DB::connection('civilregistry')
            ->table('persons')
            ->select(
                'CI_ID_NUM as id_number',
                'CI_FIRST_ARB',
                'CI_FATHER_ARB',
                'CI_GRAND_FATHER_ARB',
                'CI_FAMILY_ARB',
                'CI_BIRTH_DT',
                'CI_SEX_CD',
                'CI_DEAD_DT'
            )
            ->where('CI_ID_NUM', $idNumber)
            ->first();

        if ($exact) {
            return collect([$exact]);
        }

        // إذا لم يجد نتيجة دقيقة، البحث بالبداية
        if (strlen($idNumber) < 9) {
            return DB::connection('civilregistry')
                ->table('persons')
                ->select(
                    'CI_ID_NUM as id_number',
                    'CI_FIRST_ARB',
                    'CI_FATHER_ARB',
                    'CI_GRAND_FATHER_ARB',
                    'CI_FAMILY_ARB',
                    'CI_BIRTH_DT',
                    'CI_SEX_CD',
                    'CI_DEAD_DT'
                )
                ->where('CI_ID_NUM', 'LIKE', $idNumber . '%')
                ->limit($limit)
                ->get();
        }

        return collect([]);
    }

    /**
     * البحث المحسّن لعدة كلمات (يستخدم INDEX للسرعة)
     * يدعم الأسماء المركبة مثل "عبد الناصر" و "أبو محمد"
     * يستخدم الأعمدة المطبّعة مسبقاً للسرعة القصوى
     */
    private function multiWordCivilRegistrySearchOptimized($query, string $searchTerm, array $words, int $limit)
    {
        // دمج الكلمات المركبة الشائعة
        $mergedWords = $this->mergeCompoundNames($words);

        $firstWord = $mergedWords[0] ?? '';
        $secondWord = $mergedWords[1] ?? '';
        $thirdWord = $mergedWords[2] ?? '';
        $fourthWord = $mergedWords[3] ?? '';

        return $query->select(
            'CI_ID_NUM as id_number',
            'CI_FIRST_ARB',
            'CI_FATHER_ARB',
            'CI_GRAND_FATHER_ARB',
            'CI_FAMILY_ARB',
            'CI_BIRTH_DT',
            'CI_SEX_CD',
            'CI_DEAD_DT'
        )
        ->where(function($q) use ($firstWord, $secondWord, $thirdWord, $fourthWord, $words) {
            // البحث باستخدام الأعمدة المطبّعة (أسرع بكثير!)
            if ($fourthWord) {
                // 4 كلمات: الاسم + الأب + الجد + العائلة
                $q->where(function($sq) use ($firstWord, $secondWord, $thirdWord, $fourthWord) {
                    $sq->where('CI_FIRST_ARB_NORMALIZED', 'LIKE', $firstWord . '%')
                       ->where('CI_FATHER_ARB_NORMALIZED', 'LIKE', $secondWord . '%')
                       ->where('CI_GRAND_FATHER_ARB_NORMALIZED', 'LIKE', $thirdWord . '%')
                       ->where('CI_FAMILY_ARB_NORMALIZED', 'LIKE', $fourthWord . '%');
                });
            } elseif ($thirdWord) {
                // 3 كلمات: الاسم + الأب + (الجد أو العائلة)
                $q->where(function($sq) use ($firstWord, $secondWord, $thirdWord) {
                    $sq->where('CI_FIRST_ARB_NORMALIZED', 'LIKE', $firstWord . '%')
                       ->where('CI_FATHER_ARB_NORMALIZED', 'LIKE', $secondWord . '%')
                       ->where(function($ssq) use ($thirdWord) {
                           $ssq->where('CI_GRAND_FATHER_ARB_NORMALIZED', 'LIKE', $thirdWord . '%')
                              ->orWhere('CI_FAMILY_ARB_NORMALIZED', 'LIKE', $thirdWord . '%');
                       });
                });
            } elseif ($secondWord) {
                // كلمتان: الاسم + (الأب أو العائلة)
                // البحث الأكثر دقة: الاسم + العائلة
                $q->where(function($sq) use ($firstWord, $secondWord) {
                    $sq->where('CI_FIRST_ARB_NORMALIZED', 'LIKE', $firstWord . '%')
                       ->where('CI_FAMILY_ARB_NORMALIZED', 'LIKE', $secondWord . '%');
                });

                // البحث البديل: الاسم + الأب
                $q->orWhere(function($sq) use ($firstWord, $secondWord) {
                    $sq->where('CI_FIRST_ARB_NORMALIZED', 'LIKE', $firstWord . '%')
                       ->where('CI_FATHER_ARB_NORMALIZED', 'LIKE', $secondWord . '%');
                });

                // البحث الأوسع: الأب + العائلة
                $q->orWhere(function($sq) use ($firstWord, $secondWord) {
                    $sq->where('CI_FATHER_ARB_NORMALIZED', 'LIKE', $firstWord . '%')
                       ->where('CI_FAMILY_ARB_NORMALIZED', 'LIKE', $secondWord . '%');
                });
            }
        })
        ->limit($limit)
        ->get();
    }

    /**
     * دمج الكلمات المركبة الشائعة في الأسماء العربية
     */
    private function mergeCompoundNames(array $words): array
    {
        $compoundPrefixes = ['عبد', 'أبو', 'ابو', 'أم', 'ام', 'بن', 'ابن'];
        $merged = [];
        $i = 0;

        while ($i < count($words)) {
            $current = $words[$i];

            // إذا كانت الكلمة الحالية من البادئات المركبة والكلمة التالية موجودة
            if (in_array($current, $compoundPrefixes) && isset($words[$i + 1])) {
                // دمج الكلمتين
                $merged[] = $current . ' ' . $words[$i + 1];
                $i += 2; // تخطي الكلمتين
            } else {
                $merged[] = $current;
                $i++;
            }
        }

        return $merged;
    }

    /**
     * البحث المحسّن لكلمة واحدة مع الأعمدة المطبّعة
     */
    private function singleWordCivilRegistrySearchOptimized($query, string $term, int $limit)
    {
        return $query->select(
            'CI_ID_NUM as id_number',
            'CI_FIRST_ARB',
            'CI_FATHER_ARB',
            'CI_GRAND_FATHER_ARB',
            'CI_FAMILY_ARB',
            'CI_BIRTH_DT',
            'CI_SEX_CD',
            'CI_DEAD_DT'
        )
        ->where(function($q) use ($term) {
            // البحث الدقيق باستخدام الأعمدة المطبّعة
            $q->where('CI_FIRST_ARB_NORMALIZED', 'LIKE', $term . '%')
              ->orWhere('CI_FATHER_ARB_NORMALIZED', 'LIKE', $term . '%')
              ->orWhere('CI_GRAND_FATHER_ARB_NORMALIZED', 'LIKE', $term . '%')
              ->orWhere('CI_FAMILY_ARB_NORMALIZED', 'LIKE', $term . '%');
        })
        ->limit($limit)
        ->get();
    }

    /**
     * البحث في جدول data مع التطبيع (محسّن لدعم كلمات متعددة)
     * يطبّع الأعمدة في SQL للبحث الدقيق في جميع الحالات
     */
    public function searchDataTable(string $searchTerm, int $limit = 50)
    {
        // إذا كان رقماً، استخدم INDEX مباشرة
        if (is_numeric($searchTerm)) {
            return DB::table('data')
                ->where('data_id_number', $searchTerm)
                ->orWhere('file_id_number', $searchTerm)
                ->limit($limit)
                ->get();
        }

        $normalizedTerm = normalizeArabicText($searchTerm);
        $words = preg_split('/\s+/', trim($normalizedTerm));

        // إذا كانت كلمة واحدة، بحث بسيط مع تطبيع SQL
        if (count($words) === 1) {
            return DB::table('data')
                ->where(function($q) use ($normalizedTerm) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$normalizedTerm . '%'])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_father_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$normalizedTerm . '%'])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_grand_father_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$normalizedTerm . '%'])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_family_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$normalizedTerm . '%']);
                })
                ->limit($limit)
                ->get();
        }

        // دمج الأسماء المركبة (مثل "عبد الناصر")
        $mergedWords = $this->mergeCompoundNames($words);

        $firstWord = $mergedWords[0] ?? '';
        $secondWord = $mergedWords[1] ?? '';
        $thirdWord = $mergedWords[2] ?? '';
        $fourthWord = $mergedWords[3] ?? '';

        return DB::table('data')
            ->where(function($q) use ($firstWord, $secondWord, $thirdWord, $fourthWord) {
                // 4 كلمات: الاسم + الأب + الجد + العائلة
                if ($fourthWord) {
                    $q->where(function($sq) use ($firstWord, $secondWord, $thirdWord, $fourthWord) {
                        $sq->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$firstWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_father_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$secondWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_grand_father_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$thirdWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_family_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$fourthWord . '%']);
                    });
                }
                // 3 كلمات: الاسم + الأب + العائلة
                elseif ($thirdWord) {
                    $q->where(function($sq) use ($firstWord, $secondWord, $thirdWord) {
                        $sq->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$firstWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_father_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$secondWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_family_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$thirdWord . '%']);
                    });
                }
                // كلمتان: الاسم + العائلة (أولوية أعلى)
                elseif ($secondWord) {
                    $q->where(function($sq) use ($firstWord, $secondWord) {
                        $sq->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$firstWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_family_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$secondWord . '%']);
                    });

                    // أو: الاسم + الأب
                    $q->orWhere(function($sq) use ($firstWord, $secondWord) {
                        $sq->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$firstWord . '%'])
                           ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(data_father_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي') LIKE ?", [$secondWord . '%']);
                    });
                }
            })
            ->limit($limit)
            ->get();
    }

    /**
     * البحث في الملفات مع التطبيع
     */
    public function searchFiles(string $searchTerm, int $limit = 50)
    {
        $normalizedTerm = normalizeArabicText($searchTerm);

        $attachments = DB::table('attachments')
            ->where(function($q) use ($normalizedTerm) {
                $q->where('stored_file_name', 'LIKE', "%{$normalizedTerm}%")
                  ->orWhere('file_path', 'LIKE', "%{$normalizedTerm}%")
                  ->orWhere('person_identity_number', 'LIKE', "%{$normalizedTerm}%");
            })
            ->limit($limit)
            ->get();

        $enhancedAttachments = DB::table('enhanced_attachments')
            ->where(function($q) use ($normalizedTerm) {
                $q->where('original_file_name', 'LIKE', "%{$normalizedTerm}%")
                  ->orWhere('stored_file_name', 'LIKE', "%{$normalizedTerm}%")
                  ->orWhere('file_path', 'LIKE', "%{$normalizedTerm}%")
                  ->orWhere('record_number', 'LIKE', "%{$normalizedTerm}%");
            })
            ->limit($limit)
            ->get();

        return [
            'attachments' => $attachments,
            'enhanced_attachments' => $enhancedAttachments
        ];
    }
}
