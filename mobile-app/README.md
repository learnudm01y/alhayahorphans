# AlHayah Orphans Mobile App
## تطبيق الحياة لتنمية الأسرة - الأيتام

تطبيق Capacitor للهاتف المحمول لإدارة بيانات الأيتام مع دعم المزامنة الذكية والرفع المباشر لـ Google Drive.

## 🌟 الميزات الرئيسية

### 1. المزامنة الذكية
- التحقق من وجود الشخص قبل الإضافة (محلي + سيرفر)
- توليد أرقام ملفات فريدة (GYYYYXXXXXX)
- نظام Handshake للتأكد من الحفظ
- متابعة تقدم المزامنة في الوقت الفعلي

### 2. الرفع المباشر لـ Google Drive
- الرفع عبر Rclone (لا مزامنة للمرفقات)
- كشف الملفات المكررة بـ SHA256 Hash
- تتبع حالة الرفع
- إشعار السيرفر بعد الرفع الناجح

### 3. العمل بدون اتصال
- قاعدة بيانات SQLCipher مشفرة
- حفظ البيانات محلياً أولاً
- المزامنة التلقائية عند عودة الاتصال

## 📁 هيكل المشروع

```
mobile-app/
├── src/
│   ├── services/
│   │   ├── ConfigService.ts       # إعدادات التطبيق
│   │   ├── DatabaseService.ts     # قاعدة البيانات SQLCipher
│   │   ├── ApiService.ts          # اتصال API
│   │   ├── FileHashService.ts     # حساب SHA256
│   │   ├── GoogleDriveUploadsTracker.ts  # تتبع الرفع
│   │   ├── RcloneService.ts       # خدمة Rclone
│   │   ├── SyncProgressTracker.ts # تتبع تقدم المزامنة
│   │   ├── PersonSyncService.ts   # مزامنة الأشخاص
│   │   └── index.ts               # تصدير مركزي
│   └── main.ts                    # نقطة الدخول
├── index.html                     # الصفحة الرئيسية
├── sync-monitor.html              # صفحة مراقبة المزامنة
├── capacitor.config.json          # إعدادات Capacitor
├── package.json
├── tsconfig.json
└── vite.config.ts
```

## 🚀 التثبيت

### المتطلبات
- Node.js 18+
- Android Studio (لتطبيق Android)
- Rclone (للرفع إلى Google Drive)

### الخطوات

```bash
# 1. الانتقال لمجلد التطبيق
cd mobile-app

# 2. تثبيت المكتبات
npm install

# 3. بناء التطبيق
npm run build

# 4. إضافة منصة Android
npx cap add android

# 5. مزامنة الملفات
npx cap sync

# 6. فتح Android Studio
npx cap open android
```

## ⚙️ الإعداد

### إعداد API
عدّل ملف `src/services/ConfigService.ts`:

```typescript
const defaultConfig: AppConfig = {
  apiBaseUrl: 'https://your-server.com/api',  // عنوان السيرفر
  // ...
};
```

### إعداد Rclone
1. ثبّت Rclone على الجهاز
2. قم بإعداد remote لـ Google Drive:
   ```bash
   rclone config
   ```
3. عدّل الإعدادات في `ConfigService.ts`:
   ```typescript
   rcloneRemote: 'gdrive',
   googleDriveFolderId: 'YOUR_FOLDER_ID',
   ```

### إعداد قاعدة البيانات
تستخدم قاعدة بيانات SQLCipher مشفرة. غيّر مفتاح التشفير:

```typescript
dbEncryptionKey: 'YOUR_SECRET_ENCRYPTION_KEY',
```

## 📱 API Endpoints

التطبيق يتصل مع Laravel عبر هذه الـ APIs:

### مزامنة الأشخاص
- `POST /api/sync/check-person-all-tables` - التحقق من وجود الشخص
- `POST /api/sync/generate-file-id` - توليد رقم ملف جديد
- `POST /api/sync/activate-file-id` - تفعيل رقم الملف
- `POST /api/sync/new-person-entry` - إنشاء شخص جديد

### تتبع الرفع
- `POST /api/uploads/check-duplicate` - التحقق من الملفات المكررة
- `POST /api/uploads/notify-completed` - إشعار الرفع الناجح
- `GET /api/uploads/stats` - إحصائيات الرفع

## 🔄 سير العمل

### إضافة شخص جديد
```
1. المستخدم يدخل رقم الهوية
2. التحقق محلياً → التحقق من السيرفر
3. إذا غير موجود:
   - طلب رقم ملف جديد (Handshake)
   - حفظ محلياً
   - إشعار السيرفر
   - تفعيل رقم الملف
```

### رفع ملف
```
1. المستخدم يختار/يلتقط صورة
2. حساب SHA256 Hash
3. التحقق من التكرار (محلي + سيرفر)
4. الرفع عبر Rclone
5. إشعار السيرفر بـ Google Drive File ID
```

## 🛠️ التطوير

```bash
# تشغيل في وضع التطوير
npm run dev

# بناء للإنتاج
npm run build

# مزامنة مع Android
npm run sync

# تشغيل على جهاز Android
npm run android:run
```

## 📊 مراقبة التقدم

صفحة `sync-monitor.html` توفر:
- حالة الاتصال (متصل/غير متصل)
- شريط تقدم المزامنة
- قائمة المهام الحالية
- إحصائيات (معلق، جاري، مكتمل، فاشل)
- أزرار المزامنة وإعادة المحاولة

## 🔐 الأمان

- قاعدة بيانات مشفرة بـ SQLCipher
- الاتصال عبر HTTPS
- مصادقة Bearer Token
- تشفير الملفات أثناء النقل

## 📝 ملاحظات

- **المرفقات لا تُزامَن** - تُرفع مباشرة لـ Google Drive
- جدول `google_drive_uploads` يمنع التكرار
- العمل بدون اتصال ممكن (الحفظ المحلي)
- المزامنة التلقائية عند عودة الاتصال

## 📄 الترخيص

MIT License - AlHayah Foundation
