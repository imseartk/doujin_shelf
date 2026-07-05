<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.order-summary { display: flex; gap: 18px; flex-wrap: wrap; align-items: center; }
.order-books { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
.order-book { display: grid; gap: 10px; grid-template-columns: 72px minmax(0, 1fr); align-items: start; padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); }
.order-book-cover, .order-book-empty { width: 72px; height: 100px; border-radius: 4px; }
.order-book-cover { object-fit: cover; border: 1px solid var(--line); background: #f0f2f0; }
.order-book-empty { display: grid; place-items: center; border: 1px dashed #b8c2bd; color: var(--muted); font-size: 12px; }
.order-book-title { color: var(--text); font-weight: 700; line-height: 1.4; }
.order-book-meta { color: var(--muted); font-size: 13px; line-height: 1.45; }
.order-status { display: inline-flex; margin-top: 6px; }
</style>
<section class="page-head">
    <div>
        <h1><?= esc($order['shop_name'] ?? '訂單') ?></h1>
        <div class="order-summary muted">
            <span>建立時間 <?= esc($order['created_at'] ?? '') ?></span>
            <span><?= number_format(count($books)) ?> 本</span>
            <span><?= number_format((int) $orderedCount) ?> 本已訂購待轉入</span>
        </div>
    </div>
    <div class="page-actions">
        <?php if ((int) $orderedCount > 0): ?>
            <form method="post" action="/orders/<?= (int) $order['id'] ?>/complete" data-confirm="確定要把這張訂單內的已訂購書籍批次轉成已擁有嗎？">
                <?= csrf_field() ?>
                <button class="button primary" type="submit">批次轉為已擁有</button>
            </form>
        <?php endif; ?>
        <a class="button ghost" href="/orders">回訂單</a>
        <a class="button" href="/sources">回購物車</a>
    </div>
</section>

<section class="panel wide">
    <h2>這批書</h2>
    <div class="order-books">
        <?php foreach ($books as $book): ?>
            <?php $displayCoverUrl = cover_display_url($book['cover_url'] ?? ''); ?>
            <article class="order-book">
                <?php if (! empty($book['cover_url'])): ?>
                    <img class="order-book-cover" src="<?= esc($displayCoverUrl) ?>" alt="">
                <?php else: ?>
                    <div class="order-book-empty">no image</div>
                <?php endif; ?>
                <div>
                    <a class="order-book-title" href="/books/<?= (int) $book['id'] ?>/edit"><?= esc($book['title']) ?></a>
                    <?php if (! empty($book['circle']) || ! empty($book['author'])): ?>
                        <div class="order-book-meta"><?= esc(trim(($book['circle'] ?? '') . ' / ' . ($book['author'] ?? ''), ' /')) ?></div>
                    <?php endif; ?>
                    <span class="status status-<?= esc($book['status'] ?? '') ?> order-status">
                        <?= esc(['owned' => '已擁有', 'blacklisted' => '黑名單', 'ordered' => '已訂購', 'wishlist' => '願望清單'][$book['status'] ?? ''] ?? ($book['status'] ?? '')) ?>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($books === []): ?>
            <div class="empty">這張訂單目前沒有書本。</div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
