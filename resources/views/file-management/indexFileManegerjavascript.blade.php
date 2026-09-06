@push('scriptsCode')
    <style>
        /* تأثيرات CSS لأشرطة التقدم الجذابة */
        @keyframes progress-glow {
            0% {
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            100% {
                box-shadow: 0 4px 20px rgba(0,0,0,0.4), 0 0 10px rgba(255,255,255,0.3);
            }
        }

        @keyframes rainbow-flow {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes pulse-processing {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        @keyframes pulse-duplicate {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
            }
            50% {
                transform: scale(1.02);
                box-shadow: 0 4px 15px rgba(255, 193, 7, 0.6);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .progress-bar-animated {
            background-size: 200% 200% !important;
            animation: rainbow-flow 3s ease infinite !important;
        }

        .batch-upload-progress {
            animation: pulse-processing 2s ease-in-out infinite;
        }

        .real-time-progress {
            animation: rainbow-flow 4s ease infinite;
            background-size: 300% 300% !important;
        }

        /* تصميم المجلدات الجديد */
        .folder-section {
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .folder-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .folder-header .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #dee2e6;
        }

        .folder-files-grid {
            padding: 1rem 0;
        }

        .file-preview.card {
            transition: all 0.2s ease;
            border: 1px solid #e3e6f0;
            will-change: transform;
            backface-visibility: hidden;
            transform: translateZ(0);
        }

        .file-preview.card:hover {
            transform: translateY(-2px) translateZ(0);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-color: #5a5c69;
        }

        .file-preview .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .processing-overlay {
            background: rgba(0,0,0,0.7) !important;
            border-radius: 0.375rem;
        }

        .duplicate-file {
            animation: pulse-duplicate 3s infinite;
            border: 2px solid #ffc107 !important;
            background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%) !important;
        }

        .folder-quick-stats .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }

        .folder-controls .btn {
            margin-left: 0.25rem;
        }

        /* تصميم معلومات الشخص المحسن */
        .person-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem 0;
            transition: all 0.3s ease;
            min-height: 4rem;
            display: flex;
            align-items: center;
        }

        .person-name-display {
            min-height: 2.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            width: 100%;
            flex: 1;
        }

        .person-name-display.success {
            color: #198754;
        }

        .person-name-display.not-found {
            color: #fd7e14;
        }

        .person-name-display.error {
            color: #dc3545;
        }

        .loading-state {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .loading-state .fa-spinner {
            animation: spin 1s linear infinite;
        }

        .success-state {
            width: 100%;
        }

        .person-details {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .not-found-state,
        .error-state {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            width: 100%;
        }

        .not-found-state span,
        .error-state span {
            flex-grow: 1;
        }

        .text-pink {
            color: #e83e8c !important;
        }

        /* تأثيرات الحركة */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .person-info-card {
            animation: fadeInUp 0.6s ease-out;
            min-height: 65px;
            display: flex;
            align-items: center;
            padding: 16px;
        }

        .person-name-display {
            width: 100%;
            display: flex;
            align-items: center;
        }

        .person-name-display:hover {
            transform: translateY(-1px);
        }

        .success-state {
            animation: fadeInUp 0.5s ease-out;
        }

        /* تحسينات إضافية للأزرار */
        .folder-quick-stats .badge {
            font-size: 0.7rem;
            padding: 0.35em 0.5em;
            margin: 0.1rem;
        }

        .folder-controls {
            margin-top: 0.5rem;
        }

        .folder-controls .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        /* تصميم رقم الهوية المكبر */
        .id-number-display {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #2196f3;
            border-radius: 6px;
            padding: 8px 12px;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(33, 150, 243, 0.1);
        }

        .id-number-display .h6 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* تحسين عرض معلومات المدينة */
        .person-details small {
            font-size: 0.85rem;
        }

        .person-details .text-dark {
            color: #495057 !important;
        }

        /* تحسينات عناوين المجلدات */
        #folder-title-950297671,
        [id^="folder-title-"] {
            transition: all 0.3s ease;
            min-height: 2rem;
            display: flex;
            align-items: center;
        }

        [id^="folder-title-"].text-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-radius: 6px;
            padding: 8px 12px;
            border-left: 4px solid #28a745;
        }

        [id^="folder-title-"].text-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 6px;
            padding: 8px 12px;
            border-left: 4px solid #ffc107;
        }

        [id^="folder-title-"].text-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-radius: 6px;
            padding: 8px 12px;
            border-left: 4px solid #dc3545;
        }

        /* تصميم معرض الملفات */
        .files-gallery {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #e9ecef;
        }

        .file-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #007bff;
        }

        .file-preview {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .file-preview img {
            transition: transform 0.2s ease;
            will-change: transform;
            backface-visibility: hidden;
        }

        .file-preview img:hover {
            transform: scale(1.02) translateZ(0);
        }

        /* Dropdown Styles for File Cards */
        .file-card .dropdown-toggle {
            background: rgba(255,255,255,0.9);
            border: 1px solid #dee2e6;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .file-card .dropdown-toggle:hover {
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            border-color: #007bff;
        }

        .file-card .dropdown-toggle:focus {
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
        }

        .file-card .dropdown-menu {
            font-size: 0.9rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            border: 1px solid #dee2e6;
            min-width: 160px;
            z-index: 1050;
        }

        .file-card .dropdown-item {
            padding: 0.5rem 1rem;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
        }

        .file-card .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .file-card .dropdown-item.text-danger:hover {
            background-color: #f8d7da;
            color: #721c24;
        }

        .files-gallery {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .no-files-message {
            background: white;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
            padding: 2rem;
        }

        .btn-group .btn {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .no-files-message {
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        /* تحسينات المودال */
        #filePreviewModal .modal-body img,
        #localFilePreviewModal .modal-body img {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* تحسينات التنبيهات */
        .alert {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* تحسينات معاينة الصور المحلية */
        .file-preview img {
            border-radius: 4px;
            transition: transform 0.2s ease;
            will-change: transform;
            backface-visibility: hidden;
        }

        .file-preview img:hover {
            transform: scale(1.01) translateZ(0);
        }

        .file-preview .position-relative {
            overflow: hidden;
            border-radius: 4px;
        }

        /* تحسينات القائمة المنسدلة للملفات المحلية */
        .file-preview .dropdown-toggle {
            background: rgba(255,255,255,0.9);
            border: 1px solid #dee2e6;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .file-preview .dropdown-toggle:hover {
            background: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .file-preview .dropdown-menu {
            font-size: 0.9rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .person-name-display .btn-sm {
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
        }

        /* تحسينات للاستجابة */
        @media (max-width: 768px) {
            .folder-header .row > div:first-child {
                margin-bottom: 1rem;
            }

            .folder-quick-stats {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }

            .folder-controls {
                margin-top: 0.5rem;
            }
        }

        /* تحسينات الأداء لتمرير سلس */
        * {
            -webkit-overflow-scrolling: touch;
        }

        .file-preview.card,
        .file-preview img,
        .folder-section {
            -webkit-transform: translateZ(0);
            -moz-transform: translateZ(0);
            -ms-transform: translateZ(0);
            -o-transform: translateZ(0);
            transform: translateZ(0);
        }

        .folder-files-grid {
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            -webkit-perspective: 1000;
            perspective: 1000;
        }

        /* تقليل تأثيرات الانتقال للأجهزة الأبطأ */
        @media (prefers-reduced-motion: reduce) {
            .file-preview.card,
            .file-preview img,
            .folder-section {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
    <script>
                // =====================================================
                // نظام IndexedDB لإدارة جلسات الرفع واستئناف الرفع
                // =====================================================
                class UploadSessionDB {
                    constructor() {
                        this.dbName = 'FileManagerUploadDB';
                        this.dbVersion = 1;
                        this.db = null;
                    }

                    async open() {
                        if (this.db) return this.db;
                        return new Promise((resolve, reject) => {
                            const request = indexedDB.open(this.dbName, this.dbVersion);
                            request.onupgradeneeded = (event) => {
                                const db = event.target.result;
                                // جدول جلسات الرفع
                                if (!db.objectStoreNames.contains('upload_sessions')) {
                                    const sessionStore = db.createObjectStore('upload_sessions', { keyPath: 'id' });
                                    sessionStore.createIndex('status', 'status', { unique: false });
                                    sessionStore.createIndex('created_at', 'created_at', { unique: false });
                                }
                                // جدول الملفات لكل جلسة (بيانات وصفية فقط - الملفات نفسها تبقى في الذاكرة)
                                if (!db.objectStoreNames.contains('session_files')) {
                                    const filesStore = db.createObjectStore('session_files', { keyPath: 'id', autoIncrement: true });
                                    filesStore.createIndex('session_id', 'session_id', { unique: false });
                                    filesStore.createIndex('status', 'status', { unique: false });
                                    filesStore.createIndex('batch_index', 'batch_index', { unique: false });
                                }
                            };
                            request.onsuccess = (event) => {
                                this.db = event.target.result;
                                resolve(this.db);
                            };
                            request.onerror = (event) => {
                                console.error('❌ خطأ في فتح IndexedDB:', event.target.error);
                                reject(event.target.error);
                            };
                        });
                    }

                    // إنشاء جلسة رفع جديدة
                    async createSession(sessionData) {
                        const db = await this.open();
                        const session = {
                            id: 'session_' + Date.now(),
                            status: 'active', // active, paused, completed, failed
                            created_at: new Date().toISOString(),
                            updated_at: new Date().toISOString(),
                            upload_type: sessionData.upload_type || 'folder',
                            total_files: sessionData.total_files || 0,
                            total_batches: sessionData.total_batches || 0,
                            completed_batches: 0,
                            last_completed_batch: -1,
                            options: sessionData.options || {},
                            stats: {
                                successful: 0,
                                duplicates: 0,
                                errors: 0,
                                skipped_large: 0
                            }
                        };

                        return new Promise((resolve, reject) => {
                            const tx = db.transaction('upload_sessions', 'readwrite');
                            tx.objectStore('upload_sessions').put(session);
                            tx.oncomplete = () => {
                                console.log('✅ تم إنشاء جلسة رفع:', session.id);
                                resolve(session);
                            };
                            tx.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // تحديث جلسة الرفع
                    async updateSession(sessionId, updates) {
                        const db = await this.open();
                        const session = await this.getSession(sessionId);
                        if (!session) return null;

                        Object.assign(session, updates, { updated_at: new Date().toISOString() });

                        return new Promise((resolve, reject) => {
                            const tx = db.transaction('upload_sessions', 'readwrite');
                            tx.objectStore('upload_sessions').put(session);
                            tx.oncomplete = () => resolve(session);
                            tx.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // جلب جلسة رفع
                    async getSession(sessionId) {
                        const db = await this.open();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction('upload_sessions', 'readonly');
                            const request = tx.objectStore('upload_sessions').get(sessionId);
                            request.onsuccess = () => resolve(request.result || null);
                            request.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // جلب جلسات الرفع المعلقة (active أو paused)
                    async getPendingSessions() {
                        const db = await this.open();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction('upload_sessions', 'readonly');
                            const store = tx.objectStore('upload_sessions');
                            const request = store.getAll();
                            request.onsuccess = () => {
                                const sessions = (request.result || []).filter(
                                    s => s.status === 'active' || s.status === 'paused'
                                );
                                resolve(sessions);
                            };
                            request.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // حفظ بيانات ملفات الدفعة
                    async saveSessionFiles(sessionId, batchIndex, filesMetadata) {
                        const db = await this.open();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction('session_files', 'readwrite');
                            const store = tx.objectStore('session_files');
                            filesMetadata.forEach(fileMeta => {
                                store.put({
                                    session_id: sessionId,
                                    batch_index: batchIndex,
                                    file_name: fileMeta.name,
                                    file_path: fileMeta.path,
                                    file_size: fileMeta.size,
                                    file_type: fileMeta.type,
                                    status: fileMeta.status || 'pending'
                                });
                            });
                            tx.oncomplete = () => resolve();
                            tx.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // تحديث حالة ملفات دفعة معينة
                    async updateBatchFilesStatus(sessionId, batchIndex, newStatus) {
                        const db = await this.open();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction('session_files', 'readwrite');
                            const store = tx.objectStore('session_files');
                            const idx = store.index('session_id');
                            const cursorReq = idx.openCursor(IDBKeyRange.only(sessionId));
                            cursorReq.onsuccess = (e) => {
                                const cursor = e.target.result;
                                if (cursor) {
                                    const record = cursor.value;
                                    if (record.batch_index === batchIndex) {
                                        record.status = newStatus;
                                        cursor.update(record);
                                    }
                                    cursor.continue();
                                }
                            };
                            tx.oncomplete = () => resolve();
                            tx.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // حذف جلسة مكتملة مع ملفاتها
                    async deleteSession(sessionId) {
                        const db = await this.open();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction(['upload_sessions', 'session_files'], 'readwrite');
                            tx.objectStore('upload_sessions').delete(sessionId);
                            // حذف ملفات الجلسة
                            const filesStore = tx.objectStore('session_files');
                            const idx = filesStore.index('session_id');
                            const cursorReq = idx.openCursor(IDBKeyRange.only(sessionId));
                            cursorReq.onsuccess = (e) => {
                                const cursor = e.target.result;
                                if (cursor) {
                                    cursor.delete();
                                    cursor.continue();
                                }
                            };
                            tx.oncomplete = () => {
                                console.log('🗑️ تم حذف جلسة الرفع:', sessionId);
                                resolve();
                            };
                            tx.onerror = (e) => reject(e.target.error);
                        });
                    }

                    // تنظيف الجلسات القديمة (أكثر من 7 أيام)
                    async cleanOldSessions() {
                        const db = await this.open();
                        const sevenDaysAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString();
                        return new Promise((resolve, reject) => {
                            const tx = db.transaction(['upload_sessions', 'session_files'], 'readwrite');
                            const store = tx.objectStore('upload_sessions');
                            const request = store.getAll();
                            request.onsuccess = () => {
                                const oldSessions = (request.result || []).filter(
                                    s => s.created_at < sevenDaysAgo
                                );
                                oldSessions.forEach(s => {
                                    store.delete(s.id);
                                    const filesStore = tx.objectStore('session_files');
                                    const idx = filesStore.index('session_id');
                                    const cursorReq = idx.openCursor(IDBKeyRange.only(s.id));
                                    cursorReq.onsuccess = (e) => {
                                        const cursor = e.target.result;
                                        if (cursor) { cursor.delete(); cursor.continue(); }
                                    };
                                });
                            };
                            tx.oncomplete = () => resolve();
                            tx.onerror = (e) => reject(e.target.error);
                        });
                    }
                }

                // إنشاء نسخة عامة من IndexedDB Manager
                const uploadDB = new UploadSessionDB();

                // =====================================================

                // إنشاء متغير عام للتطبيق
                let app;

                // ربط زر عرض الملفات المكررة بالمودال
                document.addEventListener('DOMContentLoaded', function() {
                    const btn = document.getElementById('showDuplicateFilesBtn');
                    if (btn) {
                        btn.onclick = async function() {
                            try {
                                // جلب الملفات المكررة من قاعدة البيانات
                                const response = await fetch('/api/duplicate-files/summary', {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });

                                if (response.ok) {
                                    const data = await response.json();
                                    if (data.success && data.data && data.data.length > 0) {
                                        showDuplicateFilesModal(data.data);
                                        const modal = new bootstrap.Modal(document.getElementById('duplicateFilesModal'));
                                        modal.show();
                                    } else {
                                        alert('لا توجد ملفات مكررة في قاعدة البيانات.');
                                    }
                                } else {
                                    alert('فشل في جلب الملفات المكررة.');
                                }
                            } catch (error) {
                                console.error('خطأ في جلب الملفات المكررة:', error);
                                alert('حدث خطأ أثناء جلب الملفات المكررة.');
                            }
                        };
                    }

                    // تهيئة التطبيق
                    app = new AdvancedFileManager();

                    // جعل الدوال متاحة عالمياً
                    window.app = app;
                });

                // دالة لعرض الملفات المكررة في المودال
                function showDuplicateFilesModal(duplicatesData) {
                    const modalBody = document.querySelector('#duplicateFilesModal .modal-body');

                    if (!duplicatesData || duplicatesData.length === 0) {
                        modalBody.innerHTML = `
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h4>لا توجد ملفات مكررة</h4>
                                <p class="text-muted">جميع الملفات في النظام فريدة</p>
                            </div>
                        `;
                        return;
                    }

                    let tableContent = `
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>اسم الملف</th>
                                        <th>الحجم</th>
                                        <th>تاريخ الرفع</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    duplicatesData.forEach(file => {
                        const fileName = file.original_name || file.duplicate_name || 'غير محدد';
                        tableContent += `
                            <tr>
                                <td>` + fileName + `</td>
                                <td>` + formatFileSize(file.file_size || 0) + `</td>
                                <td>` + (file.created_at || 'غير محدد') + `</td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="deleteDuplicateFile('` + file.id + `')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    tableContent += `
                                </tbody>
                            </table>
                        </div>
                    `;

                    modalBody.innerHTML = tableContent;
                }

                // دالة لتنسيق حجم الملف
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }

                // دالة لحذف ملف مكرر
                async function deleteDuplicateFile(fileId) {
                    if (!confirm('هل أنت متأكد من حذف هذا الملف المكرر؟')) {
                        return;
                    }

                    try {
                        const response = await fetch(`/api/duplicate-files/delete`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ file_id: fileId })
                        });

                        if (response.ok) {
                            const data = await response.json();
                            if (data.success) {
                                alert('تم حذف الملف المكرر بنجاح');
                                // إعادة تحميل قائمة الملفات المكررة
                                document.getElementById('showDuplicateFilesBtn').click();
                            } else {
                                alert('فشل في حذف الملف المكرر');
                            }
                        } else {
                            alert('خطأ في الاتصال بالخادم');
                        }
                    } catch (error) {
                        console.error('خطأ في حذف الملف المكرر:', error);
                        alert('حدث خطأ أثناء حذف الملف المكرر');
                    }
                }

                class AdvancedFileManager {
                    constructor() {
                        this.files = new Map();
                        this.currentBatch = null;
                        this.uploadQueue = [];
                        this.maxConcurrentUploads = 3;
                        this.activeUploads = 0;

                        // متغيرات للتعامل مع ملفات المجلدات
                        this.processedFiles = null;
                        this.currentUploadType = null;

                        // حالة كشف الملفات المكررة (مفعل افتراضياً)
                        this.duplicateDetectionEnabled = true;

                        // طابور طلبات API مع تحكم بالتزامن
                        this._apiQueue = [];
                        this._apiRunning = 0;
                        this._apiMaxConcurrent = 2; // حد أقصى 2 طلب متزامن لمنع 429
                        this._apiProcessing = false;

                        // نظام Pagination للمجلدات
                        this._pagination = {
                            foldersPerPage: 10,     // عدد المجلدات في كل صفحة
                            currentPage: 1,
                            totalPages: 1,
                            groupedFiles: new Map(), // Map<folderName, fileData[]>
                            folderOrder: [],          // ترتيب المجلدات
                            renderedFolders: new Set() // المجلدات المعروضة حالياً
                        };

                        // نظام إدارة جلسات الرفع (IndexedDB)
                        this._uploadSession = {
                            currentSessionId: null,
                            isUploading: false,
                            isPaused: false,
                            pauseReason: null, // 'offline' | 'user' | null
                            pendingResumeSessionId: null
                        };

                        this.initializeEventListeners();
                        this.loadAnalytics();
                        this.checkForExistingDuplicates();

                        // تحديث الإحصائيات كل 30 ثانية
                        this.startAnalyticsRefresh();

                        // تهيئة نظام كشف الاتصال واستئناف الرفع
                        this._initConnectionMonitor();
                        // تنظيف الجلسات القديمة والتحقق من جلسات معلقة
                        this._initUploadResume();
                    }

                    /**
                     * إضافة طلب API للطابور مع تحكم بالتزامن
                     */
                    queueApiCall(apiCallFn) {
                        this._apiQueue.push(apiCallFn);
                        if (!this._apiProcessing) {
                            this._processApiQueue();
                        }
                    }

                    async _processApiQueue() {
                        if (this._apiProcessing) return;
                        this._apiProcessing = true;

                        while (this._apiQueue.length > 0) {
                            // تشغيل دفعة محدودة
                            const batch = [];
                            while (batch.length < this._apiMaxConcurrent && this._apiQueue.length > 0) {
                                batch.push(this._apiQueue.shift());
                            }

                            // تنفيذ الدفعة
                            await Promise.allSettled(batch.map(async (apiCall) => {
                                try {
                                    await apiCall();
                                } catch (e) {
                                    console.warn('⚠️ خطأ في طلب API:', e.message);
                                }
                            }));

                            // تأخير بين الدفعات لمنع 429
                            if (this._apiQueue.length > 0) {
                                await new Promise(resolve => setTimeout(resolve, 300));
                            }
                        }

                        this._apiProcessing = false;
                    }

                    /**
                     * بدء تحديث الإحصائيات بشكل دوري
                     */
                    startAnalyticsRefresh() {
                        // تحديث كل 30 ثانية فقط (بدون تحديث فوري مكرر)
                        setInterval(() => {
                            this.loadAnalytics();
                        }, 30000);
                    }

                    // =====================================================
                    // نظام كشف الاتصال واستئناف الرفع التلقائي
                    // =====================================================

                    _initConnectionMonitor() {
                        // مراقبة حالة الاتصال
                        window.addEventListener('offline', () => {
                            console.warn('🔴 انقطع الاتصال بالإنترنت!');
                            this._showConnectionStatus(false);

                            // إيقاف الرفع الجاري مؤقتاً
                            if (this._uploadSession.isUploading && !this._uploadSession.isPaused) {
                                this._uploadSession.isPaused = true;
                                this._uploadSession.pauseReason = 'offline';
                                console.log('⏸️ تم إيقاف الرفع مؤقتاً بسبب انقطاع الإنترنت');

                                // حفظ حالة التوقف في IndexedDB
                                if (this._uploadSession.currentSessionId) {
                                    uploadDB.updateSession(this._uploadSession.currentSessionId, {
                                        status: 'paused',
                                        pause_reason: 'offline',
                                        paused_at: new Date().toISOString()
                                    });
                                }
                            }
                        });

                        window.addEventListener('online', () => {
                            console.log('🟢 عاد الاتصال بالإنترنت!');
                            this._showConnectionStatus(true);

                            // استئناف الرفع تلقائياً إذا كان متوقفاً بسبب الإنترنت
                            if (this._uploadSession.isPaused && this._uploadSession.pauseReason === 'offline') {
                                console.log('▶️ استئناف الرفع تلقائياً بعد عودة الإنترنت...');
                                // تأخير 2 ثانية للتأكد من استقرار الاتصال
                                setTimeout(() => {
                                    if (navigator.onLine) {
                                        this._resumeUploadFromSession();
                                    }
                                }, 2000);
                            }
                        });
                    }

                    _showConnectionStatus(isOnline) {
                        // إزالة الإشعار السابق
                        const existing = document.getElementById('connection-status-bar');
                        if (existing) existing.remove();

                        const bar = document.createElement('div');
                        bar.id = 'connection-status-bar';
                        bar.style.cssText = `
                            position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
                            padding: 10px 20px; text-align: center; font-weight: bold;
                            transition: all 0.3s ease; direction: rtl;
                        `;

                        if (isOnline) {
                            bar.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                            bar.style.color = 'white';
                            bar.innerHTML = '<i class="fas fa-wifi me-2"></i> عاد الاتصال بالإنترنت - جاري استئناف الرفع...';
                            setTimeout(() => bar.remove(), 4000);
                        } else {
                            bar.style.background = 'linear-gradient(135deg, #dc3545, #fd7e14)';
                            bar.style.color = 'white';
                            bar.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> انقطع الاتصال بالإنترنت! سيتم استئناف الرفع تلقائياً عند عودة الاتصال';
                        }

                        document.body.prepend(bar);
                    }

                    async _initUploadResume() {
                        try {
                            // تنظيف الجلسات القديمة
                            await uploadDB.cleanOldSessions();

                            // التحقق من وجود جلسات معلقة
                            const pendingSessions = await uploadDB.getPendingSessions();
                            if (pendingSessions.length > 0) {
                                const lastSession = pendingSessions[pendingSessions.length - 1];
                                console.log('📋 توجد جلسة رفع معلقة:', lastSession);
                                this._showResumePrompt(lastSession);
                            }
                        } catch (e) {
                            console.warn('⚠️ تعذر التحقق من جلسات الرفع المعلقة:', e.message);
                        }
                    }

                    _showResumePrompt(session) {
                        const completedPercent = session.total_batches > 0
                            ? Math.round((session.completed_batches / session.total_batches) * 100)
                            : 0;
                        const remaining = session.total_batches - session.completed_batches;

                        // إنشاء شريط إشعار الاستئناف
                        const resumeBar = document.createElement('div');
                        resumeBar.id = 'upload-resume-bar';
                        resumeBar.className = 'alert alert-warning alert-dismissible fade show';
                        resumeBar.style.cssText = `
                            position: fixed; bottom: 20px; left: 20px; right: 20px;
                            z-index: 9999; direction: rtl; border-radius: 12px;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                        `;
                        resumeBar.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-1"><i class="fas fa-pause-circle me-2"></i>توجد عملية رفع متوقفة</h6>
                                    <small>
                                        تم رفع <strong>${session.completed_batches}</strong> من <strong>${session.total_batches}</strong> دفعة
                                        (${completedPercent}%) - متبقي <strong>${remaining}</strong> دفعة
                                        | ملفات: ${session.total_files}
                                        | ناجح: ${session.stats.successful} | مكرر: ${session.stats.duplicates} | أخطاء: ${session.stats.errors}
                                    </small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm" onclick="app.resumePendingUpload('${session.id}')">
                                        <i class="fas fa-play me-1"></i> استئناف الرفع
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="app.discardPendingUpload('${session.id}')">
                                        <i class="fas fa-trash me-1"></i> تجاهل
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;

                        // إضافة بعد تحميل الصفحة
                        const container = document.getElementById('filePreviewsContainer');
                        if (container) {
                            container.parentNode.insertBefore(resumeBar, container);
                        } else {
                            document.body.appendChild(resumeBar);
                        }
                    }

                    async resumePendingUpload(sessionId) {
                        const session = await uploadDB.getSession(sessionId);
                        if (!session) {
                            this.showAlert('لم يتم العثور على جلسة الرفع', 'warning');
                            return;
                        }

                        // إزالة شريط الاستئناف
                        const resumeBar = document.getElementById('upload-resume-bar');
                        if (resumeBar) resumeBar.remove();

                        // تحقق أن لدينا ملفات محلية
                        if (!this.processedFiles || this.processedFiles.length === 0) {
                            this.showAlert('يرجى إعادة اختيار المجلد لاستئناف الرفع. الملفات المحلية لم تعد متوفرة في المتصفح.', 'info');
                            // حفظ معرّف الجلسة للاستئناف بعد اختيار المجلد
                            this._uploadSession.pendingResumeSessionId = sessionId;
                            return;
                        }

                        console.log(`▶️ استئناف الرفع من الدفعة ${session.last_completed_batch + 2}/${session.total_batches}...`);
                        this._uploadSession.currentSessionId = sessionId;
                        this._uploadSession.isPaused = false;
                        this._uploadSession.pauseReason = null;

                        await uploadDB.updateSession(sessionId, { status: 'active' });

                        // استئناف الرفع
                        await this._resumeUploadFromBatch(
                            this.processedFiles,
                            this.currentUploadType,
                            session
                        );
                    }

                    async discardPendingUpload(sessionId) {
                        await uploadDB.deleteSession(sessionId);
                        const resumeBar = document.getElementById('upload-resume-bar');
                        if (resumeBar) resumeBar.remove();
                        console.log('🗑️ تم تجاهل جلسة الرفع المعلقة');
                    }

                    async _resumeUploadFromSession() {
                        console.log('🔄 محاولة استئناف الرفع...', {
                            currentSessionId: this._uploadSession.currentSessionId,
                            hasProcessedFiles: !!this.processedFiles,
                            processedFilesCount: this.processedFiles?.length || 0
                        });

                        // البحث عن جلسة معلقة
                        let sessionId = this._uploadSession.currentSessionId;
                        let session = null;

                        if (sessionId) {
                            session = await uploadDB.getSession(sessionId);
                        }

                        // إذا لم نجد الجلسة بالمعرف الحالي، نبحث عن أي جلسة معلقة
                        if (!session || (session.status !== 'paused' && session.status !== 'active')) {
                            const pendingSessions = await uploadDB.getPendingSessions();
                            if (pendingSessions.length > 0) {
                                session = pendingSessions[pendingSessions.length - 1];
                                sessionId = session.id;
                                console.log('🔍 تم العثور على جلسة معلقة:', sessionId);
                            }
                        }

                        if (!session) {
                            console.warn('⚠️ لا توجد جلسة رفع للاستئناف');
                            return;
                        }

                        // التحقق من وجود الملفات في الذاكرة
                        if (!this.processedFiles || this.processedFiles.length === 0) {
                            console.warn('⚠️ الملفات غير متوفرة في الذاكرة - يرجى إعادة اختيار المجلد');
                            this._uploadSession.pendingResumeSessionId = session.id;
                            this._showResumePrompt(session);
                            return;
                        }

                        console.log(`▶️ استئناف الجلسة ${session.id} من الدفعة ${session.last_completed_batch + 2}/${session.total_batches}`);

                        this._uploadSession.currentSessionId = session.id;
                        this._uploadSession.isPaused = false;
                        this._uploadSession.pauseReason = null;
                        this._uploadSession.isUploading = true;

                        await uploadDB.updateSession(session.id, { status: 'active' });

                        await this._resumeUploadFromBatch(
                            this.processedFiles,
                            this.currentUploadType || 'folder',
                            session
                        );

                        // بعد اكتمال الاستئناف، مسح الملفات إذا انتهى الرفع بنجاح
                        if (!this._uploadSession.isPaused) {
                            refreshAllFolderFiles();
                            this.processedFiles = null;
                            this.currentUploadType = null;

                            setTimeout(() => {
                                const progressSection = document.getElementById('uploadProgressSection');
                                if (progressSection) {
                                    progressSection.style.transition = 'opacity 0.5s ease';
                                    progressSection.style.opacity = '0';
                                    setTimeout(() => {
                                        progressSection.style.display = 'none';
                                        progressSection.style.opacity = '1';
                                    }, 500);
                                }
                            }, 3000);
                        }
                    }

                    async _resumeUploadFromBatch(files, uploadType, session) {
                        const batches = this.createBatches(files, 10, 50 * 1024 * 1024);
                        const startBatch = session.last_completed_batch + 1;

                        if (startBatch >= batches.length) {
                            console.log('✅ جميع الدفعات مكتملة بالفعل');
                            await uploadDB.updateSession(session.id, { status: 'completed' });
                            return;
                        }

                        const options = session.options || {};
                        this._uploadSession.isUploading = true;

                        // إعداد شريط التقدم
                        this.setupUploadProgress();
                        this.showRealTimeUploadProgress(batches.length);

                        let totalSuccessful = session.stats.successful;
                        let totalDuplicates = session.stats.duplicates;
                        let totalErrors = session.stats.errors;
                        let duplicateSessionId = null;

                        console.log(`▶️ استئناف من الدفعة ${startBatch + 1}/${batches.length}`);
                        this.updateRealTimeProgress(
                            Math.round((startBatch / batches.length) * 100),
                            `استئناف... مكتمل: ${totalSuccessful}, مكرر: ${totalDuplicates}, أخطاء: ${totalErrors}`
                        );

                        for (let i = startBatch; i < batches.length; i++) {
                            // فحص إيقاف مؤقت
                            if (this._uploadSession.isPaused) {
                                console.log(`⏸️ تم إيقاف الرفع عند الدفعة ${i + 1}`);
                                await uploadDB.updateSession(session.id, {
                                    status: 'paused',
                                    stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                });
                                return;
                            }

                            // فحص الاتصال قبل كل دفعة
                            if (!navigator.onLine) {
                                console.warn('🔴 لا يوجد اتصال - إيقاف الرفع');
                                this._uploadSession.isPaused = true;
                                this._uploadSession.pauseReason = 'offline';
                                await uploadDB.updateSession(session.id, {
                                    status: 'paused',
                                    pause_reason: 'offline',
                                    stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                });
                                return;
                            }

                            this.updateBatchProgress(i, batches.length, `معالجة الدفعة ${i + 1}/${batches.length}...`);

                            try {
                                const result = await this.uploadSingleBatch(batches[i], i, batches.length, options);

                                if (result.success) {
                                    totalSuccessful += result.statistics?.files_saved || 0;
                                    const batchDuplicates = result.duplicate_results?.duplicates_found || result.statistics?.duplicates_detected || 0;
                                    totalDuplicates += batchDuplicates;

                                    if (result.session_id && !duplicateSessionId) {
                                        duplicateSessionId = result.session_id;
                                    }

                                    this.updateProcessedFilesStatus(result.statistics?.files_saved || 0, 'completed');

                                    // تحديث ملفات المجلدات المرفوضة إلى فشل
                                    if (result.folder_analysis?.rejected_folders?.length > 0) {
                                        const rejectedCount = this.markRejectedFolderFilesAsFailed(result.folder_analysis.rejected_folders);
                                        totalErrors += rejectedCount;
                                    }

                                    if (batchDuplicates > 0 && result.duplicate_results?.duplicate_files) {
                                        this.updateSpecificDuplicateFiles(result.duplicate_results.duplicate_files);
                                    }
                                } else {
                                    totalErrors++;
                                    this.updateProcessedFilesStatus(batches[i].length, 'failed');
                                }
                            } catch (error) {
                                // إذا كان الخطأ بسبب الشبكة، أوقف وانتظر استئناف
                                if (!navigator.onLine || error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                                    console.warn('🔴 خطأ شبكة أثناء الرفع - إيقاف مؤقت');
                                    this._uploadSession.isPaused = true;
                                    this._uploadSession.pauseReason = 'offline';
                                    await uploadDB.updateSession(session.id, {
                                        status: 'paused',
                                        pause_reason: 'network_error',
                                        stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                    });
                                    this._showConnectionStatus(false);
                                    return;
                                }
                                totalErrors++;
                                this.updateProcessedFilesStatus(batches[i].length, 'failed');
                            }

                            // تحديث الجلسة بعد كل دفعة ناجحة
                            await uploadDB.updateSession(session.id, {
                                last_completed_batch: i,
                                completed_batches: i + 1,
                                stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                            });

                            const overallProgress = Math.round(((i + 1) / batches.length) * 100);
                            this.updateRealTimeProgress(
                                overallProgress,
                                `مكتمل: ${totalSuccessful}, مكرر: ${totalDuplicates}, أخطاء: ${totalErrors}`
                            );

                            if (i < batches.length - 1) {
                                await new Promise(resolve => setTimeout(resolve, 500));
                            }
                        }

                        // اكتملت جميع الدفعات
                        this._uploadSession.isUploading = false;
                        await uploadDB.updateSession(session.id, { status: 'completed' });

                        // تحديث الملفات المتبقية في حالة "قيد المعالجة"
                        const remainingProcessing = Array.from(this.files.values()).filter(f => f.status === 'processing');
                        if (remainingProcessing.length > 0) {
                            remainingProcessing.forEach(fileData => {
                                fileData.status = 'completed';
                                this.updateFileStatus(fileData.id, 'completed');
                            });
                            totalSuccessful += remainingProcessing.length;
                        }

                        this.updateBatchProgress(batches.length, batches.length, 'تم الانتهاء!');
                        this.updateRealTimeProgress(100, `النهاية: مكتمل ${totalSuccessful}, مكرر ${totalDuplicates}, أخطاء ${totalErrors}`);
                        this.updateFileCounts();
                        this.loadAnalytics();
                        refreshAllFolderFiles();

                        setTimeout(() => {
                            this.hideBatchUploadProgress();
                            this.hideRealTimeProgress();
                        }, 3000);

                        // تنظيف الجلسة المكتملة
                        setTimeout(() => uploadDB.deleteSession(session.id), 10000);
                    }

                    // فحص وجود ملفات مكررة موجودة مسبقاً
                    async checkForExistingDuplicates() {
                        const sessionId = sessionStorage.getItem('duplicate_files_session_id');
                        if (sessionId) {
                            try {
                                const response = await fetch(`/admin/file/duplicate-summary?session_id=${sessionId}`);
                                if (response.ok) {
                                    const result = await response.json();
                                    if (result.success && result.data && result.data.files.length > 0) {
                                        window.CURRENT_DUPLICATE_SESSION_ID = sessionId;
                                        const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                                        if (duplicateBtn) {
                                            duplicateBtn.style.display = 'inline-block';
                                            duplicateBtn.innerHTML =
                                                `<i class="fas fa-clone me-2"></i>عرض الملفات المكررة (${result.data.total_duplicates})`;
                                            duplicateBtn.classList.add('btn-warning');
                                            duplicateBtn.classList.remove('btn-secondary');
                                        }
                                        console.log('🔍 تم العثور على ملفات مكررة موجودة مسبقاً:', result.data);
                                    }
                                }
                            } catch (error) {
                                console.log('لا توجد ملفات مكررة سابقة');
                            }
                        }
                    }

                    initializeEventListeners() {
                        const fileDropZone = document.getElementById('fileDropZone');
                        if (fileDropZone) {
                            fileDropZone.style.display = 'none';
                        }

                        const dropZone = document.getElementById('fileDropZone');
                        const fileInput = document.getElementById('fileInput');
                        const folderInput = document.getElementById('folderInput');
                        const selectFolderBtn = document.getElementById('selectFolderBtn');
                        const selectFolderBtn2 = document.getElementById('selectFolderBtn2');
                        const selectFolderBtnMain = document.getElementById('selectFolderBtnMain');
                        const generateRecordBtn = document.getElementById('generateRecordBtn');
                        const clearAllBtn = document.getElementById('clearAllBtn');
                        const clearAllBtn2 = document.getElementById('clearAllBtn2');
                        const clearAllBtnMain = document.getElementById('clearAllBtnMain');
                        const startUploadBtn = document.getElementById('startUploadBtn');
                        const startUploadBtnMain = document.getElementById('startUploadBtnMain');

                        // Drag and drop events - only if dropZone exists
                        if (dropZone) {
                            dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
                            dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
                            dropZone.addEventListener('drop', this.handleDrop.bind(this));
                        }

                        // File and folder selection - only if elements exist
                        if (selectFolderBtn && folderInput) {
                            selectFolderBtn.addEventListener('click', () => folderInput.click());
                        }
                        if (selectFolderBtn2 && folderInput) {
                            selectFolderBtn2.addEventListener('click', () => folderInput.click());
                        }
                        if (selectFolderBtnMain && folderInput) {
                            selectFolderBtnMain.addEventListener('click', () => folderInput.click());
                        }

                        if (fileInput) {
                            fileInput.addEventListener('change', this.handleFileSelect.bind(this));
                        }
                        if (folderInput) {
                            folderInput.addEventListener('change', this.handleFolderSelect.bind(this));
                        }

                        // Record number generation - only if element exists
                        if (generateRecordBtn) {
                            generateRecordBtn.addEventListener('click', this.generateRecordNumber.bind(this));
                        }

                        // Clear all files - only if elements exist
                        if (clearAllBtn) {
                            clearAllBtn.addEventListener('click', this.clearAllFiles.bind(this));
                        }
                        if (clearAllBtn2) {
                            clearAllBtn2.addEventListener('click', this.clearAllFiles.bind(this));
                        }
                        if (clearAllBtnMain) {
                            clearAllBtnMain.addEventListener('click', this.clearAllFiles.bind(this));
                        }

                        // Start upload manually - only if elements exist
                        if (startUploadBtn) {
                            startUploadBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                console.log('🔴 تم الضغط على زر بدء الرفع (startUploadBtn)');
                                console.log('📊 حالة النظام الحالية:', {
                                    filesCount: this.files.size,
                                    processedFilesCount: this.processedFiles ? this.processedFiles.length : 0,
                                    currentUploadType: this.currentUploadType
                                });
                                this.startUploads();
                            });
                        }
                        if (startUploadBtnMain) {
                            startUploadBtnMain.addEventListener('click', (e) => {
                                e.preventDefault();
                                console.log('🔴 تم الضغط على زر بدء الرفع الرئيسي (startUploadBtnMain)');
                                console.log('📊 حالة النظام الحالية:', {
                                    filesCount: this.files.size,
                                    processedFilesCount: this.processedFiles ? this.processedFiles.length : 0,
                                    currentUploadType: this.currentUploadType
                                });
                                this.startUploads();
                            });
                        }

                        // Excel import options visibility - only if fileInput exists
                        if (fileInput) {
                            fileInput.addEventListener('change', this.toggleExcelOptions.bind(this));
                        }

                        // Enable/disable Excel import target table
                        const enableExcelImport = document.getElementById('enableExcelImport');
                        if (enableExcelImport) {
                            enableExcelImport.addEventListener('change', (e) => {
                                const excelTargetOptions = document.getElementById('excelTargetOptions');
                                if (excelTargetOptions) {
                                    excelTargetOptions.style.display = e.target.checked ? 'block' : 'none';
                                }
                            });
                        }

                        // مستمع للتحكم في تشغيل/إيقاف كشف الملفات المكررة
                        const enableDuplicateDetection = document.getElementById('enableDuplicateDetection');
                        if (enableDuplicateDetection) {
                            enableDuplicateDetection.addEventListener('change', (e) => {
                                this.duplicateDetectionEnabled = e.target.checked;
                                console.log('🔍 تم تغيير حالة كشف الملفات المكررة:', this.duplicateDetectionEnabled ? 'مفعل' : 'معطل');

                                // عرض رسالة للمستخدم
                                if (this.duplicateDetectionEnabled) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'تم تفعيل كشف الملفات المكررة',
                                        text: 'سيتم الآن كشف جميع الملفات المكررة أثناء الرفع',
                                        timer: 2000,
                                        showConfirmButton: false,
                                        toast: true,
                                        position: 'top-end'
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'تم إيقاف كشف الملفات المكررة',
                                        text: 'سيتم تجاهل جميع الملفات المكررة وقبول الملفات الجديدة',
                                        timer: 2000,
                                        showConfirmButton: false,
                                        toast: true,
                                        position: 'top-end'
                                    });
                                }
                            });
                        }

                        // Excel file selection for folder uploads
                        const excelFileInput = document.getElementById('excelFileInput');
                        if (excelFileInput) {
                            excelFileInput.addEventListener('change', (e) => {
                                const file = e.target.files[0];
                                const statusDiv = document.getElementById('excelFileStatus');

                                if (file && statusDiv) {
                                    statusDiv.innerHTML =
                                        `<i class="fas fa-file-excel text-success"></i> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                                    console.log('📊 ملف Excel محدد:', {
                                        name: file.name,
                                        size: file.size,
                                        type: file.type
                                    });
                                } else {
                                    statusDiv.innerHTML = '';
                                }
                            });
                        }

                        // File filter radio buttons
                        document.querySelectorAll('input[name="fileFilter"]').forEach(radio => {
                            radio.addEventListener('change', this.handleFilterChange.bind(this));
                        });
                    }

                    clearAllFiles() {
                        this.files.clear();

                        const fileInput = document.getElementById('fileInput');
                        const folderInput = document.getElementById('folderInput');
                        const filePreviewsContainer = document.getElementById('filePreviewsContainer');
                        const startUploadBtn = document.getElementById('startUploadBtn');
                        const startUploadBtnMain = document.getElementById('startUploadBtnMain');
                        const uploadProgressSection = document.getElementById('uploadProgressSection');

                        if (fileInput) {
                            fileInput.value = '';
                        }
                        if (folderInput) {
                            folderInput.value = '';
                        }
                        if (filePreviewsContainer) {
                            filePreviewsContainer.innerHTML = `
                        <div class="col-12 text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                            <p>لا توجد ملفات محددة بعد</p>
                            <small class="text-muted">اختر ملفات أو مجلدات لبدء العملية</small>
                        </div>
                    `;
                        }

                        // إخفاء أزرار الرفع في جميع الأماكن - only if they exist
                        if (startUploadBtn) {
                            startUploadBtn.style.display = 'none';
                        }
                        if (startUploadBtnMain) {
                            startUploadBtnMain.style.display = 'none';
                        }
                        if (uploadProgressSection) {
                            uploadProgressSection.style.display = 'none';
                        }

                        this.updateFileCounts();

                        console.log('🧹 تم مسح جميع الملفات والمجلدات');
                    }

                    async generateRecordNumber() {
                        try {
                            const response = await fetch('/api/files/generate-record-number', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (response.ok) {
                                const result = await response.json();
                                document.getElementById('recordNumber').value = result.record_number;

                                // عرض رقم الملف في Console
                                console.log('🔢 رقم الملف المولد:', result.record_number);
                                console.log('📄 تفاصيل إضافية:', {
                                    recordNumber: result.record_number,
                                    timestamp: new Date().toISOString(),
                                    source: 'file_id_number من قاعدة البيانات'
                                });

                                this.showAlert(`تم إنشاء رقم الملف: ${result.record_number}`, 'success');
                            } else {
                                throw new Error('Failed to generate record number');
                            }
                        } catch (error) {
                            console.error('❌ خطأ في توليد رقم الملف:', error);
                            this.showAlert('فشل في إنشاء رقم الملف', 'danger');
                        }
                    }

                    toggleExcelOptions() {
                        const files = Array.from(document.getElementById('fileInput').files);
                        const hasExcelFiles = files.some(file => {
                            const ext = file.name.split('.').pop().toLowerCase();
                            return ['xlsx', 'xls', 'csv', 'zip', 'rar', '7z'].includes(ext);
                        });

                        document.getElementById('excelImportSection').style.display =
                            hasExcelFiles ? 'block' : 'none';
                    }

                    handleDragOver(e) {
                        e.preventDefault();
                        e.currentTarget.classList.add('drag-over');
                    }

                    handleDragLeave(e) {
                        e.currentTarget.classList.remove('drag-over');
                    }

                    handleDrop(e) {
                        e.preventDefault();
                        e.currentTarget.classList.remove('drag-over');

                        const files = Array.from(e.dataTransfer.files);
                        this.processFiles(files);
                    }

                    handleFileSelect(e) {
                        const files = Array.from(e.target.files);
                        this.processFiles(files);
                        e.target.value = ''; // Reset input
                    }

                    handleFolderSelect(e) {
                        const files = Array.from(e.target.files);
                        console.log('📁 تم اختيار مجلد يحتوي على:', files.length, 'ملف');

                        // تحليل المجلد وعرض التفاصيل
                        const folderAnalysis = this.analyzeFolderStructure(files);
                        console.log('📊 تحليل المجلد:', folderAnalysis);

                        // عرض هيكل المجلدات
                        console.log('🏗️ هيكل المجلدات:', folderAnalysis.folderHierarchy);

                        // عرض تفاصيل المجلدات الأب
                        if (folderAnalysis.parentFolders.length > 0) {
                            console.log('📂 المجلدات الأب المكتشفة:', folderAnalysis.parentFolders);
                            folderAnalysis.parentFolders.forEach(parent => {
                                console.log(`📁 مجلد أب: ${parent} - سيتم تجاهله وتحليل محتوياته`);
                            });
                        }

                        // عرض تفاصيل إضافية حول مجلدات الهوية
                        if (folderAnalysis.identityFolders.length > 0) {
                            console.log('🆔 مجلدات الهوية المكتشفة:', folderAnalysis.identityFolders);
                            folderAnalysis.identityFolders.forEach(identity => {
                                console.log(`📋 ${identity}: سيتم التحقق من وجوده في النظام`);
                            });
                        } else {
                            console.log('⚠️ لم يتم العثور على مجلدات بأسماء أرقام هوية');
                            console.log('💡 المجلدات الموجودة:', folderAnalysis.folders);
                            console.log('💡 تنبيه: يجب أن تحتوي على مجلدات فرعية بأسماء أرقام هوية صحيحة');
                        }

                        // عرض المجلدات غير الصالحة
                        if (folderAnalysis.invalidFolders.length > 0) {
                            console.log('❌ مجلدات غير صالحة (ستُتجاهل):', folderAnalysis.invalidFolders);
                        }

                        this.processFolderFiles(files, 'folder');
                        e.target.value = ''; // Reset input
                    }

                    analyzeFolderStructure(files) {
                        const analysis = {
                            totalFiles: files.length,
                            folders: new Set(),
                            identityFolders: new Set(),
                            parentFolders: new Set(),
                            invalidFolders: new Set(),
                            fileTypes: {},
                            structure: {},
                            folderHierarchy: {}
                        };

                        files.forEach(file => {
                            // تحليل المسار الكامل
                            const pathParts = file.webkitRelativePath.split('/').filter(part => part.trim() !== '');

                            if (pathParts.length === 0) return;

                            // المجلد المباشر للملف (المجلد الأخير في المسار)
                            const directFolder = pathParts[pathParts.length - 2] || 'root';

                            // جميع المجلدات في المسار
                            pathParts.slice(0, -1).forEach((folderName, index) => {
                                analysis.folders.add(folderName);

                                // تحديد نوع المجلد
                                if (/^\d{8,10}$/.test(folderName)) {
                                    analysis.identityFolders.add(folderName);
                                } else if (index === 0) {
                                    // المجلد الأول في المسار (المجلد الأب)
                                    analysis.parentFolders.add(folderName);
                                } else {
                                    // مجلد متوسط أو غير صالح
                                    analysis.invalidFolders.add(folderName);
                                }
                            });

                            // بناء هيكل التسلسل الهرمي للمجلدات
                            let currentLevel = analysis.folderHierarchy;
                            pathParts.slice(0, -1).forEach(folderName => {
                                if (!currentLevel[folderName]) {
                                    currentLevel[folderName] = {
                                        files: [],
                                        subfolders: {},
                                        isIdentityFolder: /^\d{8,10}$/.test(folderName),
                                        isParentFolder: false
                                    };
                                }
                                currentLevel = currentLevel[folderName].subfolders;
                            });

                            // إضافة الملف إلى المجلد المناسب
                            let targetLevel = analysis.folderHierarchy;
                            pathParts.slice(0, -1).forEach(folderName => {
                                targetLevel = targetLevel[folderName];
                                if (pathParts.slice(0, -1)[pathParts.slice(0, -1).length - 1] === folderName) {
                                    targetLevel.files.push({
                                        name: file.name,
                                        size: file.size,
                                        type: file.type,
                                        fullPath: file.webkitRelativePath
                                    });
                                }
                                targetLevel = targetLevel.subfolders;
                            });

                            // تحليل نوع الملف
                            const ext = file.name.split('.').pop().toLowerCase();
                            analysis.fileTypes[ext] = (analysis.fileTypes[ext] || 0) + 1;

                            // بناء هيكل المجلد التقليدي (للتوافق مع الكود الموجود)
                            if (!analysis.structure[directFolder]) {
                                analysis.structure[directFolder] = [];
                            }
                            analysis.structure[directFolder].push({
                                name: file.name,
                                size: file.size,
                                type: file.type,
                                fullPath: file.webkitRelativePath
                            });
                        });

                        // تحويل Sets إلى Arrays
                        analysis.folders = Array.from(analysis.folders);
                        analysis.identityFolders = Array.from(analysis.identityFolders);
                        analysis.parentFolders = Array.from(analysis.parentFolders);
                        analysis.invalidFolders = Array.from(analysis.invalidFolders);

                        // تحديد المجلدات الأب
                        Object.keys(analysis.folderHierarchy).forEach(rootFolder => {
                            analysis.folderHierarchy[rootFolder].isParentFolder = true;
                        });

                        return analysis;
                    }

                    processFiles(files) {
                        if (!this.validateInputs()) return;

                        console.log('🔄 بدء معالجة الملفات...', { count: files.length });

                        files.forEach(file => {
                            const fileId = this.generateFileId();
                            const fileData = {
                                id: fileId,
                                file: file,
                                status: 'pending',
                                progress: 0,
                                type: this.detectFileType(file),
                                preview: null,
                                processing: {
                                    compression: false,
                                    cloudSync: false,
                                    ocr: false
                                }
                            };

                            this.files.set(fileId, fileData);
                            this.createFilePreview(fileData);
                        });

                        this.updateFileCounts();

                        // إضافة تأخير قصير قبل بدء الرفع للتأكد من تحديث الواجهة
                        setTimeout(() => {
                            // إعداد التقدم قبل بدء الرفع
                            this.setupUploadProgress();
                            this.startUploads();
                        }, 100);
                    }

                    setupUploadProgress() {
                        console.log('🔧 إعداد شريط تقدم الرفع...');

                        const uploadProgressSection = document.getElementById('uploadProgressSection');
                        if (uploadProgressSection) {
                            uploadProgressSection.style.display = 'block';
                        }

                        const progressText = document.getElementById('progressText');
                        if (progressText) {
                            progressText.textContent = 'جاري إعداد الرفع...';
                        }

                        const overallProgress = document.getElementById('overallProgress');
                        if (overallProgress) {
                            overallProgress.style.width = '0%';
                            overallProgress.setAttribute('aria-valuenow', 0);
                            overallProgress.textContent = '0%';
                        }

                        this.updateFileCounts();
                        console.log('✅ تم إعداد شريط تقدم الرفع بنجاح');
                    }

                    processFolderFiles(files, type) {
                        if (!this.validateInputs()) return;

                        console.log(`🔄 بدء معالجة ${type === 'folder' ? 'المجلد' : 'مجلد الصور'}...`);

                        // تنظيف البيانات السابقة لضمان دقة العدّ
                        this.files = new Map();
                        const container = document.getElementById('filePreviewsContainer');
                        if (container) {
                            const existingFolders = container.querySelectorAll('.folder-section');
                            existingFolders.forEach(el => el.remove());
                            const oldPagination = container.querySelector('.pagination-controls');
                            if (oldPagination) oldPagination.remove();
                        }

                        // تحليل هيكل المجلدات لتحديد الملفات الصالحة
                        const folderAnalysis = this.analyzeFolderStructure(files);

                        // قبول جميع الملفات بدون فلترة - لضمان عدّ كل ملف
                        const validFiles = files.filter(file => {
                            // تجاهل الملفات الخفية وملفات النظام فقط
                            const fileName = file.name;
                            if (fileName.startsWith('.') || fileName === 'Thumbs.db' || fileName === 'desktop.ini') {
                                return false;
                            }
                            return true;
                        });

                        console.log(`📊 الملفات: ${files.length} إجمالي → ${validFiles.length} صالح (بعد استبعاد ملفات النظام فقط)`);

                        if (validFiles.length === 0) {
                            console.log('⚠️ لا توجد ملفات للرفع.');
                            return;
                        }

                        // تجميع الملفات حسب مجلدها المباشر (بدون عرضها بعد)
                        this._pagination.groupedFiles = new Map();
                        this._pagination.folderOrder = [];

                        validFiles.forEach(file => {
                            const fileId = this.generateFileId();
                            const pathParts = file.webkitRelativePath.split('/').filter(part => part.trim() !== '');
                            // البحث عن مجلد هوية (8-10 أرقام) في المسار
                            const identityFolder = pathParts.find(part => /^\d{8,10}$/.test(part));

                            // تحديد اسم مجلد التجميع: مجلد الهوية أو المجلد الأب المباشر
                            let groupFolder;
                            if (identityFolder) {
                                groupFolder = identityFolder;
                            } else if (pathParts.length >= 2) {
                                // استخدام المجلد الأب المباشر للملف
                                groupFolder = pathParts[pathParts.length - 2];
                            } else {
                                groupFolder = 'ملفات منفصلة';
                            }

                            const fileData = {
                                id: fileId,
                                file: file,
                                status: 'pending',
                                progress: 0,
                                type: this.detectFileType(file),
                                preview: null,
                                folderPath: file.webkitRelativePath.split('/').slice(0, -1).join('/'),
                                relativePath: file.webkitRelativePath,
                                identityFolder: identityFolder,
                                source: type === 'folder' ? 'folder-upload' : 'images-folder',
                                processing: {
                                    compression: document.getElementById('compressImages')?.checked ?? false,
                                    cloudSync: document.getElementById('cloudSync')?.checked ?? false,
                                    ocr: false
                                }
                            };

                            // تخزين جميع الملفات في files Map للإحصائيات الدقيقة
                            this.files.set(fileId, fileData);

                            // تجميع حسب المجلد
                            if (!this._pagination.groupedFiles.has(groupFolder)) {
                                this._pagination.groupedFiles.set(groupFolder, []);
                                this._pagination.folderOrder.push(groupFolder);
                            }
                            this._pagination.groupedFiles.get(groupFolder).push(fileData);
                        });

                        // حساب عدد الصفحات
                        const totalFolders = this._pagination.folderOrder.length;
                        this._pagination.totalPages = Math.ceil(totalFolders / this._pagination.foldersPerPage);
                        this._pagination.currentPage = 1;
                        this._pagination.renderedFolders = new Set();

                        console.log(`📊 تجميع المجلدات: ${totalFolders} مجلد في ${this._pagination.totalPages} صفحة`);

                        // تحديث الإحصائيات الإجمالية (لجميع الملفات)
                        this.updateFileCounts();
                        this.toggleExcelOptions();

                        console.log(`✅ تمت معالجة ${validFiles.length} ملف صالح من ${type === 'folder' ? 'المجلد' : 'مجلد الصور'}`);
                        console.log('📊 حالة النظام:', {
                            totalFiles: this.files.size,
                            totalFolders: totalFolders,
                            pagesCount: this._pagination.totalPages,
                            identityFolders: folderAnalysis.identityFolders,
                            parentFolders: folderAnalysis.parentFolders
                        });

                        // إظهار زر الرفع
                        const startUploadBtn = document.getElementById('startUploadBtn');
                        if (startUploadBtn) {
                            startUploadBtn.style.display = 'inline-block';
                        }

                        // حفظ الملفات المعالجة للرفع اللاحق
                        this.processedFiles = validFiles;
                        this.currentUploadType = type;

                        console.log(`✨ تم تحضير ${validFiles.length} ملف للرفع. اضغط على زر "بدء الرفع" لتنفيذ العملية.`);

                        // إعداد شريط التقدم
                        this.setupUploadProgress();

                        // عرض الصفحة الأولى من المجلدات فقط
                        this.renderFolderPage(1);

                        // استئناف تلقائي إذا كانت هناك جلسة معلقة تنتظر اختيار المجلد
                        if (this._uploadSession.pendingResumeSessionId) {
                            const pendingId = this._uploadSession.pendingResumeSessionId;
                            console.log('▶️ تم اكتشاف جلسة معلقة - استئناف تلقائي...');
                            setTimeout(() => this.resumePendingUpload(pendingId), 500);
                        }
                    }

                    /**
                     * عرض صفحة معينة من المجلدات
                     */
                    renderFolderPage(pageNumber) {
                        const { foldersPerPage, folderOrder, groupedFiles, totalPages } = this._pagination;

                        // التحقق من رقم الصفحة
                        if (pageNumber < 1 || pageNumber > totalPages) return;
                        this._pagination.currentPage = pageNumber;

                        // مسح الطابور المعلق وإعادة تعيين حالة المعالجة
                        this._apiQueue = [];
                        this._apiProcessing = false;

                        const container = document.getElementById('filePreviewsContainer');

                        // إزالة المجلدات المعروضة (وليس عناصر التحكم)
                        const existingFolders = container.querySelectorAll('.folder-section');
                        existingFolders.forEach(el => el.remove());
                        const emptyMessage = container.querySelector('.text-center.text-muted');
                        if (emptyMessage) emptyMessage.remove();

                        // إزالة شريط pagination القديم
                        const oldPagination = container.querySelector('.pagination-controls');
                        if (oldPagination) oldPagination.remove();

                        // حساب نطاق المجلدات لهذه الصفحة
                        const startIdx = (pageNumber - 1) * foldersPerPage;
                        const endIdx = Math.min(startIdx + foldersPerPage, folderOrder.length);
                        const pageFolders = folderOrder.slice(startIdx, endIdx);

                        console.log(`📄 عرض الصفحة ${pageNumber}/${totalPages}: المجلدات ${startIdx + 1} إلى ${endIdx} من ${folderOrder.length}`);

                        // عرض المجلدات لهذه الصفحة
                        pageFolders.forEach(folderName => {
                            const filesInFolder = groupedFiles.get(folderName);
                            if (!filesInFolder || filesInFolder.length === 0) return;

                            const folderId = this.sanitizeFolderId(folderName);

                            // إنشاء قسم المجلد
                            const folderSection = this.createFolderSection(folderName, folderId, filesInFolder[0]);
                            container.appendChild(folderSection);

                            // إضافة جميع ملفات هذا المجلد
                            const filesGrid = folderSection.querySelector('.folder-files-grid');
                            filesInFolder.forEach(fileData => {
                                const fileElement = this.createFileElement(fileData);
                                filesGrid.appendChild(fileElement);
                            });

                            // تحديث إحصائيات المجلد
                            this.updateFolderStats(folderId);

                            // استدعاء API لجلب اسم الشخص فقط (لا نستدعي loadFolderFiles لأن الملفات محلية ولم تُرفع بعد)
                            const isIdentityFolder = /^\d{8,10}$/.test(folderName);
                            if (isIdentityFolder) {
                                this.queueApiCall(() => this.fetchPersonName(folderName, folderId));
                            }

                            this._pagination.renderedFolders.add(folderName);
                        });

                        // إضافة شريط الـ Pagination
                        this.renderPaginationControls(container);

                        // تحديث العدادات (تبقى دقيقة لأنها تعتمد على this.files)
                        this.updateFileCounts();
                    }

                    /**
                     * عرض أزرار التنقل بين الصفحات
                     */
                    renderPaginationControls(container) {
                        const { currentPage, totalPages, folderOrder, foldersPerPage } = this._pagination;

                        if (totalPages <= 1) return;

                        const paginationDiv = document.createElement('div');
                        paginationDiv.className = 'pagination-controls col-12 mt-4';

                        // حساب نطاق الصفحات المعروضة
                        const maxVisiblePages = 7;
                        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                        if (endPage - startPage < maxVisiblePages - 1) {
                            startPage = Math.max(1, endPage - maxVisiblePages + 1);
                        }

                        let paginationHtml = `
                            <div class="card">
                                <div class="card-body py-3">
                                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                                        <div class="text-muted small">
                                            <i class="fas fa-folder me-1"></i>
                                            عرض المجلدات <strong>${((currentPage - 1) * foldersPerPage) + 1}</strong>
                                            إلى <strong>${Math.min(currentPage * foldersPerPage, folderOrder.length)}</strong>
                                            من <strong>${folderOrder.length}</strong> مجلد
                                        </div>
                                        <nav aria-label="تنقل بين صفحات المجلدات">
                                            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">
                                                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                                                    <a class="page-link" href="#" onclick="app.renderFolderPage(1); return false;" title="الصفحة الأولى">
                                                        <i class="fas fa-angle-double-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                                                    <a class="page-link" href="#" onclick="app.renderFolderPage(${currentPage - 1}); return false;" title="الصفحة السابقة">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>`;

                        for (let i = startPage; i <= endPage; i++) {
                            paginationHtml += `
                                                <li class="page-item ${i === currentPage ? 'active' : ''}">
                                                    <a class="page-link" href="#" onclick="app.renderFolderPage(${i}); return false;">${i}</a>
                                                </li>`;
                        }

                        paginationHtml += `
                                                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                                                    <a class="page-link" href="#" onclick="app.renderFolderPage(${currentPage + 1}); return false;" title="الصفحة التالية">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                                                    <a class="page-link" href="#" onclick="app.renderFolderPage(${totalPages}); return false;" title="الصفحة الأخيرة">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                        <div class="d-flex align-items-center gap-2">
                                            <label class="small text-muted mb-0">مجلدات بالصفحة:</label>
                                            <select class="form-select form-select-sm" style="width: auto;" onchange="app.changeFoldersPerPage(parseInt(this.value))">
                                                <option value="5" ${foldersPerPage === 5 ? 'selected' : ''}>5</option>
                                                <option value="10" ${foldersPerPage === 10 ? 'selected' : ''}>10</option>
                                                <option value="20" ${foldersPerPage === 20 ? 'selected' : ''}>20</option>
                                                <option value="50" ${foldersPerPage === 50 ? 'selected' : ''}>50</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                        paginationDiv.innerHTML = paginationHtml;
                        container.appendChild(paginationDiv);
                    }

                    /**
                     * تغيير عدد المجلدات في كل صفحة
                     */
                    changeFoldersPerPage(count) {
                        this._pagination.foldersPerPage = count;
                        this._pagination.totalPages = Math.ceil(this._pagination.folderOrder.length / count);
                        this._pagination.currentPage = 1;
                        this.renderFolderPage(1);
                    }

                    getFolderStructure() {
                        const structure = {};
                        this.files.forEach(fileData => {
                            if (fileData.folderPath) {
                                if (!structure[fileData.folderPath]) {
                                    structure[fileData.folderPath] = [];
                                }
                                structure[fileData.folderPath].push(fileData);
                            }
                        });
                        return structure;
                    }

                    async loadAnalytics() {
                        try {
                            const response = await fetch('/api/files/analytics/new', {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (response.ok) {
                                const data = await response.json();

                                console.log('📊 البيانات المستلمة من API:', data);

                                // التحقق من وجود البيانات المطلوبة
                                if (data.total_files && typeof data.total_files === 'object') {
                                    this.updateFilesStats(data.total_files);
                                } else {
                                    console.warn('⚠️ بيانات total_files غير صالحة:', data.total_files);
                                    this.updateFilesStats({});
                                }

                                if (data.total_storage_size && typeof data.total_storage_size === 'object') {
                                    this.updateStorageStats(data.total_storage_size);
                                } else {
                                    console.warn('⚠️ بيانات total_storage_size غير صالحة:', data.total_storage_size);
                                    this.updateStorageStats({});
                                }

                                if (data.today_files && typeof data.today_files === 'object') {
                                    this.updateTodayStats(data.today_files);
                                } else {
                                    console.warn('⚠️ بيانات today_files غير صالحة:', data.today_files);
                                    this.updateTodayStats({});
                                }

                                if (data.file_types_breakdown && typeof data.file_types_breakdown === 'object') {
                                    this.updateFileTypesStats(data.file_types_breakdown);
                                } else {
                                    console.warn('⚠️ بيانات file_types_breakdown غير صالحة:', data.file_types_breakdown);
                                    this.updateFileTypesStats({});
                                }

                                console.log('✅ تم تحديث الإحصائيات بنجاح');
                            } else {
                                const errorText = await response.text();
                                console.error('❌ استجابة خطأ من الخادم:', response.status, errorText);
                                throw new Error(`HTTP ${response.status}: ${errorText}`);
                            }
                        } catch (error) {
                            console.error('❌ خطأ في تحميل بيانات التحليلات:', error);
                            // عرض قيم افتراضية في حالة الخطأ
                            this.loadDefaultAnalytics();

                            // عرض رسالة للمستخدم
                            this.showAlert('فشل في تحميل الإحصائيات. سيتم المحاولة مرة أخرى...', 'warning');
                        }
                    }

                    updateFilesStats(totalFiles) {
                        document.getElementById('totalFilesCount').innerText = totalFiles.grand_total || 0;
                        document.getElementById('attachmentsCount').innerText = totalFiles.attachments || 0;
                        document.getElementById('enhancedCount').innerText = totalFiles.enhanced_attachments || 0;
                        document.getElementById('duplicatesCount').innerText = totalFiles.duplicate_files_temp || 0;
                        document.getElementById('grandTotalFiles').innerText = totalFiles.grand_total || 0;
                    }

                    updateStorageStats(totalStorage) {
                        document.getElementById('totalStorageSize').innerText = this.formatFileSize(totalStorage.grand_total || 0);
                        document.getElementById('attachmentsSize').innerText = this.formatFileSize(totalStorage.attachments || 0);
                        document.getElementById('enhancedSize').innerText = this.formatFileSize(totalStorage.enhanced_attachments || 0);
                        document.getElementById('duplicatesSize').innerText = this.formatFileSize(totalStorage.duplicate_files_temp || 0);
                        document.getElementById('grandTotalSize').innerText = this.formatFileSize(totalStorage.grand_total || 0);
                    }

                    updateTodayStats(todayFiles) {
                        document.getElementById('todayFilesCount').innerText = todayFiles.grand_total || 0;
                        document.getElementById('todayAttachments').innerText = todayFiles.attachments || 0;
                        document.getElementById('todayEnhanced').innerText = todayFiles.enhanced_attachments || 0;
                        document.getElementById('todayDuplicates').innerText = todayFiles.duplicate_files_temp || 0;
                        document.getElementById('grandTotalToday').innerText = todayFiles.grand_total || 0;
                    }

                    updateFileTypesStats(fileTypes) {
                        console.log('📊 تحديث إحصائيات أنواع الملفات:', fileTypes);

                        if (fileTypes) {
                            document.getElementById('imagesCount').innerText = fileTypes.images?.count || 0;
                            document.getElementById('pdfCount').innerText = fileTypes.pdf?.count || 0;
                            document.getElementById('excelCount').innerText = fileTypes.excel?.count || 0;
                            document.getElementById('wordCount').innerText = fileTypes.word?.count || 0;
                            document.getElementById('archiveCount').innerText = fileTypes.archive?.count || 0;
                            document.getElementById('otherCount').innerText = fileTypes.other?.count || 0;
                        } else {
                            console.warn('⚠️ بيانات أنواع الملفات غير صالحة:', fileTypes);
                            document.getElementById('imagesCount').innerText = 0;
                            document.getElementById('pdfCount').innerText = 0;
                            document.getElementById('excelCount').innerText = 0;
                            document.getElementById('wordCount').innerText = 0;
                            document.getElementById('archiveCount').innerText = 0;
                            document.getElementById('otherCount').innerText = 0;
                        }

                        console.log('✅ تم تحديث إحصائيات أنواع الملفات بنجاح');
                    }

                    loadDefaultAnalytics() {
                        // تحديث إجمالي الملفات
                        this.updateFilesStats({
                            attachments: 0,
                            enhanced_attachments: 0,
                            duplicate_files_temp: 0,
                            grand_total: 0
                        });

                        // تحديث حجم التخزين
                        this.updateStorageStats({
                            attachments: 0,
                            enhanced_attachments: 0,
                            duplicate_files_temp: 0,
                            grand_total: 0
                        });

                        // تحديث ملفات اليوم
                        this.updateTodayStats({
                            attachments: 0,
                            enhanced_attachments: 0,
                            duplicate_files_temp: 0,
                            grand_total: 0
                        });

                        // تحديث أنواع الملفات
                        this.updateFileTypesStats({});
                    }

                    validateInputs() {
                        // Add your validation logic here
                        return true;
                    }

                    generateFileId() {
                        // عداد تسلسلي يضمن عدم تكرار ID أبداً
                        if (!this._fileIdCounter) this._fileIdCounter = 0;
                        this._fileIdCounter++;
                        return 'file_' + Date.now() + '_' + this._fileIdCounter;
                    }

                    detectFileType(file) {
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext)) return 'image';
                        if (['pdf'].includes(ext)) return 'pdf';
                        if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel';
                        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'archive';
                        if (['doc', 'docx'].includes(ext)) return 'word';
                        return 'unknown';
                    }

                    createFilePreview(fileData) {
                        // في وضع pagination، لا يتم عرض الملف إلا إذا كان مجلده معروض
                        if (this._pagination.folderOrder.length > 0) {
                            const folderName = this.getFolderDisplayName(fileData);
                            if (!this._pagination.renderedFolders.has(folderName)) {
                                return; // المجلد غير معروض في الصفحة الحالية
                            }
                        }
                        this.createOrUpdateFolderGrouping(fileData);
                    }

                    /**
                     * إنشاء أو تحديث تجميع الملفات حسب المجلدات
                     */
                    createOrUpdateFolderGrouping(fileData) {
                        const container = document.getElementById('filePreviewsContainer');

                        // إزالة رسالة "لا توجد ملفات" إذا كانت موجودة
                        const emptyMessage = container.querySelector('.text-center.text-muted');
                        if (emptyMessage) {
                            emptyMessage.remove();
                        }

                        // تحديد اسم المجلد للتجميع
                        const folderName = this.getFolderDisplayName(fileData);
                        const folderId = this.sanitizeFolderId(folderName);

                        // البحث عن أو إنشاء قسم المجلد
                        let folderSection = document.getElementById(`folder-section-${folderId}`);
                        let isNewSection = false;

                        if (!folderSection) {
                            folderSection = this.createFolderSection(folderName, folderId, fileData);
                            container.appendChild(folderSection);
                            isNewSection = true;
                        }

                        // إضافة الملف إلى قسم المجلد
                        const filesGrid = folderSection.querySelector('.folder-files-grid');
                        const fileElement = this.createFileElement(fileData);
                        filesGrid.appendChild(fileElement);

                        // تحديث إحصائيات المجلد
                        this.updateFolderStats(folderId);

                        // تحميل البيانات من الخادم بعد إضافة القسم للـ DOM
                        if (isNewSection) {
                            const isIdentityFolder = /^\d{8,10}$/.test(folderName);
                            if (isIdentityFolder) {
                                this.queueApiCall(() => this.fetchPersonName(folderName, folderId));
                            }
                        }
                    }

                    /**
                     * الحصول على اسم المجلد للعرض
                     */
                    getFolderDisplayName(fileData) {
                        if (fileData.identityFolder) {
                            return fileData.identityFolder;
                        }
                        if (fileData.folderPath) {
                            const pathParts = fileData.folderPath.split('/');
                            return pathParts[pathParts.length - 1] || pathParts[pathParts.length - 2] || 'مجلد رئيسي';
                        }
                        return 'ملفات منفصلة';
                    }

                    /**
                     * تنظيف معرف المجلد ليكون صالح للـ HTML
                     */
                    sanitizeFolderId(folderName) {
                        return folderName.replace(/[^a-zA-Z0-9\u0600-\u06FF]/g, '_');
                    }

                    /**
                     * إنشاء قسم مجلد جديد
                     */
                    createFolderSection(folderName, folderId, firstFileData) {
                        const folderSection = document.createElement('div');
                        folderSection.className = 'folder-section mb-4';
                        folderSection.id = `folder-section-${folderId}`;

                        // تحديد أيقونة ولون المجلد بناءً على نوعه
                        const isIdentityFolder = /^\d{8,10}$/.test(folderName);
                        const folderIcon = isIdentityFolder ? 'fas fa-id-card' : 'fas fa-folder';
                        const folderColorClass = isIdentityFolder ? 'text-primary' : 'text-secondary';
                        const borderColorClass = isIdentityFolder ? 'border-primary' : 'border-secondary';

                        folderSection.innerHTML = `
                            <div class="folder-header card ${borderColorClass}" style="border-width: 2px; border-style: solid;">
                                <div class="card-header bg-light">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <i class="${folderIcon} fa-2x ${folderColorClass} me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1 ${folderColorClass}" id="folder-title-${folderId}">
                                                        ${isIdentityFolder ? 'مجلد شخص' : folderName}
                                                    </h5>
                                                    ${isIdentityFolder ? `
                                                        <div class="person-info-card mb-2">
                                                            <div id="person-name-${folderId}" class="person-name-display">
                                                                <div class="loading-state">
                                                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                                                    <span>جاري البحث عن المعلومات...</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ` : ''}
                                                    <small class="text-muted">
                                                        ${isIdentityFolder ? 'مجلد شخص - ' : 'مجلد عام - '}
                                                        <span id="folder-stats-${folderId}">1 ملف</span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex flex-column align-items-end gap-2">
                                                <!-- إحصائيات سريعة للمجلد -->
                                                <div class="folder-quick-stats d-flex flex-wrap gap-1 justify-content-end">
                                                    <span class="badge bg-success small" id="folder-completed-${folderId}">0 مكتمل</span>
                                                    <span class="badge bg-warning small" id="folder-processing-${folderId}">0 قيد المعالجة</span>
                                                    <span class="badge bg-danger small" id="folder-failed-${folderId}">0 فاشل</span>
                                                    <span class="badge bg-info small" id="folder-duplicate-${folderId}">0 مكرر</span>
                                                </div>
                                                <!-- أزرار التحكم في المجلد -->
                                                <div class="folder-controls">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="app.toggleFolderCollapse('${folderId}')" id="toggle-btn-${folderId}">
                                                        <i class="fas fa-chevron-up" id="toggle-icon-${folderId}"></i>
                                                    </button>
                                                    <div class="dropdown d-inline">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="folderActions${folderId}" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="app.downloadFolderFiles('${folderId}')">
                                                                <i class="fas fa-download me-2"></i> تحميل جميع الملفات
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="app.selectAllFolderFiles('${folderId}')">
                                                                <i class="fas fa-check-square me-2"></i> تحديد الكل
                                                            </a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="app.deleteFolderFiles('${folderId}')">
                                                                <i class="fas fa-trash me-2"></i> حذف جميع الملفات
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body collapse show" id="folder-content-${folderId}">
                                    <div class="folder-files-grid row" id="folder-files-${folderId}">
                                        <!-- سيتم إضافة الملفات هنا -->
                                    </div>
                                </div>
                            </div>
                        `;

                        return folderSection;
                    }

                    // دالة محسنة لجلب وعرض معلومات الشخص مع إعادة المحاولة
                    async fetchPersonName(identityNumber, folderId, retryCount = 0) {
                        const maxRetries = 3;
                        try {
                            const response = await fetch(`/api/person-name/${identityNumber}`);

                            // معالجة خطأ 429 - إعادة المحاولة بتأخير تصاعدي
                            if (response.status === 429 && retryCount < maxRetries) {
                                const delay = Math.pow(2, retryCount) * 2000;
                                console.warn(`⏳ طلبات كثيرة (429) لجلب اسم ${identityNumber} - إعادة المحاولة بعد ${delay/1000} ثانية...`);
                                await new Promise(resolve => setTimeout(resolve, delay));
                                return this.fetchPersonName(identityNumber, folderId, retryCount + 1);
                            }

                            const data = await response.json();

                            const nameElement = document.getElementById(`person-name-${folderId}`);
                            const titleElement = document.getElementById(`folder-title-${folderId}`);

                            if (nameElement) {
                                if (data.success) {
                                    // تحديث عنوان المجلد بالاسم الكامل
                                    if (titleElement) {
                                        titleElement.innerHTML = `<i class="fas fa-user-check text-success me-2"></i>${data.full_name}`;
                                        titleElement.className = 'mb-1 text-success fw-bold';
                                    }

                                    // عرض ناجح مع معلومات كاملة
                                    nameElement.innerHTML = `
                                        <div class="success-state">
                                            <div class="person-details">
                                                <div class="id-number-display mb-2">
                                                    <i class="fas fa-id-card me-2 text-primary"></i>
                                                    <strong class="h6 text-primary">${identityNumber}</strong>
                                                </div>
                                                <small class="text-muted d-flex align-items-center">
                                                    ${data.city && data.city !== 'غير محدد' ?
                                                        `<i class="fas fa-map-marker-alt me-1 text-info"></i>المدينة: <span class="fw-bold text-dark">${data.city}</span>` :
                                                        `<i class="fas fa-question-circle me-1 text-secondary"></i><span class="text-secondary">المدينة غير محددة</span>`
                                                    }
                                                </small>
                                            </div>
                                        </div>
                                    `;
                                    nameElement.className = 'person-name-display success';
                                } else {
                                    // تحديث العنوان للحالة غير الموجودة
                                    if (titleElement) {
                                        titleElement.innerHTML = `<i class="fas fa-user-times text-warning me-2"></i>رقم هوية: ${identityNumber}`;
                                        titleElement.className = 'mb-1 text-warning';
                                    }

                                    // حالة عدم وجود البيانات
                                    nameElement.innerHTML = `
                                        <div class="not-found-state">
                                            <i class="fas fa-user-times text-warning me-2"></i>
                                            <span>لم يتم العثور على معلومات الشخص</span>
                                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="app.fetchPersonName('${identityNumber}', '${folderId}')" title="إعادة المحاولة">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    `;
                                    nameElement.className = 'person-name-display not-found';
                                }
                            }
                        } catch (error) {
                            // حالة خطأ في الشبكة
                            const nameElement = document.getElementById(`person-name-${folderId}`);
                            const titleElement = document.getElementById(`folder-title-${folderId}`);

                            if (titleElement) {
                                titleElement.innerHTML = `<i class="fas fa-exclamation-triangle text-danger me-2"></i>رقم هوية: ${identityNumber}`;
                                titleElement.className = 'mb-1 text-danger';
                            }

                            if (nameElement) {
                                nameElement.innerHTML = `
                                    <div class="error-state">
                                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                        <span>خطأ في تحميل المعلومات</span>
                                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="app.fetchPersonName('${identityNumber}', '${folderId}')" title="إعادة المحاولة">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    </div>
                                `;
                                nameElement.className = 'person-name-display error';
                            }
                        }
                    }

                    /**
                     * دالة لإظهار تفاصيل الشخص في مودال
                     */
                    async showPersonDetails(identityNumber, folderId) {
                        try {
                            // جلب البيانات من API
                            const response = await fetch(`/api/person-name/${identityNumber}`);
                            const data = await response.json();

                            if (data.success) {
                                // إنشاء المودال
                                const modal = document.createElement('div');
                                modal.className = 'modal fade';
                                modal.id = 'personDetailsModal';
                                modal.innerHTML = `
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    تفاصيل الشخص
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="card h-100">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>المعلومات الشخصية</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-primary">الاسم الكامل:</label>
                                                                    <p class="mb-0">${data.full_name}</p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-primary">رقم الهوية:</label>
                                                                    <p class="mb-0">${identityNumber}</p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-primary">الجنس:</label>
                                                                    <p class="mb-0">
                                                                        ${data.gender == '1' ? '<i class="fas fa-mars text-info me-1"></i>ذكر' :
                                                                          data.gender == '2' ? '<i class="fas fa-venus text-pink me-1"></i>أنثى' : 'غير محدد'}
                                                                    </p>
                                                                </div>
                                                                ${data.city ? `
                                                                    <div class="mb-3">
                                                                        <label class="fw-bold text-primary">المدينة:</label>
                                                                        <p class="mb-0"><i class="fas fa-map-marker-alt text-success me-1"></i>${data.city}</p>
                                                                    </div>
                                                                ` : ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card h-100">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fas fa-users me-2"></i>تفاصيل الاسم</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-secondary">الاسم الأول:</label>
                                                                    <p class="mb-0">${data.first_name || 'غير محدد'}</p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-secondary">اسم الأب:</label>
                                                                    <p class="mb-0">${data.father_name || 'غير محدد'}</p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-secondary">اسم الجد:</label>
                                                                    <p class="mb-0">${data.grand_father_name || 'غير محدد'}</p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="fw-bold text-secondary">اسم العائلة:</label>
                                                                    <p class="mb-0">${data.family_name || 'غير محدد'}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i>إغلاق
                                                </button>
                                                <button type="button" class="btn btn-primary" onclick="app.fetchPersonName('${identityNumber}', '${folderId}')">
                                                    <i class="fas fa-sync-alt me-1"></i>تحديث المعلومات
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;

                                // إزالة المودال السابق إن وجد
                                const existingModal = document.getElementById('personDetailsModal');
                                if (existingModal) {
                                    existingModal.remove();
                                }

                                // إضافة المودال الجديد
                                document.body.appendChild(modal);

                                // إظهار المودال
                                const bsModal = new bootstrap.Modal(modal);
                                bsModal.show();

                                // إزالة المودال عند الإغلاق
                                modal.addEventListener('hidden.bs.modal', () => {
                                    modal.remove();
                                });
                            } else {
                                alert('لم يتم العثور على معلومات هذا الشخص');
                            }
                        } catch (error) {
                            console.error('خطأ في جلب تفاصيل الشخص:', error);
                            alert('حدث خطأ في جلب تفاصيل الشخص');
                        }
                    }

                    /**
                     * عرض مودال مقارنة الملف المكرر (الجديد والقديم)
                     */
                    async showDuplicateComparisonModal(fileId) {
                        try {
                            console.log('🔍 عرض مقارنة الملف المكرر، File ID:', fileId);

                            // الحصول على بيانات الملف الجديد من الذاكرة
                            const newFileData = this.files.get(fileId);

                            console.log('📦 بيانات الملف المسترجعة:', newFileData);
                            console.log('📊 حالة الملف:', newFileData?.status);
                            console.log('🔗 معلومات التكرار:', newFileData?.duplicateInfo);

                            if (!newFileData) {
                                console.error('❌ لم يتم العثور على بيانات الملف في files Map');
                                console.log('📋 محتويات files Map:', Array.from(this.files.entries()));
                                return;
                            }

                            // التحقق من وجود معلومات الملف القديم المخزنة
                            if (!newFileData.duplicateInfo || !newFileData.duplicateInfo.existing_file_name) {
                                console.error('❌ لا توجد معلومات عن الملف المكرر');
                                console.log('🔍 التفاصيل المتاحة:', {
                                    hasDuplicateInfo: !!newFileData.duplicateInfo,
                                    duplicateInfo: newFileData.duplicateInfo,
                                    fileName: newFileData.file?.name,
                                    status: newFileData.status
                                });
                                Swal.fire({
                                    icon: 'error',
                                    title: 'خطأ',
                                    text: 'لا توجد معلومات كافية عن الملف المكرر',
                                    confirmButtonText: 'حسناً'
                                });
                                return;
                            }

                            const oldFile = newFileData.duplicateInfo;
                            console.log('📋 معلومات الملف المكرر:', oldFile);

                            // إنشاء URL للمعاينة المحلية للملف الجديد
                            const newFileUrl = URL.createObjectURL(newFileData.file);
                            const isImage = newFileData.type === 'image';
                            const isPdf = newFileData.type === 'pdf';

                            // إعداد محتوى الملف الجديد
                            let newFilePreviewHTML = '';
                            if (isImage) {
                                newFilePreviewHTML = `
                                    <img src="${newFileUrl}" alt="${newFileData.file.name}"
                                         class="img-fluid" style="max-height: 400px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                `;
                            } else if (isPdf) {
                                newFilePreviewHTML = `
                                    <div class="text-center p-4 bg-light" style="border-radius: 8px;">
                                        <i class="fas fa-file-pdf fa-5x text-danger mb-3"></i>
                                        <p class="mb-0">ملف PDF - لا يمكن معاينته قبل الرفع</p>
                                    </div>
                                `;
                            } else {
                                newFilePreviewHTML = `
                                    <div class="text-center p-4 bg-light" style="border-radius: 8px;">
                                        <i class="fas fa-file fa-5x text-secondary mb-3"></i>
                                        <p class="mb-0">لا يمكن معاينة هذا النوع من الملفات</p>
                                    </div>
                                `;
                            }

                            const newFileInfoHTML = `
                                <p><strong><i class="fas fa-tag me-2 text-primary"></i>اسم الملف:</strong> ${newFileData.file.name}</p>
                                <p><strong><i class="fas fa-hdd me-2 text-info"></i>الحجم:</strong> ${this.formatFileSize(newFileData.file.size)}</p>
                                <p><strong><i class="fas fa-file-alt me-2 text-success"></i>النوع:</strong> ${newFileData.type}</p>
                                <p><strong><i class="fas fa-clock me-2 text-warning"></i>آخر تعديل:</strong> ${new Date(newFileData.file.lastModified).toLocaleString('ar-EG')}</p>
                            `;

                            // إعداد محتوى الملف القديم
                            let oldFilePreviewHTML = '';

                            // استخدام الرابط الآمن لعرض الملفات
                            const oldFileUrl = oldFile.existing_file_name
                                ? `/admin/file/show/${encodeURIComponent(oldFile.existing_file_name)}`
                                : null;

                            console.log('🖼️ URL الملف القديم (آمن):', oldFileUrl);
                            console.log('📁 اسم الملف:', oldFile.existing_file_name);

                            const oldFileName = oldFile.existing_file_name || 'غير متوفر';
                            const oldFileExtension = oldFileName.split('.').pop().toLowerCase();
                            const oldFileIsImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(oldFileExtension);
                            const oldFileIsPdf = oldFileExtension === 'pdf';

                            if (oldFileUrl && oldFileIsImage) {
                                oldFilePreviewHTML = `
                                    <img src="${oldFileUrl}" alt="${oldFileName}"
                                         class="img-fluid" style="max-height: 400px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"
                                         onerror="this.parentElement.innerHTML='<div class=\\'text-center p-4 bg-danger text-white\\' style=\\'border-radius: 8px;\\'><i class=\\'fas fa-exclamation-triangle fa-5x mb-3\\'></i><p class=\\'mb-0\\'>تعذر تحميل الصورة</p><small class=\\'d-block mt-2 text-white-50\\'>المسار: ${oldFileUrl}</small></div>'">
                                `;
                            } else if (oldFileUrl && oldFileIsPdf) {
                                oldFilePreviewHTML = `
                                    <div class="text-center p-4 bg-light" style="border-radius: 8px;">
                                        <i class="fas fa-file-pdf fa-5x text-danger mb-3"></i>
                                        <a href="${oldFileUrl}" target="_blank" class="btn btn-primary mt-2">
                                            <i class="fas fa-external-link-alt me-1"></i>فتح ملف PDF
                                        </a>
                                    </div>
                                `;
                            } else if (oldFileUrl) {
                                oldFilePreviewHTML = `
                                    <div class="text-center p-4 bg-light" style="border-radius: 8px;">
                                        <i class="fas fa-file fa-5x text-secondary mb-3"></i>
                                        <a href="${oldFileUrl}" target="_blank" class="btn btn-primary mt-2">
                                            <i class="fas fa-download me-1"></i>تحميل الملف
                                        </a>
                                    </div>
                                `;
                            } else {
                                oldFilePreviewHTML = `
                                    <div class="text-center p-4 bg-warning text-dark" style="border-radius: 8px;">
                                        <i class="fas fa-exclamation-triangle fa-5x mb-3"></i>
                                        <p class="mb-0">لا يمكن معاينة الملف - المسار غير متوفر</p>
                                    </div>
                                `;
                            }

                            const oldFileInfoHTML = `
                                <p><strong><i class="fas fa-tag me-2 text-primary"></i>اسم الملف:</strong> ${oldFileName}</p>
                                <p><strong><i class="fas fa-fingerprint me-2 text-danger"></i>السبب:</strong> ${oldFile.reason || 'ملف مكرر'}</p>
                                <p><strong><i class="fas fa-info-circle me-2 text-info"></i>الرسالة:</strong> ${oldFile.message || 'تم اكتشاف ملف مطابق في النظام'}</p>
                            `;

                            // تحديث محتوى المودال
                            document.getElementById('newFilePreview').innerHTML = newFilePreviewHTML;
                            document.getElementById('newFileInfo').innerHTML = newFileInfoHTML;
                            document.getElementById('oldFilePreview').innerHTML = oldFilePreviewHTML;
                            document.getElementById('oldFileInfo').innerHTML = oldFileInfoHTML;

                            // عرض المودال
                            const modalElement = document.getElementById('duplicateComparisonModal');
                            const modal = new bootstrap.Modal(modalElement);
                            modal.show();

                            // إضافة معالجات الأزرار
                            const ignoreBtn = document.getElementById('ignoreDuplicateBtn');
                            const replaceBtn = document.getElementById('replaceDuplicateBtn');

                            // زر تجاهل - حذف الملف من القائمة
                            ignoreBtn.onclick = () => {
                                this.handleIgnoreDuplicate(fileId);
                                modal.hide();
                            };

                            // زر استبدال - استبدال الملف القديم بالجديد
                            replaceBtn.onclick = async () => {
                                await this.handleReplaceDuplicate(fileId, oldFile);
                                modal.hide();
                            };

                            // تنظيف URL المؤقت عند إغلاق المودال
                            modalElement.addEventListener('hidden.bs.modal', () => {
                                URL.revokeObjectURL(newFileUrl);
                            }, { once: true });

                        } catch (error) {
                            console.error('خطأ في عرض مقارنة الملف المكرر:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: 'حدث خطأ أثناء جلب معلومات الملف القديم',
                                confirmButtonText: 'حسناً'
                            });
                        }
                    }

                    /**
                     * معالجة تجاهل الملف المكرر - حذفه من القائمة
                     */
                    handleIgnoreDuplicate(fileId) {
                        console.log('🚫 تجاهل الملف المكرر:', fileId);

                        // حذف الملف من الذاكرة
                        this.files.delete(fileId);

                        // حذف عنصر الملف من الواجهة
                        const fileElement = document.querySelector(`[data-file-id="${fileId}"]`);
                        if (fileElement) {
                            fileElement.remove();
                        }

                        // تحديث العدادات
                        this.updateFileCounts();

                        Swal.fire({
                            icon: 'success',
                            title: 'تم التجاهل',
                            text: 'تم تجاهل الملف المكرر وحذفه من القائمة',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }

                    /**
                     * معالجة استبدال الملف القديم بالجديد
                     */
                    async handleReplaceDuplicate(fileId, oldFile) {
                        console.log('🔄 استبدال الملف القديم بالجديد:', fileId);

                        try {
                            const fileData = this.files.get(fileId);
                            if (!fileData) {
                                throw new Error('لم يتم العثور على بيانات الملف');
                            }

                            // عرض رسالة تأكيد
                            const confirm = await Swal.fire({
                                icon: 'warning',
                                title: 'تأكيد الاستبدال',
                                html: `
                                    <p>هل أنت متأكد من استبدال الملف القديم؟</p>
                                    <p class="text-danger"><strong>هذا الإجراء لا يمكن التراجع عنه!</strong></p>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'نعم، استبدل',
                                cancelButtonText: 'إلغاء',
                                confirmButtonColor: '#28a745',
                                cancelButtonColor: '#6c757d'
                            });

                            if (!confirm.isConfirmed) {
                                return;
                            }

                            // إظهار رسالة التحميل
                            Swal.fire({
                                title: 'جاري الاستبدال...',
                                text: 'يرجى الانتظار',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            // إعداد FormData للرفع
                            const formData = new FormData();
                            formData.append('file', fileData.file);
                            formData.append('old_file_name', oldFile.existing_file_name);
                            formData.append('old_file_path', oldFile.existing_file_path);
                            formData.append('replace_mode', 'true');

                            // إرسال الطلب للخادم
                            const response = await fetch('/api/replace-duplicate-file', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            });

                            const result = await response.json();

                            if (result.success) {
                                // تحديث حالة الملف إلى مكتمل
                                fileData.status = 'completed';
                                this.updateFileStatus(fileId, 'completed');

                                // حذف الملف من قائمة المكررات
                                this.files.delete(fileId);

                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الاستبدال بنجاح!',
                                    text: 'تم استبدال الملف القديم بالملف الجديد',
                                    timer: 3000,
                                    showConfirmButton: false
                                });

                                // تحديث العدادات
                                this.updateFileCounts();
                            } else {
                                throw new Error(result.message || 'فشل الاستبدال');
                            }

                        } catch (error) {
                            console.error('خطأ في استبدال الملف:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'فشل الاستبدال',
                                text: error.message || 'حدث خطأ أثناء استبدال الملف',
                                confirmButtonText: 'حسناً'
                            });
                        }
                    }

                    /**
                     * إنشاء عنصر ملف منفرد مع معاينة فورية للصور
                     */
                    createFileElement(fileData) {
                        const fileElement = document.createElement('div');
                        fileElement.className = 'col-lg-3 col-md-4 col-sm-6 mb-3';
                        fileElement.setAttribute('data-file-id', fileData.id);

                        // تحديد نوع الملف وأيقونته
                        let iconClass = 'fas fa-file';
                        let iconColor = 'text-secondary';
                        const isImage = fileData.type === 'image';

                        if (isImage) {
                            iconClass = 'fas fa-image';
                            iconColor = 'text-success';
                        } else if (fileData.type === 'pdf') {
                            iconClass = 'fas fa-file-pdf';
                            iconColor = 'text-danger';
                        } else if (fileData.type === 'excel') {
                            iconClass = 'fas fa-file-excel';
                            iconColor = 'text-success';
                        } else if (fileData.type === 'archive') {
                            iconClass = 'fas fa-file-archive';
                            iconColor = 'text-warning';
                        }

                        // إنشاء preview URL للصور قبل الرفع
                        let previewContent = '';
                        if (isImage) {
                            const previewUrl = URL.createObjectURL(fileData.file);
                            previewContent = `
                                <img src="${previewUrl}" alt="${fileData.file.name}"
                                     class="img-fluid w-100 h-100"
                                     style="object-fit: cover; cursor: pointer;"
                                     onclick="previewLocalFile('${fileData.id}', '${fileData.file.name}', true)">
                            `;
                        } else {
                            previewContent = `
                                <div class="d-flex align-items-center justify-content-center bg-light" style="height: 120px;">
                                    <i class="${iconClass} fa-2x ${iconColor}"></i>
                                </div>
                            `;
                        }

                        fileElement.innerHTML = `
                            <div class="file-preview card h-100" id="preview_${fileData.id}">
                                <div class="position-relative">
                                    ${previewContent}
                                    <div class="processing-overlay position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-dark bg-opacity-50"
                                         id="overlay_${fileData.id}" style="display: none !important;">
                                        <div class="spinner-border text-light" role="status">
                                            <span class="visually-hidden">جاري المعالجة...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-2">
                                    <h6 class="card-title mb-1 small" id="fileName_${fileData.id}" title="${fileData.file.name}">
                                        ${fileData.file.name.length > 18 ? fileData.file.name.substring(0, 18) + '...' : fileData.file.name}
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-secondary small" id="fileType_${fileData.id}">${fileData.type}</span>
                                        <span class="badge bg-info text-dark small" id="fileStatus_${fileData.id}">معلق</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            ${this.formatFileSize(fileData.file.size)}
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            ${isImage ?
                                                `<button class="btn btn-outline-primary btn-sm" onclick="previewLocalFile('${fileData.id}', '${fileData.file.name}', true)" title="معاينة">
                                                    <i class="fas fa-eye"></i>
                                                 </button>` :
                                                `<button class="btn btn-outline-info btn-sm" onclick="openLocalFile('${fileData.id}')" title="عرض">
                                                    <i class="fas fa-external-link-alt"></i>
                                                 </button>`
                                            }
                                            <button class="btn btn-outline-danger btn-sm" onclick="removeFileFromQueue('${fileData.id}')" title="إزالة">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer p-1">
                                    <div class="progress" style="height: 4px;" id="fileProgress_${fileData.id}">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        `;

                        return fileElement;
                    }

                    /**
                     * تحديث إحصائيات المجلد
                     */
                    updateFolderStats(folderId) {
                        const folderSection = document.getElementById(`folder-section-${folderId}`);
                        if (!folderSection) return;

                        const folderFiles = folderSection.querySelectorAll('[data-file-id]');
                        const totalFiles = folderFiles.length;

                        // حساب الإحصائيات من البيانات الفعلية
                        let completed = 0, processing = 0, failed = 0, duplicate = 0, pending = 0;

                        folderFiles.forEach(fileEl => {
                            const fileId = fileEl.getAttribute('data-file-id');
                            const fileData = this.files.get(fileId);
                            if (fileData) {
                                switch(fileData.status) {
                                    case 'completed': completed++; break;
                                    case 'processing': processing++; break;
                                    case 'failed': failed++; break;
                                    case 'duplicate': duplicate++; break;
                                    default: pending++; break;
                                }
                            }
                        });

                        // تحديث العدادات في الواجهة
                        const statsElement = document.getElementById(`folder-stats-${folderId}`);
                        if (statsElement) {
                            statsElement.textContent = `${totalFiles} ملف`;
                        }

                        // تحديث الشارات الملونة
                        this.updateFolderBadge(`folder-completed-${folderId}`, completed, 'مكتمل');
                        this.updateFolderBadge(`folder-processing-${folderId}`, processing, 'قيد المعالجة');
                        this.updateFolderBadge(`folder-failed-${folderId}`, failed, 'فاشل');
                        this.updateFolderBadge(`folder-duplicate-${folderId}`, duplicate, 'مكرر');
                    }

                    /**
                     * تحديث شارة إحصائيات المجلد
                     */
                    updateFolderBadge(elementId, count, label) {
                        const element = document.getElementById(elementId);
                        if (element) {
                            element.textContent = `${count} ${label}`;
                            element.style.display = count > 0 ? 'inline-block' : 'none';
                        }
                    }

                    /**
                     * تبديل طي/فتح المجلد
                     */
                    toggleFolderCollapse(folderId) {
                        const content = document.getElementById(`folder-content-${folderId}`);
                        const icon = document.getElementById(`toggle-icon-${folderId}`);

                        if (content && icon) {
                            if (content.classList.contains('show')) {
                                content.classList.remove('show');
                                icon.className = 'fas fa-chevron-down';
                            } else {
                                content.classList.add('show');
                                icon.className = 'fas fa-chevron-up';
                            }
                        }
                    }

                    updateFileCounts() {
                        // تحديث عدادات الواجهة
                        const totalFiles = this.files.size;
                        const totalFilesElement = document.getElementById('totalFiles');
                        if (totalFilesElement) {
                            totalFilesElement.textContent = totalFiles;
                        }

                        // تحديث إحصائيات بسيطة
                        const completedFiles = Array.from(this.files.values()).filter(f => f.status === 'completed').length;
                        const processingFiles = Array.from(this.files.values()).filter(f => f.status === 'processing').length;
                        const failedFiles = Array.from(this.files.values()).filter(f => f.status === 'failed').length;
                        const duplicateFiles = Array.from(this.files.values()).filter(f => f.status === 'duplicate').length;
                        const pendingFiles = Array.from(this.files.values()).filter(f => f.status === 'pending').length;

                        // سجل لمساعدة في التشخيص
                        console.log('📊 تحديث العدادات - الإحصائيات الحالية:', {
                            total: totalFiles,
                            completed: completedFiles,
                            processing: processingFiles,
                            failed: failedFiles,
                            duplicate: duplicateFiles,
                            pending: pendingFiles
                        });

                        const completedFilesElement = document.getElementById('completedFiles');
                        const processingFilesElement = document.getElementById('processingFiles');
                        const failedFilesElement = document.getElementById('failedFiles');
                        const duplicateFilesElement = document.getElementById('duplicateFiles');
                        const pendingFilesElement = document.getElementById('pendingFiles');

                        if (completedFilesElement) completedFilesElement.textContent = completedFiles;
                        if (processingFilesElement) processingFilesElement.textContent = processingFiles;
                        if (failedFilesElement) failedFilesElement.textContent = failedFiles;
                        if (duplicateFilesElement) {
                            duplicateFilesElement.textContent = duplicateFiles;
                            console.log(`🔄 تم تحديث عداد الملفات المكررة في الواجهة: ${duplicateFiles}`);
                        }
                        if (pendingFilesElement) pendingFilesElement.textContent = pendingFiles;

                        // تحديث إحصائيات جميع المجلدات
                        this.updateAllFoldersStats();

                        // إظهار/إخفاء أزرار الرفع بناءً على وجود ملفات
                        const startUploadBtn = document.getElementById('startUploadBtn');
                        const startUploadBtnMain = document.getElementById('startUploadBtnMain');
                        const uploadProgressSection = document.getElementById('uploadProgressSection');

                        if (totalFiles > 0) {
                            // إظهار زر الرفع في جميع الأماكن
                            if (startUploadBtn) {
                                startUploadBtn.style.display = 'inline-block';
                            }
                            if (startUploadBtnMain) {
                                startUploadBtnMain.style.display = 'inline-block';
                            }

                            // إظهار قسم التقدم مع فحص الوجود
                            if (uploadProgressSection) {
                                uploadProgressSection.style.display = 'block';
                            }

                            // تحديث شريط التقدم مع فحص الوجود
                            const overallProgress = document.getElementById('overallProgress');
                            if (overallProgress) {
                                const progress = totalFiles > 0 ? (completedFiles / totalFiles) * 100 : 0;
                                overallProgress.style.width = `${progress}%`;
                                overallProgress.setAttribute('aria-valuenow', progress);
                            }
                        } else {
                            // إخفاء زر الرفع في جميع الأماكن
                            if (startUploadBtn) {
                                startUploadBtn.style.display = 'none';
                            }
                            if (startUploadBtnMain) {
                                startUploadBtnMain.style.display = 'none';
                            }
                            // إخفاء قسم التقدم
                            if (uploadProgressSection) {
                                uploadProgressSection.style.display = 'none';
                            }
                        }
                    }

                    /**
                     * تحديث إحصائيات جميع المجلدات
                     */
                    updateAllFoldersStats() {
                        const folderSections = document.querySelectorAll('[id^="folder-section-"]');
                        folderSections.forEach(section => {
                            const folderId = section.id.replace('folder-section-', '');
                            this.updateFolderStats(folderId);
                        });
                    }

                    async uploadFolderFile(files, uploadType) {
                        console.log(`🚀 بدء رفع ${uploadType}:`, {
                            filesCount: files.length,
                            timestamp: new Date().toISOString()
                        });

                        // تحديث حالة جميع الملفات إلى "قيد المعالجة"
                        this.updateAllFilesToProcessing();

                        const formData = new FormData();

                        // إضافة جميع الملفات
                        files.forEach((file, index) => {
                            formData.append(`files[${index}]`, file);
                            formData.append(`paths[${index}]`, file.webkitRelativePath || file.name);
                        });

                        // إضافة معلومات إضافية
                        formData.append('upload_type', uploadType);
                        // formData.append('person_id', document.getElementById('personId').value || '');
                        formData.append('compress_images', document.getElementById('compressImages')?.checked ?? false);
                        formData.append('auto_organize', document.getElementById('autoOrganize')?.checked ?? false);
                        formData.append('cloud_sync', document.getElementById('cloudSync')?.checked ?? false);
                        formData.append('enable_duplicate_detection', this.duplicateDetectionEnabled);
                        formData.append('enable_duplicate_detection', this.duplicateDetectionEnabled);

                        // إضافة ملف Excel إذا كان موجود
                        const excelFile = document.getElementById('excelFileInput')?.files?.[0];
                        if (excelFile) {
                            formData.append('excel_file', excelFile);
                            formData.append('enable_excel_import', document.getElementById('enableExcelImport').checked);
                            formData.append('target_table', document.getElementById('targetTable').value);

                            console.log('📊 ملف Excel مرفق:', {
                                name: excelFile.name,
                                size: excelFile.size,
                                importEnabled: document.getElementById('enableExcelImport').checked
                            });
                        }

                        try {
                            console.log('📤 إرسال البيانات إلى الخادم...');

                            // فحص حجم الملفات وعددها للتبديل التلقائي للنظام المتعدد
                            const totalSize = files.reduce((sum, file) => sum + file.size, 0);
                            const totalSizeMB = totalSize / (1024 * 1024);
                            const shouldUseBatchUpload = files.length > 20 || totalSizeMB > 50;

                            if (shouldUseBatchUpload) {
                                console.log('🔄 التبديل للنظام المتعدد:', {
                                    filesCount: files.length,
                                    totalSizeMB: totalSizeMB.toFixed(2),
                                    reason: files.length > 20 ? 'عدد الملفات كبير' : 'حجم الملفات كبير'
                                });

                                return await this.uploadFolderFileWithBatches(files, uploadType);
                            }

                            const response = await fetch('/admin/file/process-bulk-folder-upload', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                        'content'),
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: formData
                            });

                            const result = await response.json();

                            if (response.ok) {
                                console.log('✅ نجح رفع المجلد مع كشف التكرار:', result);

                                // فحص وعرض الملفات المكررة الجديدة
                                if (result.duplicates_info && result.duplicates_info.total_duplicates > 0) {
                                    console.log('🔍 تم اكتشاف ملفات مكررة جديدة:', result.duplicates_info);

                                    // إذا كان كشف التكرار معطلاً، قم بإخفاء الملفات المكررة
                                    if (!this.duplicateDetectionEnabled) {
                                        console.log('🚫 كشف التكرار معطّل - سيتم إخفاء جميع الملفات المكررة');

                                        // إذا كانت هناك معلومات تفصيلية، استخدم updateSpecificDuplicateFiles
                                        if (result.results && result.results.duplicate_files) {
                                            this.updateSpecificDuplicateFiles(result.results.duplicate_files);
                                        }
                                    } else {
                                        // إذا كان كشف التكرار مفعلاً، عرض المعلومات والأزرار
                                        console.log('⚠️ كشف التكرار مفعّل - سيتم عرض معلومات الملفات المكررة');

                                        // حفظ session_id للملفات المكررة
                                        window.CURRENT_DUPLICATE_SESSION_ID = result.session_id;
                                        sessionStorage.setItem('duplicate_files_session_id', result.session_id);

                                        // تحديث زر عرض الملفات المكررة
                                        this.updateDuplicateFilesButton(result.duplicates_info);

                                        // عرض SweetAlert للملفات المكررة
                                        this.showDuplicateFilesAlert(result.duplicates_info, result.session_id);

                                        // تحديث معلومات الملفات المكررة في الواجهة
                                        if (result.results && result.results.duplicate_files) {
                                            this.updateSpecificDuplicateFiles(result.results.duplicate_files);
                                        }
                                    }
                                }

                                // تسجيل ملخص العملية فقط في console
                                if (result.summary) {
                                    console.log('📊 ملخص العملية:', result.summary);
                                }

                                // تحديث الملفات المعروضة في المجلدات تلقائياً
                                refreshAllFolderFiles();

                                // عرض تحليل المجلدات
                                if (result.folder_analysis && result.folder_analysis.validated_folders) {
                                    console.log('📁 المجلدات المعتمدة:', result.folder_analysis.validated_folders);
                                    Object.entries(result.folder_analysis.validated_folders).forEach(([folderName, data]) => {
                                        console.log(
                                            `✅ ${folderName} → file_id: ${data.file_id_number} (${data.matched_by})`
                                        );
                                    });
                                }

                                if (result.folder_analysis && result.folder_analysis.rejected_folders && result.folder_analysis
                                    .rejected_folders.length > 0) {
                                    console.log('❌ مجلدات مرفوضة:', result.folder_analysis.rejected_folders);
                                    const rejectedCount = this.markRejectedFolderFilesAsFailed(result.folder_analysis.rejected_folders);
                                    const rejectedList = result.folder_analysis.rejected_folders.join('، ');
                                    this.showAlert(
                                        `⚠️ لم يتم رفع ${rejectedCount} ملف بسبب عدم وجود معلومات الشخص في النظام.\nالمجلدات المرفوضة: ${rejectedList}`,
                                        'warning'
                                    );
                                }

                                // عرض تفاصيل كشف التكرار
                                if (result.duplicate_detection_results) {
                                    console.log('� نتائج كشف التكرار:', result.duplicate_detection_results);

                                    if (result.duplicate_detection_results.folder_analysis) {
                                        console.log('📂 تحليل المجلدات للتكرار:', result.duplicate_detection_results
                                            .folder_analysis);
                                    }
                                }

                                // تحديث الواجهة
                                this.updateFileCounts();
                                this.loadAnalytics();

                                return result;
                            } else {
                                // معالجة أخطاء التحقق من صحة المجلدات
                                if (response.status === 422) {
                                    let errorMessage = result.message;

                                    if (result.rejected_folders && result.rejected_folders.length > 0) {
                                        console.error('❌ مجلدات هوية مرفوضة:', result.rejected_folders);
                                        errorMessage += `: ${result.rejected_folders.join(', ')}`;
                                    }

                                    if (result.ignored_parent_folders && result.ignored_parent_folders.length > 0) {
                                        console.log('ℹ️ مجلدات أب تم تجاهلها (عادي):', result.ignored_parent_folders);
                                        errorMessage +=
                                            `\nملاحظة: تم تجاهل المجلدات الأب التالية بشكل طبيعي: ${result.ignored_parent_folders.join(', ')}`;
                                    }

                                    this.showAlert(errorMessage, 'danger');
                                    // تحديث حالة الملفات إلى "فاشلة" في حالة الخطأ
                                    this.updateAllFilesToFailed();
                                } else {
                                    throw new Error(result.message || 'فشل في رفع المجلد');
                                }
                            }
                        } catch (error) {
                            console.error('❌ خطأ في رفع المجلد:', error);
                            this.showAlert(`فشل في رفع المجلد: ${error.message}`, 'danger');
                            // تحديث حالة الملفات إلى "فاشلة" في حالة الخطأ
                            this.updateAllFilesToFailed();
                            throw error;
                        }
                    }

                    async startUploads() {
                        console.log('🚀 تم استدعاء startUploads()...');

                        // إظهار وإعداد قسم التقدم أولاً
                        this.setupUploadProgress();

                        // التحقق من وجود ملفات معالجة للرفع (من المجلدات)
                        if (this.processedFiles && this.processedFiles.length > 0) {
                            console.log('🚀 بدء رفع الملفات المعالجة من المجلد...');

                            try {
                                const result = await this.uploadFolderFile(this.processedFiles, this.currentUploadType);

                                // إذا تم إيقاف الرفع مؤقتاً (انقطاع إنترنت) - لا تمسح الملفات!
                                if (result && result.paused) {
                                    console.log('⏸️ الرفع متوقف مؤقتاً - الملفات محفوظة للاستئناف', {
                                        totalSuccessful: result.totalSuccessful,
                                        totalDuplicates: result.totalDuplicates,
                                        totalErrors: result.totalErrors
                                    });
                                    // لا نمسح processedFiles ولا نخفي شريط التقدم
                                    return;
                                }

                                console.log('✅ اكتملت عملية الرفع', result);

                                // تحديث الملفات المعروضة في المجلدات تلقائياً
                                refreshAllFolderFiles();

                                // مسح الملفات المعالجة بعد الرفع الكامل فقط
                                this.processedFiles = null;
                                this.currentUploadType = null;
                            } catch (error) {
                                console.error('❌ فشلت عملية الرفع:', error);
                            } finally {
                                // إخفاء شريط التقدم فقط إذا لم يكن الرفع متوقفاً مؤقتاً
                                if (!this._uploadSession.isPaused) {
                                    setTimeout(() => {
                                        const progressSection = document.getElementById('uploadProgressSection');
                                        if (progressSection) {
                                            progressSection.style.transition = 'opacity 0.5s ease';
                                            progressSection.style.opacity = '0';

                                            setTimeout(() => {
                                                progressSection.style.display = 'none';
                                                progressSection.style.opacity = '1';
                                            }, 500);
                                        }
                                    }, 1000);
                                }
                            }
                            return;
                        }

                        // معالجة الملفات العادية (غير المجلدات) بشكل تدريجي
                        if (this.activeUploads >= this.maxConcurrentUploads) {
                            this.showAlert('يوجد عمليات رفع نشطة. يرجى الانتظار حتى تكتمل.', 'warning');
                            return;
                        }

                        // ⛔ التحقق من عدم وجود ملفات مكررة - إيقاف العملية تماماً
                        const duplicateFiles = Array.from(this.files.values()).filter(f => f.status === 'duplicate');
                        if (duplicateFiles.length > 0) {
                            console.error('⛔ تم العثور على', duplicateFiles.length, 'ملف مكرر - يجب إزالتها أولاً!');

                            Swal.fire({
                                icon: 'error',
                                title: '⛔ لا يمكن المتابعة!',
                                html: `
                                    <p><strong>${duplicateFiles.length}</strong> ملف مكرر موجود في القائمة.</p>
                                    <p class="text-danger"><strong>يجب إزالة جميع الملفات المكررة قبل المتابعة!</strong></p>
                                    <p>استخدم زر "🗑️ حذف" لإزالة الملفات المكررة من القائمة.</p>
                                `,
                                confirmButtonText: 'فهمت',
                                confirmButtonColor: '#dc3545',
                                allowOutsideClick: false
                            });

                            // ⛔ إيقاف العملية بشكل قاطع
                            return;
                        }

                        // تصفية الملفات - فقط الملفات المعلقة
                        const pendingFiles = Array.from(this.files.values()).filter(f => f.status === 'pending');

                        if (pendingFiles.length === 0) {
                            console.warn('⚠️ لا توجد ملفات جديدة للرفع');
                            Swal.fire({
                                icon: 'info',
                                title: 'لا توجد ملفات',
                                text: 'لا توجد ملفات جديدة للرفع.',
                                confirmButtonText: 'حسناً'
                            });
                            return;
                        }

                        this.updateFileCounts();

                        // رفع الملفات بشكل تدريجي ومتسلسل
                        await this.uploadFilesSequentially(pendingFiles);
                    }

                    // دالة جديدة لرفع الملفات بشكل متسلسل وسلس
                    async uploadFilesSequentially(files) {
                        console.log('🚀 بدء رفع الملفات العادية...', { count: files.length });

                        files.forEach(fileData => {
                            fileData.status = 'processing';
                            this.updateFileStatus(fileData.id, 'processing');
                        });
                        this.updateOverallProgress();

                        try {
                            const formData = new FormData();

                            files.forEach((fileData, index) => {
                                formData.append(`files[${index}]`, fileData.file);
                            });

                            formData.append('record_number', document.getElementById('recordNumber')?.value || '');
                            formData.append('person_id', document.getElementById('personId')?.value || '');
                            formData.append('auto_compress', document.getElementById('compressImages')?.checked || false);
                            formData.append('cloud_sync', document.getElementById('cloudSync')?.checked || false);
                            formData.append('auto_organize', document.getElementById('autoOrganize')?.checked || false);

                            console.log('📤 إرسال الملفات إلى الخادم via admin upload...');

                            const response = await fetch('/admin/file/upload-files', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: formData
                            });

                            const result = await response.json();

                            if (response.ok && result.success) {
                                console.log('✅ نجح رفع الملفات:', result);

                                files.forEach(fileData => {
                                    fileData.status = 'completed';
                                    fileData.progress = 100;
                                    this.updateFileStatus(fileData.id, 'completed');
                                });

                                refreshAllFolderFiles();
                            } else {
                                console.error('❌ فشل رفع الملفات:', result);
                                files.forEach(fileData => {
                                    fileData.status = 'failed';
                                    this.updateFileStatus(fileData.id, 'failed');
                                });
                            }
                        } catch (error) {
                            console.error('❌ خطأ في رفع الملفات:', error);
                            files.forEach(fileData => {
                                fileData.status = 'failed';
                                this.updateFileStatus(fileData.id, 'failed');
                            });
                        } finally {
                            this.updateFileCounts();
                            this.updateOverallProgress();

                            setTimeout(() => {
                                const progressSection = document.getElementById('uploadProgressSection');
                                if (progressSection) {
                                    progressSection.style.transition = 'opacity 0.5s ease';
                                    progressSection.style.opacity = '0';
                                    setTimeout(() => {
                                        progressSection.style.display = 'none';
                                        progressSection.style.opacity = '1';
                                    }, 500);
                                }
                            }, 1000);
                        }
                    }

                    updateFileStatus(fileId, status) {
                        // تحديث بيانات الملف
                        const fileData = this.files.get(fileId);
                        if (fileData) {
                            fileData.status = status;
                            this.files.set(fileId, fileData);
                        }

                        const fileElement = document.getElementById(`preview_${fileId}`);
                        const statusLabel = document.getElementById(`fileStatus_${fileId}`);
                        const overlay = document.getElementById(`overlay_${fileId}`);

                        if (status === 'processing') {
                            if (statusLabel) {
                                statusLabel.innerText = 'قيد المعالجة';
                                statusLabel.className = 'badge bg-primary text-white small';
                                statusLabel.style.background = 'linear-gradient(45deg, #007bff, #0056b3)';
                                statusLabel.style.fontWeight = 'bold';
                                statusLabel.style.animation = 'none';
                            }
                            if (overlay) {
                                overlay.style.display = 'flex';
                                overlay.classList.remove('d-none');
                            }
                            if (fileElement) {
                                fileElement.classList.add('processing');
                            }
                        } else if (status === 'completed') {
                            if (statusLabel) {
                                statusLabel.innerText = 'مكتملة';
                                statusLabel.className = 'badge bg-success text-white small';
                                statusLabel.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
                                statusLabel.style.animation = 'none';
                            }
                            if (overlay) {
                                overlay.style.display = 'none';
                                overlay.classList.add('d-none');
                            }
                            if (fileElement) {
                                fileElement.classList.remove('processing');
                                fileElement.style.animation = 'none';
                            }
                        } else if (status === 'failed') {
                            const failReason = fileData?.failReason;
                            if (statusLabel) {
                                statusLabel.innerText = failReason || 'فشل الرفع';
                                statusLabel.className = 'badge bg-danger text-white small';
                                statusLabel.style.background = 'linear-gradient(45deg, #dc3545, #c82333)';
                                statusLabel.style.animation = 'none';
                            }
                            if (overlay) {
                                overlay.style.display = 'none';
                                overlay.classList.add('d-none');
                            }
                            if (fileElement) {
                                fileElement.classList.remove('processing');
                                fileElement.style.borderColor = '#dc3545';
                            }
                        } else if (status === 'duplicate') {
                            if (statusLabel) {
                                statusLabel.innerText = '⚠️ مكرر - ممنوع الرفع';
                                statusLabel.className = 'badge bg-danger text-white small';
                                statusLabel.style.background = '#dc3545';
                                statusLabel.style.fontWeight = 'bold';
                                statusLabel.style.boxShadow = '0 2px 8px rgba(220, 53, 69, 0.4)';
                                statusLabel.style.border = '2px solid #bd2130';
                                statusLabel.style.fontSize = '0.85rem';
                                // ⛔ لا توجد animations على الإطلاق للملفات المكررة
                                statusLabel.style.animation = 'none';
                                statusLabel.style.transition = 'none';
                            }
                            if (overlay) {
                                overlay.style.display = 'none';
                                overlay.classList.add('d-none');
                            }
                            if (fileElement) {
                                fileElement.classList.remove('processing');
                                fileElement.classList.add('duplicate-file');
                                fileElement.style.border = '3px solid #dc3545';
                                fileElement.style.boxShadow = '0 4px 15px rgba(220, 53, 69, 0.6)';
                                // ⛔ لا توجد animations أو حركات على الكرت المكرر
                                fileElement.style.animation = 'none';
                                fileElement.style.transition = 'none';
                                fileElement.style.transform = 'none';

                                // إضافة زر عرض المقارنة داخل الكرت
                                const cardBody = fileElement.querySelector('.card-body');
                                if (cardBody && !cardBody.querySelector('.compare-duplicate-btn')) {
                                    const compareBtn = document.createElement('button');
                                    compareBtn.className = 'btn btn-danger btn-sm w-100 mt-2 compare-duplicate-btn';
                                    compareBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> عرض الملف القديم والجديد';
                                    compareBtn.onclick = () => {
                                        const fileId = fileElement.closest('[data-file-id]')?.getAttribute('data-file-id');
                                        if (fileId) {
                                            app.showDuplicateComparisonModal(fileId);
                                        }
                                    };
                                    cardBody.appendChild(compareBtn);
                                }
                            }
                        } else if (status === 'pending') {
                            if (statusLabel) {
                                statusLabel.innerText = 'معلق';
                                statusLabel.className = 'badge bg-info text-dark small';
                                statusLabel.style.background = 'linear-gradient(45deg, #17a2b8, #138496)';
                                statusLabel.style.animation = 'none';
                            }
                            if (overlay) {
                                overlay.style.display = 'none';
                                overlay.classList.add('d-none');
                            }
                            if (fileElement) {
                                fileElement.classList.remove('processing', 'duplicate-file');
                            }
                        }

                        // تحديث إحصائيات المجلد المعني
                        const folderSection = fileElement?.closest('.folder-section');
                        if (folderSection) {
                            const folderId = folderSection.id.replace('folder-section-', '');
                            this.updateFolderStats(folderId);
                        }

                        // تحديث تقدم العملية الإجمالي
                        this.updateOverallProgress();
                    }

                    updateOverallProgress() {
                        const totalFiles = this.files.size;
                        if (totalFiles === 0) return;

                        const completedFiles = Array.from(this.files.values()).filter(f => f.status === 'completed').length;
                        const processingFiles = Array.from(this.files.values()).filter(f => f.status === 'processing').length;
                        const failedFiles = Array.from(this.files.values()).filter(f => f.status === 'failed').length;

                        const progress = (completedFiles / totalFiles) * 100;

        // تحديث شريط التقدم الرئيسي بانيميشن سلس وألوان ديناميكية
        const overallProgress = document.getElementById('overallProgress');
        if (overallProgress) {
            // إضافة transition CSS لجعل التحرك سلساً
            overallProgress.style.transition = 'all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            overallProgress.style.width = `${progress}%`;
            overallProgress.setAttribute('aria-valuenow', progress);
            overallProgress.textContent = `${Math.round(progress)}%`;

            // تغيير اللون حسب نسبة التقدم - جميع درجات الأزرق الغامق جداً
            let gradient;
            if (progress < 25) {
                gradient = 'linear-gradient(90deg, #0D47A1, #1565C0)'; // أزرق غامق جداً للبداية
            } else if (progress < 50) {
                gradient = 'linear-gradient(90deg, #1565C0, #0277BD)'; // أزرق غامق للربع الثاني
            } else if (progress < 75) {
                gradient = 'linear-gradient(90deg, #0277BD, #01579B)'; // أزرق غامق جداً للربع الثالث
            } else if (progress < 100) {
                gradient = 'linear-gradient(90deg, #01579B, #0D47A1)'; // أزرق غامق جداً للربع الأخير
            } else {
                gradient = 'linear-gradient(90deg, #0D47A1, #1565C0, #0277BD, #01579B)'; // أزرق غامق متدرج للاكتمال
            }

            overallProgress.style.background = gradient;
            overallProgress.style.borderRadius = '10px';
            overallProgress.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
            overallProgress.style.color = 'white';
            overallProgress.style.fontWeight = 'bold';
            overallProgress.style.textShadow = '1px 1px 2px rgba(0,0,0,0.5)';

            // إضافة كلاس للانيميشن إذا كان التقدم يتحرك
            if (progress > 0 && progress < 100) {
                overallProgress.classList.add('progress-bar-animated');
                // إضافة تأثير وميض للانيميشن
                overallProgress.style.animation = 'progress-glow 2s ease-in-out infinite alternate';
            } else {
                overallProgress.classList.remove('progress-bar-animated');
                overallProgress.style.animation = 'none';
            }
        }                        // تحديث النص التوضيحي بتأثير سلس
                        const progressText = document.getElementById('progressText');
                        if (progressText) {
                            progressText.style.transition = 'opacity 0.2s ease';
                            progressText.style.opacity = '0.7';
                            progressText.textContent = `${completedFiles} من ${totalFiles} مكتمل`;

                            setTimeout(() => {
                                progressText.style.opacity = '1';
                            }, 100);
                        }

                        console.log(`📊 تحديث التقدم: ${Math.round(progress)}% (${completedFiles}/${totalFiles})`);
                    }

                    // دوال مساعدة لتحديث حالة جميع الملفات
                    updateAllFilesToProcessing() {
                        console.log('🔄 تحديث حالة الملفات الجديدة إلى "قيد المعالجة"...');

                        // ⛔ فقط الملفات التي ليست مكررة
                        this.files.forEach(fileData => {
                            if (fileData.status !== 'duplicate') {
                                fileData.status = 'processing';
                                this.updateFileStatus(fileData.id, 'processing');
                            }
                        });

                        // تحديث فوري للعدادات
                        setTimeout(() => {
                            this.updateFileCounts();
                            this.updateOverallProgress();
                        }, 100);
                    }

                    updateAllFilesToFailed() {
                        console.log('❌ تحديث حالة جميع الملفات إلى "فاشلة"...');
                        this.files.forEach(fileData => {
                            if (fileData.status !== 'completed' && fileData.status !== 'duplicate') {
                                fileData.status = 'failed';
                                this.updateFileStatus(fileData.id, 'failed');
                            }
                        });
                        setTimeout(() => {
                            this.updateFileCounts();
                            this.updateOverallProgress();
                        }, 100);
                    }

                    // دالة جديدة لتحديث الملفات المكررة
                    updateFilesToDuplicate(fileIds) {
                        console.log('⚠️ تحديث حالة الملفات المكررة...', fileIds);
                        if (!Array.isArray(fileIds)) {
                            fileIds = [fileIds];
                        }

                        fileIds.forEach(fileId => {
                            const fileData = Array.from(this.files.values()).find(f => f.id === fileId);
                            if (fileData) {
                                fileData.status = 'duplicate';
                                this.updateFileStatus(fileData.id, 'duplicate');
                                console.log(`🔄 تم تحديث الملف ${fileData.file.name} إلى "مكرر"`);
                            }
                        });

                        // تحديث العدادات
                        this.updateFileCounts();
                    }

                    // دالة لتحديث جميع الملفات إلى حالة مكررة
                    updateAllFilesToDuplicate() {
                        console.log('⚠️ تحديث حالة جميع الملفات إلى "مكررة" بشكل تدريجي...');
                        this.updateFilesGradually('duplicate');
                    }

                    // دالة جديدة لتحديث الملفات بشكل تدريجي وسلس
                    updateFilesGradually(targetStatus) {
                        const filesArray = Array.from(this.files.values());
                        let currentIndex = 0;

                        const updateNext = () => {
                            if (currentIndex < filesArray.length) {
                                const fileData = filesArray[currentIndex];
                                fileData.status = targetStatus;
                                this.updateFileStatus(fileData.id, targetStatus);

                                // تحديث التقدم مع كل ملف
                                this.updateFileCounts();
                                this.updateOverallProgress();

                                currentIndex++;

                                // انتظار قصير قبل الملف التالي (للتأثير البصري السلس)
                                setTimeout(updateNext, 150); // 150ms بين كل ملف
                            } else {
                                console.log(`✅ تم الانتهاء من تحديث جميع الملفات إلى "${targetStatus}"`);
                            }
                        };

                        updateNext();
                    }

                    /**
                     * عرض SweetAlert للملفات المكررة مع تفاصيل شاملة
                     */
                    showDuplicateFilesAlert(duplicatesInfo, sessionId) {
                        const duplicatesCount = duplicatesInfo.total_duplicates || 0;
                        const totalSize = duplicatesInfo.total_size || 0;
                        const formattedSize = totalSize > 0 ? this.formatFileSize(totalSize) : 'غير محدد';

                        console.log('🚨 عرض تنبيه الملفات المكررة:', {
                            count: duplicatesCount,
                            size: formattedSize,
                            sessionId: sessionId
                        });

                        // تحديد النوع والألوان حسب عدد الملفات المكررة
                        let icon, iconColor, confirmButtonColor;
                        if (duplicatesCount <= 5) {
                            icon = 'warning';
                            iconColor = '#f39c12';
                            confirmButtonColor = '#f39c12';
                        } else if (duplicatesCount <= 20) {
                            icon = 'error';
                            iconColor = '#e74c3c';
                            confirmButtonColor = '#e74c3c';
                        } else {
                            icon = 'error';
                            iconColor = '#c0392b';
                            confirmButtonColor = '#c0392b';
                        }

                        // إنشاء محتوى HTML مفصل
                        let htmlContent = `
                            <div class="duplicate-alert-content" style="text-align: center; direction: rtl;">
                                <div class="mb-3">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: ${iconColor}; margin-bottom: 15px;"></i>
                                </div>

                                <div class="alert alert-warning" style="border-radius: 10px; margin-bottom: 20px;">
                                    <h5 style="margin-bottom: 10px; color: #856404;">
                                        <i class="fas fa-copy me-2"></i>تم اكتشاف ملفات مكررة!
                                    </h5>
                                    <hr style="margin: 10px 0; border-color: rgba(133, 100, 4, 0.2);">
                                    <div class="row text-center">
                                        <div class="col-md-6">
                                            <strong>عدد الملفات المكررة:</strong><br>
                                            <span class="badge bg-danger fs-6">${duplicatesCount} ملف</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>الحجم الإجمالي:</strong><br>
                                            <span class="badge bg-info fs-6">${formattedSize}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <p style="font-size: 16px; color: #495057; line-height: 1.6;">
                                        تم حفظ الملفات المكررة في مجلد مؤقت للمراجعة. يمكنك:
                                    </p>
                                </div>

                                <div class="action-buttons" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                    <button id="viewDuplicatesBtn" class="btn btn-warning" style="border-radius: 20px; padding: 8px 20px;">
                                        <i class="fas fa-eye me-2"></i>عرض الملفات المكررة
                                    </button>
                                    <button id="downloadDuplicatesBtn" class="btn btn-success" style="border-radius: 20px; padding: 8px 20px;">
                                        <i class="fas fa-download me-2"></i>تحميل كملف مضغوط
                                    </button>
                                </div>

                                <div class="mt-3">
                                    <small style="color: #6c757d;">
                                        <i class="fas fa-clock me-1"></i>
                                        سيتم حذف الملفات المؤقتة تلقائياً بعد 24 ساعة
                                    </small>
                                </div>
                            </div>
                        `;

                        Swal.fire({
                            title: 'تحذير: ملفات مكررة!',
                            html: htmlContent,
                            icon: icon,
                            iconColor: iconColor,
                            showCancelButton: true,
                            confirmButtonColor: confirmButtonColor,
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-check me-2"></i>موافق، فهمت',
                            cancelButtonText: '<i class="fas fa-times me-2"></i>إغلاق',
                            width: '600px',
                            padding: '25px',
                            background: '#fff',
                            backdrop: `
                                rgba(0,0,0,0.6)
                                left top
                                no-repeat
                            `,
                            showClass: {
                                popup: 'animate__animated animate__slideInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__slideOutUp'
                            },
                            customClass: {
                                popup: 'duplicate-alert-popup',
                                title: 'duplicate-alert-title',
                                htmlContainer: 'duplicate-alert-html'
                            },
                            didOpen: () => {
                                // إضافة معالجات الأحداث للأزرار
                                const viewBtn = document.getElementById('viewDuplicatesBtn');
                                const downloadBtn = document.getElementById('downloadDuplicatesBtn');

                                if (viewBtn) {
                                    viewBtn.addEventListener('click', () => {
                                        this.showDuplicateFilesModal(sessionId);
                                        Swal.close();
                                    });
                                }

                                if (downloadBtn) {
                                    downloadBtn.addEventListener('click', () => {
                                        this.downloadDuplicateFiles(sessionId);
                                        Swal.close();
                                    });
                                }

                                // إضافة أنيميشن للأزرار
                                const buttons = document.querySelectorAll('.action-buttons .btn');
                                buttons.forEach(btn => {
                                    btn.addEventListener('mouseenter', function() {
                                        this.style.transform = 'translateY(-2px)';
                                        this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
                                    });
                                    btn.addEventListener('mouseleave', function() {
                                        this.style.transform = 'translateY(0)';
                                        this.style.boxShadow = 'none';
                                    });
                                });
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                console.log('✅ تم تأكيد فهم المستخدم للملفات المكررة');

                                // يمكن إضافة منطق إضافي هنا لتحديث حالة الملفات المكررة
                                // مثال: تحديث الملفات المكررة في الواجهة
                                // this.updateFilesToDuplicate(duplicateFileIds);
                            }
                        });
                    }

                    /**
                     * تحميل الملفات المكررة كملف مضغوط
                     */
                    async downloadDuplicateFiles(sessionId) {
                        try {
                            console.log('📥 بدء تحميل الملفات المكررة...');

                            // عرض loading
                            Swal.fire({
                                title: 'جاري التحميل...',
                                html: 'يتم تحضير الملفات المكررة للتحميل',
                                allowOutsideClick: false,
                                showConfirmButton: false,
                                willOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            // بدء التحميل
                            const downloadUrl = `/admin/file/download-duplicate-files?session_id=${sessionId}`;
                            window.open(downloadUrl, '_blank');

                            // إغلاق loading بعد ثانيتين
                            setTimeout(() => {
                                Swal.close();
                                this.showAlert('تم بدء تحميل الملفات المكررة', 'success');
                            }, 2000);

                        } catch (error) {
                            console.error('خطأ في تحميل الملفات المكررة:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ في التحميل',
                                text: 'حدث خطأ أثناء تحميل الملفات المكررة',
                                confirmButtonText: 'موافق',
                                confirmButtonColor: '#e74c3c',
                                background: '#fff',
                                customClass: {
                                    popup: 'duplicate-alert-popup'
                                }
                            });
                        }
                    }

                    showAlert(message, type = 'info') {
                        const alertBox = document.createElement('div');
                        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
                        alertBox.role = 'alert';
                        alertBox.innerHTML = `
                    <i class="fas fa-info-circle me-2"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                        document.querySelector('.container-fluid').prepend(alertBox);

                        // إزالة التنبيه تلقائياً بعد 5 ثوان
                        setTimeout(() => {
                            if (alertBox && alertBox.parentNode) {
                                alertBox.parentNode.removeChild(alertBox);
                            }
                        }, 5000);
                    }

                    handleFilterChange(e) {
                        const filter = e.target.value;
                        const files = document.querySelectorAll('#filePreviewsContainer .file-preview');

                        files.forEach(file => {
                            const fileType = file.querySelector('[id^="fileType_"]').innerText.toLowerCase();
                            if (filter === 'all' || fileType === filter) {
                                file.closest('.col-md-4').style.display = 'block';
                            } else {
                                file.closest('.col-md-4').style.display = 'none';
                            }
                        });
                    }

                    downloadFile(fileId) {
                        const fileData = this.files.get(fileId);
                        if (!fileData) return;

                        const url = URL.createObjectURL(fileData.file);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = fileData.file.name;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        this.showAlert(`جاري تحميل ${fileData.file.name}...`, 'info');
                    }

                    deleteFile(fileId) {
                        this.files.delete(fileId);
                        document.getElementById(`preview_${fileId}`).closest('.col-md-4').remove();
                        this.showAlert('تم حذف الملف بنجاح.', 'success');
                    }

                    /**
                     * تحديث زر عرض الملفات المكررة
                     */
                    updateDuplicateFilesButton(duplicatesInfo) {
                        const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                        if (duplicateBtn && duplicatesInfo) {
                            duplicateBtn.style.display = 'inline-block';
                            duplicateBtn.innerHTML =
                                `<i class="fas fa-clone me-2"></i>عرض الملفات المكررة (${duplicatesInfo.total_duplicates})`;
                            duplicateBtn.classList.add('btn-warning');
                            duplicateBtn.classList.remove('btn-secondary');

                            // إضافة وظيفة النقر
                            duplicateBtn.onclick = () => {
                                this.showDuplicateFilesModal(duplicatesInfo.session_id);
                            };
                        }
                    }

                    /**
                     * عرض modal الملفات المكررة
                     */
                    async showDuplicateFilesModal(sessionId) {
                        try {
                            // جلب قائمة الملفات المكررة
                            const response = await fetch(`/admin/file/duplicate-summary?session_id=${sessionId}`);
                            const result = await response.json();

                            if (result.success && result.data.total_duplicates > 0) {
                                // تحديث محتوى الـ modal
                                this.populateDuplicateFilesModal(result.data);

                                // عرض الـ modal
                                const modal = new bootstrap.Modal(document.getElementById('duplicateFilesModal'));
                                modal.show();
                            } else {
                                this.showAlert('لا توجد ملفات مكررة للعرض', 'info');
                            }
                        } catch (error) {
                            console.error('خطأ في جلب الملفات المكررة:', error);
                            this.showAlert('حدث خطأ أثناء جلب قائمة الملفات المكررة', 'danger');
                        }
                    }

                    /**
                     * ملء محتوى modal الملفات المكررة
                     */
                    populateDuplicateFilesModal(duplicateData) {
                        const modalBody = document.querySelector('#duplicateFilesModal .modal-body');

                        let html = `
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> تم العثور على ${duplicateData.total_duplicates} ملف مكرر</h6>
                        <p>الحجم الإجمالي: ${this.formatFileSize(duplicateData.total_size)}</p>
                    </div>
                `;

                        // عرض تحليل المجلدات إذا كان متوفراً
                        if (duplicateData.folders_analysis) {
                            html += `<h6>تحليل المجلدات المتأثرة:</h6>`;
                            Object.values(duplicateData.folders_analysis).forEach(folder => {
                                html += `
                            <div class="card mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">مجلد: ${folder.folder_id}</h6>
                                    <p class="card-text">
                                        ملفات مكررة: ${folder.duplicates_count}<br>
                                        الحجم: ${this.formatFileSize(folder.total_size)}
                                    </p>
                                </div>
                            </div>
                        `;
                            });
                        }

                        // قائمة الملفات المكررة
                        html += `<h6>قائمة الملفات المكررة:</h6>`;
                        html += `<div class="list-group">`;

                        duplicateData.files.forEach(file => {
                            const canDownload = file.can_download;
                            html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">${file.original_name}</h6>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            المجلد الأصلي: ${file.original_folder} → المجلد الهدف: ${file.target_folder}
                                        </small>
                                    </p>
                                    <small>الحجم: ${this.formatFileSize(file.file_size)}</small>
                                </div>
                                <div>
                                    ${canDownload ? `
                                                                <button class="btn btn-sm btn-outline-primary" onclick="window.open('${file.download_url}', '_blank')">
                                                                    <i class="fas fa-download"></i> تحميل
                                                                </button>
                                                            ` : `
                                                                <span class="badge bg-secondary">غير متوفر</span>
                                                            `}
                                </div>
                            </div>
                        </div>
                    `;
                        });

                        html += `</div>`;

                        // أزرار العمليات
                        html += `
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-primary" onclick="app.downloadAllDuplicates('${duplicateData.session_id}')">
                            <i class="fas fa-download"></i> تحميل جميع الملفات
                        </button>
                        <button class="btn btn-danger" onclick="app.deleteAllDuplicates('${duplicateData.session_id}')">
                            <i class="fas fa-trash"></i> حذف جميع الملفات المكررة
                        </button>
                        <button class="btn btn-info" onclick="app.showDuplicateStatistics('${duplicateData.session_id}')">
                            <i class="fas fa-chart-bar"></i> إحصائيات مفصلة
                        </button>
                    </div>
                `;

                        modalBody.innerHTML = html;
                    }

                    /**
                     * تحميل جميع الملفات المكررة
                     */
                    async downloadAllDuplicates(sessionId) {
                        try {
                            window.open(`/admin/file/download-duplicates?session_id=${sessionId}`, '_blank');
                            this.showAlert('جاري تحميل جميع الملفات المكررة...', 'info');
                        } catch (error) {
                            console.error('خطأ في تحميل الملفات:', error);
                            this.showAlert('حدث خطأ أثناء تحميل الملفات', 'danger');
                        }
                    }

                    /**
                     * حذف جميع الملفات المكررة
                     */
                    async deleteAllDuplicates(sessionId) {
                        if (!confirm('هل أنت متأكد من حذف جميع الملفات المكررة؟ هذا الإجراء لا يمكن التراجع عنه.')) {
                            return;
                        }

                        try {
                            const response = await fetch(`/admin/file/delete-duplicates?session_id=${sessionId}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                        'content')
                                }
                            });

                            const result = await response.json();

                            if (result.success) {
                                this.showAlert(`تم حذف ${result.data.deleted_files} ملف مكرر بنجاح`, 'success');

                                // إغلاق الـ modal
                                const modal = bootstrap.Modal.getInstance(document.getElementById('duplicateFilesModal'));
                                if (modal) modal.hide();

                                // إخفاء زر عرض الملفات المكررة
                                const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                                if (duplicateBtn) {
                                    duplicateBtn.style.display = 'none';
                                }

                                // مسح معرف الجلسة
                                sessionStorage.removeItem('duplicate_files_session_id');
                                delete window.CURRENT_DUPLICATE_SESSION_ID;

                            } else {
                                this.showAlert(`فشل في حذف الملفات: ${result.message}`, 'danger');
                            }
                        } catch (error) {
                            console.error('خطأ في حذف الملفات:', error);
                            this.showAlert('حدث خطأ أثناء حذف الملفات', 'danger');
                        }
                    }

                    /**
                     * عرض إحصائيات مفصلة للملفات المكررة
                     */
                    async showDuplicateStatistics(sessionId) {
                        try {
                            const response = await fetch(`/admin/file/duplicate-statistics?session_id=${sessionId}`);
                            const result = await response.json();

                            if (result.success) {
                                const stats = result.data;
                                let html = `
                            <div class="modal fade" id="statisticsModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">إحصائيات الملفات المكررة</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">إحصائيات عامة</div>
                                                        <div class="card-body">
                                                            <p>إجمالي الملفات المكررة: <strong>${stats.total_duplicates}</strong></p>
                                                            <p>الحجم الإجمالي: <strong>${this.formatFileSize(stats.total_size)}</strong></p>
                                                            <p>المجلدات المتأثرة: <strong>${stats.folders_affected}</strong></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">أنواع الملفات</div>
                                                        <div class="card-body">
                        `;

                                Object.entries(stats.file_types).forEach(([ext, count]) => {
                                    html += `<p>${ext.toUpperCase()}: <strong>${count}</strong> ملف</p>`;
                                });

                                html += `
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <h6>تفصيل المجلدات:</h6>
                        `;

                                Object.values(stats.folders_breakdown).forEach(folder => {
                                    html += `
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <h6>مجلد: ${folder.folder_id}</h6>
                                        <p>ملفات مكررة: ${folder.duplicates_count} | الحجم: ${this.formatFileSize(folder.total_size)}</p>
                                        <small class="text-muted">الملفات: ${folder.files.join(', ')}</small>
                                    </div>
                                </div>
                            `;
                                });

                                html += `
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                                // إضافة الـ modal إلى الصفحة وعرضه
                                document.body.insertAdjacentHTML('beforeend', html);
                                const statsModal = new bootstrap.Modal(document.getElementById('statisticsModal'));
                                statsModal.show();

                                // حذف الـ modal عند الإغلاق
                                document.getElementById('statisticsModal').addEventListener('hidden.bs.modal', function() {
                                    this.remove();
                                });

                            } else {
                                this.showAlert('فشل في جلب الإحصائيات', 'danger');
                            }
                        } catch (error) {
                            console.error('خطأ في جلب الإحصائيات:', error);
                            this.showAlert('حدث خطأ أثناء جلب الإحصائيات', 'danger');
                        }
                    }

                    /**
                     * تنسيق حجم الملف
                     */
                    formatFileSize(bytes) {
                        if (bytes === 0) return '0 Bytes';
                        const k = 1024;
                        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                        const i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                    }

                    /**
                     * نظام الرفع المتعدد للمجلدات الكبيرة
                     */
                    async uploadFolderFileWithBatches(files, uploadType) {
                        console.log(`🚀 بدء رفع ${uploadType} باستخدام النظام المتعدد:`, {
                            filesCount: files.length,
                            timestamp: new Date().toISOString()
                        });

                        // إنشاء الدفعات
                        const batches = this.createBatches(files, 10, 50 * 1024 * 1024); // 10 ملفات، 50MB max
                        console.log(`📦 تم إنشاء ${batches.length} دفعة`);

                        // جمع الخيارات
                        const options = {
                            compress_images: document.getElementById('compressImages')?.checked || false,
                            auto_organize: document.getElementById('autoOrganize')?.checked || true,
                            cloud_sync: document.getElementById('cloudSync')?.checked || false,
                            enable_duplicate_detection: this.duplicateDetectionEnabled
                        };

                        // إضافة ملف Excel إذا كان موجود
                        const excelFile = document.getElementById('excelFileInput')?.files[0];
                        if (excelFile) {
                            options.excelFile = excelFile;
                            options.enable_excel_import = document.getElementById('enableExcelImport')?.checked || false;
                            options.target_table = document.getElementById('targetTable')?.value || 'data';
                        }

                        try {
                            // === إنشاء جلسة رفع في IndexedDB ===
                            let session;
                            let startBatch = 0;
                            let totalSuccessful = 0;
                            let totalDuplicates = 0;
                            let totalErrors = 0;
                            let allRejectedFolders = new Set();

                            // التحقق من وجود جلسة استئناف معلقة
                            if (this._uploadSession.pendingResumeSessionId) {
                                session = await uploadDB.getSession(this._uploadSession.pendingResumeSessionId);
                                if (session && session.status === 'paused') {
                                    startBatch = session.last_completed_batch + 1;
                                    totalSuccessful = session.stats.successful;
                                    totalDuplicates = session.stats.duplicates;
                                    totalErrors = session.stats.errors;
                                    console.log(`▶️ استئناف جلسة معلقة من الدفعة ${startBatch + 1}/${batches.length}`);
                                }
                                this._uploadSession.pendingResumeSessionId = null;
                            }

                            if (!session) {
                                session = await uploadDB.createSession({
                                    upload_type: uploadType,
                                    total_files: files.length,
                                    total_batches: batches.length,
                                    options: {
                                        compress_images: options.compress_images,
                                        auto_organize: options.auto_organize,
                                        cloud_sync: options.cloud_sync,
                                        enable_duplicate_detection: options.enable_duplicate_detection
                                    }
                                });

                                // حفظ بيانات الملفات في IndexedDB لكل دفعة
                                for (let bIdx = 0; bIdx < batches.length; bIdx++) {
                                    const batchMeta = batches[bIdx].map(f => ({
                                        name: f.name,
                                        path: f.webkitRelativePath || f.name,
                                        size: f.size,
                                        type: f.type,
                                        status: 'pending'
                                    }));
                                    await uploadDB.saveSessionFiles(session.id, bIdx, batchMeta);
                                }
                                console.log(`💾 تم حفظ بيانات ${files.length} ملف في IndexedDB`);
                            }

                            this._uploadSession.currentSessionId = session.id;
                            this._uploadSession.isUploading = true;
                            this._uploadSession.isPaused = false;

                            // تحديث حالة جميع الملفات إلى "قيد المعالجة"
                            this.updateAllFilesToProcessing();

                            // عرض شريط التقدم مع تتبع حقيقي
                            this.showRealTimeUploadProgress(batches.length);

                            let processedBatches = startBatch;
                            let duplicateSessionId = null;

                            if (startBatch > 0) {
                                this.updateRealTimeProgress(
                                    Math.round((startBatch / batches.length) * 100),
                                    `استئناف... مكتمل: ${totalSuccessful}, مكرر: ${totalDuplicates}, أخطاء: ${totalErrors}`
                                );
                            }

                            for (let i = startBatch; i < batches.length; i++) {
                                // === فحص إيقاف مؤقت (إنترنت أو مستخدم) ===
                                if (this._uploadSession.isPaused) {
                                    console.log(`⏸️ تم إيقاف الرفع عند الدفعة ${i + 1}/${batches.length}`);
                                    await uploadDB.updateSession(session.id, {
                                        status: 'paused',
                                        pause_reason: this._uploadSession.pauseReason,
                                        stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                    });
                                    this._uploadSession.isUploading = false;
                                    return { success: false, paused: true, totalSuccessful, totalDuplicates, totalErrors };
                                }

                                // === فحص الاتصال قبل كل دفعة ===
                                if (!navigator.onLine) {
                                    console.warn('🔴 لا يوجد اتصال - إيقاف الرفع');
                                    this._uploadSession.isPaused = true;
                                    this._uploadSession.pauseReason = 'offline';
                                    await uploadDB.updateSession(session.id, {
                                        status: 'paused',
                                        pause_reason: 'offline',
                                        stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                    });
                                    this._showConnectionStatus(false);
                                    this._uploadSession.isUploading = false;
                                    return { success: false, paused: true, totalSuccessful, totalDuplicates, totalErrors };
                                }

                                this.updateBatchProgress(i, batches.length, `معالجة الدفعة ${i + 1}/${batches.length}...`);

                                try {
                                    const result = await this.uploadSingleBatch(batches[i], i, batches.length, options);

                                    if (result.success) {
                                        totalSuccessful += result.statistics?.files_saved || 0;
                                        const batchDuplicates = result.duplicate_results?.duplicates_found || result.statistics?.duplicates_detected || 0;
                                        totalDuplicates += batchDuplicates;

                                        if (result.session_id && !duplicateSessionId) {
                                            duplicateSessionId = result.session_id;
                                        }

                                        console.log(`✅ تم رفع الدفعة ${i + 1} بنجاح - الملفات المحفوظة: ${result.statistics?.files_saved || 0}, مكررة: ${batchDuplicates}`);

                                        if (batchDuplicates > 0) {
                                            if (!this.duplicateDetectionEnabled) {
                                                if (result.duplicate_results && result.duplicate_results.duplicate_files) {
                                                    this.updateSpecificDuplicateFiles(result.duplicate_results.duplicate_files);
                                                }
                                            } else {
                                                this.updateProcessedFilesStatus(batchDuplicates, 'duplicate');
                                                if (result.duplicate_results && result.duplicate_results.duplicate_files) {
                                                    this.updateSpecificDuplicateFiles(result.duplicate_results.duplicate_files);
                                                }
                                            }
                                        }

                                        const updatedFiles = this.updateProcessedFilesStatus(result.statistics?.files_saved || 0, 'completed');

                                        // تحديث ملفات المجلدات المرفوضة (لا يوجد شخص في النظام) إلى حالة فشل
                                        if (result.folder_analysis?.rejected_folders?.length > 0) {
                                            result.folder_analysis.rejected_folders.forEach(f => allRejectedFolders.add(f));
                                            const rejectedCount = this.markRejectedFolderFilesAsFailed(result.folder_analysis.rejected_folders);
                                            totalErrors += rejectedCount;
                                        }
                                    } else {
                                        totalErrors++;
                                        console.error(`❌ فشل في رفع الدفعة ${i + 1}:`, result.message);
                                        this.updateProcessedFilesStatus(batches[i].length, 'failed');
                                    }
                                } catch (error) {
                                    // === خطأ شبكة: إيقاف مؤقت وحفظ الحالة ===
                                    if (!navigator.onLine || error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                                        console.warn('🔴 خطأ شبكة أثناء الرفع - إيقاف مؤقت وحفظ التقدم');
                                        this._uploadSession.isPaused = true;
                                        this._uploadSession.pauseReason = 'offline';
                                        await uploadDB.updateSession(session.id, {
                                            status: 'paused',
                                            pause_reason: 'network_error',
                                            last_completed_batch: i - 1,
                                            completed_batches: i,
                                            stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                        });
                                        this._showConnectionStatus(false);
                                        this._uploadSession.isUploading = false;
                                        return { success: false, paused: true, totalSuccessful, totalDuplicates, totalErrors };
                                    }
                                    totalErrors++;
                                    console.error(`❌ خطأ في رفع الدفعة ${i + 1}:`, error);
                                    this.updateProcessedFilesStatus(batches[i].length, 'failed');
                                }

                                processedBatches = i + 1;

                                // === حفظ التقدم في IndexedDB بعد كل دفعة ===
                                await uploadDB.updateSession(session.id, {
                                    last_completed_batch: i,
                                    completed_batches: processedBatches,
                                    stats: { successful: totalSuccessful, duplicates: totalDuplicates, errors: totalErrors }
                                });

                                // تحديث حالة ملفات الدفعة في IndexedDB
                                try {
                                    await uploadDB.updateBatchFilesStatus(session.id, i, 'uploaded');
                                } catch(e) { /* تجاهل */ }

                                const overallProgress = Math.round((processedBatches / batches.length) * 100);
                                this.updateRealTimeProgress(overallProgress, `مكتمل: ${totalSuccessful}, مكرر: ${totalDuplicates}, أخطاء: ${totalErrors}`);

                                if (i < batches.length - 1) {
                                    await new Promise(resolve => setTimeout(resolve, 500));
                                }
                            }

                            // === اكتملت جميع الدفعات ===
                            this._uploadSession.isUploading = false;
                            this._uploadSession.currentSessionId = null;
                            await uploadDB.updateSession(session.id, { status: 'completed' });

                            // تحديث الملفات المتبقية في حالة "قيد المعالجة"
                            const remainingProcessing = Array.from(this.files.values()).filter(f => f.status === 'processing');
                            if (remainingProcessing.length > 0) {
                                remainingProcessing.forEach(fileData => {
                                    // ملفات مجلدات بدون معلومات شخص تبقى فاشلة
                                    if (fileData.identityFolder && fileData.failReason) {
                                        // تم تحديثها مسبقاً
                                    } else {
                                        fileData.status = 'completed';
                                        this.updateFileStatus(fileData.id, 'completed');
                                        totalSuccessful++;
                                    }
                                });
                                console.log(`📊 تحديث الملفات المتبقية من "قيد المعالجة"`);
                            }

                            this.updateBatchProgress(batches.length, batches.length, 'تم الانتهاء!');
                            this.updateRealTimeProgress(100, `النهاية: مكتمل ${totalSuccessful}, مكرر ${totalDuplicates}, أخطاء ${totalErrors}`);

                            if (totalDuplicates > 0 && duplicateSessionId && this.duplicateDetectionEnabled) {
                                this.updateFileCounts();
                                this.updateDuplicateFilesButton({
                                    total_duplicates: totalDuplicates,
                                    session_id: duplicateSessionId
                                });
                                setTimeout(() => {
                                    this.showDuplicateFilesAlert({
                                        total_duplicates: totalDuplicates,
                                        total_size: 0
                                    }, duplicateSessionId);
                                }, 1000);
                            }

                            this.updateFileCounts();
                            this.loadAnalytics();

                            // عرض تنبيه بالمجلدات المرفوضة (لا يوجد شخص في النظام)
                            if (allRejectedFolders.size > 0) {
                                const rejectedList = Array.from(allRejectedFolders).join('، ');
                                const rejectedFilesCount = Array.from(this.files.values()).filter(f => f.failReason).length;
                                this.showAlert(
                                    `⚠️ لم يتم رفع ${rejectedFilesCount} ملف بسبب عدم وجود معلومات الشخص في النظام.\n` +
                                    `المجلدات المرفوضة: ${rejectedList}`,
                                    'warning'
                                );
                            }

                            setTimeout(() => {
                                this.hideBatchUploadProgress();
                                this.hideRealTimeProgress();
                            }, 3000);

                            // تنظيف الجلسة المكتملة
                            setTimeout(() => uploadDB.deleteSession(session.id), 10000);

                            return {
                                success: totalErrors === 0,
                                totalSuccessful,
                                totalDuplicates,
                                totalErrors
                            };

                        } catch (error) {
                            console.error('❌ خطأ في الرفع المتعدد:', error);
                            this.showAlert(`فشل في رفع المجلد: ${error.message}`, 'danger');
                            this._uploadSession.isUploading = false;
                            this.updateAllFilesToFailed();
                            this.hideBatchUploadProgress();
                            throw error;
                        }
                    }

                    /**
                     * إنشاء دفعات من الملفات
                     */
                    createBatches(files, maxFiles, maxSize) {
                        const batches = [];
                        let currentBatch = [];
                        let currentSize = 0;

                        for (let file of files) {
                            // الملفات الكبيرة توضع في دفعة خاصة بها بدلاً من تخطيها
                            if (file.size > maxSize) {
                                // حفظ الدفعة الحالية أولاً
                                if (currentBatch.length > 0) {
                                    batches.push(currentBatch);
                                    currentBatch = [];
                                    currentSize = 0;
                                }
                                // إنشاء دفعة خاصة للملف الكبير
                                batches.push([file]);
                                console.log(`📦 ملف كبير في دفعة خاصة: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
                                continue;
                            }

                            // إضافة للدفعة الحالية أو إنشاء دفعة جديدة
                            if (currentBatch.length < maxFiles && currentSize + file.size <= maxSize) {
                                currentBatch.push(file);
                                currentSize += file.size;
                            } else {
                                if (currentBatch.length > 0) {
                                    batches.push(currentBatch);
                                }
                                currentBatch = [file];
                                currentSize = file.size;
                            }
                        }

                        if (currentBatch.length > 0) {
                            batches.push(currentBatch);
                        }

                        return batches;
                    }

                    /**
                     * رفع دفعة واحدة
                     */
                    async uploadSingleBatch(files, batchIndex, totalBatches, options = {}) {
                        const formData = new FormData();

                        // إضافة جميع الملفات
                        files.forEach((file, index) => {
                            formData.append(`files[${index}]`, file);
                            formData.append(`paths[${index}]`, file.webkitRelativePath || file.name);
                        });

                        console.log(`📤 رفع ${files.length} ملف`);

                        // إضافة معلومات الدفعة
                        formData.append('batch_index', batchIndex);
                        formData.append('total_batches', totalBatches);
                        formData.append('is_final_batch', batchIndex === totalBatches - 1 ? '1' : '0');
                        formData.append('upload_type', 'batch_folder');

                        // إضافة الخيارات
                        if (options.compress_images) formData.append('compress_images', options.compress_images);
                        if (options.auto_organize) formData.append('auto_organize', options.auto_organize);
                        if (options.cloud_sync) formData.append('cloud_sync', options.cloud_sync);
                        if (options.enable_duplicate_detection !== undefined) formData.append('enable_duplicate_detection', options.enable_duplicate_detection);

                        // إضافة ملف Excel فقط في الدفعة الأولى
                        if (batchIndex === 0 && options.excelFile) {
                            formData.append('excel_file', options.excelFile);
                            formData.append('enable_excel_import', options.enable_excel_import);
                            formData.append('target_table', options.target_table);
                        }

                        try {
                            const response = await fetch('/admin/file/process-bulk-folder-upload-batch', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: formData
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }

                            return await response.json();

                        } catch (error) {
                            return {
                                success: false,
                                message: error.message,
                                batch_index: batchIndex
                            };
                        }
                    }

                    /**
                     * عرض شريط تقدم الرفع المتعدد
                     */
                    showBatchUploadProgress() {
                        let progressHtml = `
                        <div class="batch-upload-progress-container" style="margin: 20px 0;">
                            <h5>📊 تقدم الرفع المتعدد</h5>
                            <div class="progress" style="height: 25px; border-radius: 15px; box-shadow: 0 2px 4px rgba(13,71,161,0.4);">
                                <div class="progress-bar batch-upload-progress"
                                     role="progressbar" style="width: 0%; background: linear-gradient(90deg, #0D47A1, #1565C0, #0277BD, #01579B); border-radius: 15px; transition: width 0.5s ease;"
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    0%
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <small class="batch-upload-text text-muted">جاري التحضير...</small>
                            </div>
                        </div>`;

                        // البحث عن منطقة مناسبة لعرض شريط التقدم
                        const targetArea = document.querySelector('.upload-status') ||
                                          document.querySelector('.file-upload-container') ||
                                          document.querySelector('.modal-body') ||
                                          document.querySelector('main');

                        if (targetArea) {
                            targetArea.insertAdjacentHTML('beforeend', progressHtml);
                        }
                    }

                    /**
                     * عرض شريط تقدم متزامن مع الخلفية
                     */
                    showRealTimeUploadProgress(totalBatches) {
                        let progressHtml = `
                        <div class="real-time-progress-container" style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #0D47A1 0%, #01579B 100%); border-radius: 12px; box-shadow: 0 4px 15px rgba(13,71,161,0.5);">
                            <h5><i class="fa fa-cloud-upload text-white"></i> <span style="color: white;">تقدم الرفع الحقيقي</span></h5>
                            <div class="progress" style="height: 35px; margin-bottom: 10px; border-radius: 20px; background: rgba(255,255,255,0.2);">
                                <div class="progress-bar real-time-progress"
                                     role="progressbar" style="width: 0%; background: linear-gradient(90deg, #0D47A1, #1565C0, #0277BD, #01579B); border-radius: 20px; box-shadow: 0 2px 10px rgba(13,71,161,0.6); transition: width 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);"
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <span class="progress-percentage" style="color: white; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">0%</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="real-time-status text-muted">جاري بدء العملية...</small>
                                </div>
                                <div class="col-6 text-right">
                                    <small class="batch-counter text-info">0/${totalBatches} دفعات</small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="files-stats text-success">مكتمل: 0 | مكرر: 0 | أخطاء: 0</small>
                            </div>
                        </div>`;

                        // البحث عن منطقة مناسبة لعرض شريط التقدم
                        const targetArea = document.querySelector('.upload-status') ||
                                          document.querySelector('.file-upload-container') ||
                                          document.querySelector('.modal-body') ||
                                          document.querySelector('main');

                        if (targetArea) {
                            // إزالة أي شريط تقدم قديم
                            const oldProgress = targetArea.querySelector('.real-time-progress-container');
                            if (oldProgress) oldProgress.remove();

                            targetArea.insertAdjacentHTML('beforeend', progressHtml);
                        }
                    }

                    /**
                     * تحديث التقدم الحقيقي بناءً على نتائج الخلفية
                     */
                    updateRealTimeProgress(percentage, statusMessage) {
                        const progressBar = document.querySelector('.real-time-progress');
                        const statusElement = document.querySelector('.real-time-status');
                        const statsElement = document.querySelector('.files-stats');
                        const percentageElement = document.querySelector('.progress-percentage');

                        if (progressBar) {
                            progressBar.style.width = percentage + '%';
                            progressBar.setAttribute('aria-valuenow', percentage);
                        }

                        if (percentageElement) {
                            percentageElement.textContent = percentage + '%';
                        }

                        if (statusElement) {
                            statusElement.textContent = statusMessage || `تقدم العملية: ${percentage}%`;
                        }

                        if (statsElement && statusMessage) {
                            statsElement.textContent = statusMessage;
                        }

                        console.log(`📊 تحديث التقدم الحقيقي: ${percentage}% - ${statusMessage}`);
                    }

                    /**
                     * تحديث حالة الملفات المعالجة بناءً على النتائج الفعلية
                     */
                    /**
                     * تحديث ملفات المجلدات المرفوضة (لا توجد معلومات شخص) إلى حالة فشل
                     */
                    markRejectedFolderFilesAsFailed(rejectedFolders) {
                        if (!rejectedFolders || !Array.isArray(rejectedFolders) || rejectedFolders.length === 0) {
                            return 0;
                        }

                        const rejectedSet = new Set(rejectedFolders.map(String));
                        let markedCount = 0;

                        for (const [fileId, fileData] of this.files) {
                            if (fileData.status === 'processing' && fileData.identityFolder && rejectedSet.has(String(fileData.identityFolder))) {
                                fileData.status = 'failed';
                                fileData.failReason = 'لا توجد معلومات كافية للشخص في النظام';
                                this.files.set(fileId, fileData);
                                this.updateFileStatus(fileId, 'failed');
                                markedCount++;
                            }
                        }

                        if (markedCount > 0) {
                            console.log(`⚠️ تم تحديث ${markedCount} ملف من مجلدات مرفوضة (${rejectedFolders.join(', ')}) إلى حالة "فشل"`);
                            this.updateFileCounts();
                            this.updateOverallProgress();
                        }

                        return markedCount;
                    }

                    updateProcessedFilesStatus(processedCount, status) {
                        let updatedCount = 0;

                        // العثور على الملفات التي ما زالت في حالة "قيد المعالجة" مرتبة حسب الإضافة
                        const processingFiles = Array.from(this.files.values())
                            .filter(f => f.status === 'processing')
                            .sort((a, b) => a.id - b.id); // ترتيب حسب ID للحصول على ترتيب الإضافة

                        console.log(`🔍 العثور على ${processingFiles.length} ملف في حالة المعالجة, سيتم تحديث ${Math.min(processedCount, processingFiles.length)} ملف`);

                        for (let i = 0; i < Math.min(processedCount, processingFiles.length); i++) {
                            const fileData = processingFiles[i];
                            const oldStatus = fileData.status;
                            fileData.status = status;
                            this.files.set(fileData.id, fileData);
                            this.updateFileStatus(fileData.id, status);
                            updatedCount++;

                            console.log(`📄 ملف "${fileData.file?.name || 'ملف غير محدد'}" تم تحديثه من "${oldStatus}" إلى "${status}"`);
                        }

                        console.log(`✅ تم تحديث ${updatedCount} ملف إلى حالة "${status}" بناءً على النتائج الفعلية`);

                        // تحديث العدادات
                        this.updateFileCounts();
                        this.updateOverallProgress();

                        return updatedCount;
                    }

                    /**
                     * تحديث ملفات مكررة محددة بناءً على أسماء الملفات
                     */
                    updateSpecificDuplicateFiles(duplicateFilesList) {
                        if (!duplicateFilesList || !Array.isArray(duplicateFilesList)) {
                            console.log('⚠️ لا توجد قائمة ملفات مكررة محددة للتحديث');
                            return;
                        }

                        console.log('🎯 تحديث ملفات مكررة محددة:', duplicateFilesList);
                        console.log('🔍 حالة كشف التكرار:', this.duplicateDetectionEnabled ? 'مفعّل' : 'معطّل');

                        duplicateFilesList.forEach(duplicateInfo => {
                            const originalName = duplicateInfo.original_name || duplicateInfo.name;
                            if (originalName) {
                                // البحث عن الملف في القائمة بناءً على الاسم
                                const fileEntry = Array.from(this.files.entries()).find(([id, fileData]) => {
                                    return fileData.file && fileData.file.name === originalName;
                                });

                                if (fileEntry) {
                                    const [fileId, fileData] = fileEntry;
                                    console.log(`🔄 تحديث ملف مكرر محدد: ${originalName} (ID: ${fileId})`);

                                    // إذا كان كشف التكرار معطلاً، قم بإخفاء الملف (تجاهله)
                                    if (!this.duplicateDetectionEnabled) {
                                        console.log(`🚫 كشف التكرار معطّل - سيتم إخفاء الملف: ${originalName}`);

                                        // حذف الملف من files Map
                                        this.files.delete(fileId);

                                        // إخفاء عنصر الملف من الواجهة
                                        const fileElement = document.querySelector(`[data-file-id="${fileId}"]`);
                                        if (fileElement) {
                                            fileElement.style.display = 'none';
                                            // أو حذفه تماماً
                                            fileElement.remove();
                                            console.log(`✅ تم إخفاء/حذف الملف من الواجهة: ${originalName}`);
                                        }
                                    } else {
                                        // إذا كان كشف التكرار مفعلاً، عرض الملف كمكرر
                                        console.log(`⚠️ كشف التكرار مفعّل - سيتم عرض الملف كمكرر: ${originalName}`);

                                        // ⭐ حفظ معلومات الملف المكرر للاستخدام لاحقاً في المقارنة
                                        fileData.status = 'duplicate';
                                        fileData.duplicateInfo = {
                                            original_name: duplicateInfo.original_name,
                                            existing_file_name: duplicateInfo.existing_file_name,
                                            existing_file_path: duplicateInfo.existing_file_path,
                                            target_folder: duplicateInfo.target_folder,
                                            reason: duplicateInfo.reason || 'same_identity_number',
                                            message: duplicateInfo.message || 'الملف مكرر'
                                        };

                                        this.files.set(fileId, fileData);
                                        this.updateFileStatus(fileId, 'duplicate');

                                        console.log(`✅ تم حفظ معلومات الملف المكرر:`, fileData.duplicateInfo);
                                    }
                                } else {
                                    console.log(`❓ لم يتم العثور على الملف المكرر في الواجهة: ${originalName}`);
                                }
                            }
                        });

                        // تحديث العدادات بعد التحديث المحدد
                        this.updateFileCounts();
                    }

                    /**
                     * تحميل جميع ملفات المجلد
                     */
                    downloadFolderFiles(folderId) {
                        const folderSection = document.getElementById(`folder-section-${folderId}`);
                        if (!folderSection) return;

                        const fileElements = folderSection.querySelectorAll('[data-file-id]');
                        const fileIds = Array.from(fileElements).map(el => el.getAttribute('data-file-id'));

                        console.log(`📥 تحميل جميع ملفات المجلد ${folderId}:`, fileIds);

                        fileIds.forEach(fileId => {
                            this.downloadFile(fileId);
                        });
                    }

                    /**
                     * تحديد جميع ملفات المجلد
                     */
                    selectAllFolderFiles(folderId) {
                        const folderSection = document.getElementById(`folder-section-${folderId}`);
                        if (!folderSection) return;

                        const checkboxes = folderSection.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = true;
                        });

                        console.log(`✅ تم تحديد جميع ملفات المجلد ${folderId}`);
                    }

                    /**
                     * حذف جميع ملفات المجلد
                     */
                    deleteFolderFiles(folderId) {
                        if (!confirm('هل أنت متأكد من حذف جميع ملفات هذا المجلد؟')) {
                            return;
                        }

                        const folderSection = document.getElementById(`folder-section-${folderId}`);
                        if (!folderSection) return;

                        const fileElements = folderSection.querySelectorAll('[data-file-id]');
                        const fileIds = Array.from(fileElements).map(el => el.getAttribute('data-file-id'));

                        console.log(`🗑️ حذف جميع ملفات المجلد ${folderId}:`, fileIds);

                        fileIds.forEach(fileId => {
                            this.deleteFile(fileId);
                        });

                        // حذف قسم المجلد بالكامل إذا لم تعد هناك ملفات
                        setTimeout(() => {
                            const remainingFiles = folderSection.querySelectorAll('[data-file-id]');
                            if (remainingFiles.length === 0) {
                                folderSection.remove();
                            }
                        }, 500);
                    }

                    /**
                     * عرض تفاصيل الملف
                     */
                    viewFileDetails(fileId) {
                        const fileData = this.files.get(fileId);
                        if (!fileData) return;

                        const details = {
                            name: fileData.file.name,
                            size: this.formatFileSize(fileData.file.size),
                            type: fileData.type,
                            status: fileData.status,
                            folder: this.getFolderDisplayName(fileData),
                            lastModified: new Date(fileData.file.lastModified).toLocaleString('ar-SA')
                        };

                        console.log(`📋 تفاصيل الملف ${fileId}:`, details);

                        // يمكن إضافة مودال لعرض التفاصيل هنا
                        alert(`تفاصيل الملف:\n\nالاسم: ${details.name}\nالحجم: ${details.size}\nالنوع: ${details.type}\nالحالة: ${details.status}\nالمجلد: ${details.folder}\nآخر تعديل: ${details.lastModified}`);
                    }

                    /**
                     * حذف ملف واحد
                     */
                    deleteFile(fileId) {
                        const fileData = this.files.get(fileId);
                        if (!fileData) return;

                        // حذف الملف من البيانات
                        this.files.delete(fileId);

                        // حذف العنصر من الواجهة
                        const fileElement = document.querySelector(`[data-file-id="${fileId}"]`);
                        if (fileElement) {
                            const folderSection = fileElement.closest('.folder-section');
                            fileElement.remove();

                            // تحديث إحصائيات المجلد
                            if (folderSection) {
                                const folderId = folderSection.id.replace('folder-section-', '');
                                this.updateFolderStats(folderId);

                                // حذف المجلد إذا لم تعد هناك ملفات
                                const remainingFiles = folderSection.querySelectorAll('[data-file-id]');
                                if (remainingFiles.length === 0) {
                                    folderSection.remove();
                                }
                            }
                        }

                        this.updateFileCounts();
                        console.log(`🗑️ تم حذف الملف ${fileId}`);
                    }

                    /**
                     * تحميل ملف واحد
                     */
                    downloadFile(fileId) {
                        const fileData = this.files.get(fileId);
                        if (!fileData) return;

                        // إنشاء رابط تحميل مؤقت
                        const url = URL.createObjectURL(fileData.file);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = fileData.file.name;
                        a.click();
                        URL.revokeObjectURL(url);

                        console.log(`📥 تم تحميل الملف ${fileId}: ${fileData.file.name}`);
                    }

                    /**
                     * تحديث شريط تقدم الرفع المتعدد مع التزامن
                     */
                    updateBatchProgress(current, total, message) {
                        const percentage = Math.round((current / total) * 100);

                        // تحديث شريط التقدم القديم (للتوافق)
                        const progressBar = document.querySelector('.batch-upload-progress');
                        const progressText = document.querySelector('.batch-upload-text');

                        if (progressBar) {
                            progressBar.style.width = `${percentage}%`;
                            progressBar.setAttribute('aria-valuenow', percentage);
                            progressBar.textContent = `${percentage}%`;
                        }

                        if (progressText) {
                            progressText.textContent = message;
                        }

                        // تحديث العداد في النظام الجديد
                        const batchCounter = document.querySelector('.batch-counter');
                        if (batchCounter) {
                            batchCounter.textContent = `${current}/${total} دفعات`;
                        }

                        console.log(`📊 التقدم: ${percentage}% - ${message}`);
                    }

                    /**
                     * إخفاء شريط تقدم الرفع المتعدد
                     */
                    hideBatchUploadProgress() {
                        const progressContainer = document.querySelector('.batch-upload-progress-container');
                        if (progressContainer) {
                            progressContainer.remove();
                        }
                    }

                    /**
                     * إخفاء شريط التقدم الحقيقي
                     */
                    hideRealTimeProgress() {
                        const progressContainer = document.querySelector('.real-time-progress-container');
                        if (progressContainer) {
                            progressContainer.style.transition = 'opacity 0.5s ease';
                            progressContainer.style.opacity = '0';

                            setTimeout(() => {
                                progressContainer.remove();
                            }, 500);
                        }
                    }

                    /**
                     * حذف ملف من القائمة
                     */
                    deleteFile(fileId) {
                        if (confirm('هل تريد حذف هذا الملف من القائمة؟')) {
                            this.files.delete(fileId);

                            // إزالة العرض البصري للملف
                            const fileElement = document.getElementById(`preview_${fileId}`);
                            if (fileElement && fileElement.parentElement) {
                                fileElement.parentElement.remove();
                            }

                            // تحديث العدادات
                            this.updateFileCounts();

                            console.log(`🗑️ تم حذف الملف ${fileId} من القائمة`);
                        }
                    }

                    /**
                     * تحميل ملف
                     */
                    downloadFile(fileId) {
                        const fileData = this.files.get(fileId);
                        if (fileData && fileData.file) {
                            const url = URL.createObjectURL(fileData.file);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = fileData.file.name;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);

                            console.log(`📥 تحميل الملف: ${fileData.file.name}`);
                        }
                    }

                    /**
                     * عرض التنبيه
                     */
                    showAlert(message, type = 'info') {
                        // إنشاء عنصر التنبيه
                        const alertElement = document.createElement('div');
                        alertElement.className = `alert alert-${type} alert-dismissible fade show`;
                        alertElement.style.position = 'fixed';
                        alertElement.style.top = '20px';
                        alertElement.style.right = '20px';
                        alertElement.style.zIndex = '9999';
                        alertElement.style.maxWidth = '400px';

                        alertElement.innerHTML = `
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;

                        document.body.appendChild(alertElement);

                        // إزالة التنبيه تلقائياً بعد 5 ثوان
                        setTimeout(() => {
                            if (alertElement.parentNode) {
                                alertElement.remove();
                            }
                        }, 5000);
                    }

                    /**
                     * تنسيق حجم الملف
                     */
                    formatFileSize(bytes) {
                        if (bytes === 0) return '0 Bytes';
                        const k = 1024;
                        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                        const i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                    }

                    /**
                     * تصفية الملفات
                     */
                    handleFilterChange(e) {
                        const filterValue = e.target.value;
                        console.log('🔍 تطبيق فلتر:', filterValue);

                        // تطبيق الفلتر على الملفات المعروضة
                        const fileElements = document.querySelectorAll('.file-preview');
                        fileElements.forEach(element => {
                            const fileId = element.id.replace('preview_', '');
                            const fileData = this.files.get(fileId);

                            if (filterValue === 'all' || fileData.type === filterValue) {
                                element.parentElement.style.display = 'block';
                            } else {
                                element.parentElement.style.display = 'none';
                            }
                        });
                    }

                    /**
                     * تحديث زر الملفات المكررة
                     */
                    updateDuplicateFilesButton(duplicatesInfo) {
                        const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                        if (duplicateBtn && duplicatesInfo) {
                            duplicateBtn.style.display = 'inline-block';
                            duplicateBtn.innerHTML = `<i class="fas fa-clone me-2"></i>عرض الملفات المكررة (${duplicatesInfo.total_duplicates})`;
                            duplicateBtn.classList.add('btn-warning');
                            duplicateBtn.classList.remove('btn-secondary');
                        }
                    }
                }

                // تفعيل بوابة Excel - يتم التعامل معها في DOMContentLoaded
                document.addEventListener('DOMContentLoaded', function() {
                    const excelGatewayBtn = document.getElementById('excel-gateway-btn');
                    if (excelGatewayBtn) {
                        excelGatewayBtn.addEventListener('click', function() {
                            const modal = new bootstrap.Modal(document.getElementById('excelGatewayModal'));
                            modal.show();
                        });
                    }
                });

                /**
                 * File Gallery Functions - وظائف معرض الصور
                 */

                // جلب وعرض ملفات المجلد مع إعادة المحاولة عند 429
                async function loadFolderFiles(folderName, folderId, retryCount = 0) {
                    const maxRetries = 3;
                    try {
                        console.log(`🔄 جلب ملفات المجلد: ${folderName} (ID: ${folderId})`);

                        // إظهار مؤشر التحميل
                        showFileLoadingIndicator(folderId);

                        const response = await fetch(`/api/gallery/folder/${encodeURIComponent(folderName)}`);

                        // معالجة خطأ 429 - إعادة المحاولة بتأخير تصاعدي
                        if (response.status === 429 && retryCount < maxRetries) {
                            const delay = Math.pow(2, retryCount) * 2000; // 2s, 4s, 8s
                            console.warn(`⏳ طلبات كثيرة (429) للمجلد ${folderName} - إعادة المحاولة بعد ${delay/1000} ثانية...`);
                            await new Promise(resolve => setTimeout(resolve, delay));
                            return loadFolderFiles(folderName, folderId, retryCount + 1);
                        }

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }

                        const data = await response.json();

                        console.log(`📁 نتائج API للمجلد ${folderName}:`, data);

                        if (data.success && data.files && data.files.length > 0) {
                            console.log(`✅ تم العثور على ${data.files.length} ملف في المجلد ${folderName}`);
                            showFilesGallery(folderName, data.files, folderId);
                        } else {
                            console.log(`📂 المجلد ${folderName} فارغ أو لا يحتوي على ملفات مدعومة`);
                            showNoFilesMessage(folderId);
                        }
                    } catch (error) {
                        console.error('خطأ في جلب ملفات المجلد:', error);
                        showErrorMessage(folderId, error.message);
                    }
                }

                // عرض مؤشر التحميل للملفات
                function showFileLoadingIndicator(folderId) {
                    let filesContainer = document.getElementById(`files-container-${folderId}`);
                    if (!filesContainer) {
                        createFilesContainer(folderId);
                        filesContainer = document.getElementById(`files-container-${folderId}`);
                        if (!filesContainer) {
                            console.warn(`⚠️ لم يتم العثور على folder-section-${folderId} - تخطي عرض مؤشر التحميل`);
                            return;
                        }
                    }

                    filesContainer.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                            <p class="mt-2 text-muted">جاري تحميل الملفات...</p>
                        </div>
                    `;
                }

                // عرض معرض الصور
                function showFilesGallery(folderName, files, folderId) {
                    let filesContainer = document.getElementById(`files-container-${folderId}`);
                    if (!filesContainer) {
                        // إنشاء منطقة عرض الملفات إذا لم تكن موجودة
                        createFilesContainer(folderId);
                        filesContainer = document.getElementById(`files-container-${folderId}`);
                        if (!filesContainer) {
                            console.warn(`⚠️ لم يتم العثور على folder-section-${folderId} - تخطي عرض معرض الصور`);
                            return;
                        }
                    }

                    let filesHtml = `
                        <div class="files-gallery mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="fas fa-images me-2 text-primary"></i>الملفات المرفوعة (${files.length})</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshFolderFiles('${folderName}', '${folderId}')" title="تحديث الملفات">
                                    <i class="fas fa-sync-alt"></i> تحديث
                                </button>
                            </div>
                            <div class="row g-2">
                    `;

                    files.forEach(file => {
                        const fileIcon = getFileIcon(file.extension);
                        const isImage = file.is_image;
                        const safeFileName = file.name.replace(/'/g, "\\'").replace(/"/g, '\\"');
                        const safeFolderName = folderName.replace(/'/g, "\\'").replace(/"/g, '\\"');

                        filesHtml += `
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="file-card card h-100 position-relative">
                                    <!-- قائمة منسدلة للخيارات -->
                                    <div class="dropdown position-absolute" style="top: 5px; right: 5px; z-index: 10;">
                                        <button class="btn btn-sm btn-light dropdown-toggle" type="button"
                                                id="actionsMenu_file_${file.name.replace(/[^a-zA-Z0-9]/g, '_')}_${folderId}"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="actionsMenu_file_${file.name.replace(/[^a-zA-Z0-9]/g, '_')}_${folderId}">
                                            ${isImage ?
                                                `<li><a class="dropdown-item" href="#" onclick="previewFile('${file.url}', '${safeFileName}', true); return false;">
                                                    <i class="fas fa-eye me-2"></i>معاينة الصورة
                                                 </a></li>` :
                                                `<li><a class="dropdown-item" href="#" onclick="window.open('${file.url}', '_blank'); return false;">
                                                    <i class="fas fa-external-link-alt me-2"></i>فتح الملف
                                                 </a></li>`
                                            }
                                            <li><a class="dropdown-item" href="#" onclick="downloadFile('${safeFolderName}', '${safeFileName}'); return false;">
                                                <i class="fas fa-download me-2"></i>تحميل
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteFile('${safeFolderName}', '${safeFileName}', '${folderId}'); return false;">
                                                <i class="fas fa-trash me-2"></i>حذف
                                            </a></li>
                                        </ul>
                                    </div>

                                    <div class="file-preview" style="height: 150px; overflow: hidden;">
                                        ${isImage ?
                                            `<img src="${file.url}" alt="${file.name}"
                                                 class="img-fluid w-100 h-100"
                                                 style="object-fit: cover; cursor: pointer;"
                                                 onclick="previewFile('${file.url}', '${safeFileName}', true)">` :
                                            `<div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                                <i class="${fileIcon} fa-3x text-secondary"></i>
                                             </div>`
                                        }
                                    </div>
                                    <div class="card-body p-2">
                                        <h6 class="card-title text-truncate mb-1" style="font-size: 0.8rem;" title="${file.name}">
                                            ${file.name}
                                        </h6>
                                        <p class="card-text text-muted mb-2" style="font-size: 0.7rem;">
                                            ${file.size_formatted}
                                        </p>
                                        <div class="btn-group w-100" role="group">
                                            ${isImage ?
                                                `<button class="btn btn-outline-primary btn-sm" onclick="previewFile('${file.url}', '${safeFileName}', true)" title="معاينة">
                                                    <i class="fas fa-eye"></i>
                                                 </button>` :
                                                `<button class="btn btn-outline-info btn-sm" onclick="window.open('${file.url}', '_blank')" title="فتح">
                                                    <i class="fas fa-external-link-alt"></i>
                                                 </button>`
                                            }
                                            <button class="btn btn-outline-success btn-sm" onclick="downloadFile('${safeFolderName}', '${safeFileName}')" title="تنزيل">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteFile('${safeFolderName}', '${safeFileName}', '${folderId}')" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    filesHtml += `
                            </div>
                        </div>
                    `;

                    filesContainer.innerHTML = filesHtml;

                    // تفعيل Bootstrap dropdowns بعد إنشاء HTML
                    setTimeout(() => {
                        const dropdownElements = filesContainer.querySelectorAll('[data-bs-toggle="dropdown"]');
                        dropdownElements.forEach(element => {
                            if (!element.getAttribute('data-bs-initialized')) {
                                new bootstrap.Dropdown(element);
                                element.setAttribute('data-bs-initialized', 'true');
                            }
                        });
                    }, 100);
                }

                // إنشاء منطقة عرض الملفات
                function createFilesContainer(folderId) {
                    const folderSection = document.getElementById(`folder-section-${folderId}`);
                    if (folderSection) {
                        const filesContainer = document.createElement('div');
                        filesContainer.id = `files-container-${folderId}`;
                        filesContainer.className = 'files-container mt-3';
                        folderSection.appendChild(filesContainer);
                    }
                }

                // عرض رسالة عدم وجود ملفات
                function showNoFilesMessage(folderId) {
                    let filesContainer = document.getElementById(`files-container-${folderId}`);
                    if (!filesContainer) {
                        createFilesContainer(folderId);
                        filesContainer = document.getElementById(`files-container-${folderId}`);
                        if (!filesContainer) {
                            console.warn(`⚠️ لم يتم العثور على folder-section-${folderId} - تخطي عرض رسالة عدم الملفات`);
                            return;
                        }
                    }

                    filesContainer.innerHTML = `
                        <div class="no-files-message text-center py-4">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">لا توجد ملفات في هذا المجلد</p>
                        </div>
                    `;
                }

                // عرض رسالة خطأ
                function showErrorMessage(folderId, errorMessage) {
                    let filesContainer = document.getElementById(`files-container-${folderId}`);
                    if (!filesContainer) {
                        createFilesContainer(folderId);
                        filesContainer = document.getElementById(`files-container-${folderId}`);
                        if (!filesContainer) {
                            console.warn(`⚠️ لم يتم العثور على folder-section-${folderId} - تخطي عرض رسالة الخطأ`);
                            return;
                        }
                    }

                    filesContainer.innerHTML = `
                        <div class="error-message text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <p class="text-muted">خطأ في تحميل الملفات</p>
                            <small class="text-danger">${errorMessage}</small>
                            <br>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadFolderFiles('${folderId.replace('folder-section-', '')}', '${folderId}')">
                                <i class="fas fa-retry"></i> إعادة المحاولة
                            </button>
                        </div>
                    `;
                }

                // معاينة الملف (للصور)
                function previewFile(fileUrl, fileName, isImage) {
                    if (isImage) {
                        const modalHtml = `
                            <div class="modal fade" id="filePreviewModal" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-image me-2"></i>معاينة الصورة
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="${fileUrl}" alt="${fileName}" class="img-fluid" style="max-height: 70vh;">
                                            <p class="mt-3 text-muted">${fileName}</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                            <a href="${fileUrl}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-external-link-alt me-2"></i>فتح في علامة تبويب جديدة
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                        // إزالة المودال السابق إن وجد
                        const existingModal = document.getElementById('filePreviewModal');
                        if (existingModal) {
                            existingModal.remove();
                        }

                        // إضافة المودال الجديد
                        document.body.insertAdjacentHTML('beforeend', modalHtml);

                        // عرض المودال
                        const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
                        modal.show();
                    }
                }

                // تنزيل الملف
                async function downloadFile(folderName, fileName) {
                    try {
                        const url = `/api/gallery/download/${encodeURIComponent(folderName)}/${encodeURIComponent(fileName)}`;

                        // إنشاء رابط وهمي للتنزيل
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = fileName;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        showAlert('تم بدء تنزيل الملف', 'success');
                    } catch (error) {
                        console.error('خطأ في تنزيل الملف:', error);
                        showAlert('حدث خطأ في تنزيل الملف', 'danger');
                    }
                }

                // حذف الملف
                async function deleteFile(folderName, fileName, folderId) {
                    if (!confirm(`هل أنت متأكد من حذف الملف: ${fileName}؟`)) {
                        return;
                    }

                    try {
                        const response = await fetch(`/api/gallery/file/${encodeURIComponent(folderName)}/${encodeURIComponent(fileName)}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            showAlert('تم حذف الملف بنجاح', 'success');
                            // إعادة تحميل ملفات المجلد
                            loadFolderFiles(folderName, folderId);
                        } else {
                            showAlert(data.message || 'فشل في حذف الملف', 'danger');
                        }
                    } catch (error) {
                        console.error('خطأ في حذف الملف:', error);
                        showAlert('حدث خطأ في حذف الملف', 'danger');
                    }
                }

                // تحديث ملفات المجلد
                function refreshFolderFiles(folderName, folderId) {
                    loadFolderFiles(folderName, folderId);
                }

                // تحديث جميع المجلدات المعروضة
                function refreshAllFolderFiles() {
                    console.log('🔄 بدء تحديث جميع المجلدات المعروضة...');

                    // البحث عن جميع المجلدات المعروضة وتحديث ملفاتها
                    const folderSections = document.querySelectorAll('[id^="folder-section-"]');

                    if (folderSections.length === 0) {
                        console.log('⚠️ لا توجد مجلدات معروضة للتحديث');
                        return;
                    }

                    console.log(`📁 تم العثور على ${folderSections.length} مجلد للتحديث`);

                    folderSections.forEach((section, index) => {
                        const folderId = section.id.replace('folder-section-', '');
                        const folderNameElement = section.querySelector('.folder-name');

                        if (folderNameElement) {
                            const folderName = folderNameElement.textContent.trim();
                            console.log(`🔄 تحديث ملفات المجلد: ${folderName} (ID: ${folderId})`);

                            // تأخير تدريجي لتجنب الحمولة الزائدة
                            setTimeout(() => {
                                loadFolderFiles(folderName, folderId);
                            }, index * 200); // تأخير 200ms بين كل مجلد
                        }
                    });

                    console.log('✅ تم بدء تحديث جميع المجلدات');
                }

                // الحصول على أيقونة الملف
                function getFileIcon(extension) {
                    const iconMap = {
                        'pdf': 'fas fa-file-pdf text-danger',
                        'doc': 'fas fa-file-word text-primary',
                        'docx': 'fas fa-file-word text-primary',
                        'xls': 'fas fa-file-excel text-success',
                        'xlsx': 'fas fa-file-excel text-success',
                        'txt': 'fas fa-file-alt text-secondary',
                        'zip': 'fas fa-file-archive text-warning',
                        'rar': 'fas fa-file-archive text-warning'
                    };

                    return iconMap[extension] || 'fas fa-file text-secondary';
                }

                // عرض رسالة تنبيه
                function showAlert(message, type = 'info') {
                    const alertHtml = `
                        <div class="alert alert-${type} alert-dismissible fade show position-fixed"
                             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;

                    document.body.insertAdjacentHTML('beforeend', alertHtml);

                    // إزالة التنبيه تلقائياً بعد 5 ثوان
                    setTimeout(() => {
                        const alerts = document.querySelectorAll('.alert');
                        alerts.forEach(alert => {
                            if (alert.textContent.includes(message)) {
                                alert.remove();
                            }
                        });
                    }, 5000);
                }

                /**
                 * دوال معاينة الملفات المحلية قبل الرفع
                 */

                // معاينة ملف محلي (للصور)
                function previewLocalFile(fileId, fileName, isImage) {
                    const fileData = app.files.get(fileId);
                    if (!fileData || !isImage) return;

                    const previewUrl = URL.createObjectURL(fileData.file);

                    const modalHtml = `
                        <div class="modal fade" id="localFilePreviewModal" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-image me-2"></i>معاينة الصورة (قبل الرفع)
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <img src="${previewUrl}" alt="${fileName}" class="img-fluid" style="max-height: 70vh;">
                                        <div class="mt-3">
                                            <h6>${fileName}</h6>
                                            <p class="text-muted">الحجم: ${app.formatFileSize(fileData.file.size)}</p>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                هذه معاينة للملف قبل رفعه إلى الخادم
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                        <button type="button" class="btn btn-danger" onclick="removeFileFromQueue('${fileId}'); bootstrap.Modal.getInstance(document.getElementById('localFilePreviewModal')).hide();">
                                            <i class="fas fa-trash me-2"></i>إزالة من القائمة
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // إزالة المودال السابق إن وجد
                    const existingModal = document.getElementById('localFilePreviewModal');
                    if (existingModal) {
                        existingModal.remove();
                    }

                    // إضافة المودال الجديد
                    document.body.insertAdjacentHTML('beforeend', modalHtml);

                    // عرض المودال
                    const modal = new bootstrap.Modal(document.getElementById('localFilePreviewModal'));
                    modal.show();

                    // تنظيف URL بعد إغلاق المودال
                    modal._element.addEventListener('hidden.bs.modal', () => {
                        URL.revokeObjectURL(previewUrl);
                        modal._element.remove();
                    });
                }

                // فتح ملف محلي (للملفات غير الصور)
                function openLocalFile(fileId) {
                    const fileData = app.files.get(fileId);
                    if (!fileData) return;

                    const fileUrl = URL.createObjectURL(fileData.file);

                    // فتح الملف في نافذة جديدة
                    const newWindow = window.open(fileUrl, '_blank');

                    // تنظيف URL بعد فترة
                    setTimeout(() => {
                        URL.revokeObjectURL(fileUrl);
                    }, 10000);
                }

                // إزالة ملف من قائمة الانتظار
                function removeFileFromQueue(fileId) {
                    if (!confirm('هل أنت متأكد من إزالة هذا الملف من قائمة الانتظار؟')) {
                        return;
                    }

                    const fileData = app.files.get(fileId);
                    if (fileData) {
                        // تنظيف URL إذا كان موجوداً
                        if (fileData.previewUrl) {
                            URL.revokeObjectURL(fileData.previewUrl);
                        }

                        // إزالة الملف من الخريطة
                        app.files.delete(fileId);

                        // إزالة العنصر من الواجهة
                        const fileElement = document.querySelector(`[data-file-id="${fileId}"]`);
                        if (fileElement) {
                            fileElement.remove();
                        }

                        // تحديث العدادات
                        app.updateFileCounts();

                        console.log(`🗑️ تم إزالة الملف ${fileId} من قائمة الانتظار`);
                        showAlert('تم إزالة الملف من قائمة الانتظار', 'success');
                    }
                }
            </script>
@endpush
