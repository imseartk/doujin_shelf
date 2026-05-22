<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.order-list-table .order-actions { width: 118px; white-space: nowrap; }
.order-list-table .order-count { width: 110px; text-align: right; }
.order-list-table .order-time { width: 190px; white-space: nowrap; }
</style>
<section class="page-head">
    <div>
        <h1>訂單</h1>
        <p>這裡先放還需要處理的下單批次，之後會接到到貨驗收與位置指定。</p>
    </div>
</section>

<div class="table-wrap">
    <table class="data-table order-list-table">
        <thead>
            <tr>
                <th>店鋪</th>
                <th class="order-time">建立時間</th>
                <th class="order-count">書本數</th>
                <th class="order-actions">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= esc($order['shop_name'] ?? '未命名店鋪') ?></td>
                <td class="order-time"><?= esc($order['created_at'] ?? '') ?></td>
                <td class="order-count"><?= number_format((int) ($order['book_count'] ?? 0)) ?></td>
                <td class="order-actions"><a class="button small" href="/orders/<?= (int) $order['id'] ?>">查看</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($orders === []): ?>
            <tr><td colspan="4" class="empty">目前沒有待處理訂單。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
