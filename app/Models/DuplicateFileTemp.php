<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DuplicateFileTemp extends Model
{
    use HasFactory;

    protected $table = 'duplicate_files_temp';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'original_name',
        'duplicate_name',
        'temp_path',
        'original_folder',
        'target_folder',
        'existing_file_name',
        'existing_file_id',
        'file_size',
        'mime_type',
        'created_at',
        'expires_at'
    ];

    protected $dates = [
        'created_at',
        'expires_at'
    ];

    /**
     * Scope to get non-expired files
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope to get files by session
     */
    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Check if file is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < Carbon::now();
    }

    /**
     * Get the existing attachment record
     */
    public function existingAttachment()
    {
        return $this->belongsTo(Attachment::class, 'existing_file_name', 'file_name');
    }
}
