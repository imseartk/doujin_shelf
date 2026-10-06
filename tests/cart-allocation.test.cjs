const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = vm.createContext({ console });
vm.runInContext(fs.readFileSync('public/assets/vendor/lp-solver/solver.global.js', 'utf8'), context);
const { allocate } = require('../public/assets/cart-allocation-core.js');
const solve = (shops, sources) => allocate(shops, sources, model => context.solver.Solve(model));
const shops = [{ id: 'a', minimum: 0 }, { id: 'b', minimum: 0 }];
const offer = (id, bookId, shopId, price) => ({ id, bookId, shopId, price });
test('cheapest unique books, ties and zero prices', () => {
    const result = solve(shops, [offer('1','1','a',100), offer('2','1','b',90), offer('3','2','a',0), offer('4','2','b',0)]);
    assert.equal(result.total, 90);
    assert.equal(result.chosen.length, 2);
});
test('minimum can require a more expensive source of the same book', () => {
    const result = solve([{id:'a', minimum:150}], [offer('1','1','a',100), offer('2','1','a',150)]);
    assert.equal(result.total, 150);
});
test('jointly impossible minimums and individual maximum', () => {
    const sources = [offer('1','1','a',100), offer('2','1','b',100)];
    assert.equal(solve(shops.map(shop => ({...shop, minimum:100})), sources).status, 'infeasible');
    assert.equal(solve([{id:'a', minimum:101}], sources).status, 'minimum');
});
test('missing price is not free, unselected shops excluded', () => {
    const sources = [offer('1','1','a',null), offer('2','1','b',100), offer('3','2','b',10)];
    assert.equal(solve([{id:'a',minimum:0}], sources).status, 'missing');
    assert.equal(solve(shops, sources).total, 110);
    assert.equal(solve([], sources).status, 'empty');
});
test('random small models match exhaustive enumeration', () => {
    let seed = 31;
    const random = n => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed % n; };
    for (let trial = 0; trial < 150; trial++) {
        const stores = ['a','b','c'].map(id => ({id, minimum:random(20)}));
        const groups = Array.from({length:5}, (_, book) => stores.filter(() => random(3) !== 0)
            .map(shop => offer(book + shop.id, String(book), shop.id, random(15))));
        groups.forEach((group, book) => { if (!group.length) group.push(offer(book+'a',String(book),'a',5)); });
        let best = Infinity;
        function enumerate(index, totals, cost) {
            if (index === groups.length) {
                if (stores.every(shop => totals[shop.id] >= shop.minimum)) best = Math.min(best, cost);
                return;
            }
            for (const source of groups[index]) {
                enumerate(index+1, {...totals, [source.shopId]:totals[source.shopId]+source.price}, cost+source.price);
            }
        }
        enumerate(0,{a:0,b:0,c:0},0);
        const result = solve(stores, groups.flat());
        if (best === Infinity) assert.ok(['infeasible','minimum'].includes(result.status));
        else { assert.equal(result.status,'optimal'); assert.equal(result.total,best, 'trial '+trial); }
    }
});
