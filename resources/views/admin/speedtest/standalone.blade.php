@extends('admin.dashboard.toolbars.index')

@push('styles')
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .speedtest-container {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            padding: 20px;
        }

        .speedtest-container .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .speedtest-container .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .speedtest-container .header p {
            font-size: 1.1rem;
            margin-bottom: 0;
            opacity: 0.9;
        }

        .speedtest-container .speedtest-main {
            padding: 40px;
        }

        .speedtest-container .test-controls {
            text-align: center;
            margin-bottom: 40px;
        }

        .speedtest-container .start-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .speedtest-container .start-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .speedtest-container .start-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .speedtest-container .start-btn.running {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .speedtest-container .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .speedtest-container .metric-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .speedtest-container .metric-card:hover {
            transform: translateY(-5px);
        }

        .speedtest-container .metric-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        .speedtest-container .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .speedtest-container .metric-label {
            font-size: 1.1rem;
            color: #7f8c8d;
            font-weight: 500;
        }

        .speedtest-container .metric-unit {
            font-size: 1rem;
            color: #95a5a6;
        }

        .speedtest-container .progress-section {
            margin-bottom: 30px;
        }

        .speedtest-container .progress-bar {
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .speedtest-container .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 5px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .speedtest-container .progress-label {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
        }

        .speedtest-container .info-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
        }

        .speedtest-container .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .speedtest-container .info-item {
            text-align: center;
        }

        .speedtest-container .info-item i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .speedtest-container .info-item h5 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .speedtest-container .info-item p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .speedtest-container .status-indicator {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .speedtest-container .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .speedtest-container .status-testing {
            background: #fff3cd;
            color: #856404;
        }

        .speedtest-container .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .speedtest-container .results-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .speedtest-container .results-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .speedtest-container .results-header h3 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .speedtest-container .results-timestamp {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .speedtest-container .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .speedtest-container .alert {
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .speedtest-container .alert-info {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .speedtest-container .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }

        .speedtest-container .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        @media (max-width: 768px) {
            .speedtest-container .header h1 {
                font-size: 2rem;
            }

            .speedtest-container .speedtest-main {
                padding: 20px;
            }

            .speedtest-container .metrics-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .speedtest-container .metric-card {
                padding: 20px;
            }

            .speedtest-container .metric-value {
                font-size: 2rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="speedtest-container">
        <div class="header">
            <h1><i class="fas fa-tachometer-alt me-3"></i>اختبار سرعة الإنترنت</h1>
            <p>قياس دقيق وشامل لسرعة الاتصال بالإنترنت باستخدام LibreSpeed</p>
        </div>
        <div class="speedtest-main">
            <!-- Status Indicator -->
            <div class="text-center">
                <div id="statusIndicator" class="status-indicator status-ready">
                    <i class="fas fa-check-circle me-2"></i>
                    النظام جاهز للاختبار
                </div>
            </div>

            <!-- Test Controls -->
            <div class="test-controls">
                <button id="startBtn" class="start-btn" onclick="startSpeedTest()">
                    <i class="fas fa-play me-2"></i>
                    بدء الاختبار
                </button>
            </div>

            <!-- Progress Section -->
            <div id="progressSection" class="progress-section" style="display: none;">
                <div class="progress-label" id="progressLabel">جاري التحضير...</div>
                <div class="progress-bar">
                    <div id="progressFill" class="progress-fill"></div>
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div id="downloadSpeed" class="metric-value">--</div>
                    <div class="metric-label">التحميل</div>
                    <div class="metric-unit">Mbps</div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div id="uploadSpeed" class="metric-value">--</div>
                    <div class="metric-label">الرفع</div>
                    <div class="metric-unit">Mbps</div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div id="pingValue" class="metric-value">--</div>
                    <div class="metric-label">Ping</div>
                    <div class="metric-unit">ms</div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fas fa-wave-square"></i>
                    </div>
                    <div id="jitterValue" class="metric-value">--</div>
                    <div class="metric-label">Jitter</div>
                    <div class="metric-unit">ms</div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="info-section">
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-globe"></i>
                        <h5>عنوان IP</h5>
                        <p id="ipAddress">جاري التحديد...</p>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-server"></i>
                        <h5>الخادم</h5>
                        <p>خادم Laravel المحلي</p>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-shield-alt"></i>
                        <h5>الأمان</h5>
                        <p>اختبار آمن ومحلي</p>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-chart-line"></i>
                        <h5>الدقة</h5>
                        <p>قياس عالي الدقة</p>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" class="results-section" style="display: none;">
                <div class="results-header">
                    <h3><i class="fas fa-trophy me-2"></i>نتائج الاختبار</h3>
                    <div id="resultsTimestamp" class="results-timestamp"></div>
                </div>

                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>ملخص النتائج</h5>
                    <p id="resultsSummary" class="mb-0"></p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    <!-- Include LibreSpeed Integration -->
    <script src="{{ asset('js/real-speed-test-libre.js') }}"></script>
    <script>
        let isTestRunning = false;
        let testResults = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 تهيئة واجهة اختبار السرعة...');

            // Check system availability
            checkSystemStatus();

            // Get initial IP
            getInitialIP();
        });

        async function checkSystemStatus() {
            try {
                const response = await fetch('/admin/speedtest/stats');
                const data = await response.json();

                if (data.status === 'active') {
                    updateStatus('ready', 'النظام جاهز للاختبار');
                } else {
                    updateStatus('error', 'النظام غير متاح');
                }
            } catch (error) {
                console.error('خطأ في فحص حالة النظام:', error);
                updateStatus('error', 'خطأ في الاتصال بالخادم');
            }
        }

        async function getInitialIP() {
            try {
                const response = await fetch('/admin/speedtest/api?endpoint=getIP');
                const data = await response.json();

                if (data.processedString) {
                    document.getElementById('ipAddress').textContent = data.processedString;
                }
            } catch (error) {
                console.error('خطأ في الحصول على IP:', error);
                document.getElementById('ipAddress').textContent = 'غير متاح';
            }
        }

        function updateStatus(type, message) {
            const indicator = document.getElementById('statusIndicator');
            indicator.className = `status-indicator status-${type}`;

            const icons = {
                ready: 'fas fa-check-circle',
                testing: 'fas fa-spinner fa-spin',
                error: 'fas fa-exclamation-triangle'
            };

            indicator.innerHTML = `<i class="${icons[type]} me-2"></i>${message}`;
        }

        function updateProgress(percentage, label) {
            const progressSection = document.getElementById('progressSection');
            const progressFill = document.getElementById('progressFill');
            const progressLabel = document.getElementById('progressLabel');

            if (percentage > 0) {
                progressSection.style.display = 'block';
                progressFill.style.width = percentage + '%';
                progressLabel.textContent = label;
            } else {
                progressSection.style.display = 'none';
            }
        }

        function updateMetric(elementId, value, unit = '') {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = value + unit;
            }
        }

        async function startSpeedTest() {
            if (isTestRunning) {
                // Stop test
                isTestRunning = false;
                updateStatus('ready', 'تم إيقاف الاختبار');
                updateProgress(0, '');
                document.getElementById('startBtn').innerHTML = '<i class="fas fa-play me-2"></i>بدء الاختبار';
                document.getElementById('startBtn').className = 'start-btn';
                return;
            }

            // Start test
            isTestRunning = true;
            updateStatus('testing', 'جاري تشغيل الاختبار...');
            document.getElementById('startBtn').innerHTML = '<i class="fas fa-stop me-2"></i>إيقاف الاختبار';
            document.getElementById('startBtn').className = 'start-btn running';

            try {
                // Check if RealSpeedTestLibre is available
                if (typeof window.realSpeedTestLibre === 'undefined') {
                    throw new Error('نظام قياس السرعة غير متاح');
                }

                // Reset metrics
                updateMetric('downloadSpeed', '--');
                updateMetric('uploadSpeed', '--');
                updateMetric('pingValue', '--');
                updateMetric('jitterValue', '--');

                // Test IP
                updateProgress(10, 'فحص عنوان IP...');
                await window.realSpeedTestLibre.getClientIP();

                // Test Ping
                updateProgress(25, 'اختبار Ping...');
                const pingResults = await window.realSpeedTestLibre.testPing();
                if (pingResults && isTestRunning) {
                    updateMetric('pingValue', pingResults.ping.toFixed(0));
                    updateMetric('jitterValue', pingResults.jitter.toFixed(0));
                }

                // Test Download
                updateProgress(50, 'اختبار سرعة التحميل...');
                const downloadSpeed = await window.realSpeedTestLibre.testDownloadSpeed();
                if (downloadSpeed && isTestRunning) {
                    updateMetric('downloadSpeed', downloadSpeed.toFixed(1));
                }

                // Test Upload
                updateProgress(75, 'اختبار سرعة الرفع...');
                const uploadSpeed = await window.realSpeedTestLibre.testUploadSpeed();
                if (uploadSpeed && isTestRunning) {
                    updateMetric('uploadSpeed', uploadSpeed.toFixed(1));
                }

                // Complete
                updateProgress(100, 'اكتمل الاختبار');

                if (isTestRunning) {
                    testResults = {
                        download: downloadSpeed,
                        upload: uploadSpeed,
                        ping: pingResults.ping,
                        jitter: pingResults.jitter,
                        timestamp: new Date()
                    };

                    showResults();
                    updateStatus('ready', 'اكتمل الاختبار بنجاح');
                }

            } catch (error) {
                console.error('خطأ في الاختبار:', error);
                updateStatus('error', 'فشل الاختبار: ' + error.message);
            } finally {
                isTestRunning = false;
                document.getElementById('startBtn').innerHTML = '<i class="fas fa-play me-2"></i>بدء الاختبار';
                document.getElementById('startBtn').className = 'start-btn';
                updateProgress(0, '');
            }
        }

        function showResults() {
            const resultsSection = document.getElementById('resultsSection');
            const resultsTimestamp = document.getElementById('resultsTimestamp');
            const resultsSummary = document.getElementById('resultsSummary');

            resultsSection.style.display = 'block';

            resultsTimestamp.textContent = 'تم الاختبار في: ' + testResults.timestamp.toLocaleString('ar-EG');

            const summary = `
                سرعة التحميل: ${testResults.download.toFixed(1)} Mbps |
                سرعة الرفع: ${testResults.upload.toFixed(1)} Mbps |
                Ping: ${testResults.ping.toFixed(0)} ms |
                Jitter: ${testResults.jitter.toFixed(0)} ms
            `;

            resultsSummary.textContent = summary;

            // Scroll to results
            resultsSection.scrollIntoView({ behavior: 'smooth' });
        }

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && isTestRunning) {
                // Pause test if page is hidden
                console.log('تم إيقاف الاختبار مؤقتاً - الصفحة مخفية');
            }
        });
    </script>
@endpush
