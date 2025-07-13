console.log('🔍 DEBUGGING ATTACHMENT DATA STORAGE\n');

console.log('📋 TRACING DATA FLOW:');
console.log('=' .repeat(60));

// Simulate the actual file processing
const testFile = {
    originalName: 'A_566557550_4.jpg',
    folderName: '001460',
    fileSize: 1024000
};

console.log(`\n1. 📁 INPUT DATA:`);
console.log(`   Original file: ${testFile.originalName}`);
console.log(`   Target folder: ${testFile.folderName}`);
console.log(`   File size: ${testFile.fileSize} bytes`);

console.log(`\n2. 🔧 PROCESSING STEPS:`);

// Step 1: Extract identity number from ORIGINAL filename
const originalNameWithoutExt = testFile.originalName.split('.')[0];
const originalParts = originalNameWithoutExt.split('_');
const identityNumber = originalParts[1]; // From A_566557550_4
console.log(`   extractIdentityNumberFromFilename(${testFile.originalName}) = ${identityNumber}`);

// Step 2: Process filename to get NEW filename
const documentTypeId = originalParts[2];
const mockDocumentTypes = {
    '1': 'BIRTH',
    '2': 'DEATH',
    '3': 'ID',
    '4': 'PASS',
    '5': 'OTHER'
};
const prefix = mockDocumentTypes[documentTypeId];
const newFileName = `${prefix}_${testFile.folderName}_${identityNumber}.jpg`;
console.log(`   processImageFileName(${testFile.originalName}, ${testFile.folderName}) = ${newFileName}`);

// Step 3: Generate storage path
const storedPath = `public/uploads/${testFile.folderName}/${newFileName}`;
console.log(`   storeAs() = ${storedPath}`);

// Step 4: Determine file type
const fileType = 'image'; // from jpg extension
console.log(`   determineFileTypeFromExtension('jpg') = ${fileType}`);

console.log(`\n3. 💾 WHAT GETS STORED IN DATABASE:`);
const attachmentRecord = {
    person_identity_number: identityNumber,
    stored_file_name: newFileName,
    file_path: storedPath,
    file_type: fileType,
    file_size: testFile.fileSize
};

console.log(`   saveToAttachmentsTable() parameters:`);
console.log(`   - identityNumber: ${identityNumber}`);
console.log(`   - storedFileName: ${newFileName}`);
console.log(`   - filePath: ${storedPath}`);
console.log(`   - fileType: ${fileType}`);
console.log(`   - fileSize: ${testFile.fileSize}`);

console.log(`\n4. 🗄️  DATABASE RECORD:`);
console.log(`   INSERT INTO attachments:`);
Object.entries(attachmentRecord).forEach(([key, value]) => {
    console.log(`      ${key}: "${value}"`);
});

console.log(`\n5. ✅ EXPECTED vs ACTUAL CHECK:`);
console.log(`   Expected stored_file_name: "PASS_001460_566557550.jpg"`);
console.log(`   Actual stored_file_name: "${newFileName}"`);
console.log(`   Match: ${newFileName === 'PASS_001460_566557550.jpg' ? '✅ YES' : '❌ NO'}`);

console.log(`   Expected person_identity_number: "566557550"`);
console.log(`   Actual person_identity_number: "${identityNumber}"`);
console.log(`   Match: ${identityNumber === '566557550' ? '✅ YES' : '❌ NO'}`);

console.log(`\n6. 🚨 POTENTIAL ISSUES TO CHECK:`);
console.log(`   • Is the database table structure correct?`);
console.log(`   • Are the column names matching exactly?`);
console.log(`   • Is the data being truncated or modified?`);
console.log(`   • Are there any database constraints failing?`);

console.log(`\n7. 🔍 DEBUG SUGGESTIONS:`);
console.log(`   • Add debug logging to saveToAttachmentsTable method`);
console.log(`   • Check database table schema`);
console.log(`   • Verify column data types and lengths`);
console.log(`   • Test with a simple static insert`);

console.log('\n' + '=' .repeat(60));
console.log('📊 SUMMARY: The code logic appears correct.');
console.log('🔍 Issue might be in database schema or data handling.');
console.log('💡 Need to examine actual database structure and constraints.');
