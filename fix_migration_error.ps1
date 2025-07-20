# Laravel Migration Fix Script for Windows/PowerShell
# Run this script if you encounter the duplicate index error

Write-Host "🔧 Laravel Migration Fix Script" -ForegroundColor Cyan
Write-Host "===============================" -ForegroundColor Cyan

# Step 1: Check current migration status
Write-Host "📋 Checking migration status..." -ForegroundColor Yellow
php artisan migrate:status

Write-Host ""
Write-Host "🛠️ Fixing duplicate index error..." -ForegroundColor Yellow

# Step 2: Try to run pending migrations
Write-Host "🚀 Running pending migrations..." -ForegroundColor Green
$result = php artisan migrate --force

# Check if migration was successful
if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "❌ Migration failed. Trying alternative approaches..." -ForegroundColor Red

    Write-Host ""
    Write-Host "📝 Manual SQL commands to run in your database:" -ForegroundColor Cyan
    Write-Host "=============================================="

    Write-Host "-- Check if table exists" -ForegroundColor Gray
    Write-Host "SHOW TABLES LIKE 'duplicate_files_temp';" -ForegroundColor White
    Write-Host ""

    Write-Host "-- If table exists, check existing indexes" -ForegroundColor Gray
    Write-Host "SHOW INDEX FROM duplicate_files_temp;" -ForegroundColor White
    Write-Host ""

    Write-Host "-- Drop problematic index if it exists" -ForegroundColor Gray
    Write-Host "ALTER TABLE duplicate_files_temp DROP INDEX IF EXISTS duplicate_files_temp_expires_at_index;" -ForegroundColor White
    Write-Host ""

    Write-Host "-- Create table manually if needed" -ForegroundColor Gray
    Write-Host @"
CREATE TABLE IF NOT EXISTS `duplicate_files_temp` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `session_id` varchar(100) NOT NULL,
    `original_name` varchar(255) NOT NULL,
    `duplicate_name` varchar(255) NOT NULL,
    `temp_path` varchar(500) NOT NULL,
    `original_folder` varchar(100) DEFAULT NULL,
    `target_folder` varchar(100) DEFAULT NULL,
    `existing_file_name` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL,
    `expires_at` timestamp NOT NULL,
    PRIMARY KEY (`id`),
    KEY `duplicate_files_temp_session_id_index` (`session_id`),
    KEY `duplicate_files_temp_expires_at_index` (`expires_at`),
    KEY `duplicate_files_temp_session_id_expires_at_index` (`session_id`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"@ -ForegroundColor White
    Write-Host ""

    Write-Host "-- Mark migration as completed" -ForegroundColor Gray
    Write-Host @"
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2025_07_20_121500_fix_duplicate_files_temp_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations AS m));
"@ -ForegroundColor White
    Write-Host ""

    Write-Host "🎯 After running these SQL commands, try migration again:" -ForegroundColor Yellow
    Write-Host "php artisan migrate --force" -ForegroundColor White
} else {
    Write-Host "✅ Migration completed successfully!" -ForegroundColor Green
}

Write-Host ""
Write-Host "🔍 Final verification..." -ForegroundColor Yellow
php artisan migrate:status

Write-Host ""
Write-Host "✅ Fix script completed!" -ForegroundColor Green
Write-Host "📞 If issues persist, check the MIGRATION_ERROR_FIX_GUIDE.md file for detailed troubleshooting." -ForegroundColor Cyan
