const puppeteer = require('puppeteer');

const BASE = 'http://localhost/alhayahorphans/public';
const sleep = ms => new Promise(r => setTimeout(r, ms));

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
    await page.type('#email', 'admin@gmail.com');
    await page.type('#password', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
        page.click('button[type=submit]')
    ]);
    console.log('after login url:', page.url());

    // ---- intercept bulk status endpoints ----
    await page.setRequestInterception(true);
    page.on('request', req => {
        const u = req.url();
        if (u.includes('bulk-update-status') && req.method() === 'POST') {
            const data = req.postData() || '';
            const isPreview = data.includes('preview');
            const body = isPreview
                ? { success: true, preview: true, total: 12, to_update: 7, unchanged: 5, filters: [] }
                : { success: true, message: 'تم تحديث 7 كفالات', summary: { updated: 7, unchanged: 5 } };
            req.respond({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
            return;
        }
        req.continue();
    });

    await page.goto(BASE + '/admin/sponsorships/sponsored', { waitUntil: 'networkidle2' });
    await sleep(2500);

    // ---- instrument bootstrap Modal ----
    await page.evaluate(() => {
        window.__log = [];
        const t0 = performance.now();
        const P = bootstrap.Modal.prototype;
        ['show', 'hide', '_showElement', '_hideModal'].forEach(name => {
            const orig = P[name];
            P[name] = function (...args) {
                window.__log.push([Math.round(performance.now() - t0), name, this._element.id,
                    'isShown=' + this._isShown, 'isTrans=' + this._isTransitioning,
                    'display=' + this._element.style.display,
                    'cls=' + this._element.className]);
                return orig.apply(this, args);
            };
        });
        const BD = bootstrap.Backdrop ? null : null;
        const obs = new MutationObserver(() => {
            window.__log.push([Math.round(performance.now() - t0), 'MUT', 'display=' + document.getElementById('bulkStatusModal').style.display + ' cls=' + document.getElementById('bulkStatusModal').className]);
        });
        obs.observe(document.getElementById('bulkStatusModal'), { attributes: true, attributeFilter: ['style', 'class'] });
    });

    const duplicates = await page.evaluate(() => ({
        modalCount: document.querySelectorAll('#bulkStatusModal').length,
        backdropCount: document.querySelectorAll('.modal-backdrop').length,
        backdropParents: Array.from(document.querySelectorAll('.modal-backdrop')).map(b => b.parentElement.tagName + '.' + b.parentElement.className),
        modalParents: Array.from(document.querySelectorAll('[id="bulkStatusModal"]')).map(m => m.parentElement.tagName + '.' + m.parentElement.className)
    }));
    console.log('DOM:', JSON.stringify(duplicates, null, 2));

    const dump = async (label) => {
        const s = await page.evaluate((l) => {
            const el = document.getElementById('bulkStatusModal');
            const inst = bootstrap.Modal.getInstance(el);
            return {
                label: l,
                display: el.style.display,
                cls: el.className,
                isShown: inst && inst._isShown,
                isTrans: inst && inst._isTransitioning,
                backdropInDom: document.querySelectorAll('.modal-backdrop').length,
                backdropAppended: inst && inst._backdrop._isAppended,
                log: window.__log
            };
        }, label);
        console.log('=== ' + label + ' ===');
        console.log(JSON.stringify(s, null, 2));
        window_last = s;
        return s;
    };

    // ---- first open ----
    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(900);
    await dump('FIRST OPEN');

    await page.evaluate(() => { window.__log.length = 0; });

    // ---- pick status ----
    await page.evaluate(() => {
        const sel = document.getElementById('bulk_status_id');
        const opt = Array.from(sel.options).find(o => o.value !== '');
        if (opt) { sel.value = opt.value; jQuery(sel).trigger('change'); }
    });
    await sleep(1500);

    // ---- submit + confirm ----
    await page.evaluate(() => document.getElementById('bulk_status_submit_btn').click());
    await sleep(700);
    await page.evaluate(() => { const b = document.querySelector('.swal2-confirm'); if (b) b.click(); });
    await sleep(4500);
    await dump('AFTER SUCCESS SWAL CLOSED');

    await page.evaluate(() => { window.__log.length = 0; });

    // ---- second open ----
    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(1500);
    const after = await dump('SECOND OPEN');

    const centers = await page.evaluate(() => {
        const el = document.elementFromPoint(window.innerWidth / 2, window.innerHeight / 2);
        return { center: el && (el.tagName + '.' + el.className), modalDisplay: document.getElementById('bulkStatusModal').style.display };
    });
    console.log('CENTER:', JSON.stringify(centers));

    await page.screenshot({ path: 'live2-second-open.png' });
    await browser.close();
})();
