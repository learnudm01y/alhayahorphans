# 📚 Sync Documentation Index
## AlHayah Orphans System - Synchronization & File Upload

---

## 📂 Documentation Files

| ملف | الوصف |
|-----|-------|
| [OFFLINE_ONLINE_SYNC_ARCHITECTURE.md](OFFLINE_ONLINE_SYNC_ARCHITECTURE.md) | المعمارية الشاملة للمزامنة |
| [SMART_SYNC_WORKFLOW.md](SMART_SYNC_WORKFLOW.md) | سير العمل الذكي للمزامنة |
| [FILE_ID_GENERATION_AND_SYNC_MONITORING.md](FILE_ID_GENERATION_AND_SYNC_MONITORING.md) | توليد أرقام الملفات ومتابعة التقدم |
| [GOOGLE_DRIVE_DIRECT_UPLOAD.md](GOOGLE_DRIVE_DIRECT_UPLOAD.md) | نظام رفع الملفات المباشر |

---

## 🚫 ملاحظة هامة: لا يوجد مزامنة للمرفقات

**المرفقات (الصور والفيديوهات) لن يتم مزامنتها!**

بدلاً من ذلك، سيتم رفعها **مباشرة** إلى Google Drive من خلال:
- **Capacitor** (تطبيق الموبايل)
- **Laravel** (الخادم الخلفي)
- **Rclone** (أداة رفع الملفات)

### لماذا لا يوجد مزامنة للمرفقات؟

1. **حجم الملفات**: الصور والفيديوهات كبيرة الحجم
2. **التعقيد**: المزامنة ثنائية الاتجاه معقدة للملفات الثنائية
3. **الكفاءة**: الرفع المباشر أسرع وأكثر موثوقية
4. **منع التكرار**: جدول `google_drive_uploads` يتتبع الملفات المرفوعة

---

## 📊 جدول تتبع رفع الملفات

```sql
google_drive_uploads
├── id
├── local_file_path          -- مسار الملف المحلي على الجهاز
├── local_file_hash          -- SHA256 hash للملف
├── google_drive_file_id     -- معرف الملف على Google Drive
├── google_drive_path        -- المسار على Google Drive
├── file_name
├── file_size_bytes
├── mime_type
├── upload_status            -- pending | uploading | completed | failed
├── upload_progress          -- 0-100%
├── entity_type              -- sponsorship | orphan | guardian | deceased
├── entity_id                -- معرف السجل المرتبط
├── attachment_type          -- personal_photo | birth_certificate | etc.
├── device_id                -- معرف الجهاز
├── uploaded_by              -- المستخدم
├── retry_count
├── error_message
├── created_at
├── uploaded_at
├── synced_to_server         -- هل تم إخطار Laravel؟
└── server_attachment_id     -- معرف السجل في جدول attachments
```

---

## 🔄 سير عمل رفع الملفات

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  Capacitor App  │         │  Google Drive    │         │  Laravel API    │
└────────┬────────┘         └────────┬─────────┘         └────────┬────────┘
         │                           │                            │
         │ 1. التقاط صورة/فيديو     │                            │
         │                           │                            │
         │ 2. حساب hash              │                            │
         │                           │                            │
         │ 3. تسجيل في              │                            │
         │    google_drive_uploads   │                            │
         │    (status=pending)       │                            │
         │                           │                            │
         │ 4. رفع الملف عبر Rclone  │                            │
         │──────────────────────────>│                            │
         │                           │                            │
         │<──────────────────────────│                            │
         │  google_drive_file_id     │                            │
         │                           │                            │
         │ 5. تحديث حالة الرفع       │                            │
         │    (status=completed)     │                            │
         │                           │                            │
         │ 6. إخطار Laravel          │                            │
         │─────────────────────────────────────────────────────>│
         │  {file_id, entity_type, entity_id, attachment_type}   │
         │                           │                            │
         │                           │   7. إنشاء سجل attachment │
         │                           │      وربطه بالكيان        │
         │                           │                            │
         │<─────────────────────────────────────────────────────│
         │  {success, attachment_id}  │                            │
         │                           │                            │
         │ 8. تحديث synced_to_server │                            │
         │    = true                  │                            │
```

---

## 📁 هيكل المجلدات على Google Drive

```
AlHayah_Orphans/
├── sponsorships/
│   ├── {relation_id}/
│   │   ├── personal_photos/
│   │   ├── birth_certificates/
│   │   ├── death_certificates/
│   │   └── other_documents/
│   └── ...
├── guardians/
│   ├── {file_id_number}/
│   │   ├── identity_cards/
│   │   └── documents/
│   └── ...
├── orphans/
│   ├── {registration_id}/
│   │   ├── photos/
│   │   └── certificates/
│   └── ...
└── deceased/
    ├── {re_file_id}/
    │   └── death_certificates/
    └── ...
```

---

## 🔑 منع التكرار

### استخدام Hash للتحقق

```typescript
// قبل رفع أي ملف
async function checkDuplicateUpload(fileHash: string): Promise<boolean> {
    const existing = await db.query(`
        SELECT id FROM google_drive_uploads 
        WHERE local_file_hash = ? 
        AND upload_status = 'completed'
    `, [fileHash]);
    
    return existing.length > 0;
}
```

### التحقق من الخادم

```php
// Laravel API
public function checkFileExists(Request $request) {
    $exists = GoogleDriveUpload::where('local_file_hash', $request->file_hash)
        ->where('upload_status', 'completed')
        ->exists();
    
    return response()->json(['exists' => $exists]);
}
```

---

**Document Version**: 1.0  
**Last Updated**: January 10, 2026  
**Author**: GitHub Copilot
