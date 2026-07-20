// حل رفع المجلدات على دفعات - JavaScript
// هذا الحل يقسم الملفات إلى مجموعات صغيرة لتجنب حدود PHP

class BatchFileUploader {
    constructor() {
        this.batchSize = 10; // عدد الملفات في كل دفعة
        this.maxFileSize = 1.5 * 1024 * 1024; // 1.5 ميجابايت لكل ملف
        this.maxBatchSize = 6 * 1024 * 1024; // 6 ميجابايت لكل دفعة
    }

    // تقسيم الملفات إلى دفعات
    createBatches(files) {
        const batches = [];
        let currentBatch = [];
        let currentBatchSize = 0;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];

            // فحص حجم الملف
            if (file.size > this.maxFileSize) {
                console.warn(`⚠️ ملف كبير جداً: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
                continue;
            }

            // إضافة الملف للدفعة الحالية إذا كان مناسب
            if (currentBatch.length < this.batchSize &&
                currentBatchSize + file.size <= this.maxBatchSize) {
                currentBatch.push(file);
                currentBatchSize += file.size;
            } else {
                // إنهاء الدفعة الحالية وبدء دفعة جديدة
                if (currentBatch.length > 0) {
                    batches.push(currentBatch);
                }
                currentBatch = [file];
                currentBatchSize = file.size;
            }
        }

        // إضافة الدفعة الأخيرة
        if (currentBatch.length > 0) {
            batches.push(currentBatch);
        }

        return batches;
    }

    // رفع دفعة واحدة
    async uploadBatch(files, batchIndex, totalBatches, options = {}) {
        console.log(`📦 رفع الدفعة ${batchIndex + 1}/${totalBatches} (${files.length} ملفات)`);

        const formData = new FormData();

        // إضافة الملفات
        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
            formData.append(`paths[${index}]`, file.webkitRelativePath || file.name);
        });

        // إضافة معلومات الدفعة
        formData.append('upload_type', 'batch_folder');
        formData.append('batch_index', batchIndex);
        formData.append('total_batches', totalBatches);
        formData.append('is_final_batch', batchIndex === totalBatches - 1);

        // إضافة الخيارات
        if (options.compress_images) formData.append('compress_images', options.compress_images);
        if (options.auto_organize) formData.append('auto_organize', options.auto_organize);
        if (options.cloud_sync) formData.append('cloud_sync', options.cloud_sync);

        // إضافة ملف Excel فقط في الدفعة الأولى
        if (batchIndex === 0 && options.excelFile) {
            formData.append('excel_file', options.excelFile);
            formData.append('enable_excel_import', options.enable_excel_import);
            formData.append('target_table', options.target_table);
        }

        try {
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.API_URL) ? window.APP_CONFIG.API_URL.replace(/\/api\/?$/, '') : '';
            const response = await fetch(baseUrl + '/admin/file/process-bulk-folder-upload-batch', {
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

            const result = await response.json();

            if (result.success) {
                console.log(`✅ نجح رفع الدفعة ${batchIndex + 1}:`, result);
                return result;
            } else {
                throw new Error(result.message || 'فشل في رفع الدفعة');
            }

        } catch (error) {
            console.error(`❌ خطأ في رفع الدفعة ${batchIndex + 1}:`, error);
            throw error;
        }
    }

    // رفع جميع الدفعات
    async uploadAllBatches(files, options = {}) {
        const batches = this.createBatches(files);
        console.log(`🚀 بدء رفع ${files.length} ملف في ${batches.length} دفعة`);

        const results = [];
        let totalSuccess = 0;
        let totalErrors = 0;
        let totalDuplicates = 0;

        for (let i = 0; i < batches.length; i++) {
            try {
                // عرض تقدم العملية
                this.updateProgress(i, batches.length, `رفع الدفعة ${i + 1}/${batches.length}...`);

                const result = await this.uploadBatch(batches[i], i, batches.length, options);
                results.push(result);

                // تجميع الإحصائيات
                if (result.statistics) {
                    totalSuccess += result.statistics.files_saved || 0;
                    totalErrors += result.statistics.errors_count || 0;
                    totalDuplicates += result.statistics.duplicates_detected || 0;
                }

                // انتظار قصير بين الدفعات لتجنب إرهاق الخادم
                if (i < batches.length - 1) {
                    await this.delay(500); // 500ms
                }

            } catch (error) {
                console.error(`❌ فشل في رفع الدفعة ${i + 1}:`, error);
                results.push({
                    success: false,
                    error: error.message,
                    batch_index: i
                });
                totalErrors++;
            }
        }

        // عرض النتائج النهائية
        this.updateProgress(batches.length, batches.length, 'تم الانتهاء!');

        const summary = {
            total_files: files.length,
            total_batches: batches.length,
            successful_batches: results.filter(r => r.success).length,
            failed_batches: results.filter(r => !r.success).length,
            total_success: totalSuccess,
            total_errors: totalErrors,
            total_duplicates: totalDuplicates
        };

        console.log('📊 ملخص العملية النهائي:', summary);

        return {
            success: summary.failed_batches === 0,
            summary: summary,
            results: results
        };
    }

    // تحديث شريط التقدم
    updateProgress(current, total, message) {
        const percentage = Math.round((current / total) * 100);

        // تحديث شريط التقدم في الواجهة
        const progressBar = document.querySelector('.batch-upload-progress');
        const progressText = document.querySelector('.batch-upload-text');

        if (progressBar) {
            progressBar.style.width = `${percentage}%`;
            progressBar.setAttribute('aria-valuenow', percentage);
        }

        if (progressText) {
            progressText.textContent = `${message} (${percentage}%)`;
        }

        console.log(`📊 التقدم: ${percentage}% - ${message}`);
    }

    // تأخير (للتحكم في سرعة الرفع)
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// دمج الكلاس الجديد مع الكود الموجود
if (typeof window.AdvancedFileManager !== 'undefined') {
    // إضافة الرافع المتعدد للكلاس الموجود
    window.AdvancedFileManager.prototype.batchUploader = new BatchFileUploader();

    // تعديل دالة رفع المجلدات لاستخدام الرفع المتعدد
    window.AdvancedFileManager.prototype.uploadFolderFileWithBatches = async function(files, uploadType) {
        console.log(`🚀 بدء رفع ${uploadType} باستخدام النظام المتعدد:`, {
            filesCount: files.length,
            timestamp: new Date().toISOString()
        });

        // جمع الخيارات
        const options = {
            compress_images: document.getElementById('compressImages')?.checked || false,
            auto_organize: document.getElementById('autoOrganize')?.checked || true,
            cloud_sync: document.getElementById('cloudSync')?.checked || false,
        };

        // إضافة ملف Excel إذا كان موجود
        const excelFile = document.getElementById('excelFileInput')?.files[0];
        if (excelFile) {
            options.excelFile = excelFile;
            options.enable_excel_import = document.getElementById('enableExcelImport')?.checked || false;
            options.target_table = document.getElementById('targetTable')?.value || 'data';
        }

        try {
            // عرض شريط التقدم
            this.showBatchUploadProgress();

            const result = await this.batchUploader.uploadAllBatches(files, options);

            if (result.success) {
                this.showAlert(`تم رفع ${result.summary.total_success} ملف بنجاح!`, 'success');

                // تحديث الواجهة
                this.updateFileCounts();
                this.loadAnalytics();
            } else {
                this.showAlert(`تم رفع ${result.summary.total_success} ملف مع ${result.summary.total_errors} خطأ`, 'warning');
            }

            // إخفاء شريط التقدم
            this.hideBatchUploadProgress();

            return result;

        } catch (error) {
            console.error('❌ خطأ في الرفع المتعدد:', error);
            this.showAlert(`فشل في رفع المجلد: ${error.message}`, 'danger');
            this.hideBatchUploadProgress();
            throw error;
        }
    };

    // دوال شريط التقدم
    window.AdvancedFileManager.prototype.showBatchUploadProgress = function() {
        let progressHtml = `
        <div class="batch-upload-progress-container" style="margin: 20px 0;">
            <div class="progress">
                <div class="progress-bar batch-upload-progress bg-success"
                     role="progressbar" style="width: 0%"
                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                </div>
            </div>
            <div class="text-center mt-2">
                <small class="batch-upload-text text-muted">جاري التحضير...</small>
            </div>
        </div>`;

        // إضافة شريط التقدم إلى modal أو منطقة الرفع
        const uploadArea = document.querySelector('.upload-area') || document.querySelector('.modal-body');
        if (uploadArea) {
            uploadArea.insertAdjacentHTML('beforeend', progressHtml);
        }
    };

    window.AdvancedFileManager.prototype.hideBatchUploadProgress = function() {
        const progressContainer = document.querySelector('.batch-upload-progress-container');
        if (progressContainer) {
            progressContainer.remove();
        }
    };
}
