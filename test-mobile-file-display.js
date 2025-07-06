/**
 * Mobile Display Test for Family Member File Preview
 * تاريخ: 2025-01-07
 *
 * هذا الاختبار يتحقق من عرض الملفات على الأجهزة المحمولة
 */

(function() {
    'use strict';

    console.log('📱 بدء اختبار عرض الملفات على الجوال');

    // محاكاة جهاز محمول
    function simulateMobileDevice() {
        // تغيير حجم النافذة لمحاكاة الجوال
        const originalWidth = window.innerWidth;
        const originalHeight = window.innerHeight;

        console.log(`📐 الحجم الأصلي: ${originalWidth}x${originalHeight}`);

        // محاكاة عرض الجوال (375px)
        Object.defineProperty(window, 'innerWidth', {
            writable: true,
            configurable: true,
            value: 375
        });

        Object.defineProperty(window, 'innerHeight', {
            writable: true,
            configurable: true,
            value: 667
        });

        // إرسال حدث تغيير الحجم
        window.dispatchEvent(new Event('resize'));

        console.log(`📱 تم التغيير إلى حجم الجوال: ${window.innerWidth}x${window.innerHeight}`);

        return { originalWidth, originalHeight };
    }

    // اختبار عرض المرفقات
    function testMobileFileDisplay() {
        console.log('🔍 فحص عرض المرفقات على الجوال...');

        // البحث عن منطقة عرض الملفات
        const previews = document.querySelectorAll('.mainDocumentPreview');
        console.log(`📋 عدد مناطق العرض الموجودة: ${previews.length}`);

        previews.forEach((preview, index) => {
            console.log(`📂 فحص منطقة العرض ${index + 1}:`);
            console.log(`   - مرئية: ${preview.style.display !== 'none'}`);
            console.log(`   - العرض: ${preview.style.width || 'افتراضي'}`);
            console.log(`   - المحتوى: ${preview.children.length} عنصر`);

            // فحص البطاقات داخل المنطقة
            const cards = preview.querySelectorAll('.attachment-card');
            console.log(`   - عدد البطاقات: ${cards.length}`);

            cards.forEach((card, cardIndex) => {
                const cardStyle = window.getComputedStyle(card);
                console.log(`     البطاقة ${cardIndex + 1}:`);
                console.log(`       - العرض: ${cardStyle.width}`);
                console.log(`       - الارتفاع: ${cardStyle.height}`);
                console.log(`       - العرض: ${cardStyle.display}`);
                console.log(`       - الظهور: ${cardStyle.visibility}`);
                console.log(`       - الشفافية: ${cardStyle.opacity}`);
            });
        });
    }

    // اختبار الاستجابة للجوال
    function testMobileResponsiveness() {
        console.log('📱 اختبار الاستجابة للجوال...');

        // محاكاة الجوال
        const original = simulateMobileDevice();

        setTimeout(() => {
            testMobileFileDisplay();

            // إضافة ملف تجريبي إذا لم تكن هناك ملفات
            const firstPreview = document.querySelector('.mainDocumentPreview');
            if (firstPreview && firstPreview.children.length === 0) {
                console.log('📤 إضافة ملف تجريبي للاختبار...');
                createTestFile(firstPreview);
            }

            // اختبار ثانٍ بعد الإضافة
            setTimeout(() => {
                testMobileFileDisplay();

                // إعادة الحجم الأصلي
                Object.defineProperty(window, 'innerWidth', {
                    writable: true,
                    configurable: true,
                    value: original.originalWidth
                });

                Object.defineProperty(window, 'innerHeight', {
                    writable: true,
                    configurable: true,
                    value: original.originalHeight
                });

                window.dispatchEvent(new Event('resize'));
                console.log(`↩️ تم إعادة الحجم الأصلي: ${window.innerWidth}x${window.innerHeight}`);

            }, 1000);
        }, 500);
    }

    // إنشاء ملف تجريبي للاختبار
    function createTestFile(preview) {
        const testCard = document.createElement('div');
        testCard.className = 'attachment-card card border-0 shadow-sm';
        testCard.style.cssText = `
            width: 100%;
            max-width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #e3e6f0 !important;
            margin-bottom: 15px;
            display: block;
            visibility: visible;
            opacity: 1;
        `;

        testCard.innerHTML = `
            <div class="card-header bg-gradient-primary text-white p-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <i class="fas fa-file-image me-1"></i>
                    <span class="text-truncate">ملف تجريبي</span>
                    <i class="fas fa-check-circle text-success"></i>
                </div>
            </div>
            <div class="card-body p-3 text-center">
                <div class="image-container position-relative" style="border-radius: 8px; overflow: hidden; margin-bottom: 10px; background: #f8f9fa;">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjE4MCIgdmlld0JveD0iMCAwIDMwMCAxODAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMTgwIiBmaWxsPSIjNDI4NWY0Ii8+Cjx0ZXh0IHg9IjE1MCIgeT0iOTAiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IndoaXRlIiBmb250LXNpemU9IjE2Ij7YtdmI2LHYqSDYqtin2KzYsdmK2KjZitipPC90ZXh0Pgo8L3N2Zz4="
                         style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;"
                         class="document-image" />
                </div>
                <div class="file-name text-muted small mb-2 text-truncate">test-image.jpg</div>
                <div class="file-info small text-muted mb-3">
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-weight-hanging me-1"></i>15.2 KB</span>
                        <span><i class="fas fa-clock me-1"></i>الآن</span>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm w-100" style="border-radius: 8px;">
                    <i class="fas fa-trash-alt me-1"></i>حذف
                </button>
            </div>
        `;

        // إضافة الحاوية إذا لم تكن موجودة
        let cardsWrapper = preview.querySelector('.d-flex');
        if (!cardsWrapper) {
            cardsWrapper = document.createElement('div');
            cardsWrapper.className = 'd-flex flex-column gap-3';
            cardsWrapper.style.cssText = `
                margin-top: 15px;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                flex-direction: column;
                gap: 15px;
            `;
            preview.appendChild(cardsWrapper);
        }

        cardsWrapper.appendChild(testCard);
        preview.style.display = 'block';

        console.log('✅ تم إنشاء ملف تجريبي للاختبار');
    }

    // تشغيل الاختبارات
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', testMobileResponsiveness);
    } else {
        testMobileResponsiveness();
    }

    // اختبار إضافي بعد 5 ثوانٍ
    setTimeout(() => {
        console.log('🔄 إعادة اختبار بعد 5 ثوانٍ...');
        testMobileFileDisplay();
    }, 5000);

})();
