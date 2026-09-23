@echo off
chcp 65001 >nul
echo.
echo ═══════════════════════════════════════════════════════════════
echo 🔥 تثبيت نظيف لـ v22:50 - WORKER CLEANUP VERSION 🔥
echo ═══════════════════════════════════════════════════════════════
echo.
echo 📋 التغييرات في v22:50:
echo    🧹 cancelAllWork() - إلغاء جميع Workers القديمة
echo    🔍 سجلات تشخيصية قبل/بعد scheduleImmediateSync()
echo    🔍 سجلات في UploadServicePlugin
echo    🔍 سجلات في CameraActivity
echo    ✅ سيظهر بالضبط أي كود يعمل!
echo.
pause
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo 📱 التحقق من اتصال الجهاز...
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
adb devices
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo 🗑️  الخطوة 1/4: إزالة التطبيق القديم (مع حذف البيانات)
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
adb uninstall com.aso.app
timeout /t 2 >nul
echo ✅ تم حذف التطبيق القديم
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo 📦 الخطوة 2/4: تثبيت v22:50
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
adb install "app\build\outputs\apk\debug\app-debug.apk"
timeout /t 2 >nul
echo ✅ تم تثبيت v22:50
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo 🧹 الخطوة 3/4: مسح السجلات القديمة
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
adb logcat -c
echo ✅ تم مسح السجلات
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo 🚀 الخطوة 4/4: تشغيل التطبيق
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
adb shell am start -n com.aso.app/.MainActivity
timeout /t 2 >nul
echo ✅ تم تشغيل التطبيق
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║  ✅ التثبيت مكتمل - البيانات القديمة محذوفة بالكامل!     ║
echo ║  📋 الخطوات التالية:                                      ║
echo ║     1. افتح التطبيق                                        ║
echo ║     2. صوّر فيديو جديد                                     ║
echo ║     3. انتظر 5 ثواني                                       ║
echo ║     4. شاهد السجلات في نافذة PowerShell الأخرى            ║
echo ╚════════════════════════════════════════════════════════════╝
echo.
echo 🔍 السجلات المتوقعة (يجب أن تظهر بالترتيب):
echo    ✅ APP STARTED - v22:50
echo    ✅ Canceling ALL old workers
echo    ✅ Old workers cancelled
echo    ... المستخدم يصور فيديو ...
echo    ✅ Added new file to queue
echo    ✅ 🔍🔍🔍 DIAGNOSTIC: About to call scheduleImmediateSync()
echo    ✅ 🚀🚀🚀 CALLING FileSyncWorker.scheduleImmediateSync() NOW!
echo    ✅ scheduleImmediateSync() RETURNED
echo    ✅ 📤 [1/6] scheduleImmediateSync() CALLED
echo    ✅ 📤 [2/6] Network available NOW: true
echo    ✅ 📤 [3/6] Constraints: NONE
echo    ✅ 📤 [4/6] Work request created
echo    ✅ 📤 [5/6] Work enqueued
echo    ✅ 📤 [6/6] Work name: file_sync_orchestrator
echo    ✅ 🏭 FileSyncWorker CONSTRUCTOR called
echo    ✅ 🔄 FileSyncWorker.doWork() STARTED
echo    ✅ Upload proceeds...
echo.
echo ⚠️  إذا لم تظهر السجلات أعلاه - هناك مشكلة مختلفة!
echo.
pause
