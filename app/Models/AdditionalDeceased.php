<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model للمتوفين الإضافيين
 * يخزن بيانات أي متوفي إضافي غير الأب والأم
 */
class AdditionalDeceased extends Model
{
    use HasFactory;

    protected $table = 'additional_deceased';

    protected $fillable = [
        're_file_id',
        'person_id',
        'first_name',
        'second_name',
        'third_name',
        'last_name',
        'relationship',
        'death_date',
        'death_reason',
    ];

    protected $casts = [
        'death_date' => 'date',
        're_file_id' => 'string',
        'death_reason' => 'integer',
    ];

    /**
     * العلاقة مع جدول البيانات الرئيسية (Data)
     */
    public function data()
    {
        return $this->belongsTo(Data::class, 're_file_id', 'file_id_number');
    }

    /**
     * العلاقة مع جدول أسباب الوفاة
     */
    public function deathReasonRelation()
    {
        return $this->belongsTo(DeathReason::class, 'death_reason', 'id');
    }

    /**
     * الحصول على الاسم الكامل
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->second_name} {$this->third_name} {$this->last_name}");
    }

    /**
     * الحصول على نص صلة القرابة بالعربية
     */
    public function getRelationshipTextAttribute(): string
    {
        $relationships = [
            'father' => 'أب',
            'mother' => 'أم',
            'brother' => 'أخ',
            'sister' => 'أخت',
            'grandfather' => 'جد',
            'grandmother' => 'جدة',
            'uncle' => 'عم/خال',
            'aunt' => 'عمة/خالة',
            'other' => 'أخرى',
        ];

        return $relationships[$this->relationship] ?? $this->relationship;
    }
}
