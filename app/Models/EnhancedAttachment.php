<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnhancedAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'enhanced_attachments';

    protected $fillable = [
        'file_name',
        'original_file_name',
        'stored_file_name',
        'file_path',
        'folder_id',
        'original_folder_name',
        'file_size',
        'mime_type',
        'file_type',
        'file_extension',
        'file_hash',
        'upload_session_id',
        'processed_at',
        'metadata',
        'thumbnail_path',
        'is_processed',
        'processing_status',
        'user_id'
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean',
        'file_size' => 'integer'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'processed_at'
    ];

    /**
     * Get the user who uploaded this attachment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by folder
     */
    public function scopeByFolder($query, $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    /**
     * Scope for filtering by file type
     */
    public function scopeByFileType($query, $fileType)
    {
        return $query->where('file_type', $fileType);
    }

    /**
     * Scope for processed files only
     */
    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute()
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get full file path
     */
    public function getFullPathAttribute()
    {
        return storage_path('app/public/' . $this->file_path);
    }

    /**
     * Check if file exists on disk
     */
    public function fileExists()
    {
        return file_exists($this->full_path);
    }

    /**
     * Get download URL
     */
    public function getDownloadUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Check if this is an image file
     */
    public function isImage()
    {
        return $this->file_type === 'image';
    }

    /**
     * Check if this is a document file
     */
    public function isDocument()
    {
        return in_array($this->file_type, ['document', 'pdf']);
    }

    /**
     * Check if this is an Excel file
     */
    public function isExcel()
    {
        return $this->file_type === 'excel';
    }
}
