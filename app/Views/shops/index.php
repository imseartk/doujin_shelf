<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head"><div><h1>店鋪</h1><p>先把常用中古店或通販站建好，書本編輯時就能選來源。</p></div></section>

<form class="inline-form" method="post" action="/shops">
    <?= csrf_field() ?>
    <input name="name" placeholder="店鋪名稱" required>
    <input name="website_url" placeholder="網站 URL">
    <button class="button primary" type="submit">新增</button>
</form>

<div class="table-wrap compact">
    <table class="data-table shops-table">
        <thead><tr><th>名稱</th><th>網站</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($shops as $shop): ?>
            <?php $formId = 'shop-form-' . (int) $shop['id']; ?>
            <tr>
                <td><input form="<?= esc($formId) ?>" name="name" value="<?= esc($shop['name']) ?>" required aria-label="店鋪名稱"></td>
                <td><input form="<?= esc($formId) ?>" name="website_url" value="<?= esc($shop['website_url'] ?? '') ?>" placeholder="網站 URL" aria-label="網站 URL"></td>
                <td class="actions">
                    <div class="row-actions">
                        <form id="<?= esc($formId) ?>" method="post" action="/shops/<?= (int) $shop['id'] ?>">
                            <?= csrf_field() ?>
                            <button class="button small" type="submit">儲存</button>
                        </form>
                        <form method="post" action="/shops/<?= (int) $shop['id'] ?>/delete" data-confirm="確定刪除店鋪？">
                            <?= csrf_field() ?><button class="button small danger" type="submit">刪除</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($shops === []): ?><tr><td colspan="3" class="empty">尚未建立店鋪。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
