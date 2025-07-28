#!/bin/bash

# ==============================================================
# ملف اختبار أداء الفهارس بعد التطبيق
# اسم الملف: test_database_performance.sh
# الغرض: قياس تحسن الأداء بعد إنشاء الفهارس
# ==============================================================

# إعدادات قاعدة البيانات
DB_NAME="u983550065_civil_regitry1"
DB_USER="u983550065_benaa101"
DB_PASS="Benaa_101_109"
DB_HOST="localhost"

# ألوان للعرض
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_message() {
    echo -e "${GREEN}[$(date '+%H:%M:%S')] $1${NC}"
}

print_info() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')] $1${NC}"
}

# دالة قياس الوقت
measure_query_time() {
    local query="$1"
    local description="$2"

    print_info "اختبار: $description"

    # تنفيذ الاستعلام مع قياس الوقت
    start_time=$(date +%s.%N)
    result=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$query" --skip-column-names 2>/dev/null)
    end_time=$(date +%s.%N)

    # حساب الوقت المستغرق
    execution_time=$(echo "$end_time - $start_time" | bc -l 2>/dev/null || echo "0")

    if [ ! -z "$result" ]; then
        echo "   ⏱️  الوقت المستغرق: ${execution_time} ثانية"
        echo "   📊 عدد النتائج: $(echo "$result" | wc -l)"

        # تحليل الاستعلام لمعرفة إذا استخدم الفهرس
        explain_result=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "EXPLAIN $query" 2>/dev/null)
        if echo "$explain_result" | grep -q "Using index"; then
            echo "   ✅ تم استخدام الفهرس"
        else
            echo "   ⚠️  لم يتم استخدام الفهرس"
        fi
    else
        echo "   ❌ لا توجد نتائج"
    fi
    echo ""
}

echo "
╔══════════════════════════════════════════════════════════╗
║                 اختبار أداء قاعدة البيانات            ║
║                  بعد إنشاء الفهارس                     ║
╚══════════════════════════════════════════════════════════╝
"

print_message "بدء اختبارات الأداء..."

# اختبار 1: البحث برقم الهوية
print_message "اختبار 1: البحث برقم الهوية"
measure_query_time "SELECT * FROM persons WHERE CI_ID_NUM = '1234567890' LIMIT 1;" "البحث برقم هوية محدد"

# اختبار 2: البحث بالاسم الأول
print_message "اختبار 2: البحث بالاسم الأول"
measure_query_time "SELECT * FROM persons WHERE CI_FIRST_ARB = 'أحمد' LIMIT 10;" "البحث بالاسم الأول"

# اختبار 3: البحث بالاسم الكامل
print_message "اختبار 3: البحث بالاسم الكامل"
measure_query_time "SELECT * FROM persons WHERE CI_FIRST_ARB = 'أحمد' AND CI_FATHER_ARB = 'محمد' LIMIT 10;" "البحث بالاسم والأب"

# اختبار 4: البحث بالجنس والمدينة
print_message "اختبار 4: البحث بالجنس والمدينة"
measure_query_time "SELECT COUNT(*) FROM persons WHERE CI_SEX_CD = 1 AND CITY = 1;" "عد الذكور في مدينة محددة"

# اختبار 5: البحث بتاريخ الميلاد
print_message "اختبار 5: البحث بتاريخ الميلاد"
measure_query_time "SELECT COUNT(*) FROM persons WHERE YEAR(CI_BIRTH_DT) = 1990;" "المواليد في سنة 1990"

# اختبار 6: البحث في الأحياء
print_message "اختبار 6: الأشخاص الأحياء"
measure_query_time "SELECT COUNT(*) FROM persons WHERE CI_DEAD_DT IS NULL;" "عدد الأشخاص الأحياء"

# عرض إحصائيات الفهارس
print_message "إحصائيات الفهارس:"
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    SELECT
        index_name as 'اسم الفهرس',
        COUNT(*) as 'عدد الأعمدة',
        GROUP_CONCAT(column_name ORDER BY seq_in_index) as 'الأعمدة'
    FROM information_schema.statistics
    WHERE table_schema = '$DB_NAME'
    AND table_name = 'persons'
    AND index_name LIKE 'idx_%'
    GROUP BY index_name
    ORDER BY index_name;
" 2>/dev/null

print_message "اكتمل اختبار الأداء!"

echo "
💡 نصائح:
- إذا كان الوقت أقل من 0.1 ثانية، فالأداء ممتاز
- إذا كان الوقت بين 0.1-1 ثانية، فالأداء جيد
- إذا كان الوقت أكثر من 1 ثانية، قد تحتاج فهارس إضافية
"
