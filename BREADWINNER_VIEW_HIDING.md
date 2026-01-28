# توثيق إخفاء الأقسام للمعيل (Breadwinner)

## المشكلة
عندما يكون الشخص المكفول من نوع **معيل** (`person_type = 'breadwinner'`)، يجب إخفاء الأقسام التالية من نموذج التسجيل:

1. **معلومات المكفول الأساسية** (Category ID: 1)
2. **المعلومات الدراسية** (Category ID: 3)
3. **الحالة النفسية والسلوكية** (Category ID: 4)
4. **الجوانب الدينية** (Category ID: 5)
5. **احتياجات وإبداع** (Category ID: 7)

## السبب
- المعيل هو الشخص الأساسي في الكفالة
- لا يحتاج المعيل لمعلومات دراسية أو نفسية (هذه للأيتام فقط)
- معلومات المعيل تُركز على: معلومات تفصيلية (صلة القرابة، الحالة الصحية، الوظيفة، عدد المعالين)

## بنية تخزين البيانات

### عندما person_type = 'breadwinner'

| البيان | الجدول | الأعمدة المهمة |
|--------|--------|----------------|
| **معلومات المعيل الأساسية** | `re_people` | `person_id`, `first_name`, `second_name`, `third_name`, `last_name`, `person_health_status` |
| **معلومات المعيل التفصيلية** | `data` | `data_relationship`, `data_health_status`, `data_emloyment_status_breadwinner`, `data_number_female`, `data_number_mail` |

**الربط**: `re_people.registration_id` → `data.file_id_number`

### مثال من قاعدة البيانات

```
sponsorships:
  id: 138
  person_type: breadwinner
  identity_number: 434128831
  orphan_name: يوسف محمد اسماعيل ابو طماعه

re_people:
  id: 138
  person_id: 434128831
  registration_id: 031690
  first_name: يوسف
  second_name: محمد
  third_name: اسماعيل
  last_name: ابو طماعه
  person_health_status: NULL

data:
  file_id_number: 031690
  data_relationship: NULL
  data_health_status: 1 (سليم)
  data_number_female: X
  data_number_mail: Y
```

## الحل المطبق

### 1. تعديل View (generalRegisrationIndex.blade.php)

```php
@foreach($groupedFields as $categoryId => $categoryData)
    @php
        // إخفاء الأقسام التالية للمعيل
        $hiddenCategoriesForBreadwinner = [1, 3, 4, 5, 7];
        $personType = $sponsorship->person_type ?? null;
        
        if ($personType === 'breadwinner' && in_array($categoryId, $hiddenCategoriesForBreadwinner)) {
            continue;
        }
    @endphp
    
    {{-- عرض القسم --}}
@endforeach
```

### 2. تعديل View الجديد (generalRegisrationIndex_new.blade.php)

```php
{{-- معلومات المكفول الأساسية - يتم إخفاؤها عندما يكون الشخص معيل --}}
@if(isset($enabledFields) && count(array_filter($enabledFields, fn($f) => $f['category_id'] == 1)) > 0 && $sponsorship->person_type !== 'breadwinner')
    {{-- عرض القسم --}}
@endif
```

## الأقسام التي تظهر للمعيل

✅ **معلومات السكن** (Category ID: 2)
✅ **معلومات المعيل التفصيلية** (Category ID: 13) - الأهم!
✅ **أفراد الأسرة** (إن وجدوا)
✅ **المعلومات البنكية** (إن وجدت)

## أمثلة على الحقول المعروضة للمعيل

من **Category 13 (معلومات المعيل التفصيلية)**:
- صلة القرابة (`field_guardian_relationship`)
- الحالة الصحية (`field_guardian_health`)
- الحالة الوظيفية (`field_guardian_job`)
- عدد من يعيلهم من الإناث (`field_dependents_female`)
- عدد من يعيلهم من الذكور (`field_dependents_male`)

## الملفات المعدلة

1. `resources/views/user/dashboard/component/generalRegisrationIndex.blade.php`
   - إضافة شرط `@php continue @endphp` للفئات المخفية

2. `resources/views/user/dashboard/component/generalRegisrationIndex_new.blade.php`
   - إضافة شرط `&& $sponsorship->person_type !== 'breadwinner'`

## الاختبار

لاختبار الحل:
1. افتح نموذج تسجيل لشخص من نوع `breadwinner`
2. تحقق من عدم ظهور الأقسام: معلومات المكفول الأساسية، المعلومات الدراسية، النفسية، الدينية، الاحتياجات
3. تحقق من ظهور: معلومات المعيل التفصيلية، معلومات السكن

## ملاحظات مهمة

⚠️ **الحالة الصحية للمعيل**:
- تُخزن في `data.data_health_status` (للمعيل الأساسي)
- تُخزن في `re_people.person_health_status` (إذا كان المعيل family_member)

⚠️ **الفرق بين الأنواع**:
- `breadwinner`: معيل حي - يخزن في re_people + data
- `family_member`: فرد عائلة (يتيم) - يخزن في re_people
- `deceased_father`/`deceased_mother`: والد متوفي - يخزن في dead_people

## تحديث لاحق (إن لزم)

إذا احتجت لإضافة حقول جديدة للمعيل:
- ضعها في **Category 13** (معلومات المعيل التفصيلية)
- تأكد من تخزينها في جدول `data` وليس `re_people`
