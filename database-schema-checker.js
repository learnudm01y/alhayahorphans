console.log('🔍 DATABASE SCHEMA CHECKER\n');

console.log('📋 SQL COMMANDS TO CHECK DATABASE STRUCTURE:');
console.log('=' .repeat(60));

console.log('\n1. 🗄️  CHECK TABLE STRUCTURE:');
console.log('   Run this SQL command in your database:');
console.log('   ```sql');
console.log('   DESCRIBE attachments;');
console.log('   -- OR --');
console.log('   SHOW COLUMNS FROM attachments;');
console.log('   ```');

console.log('\n2. 📊 EXPECTED TABLE STRUCTURE:');
console.log('   ┌─────────────────────────┬─────────────┬─────────────┐');
console.log('   │ Column Name             │ Type        │ Null        │');
console.log('   ├─────────────────────────┼─────────────┼─────────────┤');
console.log('   │ id                      │ bigint      │ NO          │');
console.log('   │ person_identity_number  │ varchar     │ YES         │');
console.log('   │ stored_file_name        │ varchar     │ NO          │');
console.log('   │ file_path              │ text        │ NO          │');
console.log('   │ file_type              │ varchar     │ NO          │');
console.log('   │ file_size              │ bigint      │ NO          │');
console.log('   │ created_at             │ timestamp   │ YES         │');
console.log('   │ updated_at             │ timestamp   │ YES         │');
console.log('   └─────────────────────────┴─────────────┴─────────────┘');

console.log('\n3. 🔍 CHECK FOR EXISTING DATA:');
console.log('   ```sql');
console.log('   SELECT * FROM attachments ORDER BY created_at DESC LIMIT 5;');
console.log('   ```');

console.log('\n4. 🚨 COMMON ISSUES TO VERIFY:');
console.log('   • Column names match exactly (case sensitive)');
console.log('   • Data types are appropriate:');
console.log('     - person_identity_number: VARCHAR(50)');
console.log('     - stored_file_name: VARCHAR(255)');
console.log('     - file_path: TEXT');
console.log('     - file_type: VARCHAR(50)');
console.log('     - file_size: BIGINT');

console.log('\n5. 🔧 CREATE MIGRATION IF NEEDED:');
console.log('   If table structure is wrong, create migration:');
console.log('   ```bash');
console.log('   php artisan make:migration update_attachments_table_structure');
console.log('   ```');

console.log('\n6. 📝 MIGRATION EXAMPLE:');
console.log('   ```php');
console.log('   public function up()');
console.log('   {');
console.log('       Schema::table(\'attachments\', function (Blueprint $table) {');
console.log('           // Add or modify columns as needed');
console.log('           $table->string(\'person_identity_number\', 50)->nullable();');
console.log('           $table->string(\'stored_file_name\', 255);');
console.log('           $table->text(\'file_path\');');
console.log('           $table->string(\'file_type\', 50);');
console.log('           $table->bigInteger(\'file_size\');');
console.log('       });');
console.log('   }');
console.log('   ```');

console.log('\n7. 🧪 TEST MANUAL INSERT:');
console.log('   Test with manual SQL insert:');
console.log('   ```sql');
console.log('   INSERT INTO attachments (');
console.log('       person_identity_number,');
console.log('       stored_file_name,');
console.log('       file_path,');
console.log('       file_type,');
console.log('       file_size,');
console.log('       created_at,');
console.log('       updated_at');
console.log('   ) VALUES (');
console.log('       \'566557550\',');
console.log('       \'PASS_001460_566557550.jpg\',');
console.log('       \'public/uploads/001460/PASS_001460_566557550.jpg\',');
console.log('       \'image\',');
console.log('       1024000,');
console.log('       NOW(),');
console.log('       NOW()');
console.log('   );');
console.log('   ```');

console.log('\n8. 🔍 LARAVEL LOG CHECK:');
console.log('   Check Laravel logs for errors:');
console.log('   ```bash');
console.log('   tail -f storage/logs/laravel.log');
console.log('   ```');

console.log('\n' + '=' .repeat(60));
console.log('💡 NEXT STEPS:');
console.log('1. Check database table structure');
console.log('2. Verify column names and types');
console.log('3. Test manual insert');
console.log('4. Check Laravel logs for detailed errors');
console.log('5. Upload a test file and monitor logs');
