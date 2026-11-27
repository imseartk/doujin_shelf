(() => {
    'use strict';
    const dialog = document.getElementById('circle-books-dialog');
    if (!dialog) return;
    const content = dialog.querySelector('.circle-books-content');
    let request;
    let trigger;
    let previousOverflow;
    document.querySelectorAll('.js-circle-books').forEach(link => {
        link.addEventListener('click', async event => {
            event.preventDefault();
            if (request) request.abort();
            request = new AbortController();
            const current = request;
            trigger = link;
            document.getElementById('circle-books-title').textContent = link.dataset.circleName + ' - 相關書籍';
            content.textContent = '載入中…';
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
            try {
                const response = await fetch(link.href, {
                    signal: current.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok || response.redirected) throw new Error('load');
                const html = await response.text();
                if (!current.signal.aborted) content.innerHTML = html;
            } catch (error) {
                if (error.name !== 'AbortError') content.textContent = '無法載入相關書籍，請關閉後重試；若登入已過期，請重新登入。';
            }
        });
    });
    dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        if (request) request.abort();
        content.replaceChildren();
        document.body.style.overflow = previousOverflow;
        if (trigger) trigger.focus();
    });
})();
