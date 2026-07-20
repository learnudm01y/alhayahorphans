/**
 * ConflictResolver - خوارزمية حل التعارضات
 * تقارن بين السجلات المحلية وسجلات الخادم أثناء المزامنة لتحديد أيها يجب الاحتفاظ به.
 */
class ConflictResolver {
    constructor(actionManager) {
        this.actionManager = actionManager;
    }

    /**
     * فحص التعارض بين سجل الخادم والسجل المحلي
     * @param {Object} serverRecord سجل البيانات القادم من الخادم
     * @param {Object} localRecord السجل المحلي الحالي من IndexedDB
     * @returns {Promise<Object>} قرار الحل { action, data }
     */
    async resolve(serverRecord, localRecord) {
        // إذا لم يكن هناك سجل محلي، نقبل سجل الخادم دائماً
        if (!localRecord) {
            return { action: 'ACCEPT_SERVER', data: serverRecord };
        }

        // فحص ما إذا كانت هناك عمليات معلقة (تعديلات لم ترفع بعد) لهذا السجل
        const hasPendingLocalChanges = await this.actionManager.hasPendingActionsForEntity(localRecord.id);

        if (!hasPendingLocalChanges) {
            // لا يوجد تعديلات محلية قيد الانتظار -> الخادم هو المصدر الموثوق
            return { action: 'ACCEPT_SERVER', data: serverRecord };
        }

        // توجد تعديلات محلية. الآن نقارن التواريخ إذا كانت متاحة
        const serverDate = new Date(serverRecord.updated_at || 0).getTime();
        const localDate = new Date(localRecord.updated_at || 0).getTime();

        if (serverDate > localDate) {
            // السجل في الخادم أحدث من التعديل المحلي الأخير! 
            // هذا يعني أن شخصاً آخر قام بتعديل السجل أثناء فترة انقطاع الاتصال
            console.warn(`[ConflictResolver] Conflict detected for entity ${localRecord.id}! Server is newer, but local has pending changes.`);
            
            // السياسة الحالية: الاحتفاظ بالنسخة المحلية (Offline First)
            // وسيتم تحديث الخادم عند معالجة طابور العمليات المعلقة
            // يمكن مستقبلاً تطوير واجهة للمستخدم للاختيار
            return { 
                action: 'KEEP_LOCAL_PENDING', 
                data: localRecord,
                conflict: {
                    serverUpdated: serverRecord.updated_at,
                    localUpdated: localRecord.updated_at,
                    resolvedBy: 'LOCAL_PRIORITY'
                }
            };
        }

        // التعديل المحلي أحدث -> الاحتفاظ بالنسخة المحلية التي سيتم رفعها
        return { action: 'KEEP_LOCAL', data: localRecord };
    }

    /**
     * معالجة مجموعة من السجلات القادمة من الخادم
     */
    async resolveBulk(serverRecords, getLocalRecordCallback) {
        const resolvedRecords = [];
        
        for (const serverRecord of serverRecords) {
            const localRecord = await getLocalRecordCallback(serverRecord.id);
            const resolution = await this.resolve(serverRecord, localRecord);
            
            if (resolution.action === 'ACCEPT_SERVER') {
                resolvedRecords.push(resolution.data);
            }
            // إذا كان الإجراء KEEP_LOCAL فلا نضيفه لأنه موجود بالفعل وسيُرفع لاحقاً
        }
        
        return resolvedRecords;
    }
}
