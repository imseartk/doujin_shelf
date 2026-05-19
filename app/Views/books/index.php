<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $request = service('request');
    $uri = $request->getUri();
    $returnTo = '/' . ltrim($uri->getPath(), '/');
    $query = $uri->getQuery();
    if ($query !== '') {
        $returnTo .= '?' . $query;
    }
    $encodedReturnTo = rawurlencode($returnTo);

    $renderTagList = static function (?string $value): string {
        $names = array_filter(array_map('trim', explode(',', (string) $value)));
        if ($names === []) {
            return '';
        }

        $html = '<div class="list-tag-group">';
        foreach ($names as $name) {
            $html .= '<span class="list-tag">' . esc($name) . '</span>';
        }
        return $html . '</div>';
    };
?>
<section class="page-head">
    <div>
        <h1>書本清單</h1>
        <p>用關鍵字快速查重，或用狀態和店鋪篩選願望清單。點欄位標題可以排序目前顯示的結果。</p>
    </div>
    <a class="button primary" href="/books/new?return_to=<?= $encodedReturnTo ?>">新增書本</a>
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
    <table class="data-table books-table js-sortable-table">
        <thead>
            <tr>
                <th data-sort="status">狀態</th>
                <th class="cover-col" data-sort="cover" data-sort-type="number">封面</th>
                <th data-sort="title">書名</th>
                <th data-sort="circle">社團</th>
                <th data-sort="author">作者</th>
                <th data-sort="tags">分類</th>
                <th data-sort="works">原作</th>
                <th data-sort="characters">角色</th>
                <th data-sort="location">位置</th>
                <th data-sort="source" data-sort-type="number">來源</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book): ?>
            <?php
                $locationText = trim(($book['parent_location_name'] ? $book['parent_location_name'] . ' / ' : '') . ($book['location_name'] ?? ''));
                $sourceSort = $book['min_price'] !== null ? (int) $book['min_price'] : 999999999;
            ?>
            <tr>
                <td data-sort-value="<?= esc($statusOptions[$book['status']] ?? $book['status']) ?>"><span class="status status-<?= esc($book['status']) ?>"><?= esc($statusOptions[$book['status']] ?? $book['status']) ?></span></td>
                <td data-sort-value="<?= ! empty($book['cover_url']) ? 1 : 0 ?>">
                    <?php if (! empty($book['cover_url'])): ?>
                        <img class="cover-thumb" src="<?= esc($book['cover_url']) ?>" alt="">
                    <?php else: ?>
                        <div class="cover-empty">no image</div>
                    <?php endif; ?>
                </td>
                <td data-sort-value="<?= esc($book['title']) ?>">
                    <div class="title-main"><?= esc($book['title']) ?></div>
                    <div class="muted"><?= esc($book['type']) ?><?= $book['circle_kana'] ? ' / ' . esc($book['circle_kana']) : '' ?></div>
                </td>
                <td data-sort-value="<?= esc($book['circle'] ?? '') ?>"><?= esc($book['circle'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['author'] ?? '') ?>"><?= esc($book['author'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['tag_names'] ?? '') ?>"><?= $renderTagList($book['tag_names'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['work_names'] ?? '') ?>"><?= $renderTagList($book['work_names'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['character_names'] ?? '') ?>"><?= $renderTagList($book['character_names'] ?? '') ?></td>
                <td data-sort-value="<?= esc($locationText) ?>"><?= esc($locationText) ?></td>
                <td data-sort-value="<?= $sourceSort ?>">
                    <?php if ((int) $book['source_count'] > 0): ?>
                        <span class="pill"><?= (int) $book['source_count'] ?> 件</span>
                        <?php if ($book['min_price'] !== null): ?>
                            <span class="muted">最低 ¥<?= number_format((int) $book['min_price']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="actions"><a class="button small" href="/books/<?= (int) $book['id'] ?>/edit?return_to=<?= $encodedReturnTo ?>">編輯</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($books === []): ?>
            <tr><td colspan="11" class="empty">沒有符合條件的書本。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
