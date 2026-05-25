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
<style>
.cover-action { display: inline-grid; place-items: center; padding: 0; border: 0; background: transparent; cursor: pointer; }
.cover-action:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 4px; }
.cover-upload-status { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; line-height: 1.25; }
.cover-upload-status.error { color: #b42318; }
.cover-lightbox { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 24px; background: rgba(15, 23, 20, 0.72); }
.cover-lightbox[hidden] { display: none; }
.cover-lightbox img { max-width: min(92vw, 720px); max-height: 88vh; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 70px rgba(0, 0, 0, 0.35); background: #fff; }
</style>
<section class="page-head">
    <div>
        <h1>藏書清單</h1>
        <p>用關鍵字快速查重，或依狀態整理目前的藏書紀錄。點欄位標題可以排序目前顯示的結果。</p>
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
    <button class="button" type="submit">搜尋</button>
    <a class="button ghost" href="/books">清除</a>
</form>

<div class="js-book-cover-upload-csrf" hidden><?= csrf_field() ?></div>

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
                        <button class="cover-action js-cover-lightbox-open" type="button" data-cover-url="<?= esc($book['cover_url']) ?>" aria-label="檢視封面大圖">
                            <img class="cover-thumb" src="<?= esc($book['cover_url']) ?>" alt="">
                        </button>
                    <?php else: ?>
                        <button class="cover-action js-cover-upload-trigger" type="button" data-book-id="<?= (int) $book['id'] ?>" aria-label="上傳封面">
                            <span class="cover-empty">no image</span>
                        </button>
                        <input class="js-cover-upload-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-book-id="<?= (int) $book['id'] ?>" hidden>
                        <span class="cover-upload-status js-cover-upload-status" data-book-id="<?= (int) $book['id'] ?>"></span>
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

<div class="cover-lightbox js-cover-lightbox" hidden>
    <img class="js-cover-lightbox-image" src="" alt="">
</div>
<?= $this->endSection() ?>
