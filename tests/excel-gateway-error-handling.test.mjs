import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const bladePath = new URL('../resources/views/admin/file/sidebar-excel-gateway.blade.php', import.meta.url);
const blade = readFileSync(bladePath, 'utf8');
const handlerStart = blade.indexOf('function handleImportResponse(data)');
const handlerEnd = blade.indexOf('// Add hover effects to file input', handlerStart);

assert.notEqual(handlerStart, -1, 'Import response handler must exist');
assert.notEqual(handlerEnd, -1, 'Import response handler boundary must exist');

const handlerSource = blade.slice(handlerStart, handlerEnd);

function renderImportError(payload) {
    let renderedHtml = '';
    const context = {
        console: { log() {} },
        Swal: {
            close() {},
            fire(options) {
                renderedHtml = options.html;
            },
        },
        document: {},
    };

    vm.runInNewContext(`${handlerSource}\nhandleImportResponse(${JSON.stringify(payload)});`, context);

    return renderedHtml;
}

test('a failed import without a message renders a useful fallback instead of undefined', () => {
    const html = renderImportError({ success: false });

    assert.doesNotMatch(html, /undefined/);
    assert.match(html, /تعذّر إكمال الاستيراد/);
});

test('a failed import renders backend error text as escaped text', () => {
    const html = renderImportError({
        success: false,
        message: '<img src=x onerror=alert(1)>',
    });

    assert.doesNotMatch(html, /<img/);
    assert.match(html, /&lt;img src=x onerror=alert\(1\)&gt;/);
});
