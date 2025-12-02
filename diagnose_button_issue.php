<?php
// ملف تشخيص شامل لمشكلة الزر

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     🔍 تشخيص مشكلة زر 'إضافة جمعية جديدة'              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$issues = [];
$warnings = [];
$success = [];

// 1. فحص وجود الملفات
echo "1️⃣  فحص الملفات الأساسية:\n";
echo str_repeat('─', 60) . "\n";

$files = [
    'Controller' => 'app/Http/Controllers/Admin/SponsorController.php',
    'Model' => 'app/Models/Sponsor.php',
    'DataTable' => 'app/DataTables/SponsorDataTable.php',
    'View' => 'resources/views/admin/dashboard/sponsors/index.blade.php',
    'Layout' => 'resources/views/admin/dashboard/toolbars/index.blade.php',
];

foreach ($files as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        echo "  ✅ {$name}: موجود\n";
        $success[] = "{$name} موجود";
    } else {
        echo "  ❌ {$name}: مفقود!\n";
        $issues[] = "{$name} مفقود";
    }
}

echo "\n2️⃣  فحص محتوى View:\n";
echo str_repeat('─', 60) . "\n";

$viewPath = __DIR__ . '/resources/views/admin/dashboard/sponsors/index.blade.php';
if (file_exists($viewPath)) {
    $content = file_get_contents($viewPath);

    // فحص العناصر المهمة
    $checks = [
        'addSponsorBtn' => 'id="addSponsorBtn"',
        'sponsorModal' => 'id="sponsorModal"',
        'Bootstrap Modal' => 'new bootstrap.Modal',
        'jQuery Click' => '$(\'#addSponsorBtn\').click',
        'Console Log' => 'console.log',
        'VERSION 2.0' => 'VERSION 2.0',
    ];

    foreach ($checks as $name => $search) {
        if (strpos($content, $search) !== false) {
            echo "  ✅ {$name}: موجود\n";
        } else {
            echo "  ❌ {$name}: مفقود!\n";
            $issues[] = "{$name} غير موجود في View";
        }
    }

    // فحص الأخطاء الشائعة
    if (preg_match('/\}\s*;\s*\$/', $content)) {
        echo "  ⚠️  تحذير: قد يوجد كود jQuery مكسور\n";
        $warnings[] = "كود jQuery قد يكون مكسور";
    }
}

echo "\n3️⃣  فحص Routes:\n";
echo str_repeat('─', 60) . "\n";

$routes = Illuminate\Support\Facades\Route::getRoutes();
$requiredRoutes = [
    'admin.sponsors.index' => 'GET',
    'admin.sponsors.store' => 'POST',
];

foreach ($requiredRoutes as $name => $method) {
    $found = false;
    foreach ($routes as $route) {
        if ($route->getName() === $name) {
            echo "  ✅ {$name} ({$method}): موجود\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "  ❌ {$name}: مفقود!\n";
        $issues[] = "Route {$name} مفقود";
    }
}

echo "\n4️⃣  فحص قاعدة البيانات:\n";
echo str_repeat('─', 60) . "\n";

try {
    $count = App\Models\Sponsor::count();
    echo "  ✅ الاتصال بقاعدة البيانات: ناجح\n";
    echo "  📊 عدد الجمعيات: {$count}\n";
    $success[] = "قاعدة البيانات تعمل";
} catch (\Exception $e) {
    echo "  ❌ خطأ في قاعدة البيانات: {$e->getMessage()}\n";
    $issues[] = "خطأ في قاعدة البيانات";
}

echo "\n5️⃣  فحص Assets:\n";
echo str_repeat('─', 60) . "\n";

$assets = [
    'Bootstrap CSS' => 'public/admin/assets/plugins/global/plugins.bundle.rtl.css',
    'Bootstrap JS' => 'public/admin/assets/plugins/global/plugins.bundle.js',
    'Scripts' => 'public/admin/assets/js/scripts.bundle.js',
];

foreach ($assets as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        echo "  ✅ {$name}: موجود (" . number_format($size/1024, 1) . " KB)\n";
    } else {
        echo "  ⚠️  {$name}: مفقود (قد يكون في CDN)\n";
        $warnings[] = "{$name} غير موجود محلياً";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                     📊 ملخص التشخيص                     ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "✅ نجاحات: " . count($success) . "\n";
if (!empty($warnings)) {
    echo "⚠️  تحذيرات: " . count($warnings) . "\n";
}
if (!empty($issues)) {
    echo "❌ مشاكل: " . count($issues) . "\n";
    foreach ($issues as $issue) {
        echo "   • {$issue}\n";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║              🔧 الحلول المقترحة                         ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

if (empty($issues)) {
    echo "✅ لا توجد مشاكل تقنية!\n\n";
    echo "المشكلة على الأرجح في:\n";
    echo "  1️⃣  Cache المتصفح - امسحه بـ Ctrl+Shift+Delete\n";
    echo "  2️⃣  الصفحة لم يتم تحديثها - اضغط Ctrl+F5\n";
    echo "  3️⃣  JavaScript Error صامت - افتح Console (F12)\n";
    echo "  4️⃣  مكتبات لم يتم تحميلها - تحقق من Network tab\n\n";

    echo "خطوات التحقق:\n";
    echo "  • افتح: http://127.0.0.1:8000/test-modal.html\n";
    echo "  • إذا عمل المودال هناك، فالمشكلة في الصفحة الأصلية\n";
    echo "  • إذا لم يعمل، فالمشكلة في Bootstrap نفسه\n\n";
} else {
    echo "يجب إصلاح المشاكل أعلاه أولاً!\n\n";
}

echo "════════════════════════════════════════════════════════════\n\n";
