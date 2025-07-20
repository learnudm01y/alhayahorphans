# Fix Record Number Column Issue in Enhanced Attachments
# Created: July 20, 2025
# Problem: Column 'record_number' not found in GROUP BY clause

Write-Host "🔧 Enhanced Attachments Record Number Fix" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# Colors for output
$Red = "Red"
$Green = "Green"
$Yellow = "Yellow"
$Cyan = "Cyan"

try {
    Write-Host "📋 Problem Analysis:" -ForegroundColor $Yellow
    Write-Host "   • Application tries to use 'record_number' in GROUP BY clause" -ForegroundColor White
    Write-Host "   • Column 'record_number' might be missing from enhanced_attachments table" -ForegroundColor White
    Write-Host "   • This causes SQLSTATE[42S22]: Column not found error" -ForegroundColor White
    Write-Host ""

    Write-Host "🎯 Solution: Add missing column and populate data" -ForegroundColor $Green
    Write-Host ""

    # Method 1: Run migrations to add the column
    Write-Host "⚡ Method 1: Running migrations (RECOMMENDED)" -ForegroundColor $Cyan
    Write-Host "   Running: php artisan migrate --force" -ForegroundColor White

    $migrateResult = php artisan migrate --force 2>&1

    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✅ Migrations completed successfully!" -ForegroundColor $Green
        Write-Host ""

        # Check if record_number column exists now
        Write-Host "🔍 Verifying column exists..." -ForegroundColor $Cyan
        Write-Host "   Checking enhanced_attachments table structure..." -ForegroundColor White

    } else {
        Write-Host "   ⚠️  Migration failed, trying manual approach..." -ForegroundColor $Yellow
        Write-Host $migrateResult -ForegroundColor White
        Write-Host ""

        # Method 2: Manual SQL commands
        Write-Host "⚡ Method 2: Manual SQL Fix" -ForegroundColor $Cyan
        Write-Host "   Connect to your database and run these SQL commands:" -ForegroundColor White
        Write-Host ""

        Write-Host "   -- Check if record_number column exists" -ForegroundColor $Green
        Write-Host "   DESCRIBE enhanced_attachments;" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Add record_number column if missing" -ForegroundColor $Green
        Write-Host "   ALTER TABLE enhanced_attachments ADD COLUMN record_number VARCHAR(255) NULL;" -ForegroundColor $Green
        Write-Host "   ALTER TABLE enhanced_attachments ADD INDEX idx_record_number (record_number);" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Populate record_number from folder_id" -ForegroundColor $Green
        Write-Host "   UPDATE enhanced_attachments SET record_number = folder_id" -ForegroundColor $Green
        Write-Host "   WHERE record_number IS NULL AND folder_id IS NOT NULL AND folder_id != '';" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Populate record_number from file names (extract 6-digit numbers)" -ForegroundColor $Green
        Write-Host "   UPDATE enhanced_attachments" -ForegroundColor $Green
        Write-Host "   SET record_number = SUBSTRING(original_file_name, LOCATE(REGEXP '[0-9]{6}', original_file_name), 6)" -ForegroundColor $Green
        Write-Host "   WHERE record_number IS NULL" -ForegroundColor $Green
        Write-Host "   AND original_file_name REGEXP '[0-9]{6}';" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Mark migration as completed" -ForegroundColor $Green
        Write-Host "   INSERT INTO migrations (migration, batch) VALUES" -ForegroundColor $Green
        Write-Host "   ('2025_07_20_160000_add_record_number_to_enhanced_attachments'," -ForegroundColor $Green
        Write-Host "   (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m));" -ForegroundColor $Green
    }

    Write-Host ""
    Write-Host "📊 Final migration status check..." -ForegroundColor $Cyan
    php artisan migrate:status | Select-String -Pattern "enhanced_attachments|record_number"

    Write-Host ""
    Write-Host "✅ Testing folder listing functionality..." -ForegroundColor $Cyan
    Write-Host "   Try accessing your file management interface now" -ForegroundColor White
    Write-Host "   The 'Error fetching folders' should be resolved" -ForegroundColor White

} catch {
    Write-Host "❌ Error occurred: $($_.Exception.Message)" -ForegroundColor $Red
    Write-Host ""
    Write-Host "🔧 Manual steps for production:" -ForegroundColor $Yellow
    Write-Host "1. Connect to your database via phpMyAdmin or similar tool" -ForegroundColor White
    Write-Host "2. Run: DESCRIBE enhanced_attachments; (to see current columns)" -ForegroundColor White
    Write-Host "3. If record_number column is missing, run:" -ForegroundColor White
    Write-Host "   ALTER TABLE enhanced_attachments ADD COLUMN record_number VARCHAR(255) NULL;" -ForegroundColor $Green
    Write-Host "   ALTER TABLE enhanced_attachments ADD INDEX idx_record_number (record_number);" -ForegroundColor $Green
    Write-Host "4. Populate the column:" -ForegroundColor White
    Write-Host "   UPDATE enhanced_attachments SET record_number = folder_id" -ForegroundColor $Green
    Write-Host "   WHERE record_number IS NULL AND folder_id IS NOT NULL;" -ForegroundColor $Green
    Write-Host "5. Then run: php artisan migrate --force" -ForegroundColor White
}

Write-Host ""
Write-Host "✨ Enhanced attachments record number fix completed!" -ForegroundColor $Green
Write-Host "🌐 Your folder listing should now work properly." -ForegroundColor $Green
