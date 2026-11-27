const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({headless:true, channel:'chrome'});
  const out = path.resolve('artifacts/c109');
  fs.mkdirSync(out,{recursive:true});
  for (const [width,height] of [[1440,1000],[390,844]]) {
    for (const kind of ['circles','map']) {
      const page = await browser.newPage({viewport:{width,height}});
      await page.route('**/*', route => route.request().method()==='GET' ? route.continue() : route.abort());
      const html=fs.readFileSync(path.join(out,kind+'.html'),'utf8').replace('<head>','<head><base href="https://doujin.artick.tw/">');
      await page.setContent(html,{waitUntil:'networkidle'});
      assert.ok((await page.locator('body').innerText()).includes('C109'));
      if(kind==='circles') {
        assert.equal(await page.locator('.js-c108-bind-day option[value="3"]').count(),1);
        assert.ok(await page.locator('a[href^="/c109/map?"]').count()>0);
      } else {
        assert.equal(await page.locator('.js-c108-map-day').inputValue(),'3');
        assert.ok(await page.locator('.c108-map-image').evaluate(el=>el.complete && el.naturalWidth>0));
        assert.ok(await page.locator('.js-c108-map-marker').count()>0);
        await page.locator('.js-c108-map-marker').first().click({force:true});
        assert.ok(await page.locator('.js-c108-map-modal').isVisible());
        await page.locator('.js-c108-map-modal-close').last().click();
      }
      await page.screenshot({path:path.join(out,`${kind}-${width}.png`)});
      await page.close();
    }
  }
  await browser.close();
  console.log('C109 desktop/mobile checks passed.');
})().catch(e=>{console.error(e);process.exit(1)});
