# سكريپت نشر النظام المُحدث للاستضافة (Windows PowerShell)
# Deploy Updated File Management System to Production

Write-Host "🚀 بدء نشر النظام المُحدث للاستضافة..." -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Yellow

# 1. تشغيل المايجريشن
Write-Host ""
Write-Host "1️⃣ تشغيل المايجريشن..." -ForegroundColor Cyan
php artisan migrate --force

# 2. تنظيف الكاش
Write-Host ""
Write-Host "2️⃣ تنظيف الكاش..." -ForegroundColor Cyan
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. تحسين الأداء
Write-Host ""
Write-Host "3️⃣ تحسين الأداء..." -ForegroundColor Cyan
php artisan config:cache
php artisan route:cache

# 4. اختبار النظام
Write-Host ""
Write-Host "4️⃣ اختبار النظام..." -ForegroundColor Cyan
php test_final_system.php

Write-Host ""
Write-Host "✅ تم نشر النظام المُحدث بنجاح!" -ForegroundColor Green
Write-Host "🎉 النظام جاهز لعرض الملفات من مصادر متعددة!" -ForegroundColor Green

# 5. عرض الإرشادات التالية
Write-Host ""
Write-Host "📋 الإرشادات التالية:" -ForegroundColor Yellow
Write-Host "=====================" -ForegroundColor Yellow
Write-Host "1. اختبر النظام من خلال الواجهة الويب" -ForegroundColor White
Write-Host "2. تأكد من عرض الملفات بشكل صحيح" -ForegroundColor White
Write-Host "3. راقب لوجات النظام لأي أخطاء" -ForegroundColor White
Write-Host "4. تأكد من عمل البحث في كلا الجدولين" -ForegroundColor White

Write-Host ""
Write-Host "🔍 للمراقبة المستمرة:" -ForegroundColor Magenta
Write-Host "Get-Content storage/logs/laravel.log -Wait" -ForegroundColor Gray

Write-Host ""
Write-Host "📊 لفحص الإحصائيات:" -ForegroundColor Magenta
Write-Host "php check_attachments_data.php" -ForegroundColor Gray

Write-Host ""
Write-Host "🧪 لاختبار المجلدات:" -ForegroundColor Magenta
Write-Host "php test_final_system.php" -ForegroundColor Gray
