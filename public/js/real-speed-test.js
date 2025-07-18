/**
 * نظام قياس سرعة الإنترنت الحقيقي - يعتمد على SpeedTestController في Laravel
 * @author GitHub Copilot
 * @version 1.0.0
 * @license MIT
 */

class RealSpeedTest {
    constructor() {
        this.isDownloadTesting = false;
        this.isUploadTesting = false;
        this.autoTestEnabled = false;
        this.autoTestInterval = null;
        this.lastDownloadSpeed = 0;
        this.lastUploadSpeed = 0;
        this.systemReady = false;
        this.connectionType = this.detectConnectionType();

        this.endpoints = {
            run: '/api/speedtest/run',
            check: '/api/speedtest/check-availability',
            clearCache: '/api/speedtest/clear-cache'
        };

        // التحقق من جاهزية النظام
        this.checkSystemAvailability();
    }

    /**
     * تقدير نوع الاتصال
     */
    detectConnectionType() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

        if (connection) {
            return {
                type: connection.effectiveType || 'unknown',
                downlinkMax: connection.downlinkMax || 'unknown',
                saveData: connection.saveData || false,
                rtt: connection.rtt || 'unknown'
            };
        }

        return {
            type: 'unknown',
            downlinkMax: 'unknown',
            saveData: false,
            rtt: 'unknown'
        };
    }

    /**
     * التحقق من توفر نظام قياس السرعة في الخادم
     */
    async checkSystemAvailability() {
        try {
            const response = await fetch(this.endpoints.check);
            const data = await response.json();

            this.systemReady = data.available;
            console.log(`🌐 حالة نظام قياس السرعة:`, data);

            if (!this.systemReady) {
                this.updateStatus('downloadStatus', 'نظام القياس غير متوفر', false);
                this.updateStatus('uploadStatus', 'نظام القياس غير متوفر', false);
                console.warn('⚠️ نظام قياس السرعة غير متاح على الخادم');
            } else {
                this.updateStatus('downloadStatus', 'جاهز للقياس', true);
                this.updateStatus('uploadStatus', 'جاهز للقياس', true);
                console.log(`✅ نظام قياس السرعة جاهز - إصدار ${data.version || 'غير محدد'}`);
            }

            return this.systemReady;
        } catch (error) {
            console.error('❌ خطأ في التحقق من نظام قياس السرعة:', error);
            this.systemReady = false;
            this.updateStatus('downloadStatus', 'خطأ في الاتصال', false);
            this.updateStatus('uploadStatus', 'خطأ في الاتصال', false);
            return false;
        }
    }

    /**
     * تحديث حالة القياس
     */
    updateStatus(elementId, message, isReady = true) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = message;
            element.classList.toggle('text-danger', !isReady);
        }
    }

    /**
     * تحديث مؤشر العداد
     */
    updateGaugeNeedle(needleId, speed) {
        const needle = document.getElementById(needleId);
        if (needle) {
            // تحويل السرعة إلى زاوية (0-100 Mbps -> 0-270 درجة)
            const maxSpeed = 100;
            const angle = Math.min(speed, maxSpeed) * (270 / maxSpeed);
            needle.style.transform = `translate(-50%, -100%) rotate(${angle}deg)`;
        }
    }

    /**
     * تحديث قيمة السرعة المعروضة
     */
    updateSpeedDisplay(elementId, speed) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = speed.toFixed(1);
        }
    }

    /**
     * تحديث شريط التقدم
     */
    updateProgressBar(elementId, percentage) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.width = `${percentage}%`;
        }
    }

    /**
     * تحديث التفاصيل
     */
    updateDetails(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = text;
        }
    }

    /**
     * تحديث زر القياس
     */
    updateButton(iconId, textId, buttonId, isRunning = false) {
        const icon = document.getElementById(iconId);
        const text = document.getElementById(textId);
        const button = document.getElementById(buttonId);

        if (icon) {
            icon.className = isRunning ? 'fas fa-spinner fa-spin' : 'fas fa-play';
        }

        if (text) {
            text.textContent = isRunning ? 'جاري القياس...' : 'قياس';
        }

        if (button) {
            button.disabled = isRunning;
        }
    }

    /**
     * اختبار سرعة التنزيل
     */
    async testDownloadSpeed() {
        if (this.isDownloadTesting || !this.systemReady) return;

        try {
            this.isDownloadTesting = true;
            this.updateButton('downloadTestIcon', 'downloadTestText', 'downloadTestBtn', true);
            this.updateStatus('downloadStatus', 'جاري قياس سرعة التنزيل...', true);
            this.updateProgressBar('downloadProgressBar', 20);

            const startTime = performance.now();
            const response = await fetch(this.endpoints.run, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    type: 'download',
                    connection_info: this.connectionType
                })
            });

            // تحديث شريط التقدم خلال الانتظار
            this.updateProgressBar('downloadProgressBar', 60);

            const data = await response.json();
            const endTime = performance.now();
            const responseTime = (endTime - startTime) / 1000;

            console.log('📥 نتيجة اختبار سرعة التنزيل:', data);
            this.updateProgressBar('downloadProgressBar', 100);

            if (data.success || data.fallback_data) {
                // حفظ نتيجة السرعة
                const downloadSpeed = data.download_mbps || data.fallback_data?.download_mbps || 0;
                this.lastDownloadSpeed = downloadSpeed;

                // تحديث واجهة المستخدم
                this.updateSpeedDisplay('downloadSpeedValue', downloadSpeed);
                this.updateGaugeNeedle('downloadNeedle', downloadSpeed);

                // تحديث التفاصيل
                let speedQuality = 'ضعيفة';
                if (downloadSpeed > 50) speedQuality = 'ممتازة';
                else if (downloadSpeed > 25) speedQuality = 'جيدة جداً';
                else if (downloadSpeed > 10) speedQuality = 'جيدة';
                else if (downloadSpeed > 5) speedQuality = 'متوسطة';

                let details = `<strong>${downloadSpeed.toFixed(1)}</strong> Mbps - سرعة ${speedQuality}`;

                // إضافة معلومات إضافية إذا كانت متوفرة
                if (data.ping_ms || data.fallback_data?.ping_ms) {
                    const ping = data.ping_ms || data.fallback_data.ping_ms;
                    details += `<br>زمن الاستجابة: <strong>${ping.toFixed(0)}</strong> مللي ثانية`;
                }

                if (data.server || data.fallback_data?.server) {
                    const server = data.server || data.fallback_data.server;
                    details += `<br>الخادم: ${server}`;
                }

                if (data.cached) {
                    details += `<br><small class="text-warning"><i class="fas fa-database"></i> من ذاكرة التخزين المؤقت</small>`;
                }

                this.updateDetails('downloadDetails', details);
                this.updateStatus('downloadStatus', speedQuality, true);

                // إظهار إشعار
                this.showNotification('تم قياس سرعة التنزيل', `السرعة: ${downloadSpeed.toFixed(1)} Mbps`);
            } else {
                this.updateStatus('downloadStatus', 'فشل في القياس', false);
                this.updateDetails('downloadDetails', 'حدث خطأ أثناء قياس السرعة');
            }
        } catch (error) {
            console.error('❌ خطأ في اختبار سرعة التنزيل:', error);
            this.updateStatus('downloadStatus', 'خطأ في القياس', false);
            this.updateDetails('downloadDetails', 'تعذر الاتصال بخادم قياس السرعة');
        } finally {
            this.isDownloadTesting = false;
            this.updateButton('downloadTestIcon', 'downloadTestText', 'downloadTestBtn', false);
        }
    }

    /**
     * اختبار سرعة الرفع
     */
    async testUploadSpeed() {
        if (this.isUploadTesting || !this.systemReady) return;

        try {
            this.isUploadTesting = true;
            this.updateButton('uploadTestIcon', 'uploadTestText', 'uploadTestBtn', true);
            this.updateStatus('uploadStatus', 'جاري قياس سرعة الرفع...', true);
            this.updateProgressBar('uploadProgressBar', 20);

            const startTime = performance.now();
            const response = await fetch(this.endpoints.run, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    type: 'upload',
                    connection_info: this.connectionType
                })
            });

            // تحديث شريط التقدم خلال الانتظار
            this.updateProgressBar('uploadProgressBar', 60);

            const data = await response.json();
            const endTime = performance.now();
            const responseTime = (endTime - startTime) / 1000;

            console.log('📤 نتيجة اختبار سرعة الرفع:', data);
            this.updateProgressBar('uploadProgressBar', 100);

            if (data.success || data.fallback_data) {
                // حفظ نتيجة السرعة
                const uploadSpeed = data.upload_mbps || data.fallback_data?.upload_mbps || 0;
                this.lastUploadSpeed = uploadSpeed;

                // تحديث واجهة المستخدم
                this.updateSpeedDisplay('uploadSpeedValue', uploadSpeed);
                this.updateGaugeNeedle('uploadNeedle', uploadSpeed);

                // تحديث التفاصيل
                let speedQuality = 'ضعيفة';
                if (uploadSpeed > 20) speedQuality = 'ممتازة';
                else if (uploadSpeed > 10) speedQuality = 'جيدة جداً';
                else if (uploadSpeed > 5) speedQuality = 'جيدة';
                else if (uploadSpeed > 2) speedQuality = 'متوسطة';

                let details = `<strong>${uploadSpeed.toFixed(1)}</strong> Mbps - سرعة ${speedQuality}`;

                // إضافة معلومات إضافية إذا كانت متوفرة
                if (data.ping_ms || data.fallback_data?.ping_ms) {
                    const ping = data.ping_ms || data.fallback_data.ping_ms;
                    details += `<br>زمن الاستجابة: <strong>${ping.toFixed(0)}</strong> مللي ثانية`;
                }

                if (data.server || data.fallback_data?.server) {
                    const server = data.server || data.fallback_data.server;
                    details += `<br>الخادم: ${server}`;
                }

                if (data.cached) {
                    details += `<br><small class="text-warning"><i class="fas fa-database"></i> من ذاكرة التخزين المؤقت</small>`;
                }

                this.updateDetails('uploadDetails', details);
                this.updateStatus('uploadStatus', speedQuality, true);

                // إظهار إشعار
                this.showNotification('تم قياس سرعة الرفع', `السرعة: ${uploadSpeed.toFixed(1)} Mbps`);
            } else {
                this.updateStatus('uploadStatus', 'فشل في القياس', false);
                this.updateDetails('uploadDetails', 'حدث خطأ أثناء قياس السرعة');
            }
        } catch (error) {
            console.error('❌ خطأ في اختبار سرعة الرفع:', error);
            this.updateStatus('uploadStatus', 'خطأ في القياس', false);
            this.updateDetails('uploadDetails', 'تعذر الاتصال بخادم قياس السرعة');
        } finally {
            this.isUploadTesting = false;
            this.updateButton('uploadTestIcon', 'uploadTestText', 'uploadTestBtn', false);
        }
    }

    /**
     * بدء القياس التلقائي
     */
    startAutoTest(intervalMinutes = 5) {
        if (this.autoTestEnabled) return;

        this.autoTestEnabled = true;
        const intervalMs = intervalMinutes * 60 * 1000;

        // تشغيل القياس الأول
        this.runFullTest();

        // جدولة القياسات المستقبلية
        this.autoTestInterval = setInterval(() => {
            this.runFullTest();
        }, intervalMs);

        // إظهار إشعار
        this.showNotification(
            'القياس التلقائي',
            `تم تشغيل القياس التلقائي كل ${intervalMinutes} دقائق`,
            'info'
        );

        console.log(`🔄 تم تشغيل القياس التلقائي كل ${intervalMinutes} دقائق`);
    }

    /**
     * إيقاف القياس التلقائي
     */
    stopAutoTest() {
        if (!this.autoTestEnabled) return;

        this.autoTestEnabled = false;
        clearInterval(this.autoTestInterval);
        this.autoTestInterval = null;

        // إظهار إشعار
        this.showNotification(
            'القياس التلقائي',
            'تم إيقاف القياس التلقائي',
            'info'
        );

        console.log('⏹️ تم إيقاف القياس التلقائي');
    }

    /**
     * تشغيل قياس كامل (تنزيل ثم رفع)
     */
    async runFullTest() {
        if (!this.systemReady || this.isDownloadTesting || this.isUploadTesting) return;

        console.log('🚀 بدء قياس السرعة الكامل...');

        // قياس سرعة التنزيل أولاً
        await this.testDownloadSpeed();

        // انتظار قليلاً قبل قياس سرعة الرفع
        setTimeout(async () => {
            await this.testUploadSpeed();
            console.log('✅ اكتمل قياس السرعة الكامل');
        }, 2000);
    }

    /**
     * مسح ذاكرة التخزين المؤقت
     */
    async clearCache() {
        try {
            const response = await fetch(this.endpoints.clearCache, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });

            const data = await response.json();

            if (data.success) {
                console.log('🧹 تم مسح ذاكرة التخزين المؤقت بنجاح');
                this.showNotification('تم مسح ذاكرة التخزين المؤقت', 'سيتم إجراء القياس التالي من الخادم مباشرة', 'info');
            }
        } catch (error) {
            console.error('❌ خطأ في مسح ذاكرة التخزين المؤقت:', error);
        }
    }

    /**
     * إظهار إشعار
     */
    showNotification(title, message, type = 'success') {
        // إذا كان نظام الإشعارات متاحاً
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: message,
                icon: '/favicon.ico'
            });
            return;
        }

        // إنشاء إشعار مخصص
        const notification = document.createElement('div');
        notification.className = 'speed-test-notification';

        let bgColor = '#28a745'; // أخضر للنجاح
        let icon = 'fa-check-circle';

        if (type === 'warning') {
            bgColor = '#ffc107'; // أصفر للتحذير
            icon = 'fa-exclamation-triangle';
        } else if (type === 'error') {
            bgColor = '#dc3545'; // أحمر للخطأ
            icon = 'fa-times-circle';
        } else if (type === 'info') {
            bgColor = '#17a2b8'; // أزرق للمعلومات
            icon = 'fa-info-circle';
        }

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            max-width: 300px;
            animation: slideInRight 0.3s ease forwards;
        `;

        notification.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 5px;">
                <i class="fas ${icon}"></i> ${title}
            </div>
            <div>${message}</div>
        `;

        document.body.appendChild(notification);

        // إزالة الإشعار بعد 5 ثوان
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }

    /**
     * تشغيل اختبار الأداء الأساسي
     */
    measureBaselinePerformance() {
        return new Promise(resolve => {
            const startTime = performance.now();
            const testArray = new Array(1000000).fill(0);

            // إجراء بعض العمليات لقياس أداء المتصفح
            for (let i = 0; i < 1000; i++) {
                testArray[i] = Math.sqrt(i * 1000);
            }

            const endTime = performance.now();
            const duration = endTime - startTime;

            // حساب مؤشر الأداء
            const performanceScore = Math.min(100, Math.max(0, 100 - (duration / 20)));

            console.log(`🔍 مؤشر أداء المتصفح: ${performanceScore.toFixed(0)}/100`);
            resolve(performanceScore);
        });
    }
}

// أنيميشن CSS للإشعارات
const styleElement = document.createElement('style');
styleElement.textContent = `
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
`;
document.head.appendChild(styleElement);
