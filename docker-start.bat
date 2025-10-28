@echo off
echo ======================================
echo   البدء السريع لمشروع Laravel
echo ======================================
echo.

echo [1/3] بناء الحاويات...
docker-compose build

echo.
echo [2/3] تشغيل الحاويات...
docker-compose up -d

echo.
echo [3/3] انتظار MySQL...
timeout /t 15 /nobreak >nul

echo.
echo ======================================
echo تم تشغيل الحاويات بنجاح!
echo ======================================
echo.
echo يمكنك الآن الوصول إلى:
echo - التطبيق: http://localhost:8000
echo - phpMyAdmin: http://localhost:8080
echo.
echo للإعداد الكامل، قم بتشغيل: docker-setup.bat
echo.
pause
