'use strict';
chrome.runtime.onMessage.addListener((message, sender, reply) => {
    if (sender.id !== chrome.runtime.id || sender.frameId !== 0 || !sender.tab || message?.kind !== 'product-dom') return;
    const snapshot = message.snapshot;
    if (!snapshot || typeof snapshot !== 'object') return;
    // Use the browser's sender URL, never a page-provided destination.
    snapshot.url = sender.url;
    chrome.runtime.sendNativeMessage('tw.artick.product_dom', snapshot)
        .then(value => reply({ok: value.ok === true}))
        .catch(() => reply({ok: false}));
    return true;
});
