@echo off
REM ═══════════════════════════════════════════════════════════════════════════════
REM 🔍 ASO APP - AUTOMATIC LOGGING SCRIPT
REM ═══════════════════════════════════════════════════════════════════════════════

echo.
echo ╔═══════════════════════════════════════════════════════════════════════════════╗
echo ║                    🔍 ASO APP - LIVE LOGGING MONITOR                          ║
echo ╚═══════════════════════════════════════════════════════════════════════════════╝
echo.

REM التحقق من اتصال الجهاز
echo 📱 Checking device connection...
adb devices | find "device" > nul
if errorlevel 1 (
    echo ❌ No device connected! Please connect your Android device via USB.
    echo    1. Enable USB Debugging on your phone
    echo    2. Connect USB cable
    echo    3. Accept USB Debugging prompt on phone
    pause
    exit /b 1
)

echo ✅ Device connected!
echo.

REM مسح اللوجات القديمة
echo 🧹 Clearing old logs...
adb logcat -c
echo ✅ Old logs cleared!
echo.

REM إنشاء اسم الملف
set logfile=aso_app_logs_%date:~-4,4%-%date:~-7,2%-%date:~-10,2%_%time:~0,2%-%time:~3,2%-%time:~6,2%.txt
set logfile=%logfile: =0%

echo ╔═══════════════════════════════════════════════════════════════════════════════╗
echo ║                        ✅ LOGGING STARTED                                     ║
echo ╚═══════════════════════════════════════════════════════════════════════════════╝
echo.
echo 📋 Monitoring these components:
echo    ✅ MainActivity
echo    ✅ UploadServicePlugin
echo    ✅ AutoUploadApp
echo    ✅ UploadTaskScheduler
echo    ✅ BackgroundUploadWorker
echo    ✅ UploadDatabaseHelper
echo    ✅ UploadBootReceiver
echo    ✅ JavaScript Console (chromium)
echo.
echo 💾 Saving to: %logfile%
echo.
echo ╔═══════════════════════════════════════════════════════════════════════════════╗
echo ║  📸 NOW: Open the app and take a photo to see the logs                       ║
echo ║  🛑 Press Ctrl+C to stop logging                                             ║
echo ╚═══════════════════════════════════════════════════════════════════════════════╝
echo.
echo ════════════════════════════════ LIVE LOGS ════════════════════════════════════
echo.

REM بدء اللوجات
adb logcat -v threadtime MainActivity:E UploadServicePlugin:E AutoUploadApp:E UploadTaskScheduler:E BackgroundUploadWorker:E UploadDatabaseHelper:E UploadBootReceiver:E chromium:I *:S > %logfile%
