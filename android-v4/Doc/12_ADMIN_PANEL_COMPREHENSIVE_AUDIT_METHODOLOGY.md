# 12 — مراجعة شاملة لاستكمال نواقص لوحة التحكم (Full Admin Panel Audit)

القرار المعتمد: **"غير محدد، بدي مراجعة شاملة لكل شي"** — بما إنه ما في نواقص محددة مسبقاً، هذا الملف منهجية تدقيق تنفيذية (Checklist قابل للتنفيذ مباشرة على السيرفر)، وليس قائمة نواقص مفترَضة.

## 1. لماذا منهجية وليس قائمة جاهزة

كل الملفات السابقة (خصوصاً 08 و09) توثّق **ما تم بناؤه**، لكن "مبني" ≠ "يعمل بالكامل بلا نواقص". المراجعة الشاملة الحقيقية تحتاج تشغيل فحوصات فعلية على الكود/السيرفر الحالي، لا افتراضات. هذا الملف يعطيك أوامر تُشغَّل + معايير حكم واضحة، والنتائج تُبنى عليها قائمة نواقص فعلية بالملف التالي (13) بعد التنفيذ.

## 2. مصفوفة الفحص — لكل الـ26 شاشة بكتالوج 08

لكل شاشة، 4 أبعاد فحص إلزامية:

| البُعد | السؤال | كيف يُختبَر |
|--------|--------|---------------|
| **أ. تعمل أوفلاين** | هل تفتح وتعرض بيانات صحيحة بدون إنترنت؟ | Airplane Mode فعلي + فتح الشاشة |
| **ب. تعمل أونلاين ومزامنة** | هل التعديل/الإضافة فيها يُزامَن فعلياً للسيرفر؟ | تعديل + مراقبة `sync_outbox_v4` يتحول لـ `applied` |
| **ج. الصلاحيات مفعَّلة** | هل تُخفي/تُقيّد فعلياً حسب `permissions_manifest_v4` أم كل شي ظاهر لأي مستخدم؟ | تسجيل دخول بمستخدم صلاحيات محدودة + التحقق |
| **د. لا أخطاء console** | هل فيها أخطاء JS ظاهرة بالـ WebView console؟ | `adb logcat` أثناء فتح كل شاشة |

## 3. سكربت تحقق آلي (Backend) — يُشغَّل أولاً، يوفر وقت كبير

```bash
cd /var/www/html/alhayahorphans

# 3.1 كل الجداول الـ22 المذكورة بالكتالوج — هل فعلياً كلها بيانات موجودة أم فاضية؟
mysql -u root -p -e "
SELECT 'academic_degrees' t, COUNT(*) c FROM academic_degrees
UNION SELECT 'category_of_relations', COUNT(*) FROM category_of_relations
UNION SELECT 'aid_statuses', COUNT(*) FROM aid_statuses
UNION SELECT 'bank_names', COUNT(*) FROM bank_names
UNION SELECT 'city', COUNT(*) FROM city
UNION SELECT 'currency_types', COUNT(*) FROM currency_types
UNION SELECT 'death_reasons', COUNT(*) FROM death_reasons
UNION SELECT 'displacement_statuses', COUNT(*) FROM displacement_statuses
UNION SELECT 'document_types', COUNT(*) FROM document_types
UNION SELECT 'employments', COUNT(*) FROM employments
UNION SELECT 'general_categories', COUNT(*) FROM general_categories
UNION SELECT 'health_statuses', COUNT(*) FROM health_statuses
UNION SELECT 'orphan_needs', COUNT(*) FROM orphan_needs
UNION SELECT 'creativity_aspects', COUNT(*) FROM creativity_aspects
UNION SELECT 'housing_statuses', COUNT(*) FROM housing_statuses
UNION SELECT 'marital_statuses', COUNT(*) FROM marital_statuses
UNION SELECT 'provinces', COUNT(*) FROM provinces
UNION SELECT 'request_statuses', COUNT(*) FROM request_statuses
UNION SELECT 'sponsorship_statuses', COUNT(*) FROM sponsorship_statuses
UNION SELECT 'type_of_accommodations', COUNT(*) FROM type_of_accommodations
UNION SELECT 'type_of_guarantees', COUNT(*) FROM type_of_guarantees
ORDER BY c ASC;
"
# أي جدول برجع 0 = تصنيف فاضي بالكامل، الشاشة "مبنية" لكن فعلياً بدون بيانات حقيقية للمستخدم

# 3.2 هل كل الـ59 مسار v4 فعلاً شغّالة (مش بس مسجَّلة)؟
php artisan route:list --path=api/mobile/v4 --json > /tmp/v4_routes.json
echo "عدد المسارات المسجَّلة:"; cat /tmp/v4_routes.json | grep -o '"uri"' | wc -l

# 3.3 سجلات أخطاء حديثة تخص v4 تحديداً
tail -n 500 storage/logs/laravel.log | grep -i "V4\|v4_" | tail -n 50
```

## 4. سكربت تحقق آلي (Android/Frontend)

```bash
# راقب أخطاء JS بكل شاشة أثناء فتحها يدوياً على الجهاز المتصل بـ USB debugging
adb logcat -c   # تفريغ السجل القديم
adb logcat | grep -i "chromium\|console\|error" &
# افتح كل شاشة من الـ26 يدوياً بالتسلسل، سجّل أي سطر أحمر/error يظهر
```

## 5. قائمة فحص يدوي إلزامية (لا بديل آلي لها)

| البند | طريقة الفحص |
|-------|---------------|
| هل شاشة `admin/category.html` الديناميكية (`?cat=`) فعلاً بتشتغل CRUD كامل على **كل** الـ22 جدول ولا بس على بعضها؟ | افتح `?cat=` لكل جدول من الـ22 وجرّب Add/Edit/Delete فعلياً |
| هل `admin/search-records.html` (البحث الشامل) بيرجع نتائج صحيحة أوفلاين من `sync_v4.db` المحلي؟ | بحث بالاسم / رقم الهوية بوضع Airplane Mode |
| هل `admin/user-requests.html` (طلبات المستخدمين) مربوطة فعلياً بجدول حقيقي، ولا شاشة واجهة بدون خلفية backend كاملة؟ | تحقق من وجود Controller/Route حقيقي يخدمها في `AdminCrudControllerV4` |
| هل تصدير Excel بشاشة الكفالات (المذكور بالكتالوج) شغّال فعلياً أم مجرد زر بدون تنفيذ؟ | اضغط زر التصدير وتحقق من الملف الناتج |
| هل `admin/civil-import.html` (استيراد Excel للسجل المدني) يتعامل صح مع ملف كبير (احتمال timeout كالمشكلة اللي واجهناها بـ file_index_v4)؟ | استيراد ملف تجريبي بـ 5000+ صف وقياس الوقت |

## 6. الخطوة التالية بعد التنفيذ

شغّل كل أوامر الأقسام 3-4، واملأ جدول القسم 5 يدوياً، وابعتلي النتائج — عندها بكتب ملف `13_ADMIN_PANEL_GAPS_FIX_PLAN.md` بقائمة نواقص **فعلية مؤكَّدة** (مو افتراضية) مع خطة إصلاح محددة لكل نقص، بدل تخمين نواقص غير موجودة أصلاً.
