<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Relation extends Model
{
    use HasFactory;

    /**
     * اسم الجدول في قاعدة البيانات
     */
    protected $table = 'relations';

    /**
     * اسم الاتصال بقاعدة البيانات
     */
    protected $connection = 'civilregistry';

    /**
     * تعطيل timestamps لأن الجدول لا يحتوي على created_at و updated_at
     */
    public $timestamps = false;

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'CF_ID_NUM',
        'CF_RELATIVE_CD',
        'CF_ID_RELATIVE',
    ];

    /**
     * العلاقة مع الشخص الأساسي (صاحب الهوية)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Persons::class, 'CF_ID_NUM', 'CI_ID_NUM');
    }

    /**
     * العلاقة مع الشخص القريب (الشخص المرتبط)
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function relativePerson()
    {
        return $this->belongsTo(Persons::class, 'CF_ID_RELATIVE', 'CI_ID_NUM');
    }

    /**
     * العلاقة مع نوع القرابة
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function relationType()
    {
        return $this->belongsTo(CategoryOfRelation::class, 'CF_RELATIVE_CD', 'id');
    }

    /**
     * الحصول على جميع علاقات شخص معين برقم هويته
     * 
     * @param string|int $idNum رقم الهوية
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPersonRelations($idNum)
    {
        return self::where('CF_ID_NUM', $idNum)
            ->with(['relativePerson', 'relationType'])
            ->get();
    }

    /**
     * الحصول على جميع العلاقات العائلية لشخص معين (في الاتجاهين)
     * 
     * @param string|int $idNum رقم الهوية
     * @return array
     */
    public static function getFamilyTree($idNum)
    {
        // العلاقات حيث الشخص هو CF_ID_NUM (العلاقات المباشرة)
        $directRelations = self::where('CF_ID_NUM', $idNum)
            ->with(['relativePerson', 'relationType'])
            ->get();

        // العلاقات حيث الشخص هو CF_ID_RELATIVE (العلاقات العكسية)
        $reverseRelations = self::where('CF_ID_RELATIVE', $idNum)
            ->with(['person', 'relationType'])
            ->get();

        return [
            'direct' => $directRelations,
            'reverse' => $reverseRelations
        ];
    }

    /**
     * البحث عن علاقات محددة حسب نوع القرابة
     * 
     * @param string|int $idNum رقم الهوية
     * @param int $relationType نوع القرابة
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRelationsByType($idNum, $relationType)
    {
        return self::where('CF_ID_NUM', $idNum)
            ->where('CF_RELATIVE_CD', $relationType)
            ->with(['relativePerson', 'relationType'])
            ->get();
    }

    /**
     * الحصول على الوالدين
     * 
     * @param string|int $idNum رقم الهوية
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getParents($idNum)
    {
        // نفترض أن رمز الأب والأم في جدول category_of_relations
        // يمكن تعديل هذه القيم حسب البيانات الفعلية
        return self::where('CF_ID_NUM', $idNum)
            ->whereIn('CF_RELATIVE_CD', [1, 2]) // 1 للأب، 2 للأم (مثال)
            ->with(['relativePerson', 'relationType'])
            ->get();
    }

    /**
     * الحصول على الأبناء
     * 
     * @param string|int $idNum رقم الهوية
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getChildren($idNum)
    {
        return self::where('CF_ID_RELATIVE', $idNum)
            ->whereIn('CF_RELATIVE_CD', [1, 2]) // العلاقة العكسية
            ->with(['person', 'relationType'])
            ->get();
    }
}
