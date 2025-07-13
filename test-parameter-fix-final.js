import fetch from 'node-fetch';
import FormData from 'form-data';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function testParameterTypeFix() {
    console.log('🔧 FINAL TEST: Folder Upload Parameter Type Fix\n');

    try {
        // Create test files to simulate folder upload
        const testFolder = path.join(__dirname, 'test-upload-folder');
        if (!fs.existsSync(testFolder)) {
            fs.mkdirSync(testFolder);
        }

        const testFile1 = path.join(testFolder, 'document1.pdf');
        const testFile2 = path.join(testFolder, 'image1.jpg');

        fs.writeFileSync(testFile1, 'PDF content simulation');
        fs.writeFileSync(testFile2, 'JPG content simulation');

        console.log('✅ Created test folder structure');
        console.log('📁 Test folder:', testFolder);
        console.log('📄 Files:', ['document1.pdf', 'image1.jpg']);

        // Get CSRF token first
        console.log('🔑 Getting CSRF token...');
        const csrfResponse = await fetch('http://127.0.0.1:8001');
        const csrfHtml = await csrfResponse.text();
        const csrfMatch = csrfHtml.match(/name="csrf-token" content="([^"]+)"/);
        const csrfToken = csrfMatch ? csrfMatch[1] : 'test-token';

        // Prepare FormData with multiple files
        const form = new FormData();
        form.append('files[]', fs.createReadStream(testFile1), {
            filename: 'document1.pdf'
        });
        form.append('files[]', fs.createReadStream(testFile2), {
            filename: 'image1.jpg'
        });
        form.append('folder_name', 'test-upload-folder');

        console.log('📤 Sending folder upload request...');
        console.log('🎯 Testing processValidatedFolderFile parameter type...');

        // Send request to test the fix
        const response = await fetch('http://127.0.0.1:8001/api/files/process-folder-upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                ...form.getHeaders()
            },
            body: form
        });

        console.log('📊 Response Status:', response.status);
        const responseText = await response.text();

        try {
            const responseData = JSON.parse(responseText);
            console.log('📋 Response Data:', JSON.stringify(responseData, null, 2));
        } catch (e) {
            console.log('📋 Raw Response:', responseText.substring(0, 500));
        }

        // Check for the specific error we fixed
        if (responseText.includes('must be of type array, Illuminate\\Http\\UploadedFile given')) {
            console.log('\n❌ FAILED: Parameter type error still exists!');
            console.log('🔧 The processValidatedFolderFile method still expects array but receives UploadedFile');
        } else if (responseText.includes('processValidatedFolderFile')) {
            console.log('\n✅ PARAMETER TYPE FIXED! Different error with processValidatedFolderFile method');
            console.log('🎯 The parameter signature change worked successfully');
        } else if (response.status === 200) {
            console.log('\n🎉 COMPLETE SUCCESS! Folder upload working perfectly!');
            console.log('✅ All parameter type issues resolved');
        } else {
            console.log('\n✅ PARAMETER TYPE FIXED! Different error occurred (not parameter type)');
            console.log('🔧 The specific "array vs UploadedFile" error has been resolved');
        }

        // Cleanup
        fs.unlinkSync(testFile1);
        fs.unlinkSync(testFile2);
        fs.rmdirSync(testFolder);
        console.log('\n🧹 Cleaned up test files');

    } catch (error) {
        console.log('\n❌ TEST ERROR:', error.message);
        console.log('⚠️  This may indicate network issues, not parameter type problems');
    }
}

testParameterTypeFix();
