<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
.wishlist-table { min-width: 1120px; }
.wishlist-table .title-main + .muted { margin-bottom: 8px; }
.wishlist-sources { display: grid; gap: 8px; min-width: 520px; }
.wishlist-source-summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 7px 0; border-bottom: 1px solid #edf0ed; }
.wishlist-source-summary[hidden], .wishlist-source-form[hidden] { display: none; }
.wishlist-source-text { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; line-height: 1.5; }
.wishlist-source-text .shop { font-weight: 700; }
.wishlist-source-text .price { white-space: nowrap; }
.wishlist-source-actions, .wishlist-book-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
.wishlist-source-form { display: grid; grid-template-columns: minmax(130px, 170px) 88px minmax(180px, 1fr) minmax(120px, 180px) auto auto; gap: 6px; align-items: center; }
.wishlist-source-form input, .wishlist-source-form select { min-width: 0; padding: 6px 8px; }
.wishlist-source-form.add { border-top: 1px solid #edf0ed; margin-top: 3px; padding-top: 8px; }
.wishlist-source-actions form { margin: 0; }
.compact-tags { min-width: 0; margin-top: 8px; }
@media (max-width: 900px) {
    .wishlist-sources { min-width: 340px; }
    .wishlist-source-summary { grid-template-columns: 1fr; }
    .wishlist-source-actions { justify-content: flex-start; }
    .wishlist-source-form { grid-template-columns: 1fr; }
}
</style>
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

        $html = '<div class="list-tag-group compact-tags">';
        foreach ($names as $name) {
            $html .= '<span class="list-tag">' . esc($name) . '</span>';
        }
        return $html . '</div>';
    };
?>
<section class="page-head">
    <div>
        <h1>願望清單</h1>
        <p>整理還想入手的書本與目前找到的店鋪來源。</p>
    </div>
    <a class="button primary" href="/books/new?return_to=<?= $encodedReturnTo ?>">新增書本</a>
</section>

<form class="toolbar" method="get" action="/wishlist">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋標題、社團、作者、首字、tag、原作、角色">
    <select name="shop_id">
        <option value="0">所有店鋪來源</option>
        <?php foreach ($shops as $shop): ?>
            <option value="<?= (int) $shop['id'] ?>" <?= $shopId === (int) $shop['id'] ? 'selected' : '' ?>><?= esc($shop['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button" type="submit">搜尋</button>
    <a class="button ghost" href="/wishlist">清除</a>
</form>

<div class="table-wrap wishlist-wrap">
    <table class="data-table wishlist-table js-sortable-table">
        <thead>
            <tr>
                <th class="cover-col" data-sort="cover" data-sort-type="number">封面</th>
                <th data-sort="title">書名</th>
                <th data-sort="circle">社團</th>
                <th data-sort="author">作者</th>
                <th data-sort="source" data-sort-type="number">來源</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book): ?>
            <?php
                $bookId = (int) $book['id'];
                $bookSources = $sourcesByBook[$bookId] ?? [];
                $addFormId = 'wishlist-add-' . $bookId;
                $displayCoverUrl = cover_display_url($book['cover_url'] ?? '');
            ?>
            <tr>
                <td data-sort-value="<?= ! empty($book['cover_url']) ? 1 : 0 ?>">
                    <?php if (! empty($book['cover_url'])): ?>
                        <img class="cover-thumb" src="<?= esc($displayCoverUrl) ?>" alt="">
                    <?php else: ?>
                        <div class="cover-empty">no image</div>
                    <?php endif; ?>
                </td>
                <td data-sort-value="<?= esc($book['title']) ?>">
                    <div class="title-main"><?= esc($book['title']) ?></div>
                    <div class="muted"><?= esc($book['type']) ?><?= $book['circle_kana'] ? ' / ' . esc($book['circle_kana']) : '' ?></div>
                    <?= $renderTagList($book['tag_names'] ?? '') ?>
                </td>
                <td data-sort-value="<?= esc($book['circle'] ?? '') ?>"><?= esc($book['circle'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['author'] ?? '') ?>"><?= esc($book['author'] ?? '') ?></td>
                <td data-sort-value="<?= $book['min_price'] !== null ? (int) $book['min_price'] : 999999999 ?>">
                    <div class="wishlist-sources">
                        <?php foreach ($bookSources as $source): ?>
                            <?php
                                $sourceId = (int) $source['id'];
                                $summaryId = 'wishlist-source-summary-' . $sourceId;
                                $editId = 'wishlist-source-edit-' . $sourceId;
                            ?>
                            <div id="<?= esc($summaryId) ?>" class="wishlist-source-summary">
                                <div class="wishlist-source-text">
                                    <span class="shop"><?= esc($source['shop_name'] ?? '') ?></span>
                                    <?php if ($source['price'] !== null): ?><span class="price">¥<?= number_format((int) $source['price']) ?></span><?php endif; ?>
                                    <?php if (! empty($source['item_url'])): ?><a href="<?= esc($source['item_url']) ?>" target="_blank" rel="noreferrer">商品頁</a><?php endif; ?>
                                    <?php if (! empty($source['note'])): ?><span class="muted"><?= esc($source['note']) ?></span><?php endif; ?>
                                </div>
                                <div class="wishlist-source-actions">
                                    <button class="button small js-wishlist-source-edit" type="button" data-summary="#<?= esc($summaryId) ?>" data-form="#<?= esc($editId) ?>">編輯</button>
                                    <form method="post" action="/wishlist/sources/<?= $sourceId ?>/delete" data-confirm="確定刪除這筆來源？">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                                        <button class="button small danger" type="submit">刪除</button>
                                    </form>
                                </div>
                            </div>
                            <form id="<?= esc($editId) ?>" class="wishlist-source-form" method="post" action="/wishlist/sources/<?= $sourceId ?>" hidden>
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                                <select name="shop_id" aria-label="來源店鋪">
                                    <?php foreach ($shops as $shop): ?>
                                        <option value="<?= (int) $shop['id'] ?>" <?= (int) $source['shop_id'] === (int) $shop['id'] ? 'selected' : '' ?>><?= esc($shop['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="price" inputmode="numeric" value="<?= esc($source['price'] ?? '') ?>" placeholder="JPY" aria-label="價格">
                                <input name="item_url" value="<?= esc($source['item_url'] ?? '') ?>" placeholder="商品 URL" aria-label="商品 URL">
                                <input name="note" value="<?= esc($source['note'] ?? '') ?>" placeholder="備註" aria-label="備註">
                                <button class="button small" type="submit">儲存</button>
                                <button class="button small ghost js-wishlist-source-cancel" type="button" data-summary="#<?= esc($summaryId) ?>" data-form="#<?= esc($editId) ?>">取消</button>
                            </form>
                        <?php endforeach; ?>
                        <form id="<?= esc($addFormId) ?>" class="wishlist-source-form add" method="post" action="/wishlist/books/<?= $bookId ?>/sources" hidden>
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                            <select name="shop_id" aria-label="新增來源店鋪" required>
                                <option value="">新增來源店鋪</option>
                                <?php foreach ($shops as $shop): ?>
                                    <option value="<?= (int) $shop['id'] ?>"><?= esc($shop['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="price" inputmode="numeric" placeholder="JPY" aria-label="新增價格">
                            <input name="item_url" placeholder="商品 URL" aria-label="新增商品 URL">
                            <input name="note" placeholder="備註" aria-label="新增備註">
                            <button class="button small primary" type="submit">加入</button>
                            <button class="button small ghost js-wishlist-add-cancel" type="button" data-form="#<?= esc($addFormId) ?>">取消</button>
                        </form>
                    </div>
                </td>
                <td class="actions">
                    <div class="wishlist-book-actions">
                        <button class="button small js-wishlist-add-toggle" type="button" data-form="#<?= esc($addFormId) ?>">加入來源</button>
                        <a class="button small" href="/books/<?= $bookId ?>/edit?return_to=<?= $encodedReturnTo ?>">編輯書本</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($books === []): ?>
            <tr><td colspan="6" class="empty">沒有符合條件的願望清單書本。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
