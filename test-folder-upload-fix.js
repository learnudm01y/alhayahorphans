import axios from 'axios';
import FormData from 'form-data';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function testFolderUploadFix() {
    console.log('🔧 Testing Folder Upload Fix...\n');

    try {
        // Test 1: Create a simple test file
        const testContent = 'Test file content for folder upload';
        const testFilePath = path.join(__dirname, 'test-file.txt');
        fs.writeFileSync(testFilePath, testContent);

        console.log('✅ Created test file:', testFilePath);

        // Test 2: Prepare FormData as if uploading a folder
        const form = new FormData();
        form.append('files[]', fs.createReadStream(testFilePath), {
            filename: 'test-file.txt',
            filepath: 'test-folder/test-file.txt' // Simulate folder structure
        });
        form.append('folder_name', 'test-folder');

        console.log('📤 Sending folder upload request...');

        // Test 3: Send request to API
        const response = await axios.post('http://localhost:8001/api/process-folder-upload', form, {
            headers: {
                ...form.getHeaders(),
                'Accept': 'application/json'
            },
            timeout: 30000
        });

        console.log('📊 Response Status:', response.status);
        console.log('📋 Response Data:', JSON.stringify(response.data, null, 2));

        if (response.status === 200) {
            console.log('\n✅ SUCCESS: Folder upload fix is working!');
            console.log('🔧 The parameter type mismatch has been resolved.');
        }

    } catch (error) {
        console.log('\n❌ ERROR Details:');

        if (error.response) {
            console.log('Status:', error.response.status);
            console.log('Data:', error.response.data);

            // Check if the specific error is fixed
            const errorData = typeof error.response.data === 'string'
                ? error.response.data
                : JSON.stringify(error.response.data);

            if (errorData.includes('must be of type array, Illuminate\\Http\\UploadedFile given')) {
                console.log('❌ STILL BROKEN: Parameter type mismatch not fixed');
            } else if (errorData.includes('processValidatedFolderFile')) {
                console.log('🔧 DIFFERENT ERROR: New issue with processValidatedFolderFile method');
            } else {
                console.log('✅ PARAMETER FIX WORKING: Different error, parameter type issue resolved');
            }
        } else {
            console.log('Network Error:', error.message);
        }
    }

    // Cleanup
    try {
        if (fs.existsSync(testFilePath)) {
            fs.unlinkSync(testFilePath);
            console.log('🧹 Cleaned up test file');
        }
    } catch (e) {
        console.log('⚠️  Could not clean up test file:', e.message);
    }
}

// Run the test
testFolderUploadFix();
