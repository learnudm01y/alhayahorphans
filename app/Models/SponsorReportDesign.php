<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
     * الحصول على صور base64 للصورة الواحدة
     */
    public function getSingleImageBase64Attribute()
    {
        if (!$this->single_image) {
            return null;
        }

        $imagePath = storage_path('app/public/' . $this->single_image);

        if (!file_exists($imagePath)) {
            Log::warning('Single image file not found', [
                'path' => $imagePath,
                'sponsor_id' => $this->sponsor_id
            ]);
            return null;
        }

        try {
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath);
            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

            Log::info('Single image loaded successfully', [
                'sponsor_id' => $this->sponsor_id,
                'path' => $imagePath,
                'size' => strlen($base64)
            ]);

            return $base64;
        } catch (\Exception $e) {
            Log::error('Error loading single image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * الحصول على صورة base64 لصورة الرأس
     */
    public function getHeaderImageBase64Attribute()
    {
        if (!$this->header_image) {
            return null;
        }

        $imagePath = storage_path('app/public/' . $this->header_image);

        if (!file_exists($imagePath)) {
            Log::warning('Header image file not found', [
                'path' => $imagePath,
                'sponsor_id' => $this->sponsor_id
            ]);
            return null;
        }

        try {
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath);
            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

            Log::info('Header image loaded successfully', [
                'sponsor_id' => $this->sponsor_id,
                'path' => $imagePath,
                'size' => strlen($base64)
            ]);

            return $base64;
        } catch (\Exception $e) {
            Log::error('Error loading header image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * الحصول على صورة base64 للصورة الرئيسية
     */
    public function getMainImageBase64Attribute()
    {
        if (!$this->main_image) {
            return null;
        }

        $imagePath = storage_path('app/public/' . $this->main_image);

        if (!file_exists($imagePath)) {
            Log::warning('Main image file not found', [
                'path' => $imagePath,
                'sponsor_id' => $this->sponsor_id
            ]);
            return null;
        }

        try {
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath);
            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

            Log::info('Main image loaded successfully', [
                'sponsor_id' => $this->sponsor_id,
                'path' => $imagePath,
                'size' => strlen($base64)
            ]);

            return $base64;
        } catch (\Exception $e) {
            Log::error('Error loading main image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * الحصول على صورة base64 لصورة التذييل
     */
    public function getFooterImageBase64Attribute()
    {
        if (!$this->footer_image) {
            return null;
        }

        $imagePath = storage_path('app/public/' . $this->footer_image);

        if (!file_exists($imagePath)) {
            Log::warning('Footer image file not found', [
                'path' => $imagePath,
                'sponsor_id' => $this->sponsor_id
            ]);
            return null;
        }

        try {
            $imageData = file_get_contents($imagePath);
            $mimeType = mime_content_type($imagePath);
            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

            Log::info('Footer image loaded successfully', [
                'sponsor_id' => $this->sponsor_id,
                'path' => $imagePath,
                'size' => strlen($base64)
            ]);

            return $base64;
        } catch (\Exception $e) {
            Log::error('Error loading footer image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
