<?php
/**
 * ملف حماية لمنع الوصول المباشر لمجلد التحميلات
 * Protection file to prevent direct access to uploads directory
 */

// منع الوصول المباشر نهائياً
http_response_code(403);
header('HTTP/1.1 403 Forbidden');
header('Content-Type: text/html; charset=UTF-8');

// تسجيل محاولة الوصول المشبوهة
error_log(sprintf(
    '[%s] محاولة وصول غير مصرح بها للمجلد الآمن - IP: %s - User Agent: %s - Request: %s',
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    $_SERVER['REQUEST_URI'] ?? 'unknown'
));

// إنهاء التنفيذ مع رسالة خطأ
exit('<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>وصول مرفوض</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; background: #f8f9fa; }
        .error { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
        .message { color: #6c757d; font-size: 16px; }
    </style>
</head>
<body>
    <div class="error">🚫 وصول مرفوض</div>
    <div class="message">غير مصرح لك بالوصول إلى هذا المجلد</div>
</body>
</html>');
