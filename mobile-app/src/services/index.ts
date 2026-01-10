/**
 * Services Index for AlHayah Orphans Mobile App
 *
 * Central export point for all services
 */

// Configuration
export { config, default as ConfigService } from './ConfigService';
export type { AppConfig } from './ConfigService';

// Database
export { database, default as DatabaseService } from './DatabaseService';
export type {
  DatabaseRecord,
  PersonRecord,
  SyncProgressRecord,
  GoogleDriveUploadRecord
} from './DatabaseService';

// API
export { api, default as ApiService } from './ApiService';
export type {
  ApiResponse,
  PersonCheckResult,
  FileIdGenerationResult,
  UploadNotification
} from './ApiService';

// File Hashing
export { fileHash, default as FileHashService } from './FileHashService';

// Google Drive Uploads Tracker
export { uploadsTracker, default as GoogleDriveUploadsTracker } from './GoogleDriveUploadsTracker';
export type { UploadTask, UploadProgress, UploadProgressCallback } from './GoogleDriveUploadsTracker';

// Rclone
export { rclone, default as RcloneService } from './RcloneService';
export type { RcloneConfig, UploadResult } from './RcloneService';

// Sync Progress
export { syncProgress, default as SyncProgressTracker } from './SyncProgressTracker';
export type {
  SyncOperationType,
  SyncStatus,
  SyncTask,
  SyncProgress,
  OverallSyncStatus,
  SyncProgressCallback
} from './SyncProgressTracker';

// Person Sync
export { personSync, default as PersonSyncService } from './PersonSyncService';
export type { PersonData, FileIdResult } from './PersonSyncService';

/**
 * Initialize all services
 */
export async function initializeServices(): Promise<void> {
  console.log('Initializing services...');

  // Initialize database first
  const { database } = await import('./DatabaseService');
  await database.init();

  // Initialize other services
  const { rclone } = await import('./RcloneService');
  await rclone.checkAvailability();

  console.log('All services initialized');
}

/**
 * Clean up all services
 */
export function cleanupServices(): void {
  const { syncProgress } = require('./SyncProgressTracker');
  syncProgress.destroy();
}
