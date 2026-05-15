<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head">
    <div>
        <h1>來源統計</h1>
        <p>統計目前願望清單/已訂購書本在各店鋪的可購買來源與總價。</p>
    </div>
</section>

<div class="table-wrap compact">
    <table class="data-table">
        <thead><tr><th>店鋪</th><th>本數</th><th>來源列</th><th>合計價格</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= esc($row['name']) ?></td>
                <td><?= (int) $row['book_count'] ?></td>
                <td><?= (int) $row['source_count'] ?></td>
                <td>¥<?= number_format((int) $row['total_price']) ?></td>
                <td><a class="button small" href="/sources?shop_id=<?= (int) $row['id'] ?>">查看</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?><tr><td colspan="5" class="empty">尚未建立店鋪。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($shopId > 0): ?>
<section class="subsection">
    <h2>店鋪清單</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>書名</th><th>社團 / 作者</th><th>狀態</th><th>價格</th><th>URL</th><th>更新</th></tr></thead>
            <tbody>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?= esc($book['title']) ?></td>
                    <td><div><?= esc($book['circle'] ?? '') ?></div><div class="muted"><?= esc($book['author'] ?? '') ?></div></td>
                    <td><span class="status status-<?= esc($book['status']) ?>"><?= esc($book['status']) ?></span></td>
                    <td><?= $book['price'] === null ? '' : '¥' . number_format((int) $book['price']) ?></td>
                    <td><?php if ($book['item_url']): ?><a href="<?= esc($book['item_url']) ?>" target="_blank" rel="noreferrer">商品頁</a><?php endif; ?></td>
                    <td class="muted"><?= esc($book['checked_at'] ?? $book['updated_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($books === []): ?><tr><td colspan="6" class="empty">這間店目前沒有願望清單來源。</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
