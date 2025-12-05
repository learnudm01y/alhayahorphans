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
        'external_file_number',
        'identity_number',
        'orphan_name',
        'guardian_name',
        'guardian_identity_number',
        'sponsorship_duration_months',
        'sponsorship_start_date',
        'sponsorship_end_date',
        'sponsorship_type_id',
        'sponsorship_status_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sponsorship_start_date' => 'date',
        'sponsorship_end_date' => 'date',
        'sponsorship_duration_months' => 'integer',
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
     * علاقة مع جدول البيانات (data) عبر رقم الملف الداخلي
     */
    public function guardianData()
    {
        return $this->belongsTo(Data::class, 'internal_file_number', 'file_id_number');
    }

    /**
     * علاقة مع جدول الأيتام (re_people) عبر رقم الهوية
     */
    public function orphan()
    {
        return $this->belongsTo(RePeople::class, 'identity_number', 'id_number');
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
