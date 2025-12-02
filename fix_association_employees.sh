#!/bin/bash

# =====================================================
# حل مشكلة Foreign Key في association_employees
# تنفيذ على الاستضافة
# =====================================================

echo "=== بدء حل مشكلة Foreign Key ==="

# الطريقة 1: باستخدام Laravel Migration
echo ""
echo "الطريقة 1: تنفيذ Migration محددة"
echo "-----------------------------------"
php artisan migrate --path=database/migrations/2025_12_02_131411_fix_association_employees_foreign_key_safe.php
php artisan migrate --path=database/migrations/2025_12_02_035205_add_sponsor_id_to_association_employees.php

# الطريقة 2: إذا فشلت Migration، استخدم SQL مباشرة
echo ""
echo "الطريقة 2: تنفيذ SQL مباشرة"
echo "-----------------------------------"

# قراءة معلومات قاعدة البيانات من .env
DB_HOST=$(grep DB_HOST .env | cut -d '=' -f2)
DB_PORT=$(grep DB_PORT .env | cut -d '=' -f2)
DB_DATABASE=$(grep DB_DATABASE .env | cut -d '=' -f2)
DB_USERNAME=$(grep DB_USERNAME .env | cut -d '=' -f2)
DB_PASSWORD=$(grep DB_PASSWORD .env | cut -d '=' -f2)

# تنفيذ الأوامر SQL
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << 'EOF'

-- تحديث البيانات الخاطئة
UPDATE association_employees
SET sponsor_id = NULL
WHERE sponsor_id IS NOT NULL
AND sponsor_id NOT IN (SELECT id FROM sponsors);

-- حذف Foreign Key القديم
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE association_employees DROP FOREIGN KEY IF EXISTS association_employees_sponsor_id_foreign;
SET FOREIGN_KEY_CHECKS = 1;

-- إضافة Foreign Key من جديد
ALTER TABLE association_employees
ADD CONSTRAINT association_employees_sponsor_id_foreign
FOREIGN KEY (sponsor_id)
REFERENCES sponsors(id)
ON DELETE CASCADE;

EOF

echo ""
echo "=== تم الانتهاء ==="
echo ""
echo "للتحقق من النتيجة، قم بتشغيل:"
echo "mysql -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE -e 'SHOW CREATE TABLE association_employees;'"
