# تم إصلاح مشكلة تحديث الحالة الصحية

## المشكلة
كانت الحالة الصحية `person_health_status` في جدول `re_people` لا يتم تحديثها عند حفظ النموذج للشخص `444183149` من نوع `family_member`.

## السبب
الكود كان يحتوي فقط على منطق **INSERT** (عند إنشاء سجل جديد) ولكن لم يكن يحتوي على منطق **UPDATE** (عند تحديث سجل موجود).

## الحل
تمت إضافة كود التحديث في الملف:
`app/Http/Controllers/Users/ShowGeneralRegisrationController.php`

في السطر **1508-1550** تقريباً، تمت إضافة:

```php
// ✅ حقل الحالة الصحية - تحديث في re_people لفرد العائلة
if ($fieldKey === 'field_health_status' && $fieldValue !== null && $fieldValue !== '') {
    if ($sponsorship->person_type === 'family_member') {
        // فرد عائلة: تحديث في re_people.person_health_status
        $healthStatusId = $this->resolveLookupIdByDescription('health_statuses', $fieldValue);
        
        if ($healthStatusId !== null) {
            $rePerson = DB::table('re_people')
                ->where('person_id', $sponsorship->identity_number)
                ->first();

            if ($rePerson) {
                DB::table('re_people')
                    ->where('id', $rePerson->id)
                    ->update([
                        'person_health_status' => $healthStatusId,
                        'updated_at' => now(),
                    ]);

                Log::info('✅ FAMILY_MEMBER_HEALTH_STATUS_UPDATED', [
                    'sponsorship_id' => $sponsorship->id,
                    'person_id' => $sponsorship->identity_number,
                    're_people_id' => $rePerson->id,
                    'health_status_id' => $healthStatusId,
                    'health_status_text' => $fieldValue,
                ]);
            }
        }
        
        $mappedToRePeople[] = $fieldKey;
    }
    continue;
}
```

## الآلية
1. عند حفظ النموذج، يتحقق الكود من وجود حقل `field_health_status`
2. إذا كان نوع الشخص `family_member`، يتم:
   - تحويل النص (مثل "سليم") إلى رقم ID من جدول `health_statuses`
   - البحث عن سجل الشخص في `re_people` باستخدام `person_id`
   - تحديث حقل `person_health_status` بالقيمة الجديدة

## الاختبار
تم اختبار التحديث على الشخص `444183149`:
- **قبل**: `person_health_status = NULL`
- **بعد**: `person_health_status = 1` (سليم)

## الحالات الصحية المتاحة
- ID: 0 => Unknown
- ID: 1 => سليم
- ID: 2 => مريض
- ID: 3 => مريض مزمن
- ID: 4 => معاق

## الخطوة التالية
يمكنك الآن فتح النموذج للشخص `444183149` وتحديد أي حالة صحية وحفظها. سيتم تحديث `re_people.person_health_status` بشكل صحيح.
