#!/bin/bash
# Migration Fix Script for Production Hosting
# Run this script if you encounter the duplicate index error

echo "🔧 Laravel Migration Fix Script"
echo "==============================="

# Step 1: Check current migration status
echo "📋 Checking migration status..."
php artisan migrate:status

echo ""
echo "🛠️ Fixing duplicate index error..."

# Step 2: Try to run pending migrations
echo "🚀 Running pending migrations..."
php artisan migrate --force

# If migration fails, provide alternative solutions
if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Migration failed. Trying alternative approaches..."

    echo ""
    echo "📝 Manual SQL commands to run in your database:"
    echo "=============================================="

    echo "-- Check if table exists"
    echo "SHOW TABLES LIKE 'duplicate_files_temp';"
    echo ""

    echo "-- If table exists, check existing indexes"
    echo "SHOW INDEX FROM duplicate_files_temp;"
    echo ""

    echo "-- Drop problematic index if it exists"
    echo "ALTER TABLE duplicate_files_temp DROP INDEX IF EXISTS duplicate_files_temp_expires_at_index;"
    echo ""

    echo "-- Create table manually if needed"
    echo "CREATE TABLE IF NOT EXISTS \`duplicate_files_temp\` ("
    echo "    \`id\` bigint(20) unsigned NOT NULL AUTO_INCREMENT,"
    echo "    \`session_id\` varchar(100) NOT NULL,"
    echo "    \`original_name\` varchar(255) NOT NULL,"
    echo "    \`duplicate_name\` varchar(255) NOT NULL,"
    echo "    \`temp_path\` varchar(500) NOT NULL,"
    echo "    \`original_folder\` varchar(100) DEFAULT NULL,"
    echo "    \`target_folder\` varchar(100) DEFAULT NULL,"
    echo "    \`existing_file_name\` varchar(255) DEFAULT NULL,"
    echo "    \`created_at\` timestamp NOT NULL,"
    echo "    \`expires_at\` timestamp NOT NULL,"
    echo "    PRIMARY KEY (\`id\`),"
    echo "    KEY \`duplicate_files_temp_session_id_index\` (\`session_id\`),"
    echo "    KEY \`duplicate_files_temp_expires_at_index\` (\`expires_at\`),"
    echo "    KEY \`duplicate_files_temp_session_id_expires_at_index\` (\`session_id\`,\`expires_at\`)"
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    echo ""

    echo "-- Mark migration as completed"
    echo "INSERT INTO \`migrations\` (\`migration\`, \`batch\`) VALUES"
    echo "('2025_07_20_121500_fix_duplicate_files_temp_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations AS m));"
    echo ""

    echo "🎯 After running these SQL commands, try migration again:"
    echo "php artisan migrate --force"
else
    echo "✅ Migration completed successfully!"
fi

echo ""
echo "🔍 Final verification..."
php artisan migrate:status

echo ""
echo "✅ Fix script completed!"
echo "📞 If issues persist, check the MIGRATION_ERROR_FIX_GUIDE.md file for detailed troubleshooting."
