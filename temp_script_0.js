// ========================================
        // المتغيرات
        // ========================================
        let allFiles = [];
        let sponsors = [];
        let currentPageNum = 1;
        const FILES_PAGE_SIZE = 10; // 10 ملفات لكل صفحة
        let autoUploadEnabled = true;

        // ========================================
        // تهيئة الصفحة
        // ========================================
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                await SyncService.init();

                if (!SyncService.isAuthenticated()) {
                    window.location.href = 'index.html';
                    return;
                }

                sponsors = await SyncService.getLocalSponsors();
                await loadFiles();

                // تهيئة نظام الرفع التلقائي
                initAutoUploadUI();

            } catch (error) {
                console.error('Init error:', error);
                showStatus('error', 'حدث خطأ في تهيئة الصفحة');
            }
        });

        // ========================================
        // تهيئة واجهة الرفع التلقائي
        // ========================================
        function initAutoUploadUI() {
            // تهيئة نظام الرفع في SyncService
            if (typeof SyncService.initAutoUpload === 'function') {
                SyncService.initAutoUpload();
            }

            // تحديث حالة الشبكة
            updateNetworkStatus(SyncService.isOnline());

            // إضافة مستمع لأحداث الرفع التلقائي
            SyncService.addAutoUploadListener(handleAutoUploadEvent);

            // تحديث حجم الطابور
            updateQueueUI();
        }

        // ========================================
        // معالجة أحداث الرفع التلقائي
        // ========================================
        function handleAutoUploadEvent(event, data) {
            console.log('Auto upload event:', event, data);

            switch (event) {
                case 'online':
                    updateNetworkStatus(true);
                    break;

                case 'offline':
                    updateNetworkStatus(false);
                    break;

                case 'file_queued':
                    updateQueueUI();
                    addToQueueList(data.file);
                    break;

                case 'waiting_network':
                    showStatus('warning', 'الملف في الطابور - سيتم رفعه عند عودة الاتصال');
                    break;

                case 'upload_started':
                    setUploadingStatus(true, data.totalFiles);
                    showProgress(true);
                    break;

                case 'file_uploading':
                    updateProgress(data.progress, `جاري الرفع... ${data.uploaded + 1}/${data.total}`);
                    updateQueueItemStatus(data.file.id, 'uploading');
                    break;

                case 'file_uploaded':
                    updateProgress(data.progress, `تم رفع ${data.uploaded}/${data.total}`);
                    removeFromQueueList(data.file.id);
                    loadFiles(); // تحديث القائمة
                    break;

                case 'file_failed':
                    removeFromQueueList(data.file.id);
                    break;

                case 'upload_completed':
                    setUploadingStatus(false);
                    setTimeout(() => showProgress(false), 2000);
                    updateQueueUI();
                    loadFiles();

                    if (data.failed > 0) {
                        showStatus('warning', `تم رفع ${data.uploaded} ملف، فشل ${data.failed}`);
                    } else if (data.uploaded > 0) {
                        showStatus('success', `تم رفع ${data.uploaded} ملف بنجاح تلقائياً`);
                    }
                    break;
            }
        }

        // ========================================
        // تحديث حالة الشبكة في الواجهة
        // ========================================
        function updateNetworkStatus(isOnline) {
            const statusDiv = document.getElementById('autoUploadStatus');
            const icon = document.getElementById('networkIcon');
            const title = document.getElementById('networkTitle');
            const subtitle = document.getElementById('networkSubtitle');

            if (isOnline) {
                statusDiv.className = 'auto-upload-status online';
                icon.textContent = 'wifi';
                title.textContent = 'متصل بالإنترنت';
                subtitle.textContent = autoUploadEnabled ? 'الرفع التلقائي مفعل' : 'الرفع التلقائي معطل';
            } else {
                statusDiv.className = 'auto-upload-status offline';
                icon.textContent = 'wifi_off';
                title.textContent = 'غير متصل بالإنترنت';
                subtitle.textContent = 'الملفات ستُرفع عند عودة الاتصال';
            }
        }

        // ========================================
        // تحديث حالة الرفع الجاري
        // ========================================
        function setUploadingStatus(isUploading, totalFiles = 0) {
            const statusDiv = document.getElementById('autoUploadStatus');
            const icon = document.getElementById('networkIcon');
            const title = document.getElementById('networkTitle');
            const subtitle = document.getElementById('networkSubtitle');

            if (isUploading) {
                statusDiv.className = 'auto-upload-status uploading';
                icon.textContent = 'cloud_upload';
                title.textContent = 'جاري الرفع التلقائي';
                subtitle.textContent = `يتم رفع ${totalFiles} ملف...`;
            } else {
                updateNetworkStatus(SyncService.isOnline());
            }
        }

        // ========================================
        // تبديل حالة الرفع التلقائي
        // ========================================
        function toggleAutoUpload() {
            autoUploadEnabled = !autoUploadEnabled;

            const btn = document.getElementById('autoUploadToggle');
            btn.textContent = autoUploadEnabled ? 'مفعل' : 'معطل';
            btn.className = 'toggle-btn ' + (autoUploadEnabled ? 'enabled' : 'disabled');

            SyncService.setAutoUploadEnabled(autoUploadEnabled);
            updateNetworkStatus(SyncService.isOnline());

            showStatus('info', autoUploadEnabled ? 'تم تفعيل الرفع التلقائي' : 'تم تعطيل الرفع التلقائي');
        }

        // ========================================
        // تحديث واجهة الطابور
        // ========================================
        function updateQueueUI() {
            const queueSize = SyncService.getAutoUploadQueueSize ? SyncService.getAutoUploadQueueSize() : 0;
            const section = document.getElementById('uploadQueueSection');
            const badge = document.getElementById('queueCount');

            badge.textContent = queueSize;
            section.classList.toggle('active', queueSize > 0);
        }

        // ========================================
        // إضافة ملف لقائمة الطابور
        // ========================================
        function addToQueueList(file) {
            const list = document.getElementById('queueList');
            const existingItem = document.getElementById('queue-item-' + file.id);

            if (existingItem) return;

            const icon = file.file_type?.includes('image') ? 'image' : 'description';

            const itemHtml = `
                <div class="queue-item" id="queue-item-${file.id}">
                    <div class="item-icon">
                        <span class="material-icons">${icon}</span>
                    </div>
                    <div class="item-info">
                        <div class="item-name">${file.file_name || 'ملف'}</div>
                        <div class="item-status">في الانتظار...</div>
                    </div>
                </div>
            `;

            list.insertAdjacentHTML('beforeend', itemHtml);
            updateQueueUI();
        }

        // ========================================
        // تحديث حالة عنصر في الطابور
        // ========================================
        function updateQueueItemStatus(fileId, status) {
            const item = document.getElementById('queue-item-' + fileId);
            if (!item) return;

            if (status === 'uploading') {
                item.classList.add('uploading');
                item.querySelector('.item-status').textContent = 'جاري الرفع...';
            }
        }

        // ========================================
        // إزالة ملف من قائمة الطابور
        // ========================================
        function removeFromQueueList(fileId) {
            const item = document.getElementById('queue-item-' + fileId);
            if (item) {
                item.remove();
            }
            updateQueueUI();
        }

        // ========================================
        // تحميل الملفات مع pagination بسيط
        // ========================================
        async function loadFiles() {
            try {
                // جلب كل الملفات
                allFiles = await SyncService.dbGetAll('files');
                const pending = allFiles.filter(f => !f.uploaded);
                const uploaded = allFiles.filter(f => f.uploaded);

                document.getElementById('statTotal').textContent = allFiles.length;
                document.getElementById('statPending').textContent = pending.length;
                document.getElementById('statUploaded').textContent = uploaded.length;

                // عرض الملفات مع pagination
                const container = document.getElementById('filesList');

                if (allFiles.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <span class="material-icons">cloud_off</span>
                            <p>لا توجد ملفات محفوظة</p>
                            <p style="font-size: 12px; margin-top: 5px;">استخدم صفحة التصوير لإلتقاط صور جديدة</p>
                        </div>
                    `;
                    document.getElementById('filesPaginationContainer').innerHTML = '';
                    return;
                }

                // عرض الصفحة الأولى
                currentPageNum = 1;
                showFilesPage(1);

            } catch (error) {
                console.error('Load files error:', error);
                showStatus('error', 'فشل في تحميل الملفات');
            }
        }

        // ========================================
        // عرض صفحة محددة من الملفات
        // ========================================
        async function showFilesPage(pageNumber) {
            currentPageNum = pageNumber;
            const start = (pageNumber - 1) * FILES_PAGE_SIZE;
            const end = start + FILES_PAGE_SIZE;
            const pageFiles = allFiles.slice(start, end);

            const sponsorships = await SyncService.dbGetAll('sponsorships');

            // عرض الملفات
            renderFilesPage(pageFiles, sponsorships);

            // عرض pagination
            renderFilesPagination();
        }

        // ========================================
        // عرض أزرار pagination
        // ========================================
        function renderFilesPagination() {
            const container = document.getElementById('filesPaginationContainer');
            const totalPages = Math.ceil(allFiles.length / FILES_PAGE_SIZE);

            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            const start = (currentPageNum - 1) * FILES_PAGE_SIZE + 1;
            const end = Math.min(currentPageNum * FILES_PAGE_SIZE, allFiles.length);

            let html = '<div class="pagination-container">';
            html += `<div class="pagination-info">عرض ${start} - ${end} من أصل ${allFiles.length} ملف</div>`;
            html += '<div class="pagination-controls">';

            // زر السابق
            html += `<button class="pagination-btn prev" onclick="showFilesPage(${currentPageNum - 1})" ${currentPageNum === 1 ? 'disabled' : ''}>السابق</button>`;

            // أرقام الصفحات
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPageNum - 1 && i <= currentPageNum + 1)) {
                    html += `<button class="pagination-btn ${i === currentPageNum ? 'active' : ''}" onclick="showFilesPage(${i})" ${i === currentPageNum ? 'disabled' : ''}>${i}</button>`;
                } else if (i === currentPageNum - 2 || i === currentPageNum + 2) {
                    html += '<span class="pagination-separator">...</span>';
                }
            }

            // زر التالي
            html += `<button class="pagination-btn next" onclick="showFilesPage(${currentPageNum + 1})" ${currentPageNum === totalPages ? 'disabled' : ''}>التالي</button>`;

            html += '</div></div>';
            container.innerHTML = html;
        }

        // ========================================
        // عرض صفحة الملفات
        // ========================================
        function renderFilesPage(files, sponsorships) {
            const container = document.getElementById('filesList');

            container.innerHTML = files.map(file => {
                // البحث عن الكفالة المرتبطة
                const sponsorship = sponsorships.find(s => s.id === file.sponsorship_id);
                const sponsorName = sponsorship ?
                    (sponsors.find(sp => sp.id === sponsorship.sponsor_id)?.name || 'غير معروف') : 'غير معروف';
                const orphanName = sponsorship?.orphan_name || 'غير معروف';

                const statusClass = file.uploaded ? 'uploaded' : 'pending';
                const statusText = file.uploaded ? 'تم الرفع' : 'معلق';
                const icon = file.file_type?.includes('image') ? 'image' : 'description';

                return `
                    <div class="file-item">
                        <div class="file-icon">
                            <span class="material-icons">${icon}</span>
                        </div>
                        <div class="file-info">
                            <div class="file-name">${file.file_name || 'ملف'}</div>
                            <div class="file-meta">${sponsorName} / ${orphanName}</div>
                        </div>
                        <span class="file-status ${statusClass}">${statusText}</span>
                    </div>
                `;
            }).join('');
        }

        // ========================================
        // رفع إلى Google Drive
        // ملاحظة هامة: هذه الدالة تعمل على allFiles الكامل
        // وليس على البيانات المعروضة في الصفحة فقط
        // لذا لن تتأثر بـ pagination
        // ========================================
        async function uploadToGoogleDrive() {
            // جلب جميع الملفات المعلقة من allFiles (وليس من الصفحة الحالية)
            const pending = allFiles.filter(f => !f.uploaded);

            if (pending.length === 0) {
                showStatus('warning', 'لا توجد ملفات معلقة للرفع');
                return;
            }

            const btn = document.getElementById('uploadBtn');
            btn.disabled = true;

            showProgress(true);
            updateProgress(0, `جاري الرفع... 0/${pending.length}`);

            // بدء العمل في الخلفية
            await startUploadBackgroundTask();

            // إرسال إشعار ببدء الرفع
            const notificationId = await SyncService.sendNotification(
                'رفع الملفات',
                'جاري رفع الملفات إلى Google Drive...',
                0,
                true // force notification
            );

            try {
                const sponsorships = await SyncService.dbGetAll('sponsorships');
                let uploaded = 0;
                let failed = 0;

                for (let i = 0; i < pending.length; i++) {
                    const file = pending[i];
                    const progress = Math.round(((i + 1) / pending.length) * 100);
                    updateProgress(progress, `جاري الرفع... ${i + 1}/${pending.length}`);

                    // تحديث الإشعار بالتقدم
                    if (notificationId) {
                        await SyncService.sendNotification(
                            'رفع الملفات',
                            `جاري رفع ${i + 1} من ${pending.length}`,
                            progress,
                            true
                        );
                    }

                    try {
                        // البحث عن الكفالة المرتبطة
                        const sponsorship = sponsorships.find(s => s.id === file.sponsorship_id);

                        if (!sponsorship) {
                            throw new Error('لم يتم العثور على الكفالة');
                        }

                        const sponsor = sponsors.find(sp => sp.id === sponsorship.sponsor_id);
                        const sponsorName = sponsor?.name || 'غير معروف';
                        const orphanName = sponsorship.orphan_name || 'غير معروف';

                        // إنشاء مسار المجلد
                        const folderPath = `alhayah/${sponsorName}/${orphanName}`;

                        // رفع الملف (سيتم تنفيذه عبر الخادم)
                        const result = await SyncService.request('/mobile/upload-file', {
                            method: 'POST',
                            body: JSON.stringify({
                                file_name: file.file_name,
                                file_type: file.file_type,
                                file_data: file.file_data,
                                folder_path: folderPath,
                                sponsorship_id: file.sponsorship_id
                            })
                        });

                        if (result.success) {
                            // تحديث حالة الملف
                            file.uploaded = true;
                            file.google_drive_id = result.file_id;
                            file.uploaded_at = new Date().toISOString();
                            await SyncService.dbPut('files', file);
                            uploaded++;
                        } else {
                            throw new Error(result.message);
                        }

                    } catch (error) {
                        console.error('Upload file error:', error);
                        failed++;
                    }
                }

                updateProgress(100, 'تم الرفع!');

                // إرسال إشعار بالإنتهاء
                if (notificationId) {
                    await SyncService.cancelNotification(notificationId);
                    await SyncService.sendNotification(
                        'اكتملت عملية الرفع',
                        failed > 0 ? `تم رفع ${uploaded} ملف، فشل ${failed}` : `تم رفع ${uploaded} ملف بنجاح`,
                        100,
                        true
                    );
                }

                if (failed > 0) {
                    showStatus('warning', `تم رفع ${uploaded} ملف، فشل ${failed}`);
                } else {
                    showStatus('success', `تم رفع ${uploaded} ملف بنجاح`);
                }

                // إعادة تحميل القائمة
                await loadFiles();

            } catch (error) {
                console.error('Upload error:', error);
                showStatus('error', 'فشل الرفع: ' + error.message);

                // إرسال إشعار بالفشل
                if (notificationId) {
                    await SyncService.cancelNotification(notificationId);
                    await SyncService.sendNotification(
                        'فشل الرفع',
                        'حدث خطأ أثناء رفع الملفات: ' + error.message,
                        null,
                        true
                    );
                }
            } finally {
                btn.disabled = false;
                setTimeout(() => showProgress(false), 2000);

                // إنهاء العمل في الخلفية
                await finishUploadBackgroundTask();
            }
        }

        // ========================================
        // دعم العمل في الخلفية
        // ========================================
        let uploadBackgroundTaskId = null;

        async function startUploadBackgroundTask() {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundTask) {
                try {
                    const BackgroundTask = window.Capacitor.Plugins.BackgroundTask;
                    uploadBackgroundTaskId = await BackgroundTask.beforeExit(async () => {
                        console.log('Upload background task started');
                    });
                    console.log('Upload background task ID:', uploadBackgroundTaskId);
                } catch (error) {
                    console.log('Failed to start upload background task:', error);
                }
            }
        }

        async function finishUploadBackgroundTask() {
            if (uploadBackgroundTaskId && window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundTask) {
                try {
                    const BackgroundTask = window.Capacitor.Plugins.BackgroundTask;
                    await BackgroundTask.finish({ taskId: uploadBackgroundTaskId });
                    uploadBackgroundTaskId = null;
                    console.log('Upload background task finished');
                } catch (error) {
                    console.log('Failed to finish upload background task:', error);
                }
            }
        }

        // ========================================
        // دوال مساعدة
        // ========================================
        function showStatus(type, message) {
            const el = document.getElementById('statusMessage');
            el.className = 'status-message ' + type;
            el.textContent = message;
        }

        function showProgress(show) {
            document.getElementById('progressSection').classList.toggle('active', show);
        }

        function updateProgress(percent, text) {
            document.getElementById('progressFill').style.width = percent + '%';
            document.getElementById('progressText').textContent = text;
        }

        function goBack() {
            window.location.href = 'index.html';
        }

        // ========================================
        // معالجة زر الرجوع
        // ========================================
        document.addEventListener('backbutton', function(e) {
            e.preventDefault();
            goBack();
        }, false);

        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.App) {
            window.Capacitor.Plugins.App.addListener('backButton', goBack);
        }
