<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persons extends Model
{
    use HasFactory;
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
    public function socialStatus()
    {
        return $this->belongsTo(CI_PERSONAL_CD::class, 'CI_PERSONAL_CD', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'CITY', 'id');
    }

    public function CI_BIRTH_TB_CD()
    {
        return $this->belongsTo(CI_BIRTH_TB_CD::class, 'CI_BIRTH_TB_CD', 'id');
    }

    public function CI_BIRTH_CD()
    {
        return $this->belongsTo(CI_BIRTH_CD::class, 'CI_BIRTH_CD', 'id');
    }

    /**
     * العلاقات العائلية المباشرة (الشخص كصاحب الهوية الأساسي)
     */
    public function relations()
    {
        return $this->hasMany(Relation::class, 'CF_ID_NUM', 'CI_ID_NUM');
    }

    /**
     * العلاقات العائلية العكسية (الشخص كقريب)
     */
    public function reverseRelations()
    {
        return $this->hasMany(Relation::class, 'CF_ID_RELATIVE', 'CI_ID_NUM');
    }

}
