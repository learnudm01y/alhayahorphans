#!/bin/bash

# سكريبت تثبيت الخطوط العربية لـ PDF
# Install Arabic fonts for PDF generation

echo "========================================="
echo "تثبيت الخطوط العربية لـ wkhtmltopdf"
echo "Installing Arabic fonts for wkhtmltopdf"
echo "========================================="

# الألوان
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# المسار الرئيسي للمشروع
PROJECT_PATH="/var/www/html/alhayahorphans"

# التحقق من وجود المشروع
if [ ! -d "$PROJECT_PATH" ]; then
    echo -e "${RED}❌ المشروع غير موجود في: $PROJECT_PATH${NC}"
    exit 1
fi

cd "$PROJECT_PATH"

# 1. التحقق من وجود ملفات الخطوط
echo ""
echo -e "${YELLOW}[1/5] التحقق من ملفات الخطوط...${NC}"

if [ ! -f "public/fonts/Cairo-Regular.ttf" ]; then
    echo -e "${RED}❌ ملف Cairo-Regular.ttf غير موجود${NC}"
    exit 1
fi

if [ ! -f "public/fonts/Cairo-Bold.ttf" ]; then
    echo -e "${RED}❌ ملف Cairo-Bold.ttf غير موجود${NC}"
    exit 1
fi

echo -e "${GREEN}✅ ملفات الخطوط موجودة${NC}"

# 2. تعيين الصلاحيات
echo ""
echo -e "${YELLOW}[2/5] تعيين صلاحيات الملفات...${NC}"
chmod 644 public/fonts/*.ttf
chown www-data:www-data public/fonts/*.ttf
echo -e "${GREEN}✅ تم تعيين الصلاحيات${NC}"

# 3. تثبيت حزم الخطوط
echo ""
echo -e "${YELLOW}[3/5] تثبيت حزم الخطوط...${NC}"
apt-get update -qq
apt-get install -y fontconfig fonts-liberation > /dev/null 2>&1
echo -e "${GREEN}✅ تم تثبيت حزم الخطوط${NC}"

# 4. نسخ الخطوط إلى مجلد النظام
echo ""
echo -e "${YELLOW}[4/5] نسخ الخطوط إلى مجلد النظام...${NC}"
mkdir -p /usr/share/fonts/truetype/cairo
cp public/fonts/Cairo-*.ttf /usr/share/fonts/truetype/cairo/
echo -e "${GREEN}✅ تم نسخ الخطوط${NC}"

# 5. تحديث ذاكرة الخطوط
echo ""
echo -e "${YELLOW}[5/5] تحديث ذاكرة الخطوط...${NC}"
fc-cache -fv > /dev/null 2>&1
echo -e "${GREEN}✅ تم تحديث ذاكرة الخطوط${NC}"

# التحقق من التثبيت
echo ""
echo -e "${YELLOW}التحقق من الخطوط المثبتة:${NC}"
CAIRO_COUNT=$(fc-list | grep -i cairo | wc -l)
if [ $CAIRO_COUNT -gt 0 ]; then
    echo -e "${GREEN}✅ تم العثور على $CAIRO_COUNT خط Cairo${NC}"
    fc-list | grep -i cairo
else
    echo -e "${RED}❌ لم يتم العثور على خطوط Cairo${NC}"
fi

# إعادة تشغيل Queue Workers
echo ""
echo -e "${YELLOW}إعادة تشغيل Queue Workers...${NC}"
if command -v supervisorctl &> /dev/null; then
    supervisorctl restart laravel-worker:*
    echo -e "${GREEN}✅ تم إعادة تشغيل Queue Workers${NC}"
else
    echo -e "${YELLOW}⚠️  Supervisor غير مثبت، يرجى إعادة تشغيل Workers يدويًا${NC}"
fi

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}✅ اكتمل تثبيت الخطوط بنجاح${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "يمكنك الآن اختبار إنشاء PDF جديد للتحقق من الخط"
echo ""
