<?php

/**
 * إعدادات PHP للملفات الكبيرة - يتم تحميلها تلقائياً
 * هذا الملف يضمن تطبيق إعدادات الملفات الكبيرة في كل طلب
 */

// تطبيق إعدادات PHP برمجياً
if (function_exists('ini_set')) {
    @ini_set('upload_max_filesize', '1024M');
    @ini_set('post_max_size', '1024M');
    @ini_set('memory_limit', '2048M');
    @ini_set('max_execution_time', 3600);
    @ini_set('max_input_time', 3600);
    @ini_set('max_file_uploads', 100);
    @ini_set('file_uploads', 'On');
    @ini_set('max_input_vars', 10000);
    @ini_set('default_socket_timeout', 3600);
}

// تعيين حد الوقت
if (function_exists('set_time_limit')) {
    @set_time_limit(3600);
}

return [];
