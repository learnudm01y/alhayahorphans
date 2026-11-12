@php
    // وحدة تخزين المسودة: لا جدول ولا مسار على السيرفر — كل شيء داخل هذا المتصفح.
    // الحالة النصية صغيرة فتبقى في localStorage، ونسخة كل صورة كبيرة فتبقى في IndexedDB.
    // يُستدعى هذا الملف أيضاً في صفحة الشكر لمسح المسودة بعد الإرسال الناجح.
@endphp
<script>
    window.RegDraft = (function () {
        const LS_PREFIX = 'alhayahDraft:v1:';
        const STATE_VERSION = 1;
        const IDB_NAME = 'alhayahRegistrationDraft';
        const IDB_STORE = 'blobs';
        const IDB_VERSION = 1;
        const TTL_MS = 7 * 24 * 60 * 60 * 1000;
        // نفس الصيغة التي يقبلها رفع الأجزاء في GeneralRegistrationController::uploadChunk
        const KEY_PATTERN = /^(?:\d{6}|TEMP-[A-Z0-9]{6})$/;

        let activeDraftKey = '';

        function pageKey() {
            if (activeDraftKey) return activeDraftKey;
            const input = document.querySelector('input[name="original_file_id_number"]');
            const value = input ? String(input.value || '').trim() : '';

            return KEY_PATTERN.test(value) ? value : '';
        }

        function lsKey(key) {
            return LS_PREFIX + key;
        }

        function storageWorks() {
            try {
                const probe = LS_PREFIX + 'probe';
                localStorage.setItem(probe, '1');
                localStorage.removeItem(probe);

                return true;
            } catch (error) {
                return false;
            }
        }

        let dbPromise = null;

        function openDb() {
            if (dbPromise) {
                return dbPromise;
            }
            dbPromise = new Promise(function (resolve, reject) {
                if (typeof indexedDB === 'undefined') {
                    reject(new Error('no-indexeddb'));

                    return;
                }
                const request = indexedDB.open(IDB_NAME, IDB_VERSION);
                request.onupgradeneeded = function () {
                    const db = request.result;
                    if (!db.objectStoreNames.contains(IDB_STORE)) {
                        const store = db.createObjectStore(IDB_STORE, { keyPath: 'blobId' });
                        store.createIndex('byDraft', 'draftKey', { unique: false });
                    }
                };
                request.onsuccess = function () {
                    resolve(request.result);
                };
                request.onerror = function () {
                    reject(request.error || new Error('indexeddb-open-failed'));
                };
                request.onblocked = function () {
                    reject(new Error('indexeddb-blocked'));
                };
            }).catch(function (error) {
                dbPromise = null;
                throw error;
            });

            return dbPromise;
        }

        function runTransaction(mode, work) {
            return openDb().then(function (db) {
                return new Promise(function (resolve, reject) {
                    const transaction = db.transaction(IDB_STORE, mode);
                    let outcome = null;
                    let settled = false;
                    transaction.oncomplete = function () {
                        resolve(settled ? outcome : true);
                    };
                    transaction.onerror = function () {
                        reject(transaction.error || new Error('indexeddb-transaction-failed'));
                    };
                    transaction.onabort = function () {
                        reject(transaction.error || new Error('indexeddb-transaction-aborted'));
                    };
                    // done() تُستدعى داخل request.onsuccess لأن نتيجة الطلب لا تتوفّر قبل اكتمال العملية
                    work(transaction.objectStore(IDB_STORE), function (value) {
                        outcome = value;
                        settled = true;
                    });
                });
            });
        }

        function allKeys() {
            const keys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const name = localStorage.key(i);
                if (name && name.indexOf(LS_PREFIX) === 0) {
                    const draftKey = name.slice(LS_PREFIX.length);
                    if (draftKey !== 'probe' && KEY_PATTERN.test(draftKey)) {
                        keys.push(draftKey);
                    }
                }
            }

            return keys;
        }

        function readState(key) {
            if (!key || !storageWorks()) {
                return null;
            }
            const raw = localStorage.getItem(lsKey(key));
            if (!raw) {
                return null;
            }
            let state;
            try {
                state = JSON.parse(raw);
            } catch (error) {
                localStorage.removeItem(lsKey(key));

                return null;
            }
            if (!state || state.version !== STATE_VERSION || typeof state.savedAt !== 'number') {
                localStorage.removeItem(lsKey(key));

                return null;
            }

            return state;
        }

        // المسح يشمل الحالة وكل صورة مرتبطة بها، فلا تبقى صورة يتيمة في المتصفح
        function deleteDraftBlobs(key) {
            return runTransaction('readwrite', function (store, done) {
                const request = store.index('byDraft').getAllKeys(IDBKeyRange.only(key));
                request.onsuccess = function () {
                    (request.result || []).forEach(function (blobId) {
                        store.delete(blobId);
                    });
                    done(true);
                };
            }).catch(function () {
                return false;
            });
        }

        function forget(key) {
            if (!key) {
                return Promise.resolve(false);
            }
            if (storageWorks()) {
                localStorage.removeItem(lsKey(key));
            }

            return deleteDraftBlobs(key);
        }

        function purgeExpired() {
            if (!storageWorks()) {
                return Promise.resolve(0);
            }
            const cutoff = Date.now() - TTL_MS;
            const expired = allKeys().filter(function (key) {
                const state = readState(key);

                return !state || state.savedAt < cutoff;
            });
            expired.forEach(function (key) {
                localStorage.removeItem(lsKey(key));
            });

            return Promise.all(expired.map(function (key) {
                return deleteDraftBlobs(key);
            })).then(function () {
                return expired.length;
            });
        }

        function listSummaries() {
            if (!storageWorks()) {
                return [];
            }

            return allKeys().map(function (key) {
                const state = readState(key);
                if (!state) {
                    return null;
                }

                return {
                    draftKey: key,
                    savedAt: state.savedAt,
                    sectionId: state.sectionId || '',
                    counts: {
                        familyMembers: (state.familyMembers || []).length,
                        additionalDeceased: (state.additionalDeceased || []).length,
                        docs: (state.docs || []).length
                    }
                };
            }).filter(function (summary) {
                return !!summary;
            }).sort(function (a, b) {
                return b.savedAt - a.savedAt;
            });
        }

        // معرّف الصورة يشتق من معرّف المهمة وحده (فريد لكل صورة منذ إنشائها)،
        // فلا تتعطّل المسودة إذا نُقلت بيانات مسودة أخرى إلى رقم ملف جديد.
        function docBlobId(taskId) {
            return 'doc_' + taskId;
        }

        return {
            TTL_MS: TTL_MS,
            pageKey: pageKey,
            useDraft: function (key) {
                if (KEY_PATTERN.test(key)) activeDraftKey = key;
            },
            available: storageWorks,

            requestPersistence: function () {
                if (navigator.storage && typeof navigator.storage.persist === 'function') {
                    navigator.storage.persist().catch(function () {});
                }
            },

            load: function (key) {
                return readState(key || pageKey());
            },

            // يعيد ok=false مع سبب عربي المصدر حتى تختار الطبقة العليا الرسالة المناسبة
            save: function (state) {
                if (!storageWorks()) {
                    return { ok: false, code: 'unavailable' };
                }
                const key = state && state.draftKey;
                if (!key) {
                    return { ok: false, code: 'unavailable' };
                }
                state.version = STATE_VERSION;
                state.savedAt = Date.now();
                try {
                    localStorage.setItem(lsKey(key), JSON.stringify(state));
                } catch (error) {
                    const quota = error && (error.name === 'QuotaExceededError'
                        || error.name === 'NS_ERROR_DOM_QUOTA_REACHED');

                    return { ok: false, code: quota ? 'quota' : 'unavailable' };
                }

                return { ok: true };
            },

            forget: forget,
            purgeExpired: purgeExpired,
            list: listSummaries,

            markSubmitting: function () {
                const key = pageKey();
                if (!key || !storageWorks()) {
                    return false;
                }
                try {
                    sessionStorage.setItem(LS_PREFIX + 'submitted', JSON.stringify({
                        draftKey: key,
                        at: Date.now()
                    }));
                } catch (error) {
                    return false;
                }

                return true;
            },

            clearSubmitted: function () {
                if (!storageWorks()) {
                    return Promise.resolve(0);
                }
                let marker = null;
                try {
                    marker = JSON.parse(sessionStorage.getItem(LS_PREFIX + 'submitted') || 'null');
                    sessionStorage.removeItem(LS_PREFIX + 'submitted');
                } catch (error) {
                    marker = null;
                }
                if (!marker || !KEY_PATTERN.test(marker.draftKey || '')) {
                    return Promise.resolve(0);
                }

                return forget(marker.draftKey).then(function () {
                    return 1;
                });
            },

            putDoc: function (draftKey, taskId, file) {
                if (!draftKey || !file) {
                    return Promise.resolve(false);
                }

                return runTransaction('readwrite', function (store, done) {
                    store.put({
                        blobId: docBlobId(taskId),
                        draftKey: draftKey,
                        name: file.name || 'image.jpg',
                        type: file.type || 'image/jpeg',
                        size: file.size || 0,
                        savedAt: Date.now(),
                        blob: file
                    });
                    done(true);
                }).catch(function () {
                    return false;
                });
            },

            getDoc: function (draftKey, taskId) {
                return runTransaction('readonly', function (store, done) {
                    const request = store.get(docBlobId(taskId));
                    request.onsuccess = function () {
                        done(request.result || null);
                    };
                }).then(function (record) {
                    if (!record || record.draftKey !== draftKey || !record.blob) {
                        return null;
                    }

                    return {
                        file: new File([record.blob], record.name, { type: record.type }),
                        size: record.size || 0
                    };
                }).catch(function () {
                    return null;
                });
            },

            dropDoc: function (draftKey, taskId) {
                return runTransaction('readwrite', function (store, done) {
                    const request = store.get(docBlobId(taskId));
                    request.onsuccess = function () {
                        if (request.result && request.result.draftKey === draftKey) {
                            store.delete(docBlobId(taskId));
                        }
                        done(true);
                    };
                }).catch(function () {
                    return false;
                });
            },

            docId: docBlobId
        };
    })();
</script>
