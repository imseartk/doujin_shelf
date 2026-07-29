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

    $pageUrl = static function (int $targetPage) use ($q, $type, $status, $filterTagId): string {
        $query = [
            'q' => $q,
            'type' => $type,
            'status' => $status,
            'filter_tag_id' => $filterTagId > 0 ? $filterTagId : '',
            'page' => $targetPage,
        ];
        $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);

        return '/tools/book-tags' . ($query === [] ? '' : '?' . http_build_query($query));
    };

    $renderPagination = static function () use ($page, $totalPages, $pageUrl): string {
        if ($totalPages <= 1) {
            return '';
        }

        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        $html = '<nav class="pagination book-pagination">';
        $html .= '<a class="button small ' . ($page <= 1 ? 'disabled' : '') . '" href="' . esc($pageUrl(1)) . '">First</a>';
        $html .= '<a class="button small ' . ($page <= 1 ? 'disabled' : '') . '" href="' . esc($pageUrl(max(1, $page - 1))) . '">Prev</a>';
        if ($start > 1) {
            $html .= '<a class="button small" href="' . esc($pageUrl(1)) . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="pagination-gap">...</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<a class="button small ' . ($i === $page ? 'primary' : '') . '" href="' . esc($pageUrl($i)) . '">' . $i . '</a>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<span class="pagination-gap">...</span>';
            }
            $html .= '<a class="button small" href="' . esc($pageUrl($totalPages)) . '">' . $totalPages . '</a>';
        }
        $html .= '<a class="button small ' . ($page >= $totalPages ? 'disabled' : '') . '" href="' . esc($pageUrl(min($totalPages, $page + 1))) . '">Next</a>';
        $html .= '<a class="button small ' . ($page >= $totalPages ? 'disabled' : '') . '" href="' . esc($pageUrl($totalPages)) . '">Last</a>';
        return $html . '</nav>';
    };

    $renderTags = static function (?string $value): string {
        $names = array_filter(array_map('trim', explode(',', (string) $value)));
        if ($names === []) {
            return '<span class="muted">no tag</span>';
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
        <h1>批次加 tag</h1>
        <p>先用條件縮小範圍，勾選書本後一次加上指定 tag。</p>
    </div>
    <a class="button ghost" href="/books">回藏書清單</a>
</section>

<form class="toolbar" method="get" action="/tools/book-tags">
    <input class="books-keyword-input" type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋標題、社團、作者、tag、原作、角色">
    <select name="type">
        <option value="">所有類型</option>
        <?php foreach ($typeOptions as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">所有狀態</option>
        <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="filter_tag_id">
        <option value="">目前 tag</option>
        <?php foreach ($tagOptions as $tag): ?>
            <option value="<?= (int) $tag['id'] ?>" <?= (int) $filterTagId === (int) $tag['id'] ? 'selected' : '' ?>><?= esc($tag['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button" type="submit">搜尋</button>
    <a class="button ghost" href="/tools/book-tags">清除</a>
</form>

<div class="list-summary book-list-summary">
    <span>Total <?= number_format((int) $totalBooks) ?> books</span>
    <span>Page <?= number_format((int) $page) ?> / <?= number_format((int) $totalPages) ?></span>
</div>

<?= $renderPagination() ?>

<form id="batch-tag-form" method="post" action="/tools/book-tags/apply">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">

    <div class="batch-tag-actions">
        <label class="batch-tag-select-all">
            <input class="js-batch-tag-select-all" type="checkbox">
            <span>選取本頁</span>
        </label>
        <select name="tag_id" required>
            <option value="">選擇要加入的 tag</option>
            <?php foreach ($tagOptions as $tag): ?>
                <option value="<?= (int) $tag['id'] ?>"><?= esc($tag['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button primary" type="submit">套用到選取書本</button>
    </div>

    <?php if ($books === []): ?>
        <div class="notice">沒有符合條件的書本。</div>
    <?php else: ?>
        <div class="batch-book-grid">
            <?php foreach ($books as $book): ?>
                <?php $displayCoverUrl = cover_display_url($book['cover_url'] ?? ''); ?>
                <label class="batch-book-card">
                    <input class="js-batch-book-checkbox" type="checkbox" name="book_ids[]" value="<?= (int) $book['id'] ?>">
                    <?php if (! empty($book['cover_url'])): ?>
                        <img class="batch-book-cover" src="<?= esc($displayCoverUrl) ?>" alt="">
                    <?php else: ?>
                        <span class="batch-book-cover batch-book-cover-empty">no image</span>
                    <?php endif; ?>
                    <span class="batch-book-body">
                        <span class="title-main"><?= esc($book['title']) ?></span>
                        <span class="muted"><?= esc($typeOptions[$book['type']] ?? $book['type']) ?><?= ! empty($book['circle']) ? ' / ' . esc($book['circle']) : '' ?></span>
                        <?= $renderTags($book['tag_names'] ?? '') ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</form>

<?= $renderPagination() ?>

<script>
$(function () {
    $('.js-batch-tag-select-all').on('change', function () {
        $('.js-batch-book-checkbox').prop('checked', this.checked);
    });

    $('.js-batch-book-checkbox').on('change', function () {
        var total = $('.js-batch-book-checkbox').length;
        var checked = $('.js-batch-book-checkbox:checked').length;
        $('.js-batch-tag-select-all').prop('checked', total > 0 && total === checked);
    });
});
</script>
<?= $this->endSection() ?>
