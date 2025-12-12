# دليل إعداد Google Drive API

## المشكلة الحالية:
Service Account لا يمكنه الرفع إلى المجلدات العادية في Google Drive حتى لو تم مشاركتها معه.

## الحلول المتاحة:

### الحل 1: استخدام Shared Drive (Google Workspace فقط)
**متطلبات:**
- يتطلب حساب Google Workspace (ليس Gmail مجاني)
- تكلفة: $6-$18 شهرياً

**الخطوات:**
1. إنشاء Shared Drive من Google Workspace Admin
2. إضافة Service Account كعضو في Shared Drive
3. استخدام Shared Drive ID في التطبيق

### الحل 2: استخدام OAuth 2.0 مع User Account (موصى به) ✅
**مميزات:**
- مجاني تماماً
- يعمل مع Gmail العادي
- لا يحتاج Google Workspace

**الخطوات:**
1. الذهاب إلى [Google Cloud Console](https://console.cloud.google.com)
2. اختيار المشروع: `alhayahorphans`
3. الذهاب إلى: APIs & Services > Credentials
4. إنشاء OAuth 2.0 Client ID:
   - Application type: **Web application**
   - Name: **AlHayah Orphans System**
   - Authorized redirect URIs:
     ```
     http://localhost:8000/google/callback
     http://127.0.0.1:8000/google/callback
     http://your-domain.com/google/callback
     ```
5. تحميل JSON credentials

### الحل 3: استخدام Google Drive API مع Personal Access (أسهل) ✅✅
**الأسهل والأسرع للتطوير:**
- استخدام Laravel Socialite
- المستخدم يسجل دخول بحسابه
- التطبيق يرفع الملفات باسم المستخدم

---

## الحل الموصى به حالياً:

### استخدام حساب Google شخصي مع OAuth 2.0

سأقوم الآن بتعديل النظام ليستخدم OAuth 2.0 بدلاً من Service Account.

**ما ستحتاج إليه:**
1. OAuth 2.0 Client ID & Secret من Google Cloud Console
2. إضافتهم في ملف `.env`

**المميزات:**
- ✅ مجاني تماماً
- ✅ يعمل مع Gmail العادي
- ✅ يمكن رفع الملفات لأي مجلد
- ✅ تحكم كامل في الصلاحيات

---

## ملاحظات مهمة:

### Service Account:
- ❌ لا يمكنه الرفع للمجلدات العادية
- ❌ يحتاج Shared Drive (مدفوع)
- ✅ جيد للتطبيقات Server-to-Server

### OAuth 2.0:
- ✅ يمكنه الرفع لأي مجلد
- ✅ مجاني تماماً
- ✅ يعمل مع Gmail العادي
- ⚠️ يحتاج تسجيل دخول من المستخدم (مرة واحدة فقط)

---

هل تريد أن أقوم بتحويل النظام إلى OAuth 2.0؟
