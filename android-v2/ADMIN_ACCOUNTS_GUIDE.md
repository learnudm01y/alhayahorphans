# 🔐 دليل حسابات المديرين - Admin Accounts Guide

## ✅ النظام الحالي

التطبيق **يعمل بالفعل مع أي مستخدم لديه صلاحية `admin`** وليس محصوراً بحساب واحد فقط!

### كيف يعمل النظام؟

عند تسجيل الدخول في `/api/mobile/login`:

```php
// الكود يتحقق من صلاحية admin في السطر 66
if ($user->role !== 'admin') {
    return response()->json([
        'success' => false,
        'message' => 'ليس لديك صلاحية الدخول. مطلوب صلاحية مدير.',
        'error_code' => 'INSUFFICIENT_PERMISSIONS'
    ], 403);
}
```

**أي مستخدم في جدول `users` لديه `role = 'admin'` يمكنه تسجيل الدخول!**

---

## 📋 الحسابات الحالية

### الحساب الافتراضي (موجود حالياً):
- **اسم المستخدم:** `admin@gmail.com`
- **كلمة المرور:** `password`
- **الصلاحية:** `admin`

---

## ➕ إضافة مستخدمين جدد بصلاحية Admin

### طريقة 1: عبر Laravel Tinker (الأسهل)

```bash
cd "I:\unit test\alhayahorphans\ASO - Copy"
php artisan tinker
```

ثم:

```php
// إنشاء مستخدم جديد
$user = new App\Models\User();
$user->name = 'أحمد محمد';
$user->email = 'ahmed@example.com';
$user->password = Hash::make('password123'); // كلمة المرور المشفرة
$user->role = 'admin'; // صلاحية مدير
$user->save();

echo "✅ تم إنشاء المستخدم بنجاح!\n";
echo "اسم المستخدم: {$user->email}\n";
echo "الصلاحية: {$user->role}\n";
```

### طريقة 2: عبر SQL مباشرة

```sql
INSERT INTO users (name, email, password, role, created_at, updated_at)
VALUES (
    'محمد علي',
    'mohamed@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password123
    'admin',
    NOW(),
    NOW()
);
```

**ملاحظة:** كلمة المرور المشفرة في المثال أعلاه = `password123`

### طريقة 3: عبر Laravel Migration

إنشاء ملف migration:

```bash
php artisan make:migration add_admin_users
```

في ملف Migration:

```php
public function up()
{
    DB::table('users')->insert([
        [
            'name' => 'فاطمة أحمد',
            'email' => 'fatima@example.com',
            'password' => Hash::make('mypassword'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'خالد محمود',
            'email' => 'khaled@example.com',
            'password' => Hash::make('anotherpass'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
        ]
    ]);
}
```

ثم:

```bash
php artisan migrate
```

---

## 🔄 تغيير صلاحية مستخدم موجود

إذا كان لديك مستخدم بصلاحية `user` وتريد جعله `admin`:

```bash
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'user@example.com')->first();
$user->role = 'admin';
$user->save();

echo "✅ تم تحديث الصلاحية إلى admin\n";
```

---

## ✅ اختبار المستخدم الجديد

1. **افتح التطبيق** على الجهاز Android
2. **سجل الخروج** من الحساب الحالي (إن وجد)
3. **أدخل بيانات المستخدم الجديد:**
   - اسم المستخدم: `ahmed@example.com` (أو الاسم أو الهاتف)
   - كلمة المرور: `password123`
4. **اضغط تسجيل الدخول**

---

## 📱 التسجيل باستخدام الاسم أو الهاتف

الـ endpoint يقبل ثلاثة خيارات:

```php
$user = User::where('name', $request->username)
    ->orWhere('email', $request->username)
    ->orWhere('phone', $request->username)
    ->first();
```

**يعني يمكنك تسجيل الدخول بـ:**
- البريد الإلكتروني: `ahmed@example.com`
- الاسم: `أحمد محمد`
- رقم الهاتف: `0599123456` (إن كان محفوظاً في حقل `phone`)

---

## 🔒 الصلاحيات المتاحة

حسب الكود، الصلاحيات في جدول `users`:

| الصلاحية | الوصول للتطبيق Mobile |
|---------|---------------------|
| `admin` | ✅ نعم              |
| `user`  | ❌ لا               |
| أخرى    | ❌ لا               |

---

## ⚠️ نصائح أمان

1. **لا تستخدم كلمات مرور ضعيفة** مثل `password` في الإنتاج
2. **غيّر كلمة مرور الحساب الافتراضي** `admin@gmail.com`
3. **احذف المستخدمين غير المستخدمين** بصلاحية admin
4. **استخدم كلمات مرور قوية** (8+ أحرف، أرقام ورموز)

---

## 🛠️ استكشاف الأخطاء

### "ليس لديك صلاحية الدخول"

**السبب:** المستخدم لديه `role != 'admin'`

**الحل:**
```bash
php artisan tinker
```
```php
$user = App\Models\User::where('email', 'البريد')->first();
echo $user->role; // تحقق من الصلاحية الحالية
$user->role = 'admin'; // تحديث الصلاحية
$user->save();
```

### "اسم المستخدم غير موجود"

**السبب:** المستخدم غير موجود في جدول `users`

**الحل:** أنشئ المستخدم باستخدام الطرق أعلاه

### "كلمة المرور غير صحيحة"

**السبب:** كلمة المرور خاطئة

**الحل:**
```bash
php artisan tinker
```
```php
$user = App\Models\User::where('email', 'البريد')->first();
$user->password = Hash::make('كلمة_المرور_الجديدة');
$user->save();
```

---

## 📊 عرض جميع المديرين

```bash
php artisan tinker
```

```php
$admins = App\Models\User::where('role', 'admin')->get();

foreach ($admins as $admin) {
    echo "👤 {$admin->name}\n";
    echo "   📧 {$admin->email}\n";
    echo "   🆔 ID: {$admin->id}\n";
    echo "   ---\n";
}

echo "📊 عدد المديرين: " . $admins->count() . "\n";
```

---

## ✅ الخلاصة

**التطبيق يدعم بالفعل تسجيل دخول أي مستخدم admin!**

- ✅ الكود صحيح ويعمل
- ✅ يتحقق من `role = 'admin'`
- ✅ يقبل email/name/phone
- ✅ لا يوجد hard-coded credentials في الكود المستخدم

**إذا كان المستخدم لا يستطيع الدخول:**
1. تأكد أن الصلاحية `role = 'admin'` في قاعدة البيانات
2. تأكد من كتابة كلمة المرور الصحيحة
3. تأكد من الاتصال بالسيرفر (خطأ الاتصال ≠ رفض الصلاحيات)
