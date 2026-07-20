/**
 * SyncActionManager - مدير العمليات الجبهي (Action Queue Pattern)
 * يدير طابور المهام التي تتم أثناء العمل دون اتصال، وينظم إرسالها عند توفر الاتصال.
 */
class SyncActionManager {
    constructor(dbManager, apiManager) {
        this.dbManager = dbManager;
        this.apiManager = apiManager; // e.g. TokenInterceptor + fetch
        
        this.STORE_NAME = 'sync_actions';
        this.isProcessing = false;

        this.STATES = {
            PENDING: 'pending',
            PROCESSING: 'processing',
            COMPLETED: 'completed',
            RETRY_WAIT: 'retry_wait',
            FAILED: 'failed'
        };

        // الأولويات (الأقل رقماً ينفذ أولاً)
        this.ACTION_TYPES = {
            DATA_UPDATE: { priority: 1, label: 'تحديث بيانات الكفالة' },
            STATUS_CHANGE: { priority: 2, label: 'تغيير حالة' },
            FILE_UPLOAD: { priority: 3, label: 'رفع ملف' }, // قد يُدار بواسطة WorkManager في الأندرويد، لكن هنا للويب
            FILE_DELETE: { priority: 4, label: 'حذف ملف' }
        };
    }

    /**
     * تسجيل عملية جديدة في الطابور
     */
    async enqueueAction(type, payload, entityId = null) {
        const actionInfo = this.ACTION_TYPES[type];
        if (!actionInfo) throw new Error(`Unknown action type: ${type}`);

        const action = {
            id: crypto.randomUUID(),
            type: type,
            entityId: entityId, // مثال: معرف الكفالة
            priority: actionInfo.priority,
            payload: payload,
            state: this.STATES.PENDING,
            retryCount: 0,
            maxRetries: type === 'FILE_UPLOAD' ? 10 : 5,
            createdAt: Date.now(),
            lastAttemptAt: null,
            nextRetryAt: null,
            errorMessage: null
        };

        await this.dbManager.put(this.STORE_NAME, action);
        
        // محاولة تنفيذ الطابور إذا كان هناك اتصال
        if (navigator.onLine) {
            this.processQueue();
        } else {
            // تسجيل المزامنة الخلفية إذا كان الـ Service Worker يدعمها
            this.registerBackgroundSync();
        }
        
        return action.id;
    }

    /**
     * سحب العمليات المعلقة مرتبة حسب الأولوية وتاريخ الإنشاء
     */
    async getPendingActions() {
        const actions = await this.dbManager.getAll(this.STORE_NAME);
        const now = Date.now();
        
        return actions
            .filter(a => 
                (a.state === this.STATES.PENDING) || 
                (a.state === this.STATES.RETRY_WAIT && a.nextRetryAt <= now)
            )
            .sort((a, b) => {
                if (a.priority !== b.priority) return a.priority - b.priority;
                return a.createdAt - b.createdAt;
            });
    }

    /**
     * هل توجد عمليات معلقة لكيان معين؟
     */
    async hasPendingActionsForEntity(entityId) {
        if (!entityId) return false;
        const actions = await this.dbManager.getAll(this.STORE_NAME);
        return actions.some(a => 
            a.entityId == entityId && 
            a.state !== this.STATES.COMPLETED && 
            a.state !== this.STATES.FAILED
        );
    }

    /**
     * معالجة الطابور بشكل متسلسل
     */
    async processQueue() {
        if (this.isProcessing) return;
        this.isProcessing = true;

        try {
            const pendingActions = await this.getPendingActions();
            
            for (const action of pendingActions) {
                if (!navigator.onLine) {
                    console.log('[SyncActionManager] Connection lost, stopping queue.');
                    break; 
                }
                
                await this.updateState(action.id, this.STATES.PROCESSING);
                
                try {
                    await this.executeAction(action);
                    await this.updateState(action.id, this.STATES.COMPLETED);
                    
                    // إزالة العملية المكتملة للحفاظ على حجم قاعدة البيانات
                    await this.dbManager.delete(this.STORE_NAME, action.id);
                } catch (error) {
                    console.error(`[SyncActionManager] Action ${action.id} failed:`, error);
                    const shouldStop = await this.handleActionFailure(action, error);
                    if (shouldStop) break; 
                }
            }
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * تنفيذ الإجراء الفعلي بناءً على نوعه
     */
    async executeAction(action) {
        if (action.type === 'DATA_UPDATE') {
            // التحديثات المجمعة تعالج عادة في bulkUpsert، ولكن لو كان فردياً:
            return await this.apiManager.fetchWithAuth('/mobile/sync/upload', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ changes: [action.payload] })
            });
        }
        
        // إضافة أنواع العمليات الأخرى حسب الحاجة
        throw new Error(`Execution for action type ${action.type} not implemented.`);
    }

    /**
     * معالجة الفشل بذكاء (التراجع الأسي أو الإلغاء الدائم)
     */
    async handleActionFailure(action, error) {
        action.retryCount++;
        action.lastAttemptAt = Date.now();
        action.errorMessage = error.message || String(error);

        const isNetworkError = this.isNetworkError(error);

        if (isNetworkError && action.retryCount < action.maxRetries) {
            // فشل مؤقت: تراجع أسي (Exponential Backoff)
            // 1s -> 2s -> 4s -> 8s -> ... max 60s
            const backoffMs = Math.min(60000, 1000 * Math.pow(2, action.retryCount));
            action.nextRetryAt = Date.now() + backoffMs;
            
            await this.updateAction(action);
            await this.updateState(action.id, this.STATES.RETRY_WAIT);
            
            return true; // إيقاف الطابور مؤقتاً لأن الشبكة سيئة
        } else if (!isNetworkError || action.retryCount >= action.maxRetries) {
            // فشل دائم أو تجاوز عدد المحاولات
            await this.updateAction(action);
            await this.updateState(action.id, this.STATES.FAILED);
            
            return false; // الاستمرار في العمليات الأخرى لأن الخطأ خاص بهذه العملية
        }
        
        return false;
    }

    isNetworkError(error) {
        // فحص ما إذا كان الخطأ ناتجاً عن انقطاع الشبكة
        if (error instanceof TypeError && error.message === 'Failed to fetch') return true;
        if (!navigator.onLine) return true;
        return false;
    }

    async updateState(actionId, newState) {
        const action = await this.dbManager.get(this.STORE_NAME, actionId);
        if (action) {
            action.state = newState;
            await this.dbManager.put(this.STORE_NAME, action);
        }
    }

    async updateAction(action) {
        await this.dbManager.put(this.STORE_NAME, action);
    }

    async registerBackgroundSync() {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('sync-pending-actions');
                console.log('[SyncActionManager] Background sync registered.');
            } catch (err) {
                console.warn('[SyncActionManager] Background sync registration failed:', err);
            }
        }
    }
}
