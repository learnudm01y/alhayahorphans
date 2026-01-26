#!/bin/bash

echo "=========================================="
echo "إعداد Supervisor لـ Laravel Queue Worker"
echo "=========================================="

# 1. التأكد من تثبيت Supervisor
if ! command -v supervisorctl &> /dev/null; then
    echo "تثبيت Supervisor..."
    sudo apt-get update
    sudo apt-get install -y supervisor
fi

# 2. نسخ ملف التكوين
echo "نسخ ملف التكوين..."
sudo cp laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

# 3. تحديث المسارات في ملف التكوين
echo "تحديث المسارات..."
sudo sed -i "s|/var/www/|$(pwd)/|g" /etc/supervisor/conf.d/laravel-worker.conf

# 4. إعادة قراءة التكوين
echo "إعادة قراءة التكوين..."
sudo supervisorctl reread

# 5. تحديث Supervisor
echo "تحديث Supervisor..."
sudo supervisorctl update

# 6. بدء العمليات
echo "بدء العمليات..."
sudo supervisorctl start laravel-worker:*

# 7. عرض حالة العمليات
echo ""
echo "حالة العمليات:"
sudo supervisorctl status laravel-worker:*

echo ""
echo "=========================================="
echo "✅ تم إعداد Supervisor بنجاح"
echo "=========================================="
echo ""
echo "أوامر مفيدة:"
echo "  - عرض الحالة: sudo supervisorctl status"
echo "  - إيقاف: sudo supervisorctl stop laravel-worker:*"
echo "  - بدء: sudo supervisorctl start laravel-worker:*"
echo "  - إعادة تشغيل: sudo supervisorctl restart laravel-worker:*"
echo "  - عرض الـ logs: tail -f storage/logs/worker.log"
