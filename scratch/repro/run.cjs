const puppeteer = require('puppeteer');
const path = require('path');

const url = 'file:///' + path.resolve(__dirname, 'index.html').replace(/\\/g, '/');
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function scenario(browser, name, showDelay) {
    const page = await browser.newPage();
    await page.goto(url);
    await sleep(300);

    // open modal
    await page.evaluate(() => document.getElementById('openBtn').click());
    await sleep(showDelay);

    const mid = await page.evaluate(() => window.__state('after show'));

    // submit -> modal('hide') + SweetAlert success
    await page.evaluate(() => jQuery('#bulkStatusForm').trigger('submit'));
    await sleep(400);
    const during = await page.evaluate(() => window.__state('400ms after hide+swal'));

    // wait for swal timer (3000) + cleanup
    await sleep(3400);
    const afterSwal = await page.evaluate(() => window.__state('after swal closed + cleanup'));

    // click the open button again
    await page.evaluate(() => document.getElementById('openBtn').click());
    await sleep(900);
    const second = await page.evaluate(() => window.__state('SECOND OPEN'));

    console.log(JSON.stringify({ scenario: name, showDelay, mid, during, afterSwal, second }, null, 2));
    await page.close();
}

(async () => {
    const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
    await scenario(browser, 'A-normal', 800);
    await scenario(browser, 'B-fast', 150);
    await browser.close();
})();

