<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class PersonSearchable extends Model
{
    use Searchable;

    protected $table = 'persons';
    protected $primaryKey = 'ID';

    /**
     * الحقول القابلة للبحث
     */
    protected $fillable = [
        'CI_ID_NUM',
        'CI_FIRST_ARB',
        'CI_FATHER_ARB',
        'CI_GRAND_FATHER_ARB',
        'CI_FAMILY_ARB',
        'CI_BIRTH_DT',
        'CI_SEX_CD',
        'MOTHER_NAME1',
        'CITY'
    ];

    /**
     * اسم الفهرس في محرك البحث
     */
    public function searchableAs(): string
    {
        return 'persons_scout_index';
    }

    /**
     * البيانات القابلة للفهرسة
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->ID,
            'CI_ID_NUM' => $this->CI_ID_NUM,
            'CI_FIRST_ARB' => $this->CI_FIRST_ARB ?? '',
            'CI_FATHER_ARB' => $this->CI_FATHER_ARB ?? '',
            'CI_GRAND_FATHER_ARB' => $this->CI_GRAND_FATHER_ARB ?? '',
            'CI_FAMILY_ARB' => $this->CI_FAMILY_ARB ?? '',
            'full_name' => trim(($this->CI_FIRST_ARB ?? '') . ' ' . ($this->CI_FATHER_ARB ?? '') . ' ' . ($this->CI_GRAND_FATHER_ARB ?? '') . ' ' . ($this->CI_FAMILY_ARB ?? '')),
            'CI_BIRTH_DT' => $this->CI_BIRTH_DT,
            'birth_year' => $this->CI_BIRTH_DT ? date('Y', strtotime($this->CI_BIRTH_DT)) : null,
            'CI_SEX_CD' => $this->CI_SEX_CD,
            'MOTHER_NAME1' => $this->MOTHER_NAME1 ?? '',
            'CITY' => $this->CITY,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * شروط البحث (فقط السجلات النشطة)
     */
    public function shouldBeSearchable(): bool
    {
        // فقط السجلات التي تحتوي على رقم هوية صحيح
        return !empty($this->CI_ID_NUM) && !empty($this->CI_FIRST_ARB);
    }

    /**
     * تخصيص فهرسة دفعية للأداء
     */
    public function searchableOptions(): array
    {
        return [
            'chunk' => 500, // فهرسة 500 سجل في المرة الواحدة لتحسين الأداء
        ];
    }

    /**
     * العلاقات المطلوبة للبحث
     */
    public function getScoutKeyName(): string
    {
        return 'ID';
    }
}
