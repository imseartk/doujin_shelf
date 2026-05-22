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
            <?php $bookSources = $sourcesByBook[(int) $book['id']] ?? []; ?>
            <tr>
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
                    <?= $renderTagList($book['tag_names'] ?? '') ?>
                </td>
                <td data-sort-value="<?= esc($book['circle'] ?? '') ?>"><?= esc($book['circle'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['author'] ?? '') ?>"><?= esc($book['author'] ?? '') ?></td>
                <td data-sort-value="<?= $book['min_price'] !== null ? (int) $book['min_price'] : 999999999 ?>">
                    <div class="wishlist-sources">
                        <?php foreach ($bookSources as $source): ?>
                            <form class="wishlist-source-form" method="post" action="/wishlist/sources/<?= (int) $source['id'] ?>">
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
                            </form>
                            <form class="wishlist-source-delete" method="post" action="/wishlist/sources/<?= (int) $source['id'] ?>/delete" data-confirm="確定刪除這筆來源？">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                                <button class="button small danger" type="submit">刪除</button>
                            </form>
                        <?php endforeach; ?>
                        <form class="wishlist-source-form add" method="post" action="/wishlist/books/<?= (int) $book['id'] ?>/sources">
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
                        </form>
                    </div>
                </td>
                <td class="actions"><a class="button small" href="/books/<?= (int) $book['id'] ?>/edit?return_to=<?= $encodedReturnTo ?>">編輯書本</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($books === []): ?>
            <tr><td colspan="6" class="empty">沒有符合條件的願望清單書本。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
