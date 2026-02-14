# ═══════════════════════════════════════════════════════════════
# 🔍 مراقبة السجلات - v22:51
# ═══════════════════════════════════════════════════════════════

Clear-Host
Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║                                                              ║" -ForegroundColor Cyan
Write-Host "║        🔍 Live Logs - v22:51 Data Persistence Test         ║" -ForegroundColor Cyan
Write-Host "║                                                              ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host ""
Write-Host "🎯 What to Look For:" -ForegroundColor Yellow
Write-Host ""
Write-Host "   عند بدء التطبيق:" -ForegroundColor White
Write-Host "   ✅ APPLICATION STARTED - v22:51" -ForegroundColor Gray
Write-Host "   ✅ Canceling ALL old workers" -ForegroundColor Gray
Write-Host "   ✅ All old workers cancelled successfully" -ForegroundColor Gray
Write-Host "   ✅ WebStorage PRESERVED - user data safe" -ForegroundColor Green
Write-Host ""
Write-Host "   عند تصوير فيديو:" -ForegroundColor White
Write-Host "   ✅ Added new file to queue" -ForegroundColor Gray
Write-Host "   ✅ DIAGNOSTIC: About to call scheduleImmediateSync()" -ForegroundColor Gray
Write-Host "   ✅ CALLING FileSyncWorker.scheduleImmediateSync() NOW!" -ForegroundColor Gray
Write-Host "   ✅ [1/6] → [6/6] scheduling steps" -ForegroundColor Gray
Write-Host "   ✅ FileSyncWorker CONSTRUCTOR called" -ForegroundColor Gray
Write-Host "   ✅ FileSyncWorker.doWork() STARTED" -ForegroundColor Gray
Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host ""
Write-Host "🚀 Starting live log stream..." -ForegroundColor Green
Write-Host "   Press Ctrl+C to stop" -ForegroundColor DarkGray
Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host ""

# Start logging with filters
adb logcat -s `
    AutoUploadApp:* `
    MainActivity:* `
    FileSyncWorker:* `
    UploadServicePlugin:* `
    UploadDatabaseHelper:D `
    CameraActivity:*
