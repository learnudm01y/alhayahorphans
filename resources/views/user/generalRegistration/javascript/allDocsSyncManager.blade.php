@push('scriptsCodeUserRegistration')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- نظام المزامنة المركزي للمرفقات بين البوابات ---
    console.log('🔄 بدء تشغيل نظام مزامنة المرفقات المركزي');

    // التأكد من وجود window.allDocs
    if (!window.allDocs) {
        window.allDocs = new Map();
        console.log('⚠️ إنشاء window.allDocs لعدم وجودها');
    }

    // مراقبة انتقالات التبويبات لحفظ واسترجاع المرفقات
    const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');

    // تخزين حالة التبويب النشط
    let activeTabId = null;
    let previousTabId = null;

    tabLinks.forEach(tabLink => {
        tabLink.addEventListener('show.bs.tab', function(e) {
            previousTabId = activeTabId;
            const targetId = e.target.getAttribute('data-bs-target');

            console.log(`🔄 الانتقال من التبويب ${previousTabId} إلى ${targetId}`);

            // حفظ المرفقات قبل الانتقال
            if (typeof preserveMainAndDeceasedAttachments === 'function') {
                preserveMainAndDeceasedAttachments();
            }
        });

        tabLink.addEventListener('shown.bs.tab', function(e) {
            activeTabId = e.target.getAttribute('data-bs-target');

            // استرجاع المرفقات بعد الانتقال
            setTimeout(() => {
                // محاولتان للاسترجاع للتأكد
                if (typeof window.restoreMainAndDeceasedAttachments === 'function') {
                    window.restoreMainAndDeceasedAttachments();
                }

                setTimeout(() => {
                    if (typeof window.restoreMainAndDeceasedAttachments === 'function') {
                        window.restoreMainAndDeceasedAttachments();
                    }
                }, 300);
            }, 100);
        });
    });

    // مراقبة تغييرات المرفقات في allDocs
    function setupAllDocsMonitor() {
        if (!window.allDocs || typeof window.allDocs.set !== 'function') return;

        // حفظ الدوال الأصلية
        const originalSet = window.allDocs.set;
        const originalDelete = window.allDocs.delete;
        const originalClear = window.allDocs.clear;

        // تعديل دالة Set
        window.allDocs.set = function(key, value) {
            const result = originalSet.apply(this, arguments);

            // حدث مخصص عند تغيير المرفقات
            const event = new CustomEvent('allDocsChanged', {
                detail: {
                    action: 'set',
                    key,
                    count: value.length
                }
            });
            document.dispatchEvent(event);

            console.log(`📝 تم تحديث البوابة: ${key} (${value.length} مرفق)`);
            return result;
        };

        // تعديل دالة Delete
        window.allDocs.delete = function(key) {
            // الحماية للبوابات الرئيسية
            if (['main', 'deceased_father', 'deceased_mother'].includes(key) ||
                key.startsWith('default_') || (!key.startsWith('family_'))) {

                console.warn(`⛔ محاولة حذف البوابة المحمية: ${key} - تم المنع`);
                return false;
            }

            const result = originalDelete.apply(this, arguments);

            // حدث مخصص عند حذف البوابة
            const event = new CustomEvent('allDocsChanged', {
                detail: {
                    action: 'delete',
                    key
                }
            });
            document.dispatchEvent(event);

            console.log(`🗑️ تم حذف البوابة: ${key}`);
            return result;
        };

        // تعديل دالة Clear - حماية كاملة من المسح
        window.allDocs.clear = function() {
            console.warn('⛔ محاولة حذف جميع المرفقات باستخدام clear() - تم المنع');

            // حفظ جميع المرفقات المهمة
            if (typeof preserveMainAndDeceasedAttachments === 'function') {
                preserveMainAndDeceasedAttachments();
            }

            // مسح مرفقات أفراد الأسرة فقط
            const keysToDelete = [];
            this.forEach((value, key) => {
                if (key.startsWith('family_')) {
                    keysToDelete.push(key);
                }
            });

            keysToDelete.forEach(key => {
                originalDelete.call(this, key);
            });

            // استرجاع المرفقات المحمية
            setTimeout(() => {
                if (typeof window.restoreMainAndDeceasedAttachments === 'function') {
                    window.restoreMainAndDeceasedAttachments(true);
                }
            }, 10);

            return this;
        };

        console.log('✅ تم تفعيل نظام مراقبة المرفقات');
    }

    // مراقبة تحميل وإزالة أداة القص
    function monitorCropperAvailability() {
        // فحص أولي لوجود أداة القص
        if (window.showCropperModal || window.showCropper) {
            console.log('✅ أداة القص متوفرة عند بدء التشغيل');
        } else {
            console.warn('⚠️ أداة القص غير متوفرة عند بدء التشغيل، سيتم المراقبة');

            // إعداد مراقبة لأداة القص
            let checkCount = 0;
            const maxChecks = 50;

            const cropperChecker = setInterval(() => {
                checkCount++;

                if (window.showCropperModal || window.showCropper) {
                    console.log('✅ تم اكتشاف أداة القص أثناء المراقبة');
                    clearInterval(cropperChecker);

                    // تحديث المرفقات بعد توفر أداة القص
                    if (typeof window.restoreMainAndDeceasedAttachments === 'function') {
                        window.restoreMainAndDeceasedAttachments(true);
                    }
                } else if (checkCount >= maxChecks) {
                    console.error('❌ لم يتم العثور على أداة القص بعد المحاولات القصوى');
                    clearInterval(cropperChecker);
                }
            }, 200);
        }
    }

    // تفعيل النظام
    setupAllDocsMonitor();
    monitorCropperAvailability();

    // الاستماع لأحداث تغيير المرفقات
    document.addEventListener('allDocsChanged', function(e) {
        const { action, key, count } = e.detail;

        // حفظ البوابات الرئيسية تلقائياً عند أي تغيير
        if (['main', 'deceased_father', 'deceased_mother'].includes(key) ||
            key.startsWith('default_') || (!key.startsWith('family_'))) {

            // حفظ حالة المرفقات بعد كل تغيير في البوابات الرئيسية
            if (typeof preserveMainAndDeceasedAttachments === 'function') {
                preserveMainAndDeceasedAttachments();
            }
        }
    });

    // فحص دوري للمرفقات
    setInterval(() => {
        // طباعة إحصائيات المرفقات كل دقيقة
        if (window.allDocs) {
            let totalDocs = 0;
            let mainDocs = 0;
            let deceasedDocs = 0;
            let familyDocs = 0;
            let otherDocs = 0;

            window.allDocs.forEach((docs, key) => {
                if (key === 'main' || key === 'default_main') {
                    mainDocs += docs.length;
                } else if (key === 'deceased_father' || key === 'deceased_mother' ||
                    key === 'default_father' || key === 'default_mother') {
                    deceasedDocs += docs.length;
                } else if (key.startsWith('family_')) {
                    familyDocs += docs.length;
                } else {
                    otherDocs += docs.length;
                }

                totalDocs += docs.length;
            });

            console.log(`📊 إحصائيات المرفقات - الإجمالي: ${totalDocs} | ` +
                `البيانات الأساسية: ${mainDocs} | المتوفين: ${deceasedDocs} | ` +
                `أفراد الأسرة: ${familyDocs} | أخرى: ${otherDocs}`);
        }
    }, 60000);
});
</script>
@endpush
