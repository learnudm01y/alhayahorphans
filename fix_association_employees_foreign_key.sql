-- =====================================================
-- حل مشكلة Foreign Key في جدول association_employees
-- =====================================================

-- الخطوة 1: التأكد من أن العمود sponsor_id هو nullable أولاً
ALTER TABLE association_employees MODIFY COLUMN sponsor_id BIGINT UNSIGNED NULL;

-- الخطوة 2: تحديث أو حذف البيانات الخاطئة (sponsor_id غير موجود في جدول sponsors)

-- خيار 1: حذف السجلات الخاطئة (موصى به)
DELETE FROM association_employees
WHERE sponsor_id IS NOT NULL
AND sponsor_id NOT IN (SELECT id FROM sponsors);

-- خيار 2: جعل sponsor_id = NULL (إذا كنت تريد الاحتفاظ بالسجلات)
-- UPDATE association_employees
-- SET sponsor_id = NULL
-- WHERE sponsor_id IS NOT NULL
-- AND sponsor_id NOT IN (SELECT id FROM sponsors);

-- الخطوة 3: حذف Foreign Key القديم إن وجد
-- قد يكون اسم constraint مختلف، جرب هذه الأوامر:
ALTER TABLE association_employees DROP FOREIGN KEY association_employees_sponsor_id_foreign;
-- أو
-- ALTER TABLE association_employees DROP FOREIGN KEY IF EXISTS association_employees_sponsor_id_foreign;

-- الخطوة 4: إضافة Foreign Key من جديد
ALTER TABLE association_employees
ADD CONSTRAINT association_employees_sponsor_id_foreign
FOREIGN KEY (sponsor_id)
REFERENCES sponsors(id)
ON DELETE CASCADE
ON UPDATE CASCADE;

-- =====================================================
-- للتحقق من النتيجة:
-- =====================================================
-- عرض السجلات التي sponsor_id = NULL
-- SELECT * FROM association_employees WHERE sponsor_id IS NULL;

-- عرض Foreign Keys في الجدول
-- SHOW CREATE TABLE association_employees;
