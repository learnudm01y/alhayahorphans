/**
 * Google Drive Uploads Tracker Service for AlHayah Orphans Mobile App
 *
 * Tracks file uploads to Google Drive to prevent duplicates
 * Works with local SQLite database and syncs with Laravel server
 */

import { database, GoogleDriveUploadRecord } from './DatabaseService';
import { api, UploadNotification } from './ApiService';
import { fileHash } from './FileHashService';
import { config } from './ConfigService';

export interface UploadTask {
  localFilePath: string;
  fileHash?: string;
  entityType: 'photo' | 'document' | 'attachment';
  entityId: string;
  originalFilename: string;
  fileSize: number;
}

export interface UploadProgress {
  taskId: number;
  progress: number;
  status: 'pending' | 'hashing' | 'checking' | 'uploading' | 'completed' | 'failed';
  message?: string;
}

export type UploadProgressCallback = (progress: UploadProgress) => void;

class GoogleDriveUploadsTracker {
  private static instance: GoogleDriveUploadsTracker;
  private deviceId: string;
  private progressCallbacks: Map<number, UploadProgressCallback> = new Map();

  private constructor() {
    this.deviceId = this.getOrCreateDeviceId();
  }

  public static getInstance(): GoogleDriveUploadsTracker {
    if (!GoogleDriveUploadsTracker.instance) {
      GoogleDriveUploadsTracker.instance = new GoogleDriveUploadsTracker();
    }
    return GoogleDriveUploadsTracker.instance;
  }

  /**
   * Get or create unique device ID
   */
  private getOrCreateDeviceId(): string {
    let deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
      deviceId = 'device_' + Date.now().toString(36) + Math.random().toString(36).substr(2);
      localStorage.setItem('device_id', deviceId);
    }
    return deviceId;
  }

  /**
   * Check if file already uploaded (by hash)
   * First checks local DB, then server
   */
  public async isDuplicate(filePath: string): Promise<{
    isDuplicate: boolean;
    existingUpload?: GoogleDriveUploadRecord;
    checkedServer: boolean;
  }> {
    // Calculate file hash
    const hash = await fileHash.hashFile(filePath);

    // Check local database first
    const localRecord = await database.getOne<GoogleDriveUploadRecord>(
      'google_drive_uploads',
      'local_file_hash = ? AND upload_status = ?',
      [hash, 'completed']
    );

    if (localRecord) {
      return {
        isDuplicate: true,
        existingUpload: localRecord,
        checkedServer: false
      };
    }

    // Check server (if online)
    try {
      const serverResponse = await api.checkUploadDuplicate(hash);

      if (serverResponse.success && serverResponse.data?.exists) {
        // Save to local DB for future reference
        await database.insert('google_drive_uploads', {
          local_file_path: filePath,
          local_file_hash: hash,
          google_drive_file_id: serverResponse.data.upload?.google_drive_file_id,
          upload_status: 'completed',
          entity_type: serverResponse.data.upload?.entity_type || 'attachment',
          entity_id: serverResponse.data.upload?.entity_id || '',
          file_size: 0,
          uploaded_bytes: 0,
          retry_count: 0,
          synced_to_server: 1
        });

        return {
          isDuplicate: true,
          checkedServer: true
        };
      }

      return {
        isDuplicate: false,
        checkedServer: true
      };
    } catch (error) {
      // Network error, return not duplicate (will upload)
      console.warn('Failed to check server for duplicate:', error);
      return {
        isDuplicate: false,
        checkedServer: false
      };
    }
  }

  /**
   * Register upload task (before starting upload)
   */
  public async registerUpload(task: UploadTask): Promise<number> {
    // Calculate hash if not provided
    const hash = task.fileHash || await fileHash.hashFile(task.localFilePath);

    // Check for duplicate first
    const duplicateCheck = await this.isDuplicate(task.localFilePath);
    if (duplicateCheck.isDuplicate) {
      throw new Error('DUPLICATE: File already uploaded');
    }

    // Insert new upload record
    const taskId = await database.insert('google_drive_uploads', {
      local_file_path: task.localFilePath,
      local_file_hash: hash,
      upload_status: 'pending',
      entity_type: task.entityType,
      entity_id: task.entityId,
      file_size: task.fileSize,
      uploaded_bytes: 0,
      retry_count: 0,
      synced_to_server: 0
    });

    return taskId;
  }

  /**
   * Update upload progress
   */
  public async updateProgress(
    taskId: number,
    uploadedBytes: number,
    status: 'uploading' | 'completed' | 'failed',
    googleDriveFileId?: string,
    errorMessage?: string
  ): Promise<void> {
    const updateData: Record<string, any> = {
      uploaded_bytes: uploadedBytes,
      upload_status: status
    };

    if (googleDriveFileId) {
      updateData.google_drive_file_id = googleDriveFileId;
    }

    if (errorMessage) {
      updateData.last_error = errorMessage;
    }

    if (status === 'failed') {
      // Increment retry count
      const record = await database.getOne<GoogleDriveUploadRecord>(
        'google_drive_uploads',
        'id = ?',
        [taskId]
      );
      if (record) {
        updateData.retry_count = record.retry_count + 1;
      }
    }

    await database.update(
      'google_drive_uploads',
      updateData,
      'id = ?',
      [taskId]
    );

    // Notify progress callback
    const callback = this.progressCallbacks.get(taskId);
    if (callback) {
      const record = await database.getOne<GoogleDriveUploadRecord>(
        'google_drive_uploads',
        'id = ?',
        [taskId]
      );
      if (record) {
        callback({
          taskId,
          progress: record.file_size > 0 ? (uploadedBytes / record.file_size) * 100 : 0,
          status,
          message: errorMessage
        });
      }
    }
  }

  /**
   * Mark upload as completed and notify server
   */
  public async markCompleted(
    taskId: number,
    googleDriveFileId: string,
    originalFilename: string
  ): Promise<void> {
    // Get upload record
    const record = await database.getOne<GoogleDriveUploadRecord>(
      'google_drive_uploads',
      'id = ?',
      [taskId]
    );

    if (!record) {
      throw new Error('Upload record not found');
    }

    // Update local record
    await database.update(
      'google_drive_uploads',
      {
        google_drive_file_id: googleDriveFileId,
        upload_status: 'completed',
        uploaded_bytes: record.file_size
      },
      'id = ?',
      [taskId]
    );

    // Notify server
    try {
      const notification: UploadNotification = {
        local_file_hash: record.local_file_hash,
        google_drive_file_id: googleDriveFileId,
        entity_type: record.entity_type,
        entity_id: record.entity_id,
        original_filename: originalFilename,
        file_size: record.file_size,
        uploaded_by_device_id: this.deviceId
      };

      const response = await api.notifyUploadCompleted(notification);

      if (response.success) {
        await database.update(
          'google_drive_uploads',
          { synced_to_server: 1 },
          'id = ?',
          [taskId]
        );
      }
    } catch (error) {
      console.warn('Failed to notify server of upload completion:', error);
      // Will be synced later
    }
  }

  /**
   * Mark upload as failed
   */
  public async markFailed(taskId: number, errorMessage: string): Promise<void> {
    const record = await database.getOne<GoogleDriveUploadRecord>(
      'google_drive_uploads',
      'id = ?',
      [taskId]
    );

    if (!record) return;

    await database.update(
      'google_drive_uploads',
      {
        upload_status: 'failed',
        last_error: errorMessage,
        retry_count: record.retry_count + 1
      },
      'id = ?',
      [taskId]
    );

    // Notify server if online
    try {
      await api.notifyUploadFailed(record.local_file_hash, errorMessage);
    } catch {
      // Ignore - will sync later
    }
  }

  /**
   * Get pending uploads (for retry)
   */
  public async getPendingUploads(): Promise<GoogleDriveUploadRecord[]> {
    return database.getAll<GoogleDriveUploadRecord>(
      'google_drive_uploads',
      "upload_status IN ('pending', 'failed') AND retry_count < ?",
      [config.get('maxRetries')],
      'created_at ASC'
    );
  }

  /**
   * Get unsynced completed uploads
   */
  public async getUnsyncedUploads(): Promise<GoogleDriveUploadRecord[]> {
    return database.getAll<GoogleDriveUploadRecord>(
      'google_drive_uploads',
      "upload_status = 'completed' AND synced_to_server = 0"
    );
  }

  /**
   * Sync unsynced uploads with server
   */
  public async syncWithServer(): Promise<{ synced: number; failed: number }> {
    const unsynced = await this.getUnsyncedUploads();
    let synced = 0;
    let failed = 0;

    for (const record of unsynced) {
      try {
        const notification: UploadNotification = {
          local_file_hash: record.local_file_hash,
          google_drive_file_id: record.google_drive_file_id || '',
          entity_type: record.entity_type,
          entity_id: record.entity_id,
          original_filename: record.local_file_path.split('/').pop() || 'unknown',
          file_size: record.file_size,
          uploaded_by_device_id: this.deviceId
        };

        const response = await api.notifyUploadCompleted(notification);

        if (response.success) {
          await database.update(
            'google_drive_uploads',
            { synced_to_server: 1 },
            'id = ?',
            [record.id]
          );
          synced++;
        } else {
          failed++;
        }
      } catch (error) {
        console.warn('Failed to sync upload:', error);
        failed++;
      }
    }

    return { synced, failed };
  }

  /**
   * Get upload statistics
   */
  public async getStats(): Promise<{
    total: number;
    pending: number;
    uploading: number;
    completed: number;
    failed: number;
    unsynced: number;
  }> {
    const total = await database.count('google_drive_uploads');
    const pending = await database.count('google_drive_uploads', "upload_status = 'pending'");
    const uploading = await database.count('google_drive_uploads', "upload_status = 'uploading'");
    const completed = await database.count('google_drive_uploads', "upload_status = 'completed'");
    const failed = await database.count('google_drive_uploads', "upload_status = 'failed'");
    const unsynced = await database.count(
      'google_drive_uploads',
      "upload_status = 'completed' AND synced_to_server = 0"
    );

    return { total, pending, uploading, completed, failed, unsynced };
  }

  /**
   * Register progress callback for a task
   */
  public onProgress(taskId: number, callback: UploadProgressCallback): void {
    this.progressCallbacks.set(taskId, callback);
  }

  /**
   * Remove progress callback
   */
  public removeProgressCallback(taskId: number): void {
    this.progressCallbacks.delete(taskId);
  }

  /**
   * Clean up old completed uploads from local database
   * Keeps last 30 days by default
   */
  public async cleanup(daysToKeep: number = 30): Promise<number> {
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - daysToKeep);
    const cutoffStr = cutoffDate.toISOString();

    const deleted = await database.delete(
      'google_drive_uploads',
      "upload_status = 'completed' AND synced_to_server = 1 AND created_at < ?",
      [cutoffStr]
    );

    return deleted;
  }
}

export const uploadsTracker = GoogleDriveUploadsTracker.getInstance();
export default GoogleDriveUploadsTracker;
