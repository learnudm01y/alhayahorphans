<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Data;

try {
    echo "🔍 فحص هيكل بيانات الجدول الرئيسي\n";
    echo "=" . str_repeat("=", 50) . "\n";

    // البحث عن سجل يحتوي على Kyle
    $records = Data::where(function($query) {
        $query->where('data_first_name', 'LIKE', '%Kyle%')
              ->orWhere('data_father_name', 'LIKE', '%Kyle%')
              ->orWhere('data_grand_father_name', 'LIKE', '%Kyle%')
              ->orWhere('data_family_name', 'LIKE', '%Kyle%');
    })->limit(3)->get();

    echo "📊 عدد السجلات الموجودة: " . $records->count() . "\n\n";

    foreach ($records as $index => $record) {
        echo "📝 السجل رقم " . ($index + 1) . ":\n";
        echo "   🆔 ID: " . $record->id . "\n";
        echo "   📋 الأعمدة والقيم:\n";

        // فحص جميع الأعمدة المحتملة للأسماء
        $nameColumns = [
            'first_name', 'father_name', 'grand_father_name', 'family_name',
            'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'
        ];

        foreach ($nameColumns as $column) {
            if (isset($record->$column)) {
                echo "      $column: '" . $record->$column . "'\n";
            }
        }

        echo "   📄 جميع الأعمدة:\n";
        foreach ($record->getAttributes() as $key => $value) {
            if (is_string($value) && strlen($value) > 0) {
                echo "      $key: '" . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "'\n";
            }
        }
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }

    echo "🎉 انتهى الفحص!\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
}
