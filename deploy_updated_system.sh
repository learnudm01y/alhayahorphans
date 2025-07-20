#!/bin/bash

# سكريبت نشر النظام المُحدث للاستضافة
# Deploy Updated File Management System to Production

echo "🚀 بدء نشر النظام المُحدث للاستضافة..."
echo "=================================================="

# 1. تشغيل المايجريشن
echo ""
echo "1️⃣ تشغيل المايجريشن..."
php artisan migrate --force

# 2. تنظيف الكاش
echo ""
echo "2️⃣ تنظيف الكاش..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. تحسين الأداء
echo ""
echo "3️⃣ تحسين الأداء..."
php artisan config:cache
php artisan route:cache

# 4. إعداد صلاحيات الملفات
echo ""
echo "4️⃣ إعداد صلاحيات الملفات..."
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/

# 5. اختبار النظام
echo ""
echo "5️⃣ اختبار النظام..."
php test_final_system.php

echo ""
echo "✅ تم نشر النظام المُحدث بنجاح!"
echo "🎉 النظام جاهز لعرض الملفات من مصادر متعددة!"

# 6. عرض الإرشادات التالية
echo ""
echo "📋 الإرشادات التالية:"
echo "====================="
echo "1. اختبر النظام من خلال الواجهة الويب"
echo "2. تأكد من عرض الملفات بشكل صحيح"
echo "3. راقب لوجات النظام لأي أخطاء"
echo "4. تأكد من عمل البحث في كلا الجدولين"

echo ""
echo "🔍 للمراقبة المستمرة:"
echo "tail -f storage/logs/laravel.log"

echo ""
echo "📊 لفحص الإحصائيات:"
echo "php check_attachments_data.php"
