/**
 * Safe Area Keyboard Handler
 * معالجة ظهور/اختفاء الكيبورد مع الحفاظ على Safe Area
 *
 * السلوك المطلوب:
 * - الشريط العلوي (safe-area-top): يبقى ثابتاً دائماً
 * - الشريط السفلي (safe-area-bottom): يختفي عند ظهور الكيبورد
 */

(function() {
    'use strict';

    // التحقق من وجود Capacitor Keyboard Plugin
    const hasCapacitorKeyboard = window.Capacitor &&
                                  window.Capacitor.Plugins &&
                                  window.Capacitor.Plugins.Keyboard;

    // الحصول على عناصر Safe Area
    function getSafeAreaElements() {
        return {
            top: document.querySelector('.safe-area-top'),
            bottom: document.querySelector('.safe-area-bottom'),
            body: document.body
        };
    }

    // تطبيق حالة ظهور الكيبورد - إخفاء الشريط السفلي
    function handleKeyboardShow(keyboardHeight) {
        const elements = getSafeAreaElements();

        // تعيين ارتفاع الكيبورد كمتغير CSS
        document.documentElement.style.setProperty('--keyboard-height', keyboardHeight + 'px');

        // إضافة class للجسم
        if (elements.body) {
            elements.body.classList.add('keyboard-visible');
        }

        // ⚠️ إخفاء الشريط السفلي تماماً عند ظهور الكيبورد
        if (elements.bottom) {
            elements.bottom.classList.add('keyboard-visible');
            elements.bottom.style.display = 'none';
        }

        // التأكد من أن الشريط العلوي يبقى ثابتاً
        if (elements.top) {
            elements.top.style.position = 'fixed';
            elements.top.style.top = '0';
        }

        console.log('⌨️ Keyboard shown, bottom bar hidden, height:', keyboardHeight);
    }

    // تطبيق حالة اختفاء الكيبورد - إظهار الشريط السفلي
    function handleKeyboardHide() {
        const elements = getSafeAreaElements();

        // إزالة متغير ارتفاع الكيبورد
        document.documentElement.style.setProperty('--keyboard-height', '0px');

        // إزالة class من الجسم
        if (elements.body) {
            elements.body.classList.remove('keyboard-visible');
        }

        // ⚠️ إظهار الشريط السفلي مرة أخرى
        if (elements.bottom) {
            elements.bottom.classList.remove('keyboard-visible');
            elements.bottom.style.display = 'block';
            elements.bottom.style.bottom = '0';
        }

        console.log('⌨️ Keyboard hidden, bottom bar visible');
    }

    // معالجة تغيير حجم النافذة (للمتصفح العادي)
    function handleResize() {
        // حساب الارتفاع المتاح
        const windowHeight = window.innerHeight;
        const screenHeight = window.screen.height;

        // إذا كان الارتفاع المتاح أقل بكثير من ارتفاع الشاشة، فالكيبورد ظاهر
        const heightDiff = screenHeight - windowHeight;

        if (heightDiff > 150) { // الكيبورد ظاهر (عادة أكثر من 150 بكسل)
            handleKeyboardShow(heightDiff);
        } else {
            handleKeyboardHide();
        }
    }

    // معالجة focus على حقول الإدخال
    function handleInputFocus(event) {
        const target = event.target;
        const tagName = target.tagName.toLowerCase();

        // التحقق من أن العنصر هو حقل إدخال
        if (tagName === 'input' || tagName === 'textarea' || target.isContentEditable) {
            // انتظار ظهور الكيبورد
            setTimeout(() => {
                // تمرير العنصر إلى المنظور المرئي
                if (target.scrollIntoView) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 300);
        }
    }

    // معالجة blur على حقول الإدخال
    function handleInputBlur(event) {
        // انتظار اختفاء الكيبورد
        setTimeout(() => {
            if (!hasCapacitorKeyboard) {
                handleKeyboardHide();
            }
        }, 100);
    }

    // التهيئة
    function init() {
        console.log('🛡️ Safe Area Keyboard Handler initialized');

        // استخدام Capacitor Keyboard Plugin إذا كان متاحاً
        if (hasCapacitorKeyboard) {
            const Keyboard = window.Capacitor.Plugins.Keyboard;

            // الاستماع لأحداث الكيبورد من Capacitor
            Keyboard.addListener('keyboardWillShow', (info) => {
                handleKeyboardShow(info.keyboardHeight);
            });

            Keyboard.addListener('keyboardWillHide', () => {
                handleKeyboardHide();
            });

            Keyboard.addListener('keyboardDidShow', (info) => {
                // تأكيد إضافي بعد ظهور الكيبورد
                handleKeyboardShow(info.keyboardHeight);
            });

            Keyboard.addListener('keyboardDidHide', () => {
                // تأكيد إضافي بعد اختفاء الكيبورد
                handleKeyboardHide();
            });

            console.log('✅ Using Capacitor Keyboard Plugin');
        } else {
            // استخدام طرق بديلة للمتصفح العادي

            // الاستماع لتغيير حجم النافذة
            window.addEventListener('resize', handleResize);

            // الاستماع لأحداث visualViewport (أكثر دقة)
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    const viewportHeight = window.visualViewport.height;
                    const windowHeight = window.innerHeight;
                    const keyboardHeight = windowHeight - viewportHeight;

                    if (keyboardHeight > 100) {
                        handleKeyboardShow(keyboardHeight);
                    } else {
                        handleKeyboardHide();
                    }
                });
            }

            console.log('⚠️ Using fallback keyboard detection');
        }

        // الاستماع لأحداث focus و blur على حقول الإدخال
        document.addEventListener('focusin', handleInputFocus, true);
        document.addEventListener('focusout', handleInputBlur, true);

        // التأكد من أن Safe Area elements موجودة ومُعدة بشكل صحيح
        ensureSafeAreaElements();
    }

    // التأكد من وجود عناصر Safe Area وإنشائها إذا لم تكن موجودة
    function ensureSafeAreaElements() {
        let safeAreaTop = document.querySelector('.safe-area-top');
        let safeAreaBottom = document.querySelector('.safe-area-bottom');

        // إضافة CSS inline لضمان الظهور
        const topStyles = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 30px !important;
            background: linear-gradient(135deg, #2a8b8b, #1e6b6b) !important;
            z-index: 2147483647 !important;
            display: block !important;
            visibility: visible !important;
            pointer-events: none;
        `;

        const bottomStyles = `
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 45px !important;
            background: linear-gradient(135deg, #2a8b8b, #1e6b6b) !important;
            z-index: 2147483647 !important;
            display: block !important;
            visibility: visible !important;
            pointer-events: none;
        `;

        if (!safeAreaTop) {
            safeAreaTop = document.createElement('div');
            safeAreaTop.className = 'safe-area-top';
            document.body.insertBefore(safeAreaTop, document.body.firstChild);
            console.log('✅ Created .safe-area-top element');
        }
        // تطبيق CSS مباشرة لضمان الظهور
        safeAreaTop.style.cssText = topStyles;

        if (!safeAreaBottom) {
            safeAreaBottom = document.createElement('div');
            safeAreaBottom.className = 'safe-area-bottom';
            document.body.appendChild(safeAreaBottom);
            console.log('✅ Created .safe-area-bottom element');
        }
        // تطبيق CSS مباشرة لضمان الظهور
        safeAreaBottom.style.cssText = bottomStyles;

        // التأكد من padding للـ body
        document.body.style.paddingTop = '30px';
        document.body.style.paddingBottom = '45px';

        console.log('✅ Safe area elements styled and ready');
    }

    // تشغيل التهيئة عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ⚠️ إعادة التأكيد على safe-area بشكل دوري (كل 500ms لأول 5 ثوانٍ)
    let safeAreaCheckCount = 0;
    const safeAreaInterval = setInterval(() => {
        ensureSafeAreaElements();
        safeAreaCheckCount++;
        if (safeAreaCheckCount >= 10) {
            clearInterval(safeAreaInterval);
            console.log('✅ Safe area check completed');
        }
    }, 500);

    // ⚠️ مراقبة تغييرات DOM لضمان بقاء safe-area
    const observer = new MutationObserver(() => {
        const bottom = document.querySelector('.safe-area-bottom');
        // تم إزالة الفحص !bottom.offsetParent لأنه دائماً يرجع true للعناصر ذات position: fixed
        // مما يؤدي إلى حلقة لا نهائية (Infinite Loop) وتوقف التطبيق (ANR)
        if (!bottom || bottom.style.display === 'none') {
            // إذا اختفى الشريط السفلي، أعد إنشاءه
            if (!document.body.classList.contains('keyboard-visible')) {
                ensureSafeAreaElements();
            }
        }
    });

    // بدء المراقبة
    setTimeout(() => {
        observer.observe(document.body, { childList: true, subtree: true, attributes: true });
    }, 1000);

    // تصدير الدوال للاستخدام الخارجي
    window.SafeAreaKeyboard = {
        show: handleKeyboardShow,
        hide: handleKeyboardHide,
        ensureElements: ensureSafeAreaElements,
        forceShowBottom: function() {
            const bottom = document.querySelector('.safe-area-bottom');
            if (bottom) {
                bottom.style.cssText = `
                    position: fixed !important;
                    bottom: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    height: 45px !important;
                    background: linear-gradient(135deg, #2a8b8b, #1e6b6b) !important;
                    z-index: 2147483647 !important;
                    display: block !important;
                    visibility: visible !important;
                    pointer-events: none;
                `;
            }
        }
    };

})();
