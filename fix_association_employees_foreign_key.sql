-- =====================================================
-- حل مشكلة Foreign Key في جدول association_employees
-- =====================================================

-- الخطوة 1: تحديث البيانات الخاطئة (sponsor_id غير موجود في جدول sponsors)
-- نجعل sponsor_id = NULL للسجلات التي لا يوجد لها sponsor
UPDATE association_employees
SET sponsor_id = NULL
WHERE sponsor_id IS NOT NULL
AND sponsor_id NOT IN (SELECT id FROM sponsors);

-- الخطوة 2: حذف Foreign Key القديم إن وجد
-- قد يكون اسم constraint مختلف، جرب هذه الأوامر:
ALTER TABLE association_employees DROP FOREIGN KEY association_employees_sponsor_id_foreign;
-- أو
-- ALTER TABLE association_employees DROP FOREIGN KEY IF EXISTS association_employees_sponsor_id_foreign;

-- الخطوة 3: التأكد من أن العمود sponsor_id هو nullable
ALTER TABLE association_employees MODIFY COLUMN sponsor_id BIGINT UNSIGNED NULL;

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
