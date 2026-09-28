const puppeteer = require('puppeteer');

const BASE = 'http://localhost/alhayahorphans/public';
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function snap(page, label) {
    const s = await page.evaluate((l) => {
        const el = document.getElementById('bulkStatusModal');
        const inst = el ? bootstrap.Modal.getInstance(el) : null;
        const bd = document.querySelectorAll('.modal-backdrop');
        const swals = Array.from(document.querySelectorAll('.swal2-container')).map(c => ({
            cls: c.className,
            bg: getComputedStyle(c).backgroundColor,
            hasPopup: !!c.querySelector('.swal2-popup')
        }));
        const dlg = el && el.querySelector('.modal-dialog');
        return {
            label: l,
            modalFound: !!el,
            modalParent: el ? el.parentElement.tagName + '.' + el.parentElement.className : null,
            modalClasses: el ? el.className : null,
            modalDisplay: el ? el.style.display : null,
            modalOpacity: el ? getComputedStyle(el).opacity : null,
            modalZIndex: el ? getComputedStyle(el).zIndex : null,
            dialogTransform: dlg ? getComputedStyle(dlg).transform : null,
            dialogOpacity: dlg ? getComputedStyle(dlg).opacity : null,
            dialogRect: dlg ? JSON.stringify(dlg.getBoundingClientRect()) : null,
            backdropCount: bd.length,
            backdropShow: bd.length ? bd[0].className : null,
            bodyClass: document.body.className,
            isShown: inst ? inst._isShown : null,
            isTransitioning: inst ? inst._isTransitioning : null,
            backdropAppended: inst ? inst._backdrop._isAppended : null,
            swals
        };
    }, label);
    console.log(JSON.stringify(s, null, 2));
    return s;
}

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox'],
        defaultViewport: { width: 1600, height: 1000 }
    });
    const page = await browser.newPage();

    page.on('pageerror', e => console.log('PAGEERROR:', e.message));
    page.on('console', m => { if (m.type() === 'error') console.log('CONSOLE-ERROR:', m.text()); });

    // ---- login ----
    await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
    if (await page.$('#email')) {
        await page.type('#email', 'admin@gmail.com');
        await page.type('#password', 'password');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
            page.click('button[type=submit]')
        ]);
    }
    console.log('after login url:', page.url());

    // ---- intercept bulk status endpoints (no real data change) ----
    await page.setRequestInterception(true);
    page.on('request', req => {
        const u = req.url();
        if (u.includes('bulk-update-status') && req.method() === 'POST') {
            const data = req.postData() || '';
            const isPreview = data.includes('preview');
            console.log('INTERCEPTED', isPreview ? 'preview' : 'execute', u);
            const body = isPreview
                ? { success: true, preview: true, total: 12, to_update: 7, unchanged: 5, filters: [{ label: 'ط§ظ„ط­ط§ظ„ط©', value: 'ط§ظ†طھط¸ط§ط± ط§ظ„طµط±ظپ' }] }
                : { success: true, message: 'طھظ… طھط­ط¯ظٹط« 7 ظƒظپط§ظ„ط§طھ', summary: { updated: 7, unchanged: 5, status_updated_rows: 0 } };
            req.respond({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
            return;
        }
        req.continue();
    });

    // ---- open page ----
    await page.goto(BASE + '/admin/sponsorships/sponsored', { waitUntil: 'networkidle2' });
    await sleep(2500);
    console.log('page url:', page.url());
    await page.screenshot({ path: 'shot-0-page.png' });

    // ---- open modal ----
    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(900);
    await snap(page, 'FIRST OPEN');
    await page.screenshot({ path: 'shot-1-first-open.png' });

    // ---- pick a status ----
    const statusValue = await page.evaluate(() => {
        const sel = document.getElementById('bulk_status_id');
        const opt = Array.from(sel.options).find(o => o.value !== '');
        if (opt) { sel.value = opt.value; jQuery(sel).trigger('change'); return opt.value; }
        return null;
    });
    console.log('status value:', statusValue);
    await sleep(1500);
    await page.screenshot({ path: 'shot-2-preview.png' });

    // ---- submit + confirm ----
    await page.evaluate(() => document.getElementById('bulk_status_submit_btn').click());
    await sleep(700);
    await page.screenshot({ path: 'shot-3-confirm.png' });
    await page.evaluate(() => { const b = document.querySelector('.swal2-confirm'); if (b) b.click(); });
    await sleep(1200);
    await snap(page, 'AFTER CONFIRM (success swal)');
    await page.screenshot({ path: 'shot-4-success.png' });

    // ---- wait for swal timer + cleanup ----
    await sleep(3500);
    await snap(page, 'AFTER SUCCESS SWAL CLOSED');
    await page.screenshot({ path: 'shot-5-after-cleanup.png' });

    // ---- second open ----
    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(1200);
    await snap(page, 'SECOND OPEN');
    await page.screenshot({ path: 'shot-6-second-open.png' });

    // what is on top?
    const top = await page.evaluate(() => {
        const el = document.elementFromPoint(window.innerWidth / 2, window.innerHeight / 2);
        let path = [];
        let n = el;
        while (n && path.length < 6) { path.push(n.tagName + (n.id ? '#' + n.id : '') + (n.className && typeof n.className === 'string' ? '.' + n.className.split(' ').join('.') : '')); n = n.parentElement; }
        return path;
    });
    console.log('element at center:', JSON.stringify(top, null, 2));

    await browser.close();
})();

