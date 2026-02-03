<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SponsorReportDesign extends Model
{
    use HasFactory;

    protected $fillable = [
        'sponsor_id',
        'background_type',
        'single_image',
        'header_image',
        'main_image',
        'footer_image',
        'theme_colors',
    ];

    protected $casts = [
        'theme_colors' => 'array',
    ];

    /**
     * العلاقة مع جدول الجمعيات
     */
    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * الحصول على المسار الكامل للصورة الواحدة
     */
    public function getSingleImagePathAttribute()
    {
        return $this->single_image
            ? storage_path('app/public/' . $this->single_image)
            : null;
    }

    /**
     * الحصول على المسار الكامل لصورة الرأس
     */
    public function getHeaderImagePathAttribute()
    {
        return $this->header_image
            ? storage_path('app/public/' . $this->header_image)
            : null;
    }

    /**
     * الحصول على المسار الكامل للصورة الرئيسية
     */
    public function getMainImagePathAttribute()
    {
        return $this->main_image
            ? storage_path('app/public/' . $this->main_image)
            : null;
    }

    /**
     * الحصول على المسار الكامل لصورة التذييل
     */
    public function getFooterImagePathAttribute()
    {
        return $this->footer_image
            ? storage_path('app/public/' . $this->footer_image)
            : null;
    }

    /**
     * الحصول على صورة base64 للصورة الواحدة
     */
    public function getSingleImageBase64Attribute()
    {
        if (!$this->single_image || !$this->single_image_path || !file_exists($this->single_image_path)) {
            return null;
        }

        try {
            $imageData = file_get_contents($this->single_image_path);
            $mimeType = mime_content_type($this->single_image_path);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * الحصول على صورة base64 لصورة الرأس
     */
    public function getHeaderImageBase64Attribute()
    {
        if (!$this->header_image || !$this->header_image_path || !file_exists($this->header_image_path)) {
            return null;
        }

        try {
            $imageData = file_get_contents($this->header_image_path);
            $mimeType = mime_content_type($this->header_image_path);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * الحصول على صورة base64 للصورة الرئيسية
     */
    public function getMainImageBase64Attribute()
    {
        if (!$this->main_image || !$this->main_image_path || !file_exists($this->main_image_path)) {
            return null;
        }

        try {
            $imageData = file_get_contents($this->main_image_path);
            $mimeType = mime_content_type($this->main_image_path);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * الحصول على صورة base64 لصورة التذييل
     */
    public function getFooterImageBase64Attribute()
    {
        if (!$this->footer_image || !$this->footer_image_path || !file_exists($this->footer_image_path)) {
            return null;
        }

        try {
            $imageData = file_get_contents($this->footer_image_path);
            $mimeType = mime_content_type($this->footer_image_path);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } catch (\Exception $e) {
            return null;
        }
    }
}
