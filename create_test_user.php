<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    إنشاء مستخدم للاختبار                                      ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

$sponsoredId = '42979087';

// جلب الكفالة
$sponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();

if (!$sponsorship) {
    echo "❌ لا يوجد سجل كفالة\n";
    exit(1);
}

echo "📋 الكفالة: ID = {$sponsorship->id}\n";
echo "   - المكفول: {$sponsorship->orphan_name} ({$sponsorship->identity_number})\n";
echo "   - المعيل: {$sponsorship->guardian_name}\n\n";

// البحث عن مستخدم موجود برقم الهوية كإيميل
// النظام يبحث عن الكفالة بناءً على user.email = sponsorship.identity_number
$existingUser = DB::table('users')->where('email', $sponsoredId)->first();

if ($existingUser) {
    echo "✓ يوجد مستخدم بالإيميل {$sponsoredId}:\n";
    echo "   - ID: {$existingUser->id}\n";
    echo "   - Email: {$existingUser->email}\n";
    echo "   - Name: {$existingUser->name}\n";

    // تحديث كلمة المرور
    DB::table('users')->where('id', $existingUser->id)->update([
        'password' => Hash::make('password123'),
        'role' => 'user',
        'updated_at' => now()
    ]);
    echo "\n✅ تم تحديث كلمة المرور!\n";

    echo "\n📌 يمكنك تسجيل الدخول باستخدام:\n";
    echo "   Email: {$existingUser->email}\n";
    echo "   Password: password123\n";
} else {
    echo "⚠️ لا يوجد مستخدم بالإيميل {$sponsoredId}. سيتم إنشاء واحد...\n\n";

    // إنشاء مستخدم جديد - الإيميل هو رقم الهوية
    $userId = DB::table('users')->insertGetId([
        'name' => $sponsorship->orphan_name,
        'email' => $sponsoredId, // رقم الهوية كإيميل
        'password' => Hash::make('password123'),
        'role' => 'user',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✅ تم إنشاء المستخدم بنجاح!\n\n";
    echo "📌 بيانات تسجيل الدخول:\n";
    echo "   Email: {$sponsoredId}\n";
    echo "   Password: password123\n";
    echo "\n   User ID: {$userId}\n";
}

echo "\n🌐 رابط الصفحة: http://127.0.0.1:8000/user/general-registration\n";
