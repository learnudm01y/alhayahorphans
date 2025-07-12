<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LargeFileUploadMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // تسجيل حالة الإعدادات قبل التحديث
        Log::info('PHP settings before update', [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);

        // تعيين إعدادات PHP لدعم الملفات الكبيرة
        $this->setPhpSettings();

        // تسجيل حالة الإعدادات بعد التحديث
        Log::info('PHP settings after update', [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);

        return $next($request);
    }

    /**
     * تعيين إعدادات PHP برمجياً
     */
    private function setPhpSettings()
    {
        // محاولة تعيين الإعدادات باستخدام ini_set
        $settings = [
            'upload_max_filesize' => '1024M',
            'post_max_size' => '1024M',
            'max_execution_time' => '3600',
            'max_input_time' => '3600',
            'memory_limit' => '2048M',
            'max_file_uploads' => '100',
            'file_uploads' => '1',
            'max_input_vars' => '10000'
        ];

        foreach ($settings as $setting => $value) {
            if (function_exists('ini_set') && ini_get($setting) !== false) {
                @ini_set($setting, $value);
            }
        }

        // تعيين حد الوقت إضافياً
        if (function_exists('set_time_limit')) {
            @set_time_limit(3600);
        }

        // زيادة حد الذاكرة إذا أمكن
        if (function_exists('memory_get_usage')) {
            $currentMemory = memory_get_usage(true);
            $requiredMemory = 2 * 1024 * 1024 * 1024; // 2GB

            if ($currentMemory < $requiredMemory) {
                @ini_set('memory_limit', '2048M');
            }
        }
    }
}
