# 🛠️ MySQL Migration Error Fix Guide
**Database Migration Issue Resolution**

## 🚨 Error Description
```
SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'duplicate_files_temp_expires_at_index'
```

**Root Cause:** The migration `2025_01_12_000000_create_duplicate_files_temp_table.php` was trying to create the same index twice:
1. `$table->timestamp('expires_at')->index();` - Creates index automatically
2. `$table->index('expires_at');` - Tries to create same index again

---

## ✅ Solution Applied

### 1. Fixed Original Migration
**File:** `database/migrations/2025_01_12_000000_create_duplicate_files_temp_table.php`
```php
// BEFORE (causing error):
$table->timestamp('expires_at')->index();
$table->index('expires_at');

// AFTER (fixed):
$table->timestamp('expires_at');
$table->index('expires_at');
```

### 2. Created Safe Migration
**File:** `database/migrations/2025_07_20_121500_fix_duplicate_files_temp_table.php`

This new migration:
- ✅ Checks if table exists before creating
- ✅ Checks if indexes exist before adding them
- ✅ Prevents duplicate index errors
- ✅ Works on both fresh and existing databases

### 3. Backup Created
- Original problematic file moved to: `2025_01_12_000000_create_duplicate_files_temp_table.php.backup`

---

## 🚀 Deployment Instructions

### For Fresh Database (New Installation):
```bash
php artisan migrate
```
The new migration will create the table correctly.

### For Existing Database (With Conflicts):
If you still get errors, run these SQL commands directly on your hosting:

```sql
-- Check if table exists
SHOW TABLES LIKE 'duplicate_files_temp';

-- If table exists, check indexes
SHOW INDEX FROM duplicate_files_temp;

-- Drop duplicate indexes if they exist
DROP INDEX duplicate_files_temp_expires_at_index ON duplicate_files_temp;

-- Then run migration
```

### Alternative: Manual Database Fix
```sql
-- Create table manually if migration fails
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

-- Mark migration as completed
INSERT INTO `migrations` (`migration`, `batch`) VALUES 
('2025_07_20_121500_fix_duplicate_files_temp_table', 1);
```

---

## 🔍 Common Migration Issues & Solutions

### Issue 1: "Table already exists"
```bash
# Reset specific migration
php artisan migrate:rollback --step=1

# Or reset all and start fresh
php artisan migrate:fresh
```

### Issue 2: "Index already exists"
```sql
-- Check existing indexes
SHOW INDEX FROM table_name;

-- Drop specific index
ALTER TABLE table_name DROP INDEX index_name;
```

### Issue 3: "Migration table not found"
```bash
# Create migration table
php artisan migrate:install
```

---

## 📋 Verification Checklist

### After Deployment:
- [ ] Run `php artisan migrate` successfully
- [ ] Check table exists: `SHOW TABLES LIKE 'duplicate_files_temp';`
- [ ] Verify indexes: `SHOW INDEX FROM duplicate_files_temp;`
- [ ] Test application functionality
- [ ] Check Laravel logs for errors

### Expected Table Structure:
```
duplicate_files_temp
├── id (bigint, primary key)
├── session_id (varchar 100, indexed)
├── original_name (varchar 255)
├── duplicate_name (varchar 255)
├── temp_path (varchar 500)
├── original_folder (varchar 100, nullable)
├── target_folder (varchar 100, nullable)
├── existing_file_name (varchar 255, nullable)
├── created_at (timestamp)
└── expires_at (timestamp, indexed)

Indexes:
├── PRIMARY (id)
├── duplicate_files_temp_session_id_index (session_id)
├── duplicate_files_temp_expires_at_index (expires_at)
└── duplicate_files_temp_session_id_expires_at_index (session_id, expires_at)
```

---

## 🎯 Prevention Tips

### Best Practices for Migrations:
1. **Avoid Duplicate Indexes:**
   ```php
   // DON'T DO THIS:
   $table->timestamp('field')->index();
   $table->index('field'); // Duplicate!
   
   // DO THIS INSTEAD:
   $table->timestamp('field');
   $table->index('field');
   ```

2. **Check Before Adding:**
   ```php
   if (!Schema::hasTable('table_name')) {
       // Create table
   }
   
   if (!Schema::hasColumn('table_name', 'column_name')) {
       // Add column
   }
   ```

3. **Use Descriptive Index Names:**
   ```php
   $table->index('column_name', 'custom_index_name');
   ```

4. **Test Migrations Locally:**
   - Always test on local database first
   - Use `migrate:fresh` to test clean installation
   - Use `migrate:rollback` to test reversibility

---

## 📞 Emergency Recovery

If migration completely fails on production:

1. **Database Backup:** Always backup before migration
2. **Manual Table Creation:** Use SQL commands above
3. **Migration Table Fix:** 
   ```sql
   INSERT INTO migrations (migration, batch) VALUES 
   ('2025_07_20_121500_fix_duplicate_files_temp_table', 1);
   ```
4. **Application Test:** Verify all features work correctly

---

**Status:** ✅ Ready for deployment  
**Files Changed:** 2 migrations (1 fixed, 1 new)  
**Risk Level:** Low (backward compatible)  
**Test Required:** Yes (verify table creation)

*Generated: July 20, 2025*
