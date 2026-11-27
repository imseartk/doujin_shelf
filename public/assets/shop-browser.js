(() => {
    'use strict';
    const form = document.querySelector('#browser-form');
    if (!form) return;
    const site = document.querySelector('#browser-site');
    const product = document.querySelector('#browser-product');
    const options = [...product.options].map(option => option.cloneNode(true));
    const canvas = document.querySelector('#browser-screen');
    const ctx = canvas.getContext('2d');
    const status = document.querySelector('#browser-status');
    const result = document.querySelector('#browser-result');
    const read = document.querySelector('#browser-read');
    const close = document.querySelector('#browser-close');
    let active = false, polling = false, busy = false, chain = Promise.resolve(), dragging = false, lastMove = 0;
    function filter() {
        product.replaceChildren(...options.filter(option => option.dataset.site === site.value).map(option => option.cloneNode(true)));
        document.querySelector('#browser-open').disabled = !product.options.length;
    }
    site.addEventListener('change', filter);
    filter();
    function command(data) {
        const request = async () => {
            const csrf = form.querySelector('input[name*="csrf"]');
            const body = new FormData();
            body.append(csrf.name, csrf.value);
            for (const [key, value] of Object.entries(data)) body.append(key, value);
            const response = await fetch('/tools/shop-browser/command', {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest'}});
            const value = await response.json();
            if (value.csrf) csrf.value = value.csrf;
            if (!response.ok || value.error) throw new Error(value.error || '管理登入已逾時，請重新整理');
            return value;
        };
        const next = chain.then(request);
        chain = next.catch(() => {});
        return next;
    }
    function fail(error) { status.textContent = error.message; }
    async function frame() {
        if (!active || polling || busy || document.hidden) return;
        polling = true;
        try {
            const response = await fetch('/tools/shop-browser/frame', {cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok || !response.headers.get('content-type')?.startsWith('image/jpeg')) throw new Error('畫面已中斷或閒置逾時，請重新開啟商品');
            const bitmap = await createImageBitmap(await response.blob());
            ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            bitmap.close();
        } catch (error) { active = false; read.disabled = true; fail(error); }
        finally { polling = false; }
    }
    setInterval(frame, 1200);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !product.value) return;
        busy = true; active = false; read.disabled = true; result.hidden = true;
        status.textContent = '正在開啟 AWS 瀏覽器…';
        try {
            const value = await command({action:'start', source_id:product.value});
            active = true; read.disabled = value.read_supported === false; close.disabled = false;
            status.textContent = (value.warning ? '載入尚未完成，請查看畫面：' : 'AWS 瀏覽器：') + value.url;
            if (value.mode === 'native') {
                status.textContent = 'AWS Chrome（人工操作、DOM 唯讀擷取）：' + value.url;
            }
        } catch (error) { fail(error); }
        finally { busy = false; frame(); }
    });
    const labels = {available:'有貨', unavailable:'缺貨', verification:'仍需要驗證', identity:'無法核對商品或分店', seller:'缺少分店識別', unknown:'尚無法辨識價格與庫存', unsupported:'尚未支援的網址'};
    labels.dom_observed = '已擷取 DOM 候選元素（尚未判定價格或庫存）';
    labels.dom_pending = '尚未收到此商品的新 DOM，請待頁面載入後重試';
    const reasons = {
        active_cart_and_offer:'主商品購物車按鈕可用，庫存資料一致',
        main_cart_sold_out:'主商品明確顯示缺貨，庫存資料一致',
        conflicting_stock:'按鈕與庫存資料互相矛盾',
        insufficient_stock:'缺少可確認的庫存證據，不視為缺貨',
        ambiguous_price:'商品價格不唯一或與結構化資料不一致',
        old_snapshot:'需要新版 DOM 擷取資料'
    };
    read.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        try {
            const value = await command({action:'read'});
            result.hidden = false;
            result.textContent = (labels[value.result.status] || value.result.status) +
                (value.result.price !== undefined ? ' · 原價 ' + (value.old_price === null ? '未填' : '¥'+value.old_price) + ' → 讀取價格 ¥'+value.result.price : '') + ' · 未寫入資料庫';
            status.textContent = '目前頁面：' + value.url;
            if (value.result.reason && reasons[value.result.reason]) {
                result.append(document.createTextNode(' · ' + reasons[value.result.reason]));
            }
            if (value.snapshot) {
                const details = document.createElement('details');
                details.open = true;
                const summary = document.createElement('summary');
                summary.textContent = 'DOM 擷取結果';
                const pre = document.createElement('pre');
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.overflowWrap = 'anywhere';
                pre.style.maxHeight = '28rem';
                pre.style.overflow = 'auto';
                pre.textContent = JSON.stringify(value.snapshot, null, 2);
                details.append(summary, pre);
                result.append(details);
            }
            if (value.diagnostics) {
                const details = document.createElement('details');
                const summary = document.createElement('summary');
                summary.textContent = 'Connection diagnostics';
                const pre = document.createElement('pre');
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.overflowWrap = 'anywhere';
                pre.textContent = JSON.stringify(value.diagnostics, null, 2);
                details.append(summary, pre);
                result.append(details);
            }
        } catch (error) { fail(error); }
        finally { busy = false; }
    });
    close.addEventListener('click', async () => {
        active = false;
        try { await command({action:'close'}); status.textContent = '已關閉瀏覽器，平台登入狀態保留'; }
        catch (error) { fail(error); }
        read.disabled = close.disabled = true;
        ctx.clearRect(0,0,canvas.width,canvas.height);
    });
    function input(data) {
        if (active && !busy) command({action:'input', ...data}).then(frame).catch(fail);
    }
    function point(event) {
        const rect = canvas.getBoundingClientRect();
        return {x:Math.max(0,Math.min(1199,(event.clientX-rect.left)*1200/rect.width)), y:Math.max(0,Math.min(799,(event.clientY-rect.top)*800/rect.height))};
    }
    canvas.addEventListener('pointerdown', event => {
        if (!active || busy) return;
        canvas.focus(); canvas.setPointerCapture(event.pointerId); dragging = true;
        input({kind:'down', ...point(event)});
    });
    canvas.addEventListener('pointermove', event => {
        if (dragging && Date.now()-lastMove > 100) { lastMove=Date.now(); input({kind:'move', ...point(event)}); }
    });
    for (const eventName of ['pointerup','pointercancel']) canvas.addEventListener(eventName, event => {
        if (dragging) input({kind:'up', ...point(event)});
        dragging = false;
    });
    canvas.addEventListener('wheel', event => { if (active) { event.preventDefault(); input({kind:'wheel',delta:event.deltaY}); } }, {passive:false});
    canvas.addEventListener('keydown', event => {
        if (!active || event.isComposing) return;
        if (event.ctrlKey && event.key.toLowerCase()==='a') { event.preventDefault(); input({kind:'key',key:'Control+A'}); }
        else if (['Enter','Tab','Backspace','Delete','Escape','ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(event.key)) {
            event.preventDefault(); input({kind:'key',key:event.shiftKey && event.key==='Tab' ? 'Shift+Tab' : event.key});
        } else if (event.key.length===1 && !event.ctrlKey && !event.metaKey) { event.preventDefault(); input({kind:'text',text:event.key}); }
    });
    document.querySelectorAll('[data-key]').forEach(button => button.addEventListener('click', () => input({kind:'key',key:button.dataset.key})));
    document.querySelector('#browser-text-form').addEventListener('submit', event => {
        event.preventDefault();
        const field = document.querySelector('#browser-text');
        input({kind:'text',text:field.value}); field.value='';
    });
})();
