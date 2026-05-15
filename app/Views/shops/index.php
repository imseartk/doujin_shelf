<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head"><div><h1>店鋪</h1><p>先把常用中古店或通販站建好，書本編輯時就能選來源。</p></div></section>

<form class="inline-form" method="post" action="/shops">
    <?= csrf_field() ?>
    <input name="name" placeholder="店鋪名稱" required>
    <input name="website_url" placeholder="網站 URL">
    <input name="sort_order" type="number" value="0" aria-label="排序">
    <button class="button primary" type="submit">新增</button>
</form>

<div class="table-wrap compact">
    <table class="data-table">
        <thead><tr><th>名稱</th><th>網站</th><th>排序</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($shops as $shop): ?>
            <tr>
                <td><?= esc($shop['name']) ?></td>
                <td><?php if ($shop['website_url']): ?><a href="<?= esc($shop['website_url']) ?>" target="_blank" rel="noreferrer"><?= esc($shop['website_url']) ?></a><?php endif; ?></td>
                <td><?= (int) $shop['sort_order'] ?></td>
                <td>
                    <form method="post" action="/shops/<?= (int) $shop['id'] ?>/delete" data-confirm="確定刪除店鋪？">
                        <?= csrf_field() ?><button class="button small danger" type="submit">刪除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($shops === []): ?><tr><td colspan="4" class="empty">尚未建立店鋪。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
