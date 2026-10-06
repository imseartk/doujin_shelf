<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.cart-shop-block { margin-bottom: 24px; }
.cart-shop-block .table-wrap { margin-top: 0; }
.cart-table { min-width: 940px; }
.cart-table .shop-col { width: 220px; }
.cart-table .total-col { width: 180px; text-align: right; }
.cart-table td.total-col { font-size: 16px; font-weight: 700; white-space: nowrap; }
.cart-shop-name { font-size: 16px; font-weight: 700; }
.cart-shop-meta { display: grid; gap: 4px; margin-top: 8px; color: var(--muted); font-size: 13px; line-height: 1.45; }
.cart-items { display: flex; gap: 14px; flex-wrap: wrap; align-items: start; min-height: 166px; }
.cart-item { position: relative; display: grid; gap: 2px; width: 138px; text-align: center; }
.cart-item[hidden] { display: none; }
.cart-cover, .cart-cover-empty { width: 102px; height: 142px; border-radius: 4px; margin: 0 auto 4px; }
.cart-cover { object-fit: cover; border: 1px solid var(--line); background: #f0f2f0; }
.cart-cover-action { display: block; width: 102px; height: 142px; padding: 0; margin: 0 auto 4px; border: 0; border-radius: 4px; background: transparent; cursor: pointer; }
.cart-cover-action:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
.cart-cover-action .cart-cover { display: block; margin: 0; }
.cart-item.is-lowest-price .cart-cover-action,
.cart-item.is-lowest-price > .cart-cover-empty {
    position: relative;
    box-shadow: 0 0 0 1px #fff6d8, 0 0 0 3px #d6b967, 0 0 0 4px #fff3ce, 0 0 10px rgba(211, 176, 82, 0.3);
}
.cart-item.is-lowest-price .cart-cover-action::after,
.cart-item.is-lowest-price > .cart-cover-empty::after {
    content: "";
    position: absolute;
    inset: -4px;
    border: 2px solid transparent;
    border-top-color: #fff8df;
    border-bottom-color: #c6a451;
    border-radius: 7px;
    pointer-events: none;
}
.cart-remove { z-index: 1; }
.cart-cover-empty { display: grid; place-items: center; border: 1px dashed #b8c2bd; color: var(--muted); font-size: 12px; }
.cart-remove { position: absolute; top: -6px; right: 10px; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 50%; background: transparent url("/assets/cancel-icon.svg") center / contain no-repeat; cursor: pointer; overflow: hidden; text-indent: -9999px; }
.cart-title { display: -webkit-box; overflow: hidden; color: var(--text); font-size: 13px; font-weight: 700; line-height: 1.35; text-decoration: none; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
a.cart-title { color: var(--accent-dark); text-decoration: underline; }
.cart-price { font-weight: 700; white-space: nowrap; line-height: 1.25; }
.cart-actions { display: grid; gap: 8px; justify-items: end; margin-top: 10px; }
.cart-actions form { margin: 0; }
.cart-actions .button { width: 100%; justify-content: center; }
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

<?php foreach ($shops as $shop): ?>
    <?php $formId = 'cart-order-' . (int) $shop['id']; ?>
    <section class="cart-shop-block js-cart-shop-row">
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
                    <tr>
                        <td>
                            <div class="cart-shop-name"><?= esc($shop['name']) ?></div>
                            <div class="cart-shop-meta">
                                <span>目前保留 <strong class="js-cart-count"><?= number_format(count($shop['items'])) ?></strong> 本</span>
                                <?php if (! empty($shop['website_url'])): ?><a href="<?= esc($shop['website_url']) ?>" target="_blank" rel="noreferrer">店鋪網站</a><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="cart-items">
                                <?php foreach ($shop['items'] as $item): ?>
                                    <?php $displayCoverUrl = cover_display_url($item['cover_url'] ?? ''); ?>
                                    <article class="cart-item js-cart-item" data-book-id="<?= (int) $item['book_id'] ?>" data-price="<?= $item['price'] === null ? '' : (int) $item['price'] ?>">
                                        <input class="js-cart-book-id" type="hidden" name="book_ids[]" value="<?= (int) $item['book_id'] ?>" form="<?= esc($formId) ?>">
                                        <button class="cart-remove js-cart-remove" type="button" aria-label="從本頁試算移除">取消</button>
                                        <?php if (! empty($item['cover_url'])): ?>
                                            <button class="cart-cover-action js-cart-shop-search-open" type="button" data-title="<?= esc($item['title']) ?>" aria-label="搜尋店鋪">
                                                <img class="cart-cover" src="<?= esc($displayCoverUrl) ?>" alt="">
                                            </button>
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
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
<?php if ($shops === []): ?>
    <div class="table-wrap">
        <table class="data-table">
            <tbody><tr><td class="empty">目前沒有已記錄店鋪來源的願望清單。</td></tr></tbody>
        </table>
    </div>
<?php endif; ?>

<div class="shop-search-modal js-cart-shop-search-modal" hidden>
    <div class="shop-search-modal-backdrop js-cart-shop-search-close"></div>
    <section class="shop-search-modal-card" role="dialog" aria-modal="true" aria-labelledby="cart-shop-search-title">
        <button class="shop-search-modal-close js-cart-shop-search-close" type="button" aria-label="Close">×</button>
        <h2 id="cart-shop-search-title">Search shops</h2>
        <p class="muted js-cart-shop-search-book-title"></p>
        <div class="shop-search-link-list js-cart-shop-search-link-list"></div>
    </section>
</div>

<script>
$(function () {
    var shopSearchLinks = [
        ['Mandarake', 'https://order.mandarake.co.jp/order/listPage/list?categoryCode=03&keyword={name}'],
        ['駿河屋', 'https://www.suruga-ya.jp/search?searchbox=1&category=11000002&search_word={name}'],
        ['らしんばん', 'https://shop.lashinbang.com/products/list?keyword={name}'],
        ['秋コミ', 'https://akicomi.com/products/list?category_id=3&name={name}'],
        ['Melonbooks', 'https://www.melonbooks.co.jp/search/search.php?mode=search&search_disp=&chara=&orderby=&disp_number=100&pageno=1&is_sp_view=0&name={name}&text_type=all&fromagee_flg=0&search_target%5B%5D=1&additional%5B%5D=r18&category_ids%5B%5D=1&is_end_of_sale2=1&sale_date_before=&sale_date_after=&publication_date_before=&publication_date_after=&co_name=&ci_name=&price_low=0&price_high=0'],
        ['とらのあな', 'https://ec.toranoana.jp/tora_r/ec/app/catalog/list?searchDisplay=12&searchBackorderFlg=1&searchCategoryCode=04&searchChildrenCategoryCode=cot&searchWord={name}']
    ];
    var $shopSearchModal = $('.js-cart-shop-search-modal');
    var $shopSearchBookTitle = $('.js-cart-shop-search-book-title');
    var $shopSearchLinkList = $('.js-cart-shop-search-link-list');

    function closeShopSearchModal() {
        $shopSearchModal.prop('hidden', true);
        $shopSearchBookTitle.text('');
        $shopSearchLinkList.empty();
    }

    $('.js-cart-shop-search-open').on('click', function () {
        var title = String($(this).data('title') || '').trim();
        var encodedTitle = encodeURIComponent(title);
        $shopSearchBookTitle.text(title);
        $shopSearchLinkList.empty();

        shopSearchLinks.forEach(function (item) {
            $('<a></a>').attr({
                href: item[1].replace('{name}', encodedTitle),
                target: '_blank',
                rel: 'noopener noreferrer'
            }).text(item[0]).appendTo($shopSearchLinkList);
        });

        $shopSearchModal.prop('hidden', false);
    });

    $('.js-cart-shop-search-close').on('click', closeShopSearchModal);
    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') closeShopSearchModal();
    });

    function syncLowestPrices() {
        var items = Array.from(document.querySelectorAll('.js-cart-item'));
        var minimums = new Map();

        items.forEach(function (item) {
            var price = Number(item.dataset.price);
            if (item.hidden || item.dataset.price === '' || !Number.isFinite(price) || price < 0) return;
            var bookId = item.dataset.bookId;
            if (!minimums.has(bookId) || price < minimums.get(bookId)) {
                minimums.set(bookId, price);
            }
        });

        items.forEach(function (item) {
            var lowest = !item.hidden && item.dataset.price !== '' &&
                minimums.has(item.dataset.bookId) && Number(item.dataset.price) === minimums.get(item.dataset.bookId);
            item.classList.toggle('is-lowest-price', lowest);
        });
    }

    function syncOrderRow($row) {
        var total = 0;
        var visibleItems = 0;

        $row.find('.js-cart-item').each(function () {
            var $item = $(this);
            if ($item.prop('hidden')) return;

            visibleItems += 1;
            total += parseInt($item.data('price'), 10) || 0;
        });

        $row.find('.js-cart-total').text(total.toLocaleString());
        $row.find('.js-cart-count').text(visibleItems.toLocaleString());
        $row.find('.js-cart-order-submit').prop('disabled', visibleItems === 0);
    }

    $('.js-cart-remove').on('click', function () {
        var $item = $(this).closest('.js-cart-item');
        var $row = $item.closest('.js-cart-shop-row');
        $item.prop('hidden', true);
        $item.find('.js-cart-book-id').prop('disabled', true);
        syncOrderRow($row);
        syncLowestPrices();
    });

    $('.js-cart-restore').on('click', function () {
        var $row = $(this).closest('.js-cart-shop-row');
        $row.find('.js-cart-item').prop('hidden', false);
        $row.find('.js-cart-book-id').prop('disabled', false);
        syncOrderRow($row);
        syncLowestPrices();
    });

    $('.js-cart-shop-row').each(function () {
        syncOrderRow($(this));
    });
    syncLowestPrices();
});
</script>
<?= $this->endSection() ?>
