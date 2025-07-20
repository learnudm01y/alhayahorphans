# Fix Enhanced Attachments Migration Conflict
# Created: July 20, 2025
# Problem: Multiple migrations trying to create the same table

Write-Host "🔧 Enhanced Attachments Migration Conflict Fix" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# Colors for output
$Red = "Red"
$Green = "Green"
$Yellow = "Yellow"
$Cyan = "Cyan"

try {
    Write-Host "📋 Current situation:" -ForegroundColor $Yellow
    Write-Host "   • Migration 2025_01_16_000001_create_enhanced_attachments_table is PENDING" -ForegroundColor White
    Write-Host "   • Migration 2025_07_12_052417_create_enhanced_attachments_table already RAN" -ForegroundColor White
    Write-Host "   • Both try to create the same table 'enhanced_attachments'" -ForegroundColor White
    Write-Host ""

    Write-Host "🎯 Solution: Mark the conflicting migration as completed" -ForegroundColor $Green
    Write-Host ""

    # Method 1: Mark migration as run without executing it
    Write-Host "⚡ Method 1: Mark migration as completed (RECOMMENDED)" -ForegroundColor $Cyan
    Write-Host "   Running: php artisan migrate:mark-as-run" -ForegroundColor White

    $markResult = php artisan migrate:mark-as-run --path=database/migrations/2025_01_16_000001_create_enhanced_attachments_table.php 2>&1

    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✅ Migration marked as completed successfully!" -ForegroundColor $Green
        Write-Host ""

        # Now try running all pending migrations
        Write-Host "🚀 Running remaining pending migrations..." -ForegroundColor $Cyan
        $migrateResult = php artisan migrate --force 2>&1

        if ($LASTEXITCODE -eq 0) {
            Write-Host "   ✅ All migrations completed successfully!" -ForegroundColor $Green
        } else {
            Write-Host "   ⚠️  Some migrations had issues:" -ForegroundColor $Yellow
            Write-Host $migrateResult -ForegroundColor White
        }
    } else {
        Write-Host "   ⚠️  Method 1 failed, trying alternative approach..." -ForegroundColor $Yellow
        Write-Host $markResult -ForegroundColor White
        Write-Host ""

        # Method 2: Manual database entry
        Write-Host "⚡ Method 2: Manual database fix" -ForegroundColor $Cyan
        Write-Host "   Please run this SQL command manually:" -ForegroundColor White
        Write-Host ""
        Write-Host "   INSERT INTO migrations (migration, batch) VALUES" -ForegroundColor $Green
        Write-Host "   ('2025_01_16_000001_create_enhanced_attachments_table', " -ForegroundColor $Green -NoNewline
        Write-Host "(SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m));" -ForegroundColor $Green
        Write-Host ""
        Write-Host "   Then run: php artisan migrate --force" -ForegroundColor $Green
    }

    Write-Host ""
    Write-Host "📊 Checking final migration status..." -ForegroundColor $Cyan
    php artisan migrate:status

} catch {
    Write-Host "❌ Error occurred: $($_.Exception.Message)" -ForegroundColor $Red
    Write-Host ""
    Write-Host "🔧 Manual steps to fix:" -ForegroundColor $Yellow
    Write-Host "1. Connect to your database" -ForegroundColor White
    Write-Host "2. Run this SQL:" -ForegroundColor White
    Write-Host "   INSERT INTO migrations (migration, batch) VALUES" -ForegroundColor $Green
    Write-Host "   ('2025_01_16_000001_create_enhanced_attachments_table', " -ForegroundColor $Green -NoNewline
    Write-Host "(SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m));" -ForegroundColor $Green
    Write-Host "3. Then run: php artisan migrate --force" -ForegroundColor White
}

Write-Host ""
Write-Host "✨ Fix completed! Check the output above for results." -ForegroundColor $Green
Write-Host "🌐 If successful, your file access should now work properly." -ForegroundColor $Green
