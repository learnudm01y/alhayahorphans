<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class CivilRegistryPerson extends Model
{
    use Searchable;

    /**
     * اتصال قاعدة البيانات المخصصة
     */
    protected $connection = 'civilregistry';

    /**
     * اسم الجدول
     */
    protected $table = 'persons';

    /**
     * المفتاح الأساسي
     */
    protected $primaryKey = 'ID';

    /**
     * نوع المفتاح الأساسي
     */
    protected $keyType = 'int';

    /**
     * هل المفتاح الأساسي تلقائي
     */
    public $incrementing = true;

    /**
     * إدارة الوقت
     */
    public $timestamps = false;

    /**
     * الحقول القابلة للملء
     */
    protected $fillable = [
        'CI_ID_NUM',
        'CI_FIRST_ARB',
        'CI_FATHER_ARB',
        'CI_GRAND_FATHER_ARB',
        'CI_FAMILY_ARB',
        'MOTHER_NAME1',
        'CI_BIRTH_DT',
        'CI_BIRTH_CD',
        'CI_BIRTH_TB_CD',
        'CI_SEX_CD',
        'CI_PERSONAL_CD',
        'CI_DEAD_DT',
        'CITY',
        'STREET',
        'HOUSE_NO',
    ];

    /**
     * تحويل البيانات
     */
    protected $casts = [
        'CI_BIRTH_DT' => 'date',
        'CI_DEAD_DT' => 'date',
        'CI_SEX_CD' => 'integer',
        'CI_PERSONAL_CD' => 'integer',
        'CI_BIRTH_CD' => 'integer',
        'CI_BIRTH_TB_CD' => 'integer',
        'CITY' => 'integer',
    ];

    /**
     * اسم الفهرس في محرك البحث Scout
     */
    public function searchableAs(): string
    {
        return 'civil_registry_persons';
    }

    /**
     * البيانات القابلة للفهرسة في Scout
     */
    public function toSearchableArray(): array
    {
        // فقط الأعمدة الموجودة فعلياً في قاعدة البيانات
        $array = [
            'ID' => $this->ID,
            'CI_ID_NUM' => (string)$this->CI_ID_NUM,
            'CI_FIRST_ARB' => $this->CI_FIRST_ARB ?? '',
            'CI_FATHER_ARB' => $this->CI_FATHER_ARB ?? '',
            'CI_GRAND_FATHER_ARB' => $this->CI_GRAND_FATHER_ARB ?? '',
            'CI_FAMILY_ARB' => $this->CI_FAMILY_ARB ?? '',
            'MOTHER_NAME1' => $this->MOTHER_NAME1 ?? '',
            'CI_BIRTH_DT' => $this->CI_BIRTH_DT ? $this->CI_BIRTH_DT->format('Y-m-d') : null,
            'CI_SEX_CD' => $this->CI_SEX_CD,
            'CI_PERSONAL_CD' => $this->CI_PERSONAL_CD,
            'CITY' => $this->CITY,
            'STREET' => $this->STREET ?? '',
            'HOUSE_NO' => $this->HOUSE_NO ?? '',
            'CI_DEAD_DT' => $this->CI_DEAD_DT ?? null,
        ];

        return $array;
    }

    /**
     * تحديد السجلات القابلة للفهرسة
     */
    public function shouldBeSearchable(): bool
    {
        // فقط السجلات التي تحتوي على رقم هوية ولها اسم أول على الأقل
        return !empty($this->CI_ID_NUM) && !empty($this->CI_FIRST_ARB);
    }

    /**
     * تخصيص إعدادات الفهرسة
     */
    public function searchableOptions(): array
    {
        return [
            'chunk' => 1000, // فهرسة 1000 سجل في المرة الواحدة لتحسين الأداء
        ];
    }

    /**
     * مفتاح Scout المخصص
     */
    public function getScoutKeyName(): string
    {
        return 'ID';
    }

    /**
     * تحديد القيم الافتراضية للبحث
     */
    public function getDefaultSearchParams(): array
    {
        return [
            'hitsPerPage' => 50,
            'attributesToRetrieve' => ['*'],
            'attributesToHighlight' => [
                'CI_FIRST_ARB',
                'CI_FATHER_ARB',
                'CI_GRAND_FATHER_ARB',
                'CI_FAMILY_ARB',
                'full_name',
                'CI_ID_NUM',
                'MOTHER_NAME1'
            ],
        ];
    }

    /**
     * الحصول على الاسم الكامل
     */
    public function getFullNameAttribute(): string
    {
        return trim(
            ($this->CI_FIRST_ARB ?? '') . ' ' .
            ($this->CI_FATHER_ARB ?? '') . ' ' .
            ($this->CI_GRAND_FATHER_ARB ?? '') . ' ' .
            ($this->CI_FAMILY_ARB ?? '')
        );
    }

    /**
     * الحصول على العمر
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->CI_BIRTH_DT) {
            return null;
        }

        return now()->year - (int)$this->CI_BIRTH_DT->format('Y');
    }

    /**
     * تحديد الجنس (نص)
     */
    public function getGenderTextAttribute(): string
    {
        return match($this->CI_SEX_CD) {
            1 => 'ذكر',
            2 => 'أنثى',
            default => 'غير محدد'
        };
    }

    /**
     * حالة الحياة
     */
    public function getIsAliveAttribute(): bool
    {
        return empty($this->CI_DEAD_DT);
    }

    /**
     * البحث المحسن مع معايير متعددة
     */
    public static function advancedSearch(array $criteria, int $limit = 100)
    {
        $query = static::search('');

        // إذا كان هناك نص للبحث
        if (!empty($criteria['search_text'])) {
            $query = static::search($criteria['search_text']);
        }

        // إضافة فلاتر إضافية إذا كان محرك البحث يدعمها
        if (!empty($criteria['gender'])) {
            $query->where('CI_SEX_CD', $criteria['gender']);
        }

        if (!empty($criteria['birth_year'])) {
            $query->where('birth_year', $criteria['birth_year']);
        }

        if (!empty($criteria['city'])) {
            $query->where('CITY', $criteria['city']);
        }

        if (isset($criteria['is_alive'])) {
            $query->where('is_alive', $criteria['is_alive']);
        }

        return $query->take($limit)->get();
    }

    /**
     * البحث السريع بالاسم أو رقم الهوية
     */
    public static function quickSearch(string $term, int $limit = 20)
    {
        return static::search($term)
                     ->take($limit)
                     ->get();
    }

    /**
     * البحث بالاسم الكامل
     */
    public static function searchByFullName(string $name, int $limit = 50)
    {
        return static::search($name)
                     ->take($limit)
                     ->get();
    }

    /**
     * البحث برقم الهوية
     */
    public static function searchByIdNumber(string $idNumber)
    {
        return static::search($idNumber)
                     ->where('CI_ID_NUM', $idNumber)
                     ->first();
    }
}
