<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'status',
        'current_step',
        'started_at',
        'completed_at',
        'duration_seconds',
        'database_size',
        'database_status',
        'laravel_size',
        'laravel_status',
        'uploads_files',
        'uploads_size',
        'uploads_copied',
        'uploads_skipped',
        'uploads_failed',
        'uploads_status',
        'attachments_files',
        'attachments_size',
        'attachments_copied',
        'attachments_skipped',
        'attachments_failed',
        'attachments_status',
        'error_message',
    ];

    protected $casts = [
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'duration_seconds'   => 'integer',
        'database_size'      => 'integer',
        'laravel_size'       => 'integer',
        'uploads_files'      => 'integer',
        'uploads_size'       => 'integer',
        'uploads_copied'     => 'integer',
        'uploads_skipped'    => 'integer',
        'uploads_failed'     => 'integer',
        'attachments_files'  => 'integer',
        'attachments_size'   => 'integer',
        'attachments_copied' => 'integer',
        'attachments_skipped'=> 'integer',
        'attachments_failed' => 'integer',
    ];

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
