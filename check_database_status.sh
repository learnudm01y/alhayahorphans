#!/bin/bash

# ملف فحص بنية قاعدة البيانات الحالية
# يُستخدم قبل تطبيق الفهارس للتأكد من حالة قاعدة البيانات

echo "==================================================="
echo "      فحص بنية قاعدة البيانات - السجل المدني"
echo "==================================================="

# معلومات قاعدة البيانات
DB_HOST="localhost"
DB_USER="u983550065_benaa101"
DB_PASS="Benaa_101_109"
DB_NAME="u983550065_civil_regitry1"

# الألوان
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "📋 فحص اتصال قاعدة البيانات..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ الاتصال بقاعدة البيانات ناجح${NC}"
else
    echo -e "${RED}❌ فشل في الاتصال بقاعدة البيانات${NC}"
    echo "تحقق من معلومات قاعدة البيانات"
    exit 1
fi

echo ""
echo "📊 معلومات جدول persons:"
echo "========================================="

# عدد السجلات
echo "🔢 عدد السجلات:"
RECORD_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "SELECT COUNT(*) FROM persons;" 2>/dev/null)
echo -e "   ${BLUE}إجمالي السجلات: ${YELLOW}$RECORD_COUNT${NC}"

# حجم الجدول
echo ""
echo "💾 حجم الجدول:"
TABLE_SIZE=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "
SELECT
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    ROUND((data_length / 1024 / 1024), 2) AS 'Data (MB)',
    ROUND((index_length / 1024 / 1024), 2) AS 'Index (MB)'
FROM information_schema.TABLES
WHERE table_schema = '$DB_NAME' AND table_name = 'persons';" 2>/dev/null)
echo -e "   ${BLUE}$TABLE_SIZE${NC}"

echo ""
echo "🗂️ الفهارس الحالية:"
echo "========================================="
CURRENT_INDEXES=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "SHOW INDEX FROM persons;" 2>/dev/null)
if [ -z "$CURRENT_INDEXES" ]; then
    echo -e "   ${YELLOW}⚠️ لا توجد فهارس حالياً${NC}"
else
    echo "$CURRENT_INDEXES" | awk '{print "   " $3 " (" $5 ")"}'
fi

echo ""
echo "📋 أعمدة الجدول:"
echo "========================================="
TABLE_COLUMNS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "DESCRIBE persons;" 2>/dev/null)
echo "$TABLE_COLUMNS" | while read line; do
    echo "   $line"
done

echo ""
echo "⚡ اختبار أداء البحث الحالي:"
echo "========================================="

# اختبار البحث برقم الهوية
echo "🔍 اختبار البحث برقم الهوية:"
SAMPLE_ID=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "SELECT CI_ID_NUM FROM persons WHERE CI_ID_NUM IS NOT NULL AND CI_ID_NUM != '' LIMIT 1;" 2>/dev/null)
if [ ! -z "$SAMPLE_ID" ]; then
    echo "   📝 رقم الهوية المُختبر: $SAMPLE_ID"
    START_TIME=$(date +%s.%N)
    RESULT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "SELECT COUNT(*) FROM persons WHERE CI_ID_NUM = '$SAMPLE_ID';" 2>/dev/null)
    END_TIME=$(date +%s.%N)
    DURATION=$(echo "$END_TIME - $START_TIME" | bc)
    echo -e "   ⏱️ وقت البحث: ${RED}$DURATION ثانية${NC}"
    echo "   📊 النتائج: $RESULT"
else
    echo -e "   ${YELLOW}⚠️ لا توجد بيانات هوية للاختبار${NC}"
fi

# اختبار البحث بالاسم
echo ""
echo "🔍 اختبار البحث بالاسم:"
SAMPLE_NAME=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "SELECT CI_FIRST_ARB FROM persons WHERE CI_FIRST_ARB IS NOT NULL AND CI_FIRST_ARB != '' LIMIT 1;" 2>/dev/null)
if [ ! -z "$SAMPLE_NAME" ]; then
    echo "   📝 الاسم المُختبر: $SAMPLE_NAME"
    START_TIME=$(date +%s.%N)
    RESULT=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -se "SELECT COUNT(*) FROM persons WHERE CI_FIRST_ARB = '$SAMPLE_NAME';" 2>/dev/null)
    END_TIME=$(date +%s.%N)
    DURATION=$(echo "$END_TIME - $START_TIME" | bc)
    echo -e "   ⏱️ وقت البحث: ${RED}$DURATION ثانية${NC}"
    echo "   📊 النتائج: $RESULT"
else
    echo -e "   ${YELLOW}⚠️ لا توجد بيانات أسماء للاختبار${NC}"
fi

echo ""
echo "💡 ملخص التوصيات:"
echo "========================================="

if [ "$RECORD_COUNT" -lt 100000 ]; then
    echo -e "   ${GREEN}✅ حجم البيانات صغير - الفهرسة ستكتمل في دقائق${NC}"
elif [ "$RECORD_COUNT" -lt 1000000 ]; then
    echo -e "   ${YELLOW}⚠️ حجم البيانات متوسط - الفهرسة ستحتاج 10-20 دقيقة${NC}"
else
    echo -e "   ${RED}⚠️ حجم البيانات كبير - الفهرسة ستحتاج 30-60 دقيقة أو أكثر${NC}"
fi

# التحقق من المساحة المتاحة
DISK_SPACE=$(df -h . | awk 'NR==2{print $4}')
echo -e "   ${BLUE}المساحة المتاحة: $DISK_SPACE${NC}"

echo ""
echo -e "${GREEN}🚀 جاهز لتطبيق الفهارس؟ شغل الملف التالي:${NC}"
echo -e "${BLUE}   bash create_database_indexes.sh${NC}"
echo ""
