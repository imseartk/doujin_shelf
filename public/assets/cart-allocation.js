(function () {
    'use strict';
    const run = document.getElementById('cart-plan-run');
    if (!run) return;
    const rows = [...document.querySelectorAll('.js-cart-shop-row')];
    const items = [...document.querySelectorAll('.js-cart-item')];
    const status = document.getElementById('cart-plan-status');
    const output = document.getElementById('cart-plan-result');
    const apply = document.getElementById('cart-plan-apply');
    const undo = document.getElementById('cart-plan-undo');
    const cancel = document.getElementById('cart-plan-cancel');
    const money = value => '\u00a5' + value.toLocaleString();
    let worker, deadline, result, snapshot;
    function stop() {
        if (worker) worker.terminate();
        worker = null;
        clearTimeout(deadline);
        run.disabled = !!snapshot;
        cancel.hidden = true;
    }
    function invalidate() {
        stop();
        result = null;
        output.replaceChildren();
        apply.hidden = true;
        status.textContent = snapshot ? '清單已變更。重新運算前請先還原套用前清單。' : '條件已變更，請重新運算。';
    }
    document.addEventListener('input', event => {
        if (event.target.matches('.js-plan-shop, .js-plan-minimum')) invalidate();
    });
    document.addEventListener('click', event => {
        if (event.target.closest('.js-cart-remove, .js-cart-restore')) invalidate();
    });
    for (const [id, checked] of [['cart-plan-all', true], ['cart-plan-none', false]]) {
        document.getElementById(id).addEventListener('click', () => {
            rows.forEach(row => { row.querySelector('.js-plan-shop').checked = checked; });
            invalidate();
        });
    }
    cancel.addEventListener('click', () => { stop(); status.textContent = '已取消運算。'; });
    run.addEventListener('click', () => {
        invalidate();
        const shops = rows.filter(row => row.querySelector('.js-plan-shop').checked).map(row => ({
            id: row.dataset.shopId,
            name: row.querySelector('.cart-shop-name').textContent,
            minimum: Number(row.querySelector('.js-plan-minimum').value)
        }));
        if (shops.some(shop => !Number.isSafeInteger(shop.minimum) || shop.minimum < 0)) {
            status.textContent = '最低金額請填入 0 或正整數。'; return;
        }
        const sources = items.filter(item => !item.hidden).map(item => ({
            id: item.dataset.sourceId, bookId: item.dataset.bookId,
            shopId: item.closest('.js-cart-shop-row').dataset.shopId,
            title: item.querySelector('.cart-title').textContent,
            price: item.dataset.price === '' ? null : Number(item.dataset.price)
        }));
        const shopName = id => shops.find(shop => shop.id === id)?.name || id;
        status.textContent = '運算中…';
        run.disabled = true;
        cancel.hidden = false;
        try {
            worker = new Worker('/assets/cart-allocation-worker.js?v=1');
            worker.onerror = () => { stop(); status.textContent = '運算載入失敗，請重新整理後再試。'; };
            worker.onmessage = event => {
                stop();
                const answer = event.data;
                if (answer.status === 'empty') { status.textContent = '請選擇店家並保留至少一本書。'; return; }
                if (answer.status === 'missing') {
                    const names = answer.books.map(id => sources.find(source => source.bookId === id)?.title || id);
                    status.textContent = '以下書籍沒有可用價格，請補上價格或移除來源：' + names.join('、'); return;
                }
                if (answer.status === 'minimum') {
                    status.textContent = shopName(answer.shopId) + ' 最多只能湊到 ' + money(answer.maximum) + '，無法達到最低金額。'; return;
                }
                if (answer.status === 'infeasible') {
                    status.textContent = '無可行組合：每本只買一本時，無法同時滿足各店最低金額。'; return;
                }
                if (answer.status !== 'optimal') { status.textContent = '運算失敗，未更動清單。'; return; }
                result = answer;
                status.textContent = answer.chosen.length + ' 本，最低商品總額 ' + money(answer.total) + '（不含運費）。尚未套用。';
                shops.forEach(shop => {
                    const chosen = answer.chosen.filter(source => source.shopId === shop.id);
                    const details = document.createElement('details');
                    details.open = true;
                    const summary = document.createElement('summary');
                    summary.textContent = shop.name + ' · ' + chosen.length + ' 本 · ' + money(answer.totals[shop.id]) +
                        (shop.minimum ? ' / 最低 ' + money(shop.minimum) + '（已達標）' : '');
                    details.append(summary);
                    const list = document.createElement('ul');
                    chosen.forEach(source => {
                        const li = document.createElement('li');
                        li.textContent = source.title + ' — ' + money(source.price);
                        list.append(li);
                    });
                    details.append(list);
                    output.append(details);
                });
                apply.hidden = false;
            };
            deadline = setTimeout(() => {
                stop();
                status.textContent = '運算超過 30 秒，尚未確認最佳解。請減少店家或品項後再試；清單未變更。';
            }, 30000);
            worker.postMessage({ shops, sources });
        } catch (error) { stop(); status.textContent = '此瀏覽器無法啟動運算，清單未變更。'; }
    });
    function setHidden(item, hidden) {
        item.hidden = hidden;
        item.querySelector('.js-cart-book-id').disabled = hidden;
    }
    apply.addEventListener('click', () => {
        if (!result) return;
        snapshot = items.map(item => item.hidden);
        const chosen = new Set(result.chosen.map(source => source.id));
        items.forEach(item => setHidden(item, !chosen.has(item.dataset.sourceId)));
        document.dispatchEvent(new Event('cart:allocation-applied'));
        apply.hidden = true;
        undo.hidden = false;
        run.disabled = true;
        status.textContent = '已套用組合，可逐店建立訂單。尚未送出任何訂單。';
    });
    undo.addEventListener('click', () => {
        if (!snapshot) return;
        items.forEach((item, index) => setHidden(item, snapshot[index]));
        snapshot = null;
        undo.hidden = true;
        invalidate();
        document.dispatchEvent(new Event('cart:allocation-applied'));
        status.textContent = '已還原套用前清單。';
    });
})();
