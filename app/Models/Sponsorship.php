<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsorship extends Model
{
    use HasFactory;

    protected $table = 'sponsorships';

    protected $fillable = [
        'sponsor_id',
        'sponsoring_organization',
        'internal_file_number',
        'relation_id_number', // رقم الربط الداخلي المخفي
        'external_file_number',
        'identity_number',
        'orphan_name',
        'sponsored_birth_date', // تاريخ ميلاد المكفول
        'guardian_name',
        'guardian_identity_number',
        'sponsorship_duration_months',
        'sponsorship_start_date',
        'sponsorship_end_date',
        'sponsorship_type_id',
        'sponsorship_status_id',
        'person_type', // نوع الشخص: breadwinner, family_member, deceased_father, deceased_mother
        'notes',
        'created_by',
        'updated_by', // مصفوفة المستخدمين الذين عدلوا السجل
    ];

    protected $casts = [
        'sponsorship_start_date' => 'date',
        'sponsorship_end_date' => 'date',
        'sponsored_birth_date' => 'date', // تاريخ ميلاد المكفول
        'sponsorship_duration_months' => 'integer',
        'updated_by' => 'array', // تحويل JSON إلى مصفوفة تلقائياً
    ];

    /**
     * علاقة مع جدول الكفلاء (sponsors) - العلاقة القديمة
     * @deprecated استخدم sponsors() للحصول على جميع الكفلاء
     */
    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class, 'sponsor_id');
    }

    /**
     * علاقة Many-to-Many مع جدول الكفلاء (sponsors)
     * كفالة واحدة يمكن أن تكون من عدة مؤسسات
     */
    public function sponsors()
    {
        return $this->belongsToMany(Sponsor::class, 'sponsorship_sponsor')
            ->withTimestamps();
    }

    /**
     * علاقة مع جدول أنواع الكفالة (type_of_guarantee)
     */
    public function sponsorshipType()
    {
        return $this->belongsTo(TypeOfGuarantee::class, 'sponsorship_type_id');
    }

    /**
     * علاقة مع جدول حالات الكفالة (sponsorship_statuses)
     */
    public function sponsorshipStatus()
    {
        return $this->belongsTo(SponsorshipStatus::class, 'sponsorship_status_id');
    }

    /**
     * علاقة مع جدول المستخدمين (users) - المستخدم الذي أنشأ السجل
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * إضافة مستخدم جديد إلى قائمة المستخدمين الذين عدلوا السجل
     */
    public function addUpdater($userId)
    {
        $updatedBy = $this->updated_by ?? [];

        // إضافة المستخدم مع التاريخ والوقت
        $updatedBy[] = [
            'user_id' => $userId,
            'updated_at' => now()->toDateTimeString(),
            'name' => optional(User::find($userId))->name
        ];

        $this->updated_by = $updatedBy;
        $this->save();
    }

    /**
     * الحصول على أسماء جميع المستخدمين الذين عدلوا السجل
     */
    public function getUpdaterNamesAttribute()
    {
        if (empty($this->updated_by)) {
            return [];
        }

        return collect($this->updated_by)->pluck('name')->unique()->toArray();
    }

    /**
     * علاقة مع جدول البيانات (data) عبر رقم الملف الداخلي
     */
    public function guardianData()
    {
        return $this->belongsTo(Data::class, 'internal_file_number', 'file_id_number');
    }

    /**
     * علاقة مع جدول البيانات (data) عبر رقم الربط relation_id_number
     * للحصول على المحافظة والمدينة للشخص المكفول
     */
    public function relationData()
    {
        return $this->belongsTo(Data::class, 'relation_id_number', 'file_id_number');
    }

    /**
     * علاقة مع جدول الأيتام (re_people) عبر رقم الهوية
     * ملاحظة: العمود الصحيح في re_people هو person_id وليس id_number
     */
    public function orphan()
    {
        return $this->belongsTo(RePeople::class, 'identity_number', 'person_id');
    }

    /**
     * Scope للكفالات النشطة
     */
    public function scopeActive($query)
    {
        return $query->whereDate('sponsorship_end_date', '>=', now())
                    ->orWhereNull('sponsorship_end_date');
    }

    /**
     * Scope للكفالات المنتهية
     */
    public function scopeExpired($query)
    {
        return $query->whereDate('sponsorship_end_date', '<', now())
                    ->whereNotNull('sponsorship_end_date');
    }

    /**
     * Accessor للحصول على الاسم الكامل للكافل
     */
    public function getSponsorNameAttribute()
    {
        return $this->sponsor ? $this->sponsor->sponsor_name : $this->sponsoring_organization;
    }

    /**
     * Accessor للتحقق من انتهاء الكفالة
     */
    public function getIsExpiredAttribute()
    {
        if (!$this->sponsorship_end_date) {
            return false;
        }

        return $this->sponsorship_end_date->isPast();
    }

    /**
     * Accessor للحصول على عدد الأيام المتبقية
     */
    public function getRemainingDaysAttribute()
    {
        if (!$this->sponsorship_end_date) {
            return null;
        }

        $now = now();
        if ($this->sponsorship_end_date->isPast()) {
            return 0;
        }

        return $now->diffInDays($this->sponsorship_end_date);
    }
}
