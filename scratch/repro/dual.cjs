const puppeteer = require('puppeteer');
const BASE = 'http://localhost/alhayahorphans/public';
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function run(blockCdn) {
    const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'], defaultViewport: { width: 1600, height: 1000 } });
    const page = await browser.newPage();
    page.on('pageerror', e => console.log('PAGEERROR:', e.message));

    await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
    await page.type('#email', 'admin@gmail.com');
    await page.type('#password', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), page.click('button[type=submit]')]);

    await page.setRequestInterception(true);
    page.on('request', req => {
        if (blockCdn && req.url().includes('cdn.jsdelivr.net/npm/bootstrap')) { req.abort(); return; }
        if (req.url().includes('bulk-update-status') && req.method() === 'POST') {
            const isPreview = (req.postData() || '').includes('preview');
            const body = isPreview
                ? { success: true, preview: true, total: 12, to_update: 7, unchanged: 5, filters: [] }
                : { success: true, message: 'تم', summary: { updated: 7, unchanged: 5 } };
            req.respond({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
            return;
        }
        req.continue();
    });

    await page.goto(BASE + '/admin/sponsorships/sponsored', { waitUntil: 'networkidle2' });
    await sleep(2500);

    const info = await page.evaluate(() => ({
        hasBootstrapGlobal: typeof window.bootstrap !== 'undefined',
        jqModalIsBootstrap: !!(window.jQuery && jQuery.fn.modal && window.bootstrap && jQuery.fn.modal.Constructor === bootstrap.Modal)
    }));
    console.log('blockCdn=' + blockCdn, JSON.stringify(info));

    // open
    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(1000);
    const st1 = await page.evaluate(() => ({
        backdrops: document.querySelectorAll('.modal-backdrop').length,
        display: document.getElementById('bulkStatusModal').style.display,
        cls: document.getElementById('bulkStatusModal').className
    }));
    console.log('  after open:', JSON.stringify(st1));

    // pick status + submit + confirm (fake ajax)
    await page.evaluate(() => {
        const sel = document.getElementById('bulk_status_id');
        const opt = Array.from(sel.options).find(o => o.value !== '');
        if (opt) { sel.value = opt.value; jQuery(sel).trigger('change'); }
    });
    await sleep(1200);
    await page.evaluate(() => document.getElementById('bulk_status_submit_btn').click());
    await sleep(700);
    await page.evaluate(() => { const b = document.querySelector('.swal2-confirm'); if (b) b.click(); });
    await sleep(4500);
    const st2 = await page.evaluate(() => ({
        backdrops: document.querySelectorAll('.modal-backdrop').length,
        display: document.getElementById('bulkStatusModal').style.display,
        cls: document.getElementById('bulkStatusModal').className
    }));
    console.log('  after success:', JSON.stringify(st2));

    // second open
    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(1500);
    const st3 = await page.evaluate(() => {
        const el = document.getElementById('bulkStatusModal');
        const inst = bootstrap.Modal.getInstance(el);
        const center = document.elementFromPoint(innerWidth / 2, innerHeight / 2);
        return {
            backdrops: document.querySelectorAll('.modal-backdrop').length,
            display: el.style.display,
            cls: el.className,
            isShown: inst && inst._isShown,
            center: center && (center.tagName + '.' + center.className)
        };
    });
    console.log('  second open:', JSON.stringify(st3));
    console.log('  => ', st3.display === 'block' && st3.center.includes('modal') ? 'DIALOG VISIBLE' : '*** DIM WITHOUT DIALOG ***');
    await browser.close();
}

(async () => {
    await run(false);
    console.log('------------------------------------');
    await run(true);
})();
