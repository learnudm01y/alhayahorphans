const puppeteer = require('puppeteer');
const BASE = 'http://localhost/alhayahorphans/public';
const sleep = ms => new Promise(r => setTimeout(r, ms));

(async () => {
    const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'], defaultViewport: { width: 1600, height: 1000 } });
    const page = await browser.newPage();
    page.on('pageerror', e => console.log('PAGEERROR:', e.message));

    await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
    await page.type('#email', 'admin@gmail.com');
    await page.type('#password', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), page.click('button[type=submit]')]);

    await page.goto(BASE + '/admin/sponsorships/sponsored', { waitUntil: 'networkidle2' });
    await sleep(2500);

    await page.evaluate(() => {
        window.__log = [];
        const P = bootstrap.Modal.prototype;
        ['show', 'hide', 'toggle', '_showElement', '_hideModal'].forEach(name => {
            const orig = P[name];
            P[name] = function (...args) {
                window.__log.push([name, this._element.id || this._element.className, 'isShown=' + this._isShown, 'isTrans=' + this._isTransitioning]);
                return orig.apply(this, args);
            };
        });
        // detect who appends .modal-backdrop
        const origAppend = Element.prototype.appendChild;
        Element.prototype.appendChild = function (child) {
            if (child && child.classList && child.classList.contains('modal-backdrop')) {
                window.__log.push(['APPEND-BACKDROP', 'by=' + (this.tagName + '#' + this.id), 'stack=' + new Error().stack.split('\n').slice(2, 6).join(' | ')]);
            }
            return origAppend.apply(this, arguments);
        };
        const origAppend2 = Element.prototype.append;
        Element.prototype.append = function (...children) {
            children.forEach(child => {
                if (child && child.classList && child.classList.contains('modal-backdrop')) {
                    window.__log.push(['PARENT-APPEND-BACKDROP', 'by=' + (this.tagName + '#' + this.id), 'stack=' + new Error().stack.split('\n').slice(2, 6).join(' | ')]);
                }
            });
            return origAppend2.apply(this, arguments);
        };
        const origInsert = Element.prototype.insertBefore;
        Element.prototype.insertBefore = function (child) {
            if (child && child.classList && child.classList.contains('modal-backdrop')) {
                window.__log.push(['INSERT-BACKDROP', 'by=' + (this.tagName + '#' + this.id), 'stack=' + new Error().stack.split('\n').slice(2, 6).join(' | ')]);
            }
            return origInsert.apply(this, arguments);
        };
    });

    await page.evaluate(() => document.querySelector('[data-bs-target="#bulkStatusModal"]').click());
    await sleep(1000);

    const out = await page.evaluate(() => ({
        log: window.__log,
        modals: Array.from(document.querySelectorAll('.modal')).map(m => ({ id: m.id, cls: m.className })),
        shownModals: Array.from(document.querySelectorAll('.modal.show')).map(m => m.id || m.className),
        backdrops: Array.from(document.querySelectorAll('.modal-backdrop')).map(b => ({
            parent: b.parentElement.tagName + '#' + b.parentElement.id,
            cls: b.className,
            next: b.nextElementSibling && (b.nextElementSibling.tagName + '#' + b.nextElementSibling.id)
        })),
        modalCount: document.querySelectorAll('.modal').length
    }));
    console.log(JSON.stringify(out, null, 2));
    await browser.close();
})();
