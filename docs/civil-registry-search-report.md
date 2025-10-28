# تقرير: كيفية عمل عملية البحث في السجل المدني (موجز تقني)

مستلم المهمة — سأنشئ تقريرًا بصيغة Markdown يشرح كيف تعمل عملية البحث في المشروع عبر الواجهة، الجافاسكربت، الراوتس، الكونترولر، السرفيس، والنموذج.

## قائمة التحقق
- [x] شرح HTML / Blade وحقول البحث وواجهة المستخدم (`resources/views/admin/dashboard/civil_registry/index.blade.php`)
- [x] شرح JavaScript (client-side) وكيف تُرسل الطلبات وتعرض النتائج (السكريبت المضمن وملفات `public/js` المذكورة)
- [x] توضيح Laravel routes المستخدمة (`routes/web.php`)
- [x] توضيح منطق السيرفر: Controller (`app/Http/Controllers/CivilRegistrySearchController.php`)
- [x] توضيح منطق الخدمة والفهرسة/الاستعلامات (`app/Services/CivilRegistryScoutSearchService.php`)
- [x] توضيح نموذج Eloquent ودمجه مع Scout وفهرسة (`app/Models/CivilRegistryPerson.php`)
- [x] ملاحظات عن الفهارس والـ cache واقتراحات تحسين

## لمحة عامة على سير البحث
1. المستخدم يفتح modal البحث في `resources/views/admin/dashboard/civil_registry/index.blade.php`.
2. يملأ الحقول (نص البحث، نوع البحث، فلترات، limit) ثم يضغط "بحث".
3. JavaScript يكّون طلب GET إلى endpoint مناسب مثل `/api/civil-registry/quick-search` مع query params.
4. Route يوجّه الطلب إلى `CivilRegistrySearchController` عبر `routes/web.php`.
5. Controller يفوض إلى `CivilRegistryScoutSearchService` لاسترجاع النتائج.
6. Service ينفذ استعلامات مباشرة على DB أو عبر Scout، يستخدم `Cache::remember` لنتائج قصيرة الأمد، ويعيد JSON.
7. JS يعرض النتائج في المودال ويحدّث معلومات زمن البحث وعدد النتائج.

## واجهة المستخدم (HTML / Blade)
- الملف: `resources/views/admin/dashboard/civil_registry/index.blade.php`
- الحقول الأساسية:
  - `search_text` (نص البحث)
  - `search_type` (quick, advanced, comprehensive, id_only, name_only)
  - `gender`, `birth_year`, `is_alive`, `limit`
- عناصر عرض: `#civilRegistryLoadingOverlay`, `#civilRegistryResults`, `#civilRegistryStats`.
- تضمين ملفات CSS/JS: `asset('css/person-search.css')`, `asset('js/person-search.js')`, `asset('js/scout-search-fixed.js')`.

## جانب العميل (JavaScript)
- الكود المضمن في نفس الـ Blade يتعامل مع:
  - حدث `submit` على `#civilRegistrySearchForm`، و`performCivilRegistrySearch()` لبناء الطلب.
  - اختيار endpoint بناءً على `search_type`:
    - quick -> `/api/civil-registry/quick-search`
    - advanced -> `/api/civil-registry/advanced-search`
    - comprehensive -> `/api/civil-registry/comprehensive-search`
    - id_only -> `/api/civil-registry/search-by-id`
    - name_only -> `/api/civil-registry/search-by-name`
  - يعرض شاشة تحميل، يحسب زمن البحث، ويعرض النتائج داخل `#civilRegistryResultsContent`.
- وظائف إضافية:
  - `#civilTestConnection` يستدعي `/api/civil-registry/test-connection`.
  - `#civilClearCache` يرسل POST إلى `/api/civil-registry/clear-cache`.

## Routes (Laravel)
- الملف: `routes/web.php`
- مجموعة `Route::prefix('api/civil-registry')` تشمل:
  - GET `/` -> index
  - GET `quick-search` -> `CivilRegistrySearchController@quickSearch`
  - GET `advanced-search` -> `...@advancedSearch`
  - GET `search-by-id` -> `...@searchById`
  - GET `search-by-name` -> `...@searchByName`
  - GET `comprehensive-search` -> `...@comprehensiveSearch`
  - GET `database-stats` -> `...@getStats`
  - GET `test-connection` -> `...@testConnection`
  - POST `index-data` -> `...@indexData`
  - POST `clear-cache` -> `...@clearCache`

## Controller
- الملف: `app/Http/Controllers/CivilRegistrySearchController.php`
- مسؤول عن:
  - التحقق من الطلبات (Validator)
  - تفويض المهام إلى `CivilRegistryScoutSearchService`
  - صياغة استجابة JSON موحدة: `success`, `message`, `data`, `total_count`, `execution_time`, `engine`.
- يحتوي على طرق لكل سيناريو: `quickSearch`, `advancedSearch`, `searchById`, `searchByName`, `comprehensiveSearch`, `getStats`, `indexData`, `testConnection`, `clearCache`.

## Service — كيف تُجرى الاستعلامات والفهرسة
- الملف: `app/Services/CivilRegistryScoutSearchService.php`
- نقاط رئيسية:
  - يستخدم اتصال قاعدة بيانات مخصص `civilregistry` و جدول `persons`.
  - يستخدم `Cache::remember` مع prefix `civil_scout_search_` (timeout افتراضي 300 ثانية).

- `quickScoutSearch($query, $limit)`:
  - إن كان `is_numeric($query)`: بحث مباشر على `CI_ID_NUM` (مطابق تام أو LIKE بداية) — يعتمد على وجود فهرس على `CI_ID_NUM` للأداء.
  - إن كان نصياً: استراتيجيات متعددة (تفكيك كلمات، CASE لتحديد `match_priority`, بحث بداية الاسم أولاً ثم fallbacks باستخدام `%LIKE%`).
  - يعيد `engine` = 'Civil Registry Direct DB (Ultra Fast v2)'.

- `advancedScoutSearch($filters, $limit)`:
  - يستخدم Scout (`CivilRegistryPerson::search($searchQuery)`) ثم يضيف where للفلترات إن أمكن.
  - يعيد `engine` = 'Civil Registry Scout Advanced'.

- `searchByIdNumber($idNumber)`:
  - استعلام مباشر على DB مع افتراض وجود فهرس؛ `engine` = 'Civil Registry Direct Index'.

- `searchByFullName($fullName, $limit)`:
  - يستخدم `CivilRegistryPerson::searchByFullName` (Scout).

- `comprehensiveSearch($criteria)`:
  - يجمع نتائج من quick, by-id, by-name ثم يحسب `total_count`.

- `getDatabaseStats()`:
  - يجلب إحصائيات counts (total, alive, male, female) ويخزنها ساعة في الكاش.

- `indexData($chunkSize)`:
  - يفهرس البيانات إلى محرك Scout عبر `CivilRegistryPerson::chunk(...)` ويدعو `searchable()`.

- `clearCache()`:
  - يستخدم `Cache::tags(['civil_scout_search'])->flush()` — تأكد من دعم موفر الكاش للـ tags (Redis يدعمه، file لا يدعمه).

- `testConnection()`:
  - يجري `getDatabaseStats()` و`quickScoutSearch('محمد',5)` ويعيد النتيجة لاختبار الصحة.

## Model (Scout) — ملاحظات على الفهرسة
- الملف: `app/Models/CivilRegistryPerson.php`
- يستخدم `Laravel\Scout\Searchable`.
- `searchableAs()` ➜ `civil_registry_persons`.
- `toSearchableArray()` يحدد الحقول المرسلة لمحرك Scout.
- `shouldBeSearchable()` يمنع فهرسة السجلات غير الكاملة (بدون CI_ID_NUM أو CI_FIRST_ARB).
- يحتوي وظائف مساعدة `quickSearch`, `searchByFullName`, `advancedSearch` التي تستدعي Scout.

## نقاط فنية مهمة و اقتراحات
- تحقق من وجود فهرس (index) على `CI_ID_NUM` في قاعدة `civilregistry` لسرعة البحث برقم الهوية.
- راجع `config/scout.php` و`.env` (`SCOUT_DRIVER`) لمعرفة محرك Scout الفعلي (Meilisearch, Algolia, database...)
- `Cache::tags(...)` يتطلب موفر كاش يدعم الوسوم (Redis أو Memcached). إذا الموفر فِيلَي، استخدم مفاتيح عادية أو غيّر الموفر.
- تحسينات مقترحة:
  - استخدم محرك بحث نصي مخصص (Meilisearch/Elasticsearch) للأسماء بدلاً عن LIKE في DB إذا الحجم كبير.
  - أدخل pagination وcursor-based pagination بدلاً من إرجاع كل النتائج دفعة واحدة.
  - أضف rate-limiting للـ API العامة.
  - ضمن عملية `indexData` إضافة retry/logging للدفعات الفاشلة.

## أماكن الملفات الرئيسية (مراجع سريعة)
- View: `resources/views/admin/dashboard/civil_registry/index.blade.php`
- Routes: `routes/web.php`
- Controller: `app/Http/Controllers/CivilRegistrySearchController.php`
- Service: `app/Services/CivilRegistryScoutSearchService.php`
- Model: `app/Models/CivilRegistryPerson.php`
- ملفات JS/CSS المضمنة: `public/js/person-search.js`, `public/js/scout-search-fixed.js`, `public/css/person-search.css` (مذكورة في الـ Blade)

## قيود ومعلومات لم يتم تأكيدها من الكود فقط
- إعداد محرك Scout الفعلي وتهيئته (`config/scout.php` و`.env`).
- وجود فهرس فعلي على الحقول في قاعدة البيانات (يتطلب مراجعة المهاجرات أو DB schema مباشرة).
- دعم `Cache::tags` من موفر الكاش الحالي.

## الخلاصة والمرحلة التالية المقترحة
- التقرير تم إنشاؤه بصيغة Markdown في `docs/civil-registry-search-report.md` (الملف هذا).
- إذا تريد التأكد الكامل، أستطيع:
  - قراءة `config/scout.php` و`.env` الآن للتأكد من محرك Scout.
  - اقتراح Migration لإنشاء index على `CI_ID_NUM` (مثال جاهز).
  - فتح ملفات JS المضمنة لتوثيق أي سلوك إضافي أو تحسين UX للبحث.

---
التقرير جاهز كمستند Markdown داخل المجلد `docs`. أخبرني ما الخطوة التالية التي تفضلها.
