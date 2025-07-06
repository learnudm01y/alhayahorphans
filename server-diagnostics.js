/**
 * أداة تشخيص مشاكل الجوال والـ Cropper - للخادم الحقيقي
 * تاريخ: 2025-01-07
 *
 * هذا الملف يقوم بـ:
 * 1. فرض إعادة تحميل CSS وJavaScript
 * 2. إنشاء ملفات تجريبية للاختبار
 * 3. إصلاح مشاكل modal backdrop
 * 4. تشخيص شامل للمشاكل
 */

// تشغيل فوري عند التحميل
(function() {
    'use strict';

    // متغيرات التشخيص
    let diagnostics = {
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        screenSize: `${window.innerWidth}x${window.innerHeight}`,
        devicePixelRatio: window.devicePixelRatio,
        touchSupport: 'ontouchstart' in window,
        isMobile: false,
        issues: [],
        fixes: []
    };

    console.log('🚨 [تشخيص الخادم] بدء التشخيص الشامل');
    console.log('📊 معلومات الجهاز:', diagnostics);

    // كشف نوع الجهاز
    function detectDevice() {
        const isMobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const isMobileScreen = window.innerWidth <= 768;
        const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

        diagnostics.isMobile = isMobileUA || isMobileScreen || hasTouch;

        console.log('📱 [كشف الجهاز]:', {
            userAgent: isMobileUA,
            screenSize: isMobileScreen,
            touch: hasTouch,
            result: diagnostics.isMobile
        });

        return diagnostics.isMobile;
    }

    // فرض إعادة تحميل الأنماط
    function forceStyleReload() {
        console.log('🔄 [إعادة تحميل] فرض إعادة تحميل الأنماط...');

        // إزالة جميع الأنماط القديمة
        const oldStyles = document.querySelectorAll('style[id*="family"], style[data-version]');
        oldStyles.forEach((style, index) => {
            console.log(`🗑️ إزالة نمط قديم ${index + 1}:`, style.id);
            style.remove();
        });

        // إنشاء نمط جديد بـ cache buster
        const timestamp = Date.now();
        const newStyle = document.createElement('style');
        newStyle.id = `force-mobile-display-${timestamp}`;
        newStyle.setAttribute('data-version', `server-fix-${timestamp}`);
        newStyle.textContent = `
            /* فرض العرض على الخادم الحقيقي - ${timestamp} */

            /* إعادة تعيين شاملة */
            .mainDocumentPreview,
            .mainDocumentPreview * {
                box-sizing: border-box !important;
            }

            /* فرض العرض - أولوية قصوى */
            .mainDocumentPreview {
                width: 100% !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                overflow: visible !important;
                clear: both !important;
                float: none !important;
                margin: 10px 0 !important;
                padding: 10px !important;
                background: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                border-radius: 8px !important;
                min-height: auto !important;
                max-height: none !important;
                height: auto !important;
            }

            /* فرض عرض البطاقات */
            .attachment-card {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                float: none !important;
                clear: both !important;
                margin: 0 0 15px 0 !important;
                padding: 0 !important;
                background: white !important;
                border: 1px solid #e3e6f0 !important;
                border-radius: 12px !important;
                overflow: visible !important;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
                transform: none !important;
                transition: none !important;
                z-index: auto !important;
                clip: auto !important;
                clip-path: none !important;
            }

            /* تحسينات الصور */
            .document-image {
                width: 100% !important;
                height: auto !important;
                max-height: 200px !important;
                object-fit: cover !important;
                display: block !important;
                border-radius: 8px !important;
            }

            /* تحسينات الحاويات */
            .d-flex.flex-wrap,
            .d-flex.flex-column {
                display: block !important;
                flex-direction: column !important;
                width: 100% !important;
                gap: 15px !important;
            }

            /* خاص بالجوال فقط */
            @media screen and (max-width: 768px) {
                body {
                    overflow-x: hidden !important;
                }

                .mainDocumentPreview {
                    width: calc(100% - 20px) !important;
                    margin: 10px !important;
                    padding: 15px !important;
                }

                .attachment-card {
                    margin: 0 0 20px 0 !important;
                }

                .card-body {
                    padding: 15px !important;
                }
            }

            /* إصلاح modal backdrop */
            .modal-backdrop {
                z-index: 1055 !important;
            }

            .modal-backdrop.fade.show {
                opacity: 0.5 !important;
            }

            #cropperModal {
                z-index: 1065 !important;
            }

            #cropperModal.show {
                display: block !important;
                opacity: 1 !important;
            }

            /* تحسينات الـ Cropper للجوال */
            @media screen and (max-width: 768px) {
                #cropperModal .modal-dialog {
                    margin: 10px !important;
                    width: calc(100vw - 20px) !important;
                    max-width: calc(100vw - 20px) !important;
                    height: calc(100vh - 20px) !important;
                    max-height: calc(100vh - 20px) !important;
                }

                #cropperModal .modal-content {
                    height: 100% !important;
                    border-radius: 8px !important;
                }

                #cropperModal .crop-area {
                    min-height: 200px !important;
                    max-height: 40vh !important;
                    overflow: hidden !important;
                }

                #cropperModal .action-buttons {
                    position: absolute !important;
                    bottom: 10px !important;
                    left: 10px !important;
                    right: 10px !important;
                    background: white !important;
                    padding: 10px !important;
                    border-radius: 8px !important;
                    box-shadow: 0 -2px 10px rgba(0,0,0,0.1) !important;
                }
            }
        `;

        document.head.appendChild(newStyle);
        console.log('✅ [إعادة تحميل] تم إضافة الأنماط الجديدة');
        diagnostics.fixes.push('فرض إعادة تحميل الأنماط');

        return timestamp;
    }

    // تنظيف modal backdrops
    function cleanupModalBackdrops() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        console.log(`🧹 [تنظيف] العثور على ${backdrops.length} backdrop`);

        backdrops.forEach((backdrop, index) => {
            console.log(`🗑️ إزالة backdrop ${index + 1}`);
            backdrop.remove();
        });

        if (backdrops.length > 0) {
            diagnostics.fixes.push(`تنظيف ${backdrops.length} modal backdrop`);
        }

        return backdrops.length;
    }

    // إنشاء ملف تجريبي
    function createTestFile() {
        console.log('📁 [إنشاء ملف] إنشاء ملف تجريبي...');

        const preview = document.querySelector('.mainDocumentPreview');
        if (!preview) {
            console.error('❌ لم يتم العثور على منطقة العرض');
            diagnostics.issues.push('منطقة العرض غير موجودة');
            return false;
        }

        // تطبيق الأنماط على منطقة العرض
        preview.style.cssText = `
            width: 100% !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            margin: 10px 0 !important;
            padding: 15px !important;
            background: #f8f9fa !important;
            border: 2px solid #28a745 !important;
            border-radius: 8px !important;
            min-height: 200px !important;
        `;

        // إنشاء بطاقة تجريبية
        const timestamp = Date.now();
        const testCard = document.createElement('div');
        testCard.className = 'attachment-card card border-0 shadow-sm server-test-card';
        testCard.setAttribute('data-test-id', timestamp);
        testCard.style.cssText = `
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            margin: 0 0 15px 0 !important;
            background: white !important;
            border: 2px solid #007bff !important;
            border-radius: 12px !important;
            overflow: visible !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
        `;

        testCard.innerHTML = `
            <div class="card-header text-white p-3" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <i class="fas fa-server me-2"></i>
                    <span class="fw-bold">اختبار الخادم الحقيقي</span>
                    <i class="fas fa-check-circle text-success"></i>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="text-center mb-3">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjE4MCIgdmlld0JveD0iMCAwIDMwMCAxODAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMTgwIiBmaWxsPSIjMDA3YmZmIi8+Cjx0ZXh0IHg9IjE1MCIgeT0iOTAiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IndoaXRlIiBmb250LXNpemU9IjE4Ij7Yp9iu2KrYqNin2LEg2YXZhNmBINil2YbYqtin2KzZiiDZhNmE2K7Yp9iv2YU8L3RleHQ+Cjwvc3ZnPg=="
                         style="width: 100% !important; height: 120px !important; object-fit: cover !important; border-radius: 8px !important; border: 1px solid #dee2e6 !important;"
                         class="document-image mb-2" />
                </div>
                <div class="alert alert-info small mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>معلومات الاختبار:</strong><br>
                    • الوقت: ${new Date().toLocaleString('ar')}<br>
                    • الجهاز: ${diagnostics.isMobile ? 'جوال' : 'شاشة كبيرة'}<br>
                    • الحجم: ${diagnostics.screenSize}<br>
                    • الـ ID: ${timestamp}
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-success btn-sm" onclick="console.log('✅ الملف التجريبي يعمل بشكل صحيح')">
                        <i class="fas fa-check me-1"></i>اختبار التفاعل
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="this.closest('.server-test-card').remove()">
                        <i class="fas fa-trash-alt me-1"></i>حذف الاختبار
                    </button>
                </div>
            </div>
        `;

        // إضافة البطاقة
        preview.innerHTML = '';
        preview.appendChild(testCard);

        console.log('✅ [إنشاء ملف] تم إنشاء الملف التجريبي بنجاح');
        diagnostics.fixes.push('إنشاء ملف تجريبي للاختبار');

        // التحقق من الظهور
        setTimeout(() => {
            const card = document.querySelector(`[data-test-id="${timestamp}"]`);
            if (card) {
                const style = window.getComputedStyle(card);
                const isVisible = style.display !== 'none' &&
                                 style.visibility !== 'hidden' &&
                                 parseFloat(style.opacity) > 0;

                console.log('🔍 [فحص الملف] حالة الظهور:', {
                    exists: !!card,
                    visible: isVisible,
                    width: style.width,
                    height: style.height,
                    display: style.display,
                    visibility: style.visibility,
                    opacity: style.opacity
                });

                if (!isVisible) {
                    diagnostics.issues.push('الملف التجريبي غير مرئي');
                } else {
                    diagnostics.fixes.push('الملف التجريبي مرئي ويعمل');
                }
            }
        }, 1000);

        return true;
    }

    // فحص دوال الـ Cropper
    function checkCropperFunctions() {
        console.log('✂️ [فحص Cropper] التحقق من دوال الـ Cropper...');

        const functions = ['showCropperModal', 'showCropper', 'cropperReady'];
        const elements = ['cropperModal', 'cropperImage', 'cropperCropBtn'];

        let functionsFound = 0;
        let elementsFound = 0;

        functions.forEach(func => {
            if (typeof window[func] === 'function') {
                console.log(`✅ دالة ${func}: موجودة`);
                functionsFound++;
            } else {
                console.log(`❌ دالة ${func}: مفقودة`);
                diagnostics.issues.push(`دالة ${func} مفقودة`);
            }
        });

        elements.forEach(elementId => {
            const element = document.getElementById(elementId);
            if (element) {
                console.log(`✅ عنصر ${elementId}: موجود`);
                elementsFound++;
            } else {
                console.log(`❌ عنصر ${elementId}: مفقود`);
                diagnostics.issues.push(`عنصر ${elementId} مفقود`);
            }
        });

        const cropperReady = functionsFound > 0 && elementsFound >= 2;
        console.log(`📊 [نتيجة Cropper] دوال: ${functionsFound}/${functions.length}, عناصر: ${elementsFound}/${elements.length}`);

        if (cropperReady) {
            diagnostics.fixes.push('نظام الـ Cropper جاهز');
        } else {
            diagnostics.issues.push('نظام الـ Cropper غير مكتمل');
        }

        return cropperReady;
    }

    // تقرير التشخيص النهائي
    function generateDiagnosticReport() {
        console.log('\n📋 [التقرير النهائي] تشخيص الخادم الحقيقي:');
        console.log('==========================================');

        console.log('📊 معلومات الجلسة:', {
            timestamp: diagnostics.timestamp,
            userAgent: diagnostics.userAgent.substring(0, 100) + '...',
            screenSize: diagnostics.screenSize,
            isMobile: diagnostics.isMobile,
            touchSupport: diagnostics.touchSupport
        });

        console.log('\n✅ الإصلاحات المطبقة:', diagnostics.fixes);
        console.log('\n❌ المشاكل المكتشفة:', diagnostics.issues);

        const successRate = (diagnostics.fixes.length / (diagnostics.fixes.length + diagnostics.issues.length)) * 100;
        console.log(`\n📈 معدل النجاح: ${successRate.toFixed(1)}%`);

        if (successRate >= 70) {
            console.log('🎉 النظام يعمل بشكل جيد على الخادم!');
        } else {
            console.log('⚠️ النظام يحتاج إلى مزيد من الإصلاحات');
        }

        console.log('==========================================');

        // حفظ النتائج في متغير عام
        window.serverDiagnostics = diagnostics;

        return diagnostics;
    }

    // تشغيل جميع الخطوات
    function runServerDiagnostics() {
        console.log('🚀 [بدء التشخيص] تشغيل تشخيص الخادم الحقيقي...');

        // الخطوة 1: كشف الجهاز
        detectDevice();

        // الخطوة 2: تنظيف المودالات
        cleanupModalBackdrops();

        // الخطوة 3: فرض إعادة تحميل الأنماط
        forceStyleReload();

        // الخطوة 4: إنشاء ملف تجريبي
        setTimeout(() => {
            createTestFile();
        }, 500);

        // الخطوة 5: فحص الـ Cropper
        setTimeout(() => {
            checkCropperFunctions();
        }, 1000);

        // الخطوة 6: التقرير النهائي
        setTimeout(() => {
            generateDiagnosticReport();
        }, 2000);
    }

    // بدء التشخيص
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runServerDiagnostics);
    } else {
        runServerDiagnostics();
    }

    // إعادة التشغيل عند تغيير حجم الشاشة
    window.addEventListener('resize', function() {
        console.log('📐 تغيير حجم الشاشة، إعادة تشغيل التشخيص...');
        setTimeout(runServerDiagnostics, 300);
    });

    // تصدير دالة للتشغيل اليدوي
    window.runServerDiagnostics = runServerDiagnostics;

})();
