<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.cart-table { min-width: 980px; }
.cart-table .shop-col { width: 205px; }
.cart-table .total-col { width: 130px; text-align: right; }
.cart-table td.total-col { font-size: 16px; font-weight: 700; white-space: nowrap; }
.cart-shop-name { font-size: 16px; font-weight: 700; }
.cart-items { display: flex; gap: 14px; flex-wrap: wrap; align-items: start; min-height: 166px; }
.cart-item { position: relative; display: grid; gap: 2px; width: 138px; text-align: center; }
.cart-item[hidden] { display: none; }
.cart-cover, .cart-cover-empty { width: 102px; height: 142px; border-radius: 4px; margin: 0 auto 4px; }
.cart-cover { object-fit: cover; border: 1px solid var(--line); background: #f0f2f0; }
.cart-cover-empty { display: grid; place-items: center; border: 1px dashed #b8c2bd; color: var(--muted); font-size: 12px; }
.cart-remove { position: absolute; top: -6px; right: 10px; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 50%; background: transparent url("data:image/png;base64,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") center / contain no-repeat; cursor: pointer; overflow: hidden; text-indent: -9999px; }
.cart-title { display: -webkit-box; overflow: hidden; color: var(--text); font-size: 13px; font-weight: 700; line-height: 1.35; text-decoration: none; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
a.cart-title { color: var(--accent-dark); text-decoration: underline; }
.cart-price { font-weight: 700; white-space: nowrap; line-height: 1.25; }
@media (max-width: 900px) {
    .cart-table { min-width: 760px; }
    .cart-items { min-height: 0; }
}
</style>
<section class="page-head">
    <div>
        <h1>購物車</h1>
        <p>比較各店鋪目前能買到的願望清單來源，先在這裡試算下單組合。</p>
    </div>
</section>

<div class="table-wrap">
    <table class="data-table cart-table">
        <thead>
            <tr>
                <th class="shop-col">店鋪</th>
                <th>願望清單</th>
                <th class="total-col">合計價格</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shops as $shop): ?>
            <tr class="js-cart-shop-row">
                <td>
                    <div class="cart-shop-name"><?= esc($shop['name']) ?></div>
                    <?php if (! empty($shop['website_url'])): ?><div class="muted"><a href="<?= esc($shop['website_url']) ?>" target="_blank" rel="noreferrer">店鋪網站</a></div><?php endif; ?>
                </td>
                <td>
                    <div class="cart-items">
                        <?php foreach ($shop['items'] as $item): ?>
                            <article class="cart-item js-cart-item" data-price="<?= (int) ($item['price'] ?? 0) ?>">
                                <button class="cart-remove js-cart-remove" type="button" aria-label="從本頁試算移除">取消</button>
                                <?php if (! empty($item['cover_url'])): ?>
                                    <img class="cart-cover" src="<?= esc($item['cover_url']) ?>" alt="">
                                <?php else: ?>
                                    <div class="cart-cover-empty">no image</div>
                                <?php endif; ?>
                                <?php if (! empty($item['item_url'])): ?>
                                    <a class="cart-title" href="<?= esc($item['item_url']) ?>" target="_blank" rel="noreferrer" title="<?= esc($item['title']) ?>"><?= esc($item['title']) ?></a>
                                <?php else: ?>
                                    <span class="cart-title" title="<?= esc($item['title']) ?>"><?= esc($item['title']) ?></span>
                                <?php endif; ?>
                                <span class="cart-price"><?= $item['price'] === null ? '未填價格' : '¥' . number_format((int) $item['price']) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td class="total-col">¥<span class="js-cart-total"><?= number_format((int) $shop['total_price']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($shops === []): ?>
            <tr><td colspan="3" class="empty">目前沒有已記錄店鋪來源的願望清單。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
