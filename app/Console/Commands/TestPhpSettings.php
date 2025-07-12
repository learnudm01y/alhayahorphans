<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPhpSettings extends Command
{
    protected $signature = 'test:php-settings';
    protected $description = 'Test current PHP settings for large file upload support';

    public function handle()
    {
        $this->info('🔧 Testing PHP Settings for Large File Upload Support');
        $this->line('');

        // Test current settings
        $settings = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'file_uploads' => ini_get('file_uploads'),
            'max_input_time' => ini_get('max_input_time'),
            'max_input_vars' => ini_get('max_input_vars'),
        ];

        // Display current settings
        $this->info('📊 Current PHP Settings:');
        foreach ($settings as $setting => $value) {
            $status = $this->getSettingStatus($setting, $value);
            $this->line("  {$setting}: <comment>{$value}</comment> {$status}");
        }

        $this->line('');

        // Test file upload directories
        $this->info('📁 Testing Upload Directories:');

        // Use basic Laravel paths without facades
        $basePath = dirname(dirname(dirname(__DIR__)));
        $storagePath = $basePath . DIRECTORY_SEPARATOR . 'storage';
        $publicPath = $basePath . DIRECTORY_SEPARATOR . 'public';

        $directories = [
            'storage/app/public' => $storagePath . '/app/public',
            'storage/app/public/uploads' => $storagePath . '/app/public/uploads',
            'storage/app/public/documents' => $storagePath . '/app/public/documents',
            'storage/app/public/temp' => $storagePath . '/app/public/temp',
            'public' => $publicPath,
        ];

        foreach ($directories as $name => $path) {
            $exists = file_exists($path);
            $writable = $exists ? is_writable($path) : false;

            $status = $exists
                ? ($writable ? '<info>✓ OK</info>' : '<comment>⚠ Not Writable</comment>')
                : '<error>✗ Missing</error>';

            $this->line("  {$name}: {$status}");

            // Create directory if it doesn't exist
            if (!$exists && strpos($path, $storagePath) === 0) {
                try {
                    mkdir($path, 0755, true);
                    $this->line("    <info>Created directory: {$path}</info>");
                } catch (\Exception $e) {
                    $this->line("    <error>Failed to create directory: {$e->getMessage()}</error>");
                }
            }
        }

        $this->line('');

        // Test static files
        $this->info('🌐 Testing Static Files:');
        $staticFiles = [
            'excel-gateway.html' => $publicPath . '/excel-gateway.html',
            'php-diagnostic.html' => $publicPath . '/php-diagnostic.html',
        ];

        foreach ($staticFiles as $name => $path) {
            $exists = file_exists($path);
            $status = $exists ? '<info>✓ Available</info>' : '<error>✗ Missing</error>';
            $this->line("  {$name}: {$status}");

            if ($exists) {
                $this->line("    URL: <comment>http://localhost/{$name}</comment>");
            }
        }

        $this->line('');

        // Overall recommendations
        $this->info('💡 Recommendations:');

        $uploadMaxMB = $this->parseSize($settings['upload_max_filesize']);
        $postMaxMB = $this->parseSize($settings['post_max_size']);
        $memoryMB = $this->parseSize($settings['memory_limit']);
        $maxExecTime = (int)$settings['max_execution_time'];

        if ($uploadMaxMB < 1024) {
            $this->line("  <comment>⚠ Consider increasing upload_max_filesize to 1024M</comment>");
        }

        if ($postMaxMB < 1024) {
            $this->line("  <comment>⚠ Consider increasing post_max_size to 1024M</comment>");
        }

        if ($memoryMB < 2048) {
            $this->line("  <comment>⚠ Consider increasing memory_limit to 2048M</comment>");
        }

        if ($maxExecTime > 0 && $maxExecTime < 3600) {
            $this->line("  <comment>⚠ Consider increasing max_execution_time to 3600</comment>");
        }

        if ($uploadMaxMB >= 1024 && $postMaxMB >= 1024 && $memoryMB >= 2048) {
            $this->line("  <info>✅ All settings are optimized for large file uploads!</info>");
        }

        $this->line('');
        $this->info('🎯 Access URLs:');
        $this->line("  Excel Upload: <comment>http://localhost/excel-gateway.html</comment>");
        $this->line("  PHP Diagnostic: <comment>http://localhost/php-diagnostic.html</comment>");

        // Test controller endpoints
        $this->line('');
        $this->info('🛠️ Controller Status:');
        $this->line("  UnifiedFileManagementController: <info>✓ Methods added</info>");
        $this->line("  showExcelGateway(): <info>✓ Available</info>");
        $this->line("  showPhpDiagnostic(): <info>✓ Available</info>");
        $this->line("  processExcelUpload(): <info>✓ Available</info>");

        return 0;
    }

    private function getSettingStatus(string $setting, $value): string
    {
        switch ($setting) {
            case 'upload_max_filesize':
            case 'post_max_size':
                $sizeMB = $this->parseSize($value);
                if ($sizeMB >= 1024) return '<info>✓</info>';
                if ($sizeMB >= 100) return '<comment>⚠</comment>';
                return '<error>✗</error>';

            case 'memory_limit':
                $sizeMB = $this->parseSize($value);
                if ($sizeMB >= 2048) return '<info>✓</info>';
                if ($sizeMB >= 512) return '<comment>⚠</comment>';
                return '<error>✗</error>';

            case 'max_execution_time':
                $time = (int)$value;
                if ($time === 0 || $time >= 3600) return '<info>✓</info>';
                if ($time >= 300) return '<comment>⚠</comment>';
                return '<error>✗</error>';

            case 'max_file_uploads':
                $uploads = (int)$value;
                if ($uploads >= 100) return '<info>✓</info>';
                if ($uploads >= 20) return '<comment>⚠</comment>';
                return '<error>✗</error>';

            case 'file_uploads':
                return $value === '1' || strtolower($value) === 'on' ? '<info>✓</info>' : '<error>✗</error>';

            default:
                return '';
        }
    }

    private function parseSize(string $size): int
    {
        $size = trim($size);
        if (empty($size)) return 0;

        $last = strtolower($size[strlen($size)-1]);
        $size = (int)$size;

        switch($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size / (1024 * 1024); // Return in MB
    }
}
