# Google Drive Direct Upload System
## AlHayah Orphans - Rclone Integration

---

## 📋 نظرة عامة

هذا المستند يوضح نظام رفع الملفات المباشر إلى Google Drive باستخدام **Rclone** بدون مزامنة جدول `attachments`.

### المبادئ الأساسية

1. **لا يوجد مزامنة لجدول attachments**
2. **الرفع المباشر** من الجهاز إلى Google Drive
3. **جدول تتبع محلي** `google_drive_uploads` يمنع التكرار
4. **إخطار Laravel** بعد نجاح الرفع لإنشاء سجل في `attachments`

---

## 🗄️ Database Schema

### Migration: create_google_drive_uploads_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoogleDriveUploadsTable extends Migration
{
    public function up()
    {
        Schema::create('google_drive_uploads', function (Blueprint $table) {
            $table->id();
            
            // Local file info
            $table->string('local_file_path', 500);
            $table->string('local_file_hash', 64)->index(); // SHA256
            $table->string('file_name');
            $table->bigInteger('file_size_bytes')->unsigned();
            $table->string('mime_type', 100);
            
            // Google Drive info
            $table->string('google_drive_file_id', 100)->nullable()->index();
            $table->string('google_drive_path', 500)->nullable();
            
            // Upload status
            $table->enum('upload_status', [
                'pending', 
                'uploading', 
                'completed', 
                'failed',
                'cancelled'
            ])->default('pending')->index();
            $table->unsignedTinyInteger('upload_progress')->default(0); // 0-100
            
            // Entity linking
            $table->enum('entity_type', [
                'sponsorship',
                'orphan', 
                'guardian',
                'deceased',
                'bank_account'
            ])->index();
            $table->string('entity_id', 50)->index(); // file_id_number, registration_id, etc.
            
            // Attachment categorization
            $table->string('attachment_type', 100); // personal_photo, birth_certificate, etc.
            $table->string('attachment_description', 500)->nullable();
            
            // Device & User tracking
            $table->string('device_id', 100)->index();
            $table->unsignedBigInteger('uploaded_by')->index();
            
            // Error handling
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            
            // Sync tracking
            $table->boolean('synced_to_server')->default(false)->index();
            $table->unsignedBigInteger('server_attachment_id')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->timestamp('uploaded_at')->nullable();
            
            // Indexes
            $table->index(['entity_type', 'entity_id']);
            $table->index(['upload_status', 'synced_to_server']);
            
            // Foreign keys
            $table->foreign('uploaded_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('google_drive_uploads');
    }
}
```

---

## 📱 Capacitor Implementation

### RcloneService

```typescript
// services/rclone.service.ts

import { Filesystem, Directory } from '@capacitor/filesystem';
import { Http } from '@capacitor-community/http';

interface UploadConfig {
    localFilePath: string;
    remotePath: string;
    entityType: 'sponsorship' | 'orphan' | 'guardian' | 'deceased' | 'bank_account';
    entityId: string;
    attachmentType: string;
    attachmentDescription?: string;
}

interface UploadResult {
    success: boolean;
    googleDriveFileId?: string;
    googleDrivePath?: string;
    errorMessage?: string;
}

class RcloneService {
    private static RCLONE_API_URL = 'http://localhost:5572'; // Rclone RC API
    private static REMOTE_NAME = 'gdrive'; // اسم الـ remote في rclone config
    
    /**
     * 🔧 التحقق من جاهزية Rclone
     */
    static async checkRcloneReady(): Promise<boolean> {
        try {
            const response = await Http.request({
                method: 'POST',
                url: `${this.RCLONE_API_URL}/rc/noop`,
                headers: { 'Content-Type': 'application/json' }
            });
            return response.status === 200;
        } catch (error) {
            console.error('Rclone not ready:', error);
            return false;
        }
    }
    
    /**
     * 📤 رفع ملف إلى Google Drive
     */
    static async uploadFile(config: UploadConfig): Promise<UploadResult> {
        console.log('📤 Starting file upload via Rclone...');
        console.log(`   Local: ${config.localFilePath}`);
        console.log(`   Remote: ${config.remotePath}`);
        
        try {
            // Step 1: Generate file hash
            const fileHash = await this.calculateFileHash(config.localFilePath);
            
            // Step 2: Check for duplicate
            const isDuplicate = await this.checkDuplicate(fileHash);
            if (isDuplicate) {
                console.log('⚠️ File already uploaded (duplicate detected)');
                return {
                    success: false,
                    errorMessage: 'File already uploaded'
                };
            }
            
            // Step 3: Create tracking record
            const trackingId = await GoogleDriveUploadsTracker.createRecord({
                localFilePath: config.localFilePath,
                localFileHash: fileHash,
                entityType: config.entityType,
                entityId: config.entityId,
                attachmentType: config.attachmentType,
                attachmentDescription: config.attachmentDescription,
                uploadStatus: 'uploading'
            });
            
            // Step 4: Upload via Rclone
            const remotePath = this.buildRemotePath(config);
            
            const uploadResponse = await Http.request({
                method: 'POST',
                url: `${this.RCLONE_API_URL}/operations/copyfile`,
                headers: { 'Content-Type': 'application/json' },
                data: {
                    srcFs: '/',
                    srcRemote: config.localFilePath,
                    dstFs: `${this.REMOTE_NAME}:`,
                    dstRemote: remotePath
                }
            });
            
            if (uploadResponse.status !== 200) {
                throw new Error(`Rclone upload failed: ${uploadResponse.data}`);
            }
            
            // Step 5: Get file info from Google Drive
            const fileInfo = await this.getRemoteFileInfo(remotePath);
            
            // Step 6: Update tracking record
            await GoogleDriveUploadsTracker.updateRecord(trackingId, {
                uploadStatus: 'completed',
                uploadProgress: 100,
                googleDriveFileId: fileInfo.id,
                googleDrivePath: remotePath,
                uploadedAt: new Date().toISOString()
            });
            
            console.log('✅ File uploaded successfully');
            console.log(`   Google Drive ID: ${fileInfo.id}`);
            
            return {
                success: true,
                googleDriveFileId: fileInfo.id,
                googleDrivePath: remotePath
            };
            
        } catch (error) {
            console.error('❌ Upload failed:', error);
            
            // Update tracking record with error
            await GoogleDriveUploadsTracker.updateRecordByPath(config.localFilePath, {
                uploadStatus: 'failed',
                errorMessage: error.message,
                retryCount: (await GoogleDriveUploadsTracker.getRetryCount(config.localFilePath)) + 1
            });
            
            return {
                success: false,
                errorMessage: error.message
            };
        }
    }
    
    /**
     * 🔢 حساب hash للملف
     */
    private static async calculateFileHash(filePath: string): Promise<string> {
        // استخدام SHA256 لحساب hash
        const fileContent = await Filesystem.readFile({
            path: filePath,
            directory: Directory.Data
        });
        
        const hashBuffer = await crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(fileContent.data)
        );
        
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }
    
    /**
     * 🔍 التحقق من وجود الملف مسبقاً
     */
    private static async checkDuplicate(fileHash: string): Promise<boolean> {
        const result = await SQLiteService.query(`
            SELECT COUNT(*) as count 
            FROM google_drive_uploads 
            WHERE local_file_hash = ? 
            AND upload_status = 'completed'
        `, [fileHash]);
        
        return result[0]?.count > 0;
    }
    
    /**
     * 📁 بناء مسار الملف على Google Drive
     */
    private static buildRemotePath(config: UploadConfig): string {
        const basePath = 'AlHayah_Orphans';
        const entityFolder = this.getEntityFolder(config.entityType);
        const fileName = config.localFilePath.split('/').pop();
        const timestamp = new Date().getTime();
        
        return `${basePath}/${entityFolder}/${config.entityId}/${config.attachmentType}/${timestamp}_${fileName}`;
    }
    
    /**
     * 📂 الحصول على اسم مجلد الكيان
     */
    private static getEntityFolder(entityType: string): string {
        const folders = {
            'sponsorship': 'sponsorships',
            'orphan': 'orphans',
            'guardian': 'guardians',
            'deceased': 'deceased',
            'bank_account': 'bank_accounts'
        };
        return folders[entityType] || 'other';
    }
    
    /**
     * ℹ️ الحصول على معلومات الملف من Google Drive
     */
    private static async getRemoteFileInfo(remotePath: string): Promise<{id: string, size: number}> {
        const response = await Http.request({
            method: 'POST',
            url: `${this.RCLONE_API_URL}/operations/stat`,
            headers: { 'Content-Type': 'application/json' },
            data: {
                fs: `${this.REMOTE_NAME}:`,
                remote: remotePath
            }
        });
        
        return {
            id: response.data?.item?.ID || '',
            size: response.data?.item?.Size || 0
        };
    }
    
    /**
     * 🔄 إعادة محاولة رفع الملفات الفاشلة
     */
    static async retryFailedUploads(): Promise<{success: number, failed: number}> {
        const failedUploads = await SQLiteService.query(`
            SELECT * FROM google_drive_uploads 
            WHERE upload_status = 'failed' 
            AND retry_count < 3
        `);
        
        let success = 0;
        let failed = 0;
        
        for (const upload of failedUploads) {
            const result = await this.uploadFile({
                localFilePath: upload.local_file_path,
                remotePath: upload.google_drive_path,
                entityType: upload.entity_type,
                entityId: upload.entity_id,
                attachmentType: upload.attachment_type
            });
            
            if (result.success) {
                success++;
            } else {
                failed++;
            }
        }
        
        return { success, failed };
    }
}
```

### GoogleDriveUploadsTracker

```typescript
// services/google-drive-uploads-tracker.service.ts

interface UploadRecord {
    id?: number;
    localFilePath: string;
    localFileHash: string;
    fileName?: string;
    fileSizeBytes?: number;
    mimeType?: string;
    googleDriveFileId?: string;
    googleDrivePath?: string;
    uploadStatus: 'pending' | 'uploading' | 'completed' | 'failed' | 'cancelled';
    uploadProgress?: number;
    entityType: 'sponsorship' | 'orphan' | 'guardian' | 'deceased' | 'bank_account';
    entityId: string;
    attachmentType: string;
    attachmentDescription?: string;
    deviceId?: string;
    uploadedBy?: number;
    retryCount?: number;
    errorMessage?: string;
    syncedToServer?: boolean;
    serverAttachmentId?: number;
    createdAt?: string;
    uploadedAt?: string;
}

class GoogleDriveUploadsTracker {
    /**
     * ➕ إنشاء سجل جديد
     */
    static async createRecord(data: Partial<UploadRecord>): Promise<number> {
        const deviceId = await AuthService.getDeviceId();
        const userId = await AuthService.getUserId();
        const fileName = data.localFilePath?.split('/').pop() || '';
        const fileInfo = await this.getFileInfo(data.localFilePath!);
        
        const result = await SQLiteService.execute(`
            INSERT INTO google_drive_uploads (
                local_file_path, local_file_hash, file_name, file_size_bytes,
                mime_type, upload_status, upload_progress, entity_type,
                entity_id, attachment_type, attachment_description,
                device_id, uploaded_by, retry_count, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `, [
            data.localFilePath,
            data.localFileHash,
            fileName,
            fileInfo.size,
            fileInfo.mimeType,
            data.uploadStatus || 'pending',
            data.uploadProgress || 0,
            data.entityType,
            data.entityId,
            data.attachmentType,
            data.attachmentDescription || null,
            deviceId,
            userId,
            0,
            new Date().toISOString()
        ]);
        
        return result.insertId;
    }
    
    /**
     * 📝 تحديث سجل
     */
    static async updateRecord(id: number, updates: Partial<UploadRecord>): Promise<void> {
        const fields: string[] = [];
        const values: any[] = [];
        
        Object.entries(updates).forEach(([key, value]) => {
            if (value !== undefined) {
                // Convert camelCase to snake_case
                const snakeKey = key.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
                fields.push(`${snakeKey} = ?`);
                values.push(value);
            }
        });
        
        fields.push('updated_at = ?');
        values.push(new Date().toISOString());
        values.push(id);
        
        await SQLiteService.execute(`
            UPDATE google_drive_uploads 
            SET ${fields.join(', ')}
            WHERE id = ?
        `, values);
    }
    
    /**
     * 📊 الحصول على إحصائيات الرفع
     */
    static async getUploadStats(): Promise<{
        pending: number;
        uploading: number;
        completed: number;
        failed: number;
        totalSize: number;
        uploadedSize: number;
    }> {
        const result = await SQLiteService.query(`
            SELECT 
                SUM(CASE WHEN upload_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN upload_status = 'uploading' THEN 1 ELSE 0 END) as uploading,
                SUM(CASE WHEN upload_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN upload_status = 'failed' THEN 1 ELSE 0 END) as failed,
                COALESCE(SUM(file_size_bytes), 0) as total_size,
                COALESCE(SUM(CASE WHEN upload_status = 'completed' THEN file_size_bytes ELSE 0 END), 0) as uploaded_size
            FROM google_drive_uploads
        `);
        
        return {
            pending: result[0]?.pending || 0,
            uploading: result[0]?.uploading || 0,
            completed: result[0]?.completed || 0,
            failed: result[0]?.failed || 0,
            totalSize: result[0]?.total_size || 0,
            uploadedSize: result[0]?.uploaded_size || 0
        };
    }
    
    /**
     * 📋 الحصول على الملفات غير المرفوعة
     */
    static async getPendingUploads(): Promise<UploadRecord[]> {
        return await SQLiteService.query(`
            SELECT * FROM google_drive_uploads 
            WHERE upload_status IN ('pending', 'uploading')
            ORDER BY created_at ASC
        `);
    }
    
    /**
     * 📋 الحصول على الملفات غير المتزامنة مع الخادم
     */
    static async getUnsyncedUploads(): Promise<UploadRecord[]> {
        return await SQLiteService.query(`
            SELECT * FROM google_drive_uploads 
            WHERE upload_status = 'completed'
            AND synced_to_server = 0
            ORDER BY uploaded_at ASC
        `);
    }
    
    /**
     * ℹ️ الحصول على معلومات الملف
     */
    private static async getFileInfo(filePath: string): Promise<{size: number, mimeType: string}> {
        try {
            const stat = await Filesystem.stat({
                path: filePath,
                directory: Directory.Data
            });
            
            const extension = filePath.split('.').pop()?.toLowerCase() || '';
            const mimeTypes: Record<string, string> = {
                'jpg': 'image/jpeg',
                'jpeg': 'image/jpeg',
                'png': 'image/png',
                'gif': 'image/gif',
                'mp4': 'video/mp4',
                'mov': 'video/quicktime',
                'pdf': 'application/pdf'
            };
            
            return {
                size: stat.size || 0,
                mimeType: mimeTypes[extension] || 'application/octet-stream'
            };
        } catch {
            return { size: 0, mimeType: 'application/octet-stream' };
        }
    }
}
```

---

## 🔧 Laravel Implementation

### Model: GoogleDriveUpload

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleDriveUpload extends Model
{
    use HasFactory;
    
    protected $table = 'google_drive_uploads';
    
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
    
    protected $casts = [
        'file_size_bytes' => 'integer',
        'upload_progress' => 'integer',
        'retry_count' => 'integer',
        'synced_to_server' => 'boolean',
        'uploaded_at' => 'datetime'
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    
    public function attachment()
    {
        return $this->belongsTo(Attachment::class, 'server_attachment_id');
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('upload_status', 'pending');
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('upload_status', 'completed');
    }
    
    public function scopeUnsynced($query)
    {
        return $query->where('upload_status', 'completed')
                     ->where('synced_to_server', false);
    }
    
    public function scopeForEntity($query, $type, $id)
    {
        return $query->where('entity_type', $type)
                     ->where('entity_id', $id);
    }
}
```

### Controller: GoogleDriveUploadController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoogleDriveUpload;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleDriveUploadController extends Controller
{
    /**
     * POST /api/uploads/check-duplicate
     * التحقق من وجود ملف مكرر
     */
    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'file_hash' => 'required|string|size:64'
        ]);
        
        $exists = GoogleDriveUpload::where('local_file_hash', $request->file_hash)
            ->where('upload_status', 'completed')
            ->exists();
        
        return response()->json([
            'exists' => $exists
        ]);
    }
    
    /**
     * POST /api/uploads/notify-completed
     * إخطار Laravel بنجاح رفع ملف
     */
    public function notifyCompleted(Request $request)
    {
        $request->validate([
            'google_drive_file_id' => 'required|string',
            'google_drive_path' => 'required|string',
            'file_hash' => 'required|string',
            'file_name' => 'required|string',
            'file_size_bytes' => 'required|integer',
            'mime_type' => 'required|string',
            'entity_type' => 'required|in:sponsorship,orphan,guardian,deceased,bank_account',
            'entity_id' => 'required|string',
            'attachment_type' => 'required|string',
            'device_id' => 'required|string'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Step 1: Create or update google_drive_uploads record
            $upload = GoogleDriveUpload::updateOrCreate(
                ['local_file_hash' => $request->file_hash],
                [
                    'google_drive_file_id' => $request->google_drive_file_id,
                    'google_drive_path' => $request->google_drive_path,
                    'file_name' => $request->file_name,
                    'file_size_bytes' => $request->file_size_bytes,
                    'mime_type' => $request->mime_type,
                    'upload_status' => 'completed',
                    'upload_progress' => 100,
                    'entity_type' => $request->entity_type,
                    'entity_id' => $request->entity_id,
                    'attachment_type' => $request->attachment_type,
                    'attachment_description' => $request->attachment_description,
                    'device_id' => $request->device_id,
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now(),
                    'synced_to_server' => true
                ]
            );
            
            // Step 2: Create attachment record
            $attachment = Attachment::create([
                'file_name' => $request->file_name,
                'stored_file_name' => $request->google_drive_file_id,
                'file_path' => $request->google_drive_path,
                'file_size' => $request->file_size_bytes,
                'mime_type' => $request->mime_type,
                'attachment_type' => $request->attachment_type,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'storage_type' => 'google_drive',
                'uploaded_by' => auth()->id()
            ]);
            
            // Step 3: Link upload to attachment
            $upload->update([
                'server_attachment_id' => $attachment->id
            ]);
            
            DB::commit();
            
            Log::info('File upload notification received', [
                'google_drive_file_id' => $request->google_drive_file_id,
                'attachment_id' => $attachment->id
            ]);
            
            return response()->json([
                'success' => true,
                'attachment_id' => $attachment->id,
                'upload_id' => $upload->id,
                'message' => 'تم تسجيل الملف بنجاح'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('File upload notification failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل تسجيل الملف: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/uploads/stats
     * إحصائيات الرفع
     */
    public function getStats()
    {
        $stats = [
            'pending' => GoogleDriveUpload::pending()->count(),
            'completed' => GoogleDriveUpload::completed()->count(),
            'failed' => GoogleDriveUpload::where('upload_status', 'failed')->count(),
            'unsynced' => GoogleDriveUpload::unsynced()->count(),
            'total_size' => GoogleDriveUpload::sum('file_size_bytes'),
            'uploaded_size' => GoogleDriveUpload::completed()->sum('file_size_bytes')
        ];
        
        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * GET /api/uploads/entity/{type}/{id}
     * الحصول على ملفات كيان معين
     */
    public function getEntityUploads($type, $id)
    {
        $uploads = GoogleDriveUpload::forEntity($type, $id)
            ->where('upload_status', 'completed')
            ->orderBy('uploaded_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'uploads' => $uploads
        ]);
    }
}
```

### Routes

```php
// routes/api.php

Route::prefix('uploads')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/check-duplicate', [GoogleDriveUploadController::class, 'checkDuplicate']);
    Route::post('/notify-completed', [GoogleDriveUploadController::class, 'notifyCompleted']);
    Route::get('/stats', [GoogleDriveUploadController::class, 'getStats']);
    Route::get('/entity/{type}/{id}', [GoogleDriveUploadController::class, 'getEntityUploads']);
});
```

---

## 🔄 Sync Flow with Laravel Notification

```typescript
// After successful upload
async function syncUploadWithServer(uploadRecord: UploadRecord): Promise<boolean> {
    try {
        const response = await ApiService.post('/api/uploads/notify-completed', {
            google_drive_file_id: uploadRecord.googleDriveFileId,
            google_drive_path: uploadRecord.googleDrivePath,
            file_hash: uploadRecord.localFileHash,
            file_name: uploadRecord.fileName,
            file_size_bytes: uploadRecord.fileSizeBytes,
            mime_type: uploadRecord.mimeType,
            entity_type: uploadRecord.entityType,
            entity_id: uploadRecord.entityId,
            attachment_type: uploadRecord.attachmentType,
            attachment_description: uploadRecord.attachmentDescription,
            device_id: uploadRecord.deviceId
        });
        
        if (response.success) {
            // Update local record
            await GoogleDriveUploadsTracker.updateRecord(uploadRecord.id!, {
                syncedToServer: true,
                serverAttachmentId: response.attachment_id
            });
            
            console.log('✅ Upload synced with server');
            return true;
        }
        
        return false;
    } catch (error) {
        console.error('Failed to sync upload with server:', error);
        return false;
    }
}

// Background sync service
async function syncAllPendingUploads(): Promise<void> {
    const unsyncedUploads = await GoogleDriveUploadsTracker.getUnsyncedUploads();
    
    for (const upload of unsyncedUploads) {
        await syncUploadWithServer(upload);
    }
}
```

---

**Document Version**: 1.0  
**Last Updated**: January 10, 2026  
**Author**: GitHub Copilot
