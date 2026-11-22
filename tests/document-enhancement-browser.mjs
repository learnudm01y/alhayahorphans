import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import puppeteer from 'puppeteer';

const source = await readFile(new URL('../resources/views/user/generalRegistration/javascript/documentUpload.blade.php', import.meta.url), 'utf8');
const markup = source
    .replace("@push('scriptsCodeUserRegistration')", '')
    .replace('@endpush', '')
    .replace("@json($documentTypes->pluck('file_type', 'pref')->toArray())", JSON.stringify({ identity: 'document', photo: 'image' }))
    .replace('{{ route("upload.chunk.generalRegistration") }}', '/upload-test');
const uploads = [];
let rejectUpload = false;
const html = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="csrf-token" content="isolated-test">
<title>اختبار تحسين المستندات</title><style>body{font-family:Arial;padding:24px}img{max-width:200px}select{padding:10px} .row{display:flex;gap:12px;flex-wrap:wrap}</style></head><body>
<h1>اختبار رفع المستندات دون قص يدوي</h1><input name="file_id_number" value="123456"><input id="data_id_number" value="111111111">
<div data-upload-zone="main"><select><option value="">اختر نوع الوثيقة</option><option value="identity">وثيقة الهوية</option><option value="photo">صورة شخصية</option><option value="unknown">غير مصنف</option></select><input type="file"><div class="mainDocumentPreview row" data-role="preview"></div></div>
<script>window.testPrompts=[];window.cropCalls=[];window.Swal={fire(o){window.testPrompts.push(o.title);return Promise.resolve({isConfirmed:false})},close(){},showLoading(){}};window.showCropperModal=function(file,callback){window.cropCalls.push(file._fileType);callback(file)};</script>
${markup}</body></html>`;
const server = createServer(async (request, response) => {
    if (request.url === '/upload-test') {
        const parts = [];
        for await (const part of request) parts.push(part);
        const body = Buffer.concat(parts);
        uploads.push(body);
        const fields = body.toString('latin1');
        const chunkIndex = Number(fields.match(/name="chunk_index"\r\n\r\n(\d+)/)?.[1] ?? 0);
        const totalChunks = Number(fields.match(/name="total_chunks"\r\n\r\n(\d+)/)?.[1] ?? 1);
        response.writeHead(rejectUpload ? 503 : 200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify(chunkIndex + 1 === totalChunks
            ? { complete: true, path: 'temp_uploads/123456/test.jpg' }
            : { complete: false }));
    } else {
        response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        response.end(html);
    }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const browser = await puppeteer.launch({ headless: true });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await page.setViewport({ width: 900, height: 900 });
const address = `http://127.0.0.1:${server.address().port}`;

async function selectFile(type, name, mime = 'image/png', options = {}) {
    await page.evaluate(async (type, name, mime, options) => {
        const zone = document.querySelector('[data-upload-zone="main"]');
        zone.querySelector('select').value = type;
        const canvas = document.createElement('canvas');
        canvas.width = options.width || 600;
        canvas.height = options.height || 800;
        const context = canvas.getContext('2d');
        if (options.noise) {
            const image = context.createImageData(canvas.width, canvas.height);
            let seed = 123456789;
            const random = () => {
                seed ^= seed << 13;
                seed ^= seed >>> 17;
                seed ^= seed << 5;
                return (seed >>> 8) & 255;
            };
            for (let i = 0; i < image.data.length; i += 4) {
                image.data[i] = random();
                image.data[i + 1] = random();
                image.data[i + 2] = random();
                image.data[i + 3] = 255;
            }
            context.putImageData(image, 0, 0);
        } else {
            context.fillStyle = '#eee8dd';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = '#111';
            context.font = '24px Arial';
            for (let row = 0; row < 16; row++) context.fillText('Test document 123456789', 50, 65 + row * 40);
        }
        const blob = await new Promise(resolve => canvas.toBlob(resolve));
        const file = new File([blob], name, { type: mime });
        const transfer = new DataTransfer();
        transfer.items.add(file);
        const input = zone.querySelector('input[type="file"]');
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, type, name, mime, options);
}

async function waitUploaded(name, state = 'done') {
    await page.waitForFunction((name, state) => (window.allDocs?.get('main') || []).some(task => task.originalFile.name === name && task.uploadStatus === state), { timeout: 15000 }, name, state);
}

try {
    await page.goto(address, { waitUntil: 'load' });
    await page.waitForFunction(() => document.querySelector('[data-upload-zone]').dataset.initialized === 'true');
    await selectFile('identity', 'document.png');
    await waitUploaded('document.png');
    const documentResult = await page.evaluate(() => {
        const task = window.allDocs.get('main')[0];
        return { status: task.status, unchanged: task.processedFile === task.originalFile, cropCalls: window.cropCalls.length, prompts: window.testPrompts.length, tempPath: task.tempPath };
    });
    assert.deepEqual(documentResult, { status: 'completed', unchanged: true, cropCalls: 0, prompts: 0, tempPath: 'temp_uploads/123456/test.jpg' });
    assert.equal(uploads.length, 1);
    assert.ok(uploads[0].includes(Buffer.from('123456')));

    await selectFile('photo', 'portrait.png');
    await waitUploaded('portrait.png');
    assert.deepEqual(await page.evaluate(() => window.cropCalls), ['image']);

    await selectFile('unknown', 'unclassified.png');
    await waitUploaded('unclassified.png');
    assert.deepEqual(await page.evaluate(() => window.cropCalls), ['image', 'image']);

    await selectFile('identity', 'oversized.png', 'image/png', { noise: true, width: 1800, height: 2200 });
    await waitUploaded('oversized.png');
    const oversized = await page.evaluate(() => {
        const task = window.allDocs.get('main').find(t => t.originalFile.name === 'oversized.png');
        return {
            status: task.status,
            mime: task.processedFile.type,
            size: task.processedFile.size,
            originalSize: task.originalFile.size,
            replaced: task.processedFile !== task.originalFile,
            cropCalls: window.cropCalls.length,
        };
    });
    assert.equal(oversized.status, 'completed');
    assert.ok(oversized.originalSize > 5 * 1024 * 1024, 'synthetic fixture must exceed the server cap');
    assert.equal(oversized.replaced, true);
    assert.equal(oversized.mime, 'image/jpeg');
    assert.ok(oversized.size <= 5 * 1024 * 1024);
    assert.equal(oversized.cropCalls, 2);

    const countBeforePdf = await page.evaluate(() => window.allDocs.get('main').length);
    await selectFile('identity', 'blocked.pdf', 'application/pdf');
    assert.equal(await page.evaluate(() => window.allDocs.get('main').length), countBeforePdf);
    assert.ok((await page.evaluate(() => window.testPrompts)).includes('نوع الملف غير مدعوم'));

    rejectUpload = true;
    await selectFile('identity', 'network-error.png');
    await waitUploaded('network-error.png', 'error');
    assert.equal(await page.evaluate(() => window.cropCalls.length), 2);
    assert.equal(await page.evaluate(() => window.allDocs.get('main').find(task => task.originalFile.name === 'network-error.png').processedFile === window.allDocs.get('main').find(task => task.originalFile.name === 'network-error.png').originalFile), true);
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ passed: ['document-auto-upload', 'original-preserved', 'personal-photo-crop-path', 'unknown-type-crop-path', 'oversized-document-compressed', 'pdf-rejected', 'upload-failure-preserves-original'], browserErrors: errors, uploadRequests: uploads.length }));
    if (process.env.DOCUMENT_TEST_SCREENSHOT) await page.screenshot({ path: process.env.DOCUMENT_TEST_SCREENSHOT, fullPage: true });
} catch (error) {
    console.error(JSON.stringify({ errors, state: await page.evaluate(() => ({ prompts: window.testPrompts, tasks: Array.from(window.allDocs || []), text: document.body.innerText })) }));
    throw error;
} finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
}
