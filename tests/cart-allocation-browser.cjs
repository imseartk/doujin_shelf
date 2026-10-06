// Run with NODE_PATH pointing to a Playwright installation.
const { chromium } = require('playwright');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
(async () => {
    const jquery = await (await fetch('https://code.jquery.com/jquery-3.7.1.min.js')).text();
    const php = `define('FCPATH', getcwd().'/public/');
function esc($s){return htmlspecialchars((string)$s, ENT_QUOTES);}
function csrf_field(){return '';}
function cover_display_url($s){return $s;}
class Fixture {
function extend($s){} function section($s){} function endSection(){}
function render(){
$shops=[];
foreach ([1,2] as $id) {
$items=[];
foreach ([1,2] as $book) $items[]=['source_id'=>$id*10+$book,'book_id'=>$book,'price'=>$id===1?100:120,'title'=>'Test book '.$book,'cover_url'=>'','item_url'=>''];
$shops[]=['id'=>$id,'name'=>'Shop '.$id,'website_url'=>'','total_price'=>200,'items'=>$items];
}
include 'app/Views/sources/index.php';
}}
(new Fixture)->render();`;
    const view = execFileSync('php', ['-r', php], { encoding:'utf8' });
    const server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            res.setHeader('Content-Type','text/html; charset=utf-8');
            res.end('<meta name="viewport" content="width=device-width,initial-scale=1"><script src="/jquery.js"></script>'+view);
        } else if (url.pathname === '/jquery.js') { res.setHeader('Content-Type','text/javascript'); res.end(jquery); }
        else if (url.pathname.startsWith('/assets/')) {
            const file = path.join(process.cwd(),'public',url.pathname);
            res.setHeader('Content-Type','text/javascript'); res.end(fs.readFileSync(file));
        } else { res.statusCode=404; res.end(); }
    });
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
    let browser;
    try {
        browser = await chromium.launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || 'msedge'});
        const page = await browser.newPage();
        const errors=[];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('http://127.0.0.1:'+server.address().port);
        await page.click('#cart-plan-all');
        await page.locator('.js-plan-minimum').nth(1).fill('120');
        await page.click('#cart-plan-run');
        await page.waitForFunction(() => !document.querySelector('#cart-plan-apply').hidden);
        assert.match(await page.locator('#cart-plan-status').textContent(), /220/);
        await page.click('#cart-plan-apply');
        assert.equal(await page.locator('.js-cart-item:not([hidden])').count(),2);
        assert.equal(await page.locator('.js-cart-book-id:not([disabled])').count(),2);
        await page.click('#cart-plan-undo');
        assert.equal(await page.locator('.js-cart-item:not([hidden])').count(),4);
        await page.locator('.js-cart-remove').first().click();
        await page.click('#cart-plan-run');
        await page.waitForFunction(() => !document.querySelector('#cart-plan-apply').hidden);
        await page.locator('.js-plan-minimum').nth(1).fill('999');
        assert.equal(await page.locator('#cart-plan-apply').isVisible(),false);
        await page.click('#cart-plan-run');
        await page.waitForFunction(() => document.querySelector('#cart-plan-status').textContent.includes('無法達到'));
        await page.setViewportSize({width:390,height:844});
        await page.screenshot({path:require('node:os').tmpdir()+'/cart-planner-mobile.png'});
        assert.deepEqual(errors,[]);
        console.log('Browser preview/apply/undo/remove/invalidate/infeasible checks passed.');
    } finally {
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => { console.error(error); process.exitCode=1; });
