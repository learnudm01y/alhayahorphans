#!/bin/bash

echo "=========================================="
echo "تثبيت متطلبات PDF و Queue"
echo "=========================================="

# 1. تثبيت wkhtmltopdf
echo ""
echo "1. تثبيت wkhtmltopdf:"
if command -v wkhtmltopdf &> /dev/null; then
    echo "✅ wkhtmltopdf مثبت بالفعل"
    wkhtmltopdf --version
else
    echo "⏳ جاري تثبيت wkhtmltopdf..."

    # تثبيت المكتبات المطلوبة
    sudo apt-get update
    sudo apt-get install -y libfontconfig1 libxrender1 libxext6 libx11-6 fontconfig xfonts-75dpi xfonts-base

    # تحميل وتثبيت wkhtmltopdf
    cd /tmp
    wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_amd64.deb
    sudo apt install -y ./wkhtmltox_0.12.6.1-2.jammy_amd64.deb

    # إنشاء symbolic link
    sudo ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf

    echo "✅ تم تثبيت wkhtmltopdf بنجاح"
    wkhtmltopdf --version
fi

# 2. تثبيت Supervisor
echo ""
echo "2. تثبيت Supervisor:"
if command -v supervisorctl &> /dev/null; then
    echo "✅ Supervisor مثبت بالفعل"
    supervisorctl --version
else
    echo "⏳ جاري تثبيت Supervisor..."
    sudo apt-get install -y supervisor
    echo "✅ تم تثبيت Supervisor بنجاح"
fi

# 3. إعداد صلاحيات المجلدات
echo ""
echo "3. إعداد الصلاحيات:"
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
echo "✅ تم إعداد الصلاحيات"

echo ""
echo "=========================================="
echo "✅ تم التثبيت بنجاح!"
echo "=========================================="
echo ""
echo "الخطوة التالية: قم بتشغيل ./setup-supervisor.sh"
