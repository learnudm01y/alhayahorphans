# نظام البحث عن العلاقات العائلية - Family Relations Search System

## نظرة عامة
تم تطوير نظام متكامل للبحث عن العلاقات العائلية في السجل المدني باستخدام قاعدة بيانات `civilregistry` مع تحسينات كبيرة في الأداء.

---

## المكونات الرئيسية

### 1. قاعدة البيانات

#### الجداول المستخدمة:
- **persons**: يحتوي على معلومات المواطنين
- **relations**: يحتوي على العلاقات العائلية بين الأشخاص
- **category_of_relations**: يحتوي على أنواع العلاقات (أب، أم، أخ، إلخ)

#### هيكل جدول relations:
```sql
- CF_ID_NUM: رقم هوية الشخص الأساسي
- CF_ID_RELATIVE: رقم هوية الشخص المرتبط (القريب)
- CF_RELATIVE_CD: نوع العلاقة (يشير إلى category_of_relations)
```

#### الفهارس المضافة (B-Tree Indexes):
تم إضافة فهارس B-Tree لتسريع عمليات البحث بشكل كبير:

1. **idx_cf_id_num**: فهرس على `CF_ID_NUM` للبحث السريع عن علاقات شخص معين
2. **idx_cf_id_relative**: فهرس على `CF_ID_RELATIVE` للبحث العكسي
3. **idx_cf_relative_cd**: فهرس على `CF_RELATIVE_CD` لتسريع الربط مع أنواع العلاقات
4. **idx_cf_id_num_relative_cd**: فهرس مركب على (`CF_ID_NUM`, `CF_RELATIVE_CD`) للبحث المحسن
5. **idx_cf_id_relative_relative_cd**: فهرس مركب على (`CF_ID_RELATIVE`, `CF_RELATIVE_CD`) للعلاقات العكسية

**ملف Migration**: `database/migrations/2025_11_08_000001_add_indexes_to_relations_table.php`

---

### 2. Models

#### Relation Model (`app/Models/Relation.php`)
Model محسّن يحتوي على:
- اتصال بقاعدة بيانات `civilregistry`
- علاقات مع `Persons` و `CategoryOfRelation`
- دوال مساعدة للبحث:
  - `getPersonRelations($idNum)`: جلب العلاقات المباشرة
  - `getFamilyTree($idNum)`: جلب شجرة العائلة الكاملة (الاتجاهين)
  - `getRelationsByType($idNum, $relationType)`: البحث حسب نوع القرابة
  - `getParents($idNum)`: جلب الوالدين
  - `getChildren($idNum)`: جلب الأبناء

#### تحديث Persons Model
تم إضافة العلاقات العائلية:
```php
public function relations() // العلاقات المباشرة
public function reverseRelations() // العلاقات العكسية
```

---

### 3. Controller

#### FamilyRelationController (`app/Http/Controllers/Admin/FamilyRelationController.php`)

##### الوظائف الرئيسية:

1. **searchFamilyRelations(Request $request)**
   - البحث عن جميع العلاقات العائلية لشخص معين
   - المعاملات: `id_number` (رقم الهوية)
   - يعيد: معلومات الشخص + قائمة أفراد العائلة + إحصائيات

2. **getFamilyTree(Request $request)**
   - بناء شجرة العائلة بعمق محدد
   - المعاملات: `id_number`, `depth` (اختياري، القيمة الافتراضية: 2)
   - يعيد: شجرة العائلة الكاملة

3. **getRelationsStatistics()**
   - إحصائيات عامة عن العلاقات
   - يعيد: إجمالي العلاقات، عدد الأشخاص، أنواع العلاقات

4. **clearRelationsCache()**
   - مسح الكاش الخاص بالعلاقات

##### التحسينات:
- استخدام **Cache** لمدة 60 دقيقة
- استعلامات محسّنة مع **JOIN** بدلاً من Eloquent لتحسين الأداء
- استخدام الفهارس للبحث السريع
- معالجة الأخطاء الشاملة مع **Logging**

---

### 4. Routes

تم إضافة المسارات في `routes/admin.php`:

```php
Route::prefix('family-relations')->name('family.relations.')->group(function () {
    Route::post('/search', [FamilyRelationController::class, 'searchFamilyRelations'])->name('search');
    Route::post('/family-tree', [FamilyRelationController::class, 'getFamilyTree'])->name('tree');
    Route::get('/statistics', [FamilyRelationController::class, 'getRelationsStatistics'])->name('statistics');
    Route::post('/clear-cache', [FamilyRelationController::class, 'clearRelationsCache'])->name('clear.cache');
});
```

**ملاحظة**: جميع المسارات محمية ضمن مجموعة `admin` وتتطلب صلاحيات Admin.

---

### 5. الواجهة الأمامية

#### Modal البحث العائلي
تم إضافة Modal جديد في `resources/views/admin/dashboard/civil_registry/index.blade.php`:

##### المكونات:
1. **نموذج البحث**: إدخال رقم الهوية
2. **معلومات الشخص**: عرض بيانات الشخص المبحوث عنه
3. **إحصائيات**: عرض إحصائيات العلاقات (إجمالي، مباشر، عكسي، وقت التنفيذ)
4. **جدول أفراد العائلة**: عرض تفصيلي لجميع الأقارب

##### المميزات:
- واجهة عربية كاملة
- شاشة تحميل أثناء البحث
- عرض النتائج في جدول منظم
- تصنيف العلاقات (مباشر/عكسي)
- عرض حالة الحياة (حي/متوفي)
- إحصائيات في الوقت الفعلي

---

## طريقة الاستخدام

### 1. تشغيل Migration (إذا لم يتم تشغيله)
```bash
php artisan migrate --path=database/migrations/2025_11_08_000001_add_indexes_to_relations_table.php
```

### 2. الوصول إلى النظام
1. انتقل إلى صفحة إدارة المواطنين: `/admin/civil-registry`
2. اضغط على زر "البحث عن العلاقات العائلية" (الزر الأخضر)
3. أدخل رقم الهوية في النموذج
4. اضغط على "بحث عن العائلة"

### 3. قراءة النتائج
- **معلومات الشخص**: بيانات الشخص المبحوث عنه
- **الإحصائيات**: أعداد العلاقات وأنواعها
- **جدول أفراد العائلة**: قائمة كاملة بالأقارب مع:
  - رقم الهوية
  - الاسم الكامل
  - نوع العلاقة
  - الاتجاه (مباشر/عكسي)
  - الجنس
  - العمر
  - حالة الحياة

---

## API Endpoints

### 1. البحث عن العلاقات
**POST** `/admin/family-relations/search`

**Request Body:**
```json
{
  "id_number": "123456789"
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم العثور على العلاقات العائلية بنجاح",
  "data": {
    "person": { ... },
    "relations": [ ... ],
    "family_members": [ ... ],
    "statistics": {
      "total_relations": 10,
      "direct_relations": 6,
      "reverse_relations": 4,
      "relation_types": { ... },
      "execution_time_ms": 45.32
    }
  }
}
```

### 2. شجرة العائلة
**POST** `/admin/family-relations/family-tree`

**Request Body:**
```json
{
  "id_number": "123456789",
  "depth": 3
}
```

### 3. الإحصائيات
**GET** `/admin/family-relations/statistics`

### 4. مسح الكاش
**POST** `/admin/family-relations/clear-cache`

---

## التحسينات والأداء

### 1. الفهارس (Indexes)
- استخدام فهارس B-Tree على جميع الأعمدة المستخدمة في البحث
- فهارس مركبة للبحث المحسن
- تسريع عمليات JOIN بشكل كبير

### 2. الكاش (Caching)
- تخزين مؤقت للنتائج لمدة 60 دقيقة
- تقليل الضغط على قاعدة البيانات
- إمكانية مسح الكاش يدوياً

### 3. الاستعلامات المحسنة
- استخدام Query Builder بدلاً من Eloquent للاستعلامات المعقدة
- دمج الجداول باستخدام JOIN للحصول على البيانات دفعة واحدة
- تقليل عدد الاستعلامات

### 4. معالجة الأخطاء
- Try-Catch شامل في جميع الوظائف
- Logging لجميع الأخطاء
- رسائل خطأ واضحة للمستخدم

---

## آلية العمل

### 1. البحث الثنائي الاتجاه
النظام يبحث في اتجاهين:

**العلاقات المباشرة** (Direct Relations):
- الشخص موجود في `CF_ID_NUM`
- يجلب جميع الأقارب من `CF_ID_RELATIVE`
- مثال: البحث عن أبناء شخص معين

**العلاقات العكسية** (Reverse Relations):
- الشخص موجود في `CF_ID_RELATIVE`
- يجلب جميع الأشخاص من `CF_ID_NUM`
- مثال: البحث عن والدي شخص معين

### 2. تصنيف العلاقات
يتم تحديد نوع العلاقة من خلال:
- `CF_RELATIVE_CD`: رقم يشير إلى نوع القرابة
- `category_of_relations.attribute`: الوصف النصي (أب، أم، أخ، إلخ)

### 3. الأداء
- **قبل الفهارس**: استعلامات بطيئة قد تستغرق عدة ثوانٍ
- **بعد الفهارس**: استعلامات سريعة جداً (أقل من 50 ميلي ثانية في معظم الحالات)

---

## الصيانة والتطوير

### إضافة نوع علاقة جديد
1. أضف السجل في جدول `category_of_relations`
2. لا حاجة لتعديل الكود - النظام يدعم جميع أنواع العلاقات تلقائياً

### تحسين الأداء أكثر
1. **زيادة حجم الكاش**: غيّر `CACHE_DURATION` في Controller
2. **استخدام Redis**: للكاش الموزع في بيئات الإنتاج
3. **تحليل الاستعلامات**: استخدم `EXPLAIN` لتحليل الاستعلامات البطيئة

### حل المشاكل
- **بطء في البحث**: تأكد من تشغيل Migration الخاص بالفهارس
- **نتائج خاطئة**: تحقق من بيانات جدول `relations`
- **مشاكل الكاش**: استخدم زر "مسح الكاش"

---

## الأمان

### الصلاحيات
- جميع المسارات محمية ضمن مجموعة `admin`
- يتطلب تسجيل الدخول كـ Admin
- لا middleware إضافية (كما طلب المستخدم)

### التحقق من المدخلات
- التحقق من رقم الهوية قبل البحث
- معالجة SQL Injection عبر استخدام Parameter Binding
- تحديد حد أقصى لعمق شجرة العائلة (5 مستويات)

---

## الملفات المنشأة/المعدلة

### ملفات جديدة:
1. `database/migrations/2025_11_08_000001_add_indexes_to_relations_table.php`
2. `app/Http/Controllers/Admin/FamilyRelationController.php`

### ملفات معدلة:
1. `app/Models/Relation.php` - تحديث كامل
2. `app/Models/Persons.php` - إضافة علاقات
3. `routes/admin.php` - إضافة مسارات
4. `resources/views/admin/dashboard/civil_registry/index.blade.php` - إضافة واجهة

---

## الخلاصة

تم تطوير نظام متكامل وسريع للبحث عن العلاقات العائلية مع:
- ✅ فهارس B-Tree لتسريع البحث
- ✅ Caching للأداء الأمثل
- ✅ واجهة مستخدم عربية سهلة
- ✅ API كامل للتكامل
- ✅ معالجة أخطاء شاملة
- ✅ توثيق كامل
- ✅ أمان محسّن

النظام جاهز للاستخدام الفوري! 🚀
