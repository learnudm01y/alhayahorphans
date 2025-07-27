<?php
/**
 * اختبار سريع للمسارات في sidebar
 */

echo "<h2>🔗 اختبار مسارات القائمة الجانبية</h2>";

// قائمة المسارات المطلوبة
$routes = [
    'admin.civil-registry.index' => 'الصفحة الرئيسية للسجل المدني',
    'admin.civil-registry.create' => 'إضافة مواطن جديد',
    'admin.index.civilian' => 'النظام القديم'
];

echo "<table border='1' style='width:100%; border-collapse:collapse;'>";
echo "<tr style='background:#f0f0f0;'><th>اسم المسار</th><th>الوصف</th><th>الحالة</th></tr>";

foreach ($routes as $route => $description) {
    try {
        if (function_exists('route')) {
            $url = route($route);
            $status = "✅ يعمل - $url";
            $color = "green";
        } else {
            $status = "⚠️ دالة route غير متوفرة";
            $color = "orange";
        }
    } catch (Exception $e) {
        $status = "❌ خطأ: " . $e->getMessage();
        $color = "red";
    }

    echo "<tr>";
    echo "<td>$route</td>";
    echo "<td>$description</td>";
    echo "<td style='color:$color;'>$status</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<p><strong>💡 ملاحظة:</strong> هذا الاختبار يتطلب تشغيل Laravel لتوليد المسارات الفعلية.</p>";
echo "<p><strong>🔗 للوصول للنظام:</strong> <a href='/admin/civil-registry'>انقر هنا</a></p>";
?>
