/**
 * ══════════════════════════════════════════════════════════════
 * 💾 File Storage Manager - IndexedDB
 * ══════════════════════════════════════════════════════════════
 *
 * يدير تخزين معلومات الملفات المرفوعة في IndexedDB
 * ويحدث حالتها عند اكتمال الرفع أو الفشل
 *
 * ✅ الحالات المدعومة:
 * - pending: في انتظار الرفع
 * - uploading: جاري الرفع
 * - completed: تم الرفع بنجاح
 * - failed: فشل الرفع
 *
 * ══════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    console.log('💾 File Storage IndexedDB - Initializing...');

    // ═══════════════════════════════════════════════════════════
    // 🔧 Configuration
    // ═══════════════════════════════════════════════════════════

    const CONFIG = {
        DB_NAME: 'FileUploadsDB',
        DB_VERSION: 1,
        STORE_NAME: 'uploads',
        DEBUG: true
    };

    let db = null;

    // ═══════════════════════════════════════════════════════════
    // 🗄️ Initialize Database
    // ═══════════════════════════════════════════════════════════

    function initDB() {
        return new Promise((resolve, reject) => {
            if (db) {
                resolve(db);
                return;
            }

            const request = indexedDB.open(CONFIG.DB_NAME, CONFIG.DB_VERSION);

            request.onerror = () => {
                console.error('❌ فشل فتح IndexedDB:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                db = request.result;
                console.log('✅ تم فتح IndexedDB بنجاح');
                resolve(db);
            };

            request.onupgradeneeded = (event) => {
                console.log('🔧 تهيئة IndexedDB...');
                const db = event.target.result;

                // حذف المخزن القديم إذا كان موجوداً
                if (db.objectStoreNames.contains(CONFIG.STORE_NAME)) {
                    db.deleteObjectStore(CONFIG.STORE_NAME);
                }

                // إنشاء مخزن جديد
                const objectStore = db.createObjectStore(CONFIG.STORE_NAME, {
                    keyPath: 'fileId',
                    autoIncrement: false
                });

                // إنشاء فهارس
                objectStore.createIndex('status', 'status', { unique: false });
                objectStore.createIndex('uploadedAt', 'uploadedAt', { unique: false });
                objectStore.createIndex('createdAt', 'createdAt', { unique: false });

                console.log('✅ تم إنشاء object store:', CONFIG.STORE_NAME);
            };
        });
    }

    // ═══════════════════════════════════════════════════════════
    // 💾 Add/Update File
    // ═══════════════════════════════════════════════════════════

    async function saveFile(fileData) {
        try {
            await initDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([CONFIG.STORE_NAME], 'readwrite');
                const objectStore = transaction.objectStore(CONFIG.STORE_NAME);

                // إضافة timestamp إذا لم يكن موجوداً
                if (!fileData.createdAt) {
                    fileData.createdAt = new Date().toISOString();
                }
                fileData.updatedAt = new Date().toISOString();

                // التأكد من وجود fileId
                if (!fileData.fileId) {
                    reject(new Error('fileId is required'));
                    return;
                }

                const request = objectStore.put(fileData);

                request.onsuccess = () => {
                    if (CONFIG.DEBUG) {
                        console.log(`✅ تم حفظ الملف في IndexedDB:`, fileData.fileId);
                        console.log('   الحالة:', fileData.status);
                    }
                    resolve(fileData);
                };

                request.onerror = () => {
                    console.error('❌ فشل حفظ الملف:', request.error);
                    reject(request.error);
                };
            });

        } catch (error) {
            console.error('❌ خطأ في saveFile:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🔄 Update File Status
    // ═══════════════════════════════════════════════════════════

    async function updateFileStatus(fileId, status, additionalData = {}) {
        try {
            if (CONFIG.DEBUG) {
                console.log(`🔄 تحديث حالة الملف #${fileId} إلى: ${status}`);
            }

            await initDB();

            return new Promise(async (resolve, reject) => {
                // الحصول على البيانات الحالية أولاً
                const currentFile = await getFile(fileId);

                if (!currentFile) {
                    // إنشاء ملف جديد إذا لم يكن موجوداً
                    const newFile = {
                        fileId: fileId,
                        status: status,
                        createdAt: new Date().toISOString(),
                        updatedAt: new Date().toISOString(),
                        ...additionalData
                    };

                    if (status === 'completed') {
                        newFile.uploadedAt = new Date().toISOString();
                    }

                    const saved = await saveFile(newFile);
                    resolve(saved);
                    return;
                }

                // تحديث البيانات الموجودة
                const updatedFile = {
                    ...currentFile,
                    status: status,
                    updatedAt: new Date().toISOString(),
                    ...additionalData
                };

                // إضافة timestamp للرفع الناجح
                if (status === 'completed' && !updatedFile.uploadedAt) {
                    updatedFile.uploadedAt = new Date().toISOString();
                }

                const saved = await saveFile(updatedFile);

                if (CONFIG.DEBUG) {
                    console.log(`✅ تم تحديث الملف #${fileId} في IndexedDB`);
                }

                resolve(saved);
            });

        } catch (error) {
            console.error(`❌ فشل تحديث حالة الملف #${fileId}:`, error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 📖 Get File by ID
    // ═══════════════════════════════════════════════════════════

    async function getFile(fileId) {
        try {
            await initDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([CONFIG.STORE_NAME], 'readonly');
                const objectStore = transaction.objectStore(CONFIG.STORE_NAME);
                const request = objectStore.get(fileId);

                request.onsuccess = () => {
                    resolve(request.result || null);
                };

                request.onerror = () => {
                    console.error(`❌ فشل قراءة الملف #${fileId}:`, request.error);
                    reject(request.error);
                };
            });

        } catch (error) {
            console.error('❌ خطأ في getFile:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 📋 Get All Files
    // ═══════════════════════════════════════════════════════════

    async function getAllFiles() {
        try {
            await initDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([CONFIG.STORE_NAME], 'readonly');
                const objectStore = transaction.objectStore(CONFIG.STORE_NAME);
                const request = objectStore.getAll();

                request.onsuccess = () => {
                    if (CONFIG.DEBUG) {
                        console.log(`📋 تم جلب ${request.result.length} ملف من IndexedDB`);
                    }
                    resolve(request.result);
                };

                request.onerror = () => {
                    console.error('❌ فشل قراءة الملفات:', request.error);
                    reject(request.error);
                };
            });

        } catch (error) {
            console.error('❌ خطأ في getAllFiles:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🔍 Get Files by Status
    // ═══════════════════════════════════════════════════════════

    async function getFilesByStatus(status) {
        try {
            await initDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([CONFIG.STORE_NAME], 'readonly');
                const objectStore = transaction.objectStore(CONFIG.STORE_NAME);
                const index = objectStore.index('status');
                const request = index.getAll(status);

                request.onsuccess = () => {
                    if (CONFIG.DEBUG) {
                        console.log(`🔍 تم جلب ${request.result.length} ملف بحالة "${status}"`);
                    }
                    resolve(request.result);
                };

                request.onerror = () => {
                    console.error(`❌ فشل قراءة الملفات بحالة "${status}":`, request.error);
                    reject(request.error);
                };
            });

        } catch (error) {
            console.error('❌ خطأ في getFilesByStatus:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 📊 Get Statistics
    // ═══════════════════════════════════════════════════════════

    async function getStats() {
        try {
            const allFiles = await getAllFiles();

            const stats = {
                total: allFiles.length,
                pending: 0,
                uploading: 0,
                completed: 0,
                failed: 0
            };

            allFiles.forEach(file => {
                if (stats.hasOwnProperty(file.status)) {
                    stats[file.status]++;
                }
            });

            if (CONFIG.DEBUG) {
                console.log('📊 إحصائيات الملفات:', stats);
            }

            return stats;

        } catch (error) {
            console.error('❌ خطأ في getStats:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🗑️ Delete File
    // ═══════════════════════════════════════════════════════════

    async function deleteFile(fileId) {
        try {
            await initDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([CONFIG.STORE_NAME], 'readwrite');
                const objectStore = transaction.objectStore(CONFIG.STORE_NAME);
                const request = objectStore.delete(fileId);

                request.onsuccess = () => {
                    if (CONFIG.DEBUG) {
                        console.log(`🗑️ تم حذف الملف #${fileId} من IndexedDB`);
                    }
                    resolve(true);
                };

                request.onerror = () => {
                    console.error(`❌ فشل حذف الملف #${fileId}:`, request.error);
                    reject(request.error);
                };
            });

        } catch (error) {
            console.error('❌ خطأ في deleteFile:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🧹 Clear All Files
    // ═══════════════════════════════════════════════════════════

    async function clearAll() {
        try {
            await initDB();

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([CONFIG.STORE_NAME], 'readwrite');
                const objectStore = transaction.objectStore(CONFIG.STORE_NAME);
                const request = objectStore.clear();

                request.onsuccess = () => {
                    console.log('🧹 تم مسح جميع الملفات من IndexedDB');
                    resolve(true);
                };

                request.onerror = () => {
                    console.error('❌ فشل مسح الملفات:', request.error);
                    reject(request.error);
                };
            });

        } catch (error) {
            console.error('❌ خطأ في clearAll:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🧹 Clear Completed Files (older than X days)
    // ═══════════════════════════════════════════════════════════

    async function clearCompletedFiles(daysOld = 7) {
        try {
            const completedFiles = await getFilesByStatus('completed');
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - daysOld);

            let deletedCount = 0;

            for (const file of completedFiles) {
                const uploadDate = new Date(file.uploadedAt || file.createdAt);
                if (uploadDate < cutoffDate) {
                    await deleteFile(file.fileId);
                    deletedCount++;
                }
            }

            console.log(`🧹 تم حذف ${deletedCount} ملف مكتمل قديم`);
            return deletedCount;

        } catch (error) {
            console.error('❌ خطأ في clearCompletedFiles:', error);
            throw error;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🌍 Global API Exposure
    // ═══════════════════════════════════════════════════════════

    window.FileStorageDB = {
        // Core Operations
        initDB: initDB,
        saveFile: saveFile,
        updateFileStatus: updateFileStatus,
        getFile: getFile,
        getAllFiles: getAllFiles,
        getFilesByStatus: getFilesByStatus,
        getStats: getStats,
        deleteFile: deleteFile,
        clearAll: clearAll,
        clearCompletedFiles: clearCompletedFiles,

        // Utils
        isReady: () => db !== null,
        getConfig: () => CONFIG
    };

    // ═══════════════════════════════════════════════════════════
    // 🚀 Auto-Init on Load
    // ═══════════════════════════════════════════════════════════

    initDB().then(() => {
        console.log('✅ FileStorageDB ready');
    }).catch(error => {
        console.error('❌ Failed to initialize FileStorageDB:', error);
    });

    console.log('💾 File Storage IndexedDB script loaded');

})();
