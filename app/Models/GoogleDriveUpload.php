<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model: GoogleDriveUpload
 *
 * Purpose: Track file uploads to Google Drive and serve as bridge between
 * mobile app and Laravel. Attachments are NOT synced - they are uploaded
 * directly via Rclone, then Laravel is notified to create attachment records.
 *
 * @property int $id
 * @property string $local_file_path
 * @property string $local_file_hash
 * @property string $file_name
 * @property int $file_size_bytes
 * @property string $mime_type
 * @property string|null $google_drive_file_id
 * @property string|null $google_drive_path
 * @property string $upload_status
 * @property int $upload_progress
 * @property string $entity_type
 * @property string $entity_id
 * @property string $attachment_type
 * @property string|null $attachment_description
 * @property string $device_id
 * @property int $uploaded_by
 * @property int $retry_count
 * @property string|null $error_message
 * @property bool $synced_to_server
 * @property int|null $server_attachment_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $uploaded_at
 */
class GoogleDriveUpload extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'google_drive_uploads';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'local_file_path',
        'local_file_hash',
        'file_name',
        'file_size_bytes',
        'mime_type',
        'google_drive_file_id',
        'google_drive_path',
        'upload_status',
        'upload_progress',
        'entity_type',
        'entity_id',
        'attachment_type',
        'attachment_description',
        'device_id',
        'uploaded_by',
        'retry_count',
        'error_message',
        'synced_to_server',
        'server_attachment_id',
        'uploaded_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size_bytes' => 'integer',
        'upload_progress' => 'integer',
        'retry_count' => 'integer',
        'synced_to_server' => 'boolean',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Upload status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_UPLOADING = 'uploading';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SKIPPED = 'skipped'; // سجل مكرر (1062) — لا تعيد المحاولة

    /**
     * Entity type constants
     */
    const ENTITY_SPONSORSHIP = 'sponsorship';
    const ENTITY_ORPHAN = 'orphan';
    const ENTITY_GUARDIAN = 'guardian';
    const ENTITY_DECEASED = 'deceased';
    const ENTITY_BANK_ACCOUNT = 'bank_account';

    // ==================== Relationships ====================

    /**
     * Get the user who uploaded the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the associated attachment record.
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'server_attachment_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope: Pending uploads
     */
    public function scopePending($query)
    {
        return $query->where('upload_status', self::STATUS_PENDING);
    }

    /**
     * Scope: Uploads in progress
     */
    public function scopeInProgress($query)
    {
        return $query->where('upload_status', self::STATUS_UPLOADING);
    }

    /**
     * Scope: Completed uploads
     */
    public function scopeCompleted($query)
    {
        return $query->where('upload_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Failed uploads
     */
    public function scopeFailed($query)
    {
        return $query->where('upload_status', self::STATUS_FAILED);
    }

    /**
     * Scope: Uploads not yet synced to server
     */
    public function scopeUnsynced($query)
    {
        return $query->where('upload_status', self::STATUS_COMPLETED)
                     ->where('synced_to_server', false);
    }

    /**
     * Scope: Uploads for a specific entity
     */
    public function scopeForEntity($query, string $type, string $id)
    {
        return $query->where('entity_type', $type)
                     ->where('entity_id', $id);
    }

    /**
     * Scope: Uploads by device
     */
    public function scopeByDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope: Uploads that can be retried (failed but under retry limit)
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->where('upload_status', self::STATUS_FAILED)
                     ->where('retry_count', '<', $maxRetries);
    }

    // ==================== Methods ====================

    /**
     * Check if file with given hash already exists
     */
    public static function hashExists(string $hash): bool
    {
        return static::where('local_file_hash', $hash)
                     ->where('upload_status', self::STATUS_COMPLETED)
                     ->exists();
    }

    /**
     * Get upload statistics
     */
    public static function getStatistics(): array
    {
        $stats = static::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN upload_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN upload_status = 'uploading' THEN 1 ELSE 0 END) as uploading,
            SUM(CASE WHEN upload_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN upload_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN synced_to_server = 0 AND upload_status = 'completed' THEN 1 ELSE 0 END) as unsynced,
            COALESCE(SUM(file_size_bytes), 0) as total_size,
            COALESCE(SUM(CASE WHEN upload_status = 'completed' THEN file_size_bytes ELSE 0 END), 0) as uploaded_size
        ")->first();

        return [
            'total' => $stats->total ?? 0,
            'pending' => $stats->pending ?? 0,
            'uploading' => $stats->uploading ?? 0,
            'completed' => $stats->completed ?? 0,
            'failed' => $stats->failed ?? 0,
            'unsynced' => $stats->unsynced ?? 0,
            'total_size' => $stats->total_size ?? 0,
            'uploaded_size' => $stats->uploaded_size ?? 0,
            'total_size_formatted' => $this->formatBytes($stats->total_size ?? 0),
            'uploaded_size_formatted' => $this->formatBytes($stats->uploaded_size ?? 0)
        ];
    }

    /**
     * Mark upload as completed
     */
    public function markAsCompleted(string $googleDriveFileId, string $googleDrivePath): void
    {
        $this->update([
            'upload_status' => self::STATUS_COMPLETED,
            'upload_progress' => 100,
            'google_drive_file_id' => $googleDriveFileId,
            'google_drive_path' => $googleDrivePath,
            'uploaded_at' => now(),
            'error_message' => null
        ]);
    }

    /**
     * Mark upload as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'upload_status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1
        ]);
    }

    /**
     * Mark as synced to server
     */
    public function markAsSynced(int $attachmentId): void
    {
        $this->update([
            'synced_to_server' => true,
            'server_attachment_id' => $attachmentId
        ]);
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        return $this->formatBytes($this->file_size_bytes);
    }

    /**
     * Format bytes to human-readable size
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
