@echo off
chcp 65001 >nul
cls
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║                                                              ║
echo ║   🔥🔥🔥   تثبيت v22:51 - DATA PERSISTENCE FIX   🔥🔥🔥      ║
echo ║                                                              ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo 📋 الإصلاحات الحاسمة في v22:51:
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo.
echo    💾 حفظ البيانات - IndexedDB لن تُحذف بعد الآن!
echo    🧹 إلغاء Workers القديمة عند بدء التطبيق
echo    🔍 سجلات واضحة جداً لتتبع المشاكل
echo.
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo.
pause
echo.
echo [1/5] التحقق من اتصال الجهاز...
adb devices
echo.
echo [2/5] حذف التطبيق القديم بالكامل...
adb uninstall com.aso.app
timeout /t 1 >nul
echo ✅ تم
echo.
echo [3/5] تثبيت v22:51...
adb install "app\build\outputs\apk\debug\app-debug.apk"
timeout /t 1 >nul
echo ✅ تم
echo.
echo [4/5] مسح السجلات...
adb logcat -c
echo ✅ تم
echo.
echo [5/5] تشغيل التطبيق...
adb shell am start -n com.aso.app/.MainActivity
timeout /t 2 >nul
echo ✅ تم
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║  ✅ التثبيت مكتمل!                                          ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.
echo 🔍 السجلات المتوقعة عند فتح التطبيق:
echo.
echo    ╔═══════════════════════════════════════════════════════════╗
echo    ║   🚨🚨🚨   APPLICATION STARTED - v22:51   🚨🚨🚨           ║
echo    ║   🔥 DATA PERSISTENCE FIX + WORKER CLEANUP! 🔥            ║
echo    ╚═══════════════════════════════════════════════════════════╝
echo.
echo    📋 v22:51 Critical Fixes:
echo       💾 REMOVED deleteAllData() - IndexedDB now PERSISTS!
echo       🧹 cancelAllWork() on startup - clean Workers
echo.
echo ⚠️  إذا لم ترَ هذه السجلات → التطبيق لم يُثبّت!
echo.
echo 📌 الخطوة التالية:
echo    1. افتح نافذة PowerShell جديدة
echo    2. نفذ: .\WATCH_LOGS.ps1
echo    3. صوّر فيديو في التطبيق
echo    4. راقب السجلات
echo.
pause
