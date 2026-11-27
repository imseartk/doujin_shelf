const { chromium } = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'chrome' });
    try {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.route('https://doujin.artick.tw/circles/*/books', route => route.fulfill({
                contentType: 'text/html', body: fs.readFileSync('artifacts/circle-books-fragment.html', 'utf8')
            }));
            const html = fs.readFileSync('artifacts/circle-books-page.html', 'utf8').replace('<head>', '<head><base href="https://doujin.artick.tw/">');
            await page.setContent(html, { waitUntil: 'networkidle' });
            await page.locator('.js-circle-books').first().click();
            await page.locator('.circle-book').first().waitFor();
            const dialog = page.locator('#circle-books-dialog');
            assert(await dialog.evaluate(el => el.open));
            assert(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth));
            assert(await page.locator('.circle-books-content').evaluate(el => el.scrollWidth <= el.clientWidth));
            assert.equal(await page.locator('.circle-book a, .circle-book button').count(), 0);
            await dialog.screenshot({ path: `artifacts/circle-books-${width}.png` });
            await page.keyboard.press('Escape');
            assert.equal(await dialog.evaluate(el => el.open), false);
            assert(await page.locator('.js-circle-books').first().evaluate(el => el === document.activeElement));
            await page.unroute('https://doujin.artick.tw/circles/*/books');
            await page.route('https://doujin.artick.tw/circles/*/books', route => route.fulfill({status: 500, body: ''}));
            await page.locator('.js-circle-books').first().click();
            await page.getByText('無法載入相關書籍', {exact:false}).waitFor();
            await dialog.locator('[data-close]').click();
            await page.close();
            console.log(`Modal ${width}px: layout, read-only, Escape/focus and error state passed`);
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
