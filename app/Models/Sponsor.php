<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use HasFactory;

    protected $table = 'sponsors';
    protected $fillable = [
        'file_id',
        'sponsor_name',
        'sponsor_short_name',
        'sponsor_phone_number',
        'sponsor_email',
        'sponsor_address',
        'sponsor_bank_name_id',
        'sponsor_account_bank_number',
        'sponsor_bank_swift_code',
        'sponsor_bank_related_phone_number',
        'sponsor_bank_account_currency',
        'country_code',
        'google_drive_enabled',
        'google_drive_folder_name',
    ];

    protected $casts = [
        'google_drive_enabled' => 'boolean',
    ];

    public function bankName()
    {
        return $this->belongsTo(BankName::class, 'sponsor_bank_name_id');
    }

    public function currencyType()
    {
        return $this->belongsTo(CurrencyType::class, 'sponsor_bank_account_currency');
    }

    public function country()
    {
        return $this->belongsTo(CI_BIRTH_CD::class, 'country_code', 'code');
    }

    public function employees()
    {
        return $this->hasMany(AssociationEmployee::class);
    }

    /**
     * علاقة Many-to-Many مع جدول الكفالات (sponsorships)
     * المؤسسة يمكن أن تكون لديها عدة كفالات
     */
    public function sponsorships()
    {
        return $this->belongsToMany(Sponsorship::class, 'sponsorship_sponsor')
            ->withTimestamps();
    }

    /**
     * علاقة مع إعدادات الحقول
     * كل جمعية لها إعدادات حقول خاصة بها
     */
    public function fieldSettings()
    {
        return $this->hasOne(SponsorFieldSetting::class);
    }

    /**
     * العلاقة مع أنواع الوثائق المفعلة
     */
    public function documentTypes()
    {
        return $this->belongsToMany(DocumentType::class, 'sponsor_document_types')
            ->withPivot(['is_enabled', 'basic_enabled', 'family_enabled', 'deceased_enabled', 'is_required', 'notes'])
            ->withTimestamps();
    }

    /**
     * العلاقة مع إعدادات الوثائق
     */
    public function sponsorDocumentTypes()
    {
        return $this->hasMany(SponsorDocumentType::class);
    }

    /**
     * العلاقة مع تصميم التقرير الخاص بالجمعية
     */
    public function reportDesign()
    {
        return $this->hasOne(SponsorReportDesign::class);
    }
}
