<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.cart-table { min-width: 1080px; }
.cart-table .shop-col { width: 205px; }
.cart-table .total-col { width: 180px; text-align: right; }
.cart-table td.total-col { font-size: 16px; font-weight: 700; white-space: nowrap; }
.cart-shop-name { font-size: 16px; font-weight: 700; }
.cart-items { display: flex; gap: 14px; flex-wrap: wrap; align-items: start; min-height: 166px; }
.cart-item { position: relative; display: grid; gap: 2px; width: 138px; text-align: center; }
.cart-item[hidden] { display: none; }
.cart-cover, .cart-cover-empty { width: 102px; height: 142px; border-radius: 4px; margin: 0 auto 4px; }
.cart-cover { object-fit: cover; border: 1px solid var(--line); background: #f0f2f0; }
.cart-cover-empty { display: grid; place-items: center; border: 1px dashed #b8c2bd; color: var(--muted); font-size: 12px; }
.cart-remove { position: absolute; top: -6px; right: 10px; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 50%; background: transparent url("/assets/cancel-icon.svg") center / contain no-repeat; cursor: pointer; overflow: hidden; text-indent: -9999px; }
.cart-title { display: -webkit-box; overflow: hidden; color: var(--text); font-size: 13px; font-weight: 700; line-height: 1.35; text-decoration: none; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
a.cart-title { color: var(--accent-dark); text-decoration: underline; }
.cart-price { font-weight: 700; white-space: nowrap; line-height: 1.25; }
.cart-actions { display: grid; gap: 8px; justify-items: end; margin-top: 10px; }
.cart-actions form { margin: 0; }
.cart-actions .button { width: 100%; justify-content: center; }
@media (max-width: 900px) {
    .cart-table { min-width: 820px; }
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
            <?php $formId = 'cart-order-' . (int) $shop['id']; ?>
            <tr class="js-cart-shop-row">
                <td>
                    <div class="cart-shop-name"><?= esc($shop['name']) ?></div>
                    <?php if (! empty($shop['website_url'])): ?><div class="muted"><a href="<?= esc($shop['website_url']) ?>" target="_blank" rel="noreferrer">店鋪網站</a></div><?php endif; ?>
                </td>
                <td>
                    <div class="cart-items">
                        <?php foreach ($shop['items'] as $item): ?>
                            <article class="cart-item js-cart-item" data-price="<?= (int) ($item['price'] ?? 0) ?>">
                                <input class="js-cart-book-id" type="hidden" name="book_ids[]" value="<?= (int) $item['book_id'] ?>" form="<?= esc($formId) ?>">
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
                <td class="total-col">
                    ¥<span class="js-cart-total"><?= number_format((int) $shop['total_price']) ?></span>
                    <div class="cart-actions">
                        <button class="button small ghost js-cart-restore" type="button">恢復全部</button>
                        <form id="<?= esc($formId) ?>" method="post" action="/orders" data-confirm="確定要把目前保留的這批書建立成訂單？">
                            <?= csrf_field() ?>
                            <input type="hidden" name="shop_id" value="<?= (int) $shop['id'] ?>">
                            <button class="button small primary js-cart-order-submit" type="submit">建立訂單</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($shops === []): ?>
            <tr><td colspan="3" class="empty">目前沒有已記錄店鋪來源的願望清單。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
