const puppeteer = require('puppeteer');
const path = require('path');

const url = 'file:///' + path.resolve(__dirname, 'index.html').replace(/\\/g, '/');
const sleep = ms => new Promise(r => setTimeout(r, ms));

const snap = (page, label) => page.evaluate((l) => {
    const swals = Array.from(document.querySelectorAll('.swal2-container')).map(c => ({
        cls: c.className,
        bg: window.getComputedStyle(c).backgroundColor,
        hasPopup: !!c.querySelector('.swal2-popup'),
        popupDisplay: c.querySelector('.swal2-popup') ? window.getComputedStyle(c.querySelector('.swal2-popup')).display : null,
        pe: window.getComputedStyle(c).pointerEvents,
        zIndex: window.getComputedStyle(c).zIndex
    }));
    const st = window.__state(l);
    st.swals = swals;
    st.bodySwal = document.body.className;
    return st;
}, label);

async function full(browser) {
    const page = await browser.newPage();
    await page.goto(url);
    await sleep(300);

    await page.evaluate(() => document.getElementById('openBtn').click());
    await sleep(800);
    await page.evaluate(() => jQuery('#bulkStatusForm').trigger('submit'));

    // confirm dialog appears
    await sleep(500);
    const s1 = await snap(page, 'confirm dialog open');

    // click "نعم" (confirm button = swal2-confirm)
    await page.evaluate(() => {
        const b = document.querySelector('.swal2-confirm');
        if (b) b.click();
    });
    await sleep(400); // ajax simulated: hide + success swal fired synchronously in confirm.then
    const s2 = await snap(page, 'after confirm -> success swal');

    await sleep(3400);
    const s3 = await snap(page, 'after success swal timer closed');

    await page.evaluate(() => document.getElementById('openBtn').click());
    await sleep(900);
    const s4 = await snap(page, 'SECOND OPEN');

    await page.evaluate(() => document.getElementById('openBtn').click());
    await sleep(900);
    const s5 = await snap(page, 'THIRD CLICK');

    console.log(JSON.stringify({ s1, s2, s3, s4, s5 }, null, 2));
    await page.close();
}

(async () => {
    const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
    await full(browser);
    await browser.close();
})();
