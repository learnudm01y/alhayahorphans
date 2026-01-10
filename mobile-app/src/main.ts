/**
 * Main Application Entry Point
 * AlHayah Orphans Mobile App
 */

import { initializeServices, syncProgress, config } from './services';
import type { OverallSyncStatus } from './services';

class App {
  private statusElement: HTMLElement | null = null;
  private progressElement: HTMLElement | null = null;
  private unsubscribeSyncProgress?: () => void;

  async init(): Promise<void> {
    console.log('AlHayah Orphans Mobile App starting...');

    // Get DOM elements
    this.statusElement = document.getElementById('sync-status');
    this.progressElement = document.getElementById('sync-progress');

    // Show loading state
    this.updateUI({ loading: true });

    try {
      // Initialize services
      await initializeServices();

      // Subscribe to sync progress
      this.unsubscribeSyncProgress = syncProgress.onProgress(
        this.handleSyncProgress.bind(this)
      );

      // App ready
      this.updateUI({ loading: false, ready: true });

      console.log('App initialized successfully');
    } catch (error) {
      console.error('Failed to initialize app:', error);
      this.updateUI({ loading: false, error: true });
    }
  }

  private handleSyncProgress(status: OverallSyncStatus): void {
    this.updateSyncUI(status);
  }

  private updateSyncUI(status: OverallSyncStatus): void {
    if (!this.statusElement) return;

    const online = status.isOnline ? '🟢 متصل' : '🔴 غير متصل';
    const syncing = status.isSyncing ? '🔄 مزامنة...' : '✅ جاهز';

    this.statusElement.innerHTML = `
      <div class="sync-status-bar">
        <span class="network-status">${online}</span>
        <span class="sync-state">${syncing}</span>
      </div>
      <div class="sync-details">
        <span>المكتملة: ${status.completedTasks}</span>
        <span>المعلقة: ${status.pendingTasks}</span>
        <span>الفاشلة: ${status.failedTasks}</span>
      </div>
    `;

    if (this.progressElement && status.isSyncing) {
      this.progressElement.style.width = `${status.overallProgress}%`;
    }
  }

  private updateUI(state: { loading?: boolean; ready?: boolean; error?: boolean }): void {
    const appElement = document.getElementById('app');
    if (!appElement) return;

    if (state.loading) {
      appElement.innerHTML = `
        <div class="loading-screen">
          <div class="spinner"></div>
          <p>جاري تحميل التطبيق...</p>
        </div>
      `;
    } else if (state.error) {
      appElement.innerHTML = `
        <div class="error-screen">
          <p>❌ حدث خطأ في تحميل التطبيق</p>
          <button onclick="location.reload()">إعادة المحاولة</button>
        </div>
      `;
    } else if (state.ready) {
      // Keep existing content, just update status
    }
  }

  destroy(): void {
    if (this.unsubscribeSyncProgress) {
      this.unsubscribeSyncProgress();
    }
  }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  const app = new App();
  app.init();

  // Make app available globally for debugging
  (window as any).app = app;
});

export default App;
