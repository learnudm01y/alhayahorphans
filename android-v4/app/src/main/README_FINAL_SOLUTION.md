# ✅ الحل النهائي لمشكلة "unknown" في أسماء المجلدات

## المشكلة
الملفات تُحفظ في:
```
❌ Pictures/sponsorships_alhayahorphans/General/unknown/
```
بدلاً من:
```
✅ Pictures/sponsorships_alhayahorphans/جمعية_الحياة/أحمد_محمد/
```

---

## السبب
**Java (Android) لا يمكنه قراءة IndexedDB مباشرة** لأنها database في المتصفح (JavaScript).

---

## الحل الجذري ✅

تم إنشاء **Sponsorship Mapping System** يخزن أسماء الجمعيات والمكفولين في SQLite (Android) لكي يستخدمها Java.

### الملفات الجديدة:
1. `SponsorshipMappingHelper.java` - قاعدة بيانات SQLite للـ mappings
2. `IndexedDBBridge.java` - تحديث للسماح بحفظ mappings
3. `SOLUTION_MAPPING.js` - دالات JavaScript جاهزة للاستخدام

---

## طريقة الاستخدام (خطوة واحدة فقط!)

### في JavaScript - عند بدء التطبيق:

```javascript
import { syncAllSponsorshipsToAndroid } from './plugins/sponsorshipMapping';

// عند فتح التطبيق أو تسجيل الدخول
async mounted() {
  await syncAllSponsorshipsToAndroid();
  console.log('✅ All sponsorships synced to Android');
}
```

هذا كل شيء! الآن **جميع** رفع الملفات ستستخدم الأسماء الحقيقية تلقائياً.

---

## الكود الكامل (نسخ ولصق)

### 1. إنشاء ملف: `src/plugins/sponsorshipMapping.js`

```javascript
import { Plugins } from '@capacitor/core';
const { IndexedDBBridge } = Plugins;

export async function syncAllSponsorshipsToAndroid() {
  try {
    // جلب جميع الكفالات
    const sponsorships = await db.sponsorships.toArray();
    const associations = await db.associations.toArray();
    
    // map للجمعيات
    const associationMap = {};
    associations.forEach(a => {
      associationMap[a.id] = a.name || 'General';
    });
    
    // تحويل البيانات
    const mappings = sponsorships.map(sp => ({
      sponsorshipId: sp.id,
      associationName: associationMap[sp.association_id] || 'General',
      personName: sp.person_name || sp.name || 'unknown'
    }));
    
    // حفظ في Android
    const result = await IndexedDBBridge.saveSponsorshipMappings({ mappings });
    
    console.log(`✅ Synced ${result.count} sponsorships to Android`);
    return result;
    
  } catch (error) {
    console.error('❌ Sync failed:', error);
  }
}
```

### 2. في `App.vue` أو `main.js`:

```javascript
import { syncAllSponsorshipsToAndroid } from './plugins/sponsorshipMapping';

export default {
  async mounted() {
    // مزامنة البيانات عند فتح التطبيق
    await syncAllSponsorshipsToAndroid();
  }
}
```

---

## التحقق من النجاح

### افتح Logcat وابحث عن:

```
✅ Saved 100 sponsorship mappings to SQLite
✅ Found in mapping DB for sponsorshipId=908
✅ Association: جمعية الحياة
✅ Person: أحمد محمد
✅✅✅ Using REAL names from mapping database!
✅ Folder: Pictures/sponsorships_alhayahorphans/جمعية_الحياة/أحمد_محمد/
```

### إذا رأيت:
```
⚠️ No mapping found for sponsorshipId=908
⚠️ Using defaults - JavaScript must call IndexedDBBridge.saveSponsorshipMappings()!
```

**يعني:** لم يتم استدعاء `syncAllSponsorshipsToAndroid()` بعد.

---

## حالات الاستخدام

### حالة 1: عند بدء التطبيق
```javascript
mounted() {
  await syncAllSponsorshipsToAndroid();
}
```

### حالة 2: بعد تحميل بيانات جديدة من السيرفر
```javascript
async syncFromServer() {
  await downloadSponsorships();  // جلب من السيرفر
  await syncAllSponsorshipsToAndroid();  // مزامنة إلى Android
}
```

### حالة 3: عند إضافة كفالة جديدة
```javascript
async addNewSponsorship(data) {
  const id = await db.sponsorships.add(data);
  
  // حفظ mapping فوراً
  await IndexedDBBridge.saveSingleMapping({
    sponsorshipId: id,
    associationName: data.association_name,
    personName: data.person_name
  });
}
```

---

## الأسئلة الشائعة

### س: كم مرة يجب استدعاء `syncAllSponsorshipsToAndroid()`؟
**ج:** مرة واحدة عند بدء التطبيق كافية. يمكن استدعاؤها مرة أخرى بعد تحميل بيانات جديدة من السيرفر.

### س: هل يجب تمرير associationName/personName في `addFileToQueue()`؟
**ج:** لا! بعد استدعاء `syncAllSponsorshipsToAndroid()`، Java سيبحث تلقائياً باستخدام `photoId`.

### س: ماذا لو كانت أسماء الحقول في IndexedDB مختلفة؟
**ج:** عدّل في `syncAllSponsorshipsToAndroid()`:
```javascript
personName: sp.full_name || sp.person_name || 'unknown'
associationName: associationMap[sp.assoc_id] || 'General'
```

### س: كيف أمسح جميع mappings؟
**ج:** 
```javascript
await IndexedDBBridge.clearMappings();
```

---

## الهيكلية المتوقعة لـ IndexedDB

```javascript
// جدول sponsorships
{
  id: 908,                    // ← يستخدم كـ photoId عند الرفع
  person_name: "أحمد محمد",   // ← اسم المكفول
  association_id: 5,          // ← رقم الجمعية
  // ...
}

// جدول associations
{
  id: 5,                      // ← يطابق sponsorships.association_id
  name: "جمعية الحياة",       // ← اسم الجمعية
  // ...
}
```

---

## اختبار سريع

```javascript
// في console.log في المتصفح
import { syncAllSponsorshipsToAndroid } from './plugins/sponsorshipMapping';

// 1. مزامنة
await syncAllSponsorshipsToAndroid();

// 2. رفع ملف
await UploadService.addFileToQueue({
  filePath: '...',
  fileName: 'test.jpg',
  photoId: 908  // ← sponsorship_id
});

// 3. افتح Logcat - يجب أن ترى:
// ✅ Found in mapping DB for sponsorshipId=908
// ✅ Using REAL names from mapping database!
```

---

## الخلاصة

1. **نسخ** ملف `SOLUTION_MAPPING.js` إلى مجلد plugins
2. **استدعاء** `syncAllSponsorshipsToAndroid()` عند بدء التطبيق
3. **لا حاجة** لتمرير associationName/personName يدوياً بعد الآن
4. **جميع الملفات** ستُحفظ في المجلدات الصحيحة تلقائياً

**Build نجح ✅ | الحل جاهز ✅ | يحتاج سطر واحد في JavaScript فقط!**
