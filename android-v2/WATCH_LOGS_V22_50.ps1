# ═══════════════════════════════════════════════════════════════
# 🔍 جمع سجلات v22:50 - DIAGNOSTIC LOGGING
# ═══════════════════════════════════════════════════════════════

Write-Host ""
Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║  🔍 Live Logging - v22:50 Worker Cleanup Test            ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""
Write-Host "📋 Filters:" -ForegroundColor Yellow
Write-Host "   - FileSyncWorker (all logs)" -ForegroundColor Gray
Write-Host "   - UploadServicePlugin (all logs)" -ForegroundColor Gray
Write-Host "   - UploadDatabaseHelper (all logs)" -ForegroundColor Gray
Write-Host "   - CameraActivity (all logs)" -ForegroundColor Gray
Write-Host "   - AutoUploadApp (all logs)" -ForegroundColor Gray
Write-Host ""
Write-Host "🎯 What to Look For:" -ForegroundColor Yellow
Write-Host "   1. 🧹 'Canceling ALL old workers' - should appear on app start" -ForegroundColor Gray
Write-Host "   2. 🔍 'DIAGNOSTIC: About to call scheduleImmediateSync()' - before scheduling" -ForegroundColor Gray
Write-Host "   3. 🚀 'CALLING FileSyncWorker.scheduleImmediateSync() NOW!' - actual call" -ForegroundColor Gray
Write-Host "   4. 📤 '[1/6]→[6/6]' - scheduling steps" -ForegroundColor Gray
Write-Host "   5. 🏭 'FileSyncWorker CONSTRUCTOR' - Worker created" -ForegroundColor Gray
Write-Host "   6. 🔄 'FileSyncWorker.doWork() STARTED' - Worker running" -ForegroundColor Gray
Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host "🚀 Starting live log stream..." -ForegroundColor Green
Write-Host "   Press Ctrl+C to stop" -ForegroundColor DarkGray
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host ""

# Start logging
adb logcat -s `
    FileSyncWorker:* `
    UploadServicePlugin:* `
    UploadDatabaseHelper:* `
    CameraActivity:* `
    AutoUploadApp:* `
    MainActivity:*
