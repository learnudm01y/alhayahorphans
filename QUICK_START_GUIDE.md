# 🚀 Quick Start Guide
## File ID Generation & Sync Monitoring

---

## 📖 للبدء السريع

### 1. Backend Setup (Laravel)

```bash
# تشغيل الـ Migrations
cd /path/to/laravel/project
php artisan migrate

# التحقق من إنشاء الجداول
php artisan tinker
>>> DB::table('file_id_registry')->count();
>>> DB::table('sync_progress')->count();
```

### 2. Frontend Integration (Capacitor)

#### أ. إضافة الخدمات

```typescript
// في src/services/file-id-generator.service.ts
// انسخ الكود من FILE_ID_GENERATION_AND_SYNC_MONITORING.md

// في src/services/sync-progress-tracker.service.ts  
// انسخ الكود من OFFLINE_ONLINE_SYNC_ARCHITECTURE.md (تحديث)
```

#### ب. تحديث SmartSyncService

```typescript
// في src/services/smart-sync.service.ts

async syncEligibleSponsorships() {
    // بدء جلسة التتبع
    const sessionId = await SyncProgressTracker.startSyncSession();
    
    const sponsorships = await this.fetchEligibleSponsorships();
    
    for (const sponsorship of sponsorships) {
        // إضافة للتتبع
        const progressId = await SyncProgressTracker.addProgressItem(
            'data_upload',
            'sponsorship',
            sponsorship.orphan_name,
            { entityId: sponsorship.id?.toString() }
        );
        
        try {
            // التحقق من وجود رقم ملف
            if (!sponsorship.relation_id_number) {
                // توليد رقم ملف جديد
                const fileId = await FileIDGeneratorService.ensureFileIDExists(
                    'orphan',
                    sponsorship.identity_number,
                    sponsorship.orphan_name
                );
                
                // تحديث sponsorship
                sponsorship.relation_id_number = fileId;
            }
            
            // المزامنة
            await this.syncPersonData(personData, sponsorship);
            
            // تحديث حالة التتبع
            await SyncProgressTracker.updateProgress(progressId, {
                status: 'success',
                progressPercentage: 100
            });
            
        } catch (error) {
            // تسجيل الفشل
            await SyncProgressTracker.markAsFailed(
                progressId,
                error.message
            );
        }
    }
    
    // إنهاء الجلسة
    await SyncProgressTracker.endSyncSession();
}
```

### 3. PWA Deployment

```bash
# نسخ ملفات PWA
cp pwa/sync-monitor.html /var/www/html/pwa/
cp pwa/css/sync-monitor.css /var/www/html/pwa/css/
cp pwa/js/sync-monitor.js /var/www/html/pwa/js/

# تحديث الصفحات الموجودة
# إضافة رابط في navigation
```

---

## 🧪 اختبار النظام

### Test 1: توليد رقم ملف جديد

```typescript
// في Capacitor app أو browser console

const fileId = await FileIDGeneratorService.ensureFileIDExists(
    'orphan',
    '987654321',  // رقم هوية
    'محمد أحمد علي'  // الاسم
);

console.log('Generated File ID:', fileId);
// Expected: O2026000001 (أو رقم تسلسلي آخر)
```

### Test 2: تتبع عملية المزامنة

```typescript
// بدء جلسة
const sessionId = await SyncProgressTracker.startSyncSession();

// إضافة عملية
const itemId = await SyncProgressTracker.addProgressItem(
    'data_upload',
    'sponsorship',
    'محمد أحمد',
    { entityId: '123' }
);

// تحديث التقدم
await SyncProgressTracker.updateProgress(itemId, {
    status: 'in_progress',
    progressPercentage: 50
});

// إنهاء بنجاح
await SyncProgressTracker.updateProgress(itemId, {
    status: 'success',
    progressPercentage: 100
});

// الإحصائيات
const stats = await SyncProgressTracker.getSessionStatistics();
console.log(stats);
```

### Test 3: صفحة المراقبة

1. افتح المتصفح: `http://localhost/pwa/sync-monitor.html`
2. اضغط "بدء المزامنة الكاملة"
3. راقب التحديثات الحية
4. اختبر الفلاتر والبحث

---

## 📊 API Endpoints Reference

### Check Person Exists

```bash
POST /api/sync/check-person-exists

Request:
{
    "person_type": "orphan",
    "identity_number": "987654321"
}

Response:
{
    "success": true,
    "exists": false,
    "file_id": null,
    "person_type": "orphan"
}
```

### Generate File ID

```bash
POST /api/sync/generate-file-id

Request:
{
    "person_type": "orphan",
    "identity_number": "987654321",
    "person_name": "محمد أحمد علي",
    "handshake_token": "abc123...",
    "device_id": "device_001"
}

Response:
{
    "success": true,
    "file_id": "O2026000001",
    "table_name": "re_people",
    "handshake_token": "abc123...",
    "created_at": "2026-01-10T10:30:00Z"
}
```

### Activate File ID

```bash
POST /api/sync/activate-file-id

Request:
{
    "file_id": "O2026000001",
    "handshake_token": "abc123..."
}

Response:
{
    "success": true,
    "message": "File ID activated successfully"
}
```

---

## 🎨 UI Screenshots

### صفحة متابعة المزامنة

```
┌───────────────────────────────────────────────┐
│  🔄 متابعة المزامنة                           │
│  [▶️ بدء المزامنة الكاملة] [🔃 تحديث]       │
├───────────────────────────────────────────────┤
│  📊 0    ⏳ 0    🔄 0    ✅ 0    ❌ 0    ⚠️ 0  │
│  إجمالي انتظار تنفيذ  نجح   فشل  تعارض      │
├───────────────────────────────────────────────┤
│  التقدم الإجمالي                      0%      │
│  [████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░]     │
├───────────────────────────────────────────────┤
│  📤 محمد أحمد علي                      [✅]   │
│  رفع البيانات - sponsorship                  │
│  ████████████████████████████ 100%            │
│  ⏱️ بدأت: منذ دقيقتين | ✅ انتهت: منذ دقيقة │
├───────────────────────────────────────────────┤
│  📷 صورة الهوية                        [🔄]   │
│  رفع الملفات - attachment                    │
│  ████████████████░░░░░░░░░░░ 65%             │
│  📦 الحجم: 3.25 / 5.00 MB                    │
└───────────────────────────────────────────────┘
```

---

## 🔧 Troubleshooting

### المشكلة: لا تظهر الإحصائيات

**الحل**:
```bash
# تحقق من قاعدة البيانات
php artisan tinker
>>> DB::table('sync_progress')->latest()->take(5)->get();

# تحقق من JavaScript console
# F12 في المتصفح → Console
```

### المشكلة: File ID غير فريد

**الحل**:
```sql
-- تحقق من الجدول
SELECT * FROM file_id_registry 
WHERE file_id = 'O2026000001';

-- إعادة تعيين التسلسل إن لزم
DELETE FROM file_id_registry WHERE status = 'reserved';
```

### المشكلة: صفحة المراقبة فارغة

**الحل**:
```javascript
// في sync-monitor.js
// تحقق من:
1. هل تم تحميل الملف بنجاح؟
2. هل هناك أخطاء في Console؟
3. هل البيانات موجودة في sync_progress؟
```

---

## 📞 الدعم الفني

إذا واجهت أي مشاكل:

1. **راجع الوثائق**:
   - FILE_ID_GENERATION_AND_SYNC_MONITORING.md
   - IMPLEMENTATION_SUMMARY.md
   - OFFLINE_ONLINE_SYNC_ARCHITECTURE.md

2. **تحقق من Logs**:
   ```bash
   # Laravel logs
   tail -f storage/logs/laravel.log
   
   # Browser console
   F12 → Console tab
   ```

3. **اختبر API مباشرة**:
   ```bash
   # باستخدام curl أو Postman
   curl -X POST http://localhost/api/sync/check-person-exists \
        -H "Content-Type: application/json" \
        -d '{"person_type":"orphan","identity_number":"123"}'
   ```

---

## ✅ Checklist قبل الإنتاج

- [ ] تشغيل جميع الـ migrations
- [ ] اختبار توليد رقم الملف
- [ ] اختبار تتبع التقدم
- [ ] نشر صفحة المراقبة
- [ ] تحديث روابط التنقل
- [ ] اختبار على الموبايل
- [ ] مراجعة الأمان (handshake tokens)
- [ ] اختبار السرعة والأداء
- [ ] تدريب المستخدمين

---

**Version**: 1.0  
**Last Updated**: January 10, 2026  
**Status**: Ready for Production ✅
