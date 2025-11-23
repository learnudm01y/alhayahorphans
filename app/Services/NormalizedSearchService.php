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
     * البحث في السجل المدني مع التطبيع
     */
    public function searchCivilRegistry(string $searchTerm, int $limit = 50)
    {
        $normalizedTerm = normalizeArabicText($searchTerm);
        $noSpacesTerm = str_replace(' ', '', $normalizedTerm); // للبحث عن عبدالناصر / عبد الناصر
        $words = preg_split('/\s+/', $normalizedTerm);

        $query = DB::connection('civilregistry')->table('persons');

        if (count($words) > 1) {
            return $this->multiWordCivilRegistrySearch($query, $words, $normalizedTerm, $noSpacesTerm, $limit);
        }

        return $this->singleWordCivilRegistrySearch($query, $normalizedTerm, $noSpacesTerm, $limit);
    }

    /**
     * البحث بعدة كلمات في السجل المدني (مع تطبيع المسافات والكلمات المركبة)
     */
    private function multiWordCivilRegistrySearch($query, array $words, string $fullTerm, string $noSpacesTerm, int $limit)
    {
        $firstWord = $words[0];
        $secondWord = $words[1] ?? '';

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
        ->where(function($q) use ($firstWord, $secondWord, $fullTerm, $noSpacesTerm) {
            // البحث في الاسم الكامل مع التطبيع الشامل (حروف + مسافات)
            $fullNameExpr = $this->buildNormalizationSQL("CONCAT_WS(' ', CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)");
            $q->whereRaw("{$fullNameExpr} LIKE ?", ["%{$fullTerm}%"]);

            // البحث بدون مسافات (للكلمات المركبة مثل عبدالناصر / عبد الناصر)
            $fullNameNoSpaces = $this->buildNoSpacesSQL("CONCAT(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)");
            $q->orWhereRaw("{$fullNameNoSpaces} LIKE ?", ["%{$noSpacesTerm}%"]);

            // البحث في الاسم الأول والأب مع التطبيع
            $q->orWhere(function($subQ) use ($firstWord, $secondWord) {
                $firstNameExpr = $this->buildNormalizationSQL('CI_FIRST_ARB');
                $fatherNameExpr = $this->buildNormalizationSQL('CI_FATHER_ARB');
                $grandNameExpr = $this->buildNormalizationSQL('CI_GRAND_FATHER_ARB');
                $familyNameExpr = $this->buildNormalizationSQL('CI_FAMILY_ARB');

                $subQ->whereRaw("{$firstNameExpr} LIKE ?", ["{$firstWord}%"])
                     ->whereRaw("({$fatherNameExpr} LIKE ? OR {$grandNameExpr} LIKE ? OR {$familyNameExpr} LIKE ?)",
                                ["%{$secondWord}%", "%{$secondWord}%", "%{$secondWord}%"]);
            });
        })
        ->limit($limit)
        ->get();
    }

    /**
     * البحث بكلمة واحدة في السجل المدني (مع تطبيع المسافات والكلمات المركبة)
     */
    private function singleWordCivilRegistrySearch($query, string $term, string $noSpacesTerm, int $limit)
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
        ->where(function($q) use ($term, $noSpacesTerm) {
            // البحث مع التطبيع الشامل في كل عمود
            $firstNameExpr = $this->buildNormalizationSQL('CI_FIRST_ARB');
            $fatherNameExpr = $this->buildNormalizationSQL('CI_FATHER_ARB');
            $grandNameExpr = $this->buildNormalizationSQL('CI_GRAND_FATHER_ARB');
            $familyNameExpr = $this->buildNormalizationSQL('CI_FAMILY_ARB');

            $q->whereRaw("{$firstNameExpr} LIKE ?", ["%{$term}%"])
              ->orWhereRaw("{$fatherNameExpr} LIKE ?", ["%{$term}%"])
              ->orWhereRaw("{$grandNameExpr} LIKE ?", ["%{$term}%"])
              ->orWhereRaw("{$familyNameExpr} LIKE ?", ["%{$term}%"]);

            // البحث بدون مسافات (للكلمات المركبة مثل عبدالناصر / عبد الناصر)
            $firstNameNoSpaces = $this->buildNoSpacesSQL('CI_FIRST_ARB');
            $fatherNameNoSpaces = $this->buildNoSpacesSQL('CI_FATHER_ARB');
            $grandNameNoSpaces = $this->buildNoSpacesSQL('CI_GRAND_FATHER_ARB');
            $familyNameNoSpaces = $this->buildNoSpacesSQL('CI_FAMILY_ARB');

            $q->orWhereRaw("{$firstNameNoSpaces} LIKE ?", ["%{$noSpacesTerm}%"])
              ->orWhereRaw("{$fatherNameNoSpaces} LIKE ?", ["%{$noSpacesTerm}%"])
              ->orWhereRaw("{$grandNameNoSpaces} LIKE ?", ["%{$noSpacesTerm}%"])
              ->orWhereRaw("{$familyNameNoSpaces} LIKE ?", ["%{$noSpacesTerm}%"]);
        })
        ->limit($limit)
        ->get();
    }

    /**
     * البحث في جدول data مع التطبيع (بما في ذلك المسافات والكلمات المركبة)
     */
    public function searchDataTable(string $searchTerm, int $limit = 50)
    {
        $normalizedTerm = normalizeArabicText($searchTerm);
        $noSpacesTerm = str_replace(' ', '', $normalizedTerm);

        return DB::table('data')
            ->where(function($q) use ($normalizedTerm, $noSpacesTerm) {
                $columns = ['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'];

                foreach ($columns as $column) {
                    $normalizedColumn = $this->buildNormalizationSQL($column);
                    $q->orWhereRaw("{$normalizedColumn} LIKE ?", ["%{$normalizedTerm}%"]);

                    // البحث بدون مسافات
                    $noSpacesColumn = $this->buildNoSpacesSQL($column);
                    $q->orWhereRaw("{$noSpacesColumn} LIKE ?", ["%{$noSpacesTerm}%"]);
                }

                // البحث في الاسم الكامل
                $fullNameExpr = $this->buildNormalizationSQL("CONCAT_WS(' ', data_first_name, data_father_name, data_grand_father_name, data_family_name)");
                $q->orWhereRaw("{$fullNameExpr} LIKE ?", ["%{$normalizedTerm}%"]);

                // البحث في الاسم الكامل بدون مسافات
                $fullNameNoSpaces = $this->buildNoSpacesSQL("CONCAT(data_first_name, data_father_name, data_grand_father_name, data_family_name)");
                $q->orWhereRaw("{$fullNameNoSpaces} LIKE ?", ["%{$noSpacesTerm}%"]);

                // البحث برقم الهوية (بدون تطبيع)
                $q->orWhere('file_id_number', 'LIKE', "%{$normalizedTerm}%")
                  ->orWhere('data_id_number', 'LIKE', "%{$normalizedTerm}%");
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
