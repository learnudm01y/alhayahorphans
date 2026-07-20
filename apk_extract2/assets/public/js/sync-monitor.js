/**
 * Sync Monitor JavaScript
 * Real-time monitoring and tracking of sync operations
 *
 * Features:
 * - Real-time progress updates
 * - Filtering and searching
 * - Detailed statistics
 * - Session history
 * - Conflict and error handling
 */

class SyncMonitor {
    constructor() {
        this.currentSessionId = null;
        this.progressItems = [];
        this.filters = {
            operationType: '',
            status: '',
            searchText: ''
        };
        this.updateInterval = null;
        this.isAutoRefreshEnabled = true;

        console.log('🚀 Initializing Sync Monitor...');
        this.initialize();
    }

    async initialize() {
        try {
            // Setup event listeners
            this.setupEventListeners();

            // Load current session
            await this.loadCurrentSession();

            // Load session history
            await this.loadSessionHistory();

            // Subscribe to real-time updates
            this.subscribeToUpdates();

            // Start auto-refresh (every 3 seconds)
            this.startAutoRefresh();

            console.log('✅ Sync Monitor initialized successfully');
        } catch (error) {
            console.error('❌ Failed to initialize Sync Monitor:', error);
            this.showError('فشل تهيئة نظام المتابعة');
        }
    }

    setupEventListeners() {
        // Start sync button
        const btnStartSync = document.getElementById('btnStartSync');
        if (btnStartSync) {
            btnStartSync.addEventListener('click', () => this.startSync());
        }

        // Refresh button
        const btnRefresh = document.getElementById('btnRefresh');
        if (btnRefresh) {
            btnRefresh.addEventListener('click', () => this.refreshData());
        }

        // Pause sync button
        const btnPauseSync = document.getElementById('btnPauseSync');
        if (btnPauseSync) {
            btnPauseSync.addEventListener('click', () => this.pauseSync());
        }

        // Filter: Operation Type
        const filterOperationType = document.getElementById('filterOperationType');
        if (filterOperationType) {
            filterOperationType.addEventListener('change', (e) => {
                this.filters.operationType = e.target.value;
                this.applyFilters();
            });
        }

        // Filter: Status
        const filterStatus = document.getElementById('filterStatus');
        if (filterStatus) {
            filterStatus.addEventListener('change', (e) => {
                this.filters.status = e.target.value;
                this.applyFilters();
            });
        }

        // Search input
        const searchEntityName = document.getElementById('searchEntityName');
        if (searchEntityName) {
            searchEntityName.addEventListener('input', (e) => {
                this.filters.searchText = e.target.value.toLowerCase();
                this.applyFilters();
            });
        }

        // Clear filters button
        const btnClearFilters = document.getElementById('btnClearFilters');
        if (btnClearFilters) {
            btnClearFilters.addEventListener('click', () => this.clearFilters());
        }

        // Load more history button
        const btnLoadMoreHistory = document.getElementById('btnLoadMoreHistory');
        if (btnLoadMoreHistory) {
            btnLoadMoreHistory.addEventListener('click', () => this.loadMoreHistory());
        }

        // Close modal button
        const btnCloseModal = document.getElementById('btnCloseModal');
        if (btnCloseModal) {
            btnCloseModal.addEventListener('click', () => this.closeModal());
        }
    }

    async loadCurrentSession() {
        try {
            // Get current session statistics
            const stats = await this.getSessionStatistics();

            // Get all progress items
            const items = await this.getCurrentSessionProgress();

            this.progressItems = items;
            this.updateStatistics(stats);
            this.renderProgressItems(items);
            this.updateOverallProgress();

        } catch (error) {
            console.error('Failed to load current session:', error);
        }
    }

    async getSessionStatistics() {
        // In production, this would call SyncProgressTracker.getSessionStatistics()
        // For now, calculate from progressItems
        const items = this.progressItems;

        return {
            total: items.length,
            pending: items.filter(i => i.status === 'pending').length,
            in_progress: items.filter(i => i.status === 'in_progress').length,
            success: items.filter(i => i.status === 'success').length,
            failed: items.filter(i => i.status === 'failed').length,
            conflict: items.filter(i => i.status === 'conflict').length,
            successRate: items.length > 0
                ? ((items.filter(i => i.status === 'success').length / items.length) * 100).toFixed(1)
                : '0.0'
        };
    }

    async getCurrentSessionProgress() {
        // In production, this would call SyncProgressTracker.getCurrentSessionProgress()
        // For demo, return sample data
        return this.generateSampleData();
    }

    generateSampleData() {
        // Sample data for demonstration
        return [
            {
                id: '1',
                operationType: 'data_upload',
                entityType: 'sponsorship',
                entityName: 'محمد أحمد علي',
                status: 'success',
                progressPercentage: 100,
                startedAt: new Date(Date.now() - 120000).toISOString(),
                completedAt: new Date(Date.now() - 60000).toISOString()
            },
            {
                id: '2',
                operationType: 'media_upload',
                entityType: 'attachment',
                entityName: 'صورة الهوية - أحمد سالم',
                status: 'in_progress',
                progressPercentage: 65,
                fileSizeBytes: 5242880,
                uploadedBytes: 3407872,
                startedAt: new Date(Date.now() - 30000).toISOString()
            },
            {
                id: '3',
                operationType: 'data_upload',
                entityType: 're_people',
                entityName: 'فاطمة خالد محمود',
                status: 'pending',
                progressPercentage: 0
            },
            {
                id: '4',
                operationType: 'data_upload',
                entityType: 'data',
                entityName: 'سعيد عبدالله يوسف',
                status: 'failed',
                progressPercentage: 0,
                errorMessage: 'فشل الاتصال بالخادم - انقطاع الإنترنت',
                retryCount: 2,
                startedAt: new Date(Date.now() - 90000).toISOString(),
                completedAt: new Date(Date.now() - 80000).toISOString()
            },
            {
                id: '5',
                operationType: 'data_upload',
                entityType: 'sponsorship',
                entityName: 'نور الدين حسن',
                status: 'conflict',
                progressPercentage: 0,
                conflictReason: 'تعارض في رقم الهوية - موجود مسبقاً',
                startedAt: new Date(Date.now() - 150000).toISOString(),
                completedAt: new Date(Date.now() - 140000).toISOString()
            }
        ];
    }

    updateStatistics(stats) {
        document.getElementById('statTotal').textContent = stats.total || 0;
        document.getElementById('statPending').textContent = stats.pending || 0;
        document.getElementById('statInProgress').textContent = stats.in_progress || 0;
        document.getElementById('statSuccess').textContent = stats.success || 0;
        document.getElementById('statFailed').textContent = stats.failed || 0;
        document.getElementById('statConflict').textContent = stats.conflict || 0;
    }

    updateOverallProgress() {
        const items = this.progressItems;
        if (items.length === 0) {
            this.setOverallProgress(0);
            return;
        }

        const totalProgress = items.reduce((sum, item) => sum + (item.progressPercentage || 0), 0);
        const averageProgress = totalProgress / items.length;

        this.setOverallProgress(averageProgress);
    }

    setOverallProgress(percentage) {
        const progressBar = document.getElementById('overallProgressBar');
        const progressPercentage = document.getElementById('overallPercentage');

        if (progressBar) {
            progressBar.style.width = `${percentage}%`;
        }

        if (progressPercentage) {
            progressPercentage.textContent = `${percentage.toFixed(1)}%`;
        }
    }

    renderProgressItems(items) {
        const container = document.getElementById('progressList');
        const emptyState = document.getElementById('emptyState');

        if (!items || items.length === 0) {
            if (emptyState) {
                emptyState.style.display = 'block';
            }
            return;
        }

        if (emptyState) {
            emptyState.style.display = 'none';
        }

        // Remove existing items (except empty state)
        const existingItems = container.querySelectorAll('.progress-item');
        existingItems.forEach(item => item.remove());

        // Render new items
        items.forEach(item => {
            const itemElement = this.createProgressItemElement(item);
            container.appendChild(itemElement);
        });
    }

    createProgressItemElement(item) {
        const div = document.createElement('div');
        div.className = `progress-item ${item.status}`;
        div.dataset.id = item.id;

        const statusText = this.getStatusText(item.status);
        const operationText = this.getOperationText(item.operationType);
        const icon = this.getOperationIcon(item.operationType);

        let detailsHTML = '';

        // File upload progress
        if (item.fileSizeBytes && item.operationType.includes('media')) {
            const uploadedMB = (item.uploadedBytes / 1024 / 1024).toFixed(2);
            const totalMB = (item.fileSizeBytes / 1024 / 1024).toFixed(2);
            detailsHTML += `
                <div class="progress-item-details">
                    📦 الحجم: ${uploadedMB} / ${totalMB} MB
                </div>
            `;
        }

        // Error message
        if (item.errorMessage && item.status === 'failed') {
            detailsHTML += `
                <div class="error-message">
                    ❌ ${item.errorMessage}
                    ${item.retryCount > 0 ? `<br><small>عدد المحاولات: ${item.retryCount}</small>` : ''}
                </div>
            `;
        }

        // Conflict message
        if (item.conflictReason && item.status === 'conflict') {
            detailsHTML += `
                <div class="conflict-message">
                    ⚠️ ${item.conflictReason}
                </div>
            `;
        }

        // Time details
        let timeDetails = '';
        if (item.startedAt) {
            timeDetails += `⏱️ بدأت: ${this.formatDateTime(item.startedAt)}`;
        }
        if (item.completedAt) {
            timeDetails += ` | ✅ انتهت: ${this.formatDateTime(item.completedAt)}`;
        }

        div.innerHTML = `
            <div class="progress-item-header">
                <div class="progress-item-title">
                    ${icon} ${item.entityName}
                </div>
                <div class="progress-item-status status-${item.status}">
                    ${statusText}
                </div>
            </div>

            <div class="progress-item-type">
                <small>${operationText} - ${item.entityType}</small>
            </div>

            <div class="progress-item-bar">
                <div class="progress-item-bar-fill" style="width: ${item.progressPercentage || 0}%"></div>
            </div>

            ${detailsHTML}

            ${timeDetails ? `<div class="progress-item-details"><small>${timeDetails}</small></div>` : ''}
        `;

        return div;
    }

    getStatusText(status) {
        const statusMap = {
            'pending': 'قيد الانتظار',
            'in_progress': 'قيد التنفيذ',
            'success': 'نجح',
            'failed': 'فشل',
            'conflict': 'تعارض'
        };
        return statusMap[status] || status;
    }

    getOperationText(operationType) {
        const operationMap = {
            'data_upload': 'رفع البيانات',
            'data_download': 'تنزيل البيانات',
            'media_upload': 'رفع الملفات',
            'media_download': 'تنزيل الملفات'
        };
        return operationMap[operationType] || operationType;
    }

    getOperationIcon(operationType) {
        const iconMap = {
            'data_upload': '📤',
            'data_download': '📥',
            'media_upload': '📷',
            'media_download': '🖼️'
        };
        return iconMap[operationType] || '📋';
    }

    formatDateTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        // If less than 1 minute ago
        if (diff < 60000) {
            return 'الآن';
        }

        // If less than 1 hour ago
        if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return `منذ ${minutes} دقيقة`;
        }

        // Otherwise show full date/time
        return date.toLocaleString('ar-SA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    applyFilters() {
        let filteredItems = [...this.progressItems];

        // Filter by operation type
        if (this.filters.operationType) {
            filteredItems = filteredItems.filter(item =>
                item.operationType === this.filters.operationType
            );
        }

        // Filter by status
        if (this.filters.status) {
            filteredItems = filteredItems.filter(item =>
                item.status === this.filters.status
            );
        }

        // Filter by search text
        if (this.filters.searchText) {
            filteredItems = filteredItems.filter(item =>
                item.entityName.toLowerCase().includes(this.filters.searchText)
            );
        }

        this.renderProgressItems(filteredItems);
    }

    clearFilters() {
        this.filters = {
            operationType: '',
            status: '',
            searchText: ''
        };

        document.getElementById('filterOperationType').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('searchEntityName').value = '';

        this.applyFilters();
    }

    async startSync() {
        try {
            const btnStartSync = document.getElementById('btnStartSync');
            btnStartSync.disabled = true;
            btnStartSync.innerHTML = '<span class="btn-icon">⏳</span><span>جاري المزامنة...</span>';

            // In production, call SmartSyncService.syncEligibleSponsorships()
            console.log('🔄 Starting sync process...');

            // Simulate sync process
            await this.simulateSync();

            this.showSuccess('✅ اكتملت عملية المزامنة بنجاح');

        } catch (error) {
            console.error('Sync failed:', error);
            this.showError('❌ فشلت عملية المزامنة: ' + error.message);
        } finally {
            const btnStartSync = document.getElementById('btnStartSync');
            btnStartSync.disabled = false;
            btnStartSync.innerHTML = '<span class="btn-icon">▶️</span><span>بدء المزامنة الكاملة</span>';
        }
    }

    async simulateSync() {
        // Simulate sync process for demonstration
        return new Promise(resolve => {
            setTimeout(() => {
                // Add some new sample progress items
                this.progressItems = this.generateSampleData();
                this.loadCurrentSession();
                resolve();
            }, 2000);
        });
    }

    async pauseSync() {
        console.log('⏸️ Pausing sync...');
        this.showSuccess('تم إيقاف المزامنة مؤقتاً');
    }

    async refreshData() {
        console.log('🔃 Refreshing data...');
        await this.loadCurrentSession();
        await this.loadSessionHistory();
        this.showSuccess('تم تحديث البيانات');
    }

    subscribeToUpdates() {
        // In production, subscribe to SyncProgressTracker updates
        // For now, use polling
        console.log('📡 Subscribed to real-time updates');
    }

    startAutoRefresh() {
        // Refresh every 3 seconds
        this.updateInterval = setInterval(() => {
            if (this.isAutoRefreshEnabled) {
                this.loadCurrentSession();
            }
        }, 3000);
    }

    stopAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    async loadSessionHistory() {
        try {
            // In production, query sync_progress table for previous sessions
            const sessions = this.generateSampleSessions();
            this.renderSessionHistory(sessions);

        } catch (error) {
            console.error('Failed to load session history:', error);
        }
    }

    generateSampleSessions() {
        return [
            {
                sync_session_id: 'session_123',
                total_operations: 15,
                successful: 12,
                failed: 2,
                conflict: 1,
                started_at: new Date(Date.now() - 3600000).toISOString(),
                ended_at: new Date(Date.now() - 3000000).toISOString()
            },
            {
                sync_session_id: 'session_122',
                total_operations: 8,
                successful: 8,
                failed: 0,
                conflict: 0,
                started_at: new Date(Date.now() - 86400000).toISOString(),
                ended_at: new Date(Date.now() - 86000000).toISOString()
            }
        ];
    }

    renderSessionHistory(sessions) {
        const container = document.getElementById('sessionHistory');
        container.innerHTML = '';

        if (!sessions || sessions.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>لا يوجد سجل لجلسات سابقة</p>
                </div>
            `;
            return;
        }

        sessions.forEach(session => {
            const successRate = session.total_operations > 0
                ? ((session.successful / session.total_operations) * 100).toFixed(1)
                : 0;

            const div = document.createElement('div');
            div.className = 'session-history-item';
            div.innerHTML = `
                <div class="session-header">
                    <div class="session-date">
                        📅 ${this.formatDateTime(session.started_at)}
                    </div>
                    <div class="session-stats">
                        <span class="badge badge-info">${session.total_operations} عملية</span>
                        <span class="badge badge-success">${session.successful} نجح</span>
                        ${session.failed > 0 ? `<span class="badge badge-danger">${session.failed} فشل</span>` : ''}
                        ${session.conflict > 0 ? `<span class="badge badge-warning">${session.conflict} تعارض</span>` : ''}
                        <span class="badge badge-primary">${successRate}%</span>
                    </div>
                </div>
            `;

            container.appendChild(div);
        });
    }

    async loadMoreHistory() {
        console.log('📜 Loading more history...');
        this.showSuccess('تم تحميل المزيد من السجلات');
    }

    showSuccess(message) {
        // Simple alert for now, can be replaced with better UI notification
        alert(message);
    }

    showError(message) {
        alert(message);
    }

    closeModal() {
        const modal = document.getElementById('statsModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.syncMonitor = new SyncMonitor();
    console.log('✅ Sync Monitor page loaded');
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.syncMonitor) {
        window.syncMonitor.stopAutoRefresh();
    }
});
