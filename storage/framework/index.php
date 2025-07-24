<?php
/**
 * ملف حماية تلقائي - AUTO-GENERATED PROTECTION FILE
 */
http_response_code(403);
header("HTTP/1.1 403 Forbidden");
header("Content-Type: text/html; charset=UTF-8");

$logMessage = sprintf(
    "[%s] DIRECTORY ACCESS DENIED - IP: %s - UA: %s - URI: %s",
    date("Y-m-d H:i:s"),
    $_SERVER["REMOTE_ADDR"] ?? "unknown",
    $_SERVER["HTTP_USER_AGENT"] ?? "unknown",
    $_SERVER["REQUEST_URI"] ?? "unknown"
);

error_log($logMessage);

exit("<!DOCTYPE html><html dir='rtl' lang='ar'><head><meta charset='UTF-8'><title>وصول مرفوض</title><style>body{font-family:Arial,sans-serif;text-align:center;margin-top:100px;background:#f8f9fa;}.error{color:#dc3545;font-size:24px;margin-bottom:20px;}.message{color:#6c757d;font-size:16px;}</style></head><body><div class='error'>🚫 وصول مرفوض</div><div class='message'>غير مصرح لك بالوصول إلى هذا المجلد</div><div style='margin-top:20px;font-size:12px;color:#999;'>Request ID: " . uniqid() . "</div></body></html>");
?>