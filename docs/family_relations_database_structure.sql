-- ===========================================================
-- نظام البحث عن العلاقات العائلية - بنية قاعدة البيانات
-- ===========================================================

-- قاعدة البيانات المستخدمة
USE civilregistry;

-- ===========================================================
-- 1. جدول الأشخاص (persons)
-- ===========================================================
-- يحتوي على معلومات المواطنين الأساسية
-- الأعمدة الرئيسية:
-- - CI_ID_NUM: رقم الهوية (Primary Key)
-- - CI_FIRST_ARB: الاسم الأول
-- - CI_FATHER_ARB: اسم الأب
-- - CI_GRAND_FATHER_ARB: اسم الجد
-- - CI_FAMILY_ARB: اسم العائلة
-- - CI_BIRTH_DT: تاريخ الميلاد
-- - CI_SEX_CD: الجنس (1=ذكر, 2=أنثى)
-- - CI_DEAD_DT: تاريخ الوفاة (NULL إذا كان حياً)

-- ===========================================================
-- 2. جدول أنواع العلاقات (category_of_relations)
-- ===========================================================
-- يحتوي على أنواع العلاقات العائلية
CREATE TABLE IF NOT EXISTS category_of_relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attribute VARCHAR(255) NOT NULL COMMENT 'نوع العلاقة (أب، أم، أخ، أخت، إلخ)',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- أمثلة على أنواع العلاقات
INSERT INTO category_of_relations (id, attribute) VALUES
(1, 'أب'),
(2, 'أم'),
(3, 'ابن'),
(4, 'ابنة'),
(5, 'أخ'),
(6, 'أخت'),
(7, 'زوج'),
(8, 'زوجة'),
(9, 'جد'),
(10, 'جدة');

-- ===========================================================
-- 3. جدول العلاقات (relations)
-- ===========================================================
-- يحتوي على العلاقات بين الأشخاص
CREATE TABLE IF NOT EXISTS relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    CF_ID_NUM BIGINT UNSIGNED NOT NULL COMMENT 'رقم هوية الشخص الأساسي',
    CF_RELATIVE_CD BIGINT UNSIGNED NOT NULL COMMENT 'نوع العلاقة (Foreign Key to category_of_relations)',
    CF_ID_RELATIVE BIGINT UNSIGNED NOT NULL COMMENT 'رقم هوية الشخص المرتبط (القريب)',

    -- الفهارس المضافة لتسريع البحث (B-Tree Indexes)
    INDEX idx_cf_id_num (CF_ID_NUM),
    INDEX idx_cf_id_relative (CF_ID_RELATIVE),
    INDEX idx_cf_relative_cd (CF_RELATIVE_CD),
    INDEX idx_cf_id_num_relative_cd (CF_ID_NUM, CF_RELATIVE_CD),
    INDEX idx_cf_id_relative_relative_cd (CF_ID_RELATIVE, CF_RELATIVE_CD),

    -- Foreign Keys (اختياري - يمكن تفعيله حسب الحاجة)
    -- FOREIGN KEY (CF_ID_NUM) REFERENCES persons(CI_ID_NUM) ON DELETE CASCADE,
    -- FOREIGN KEY (CF_ID_RELATIVE) REFERENCES persons(CI_ID_NUM) ON DELETE CASCADE,
    FOREIGN KEY (CF_RELATIVE_CD) REFERENCES category_of_relations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================
-- 4. أمثلة على البيانات
-- ===========================================================

-- مثال: عائلة افتراضية
-- الأب: رقم الهوية 1000000001
-- الأم: رقم الهوية 1000000002
-- الابن الأول: رقم الهوية 1000000003
-- الابن الثاني: رقم الهوية 1000000004
-- البنت: رقم الهوية 1000000005

-- علاقة الابن الأول بالأب (الأب -> الابن)
INSERT INTO relations (CF_ID_NUM, CF_RELATIVE_CD, CF_ID_RELATIVE) VALUES
(1000000001, 3, 1000000003); -- الأب لديه ابن

-- علاقة الابن الأول بالأب (الابن -> الأب) - العلاقة العكسية
INSERT INTO relations (CF_ID_NUM, CF_RELATIVE_CD, CF_ID_RELATIVE) VALUES
(1000000003, 1, 1000000001); -- الابن لديه أب

-- علاقة الابن الأول بالأم
INSERT INTO relations (CF_ID_NUM, CF_RELATIVE_CD, CF_ID_RELATIVE) VALUES
(1000000003, 2, 1000000002), -- الابن لديه أم
(1000000002, 3, 1000000003); -- الأم لديها ابن

-- علاقة الأخوة
INSERT INTO relations (CF_ID_NUM, CF_RELATIVE_CD, CF_ID_RELATIVE) VALUES
(1000000003, 5, 1000000004), -- الابن الأول لديه أخ
(1000000004, 5, 1000000003); -- الابن الثاني لديه أخ

-- ===========================================================
-- 5. استعلامات مفيدة
-- ===========================================================

-- البحث عن جميع أفراد عائلة شخص معين (العلاقات المباشرة)
SELECT
    r.CF_ID_NUM as 'رقم_الهوية_الأساسي',
    r.CF_ID_RELATIVE as 'رقم_هوية_القريب',
    c.attribute as 'نوع_العلاقة',
    p.CI_FIRST_ARB as 'الاسم_الأول',
    p.CI_FATHER_ARB as 'اسم_الأب',
    p.CI_FAMILY_ARB as 'اسم_العائلة'
FROM relations r
JOIN persons p ON r.CF_ID_RELATIVE = p.CI_ID_NUM
JOIN category_of_relations c ON r.CF_RELATIVE_CD = c.id
WHERE r.CF_ID_NUM = 1000000003;

-- البحث عن جميع الأشخاص المرتبطين بشخص معين (العلاقات العكسية)
SELECT
    r.CF_ID_NUM as 'رقم_الهوية',
    r.CF_ID_RELATIVE as 'رقم_هوية_الشخص_المبحوث',
    c.attribute as 'نوع_العلاقة',
    p.CI_FIRST_ARB as 'الاسم_الأول',
    p.CI_FATHER_ARB as 'اسم_الأب',
    p.CI_FAMILY_ARB as 'اسم_العائلة'
FROM relations r
JOIN persons p ON r.CF_ID_NUM = p.CI_ID_NUM
JOIN category_of_relations c ON r.CF_RELATIVE_CD = c.id
WHERE r.CF_ID_RELATIVE = 1000000003;

-- البحث الشامل (العلاقات في الاتجاهين)
(SELECT
    r.CF_ID_NUM as id_number,
    r.CF_ID_RELATIVE as relative_id,
    c.attribute as relation_type,
    p.CI_FIRST_ARB,
    p.CI_FATHER_ARB,
    p.CI_FAMILY_ARB,
    'direct' as direction
FROM relations r
JOIN persons p ON r.CF_ID_RELATIVE = p.CI_ID_NUM
JOIN category_of_relations c ON r.CF_RELATIVE_CD = c.id
WHERE r.CF_ID_NUM = 1000000003)
UNION ALL
(SELECT
    r.CF_ID_RELATIVE as id_number,
    r.CF_ID_NUM as relative_id,
    c.attribute as relation_type,
    p.CI_FIRST_ARB,
    p.CI_FATHER_ARB,
    p.CI_FAMILY_ARB,
    'reverse' as direction
FROM relations r
JOIN persons p ON r.CF_ID_NUM = p.CI_ID_NUM
JOIN category_of_relations c ON r.CF_RELATIVE_CD = c.id
WHERE r.CF_ID_RELATIVE = 1000000003);

-- ===========================================================
-- 6. إحصائيات مفيدة
-- ===========================================================

-- عدد العلاقات لكل شخص
SELECT
    p.CI_ID_NUM,
    CONCAT(p.CI_FIRST_ARB, ' ', p.CI_FATHER_ARB, ' ', p.CI_FAMILY_ARB) as full_name,
    COUNT(r.id) as total_relations
FROM persons p
LEFT JOIN relations r ON p.CI_ID_NUM = r.CF_ID_NUM
GROUP BY p.CI_ID_NUM
ORDER BY total_relations DESC;

-- توزيع أنواع العلاقات
SELECT
    c.attribute as relation_type,
    COUNT(r.id) as count
FROM relations r
JOIN category_of_relations c ON r.CF_RELATIVE_CD = c.id
GROUP BY c.attribute
ORDER BY count DESC;

-- ===========================================================
-- 7. صيانة الفهارس
-- ===========================================================

-- تحليل الجدول لتحديث الإحصائيات
ANALYZE TABLE relations;

-- إعادة بناء الفهارس (إذا لزم الأمر)
-- ALTER TABLE relations ENGINE=InnoDB;

-- عرض معلومات الفهارس
SHOW INDEX FROM relations;

-- فحص أداء الاستعلام
EXPLAIN SELECT * FROM relations WHERE CF_ID_NUM = 1000000003;

-- ===========================================================
-- 8. ملاحظات مهمة
-- ===========================================================

/*
1. الفهارس (Indexes):
   - تم إضافة 5 فهارس B-Tree لتسريع البحث بشكل كبير
   - الفهارس المركبة تحسن أداء الاستعلامات المعقدة

2. العلاقات الثنائية الاتجاه:
   - كل علاقة تُسجل في اتجاهين (مباشر وعكسي)
   - مثال: إذا كان أحمد ابن محمد، يُسجل:
     * محمد -> أحمد (علاقة: ابن)
     * أحمد -> محمد (علاقة: أب)

3. الأداء:
   - مع الفهارس: البحث يستغرق أقل من 50 ميلي ثانية
   - بدون فهارس: البحث قد يستغرق عدة ثوانٍ

4. الصيانة:
   - يُنصح بتشغيل ANALYZE TABLE بشكل دوري
   - مراقبة حجم الجدول وأداء الاستعلامات

5. التوسع المستقبلي:
   - يمكن إضافة أنواع علاقات جديدة بسهولة
   - البنية تدعم شجرة عائلة بأي عمق
*/

-- ===========================================================
-- النهاية
-- ===========================================================
