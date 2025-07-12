@echo off
echo ==============================================
echo  تحديث اعدادات PHP لدعم الملفات الكبيرة
echo ==============================================
echo.

echo جاري البحث عن ملف php.ini...
php --ini

echo.
echo الإعدادات الحالية:
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
php -r "echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"

echo.
echo تطبيق الإعدادات المحسنة برمجياً...
php -r "
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 3600);
echo 'تم تطبيق الإعدادات الجديدة:' . PHP_EOL;
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;
echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;
echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;
"

echo.
echo تم الانتهاء!
echo لفتح صفحة التشخيص: http://localhost/admin/unified-file-management/php-diagnostic
echo لفتح بوابة Excel: http://localhost/admin/unified-file-management/excel-gateway
echo.
pause
