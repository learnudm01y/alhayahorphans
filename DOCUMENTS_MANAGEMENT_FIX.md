# إصلاح مشكلة عرض الوثائق في المودال

## المشكلة
الوثائق لا تظهر في تبويب "الوثائق" داخل مودال إدارة حقول الجمعيات.

## السبب
كان هناك تعارض بين:
1. الـ Blade يعرض الوثائق بشكل ثابت عند تحميل الصفحة
2. JavaScript يحاول استبدال المحتوى بالكامل عند التبديل للتبويب

## الحل المطبق

### 1. تعديل Blade Template
**الملف**: `resources/views/admin/dashboard/sponsors/fields-management.blade.php`

التغييرات:
- إضافة `id="documents_container"` للـ div الرئيسي للوثائق
- إضافة `data-doc-id="{{ $docType->id }}"` لكل بطاقة وثيقة
- تغيير class من `document-toggle` إلى `document-enabled-toggle` للمفتاح الرئيسي
- تغيير `data-category` إلى `data-toggle-type` للخيارات الفرعية

### 2. تعديل JavaScript
**الملف**: `public/js/sponsor-fields-management.js`

التغييرات الرئيسية:

#### استبدال `displayDocuments()` بـ `updateDocumentsState()`
```javascript
// القديم: كان يستبدل المحتوى بالكامل
displayDocuments() {
    $('#documents_tab').html(html);
}

// الجديد: يحدث حالة العناصر الموجودة فقط
updateDocumentsState() {
    // إعادة تعيين جميع الـ checkboxes
    $('.document-enabled-toggle').prop('checked', false);
    
    // تحديث بناءً على البيانات
    self.documentsData.forEach(doc => {
        const $card = $(`.document-card[data-doc-id="${doc.id}"]`);
        $card.find('.document-enabled-toggle').prop('checked', doc.is_enabled);
        // ... تحديث باقي الخيارات
    });
}
```

#### تعديل `loadDocumentSettings()`
```javascript
loadDocumentSettings(sponsorId) {
    // بدلاً من استبدال المحتوى، نستخدم opacity للإشارة للتحميل
    const $container = $('#documents_container');
    $container.css('opacity', '0.5');
    
    $.ajax({
        success: function(response) {
            self.updateDocumentsState(); // استدعاء التحديث بدلاً من العرض
            $container.css('opacity', '1');
        }
    });
}
```

### 3. Routes
**الملف**: `routes/admin.php`

```php
Route::get('sponsors/{sponsor}/documents', [SponsorController::class, 'getDocumentSettings'])
    ->name('sponsors.get-documents');
Route::post('sponsors/{sponsor}/documents', [SponsorController::class, 'saveDocumentSettings'])
    ->name('sponsors.save-documents');
```

### 4. Controller Methods
**الملف**: `app/Http/Controllers/Admin/SponsorController.php`

```php
public function getDocumentSettings($sponsorId) {
    // جلب جميع أنواع الوثائق مع إعدادات الجمعية
    $documentTypes = DocumentType::with(['sponsorDocumentTypes' => function($query) use ($sponsorId) {
        $query->where('sponsor_id', $sponsorId);
    }])->get();
    
    // تحويل البيانات إلى صيغة مناسبة
    $settings = $documentTypes->map(function($docType) {
        $sponsorSetting = $docType->sponsorDocumentTypes->first();
        return [
            'id' => $docType->id,
            'description' => $docType->description,
            'is_enabled' => $sponsorSetting ? $sponsorSetting->is_enabled : false,
            // ... باقي الحقول
        ];
    });
    
    return response()->json(['success' => true, 'data' => $settings]);
}

public function saveDocumentSettings(Request $request, $sponsorId) {
    // حذف الإعدادات القديمة
    SponsorDocumentType::where('sponsor_id', $sponsorId)->delete();
    
    // إضافة الإعدادات الجديدة
    foreach ($validated['documents'] as $doc) {
        if ($doc['is_enabled'] ?? false) {
            SponsorDocumentType::create([...]);
        }
    }
}
```

## اختبار الحل

### 1. اختبار يدوي
1. افتح صفحة إدارة حقول الجمعيات: `/admin/sponsors/fields-management`
2. اضغط على "إدارة الحقول" لأي جمعية
3. انتقل إلى تبويب "الوثائق"
4. يجب أن ترى جميع الوثائق (21 وثيقة)
5. فعّل بعض الوثائق واختر الأقسام
6. احفظ التغييرات
7. أغلق المودال وأعد فتحه
8. تحقق أن الإعدادات محفوظة

### 2. اختبار API
زيارة: `/test-documents-api`
- اختبار جلب الإعدادات
- اختبار حفظ الإعدادات

### 3. Console Logs
افتح Developer Console وراقب:
```
Tab switched to: #documents_tab
Loading documents for sponsor: X
Document states updated [...]
```

## التحقق من البيانات

### عرض أنواع الوثائق
```bash
php test_documents.php
```

### عرض إعدادات جمعية معينة
```sql
SELECT sd.*, dt.description 
FROM sponsor_document_types sd
JOIN document_types dt ON sd.document_type_id = dt.id
WHERE sd.sponsor_id = 1;
```

## الملفات المعدلة
1. ✅ `resources/views/admin/dashboard/sponsors/fields-management.blade.php`
2. ✅ `public/js/sponsor-fields-management.js`
3. ✅ `app/Http/Controllers/Admin/SponsorController.php`
4. ✅ `routes/admin.php`
5. ✅ `app/Models/Sponsor.php` (relationships)
6. ✅ `app/Models/DocumentType.php` (relationships)
7. ✅ `app/Models/SponsorDocumentType.php` (new model)
8. ✅ `database/migrations/2025_12_16_203125_create_sponsor_document_types_table.php`

## ملاحظات مهمة

### حالة الوثائق الافتراضية
عند أول فتح للمودال:
- جميع الوثائق غير مفعلة افتراضياً
- يجب على المدير تفعيل الوثائق المطلوبة لكل جمعية

### تفعيل الأقسام
- كل وثيقة يمكن تفعيلها لـ:
  - ✅ البيانات الأساسية (basic)
  - ✅ أفراد الأسرة (family)
  - ✅ المتوفين (deceased)

### سلوك UI
- عند تعطيل الوثيقة: تصبح البطاقة شبه شفافة وتتعطل جميع الخيارات
- عند تفعيل الوثيقة: تصبح البطاقة واضحة وتتفعل الخيارات

## التكامل مع صفحة تحديث البيانات
في المستقبل، عند عرض صفحة تحديث البيانات للمستخدم:

```php
// في Controller
$sponsorDocuments = SponsorDocumentType::where('sponsor_id', $sponsorId)
    ->where('is_enabled', true)
    ->with('documentType')
    ->get();

// تمرير للـ view
return view('user.update-data', [
    'documents' => $sponsorDocuments
]);
```

```blade
@foreach($documents as $sponsorDoc)
    @if($sponsorDoc->basic_enabled)
        {{-- عرض حقل رفع الوثيقة في قسم البيانات الأساسية --}}
    @endif
    @if($sponsorDoc->family_enabled)
        {{-- عرض حقل رفع الوثيقة في قسم أفراد الأسرة --}}
    @endif
    @if($sponsorDoc->deceased_enabled)
        {{-- عرض حقل رفع الوثيقة في قسم المتوفين --}}
    @endif
@endforeach
```

## الخلاصة
الحل يعتمد على:
1. ✅ عرض ثابت للوثائق في Blade
2. ✅ تحديث ديناميكي للحالة عبر JavaScript
3. ✅ API endpoints للجلب والحفظ
4. ✅ علاقات قاعدة البيانات الصحيحة

هذا النهج أفضل من الاستبدال الكامل لأنه:
- ✅ أسرع (لا حاجة لإعادة رسم DOM)
- ✅ يحافظ على event listeners
- ✅ يقلل من استهلاك الذاكرة
- ✅ يتجنب مشاكل التزامن
