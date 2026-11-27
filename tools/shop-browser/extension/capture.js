(() => {
    'use strict';
    const clean = value => String(value || '').replace(/\s+/g, ' ').trim().slice(0, 300);
    function productUrl(value) {
        try {
            const url = new URL(value, location.href);
            for (const key of [...url.searchParams.keys()]) if (!['product_id','itemCode','tenpo_cd'].includes(key)) url.searchParams.delete(key);
            url.hash = '';
            return url.href;
        } catch (_) { return ''; }
    }
    function visible(node) {
        return !!node.getClientRects().length && !node.closest('[hidden],[inert],[aria-hidden="true"]') &&
            getComputedStyle(node).visibility !== 'hidden' && getComputedStyle(node).display !== 'none';
    }
    function control(node) {
        return {tag: node.tagName.toLowerCase(), id: clean(node.id), class: clean(node.className),
            text: clean(node.textContent), visible: visible(node),
            disabled: node.matches(':disabled') || node.hasAttribute('disabled'),
            ariaDisabled: !!node.closest('[aria-disabled="true"]'),
            classDisabled: !!node.closest('.disabled,.disable'),
            pointerEvents: getComputedStyle(node).pointerEvents,
            ancestors: [node.parentElement, node.parentElement?.parentElement].filter(Boolean).map(p => ({tag:p.tagName.toLowerCase(), id:clean(p.id), class:clean(p.className)}))};
    }
    function elements(selector) {
        return [...document.querySelectorAll(selector)].filter(node =>
            !node.matches('script,style,input,textarea,select') &&
            !node.closest('form[action*="login"],header,nav,footer') &&
            node.getClientRects().length && node.textContent.trim().length <= 300
        ).slice(0, 60).map(node => ({
            tag: node.tagName.toLowerCase(), id: clean(node.id), class: clean(node.className),
            text: clean(node.textContent),
            value: node.getAttribute('itemprop') === 'price' ? clean(node.getAttribute('content')) : undefined
        }));
    }
    function products(value, output = [], depth = 0) {
        if (!value || typeof value !== 'object' || depth > 12 || output.length >= 8) return output;
        if ([value['@type']].flat().includes('Product')) {
            output.push({name: clean(value.name), sku: clean(value.sku), url: value.url ? productUrl(value.url) : '',
                offers: [value.offers || []].flat().slice(0, 12).map(offer => ({
                    url: offer.url ? productUrl(offer.url) : '',
                    price: clean(offer.price), priceCurrency: clean(offer.priceCurrency),
                    availability: clean(offer.availability), seller: clean(offer.seller?.name)
                }))});
        } else {
            for (const child of Object.values(value)) products(child, output, depth + 1);
        }
        return output;
    }
    async function capture() {
        const structured = [];
        for (const node of document.querySelectorAll('script[type="application/ld+json"]')) {
            if (node.textContent.length > 300000) continue;
            try { products(JSON.parse(node.textContent), structured); } catch (_) { /* Invalid shop JSON is not evidence. */ }
        }
        const snapshot = {
            schemaVersion: 2,
            canonical: productUrl(document.querySelector('link[rel="canonical"]')?.href || location.href),
            title: clean(document.title), headings: elements('h1'),
            prices: elements('[class*="price" i],[id*="price" i],[itemprop="price"]'),
            stock: elements('[class*="stock" i],[id*="stock" i],[class*="zaiko" i],[class*="inventory" i],[itemprop="availability"]'),
            purchase: elements('button,[role="button"],a[class*="cart" i]').filter(node => /cart|\u30ab\u30fc\u30c8|\u5728\u5eab|\u54c1\u5207|\u58f2\u308a\u5207/i.test(node.text)),
            structured,
            challenge: !!document.querySelector('#challenge-running,#challenge-stage,form#challenge-form'),
        };
        if (/(^|\.)suruga-ya\.jp$/.test(location.hostname)) {
            snapshot.suruga = {
                buttons: [...document.querySelectorAll('button.btn_buy.cart1')].slice(0, 12).map(control),
                prices: [...document.querySelectorAll('.price-buy')].filter(visible).slice(0, 12).map(control),
                soldOut: [...document.querySelectorAll('p,div,span,strong')].filter(node =>
                    visible(node) && /^(?:\u7533\u3057\u8a33\u3054\u3056\u3044\u307e\u305b\u3093\u3002\s*)?\u54c1\u5207\u308c\u4e2d\u3067\u3059\u3002?$/.test(clean(node.textContent)) &&
                    ![...node.children].some(child => clean(child.textContent) === clean(node.textContent))
                ).slice(0, 8).map(control)
            };
        }
        try { await chrome.runtime.sendMessage({kind: 'product-dom', snapshot}); } catch (_) { /* Browser may be closing. */ }
    }
    capture();
    setInterval(capture, 5000);
})();
