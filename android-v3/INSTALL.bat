@echo off
adb uninstall com.aso.app
timeout /t 1 >nul
adb install "app\build\outputs\apk\debug\app-debug.apk"
timeout /t 1 >nul
adb logcat -c
adb shell am start -n com.aso.app/.MainActivity
echo DONE
