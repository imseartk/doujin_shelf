<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head"><div><h1>位置</h1><p>用兩層管理放置位置，例如「房間 / 書櫃A」。</p></div></section>

<form class="inline-form" method="post" action="/locations">
    <?= csrf_field() ?>
    <select name="parent_id">
        <option value="0">建立第一層位置</option>
        <?php foreach ($parents as $parent): ?>
            <option value="<?= (int) $parent['id'] ?>">放在 <?= esc($parent['name']) ?> 底下</option>
        <?php endforeach; ?>
    </select>
    <input name="name" placeholder="位置名稱" required>
    <input name="sort_order" type="number" value="0" aria-label="排序">
    <button class="button primary" type="submit">新增</button>
</form>

<div class="table-wrap compact">
    <table class="data-table">
        <thead><tr><th>ID</th><th>上層</th><th>名稱</th><th>排序</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($locations as $location): ?>
            <tr>
                <td><?= (int) $location['id'] ?></td>
                <td><?= esc($location['parent_id'] ?: '') ?></td>
                <td><?= esc($location['name']) ?></td>
                <td><?= (int) $location['sort_order'] ?></td>
                <td>
                    <form method="post" action="/locations/<?= (int) $location['id'] ?>/delete" data-confirm="確定刪除位置？">
                        <?= csrf_field() ?><button class="button small danger" type="submit">刪除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($locations === []): ?><tr><td colspan="5" class="empty">尚未建立位置。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
