<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigureLargeFileUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:configure-large-upload {--show-current : Show current PHP settings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure PHP settings for large file uploads (up to 1GB)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('show-current')) {
            $this->showCurrentSettings();
            return;
        }

        $this->info('🚀 Configuring PHP for Large File Uploads (1GB)');
        $this->line('');

        // Show current settings
        $this->showCurrentSettings();

        $this->line('');
        $this->info('⚙️ Applying new settings...');

        // Apply settings programmatically
        $this->applySettings();

        $this->line('');
        $this->info('📝 Creating/updating configuration files...');

        // Create htaccess configurations
        $this->createHtaccessConfig();

        // Create php.ini files
        $this->createPhpIniFiles();

        $this->line('');
        $this->info('✅ Configuration completed!');
        $this->line('');

        $this->table(['Setting', 'Before', 'After'], [
            ['upload_max_filesize', $this->formatBytes(ini_get('upload_max_filesize')), '1GB'],
            ['post_max_size', $this->formatBytes(ini_get('post_max_size')), '1GB'],
            ['memory_limit', ini_get('memory_limit'), '2GB'],
            ['max_execution_time', ini_get('max_execution_time') . 's', '3600s'],
        ]);

        $this->line('');
        $this->info('🌐 Test your configuration at:');
        $this->line('   - Diagnostic page: /php-diagnostic.php');
        $this->line('   - Excel gateway: /admin/unified-file-management/excel-gateway');

        $this->line('');
        $this->warn('⚠️ Note: Some settings may require server restart to take full effect.');
    }

    private function showCurrentSettings()
    {
        $this->info('📊 Current PHP Settings:');

        $settings = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'max_file_uploads' => ini_get('max_file_uploads'),
            'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled'
        ];

        foreach ($settings as $setting => $value) {
            $status = $this->getSettingStatus($setting, $value);
            $this->line("   {$setting}: {$value} {$status}");
        }
    }

    private function getSettingStatus($setting, $value)
    {
        $requirements = [
            'upload_max_filesize' => '1024M',
            'post_max_size' => '1024M',
            'memory_limit' => '2048M',
            'max_execution_time' => '3600s',
            'max_file_uploads' => '100',
            'file_uploads' => 'Enabled'
        ];

        if (!isset($requirements[$setting])) {
            return '';
        }

        $required = $requirements[$setting];

        if ($setting === 'file_uploads') {
            return $value === $required ? '<info>✓</info>' : '<error>✗</error>';
        }

        if (in_array($setting, ['upload_max_filesize', 'post_max_size', 'memory_limit'])) {
            $currentBytes = $this->convertToBytes($value);
            $requiredBytes = $this->convertToBytes($required);
            return $currentBytes >= $requiredBytes ? '<info>✓</info>' : '<error>✗</error>';
        }

        $currentNum = (int) str_replace('s', '', $value);
        $requiredNum = (int) str_replace('s', '', $required);
        return $currentNum >= $requiredNum ? '<info>✓</info>' : '<error>✗</error>';
    }

    private function applySettings()
    {
        $settings = [
            'upload_max_filesize' => '1024M',
            'post_max_size' => '1024M',
            'memory_limit' => '2048M',
            'max_execution_time' => '3600',
            'max_input_time' => '3600',
            'max_file_uploads' => '100',
            'file_uploads' => '1',
            'max_input_vars' => '10000'
        ];

        foreach ($settings as $setting => $value) {
            if (function_exists('ini_set')) {
                @ini_set($setting, $value);
                $this->line("   ✓ Set {$setting} = {$value}");
            }
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(3600);
            $this->line("   ✓ Set execution time limit to 3600s");
        }
    }

    private function createHtaccessConfig()
    {
        $htaccessPath = public_path('.htaccess');

        if (File::exists($htaccessPath)) {
            $content = File::get($htaccessPath);

            // Check if our settings already exist
            if (strpos($content, 'upload_max_filesize 1024M') !== false) {
                $this->line("   ✓ .htaccess already configured");
                return;
            }
        }

        $phpSettings = "
# تحسين حدود رفع الملفات لدعم ملفات كبيرة (1GB)
<IfModule mod_php7.c>
    php_value upload_max_filesize 1024M
    php_value post_max_size 1024M
    php_value max_execution_time 3600
    php_value max_input_time 3600
    php_value memory_limit 2048M
    php_value file_uploads On
    php_value max_file_uploads 100
    php_value max_input_vars 10000
</IfModule>

<IfModule mod_php8.c>
    php_value upload_max_filesize 1024M
    php_value post_max_size 1024M
    php_value max_execution_time 3600
    php_value max_input_time 3600
    php_value memory_limit 2048M
    php_value file_uploads On
    php_value max_file_uploads 100
    php_value max_input_vars 10000
</IfModule>

";

        if (File::exists($htaccessPath)) {
            $existingContent = File::get($htaccessPath);
            File::put($htaccessPath, $phpSettings . $existingContent);
        } else {
            File::put($htaccessPath, $phpSettings);
        }

        $this->line("   ✓ Updated .htaccess file");
    }

    private function createPhpIniFiles()
    {
        $phpIniContent = "; إعدادات PHP لدعم الملفات الكبيرة
upload_max_filesize = 1024M
post_max_size = 1024M
memory_limit = 2048M
max_execution_time = 3600
max_input_time = 3600
max_file_uploads = 100
file_uploads = On
max_input_vars = 10000
default_socket_timeout = 3600
realpath_cache_size = 4096K
realpath_cache_ttl = 600
";

        // Create in project root
        File::put(base_path('php.ini'), $phpIniContent);
        $this->line("   ✓ Created php.ini in project root");

        // Create in public directory
        File::put(public_path('php.ini'), $phpIniContent);
        $this->line("   ✓ Created php.ini in public directory");
    }

    private function convertToBytes($value)
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        switch($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }

    private function formatBytes($bytes)
    {
        $value = $this->convertToBytes($bytes);

        if ($value >= 1024 * 1024 * 1024) {
            return round($value / (1024 * 1024 * 1024), 1) . 'GB';
        } elseif ($value >= 1024 * 1024) {
            return round($value / (1024 * 1024), 1) . 'MB';
        } elseif ($value >= 1024) {
            return round($value / 1024, 1) . 'KB';
        }

        return $value . 'B';
    }
}
