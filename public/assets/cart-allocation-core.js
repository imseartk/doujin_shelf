(function (root) {
    'use strict';
    function allocate(shops, sources, solve) {
        const selected = new Map(shops.map(shop => [String(shop.id), shop]));
        if (!selected.size) return { status: 'empty' };
        for (const shop of shops) {
            if (!Number.isSafeInteger(shop.minimum) || shop.minimum < 0) throw new Error('Invalid minimum');
        }
        const books = new Map();
        sources.filter(source => selected.has(String(source.shopId))).forEach(source => {
            const id = String(source.bookId);
            if (!books.has(id)) books.set(id, []);
            if (Number.isSafeInteger(source.price) && source.price >= 0) books.get(id).push(source);
        });
        const missing = [...books].filter(([, options]) => !options.length).map(([id]) => id);
        if (missing.length) return { status: 'missing', books: missing };
        if (!books.size) return { status: 'empty' };
        const model = { optimize: 'cost', opType: 'min', constraints: {}, variables: {}, binaries: {} };
        const options = [];
        books.forEach((offers, id) => {
            model.constraints['book_' + id] = { equal: 1 };
            offers.forEach(offer => {
                const key = 'offer_' + options.length;
                options.push(offer);
                model.variables[key] = { cost: offer.price, ['book_' + id]: 1, ['shop_' + offer.shopId]: offer.price };
                model.binaries[key] = 1;
            });
        });
        for (const shop of shops) {
            // A book can contribute only once, even when it has several sources in a shop.
            const maximum = [...books.values()].reduce((sum, offers) => sum + Math.max(0,
                ...offers.filter(offer => String(offer.shopId) === String(shop.id)).map(offer => offer.price)), 0);
            if (maximum < shop.minimum) return { status: 'minimum', shopId: String(shop.id), maximum };
            model.constraints['shop_' + shop.id] = { min: shop.minimum };
        }
        // No approximate tolerance or internal timeout: a worker deadline cancels unfinished solves.
        const result = solve(model);
        if (!result.feasible) return { status: 'infeasible' };
        const chosen = options.filter((offer, index) => result['offer_' + index] > 0.5);
        const totals = Object.fromEntries(shops.map(shop => [String(shop.id), 0]));
        chosen.forEach(offer => { totals[offer.shopId] += offer.price; });
        if (chosen.length !== books.size || new Set(chosen.map(offer => String(offer.bookId))).size !== books.size ||
            shops.some(shop => totals[shop.id] < shop.minimum)) throw new Error('Invalid solver allocation');
        return { status: 'optimal', chosen, totals, total: chosen.reduce((sum, offer) => sum + offer.price, 0) };
    }
    root.CartAllocation = { allocate };
    if (typeof module !== 'undefined') module.exports = root.CartAllocation;
})(typeof self !== 'undefined' ? self : globalThis);
