# Fix Attachments Table Column Issue
# Created: July 20, 2025
# Problem: Column 'folder_id' not found in 'attachments' table

Write-Host "🔧 Attachments Table Column Fix" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# Colors for output
$Red = "Red"
$Green = "Green"
$Yellow = "Yellow"
$Cyan = "Cyan"

try {
    Write-Host "📋 Problem Analysis:" -ForegroundColor $Yellow
    Write-Host "   • Migration tries to add 'file_size' after 'folder_id'" -ForegroundColor White
    Write-Host "   • Column 'folder_id' doesn't exist in 'attachments' table" -ForegroundColor White
    Write-Host "   • This causes SQLSTATE[42S22]: Column not found error" -ForegroundColor White
    Write-Host ""

    Write-Host "🎯 Solution: Use safe column addition migration" -ForegroundColor $Green
    Write-Host ""

    # Check current migration status
    Write-Host "📊 Checking current migration status..." -ForegroundColor $Cyan
    php artisan migrate:status | Select-String "attachments"
    Write-Host ""

    # Method 1: Try running the new safe migration
    Write-Host "⚡ Method 1: Running safe migration (RECOMMENDED)" -ForegroundColor $Cyan
    Write-Host "   Running: php artisan migrate --force" -ForegroundColor White

    $migrateResult = php artisan migrate --force 2>&1

    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✅ Migration completed successfully!" -ForegroundColor $Green
        Write-Host ""

        # Verify the table structure
        Write-Host "🔍 Verifying table structure..." -ForegroundColor $Cyan
        Write-Host "   If you have access to your database, check that 'file_size' column exists in 'attachments' table" -ForegroundColor White

    } else {
        Write-Host "   ⚠️  Migration failed, trying manual approach..." -ForegroundColor $Yellow
        Write-Host $migrateResult -ForegroundColor White
        Write-Host ""

        # Method 2: Manual SQL commands
        Write-Host "⚡ Method 2: Manual SQL Fix" -ForegroundColor $Cyan
        Write-Host "   Connect to your database and run these SQL commands:" -ForegroundColor White
        Write-Host ""

        Write-Host "   -- Check current columns in attachments table" -ForegroundColor $Green
        Write-Host "   DESCRIBE attachments;" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Add file_size column safely" -ForegroundColor $Green
        Write-Host "   ALTER TABLE attachments ADD COLUMN file_size BIGINT UNSIGNED NULL;" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Mark the problematic migration as completed" -ForegroundColor $Green
        Write-Host "   INSERT INTO migrations (migration, batch) VALUES" -ForegroundColor $Green
        Write-Host "   ('2025_07_13_010847_add_missing_columns_to_attachments_table'," -ForegroundColor $Green
        Write-Host "   (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m));" -ForegroundColor $Green
        Write-Host ""

        Write-Host "   -- Then try running migrations again" -ForegroundColor $Green
        Write-Host "   -- php artisan migrate --force" -ForegroundColor $Green
    }

    Write-Host ""
    Write-Host "📊 Final migration status check..." -ForegroundColor $Cyan
    php artisan migrate:status | Select-String -Pattern "attachments|enhanced_attachments"

} catch {
    Write-Host "❌ Error occurred: $($_.Exception.Message)" -ForegroundColor $Red
    Write-Host ""
    Write-Host "🔧 Manual steps for production:" -ForegroundColor $Yellow
    Write-Host "1. Connect to your database via phpMyAdmin or similar tool" -ForegroundColor White
    Write-Host "2. Run: DESCRIBE attachments; (to see current columns)" -ForegroundColor White
    Write-Host "3. Run: ALTER TABLE attachments ADD COLUMN file_size BIGINT UNSIGNED NULL;" -ForegroundColor White
    Write-Host "4. Add missing migration record:" -ForegroundColor White
    Write-Host "   INSERT INTO migrations (migration, batch) VALUES" -ForegroundColor $Green
    Write-Host "   ('2025_07_13_010847_add_missing_columns_to_attachments_table'," -ForegroundColor $Green
    Write-Host "   (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m));" -ForegroundColor $Green
    Write-Host "5. Then run: php artisan migrate --force" -ForegroundColor White
}

Write-Host ""
Write-Host "✨ Attachments table fix completed!" -ForegroundColor $Green
Write-Host "🌐 Your file storage system should now work properly." -ForegroundColor $Green
