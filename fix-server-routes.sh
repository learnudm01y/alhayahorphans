#!/bin/bash

# 🔧 سكريبت إصلاح Routes على السيرفر
# يُشغّل على: /var/www/html/alhayahorphans

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║          🔧 إصلاح Routes على السيرفر                          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# التحقق من المسار
if [ ! -d "/var/www/html/alhayahorphans" ]; then
    echo "❌ خطأ: المسار /var/www/html/alhayahorphans غير موجود"
    exit 1
fi

cd /var/www/html/alhayahorphans

echo "📁 المسار الحالي: $(pwd)"
echo ""

# 1. سحب التحديثات من Git
echo "1️⃣  سحب التحديثات من Git..."
git pull origin main
if [ $? -eq 0 ]; then
    echo "✅ تم سحب التحديثات بنجاح"
else
    echo "❌ فشل سحب التحديثات"
    exit 1
fi
echo ""

# 2. مسح Cache
echo "2️⃣  مسح Cache..."
php artisan optimize:clear
if [ $? -eq 0 ]; then
    echo "✅ تم مسح Cache بنجاح"
else
    echo "❌ فشل مسح Cache"
    exit 1
fi
echo ""

# 3. التحقق من الصلاحيات
echo "3️⃣  التحقق من الصلاحيات..."
sudo chown -R www-data:www-data /var/www/html/alhayahorphans
sudo chmod -R 755 /var/www/html/alhayahorphans
sudo chmod -R 775 /var/www/html/alhayahorphans/storage
sudo chmod -R 775 /var/www/html/alhayahorphans/bootstrap/cache
echo "✅ تم تحديث الصلاحيات"
echo ""

# 4. عرض Routes
echo "4️⃣  التحقق من Routes المحدثة..."
echo ""
php artisan route:list | grep "export" | grep "records-management"
echo ""

# 5. التحقق من Memory Limit
echo "5️⃣  التحقق من Memory Limit..."
MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
echo "   Memory Limit الحالي: $MEMORY_LIMIT"
if [ "$MEMORY_LIMIT" != "2048M" ] && [ "$MEMORY_LIMIT" != "-1" ]; then
    echo "⚠️  تحذير: يُنصح برفع memory_limit إلى 2048M في php.ini"
fi
echo ""

# 6. إعادة تشغيل الخدمات
echo "6️⃣  إعادة تشغيل الخدمات..."

# التحقق من PHP-FPM
if systemctl is-active --quiet php8.3-fpm; then
    sudo systemctl restart php8.3-fpm
    echo "✅ تم إعادة تشغيل PHP-FPM"
elif systemctl is-active --quiet php8.2-fpm; then
    sudo systemctl restart php8.2-fpm
    echo "✅ تم إعادة تشغيل PHP-FPM"
elif systemctl is-active --quiet php8.1-fpm; then
    sudo systemctl restart php8.1-fpm
    echo "✅ تم إعادة تشغيل PHP-FPM"
fi

# إعادة تشغيل Web Server
if systemctl is-active --quiet nginx; then
    sudo systemctl restart nginx
    echo "✅ تم إعادة تشغيل Nginx"
elif systemctl is-active --quiet apache2; then
    sudo systemctl restart apache2
    echo "✅ تم إعادة تشغيل Apache"
fi
echo ""

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                   ✅ اكتمل الإصلاح بنجاح ✅                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "🎯 جرّب الآن:"
echo "   افتح: https://your-domain.com/admin/records-management"
echo "   اضغط: زر 'تصدير Excel (4 sheets)'"
echo ""
