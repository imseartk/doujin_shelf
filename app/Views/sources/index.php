<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.cart-table { min-width: 980px; }
.cart-table .shop-col { width: 205px; }
.cart-table .total-col { width: 130px; text-align: right; }
.cart-table td.total-col { font-size: 16px; font-weight: 700; white-space: nowrap; }
.cart-shop-name { font-size: 16px; font-weight: 700; }
.cart-items { display: flex; gap: 14px; flex-wrap: wrap; align-items: start; min-height: 166px; }
.cart-item { position: relative; display: grid; gap: 6px; width: 138px; text-align: center; }
.cart-item[hidden] { display: none; }
.cart-cover, .cart-cover-empty { width: 102px; height: 142px; border-radius: 4px; margin: 0 auto; }
.cart-cover { object-fit: cover; border: 1px solid var(--line); background: #f0f2f0; }
.cart-cover-empty { display: grid; place-items: center; border: 1px dashed #b8c2bd; color: var(--muted); font-size: 12px; }
.cart-remove { position: absolute; top: -6px; right: 10px; display: grid; place-items: center; width: 28px; height: 28px; padding: 0; border: 1px solid #d4a0a0; border-radius: 50%; background: #fff; color: var(--danger); cursor: pointer; font: inherit; line-height: 1; }
.cart-title { display: -webkit-box; overflow: hidden; min-height: 37px; color: var(--text); font-size: 13px; font-weight: 700; line-height: 1.4; text-decoration: none; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
a.cart-title { color: var(--accent-dark); text-decoration: underline; }
.cart-price { font-weight: 700; white-space: nowrap; }
.cart-empty-row { color: var(--muted); padding: 20px 0; }
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
                                <button class="cart-remove js-cart-remove" type="button" aria-label="從本頁試算移除">x</button>
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
                    <div class="cart-empty-row js-cart-shop-empty" hidden>這間店的品項已暫時從本頁試算移除。</div>
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
