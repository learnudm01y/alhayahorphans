<?php
/**
 * ملف الحماية الرئيسي لجميع مجلدات storage
 * Main protection file for all storage directories
 * 
 * هذا الملف يحمي جميع المجلدات والملفات في storage من الوصول المباشر
 * This file protects all directories and files in storage from direct access
 */

// منع الوصول المباشر نهائياً
http_response_code(403);
header('HTTP/1.1 403 Forbidden');
header('Content-Type: text/html; charset=UTF-8');

// تسجيل محاولة الوصول المشبوهة مع تفاصيل كاملة
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'none',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'none',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown'
];

// تكوين رسالة اللوج
$logMessage = sprintf(
    '[%s] STORAGE ACCESS VIOLATION - IP: %s | UA: %s | URI: %s | Method: %s | Referer: %s | Host: %s | Query: %s | Path: %s | Script: %s',
    $logData['timestamp'],
    $logData['ip'],
    $logData['user_agent'],
    $logData['request_uri'],
    $logData['request_method'],
    $logData['referer'],
    $logData['host'],
    $logData['query_string'],
    $logData['path_info'],
    $logData['script_name']
);

// كتابة اللوج
error_log($logMessage, 3, storage_path('logs/storage_access_violations.log'));

// تسجيل في لوج Laravel إذا كان متوفراً
if (function_exists('storage_path') && file_exists(storage_path('logs'))) {
    file_put_contents(
        storage_path('logs/storage_security.log'),
        $logMessage . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// تحليل نوع الهجوم المحتمل
$threatAnalysis = [];
if (strpos($logData['request_uri'], '..') !== false) {
    $threatAnalysis[] = 'PATH_TRAVERSAL_ATTEMPT';
}
if (strpos($logData['request_uri'], 'php') !== false) {
    $threatAnalysis[] = 'PHP_FILE_ACCESS_ATTEMPT';
}
if (strpos($logData['request_uri'], '.env') !== false) {
    $threatAnalysis[] = 'ENV_FILE_ACCESS_ATTEMPT';
}
if (strpos($logData['request_uri'], 'config') !== false) {
    $threatAnalysis[] = 'CONFIG_FILE_ACCESS_ATTEMPT';
}
if (preg_match('/\.(sql|db|backup|bak)$/i', $logData['request_uri'])) {
    $threatAnalysis[] = 'DATABASE_FILE_ACCESS_ATTEMPT';
}

// تسجيل تحليل التهديد
if (!empty($threatAnalysis)) {
    $threatLog = sprintf(
        '[%s] THREAT ANALYSIS - IP: %s | Threats: %s | URI: %s',
        $logData['timestamp'],
        $logData['ip'],
        implode(', ', $threatAnalysis),
        $logData['request_uri']
    );
    error_log($threatLog, 3, storage_path('logs/security_threats.log'));
}

// إنهاء التنفيذ مع رسالة خطأ شاملة
exit('<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وصول مرفوض - Access Denied</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
            backdrop-filter: blur(10px);
        }
        .error-icon {
            font-size: 80px;
            color: #e74c3c;
            margin-bottom: 30px;
            display: block;
        }
        .error-title {
            font-size: 32px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .error-message {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .error-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #e74c3c;
        }
        .error-code {
            font-family: "Courier New", monospace;
            font-size: 14px;
            color: #666;
            margin-top: 20px;
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            color: #856404;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <span class="error-icon">🛡️</span>
        <h1 class="error-title">وصول مرفوض</h1>
        <p class="error-message">
            تم منع وصولك إلى ملفات التخزين الخاصة بالنظام.<br>
            هذه المنطقة محمية وغير مسموح بالوصول إليها مباشرة.
        </p>
        
        <div class="error-details">
            <strong>معلومات الخطأ:</strong><br>
            • رمز الخطأ: 403 Forbidden<br>
            • السبب: محاولة وصول غير مصرح بها<br>
            • الوقت: ' . date('Y-m-d H:i:s') . '<br>
            • المسار: محمي بواسطة نظام الأمان
        </div>
        
        <div class="security-notice">
            <strong>تنبيه أمني:</strong> تم تسجيل هذه المحاولة في سجلات الأمان للمراجعة.
        </div>
        
        <p class="error-code">
            Storage Security System © ' . date('Y') . '<br>
            Request ID: ' . uniqid() . '
        </p>
    </div>
</body>
</html>');
?>
