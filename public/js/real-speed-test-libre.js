/**
 * نظام قياس سرعة الإنترنت الحقيقي - يعتمد على SpeedTestController في Laravel
 * @author GitHub Copilot
 * @version 2.0.0
 * @license MIT
 */

class RealSpeedTestLibre {
    constructor() {
        this.baseUrl = window.location.origin;
        this.apiUrl = this.baseUrl + '/admin/speedtest/api';
        this.isDownloadTesting = false;
        this.isUploadTesting = false;
        this.isPingTesting = false;
        this.testResults = {
            download: 0,
            upload: 0,
            ping: 0,
            jitter: 0,
            ip: 'Unknown'
        };
        this.systemAvailable = false;

        // Initialize system
        this.init();
    }

    async init() {
        console.log('🚀 تهيئة نظام قياس السرعة الحقيقي...');

        try {
            // Check if system is available
            const response = await fetch(this.baseUrl + '/admin/speedtest/stats');
            const data = await response.json();

            if (data.status === 'active') {
                this.systemAvailable = true;
                console.log('✅ نظام قياس السرعة متاح ويعمل');

                // Get initial IP
                await this.getClientIP();
            } else {
                console.warn('⚠️ نظام قياس السرعة غير متاح');
            }
        } catch (error) {
            console.error('❌ خطأ في تهيئة نظام قياس السرعة:', error);
        }
    }

    async checkSystemAvailability() {
        try {
            const response = await fetch(this.baseUrl + '/admin/speedtest/stats');
            const data = await response.json();
            return data.status === 'active';
        } catch (error) {
            console.error('خطأ في فحص توفر النظام:', error);
            return false;
        }
    }

    async getClientIP() {
        try {
            const response = await fetch(this.apiUrl + '?endpoint=getIP');
            const data = await response.json();

            if (data.processedString) {
                this.testResults.ip = data.processedString;
                console.log('📍 عنوان IP:', this.testResults.ip);
            }

            return data;
        } catch (error) {
            console.error('خطأ في الحصول على عنوان IP:', error);
            return null;
        }
    }

    async testDownloadSpeed() {
        if (this.isDownloadTesting || !this.systemAvailable) return;

        this.isDownloadTesting = true;
        console.log('📥 بدء اختبار سرعة التحميل...');

        try {
            const testSizes = [1, 5, 10, 25]; // MB
            const results = [];

            for (const size of testSizes) {
                const chunkCount = size * 4; // 4 chunks per MB
                const url = `${this.apiUrl}?endpoint=garbage&ckSize=${chunkCount}`;

                const testStart = performance.now();
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });

                // Read the response
                const reader = response.body.getReader();
                let receivedLength = 0;

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    receivedLength += value.length;
                }

                const testEnd = performance.now();
                const testDuration = (testEnd - testStart) / 1000; // seconds
                const speed = (receivedLength * 8) / (testDuration * 1000000); // Mbps

                results.push(speed);
                console.log(`📊 اختبار ${size}MB: ${speed.toFixed(2)} Mbps`);
            }

            // Calculate average speed
            const avgSpeed = results.reduce((a, b) => a + b, 0) / results.length;
            this.testResults.download = avgSpeed;

            console.log(`✅ سرعة التحميل: ${avgSpeed.toFixed(2)} Mbps`);
            return avgSpeed;

        } catch (error) {
            console.error('❌ خطأ في اختبار التحميل:', error);
            return 0;
        } finally {
            this.isDownloadTesting = false;
        }
    }

    async testUploadSpeed() {
        if (this.isUploadTesting || !this.systemAvailable) return;

        this.isUploadTesting = true;
        console.log('📤 بدء اختبار سرعة الرفع...');

        try {
            const testSizes = [1, 2, 5]; // MB
            const results = [];

            for (const size of testSizes) {
                // Generate test data safely - create chunks to avoid crypto limit
                const chunkSize = 65536; // 64KB - max crypto.getRandomValues() limit
                const totalBytes = size * 1024 * 1024; // MB to bytes
                const chunks = Math.ceil(totalBytes / chunkSize);
                const testData = new Uint8Array(totalBytes);

                // Fill data in chunks to avoid crypto limit
                for (let i = 0; i < chunks; i++) {
                    const start = i * chunkSize;
                    const end = Math.min(start + chunkSize, totalBytes);
                    const chunk = new Uint8Array(end - start);
                    crypto.getRandomValues(chunk);
                    testData.set(chunk, start);
                }

                const formData = new FormData();
                formData.append('data', new Blob([testData]));
                formData.append('endpoint', 'empty');

                const testStart = performance.now();
                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });

                await response.text(); // Consume response
                const testEnd = performance.now();

                const testDuration = (testEnd - testStart) / 1000; // seconds
                const speed = (testData.length * 8) / (testDuration * 1000000); // Mbps

                results.push(speed);
                console.log(`📊 اختبار رفع ${size}MB: ${speed.toFixed(2)} Mbps`);
            }

            // Calculate average speed
            const avgSpeed = results.reduce((a, b) => a + b, 0) / results.length;
            this.testResults.upload = avgSpeed;

            console.log(`✅ سرعة الرفع: ${avgSpeed.toFixed(2)} Mbps`);
            return avgSpeed;

        } catch (error) {
            console.error('❌ خطأ في اختبار الرفع:', error);
            return 0;
        } finally {
            this.isUploadTesting = false;
        }
    }

    async testPing() {
        if (this.isPingTesting || !this.systemAvailable) return;

        this.isPingTesting = true;
        console.log('🏓 بدء اختبار الـ Ping...');

        try {
            const pingResults = [];
            const testCount = 10;

            for (let i = 0; i < testCount; i++) {
                const startTime = performance.now();

                const response = await fetch(this.apiUrl + '?endpoint=empty', {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });

                await response.text(); // Consume response
                const endTime = performance.now();

                const pingTime = endTime - startTime;
                pingResults.push(pingTime);

                // Small delay between pings
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            // Calculate average ping
            const avgPing = pingResults.reduce((a, b) => a + b, 0) / pingResults.length;
            this.testResults.ping = avgPing;

            // Calculate jitter
            const jitter = this.calculateJitter(pingResults);
            this.testResults.jitter = jitter;

            console.log(`✅ متوسط الـ Ping: ${avgPing.toFixed(2)} ms`);
            console.log(`✅ الـ Jitter: ${jitter.toFixed(2)} ms`);

            return { ping: avgPing, jitter: jitter };

        } catch (error) {
            console.error('❌ خطأ في اختبار الـ Ping:', error);
            return { ping: 0, jitter: 0 };
        } finally {
            this.isPingTesting = false;
        }
    }

    calculateJitter(pingResults) {
        if (pingResults.length < 2) return 0;

        const differences = [];
        for (let i = 1; i < pingResults.length; i++) {
            differences.push(Math.abs(pingResults[i] - pingResults[i-1]));
        }

        return differences.reduce((a, b) => a + b, 0) / differences.length;
    }

    async runFullTest() {
        if (!this.systemAvailable) {
            console.warn('⚠️ النظام غير متاح - لا يمكن تشغيل الاختبار');
            return null;
        }

        console.log('🎯 بدء الاختبار الكامل...');

        try {
            // Update IP first
            await this.getClientIP();

            // Run tests sequentially
            const pingResults = await this.testPing();
            const downloadSpeed = await this.testDownloadSpeed();
            const uploadSpeed = await this.testUploadSpeed();

            const results = {
                download: downloadSpeed,
                upload: uploadSpeed,
                ping: pingResults.ping,
                jitter: pingResults.jitter,
                ip: this.testResults.ip,
                timestamp: new Date().toISOString()
            };

            console.log('🏆 نتائج الاختبار الكاملة:', results);

            // Update UI if elements exist
            this.updateUI(results);

            return results;

        } catch (error) {
            console.error('❌ خطأ في الاختبار الكامل:', error);
            return null;
        }
    }

    updateUI(results) {
        // Update download speed display
        const downloadElement = document.getElementById('downloadSpeedValue');
        if (downloadElement) {
            downloadElement.textContent = results.download.toFixed(1);
        }

        // Update upload speed display
        const uploadElement = document.getElementById('uploadSpeedValue');
        if (uploadElement) {
            uploadElement.textContent = results.upload.toFixed(1);
        }

        // Update ping display
        const pingElement = document.getElementById('pingValue');
        if (pingElement) {
            pingElement.textContent = results.ping.toFixed(0);
        }

        // Update jitter display
        const jitterElement = document.getElementById('jitterValue');
        if (jitterElement) {
            jitterElement.textContent = results.jitter.toFixed(0);
        }

        // Update IP display
        const ipElement = document.getElementById('ipValue');
        if (ipElement) {
            ipElement.textContent = results.ip;
        }
    }

    // Public API methods
    async testDownloadSpeedPublic() {
        return await this.testDownloadSpeed();
    }

    async testUploadSpeedPublic() {
        return await this.testUploadSpeed();
    }

    async testPingPublic() {
        return await this.testPing();
    }

    getResults() {
        return { ...this.testResults };
    }

    isSystemAvailable() {
        return this.systemAvailable;
    }
}

// Create global instance
window.realSpeedTestLibre = new RealSpeedTestLibre();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealSpeedTestLibre;
}
