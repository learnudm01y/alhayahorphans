<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

echo "=== فحص هيكل جدول attachments ===" . PHP_EOL;

try {
    // فحص الأعمدة الموجودة
    $columns = DB::select('DESCRIBE attachments');

    echo "الأعمدة الموجودة:" . PHP_EOL;
    foreach ($columns as $column) {
        echo "- " . $column->Field . " (" . $column->Type . ")" . PHP_EOL;
    }

    // فحص إذا كان عمود folder_id موجود
    $hasFolderId = false;
    foreach ($columns as $column) {
        if ($column->Field === 'folder_id') {
            $hasFolderId = true;
            break;
        }
    }

    echo PHP_EOL . "عمود folder_id موجود: " . ($hasFolderId ? 'نعم' : 'لا') . PHP_EOL;

    // فحص migrations
    echo PHP_EOL . "=== فحص migrations ===" . PHP_EOL;
    $migrations = DB::table('migrations')->where('migration', 'like', '%attachments%')->get();

    if ($migrations->count() > 0) {
        echo "Migrations الموجودة للجدول:" . PHP_EOL;
        foreach ($migrations as $migration) {
            echo "- " . $migration->migration . PHP_EOL;
        }
    } else {
        echo "لا توجد migrations للجدول" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . PHP_EOL;

    // محاولة فحص إذا كان الجدول موجود أصلاً
    try {
        $tables = DB::select('SHOW TABLES');
        echo PHP_EOL . "الجداول الموجودة:" . PHP_EOL;
        foreach ($tables as $table) {
            $tableName = reset($table);
            echo "- " . $tableName . PHP_EOL;
        }
    } catch (Exception $e2) {
        echo "خطأ في فحص الجداول: " . $e2->getMessage() . PHP_EOL;
    }
}
