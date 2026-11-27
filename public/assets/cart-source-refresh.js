(function () {
    'use strict';
    const rows = [...document.querySelectorAll('.js-cart-shop-row')];
    if (!rows.length) return;
    let running = false;
    const messages = {
        unsupported: '尚未支援的商品網址', verification: '需要人工確認／驗證，原資料保留',
        network: '連線失敗，原資料保留', http_error: '商品頁未正常回應，原資料保留',
        identity: '無法確認商品或店家相符，原資料保留', unknown: '無法確定售價與庫存，原資料保留',
        seller: '商品網址缺少分店識別，請補上該店專用商品網址',
        rate_limited: '店家限制請求頻率，請稍後再試', redirect: '商品頁發生轉址，請人工確認網址或登入狀態',
        invalid_file: '請選擇小於 4MB 的商品 HTML', missing: '來源已移除或書籍已不在願望清單',
        conflict: '資料已被修改，請重新整理', busy: '店家更新忙碌，請稍後重試'
    };
    const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
    function lock(value) {
        running = value;
        document.querySelectorAll('.js-shop-refresh, .js-source-html').forEach(el => { el.disabled = value; });
    }
    async function refresh(item, file) {
        const form = new FormData();
        const csrf = document.querySelector('input[name*="csrf"]');
        if (csrf) form.append(csrf.name, csrf.value);
        if (file) form.append('product_html', file);
        const response = await fetch('/sources/' + item.dataset.sourceId + '/refresh', {
            method: 'POST', body: form, headers: {'X-Requested-With':'XMLHttpRequest'}
        });
        const data = await response.json();
        if (data.csrf && csrf) document.querySelectorAll('input[type="hidden"]').forEach(input => {
            if (input.name === csrf.name) input.value = data.csrf;
        });
        if (!response.ok) throw new Error('登入或驗證已過期，請重新整理頁面');
        let message = messages[data.status] || '更新失敗，原資料保留';
        if (data.status === 'available') {
            const previous = item.dataset.price;
            item.dataset.price = String(data.price);
            // Existing cart totals use jQuery's cached data as well as DOM attributes.
            if (window.jQuery) window.jQuery(item).data('price', data.price);
            item.querySelector('.cart-price').textContent = '¥' + data.price.toLocaleString();
            message = '有貨：' + (previous === '' ? '未填價格' : '¥' + Number(previous).toLocaleString()) + ' → ¥' + data.price.toLocaleString();
        } else if (data.status === 'unavailable') {
            item.dataset.unavailable = '1';
            item.hidden = true;
            item.querySelector('.js-cart-book-id').disabled = true;
            message = '缺貨，已移出購物車（願望清單保留來源紀錄）';
        }
        item.querySelector('.cart-source-status').textContent = message;
        item.querySelector('.cart-html-import').hidden = ['available','unavailable','unsupported','missing'].includes(data.status);
        if (data.status === 'available' || data.status === 'unavailable') {
            document.dispatchEvent(new Event('cart:sources-updated'));
            document.dispatchEvent(new Event('cart:allocation-applied'));
        }
        return { ...data, message };
    }
    rows.forEach(row => {
        const button = row.querySelector('.js-shop-refresh');
        const stop = row.querySelector('.js-shop-refresh-stop');
        const status = row.querySelector('.cart-refresh-status');
        const log = row.querySelector('.cart-refresh-log');
        if (!button) return;
        let stopped = false;
        stop.addEventListener('click', () => { stopped = true; status.textContent = '本筆完成後停止更新。'; });
        button.addEventListener('click', async () => {
            if (running) return;
            const items = [...row.querySelectorAll('.js-cart-item')].filter(item => item.dataset.unavailable !== '1');
            if (!window.confirm('更新此店全部 ' + items.length + ' 筆來源（含暫時移除的品項）？確認缺貨的來源將移出購物車，紀錄保留在願望清單。')) return;
            stopped = false;
            lock(true);
            stop.hidden = false;
            log.replaceChildren();
            log.hidden = false;
            let completed = 0;
            try {
                for (const item of items) {
                    if (stopped) break;
                    status.textContent = '更新中 ' + (completed + 1) + ' / ' + items.length;
                    const result = await refresh(item);
                    completed++;
                    const entry = document.createElement('li');
                    entry.textContent = item.querySelector('.cart-title').textContent + '：' + result.message;
                    log.append(entry);
                    if (['verification','busy','rate_limited','redirect'].includes(result.status)) {
                        stopped = true;
                        row.querySelectorAll('.cart-html-import').forEach(label => { label.hidden = false; });
                        entry.append('。已停止此店更新；本機驗證不會傳給伺服器，可在商品頁完成驗證後儲存 HTML，再匯入對應品項。');
                        break;
                    }
                    if (completed < items.length) await wait(3200);
                }
                status.textContent = (stopped ? '已停止' : '更新完成') + '：' + completed + ' / ' + items.length;
            } catch (error) {
                status.textContent = '更新中斷：' + error.message + '。已完成的更新保留，未確認品項不變。';
            } finally { lock(false); stop.hidden = true; }
        });
        row.querySelectorAll('.js-source-html').forEach(input => input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file || running) return;
            if (file.size > 4 * 1024 * 1024) { status.textContent = messages.invalid_file; input.value = ''; return; }
            lock(true);
            try {
                const result = await refresh(input.closest('.js-cart-item'), file);
                status.textContent = result.message;
            } catch (error) { status.textContent = '匯入失敗：' + error.message; }
            finally { input.value = ''; lock(false); }
        }));
    });
})();
