/**
 * Sync Progress Tracker Service for AlHayah Orphans Mobile App
 *
 * Tracks overall sync progress, provides UI updates, and manages sync queue
 * Critical for user experience - shows real-time progress of all operations
 */

import { database, SyncProgressRecord } from './DatabaseService';
import { Network } from '@capacitor/network';

export type SyncOperationType =
  | 'person_check'
  | 'file_id_generation'
  | 'person_creation'
  | 'file_upload'
  | 'server_notification'
  | 'batch_sync';

export type SyncStatus = 'pending' | 'in_progress' | 'completed' | 'failed' | 'cancelled';

export interface SyncTask {
  id?: number;
  operationType: SyncOperationType;
  entityType: string;
  entityId: string;
  priority?: number;
  data?: any;
}

export interface SyncProgress {
  taskId: number;
  operationType: SyncOperationType;
  entityType: string;
  entityId: string;
  progress: number;
  status: SyncStatus;
  message?: string;
  startedAt?: Date;
  completedAt?: Date;
}

export interface OverallSyncStatus {
  isOnline: boolean;
  isSyncing: boolean;
  totalTasks: number;
  completedTasks: number;
  failedTasks: number;
  pendingTasks: number;
  overallProgress: number;
  lastSyncAt?: Date;
  currentTask?: SyncProgress;
}

export type SyncProgressCallback = (status: OverallSyncStatus) => void;

class SyncProgressTracker {
  private static instance: SyncProgressTracker;
  private isOnline: boolean = true;
  private isSyncing: boolean = false;
  private syncQueue: SyncTask[] = [];
  private currentTask: SyncProgress | null = null;
  private progressCallbacks: Set<SyncProgressCallback> = new Set();
  private networkUnsubscribe?: () => void;

  private constructor() {
    this.initNetworkListener();
  }

  public static getInstance(): SyncProgressTracker {
    if (!SyncProgressTracker.instance) {
      SyncProgressTracker.instance = new SyncProgressTracker();
    }
    return SyncProgressTracker.instance;
  }

  /**
   * Initialize network status listener
   */
  private async initNetworkListener(): Promise<void> {
    try {
      // Get initial status
      const status = await Network.getStatus();
      this.isOnline = status.connected;

      // Listen for changes
      this.networkUnsubscribe = Network.addListener(
        'networkStatusChange',
        (status) => {
          this.isOnline = status.connected;
          this.notifyListeners();

          // Auto-resume sync when back online
          if (status.connected && this.syncQueue.length > 0) {
            this.processQueue();
          }
        }
      ) as any;
    } catch (error) {
      console.warn('Failed to initialize network listener:', error);
      this.isOnline = true; // Assume online
    }
  }

  /**
   * Add task to sync queue
   */
  public async queueTask(task: SyncTask): Promise<number> {
    const taskId = await database.insert('sync_progress', {
      operation_type: task.operationType,
      entity_type: task.entityType,
      entity_id: task.entityId,
      progress: 0,
      status: 'pending'
    });

    task.id = taskId;
    this.syncQueue.push(task);

    this.notifyListeners();

    // Start processing if not already syncing
    if (!this.isSyncing && this.isOnline) {
      this.processQueue();
    }

    return taskId;
  }

  /**
   * Update task progress
   */
  public async updateProgress(
    taskId: number,
    progress: number,
    status: SyncStatus,
    message?: string
  ): Promise<void> {
    await database.update(
      'sync_progress',
      { progress, status, error_message: message },
      'id = ?',
      [taskId]
    );

    if (this.currentTask?.taskId === taskId) {
      this.currentTask.progress = progress;
      this.currentTask.status = status;
      this.currentTask.message = message;

      if (status === 'completed' || status === 'failed') {
        this.currentTask.completedAt = new Date();
      }
    }

    this.notifyListeners();
  }

  /**
   * Mark task as started
   */
  public async startTask(taskId: number): Promise<void> {
    await database.update(
      'sync_progress',
      { status: 'in_progress', progress: 0 },
      'id = ?',
      [taskId]
    );

    const record = await database.getOne<SyncProgressRecord>(
      'sync_progress',
      'id = ?',
      [taskId]
    );

    if (record) {
      this.currentTask = {
        taskId: record.id!,
        operationType: record.operation_type as SyncOperationType,
        entityType: record.entity_type,
        entityId: record.entity_id,
        progress: 0,
        status: 'in_progress',
        startedAt: new Date()
      };
    }

    this.notifyListeners();
  }

  /**
   * Mark task as completed
   */
  public async completeTask(taskId: number, message?: string): Promise<void> {
    await this.updateProgress(taskId, 100, 'completed', message);

    // Remove from queue
    this.syncQueue = this.syncQueue.filter(t => t.id !== taskId);

    if (this.currentTask?.taskId === taskId) {
      this.currentTask = null;
    }

    this.notifyListeners();
  }

  /**
   * Mark task as failed
   */
  public async failTask(taskId: number, errorMessage: string): Promise<void> {
    await this.updateProgress(taskId, 0, 'failed', errorMessage);

    // Keep in queue for retry

    if (this.currentTask?.taskId === taskId) {
      this.currentTask = null;
    }

    this.notifyListeners();
  }

  /**
   * Cancel task
   */
  public async cancelTask(taskId: number): Promise<void> {
    await database.update(
      'sync_progress',
      { status: 'cancelled' },
      'id = ?',
      [taskId]
    );

    this.syncQueue = this.syncQueue.filter(t => t.id !== taskId);

    if (this.currentTask?.taskId === taskId) {
      this.currentTask = null;
    }

    this.notifyListeners();
  }

  /**
   * Process sync queue
   */
  private async processQueue(): Promise<void> {
    if (this.isSyncing || !this.isOnline || this.syncQueue.length === 0) {
      return;
    }

    this.isSyncing = true;
    this.notifyListeners();

    while (this.syncQueue.length > 0 && this.isOnline) {
      // Get next task (highest priority first)
      const task = this.syncQueue.shift()!;

      try {
        await this.startTask(task.id!);

        // Execute task based on type
        await this.executeTask(task);

        await this.completeTask(task.id!);
      } catch (error: any) {
        console.error(`Task ${task.id} failed:`, error);
        await this.failTask(task.id!, error.message);

        // Re-queue failed tasks at the end (for retry)
        this.syncQueue.push(task);
      }

      // Small delay between tasks
      await this.delay(100);
    }

    this.isSyncing = false;
    this.notifyListeners();
  }

  /**
   * Execute a sync task
   */
  private async executeTask(task: SyncTask): Promise<void> {
    // This would call the appropriate service based on task type
    // For now, just simulate work

    for (let progress = 0; progress <= 100; progress += 20) {
      await this.updateProgress(task.id!, progress, 'in_progress');
      await this.delay(50);
    }
  }

  /**
   * Get overall sync status
   */
  public async getOverallStatus(): Promise<OverallSyncStatus> {
    const total = await database.count('sync_progress');
    const completed = await database.count('sync_progress', "status = 'completed'");
    const failed = await database.count('sync_progress', "status = 'failed'");
    const pending = await database.count('sync_progress', "status IN ('pending', 'in_progress')");

    const overallProgress = total > 0 ? (completed / total) * 100 : 0;

    return {
      isOnline: this.isOnline,
      isSyncing: this.isSyncing,
      totalTasks: total,
      completedTasks: completed,
      failedTasks: failed,
      pendingTasks: pending,
      overallProgress,
      currentTask: this.currentTask || undefined,
      lastSyncAt: this.getLastSyncDate()
    };
  }

  /**
   * Get last sync date
   */
  private getLastSyncDate(): Date | undefined {
    try {
      const stored = localStorage.getItem('last_sync_at');
      return stored ? new Date(stored) : undefined;
    } catch {
      return undefined;
    }
  }

  /**
   * Update last sync date
   */
  public updateLastSyncDate(): void {
    localStorage.setItem('last_sync_at', new Date().toISOString());
  }

  /**
   * Register progress callback
   */
  public onProgress(callback: SyncProgressCallback): () => void {
    this.progressCallbacks.add(callback);

    // Immediately notify with current status
    this.getOverallStatus().then(status => callback(status));

    // Return unsubscribe function
    return () => {
      this.progressCallbacks.delete(callback);
    };
  }

  /**
   * Notify all listeners
   */
  private notifyListeners(): void {
    this.getOverallStatus().then(status => {
      this.progressCallbacks.forEach(callback => {
        try {
          callback(status);
        } catch (error) {
          console.error('Callback error:', error);
        }
      });
    });
  }

  /**
   * Get pending tasks for retry
   */
  public async getPendingTasks(): Promise<SyncProgressRecord[]> {
    return database.getAll<SyncProgressRecord>(
      'sync_progress',
      "status IN ('pending', 'failed')",
      [],
      'created_at ASC'
    );
  }

  /**
   * Retry all failed tasks
   */
  public async retryFailedTasks(): Promise<number> {
    const failed = await database.getAll<SyncProgressRecord>(
      'sync_progress',
      "status = 'failed'"
    );

    let retried = 0;

    for (const record of failed) {
      await database.update(
        'sync_progress',
        { status: 'pending', progress: 0, error_message: null },
        'id = ?',
        [record.id]
      );

      this.syncQueue.push({
        id: record.id,
        operationType: record.operation_type as SyncOperationType,
        entityType: record.entity_type,
        entityId: record.entity_id
      });

      retried++;
    }

    if (retried > 0 && this.isOnline) {
      this.processQueue();
    }

    return retried;
  }

  /**
   * Clear completed tasks older than specified days
   */
  public async clearOldTasks(daysOld: number = 7): Promise<number> {
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - daysOld);

    return database.delete(
      'sync_progress',
      "status = 'completed' AND created_at < ?",
      [cutoff.toISOString()]
    );
  }

  /**
   * Force sync now
   */
  public async forceSyncNow(): Promise<void> {
    if (this.isOnline) {
      await this.processQueue();
    }
  }

  /**
   * Pause syncing
   */
  public pauseSync(): void {
    this.isSyncing = false;
    this.notifyListeners();
  }

  /**
   * Resume syncing
   */
  public resumeSync(): void {
    if (this.isOnline) {
      this.processQueue();
    }
  }

  /**
   * Clean up
   */
  public destroy(): void {
    if (this.networkUnsubscribe) {
      this.networkUnsubscribe();
    }
    this.progressCallbacks.clear();
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

export const syncProgress = SyncProgressTracker.getInstance();
export default SyncProgressTracker;
