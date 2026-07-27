<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $request = service('request');
    $canManage = admin_unlocked();
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

    $bookPageUrl = static function (int $targetPage) use ($q, $type, $status, $tagId, $sort, $dir): string {
        $query = [
            'q' => $q,
            'type' => $type,
            'status' => $status,
            'tag_id' => $tagId > 0 ? $tagId : '',
            'sort' => $sort,
            'dir' => $dir,
            'page' => $targetPage,
        ];
        $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);

        return '/books' . ($query === [] ? '' : '?' . http_build_query($query));
    };

    $renderPagination = static function () use ($page, $totalPages, $bookPageUrl): string {
        if ($totalPages <= 1) {
            return '';
        }

        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        $html = '<nav class="pagination book-pagination">';
        $html .= '<a class="button small ' . ($page <= 1 ? 'disabled' : '') . '" href="' . esc($bookPageUrl(1)) . '">First</a>';
        $html .= '<a class="button small ' . ($page <= 1 ? 'disabled' : '') . '" href="' . esc($bookPageUrl(max(1, $page - 1))) . '">Prev</a>';
        if ($start > 1) {
            $html .= '<a class="button small" href="' . esc($bookPageUrl(1)) . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="pagination-gap">...</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<a class="button small ' . ($i === $page ? 'primary' : '') . '" href="' . esc($bookPageUrl($i)) . '">' . $i . '</a>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<span class="pagination-gap">...</span>';
            }
            $html .= '<a class="button small" href="' . esc($bookPageUrl($totalPages)) . '">' . $totalPages . '</a>';
        }
        $html .= '<a class="button small ' . ($page >= $totalPages ? 'disabled' : '') . '" href="' . esc($bookPageUrl(min($totalPages, $page + 1))) . '">Next</a>';
        $html .= '<a class="button small ' . ($page >= $totalPages ? 'disabled' : '') . '" href="' . esc($bookPageUrl($totalPages)) . '">Last</a>';
        return $html . '</nav>';
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
    <?php if ($canManage): ?>
        <a class="button primary" href="/books/new?return_to=<?= $encodedReturnTo ?>">新增書本</a>
    <?php endif; ?>
</section>

<form class="toolbar" method="get" action="/books">
    <input type="hidden" name="sort" value="<?= esc($sort) ?>">
    <input type="hidden" name="dir" value="<?= esc($dir) ?>">
    <input class="books-keyword-input" type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋標題、社團、作者、首字、tag、原作、角色">
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
    <select name="tag_id">
        <option value="">常用 tag</option>
        <?php foreach ($quickTagOptions as $tag): ?>
            <option value="<?= (int) $tag['id'] ?>" <?= (int) $tagId === (int) $tag['id'] ? 'selected' : '' ?>><?= esc($tag['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button" type="submit">搜尋</button>
    <a class="button ghost" href="/books">清除</a>
</form>

<div class="list-summary book-list-summary">
    <span>Total <?= number_format((int) $totalBooks) ?> books</span>
    <span>Page <?= number_format((int) $page) ?> / <?= number_format((int) $totalPages) ?></span>
</div>

<?= $renderPagination() ?>

<?php if ($canManage): ?>
    <div class="js-book-cover-upload-csrf" hidden><?= csrf_field() ?></div>
<?php endif; ?>

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
                $displayCoverUrl = cover_display_url($book['cover_url'] ?? '');
            ?>
            <tr>
                <td data-sort-value="<?= esc($statusOptions[$book['status']] ?? $book['status']) ?>"><span class="status status-<?= esc($book['status']) ?>"><?= esc($statusOptions[$book['status']] ?? $book['status']) ?></span></td>
                <td data-sort-value="<?= ! empty($book['cover_url']) ? 1 : 0 ?>">
                    <?php if (! empty($book['cover_url'])): ?>
                        <button class="cover-action js-cover-lightbox-open" type="button" data-cover-url="<?= esc($displayCoverUrl) ?>" aria-label="檢視封面大圖">
                            <img class="cover-thumb" src="<?= esc($displayCoverUrl) ?>" alt="">
                        </button>
                    <?php elseif ($canManage): ?>
                        <button class="cover-action js-cover-upload-trigger" type="button" data-book-id="<?= (int) $book['id'] ?>" aria-label="上傳封面">
                            <span class="cover-empty">no image</span>
                        </button>
                        <input class="js-cover-upload-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-book-id="<?= (int) $book['id'] ?>" hidden>
                        <span class="cover-upload-status js-cover-upload-status" data-book-id="<?= (int) $book['id'] ?>"></span>
                    <?php else: ?>
                        <span class="cover-empty">no image</span>
                    <?php endif; ?>
                </td>
                <td data-sort-value="<?= esc($book['title']) ?>">
                    <div class="title-main"><?= esc($book['title']) ?></div>
                    <div class="muted"><?= esc($typeOptions[$book['type']] ?? $book['type']) ?><?= $book['circle_kana'] ? ' / ' . esc($book['circle_kana']) : '' ?></div>
                </td>
                <td data-sort-value="<?= esc($book['circle'] ?? '') ?>">
                    <?php if (! empty($book['circle'])): ?>
                        <a href="/circles?q=<?= rawurlencode((string) $book['circle']) ?>" target="_blank" rel="noopener noreferrer"><?= esc($book['circle']) ?></a>
                    <?php endif; ?>
                </td>
                <td data-sort-value="<?= esc($book['author'] ?? '') ?>"><?= esc($book['author'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['tag_names'] ?? '') ?>"><?= $renderTagList($book['tag_names'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['work_names'] ?? '') ?>"><?= $renderTagList($book['work_names'] ?? '') ?></td>
                <td data-sort-value="<?= esc($book['character_names'] ?? '') ?>"><?= $renderTagList($book['character_names'] ?? '') ?></td>
                <td data-sort-value="<?= esc($locationText) ?>"><?= esc($locationText) ?></td>
                <td data-sort-value="<?= $sourceSort ?>">
                    <?php if ((int) $book['source_count'] > 0): ?>
                        <a class="pill" href="/wishlist?q=<?= rawurlencode((string) $book['title']) ?>" target="_blank" rel="noopener noreferrer"><?= (int) $book['source_count'] ?> 件</a>
                        <?php if ($book['min_price'] !== null): ?>
                            <span class="muted">最低 ¥<?= number_format((int) $book['min_price']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if ($canManage): ?>
                    <a class="button small" href="/books/<?= (int) $book['id'] ?>/edit?return_to=<?= $encodedReturnTo ?>">編輯</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($books === []): ?>
            <tr><td colspan="11" class="empty">沒有符合條件的書本。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?= $renderPagination() ?>

<div class="cover-lightbox js-cover-lightbox" hidden>
    <img class="js-cover-lightbox-image" src="" alt="">
</div>

<script>
$(function () {
    var $lightbox = $('.js-cover-lightbox');
    var $lightboxImage = $('.js-cover-lightbox-image');

    $('.js-cover-lightbox-open').on('click', function () {
        $lightboxImage.attr('src', $(this).data('cover-url'));
        $lightbox.prop('hidden', false);
    });

    $lightbox.on('click', function (event) {
        if (event.target !== this) return;
        $lightbox.prop('hidden', true);
        $lightboxImage.attr('src', '');
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            $lightbox.prop('hidden', true);
            $lightboxImage.attr('src', '');
        }
    });

    $('.js-cover-upload-trigger').on('click', function () {
        var bookId = $(this).data('book-id');
        $('.js-cover-upload-file[data-book-id="' + bookId + '"]').trigger('click');
    });

    $('.js-cover-upload-file').on('change', function () {
        var input = this;
        var bookId = $(input).data('book-id');
        var file = input.files && input.files[0];
        if (!file) return;

        var $cell = $(input).closest('td');
        var $status = $cell.find('.js-cover-upload-status');
        var $trigger = $cell.find('.js-cover-upload-trigger');
        var $csrf = $('.js-book-cover-upload-csrf input[type="hidden"]').first();
        var formData = new FormData();

        formData.append('cover_file', file);
        if ($csrf.length) formData.append($csrf.attr('name'), $csrf.val());

        $status.removeClass('error').text('上傳中...');
        $trigger.prop('disabled', true);

        $.ajax({
            url: '/books/' + bookId + '/cover',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response && response.csrf && $csrf.length) $csrf.val(response.csrf);
            var displayCoverUrl = response && (response.display_cover_url || response.cover_url);
            if (!displayCoverUrl) return;

            $cell.attr('data-sort-value', '1');
            $cell.empty().append(
                $('<button type="button" class="cover-action js-cover-lightbox-open" aria-label="檢視封面大圖"></button>')
                    .attr('data-cover-url', displayCoverUrl)
                    .append($('<img class="cover-thumb" alt="">').attr('src', displayCoverUrl))
                    .on('click', function () {
                        $lightboxImage.attr('src', displayCoverUrl);
                        $lightbox.prop('hidden', false);
                    })
            );
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            if (response.csrf && $csrf.length) $csrf.val(response.csrf);
            $status.addClass('error').text(response.message || '上傳失敗');
        }).always(function () {
            input.value = '';
            $trigger.prop('disabled', false);
        });
    });
});
</script>
<?= $this->endSection() ?>
