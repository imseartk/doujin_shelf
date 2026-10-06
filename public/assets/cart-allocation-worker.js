importScripts('vendor/lp-solver/solver.global.js', 'cart-allocation-core.js');
self.onmessage = function (event) {
    try {
        self.postMessage(CartAllocation.allocate(event.data.shops, event.data.sources, model => solver.Solve(model)));
    } catch (error) {
        self.postMessage({ status: 'error' });
    }
};
