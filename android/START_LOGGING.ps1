# ═══════════════════════════════════════════════════════════════════════════════
# 🔍 ASO APP - AUTOMATIC LOGGING SCRIPT
# ═══════════════════════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "╔═══════════════════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                    🔍 ASO APP - LIVE LOGGING MONITOR                          ║" -ForegroundColor Cyan
Write-Host "╚═══════════════════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# التحقق من اتصال الجهاز
Write-Host "📱 Checking device connection..." -ForegroundColor Yellow
$devices = adb devices | Select-String "device$"
if ($devices.Count -eq 0) {
    Write-Host "❌ No device connected! Please connect your Android device via USB." -ForegroundColor Red
    Write-Host "   1. Enable USB Debugging on your phone" -ForegroundColor Yellow
    Write-Host "   2. Connect USB cable" -ForegroundColor Yellow
    Write-Host "   3. Accept USB Debugging prompt on phone" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ Device connected!" -ForegroundColor Green
Write-Host ""

# مسح اللوجات القديمة
Write-Host "🧹 Clearing old logs..." -ForegroundColor Yellow
adb logcat -c
Write-Host "✅ Old logs cleared!" -ForegroundColor Green
Write-Host ""

# إنشاء اسم الملف مع timestamp
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$logFile = "aso_app_logs_$timestamp.txt"

Write-Host "╔═══════════════════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║                        ✅ LOGGING STARTED                                     ║" -ForegroundColor Green
Write-Host "╚═══════════════════════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Monitoring these components:" -ForegroundColor Cyan
Write-Host "   ✅ MainActivity" -ForegroundColor White
Write-Host "   ✅ UploadServicePlugin" -ForegroundColor White
Write-Host "   ✅ AutoUploadApp" -ForegroundColor White
Write-Host "   ✅ UploadTaskScheduler" -ForegroundColor White
Write-Host "   ✅ BackgroundUploadWorker" -ForegroundColor White
Write-Host "   ✅ UploadDatabaseHelper" -ForegroundColor White
Write-Host "   ✅ UploadBootReceiver" -ForegroundColor White
Write-Host "   ✅ JavaScript Console (chromium)" -ForegroundColor White
Write-Host ""
Write-Host "💾 Saving to: $logFile" -ForegroundColor Yellow
Write-Host ""
Write-Host "╔═══════════════════════════════════════════════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║  📸 NOW: Open the app and take a photo to see the logs                       ║" -ForegroundColor Magenta
Write-Host "║  🛑 Press Ctrl+C to stop logging                                             ║" -ForegroundColor Magenta
Write-Host "╚═══════════════════════════════════════════════════════════════════════════════╝" -ForegroundColor Magenta
Write-Host ""
Write-Host "════════════════════════════════ LIVE LOGS ════════════════════════════════════" -ForegroundColor Yellow
Write-Host ""

# بدء اللوجات مع الحفظ في الملف (Verbose = يسجل كل شيء)
adb logcat -v threadtime MainActivity:V UploadServicePlugin:V AutoUploadApp:V UploadTaskScheduler:V BackgroundUploadWorker:V UploadDatabaseHelper:V UploadBootReceiver:V chromium:V Console:V *:S | Tee-Object -FilePath $logFile
