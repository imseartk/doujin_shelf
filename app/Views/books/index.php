<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head">
    <div>
        <h1>書本清單</h1>
        <p>最多顯示 300 筆；用關鍵字快速查重，或用狀態和店鋪篩選願望清單。</p>
    </div>
    <a class="button primary" href="/books/new">新增書本</a>
</section>

<form class="toolbar" method="get" action="/books">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋標題、社團、作者、首字、tag、原作、角色">
    <select name="status">
        <option value="">所有狀態</option>
        <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="shop_id">
        <option value="0">所有店鋪來源</option>
        <?php foreach ($shops as $shop): ?>
            <option value="<?= (int) $shop['id'] ?>" <?= $shopId === (int) $shop['id'] ? 'selected' : '' ?>><?= esc($shop['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button" type="submit">搜尋</button>
    <a class="button ghost" href="/books">清除</a>
</form>

<div class="table-wrap">
    <table class="data-table books-table">
        <thead>
            <tr>
                <th class="cover-col">封面</th>
                <th>書名</th>
                <th>社團 / 作者</th>
                <th>狀態</th>
                <th>分類</th>
                <th>原作 / 角色</th>
                <th>位置</th>
                <th>來源</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book): ?>
            <tr>
                <td>
                    <?php if (! empty($book['cover_url'])): ?>
                        <img class="cover-thumb" src="<?= esc($book['cover_url']) ?>" alt="">
                    <?php else: ?>
                        <div class="cover-empty">no image</div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="title-main"><?= esc($book['title']) ?></div>
                    <div class="muted"><?= esc($book['type']) ?><?= $book['circle_kana'] ? ' / ' . esc($book['circle_kana']) : '' ?></div>
                </td>
                <td>
                    <div><?= esc($book['circle'] ?? '') ?></div>
                    <div class="muted"><?= esc($book['author'] ?? '') ?></div>
                </td>
                <td><span class="status status-<?= esc($book['status']) ?>"><?= esc($statusOptions[$book['status']] ?? $book['status']) ?></span></td>
                <td><?= esc($book['tag_names'] ?? '') ?></td>
                <td>
                    <div><?= esc($book['work_names'] ?? '') ?></div>
                    <div class="muted"><?= esc($book['character_names'] ?? '') ?></div>
                </td>
                <td><?= esc(trim(($book['parent_location_name'] ? $book['parent_location_name'] . ' / ' : '') . ($book['location_name'] ?? ''))) ?></td>
                <td>
                    <?php if ((int) $book['source_count'] > 0): ?>
                        <span class="pill"><?= (int) $book['source_count'] ?> 件</span>
                        <?php if ($book['min_price'] !== null): ?>
                            <span class="muted">最低 ¥<?= number_format((int) $book['min_price']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="actions"><a class="button small" href="/books/<?= (int) $book['id'] ?>/edit">編輯</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($books === []): ?>
            <tr><td colspan="9" class="empty">沒有符合條件的書本。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
