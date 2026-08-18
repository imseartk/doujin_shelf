<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $days = [];
    $mapsByDay = [];
    foreach ($maps as $option) {
        $optionDay = (string) $option['day'];
        $days[$optionDay] = true;
        $mapsByDay[$optionDay][] = [
            'map' => (string) $option['map_filename'],
            'label' => (string) $option['map_filename'] . ' / ' . (string) ($option['map_name'] ?? '') . ' (追蹤 ' . number_format((int) ($option['tracked_count'] ?? 0)) . ')',
        ];
    }

    $markerClass = static function (array $row): string {
        if (! empty($row['is_tracked'])) {
            return 'c108-map-marker-tracked';
        }
        if (! empty($row['local_circle_id'])) {
            return 'c108-map-marker-known';
        }

        return 'c108-map-marker-unknown';
    };
    $statusLabel = static function (array $row): string {
        if (! empty($row['is_tracked'])) {
            return '追蹤中';
        }
        if (! empty($row['local_circle_id'])) {
            return '買過的社團';
        }

        return '一般社團';
    };
    $markerRank = static function (array $row): int {
        if (! empty($row['is_tracked'])) {
            return 3;
        }
        if (! empty($row['local_circle_id'])) {
            return 2;
        }

        return 1;
    };

    $mapSelectData = json_encode($mapsByDay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $currentDay = (string) $day;
    $currentMap = (string) $map;
    $markerRows = [];
    if ($image !== null) {
        $image['position_rows'] = $positionRows ?? [];
        $markerPositions = \App\Controllers\C108::markerPositions($rows, $image);
        foreach ($rows as $rowIndex => $row) {
            $position = $markerPositions[$rowIndex] ?? \App\Controllers\C108::markerPosition($row, $image);
            $left = $position['left'];
            $top = $position['top'];
            $markerKey = $left . ':' . $top;
            $markerRow = $row;
            $markerRow['_marker_left'] = $left;
            $markerRow['_marker_top'] = $top;
            $markerRow['_marker_axis'] = $position['axis'] ?? 'x';
            $markerRow['_marker_rank'] = $markerRank($row);

            if (! isset($markerRows[$markerKey]) || $markerRow['_marker_rank'] > $markerRows[$markerKey]['_marker_rank']) {
                $markerRows[$markerKey] = $markerRow;
            }
        }
    }
?>
<section class="page-head">
    <div>
        <h1>C108 地圖</h1>
        <p>把追蹤社團與買過的社團疊到官方館區底圖上。</p>
    </div>
    <div class="page-actions">
        <a class="button ghost" href="/c108/custom-map?day=<?= esc($day) ?>&map=<?= esc($map) ?>&relation=<?= esc($relation) ?>&priority=<?= esc($priority) ?>">自製地圖實驗</a>
        <a class="button primary" href="/c108/export-map?day=<?= esc($day) ?>&map=<?= esc($map) ?>&relation=<?= esc($relation) ?>&priority=<?= esc($priority) ?>">輸出地圖</a>
        <a class="button ghost" href="/c108">回 C108 清單</a>
    </div>
</section>

<form class="toolbar" method="get" action="/c108/map">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋社團、作者、攤位、備註">
    <select name="day" class="js-c108-map-day" data-current-day="<?= esc($currentDay) ?>">
        <?php foreach (array_keys($days) as $optionDay): ?>
            <option value="<?= esc($optionDay) ?>" <?= (string) $optionDay === $currentDay ? 'selected' : '' ?>>
                <?= esc($optionDay) ?>日目
            </option>
        <?php endforeach; ?>
    </select>
    <select name="map" class="js-c108-map-select" data-current-map="<?= esc($currentMap) ?>" data-maps="<?= esc($mapSelectData) ?>">
        <?php foreach ($maps as $option): ?>
            <?php if ((string) $option['day'] !== $currentDay) { continue; } ?>
            <?php $optionMap = (string) $option['map_filename']; ?>
            <option value="<?= esc($optionMap) ?>" <?= $optionMap === $currentMap ? 'selected' : '' ?>>
                <?= esc($optionMap) ?> / <?= esc($option['map_name'] ?? '') ?>
                (追蹤 <?= number_format((int) ($option['tracked_count'] ?? 0)) ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <select name="relation">
        <option value="tracked" <?= $relation === 'tracked' ? 'selected' : '' ?>>追蹤中</option>
        <option value="known" <?= $relation === 'known' ? 'selected' : '' ?>>買過的社團</option>
        <option value="all" <?= $relation === 'all' ? 'selected' : '' ?>>全部社團</option>
    </select>
    <select name="priority">
        <option value="">全部優先度</option>
        <option value="must" <?= $priority === 'must' ? 'selected' : '' ?>>必看</option>
        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>優先</option>
        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>普通</option>
    </select>
    <button class="button primary" type="submit">顯示地圖</button>
    <a class="button ghost" href="/c108/map">重置</a>
</form>

<div class="list-summary">
    <span><?= esc($day) ?>日目 / <?= esc($map) ?></span>
    <span><?= number_format(count($markerRows)) ?> 個點</span>
</div>

<?php if ($image === null): ?>
    <div class="notice error">找不到地圖底圖。請先在 Circle.ms 頁面匯出 common images。</div>
<?php else: ?>
    <section class="c108-map-scroll">
        <div class="c108-map-stage" style="width: <?= (int) $image['width'] ?>px; height: <?= (int) $image['height'] ?>px;">
            <img class="c108-map-image" src="<?= esc($image['url']) ?>" alt="">
            <?php $markerIndex = 0; ?>
            <?php foreach ($markerRows as $row): ?>
                <?php
                    $markerIndex++;
                    $left = (int) $row['_marker_left'];
                    $top = (int) $row['_marker_top'];
                    $label = trim((string) ($row['position_label'] ?? '') . ' ' . (string) ($row['circle_name'] ?? ''));
                    $imageUrl = (string) ($row['webcatalog_cut_url'] ?? '');
                    if ($imageUrl === '') {
                        $imageUrl = (string) ($row['cut_url'] ?? ($row['cut_web_url'] ?? ''));
                    }
                    $ownedBooks = [];
                    if (! empty($row['owned_book_1_title'])) {
                        $ownedBooks[] = ['title' => (string) $row['owned_book_1_title'], 'cover' => (string) ($row['owned_book_1_cover'] ?? '')];
                    }
                    if (! empty($row['owned_book_2_title'])) {
                        $ownedBooks[] = ['title' => (string) $row['owned_book_2_title'], 'cover' => (string) ($row['owned_book_2_cover'] ?? '')];
                    }
                ?>
                <button
                    type="button"
                    class="c108-map-marker c108-map-marker-axis-<?= esc($row['_marker_axis'] ?? 'x') ?> <?= esc($markerClass($row)) ?> <?= $q !== '' && $markerIndex === 1 ? 'c108-map-marker-focus js-c108-map-focus' : '' ?> js-c108-map-marker"
                    style="left: <?= $left ?>px; top: <?= $top ?>px;"
                    title="<?= esc($label) ?>"
                    data-id="<?= esc((string) ($row['id'] ?? '')) ?>"
                    data-local-circle-id="<?= esc((string) ($row['local_circle_id'] ?? '')) ?>"
                    data-is-tracked="<?= ! empty($row['is_tracked']) ? '1' : '0' ?>"
                    data-name="<?= esc($row['circle_name'] ?? '') ?>"
                    data-kana="<?= esc($row['circle_kana'] ?? '') ?>"
                    data-pen-name="<?= esc($row['pen_name'] ?? '') ?>"
                    data-status="<?= esc($statusLabel($row)) ?>"
                    data-position="<?= esc($row['position_label'] ?? '') ?>"
                    data-book-name="<?= esc($row['book_name'] ?? '') ?>"
                    data-description="<?= esc($row['description'] ?? '') ?>"
                    data-webcatalog-url="<?= ! empty($row['wcid']) ? esc('https://webcatalog.circle.ms/circle/' . (int) $row['wcid']) : '' ?>"
                    data-wcid="<?= esc((string) ($row['wcid'] ?? '')) ?>"
                    data-note="<?= esc($row['note'] ?? '') ?>"
                    data-image="<?= esc($imageUrl) ?>"
                    data-raw-xpos="<?= esc((string) ($row['xpos'] ?? '')) ?>"
                    data-raw-ypos="<?= esc((string) ($row['ypos'] ?? '')) ?>"
                    data-raw-xpos2="<?= esc((string) ($row['xpos2'] ?? '')) ?>"
                    data-raw-ypos2="<?= esc((string) ($row['ypos2'] ?? '')) ?>"
                    data-marker-left="<?= esc((string) $left) ?>"
                    data-marker-top="<?= esc((string) $top) ?>"
                    data-marker-axis="<?= esc((string) ($row['_marker_axis'] ?? 'x')) ?>"
                    data-owned-books="<?= esc(json_encode($ownedBooks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                ></button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="c108-map-modal js-c108-map-modal" hidden>
        <div class="c108-map-modal-backdrop js-c108-map-modal-close"></div>
        <section class="c108-map-modal-card" role="dialog" aria-modal="true" aria-labelledby="c108-map-modal-title">
            <button class="c108-map-modal-close js-c108-map-modal-close" type="button" aria-label="關閉">×</button>
            <div class="c108-map-modal-body">
                <img class="c108-map-modal-image js-c108-map-modal-image" src="" alt="" hidden>
                <div>
                    <h2 id="c108-map-modal-title" class="js-c108-map-modal-title"></h2>
                    <div class="muted js-c108-map-modal-subtitle"></div>
                    <dl class="c108-map-modal-details">
                        <div>
                            <dt>攤位</dt>
                            <dd class="js-c108-map-modal-position"></dd>
                        </div>
                        <div>
                            <dt>分類</dt>
                            <dd class="js-c108-map-modal-status"></dd>
                        </div>
                        <div>
                            <dt>預定發布物</dt>
                            <dd class="js-c108-map-modal-book-name"></dd>
                        </div>
                        <div>
                            <dt>簡介</dt>
                            <dd class="js-c108-map-modal-description"></dd>
                        </div>
                    </dl>
                    <div class="c108-map-owned js-c108-map-owned" hidden></div>
                    <div class="c108-map-modal-note js-c108-map-modal-note"></div>
                    <div class="c108-map-note-editor js-c108-map-note-editor" hidden>
                        <label for="c108-map-note-input">備註</label>
                        <textarea id="c108-map-note-input" class="js-c108-map-note-input" rows="3"></textarea>
                        <div class="c108-map-note-actions">
                            <button class="button small ghost js-c108-map-save-note" type="button">儲存備註</button>
                            <span class="muted js-c108-map-note-state"></span>
                        </div>
                    </div>
                    <div class="c108-map-modal-actions">
                        <a class="button small ghost js-c108-map-webcatalog" href="#" target="_blank" rel="noopener" hidden>Web Catalog</a>
                        <button class="button small ghost js-c108-map-link-local" type="button">加入社團</button>
                        <button class="button small ghost js-c108-map-toggle-track" type="button" hidden>追蹤</button>
                        <button class="button small js-c108-map-load-works" type="button">顯示頒布物</button>
                    </div>
                    <div class="c108-map-works js-c108-map-works" hidden></div>
                </div>
            </div>
        </section>
    </div>
<?php endif; ?>
<script>
$(function () {
    var $day = $('.js-c108-map-day');
    var $map = $('.js-c108-map-select');
    var mapsByDay = $map.data('maps') || {};
    var currentDay = String($day.data('current-day') || '');
    var currentMap = String($map.data('current-map') || '');
    var hasSearch = <?= json_encode($q !== '') ?>;

    function rebuildMapOptions(selectedDay, selectedMap) {
        var options = mapsByDay[String(selectedDay)] || [];
        $map.empty();
        options.forEach(function (item) {
            $('<option></option>')
                .val(item.map)
                .text(item.label)
                .prop('selected', String(item.map) === String(selectedMap))
                .appendTo($map);
        });
        if (!$map.val() && options.length) {
            $map.val(options[0].map);
        }
    }

    if (currentDay) {
        $day.val(currentDay);
        rebuildMapOptions(currentDay, currentMap);
    }

    $day.on('change', function () {
        rebuildMapOptions($day.val(), '');
    });

    function focusSearchMarker() {
        if (!hasSearch) return;

        var $marker = $('.js-c108-map-focus').first();
        var $scroll = $('.c108-map-scroll').first();
        if (!$marker.length || !$scroll.length) return;

        var markerPosition = $marker.position();
        var left = Math.max(0, markerPosition.left - ($scroll.width() / 2));
        var top = Math.max(0, markerPosition.top - ($scroll.height() / 2));

        $scroll.animate({ scrollLeft: left, scrollTop: top }, 280);
    }

    var $modal = $('.js-c108-map-modal');
    var $modalImage = $('.js-c108-map-modal-image');
    var $works = $('.js-c108-map-works');
    var currentWcid = '';
    var currentC108Id = '';
    var currentLocalCircleId = '';
    var currentIsTracked = false;
    var $currentMarker = $();
    var circleImageCache = {};
    var csrfName = <?= json_encode(csrf_token()) ?>;
    var csrfHash = <?= json_encode(csrf_hash()) ?>;

    function csrfData(data) {
        data = data || {};
        data[csrfName] = csrfHash;
        return data;
    }

    function updateCsrf(response) {
        if (response && response.csrf) {
            csrfHash = response.csrf;
        }
    }

    function closeCircleModal() {
        $modal.prop('hidden', true);
        $modalImage.attr('src', '').prop('hidden', true);
        $works.prop('hidden', true).empty();
        currentWcid = '';
        currentC108Id = '';
        currentLocalCircleId = '';
        currentIsTracked = false;
        $currentMarker = $();
    }

    function statusLabel(isLinked, isTracked) {
        if (isTracked) return '追蹤中';
        if (isLinked) return '買過的社團';
        return '一般社團';
    }

    function applyLocalCircleState(circle) {
        currentLocalCircleId = circle && circle.id ? String(circle.id) : currentLocalCircleId;
        currentIsTracked = !!(circle && circle.is_tracked);
        var note = circle && circle.note ? circle.note : '';
        var isLinked = !!currentLocalCircleId;
        var label = statusLabel(isLinked, currentIsTracked);

        $('.js-c108-map-modal-status').text(label);
        $('.js-c108-map-modal-note').text(note || '未設定');
        $('.js-c108-map-note-input').val(note);
        $('.js-c108-map-note-editor').prop('hidden', !isLinked);
        $('.js-c108-map-link-local').prop('hidden', isLinked).prop('disabled', !currentC108Id);
        $('.js-c108-map-toggle-track')
            .prop('hidden', !isLinked)
            .prop('disabled', !currentC108Id)
            .text(currentIsTracked ? '解除追蹤' : '追蹤');

        if ($currentMarker.length) {
            $currentMarker
                .removeClass('c108-map-marker-unknown c108-map-marker-known c108-map-marker-tracked')
                .addClass(currentIsTracked ? 'c108-map-marker-tracked' : (isLinked ? 'c108-map-marker-known' : 'c108-map-marker-unknown'))
                .data('local-circle-id', currentLocalCircleId)
                .data('is-tracked', currentIsTracked ? 1 : 0)
                .data('status', label)
                .data('note', note);
        }
    }

    $('.js-c108-map-marker').on('click', function () {
        var $marker = $(this);
        var subtitle = [$marker.data('kana'), $marker.data('pen-name')].filter(Boolean).join(' / ');
        var note = $marker.data('note') || '未設定';
        var image = $marker.data('image') || '';
        var bookName = $marker.data('book-name') || '未設定';
        var description = $marker.data('description') || '未設定';
        var webcatalogUrl = $marker.data('webcatalog-url') || '';
        var ownedBooks = $marker.data('owned-books') || [];
        currentWcid = String($marker.data('wcid') || '');
        currentC108Id = String($marker.data('id') || '');
        currentLocalCircleId = String($marker.data('local-circle-id') || '');
        currentIsTracked = String($marker.data('is-tracked') || '') === '1';
        $currentMarker = $marker;

        $('.js-c108-map-modal-title').text($marker.data('name') || '');
        $('.js-c108-map-modal-subtitle').text(subtitle);
        $('.js-c108-map-modal-position').text($marker.data('position') || '未設定');
        $('.js-c108-map-modal-status').text($marker.data('status') || '一般社團');
        $('.js-c108-map-modal-book-name').text(bookName);
        $('.js-c108-map-modal-description').text(description);
        $('.js-c108-map-modal-note').text(note);
        $works.prop('hidden', true).empty();

        var $webcatalog = $('.js-c108-map-webcatalog');
        $webcatalog.prop('hidden', !webcatalogUrl).attr('href', webcatalogUrl || '#');
        $('.js-c108-map-load-works').prop('disabled', !currentWcid).text('顯示頒布物');
        $('.js-c108-map-note-state').text('');
        applyLocalCircleState({ id: currentLocalCircleId, is_tracked: currentIsTracked, note: note === '未設定' ? '' : note });
        $('.js-c108-map-link-local').text('加入社團');
        renderOwnedBooks(Array.isArray(ownedBooks) ? ownedBooks : []);

        if (image) {
            $modalImage.attr('src', image).prop('hidden', false);
        } else {
            $modalImage.attr('src', '').prop('hidden', true);
            loadCircleImage(currentWcid, $marker);
        }

        $modal.prop('hidden', false);
    });

    function loadCircleImage(wcid, $marker) {
        if (!wcid) return;

        if (circleImageCache[wcid]) {
            $modalImage.attr('src', circleImageCache[wcid]).prop('hidden', false);
            $marker.data('image', circleImageCache[wcid]);
            return;
        }

        $.getJSON('/c108/circle/' + encodeURIComponent(wcid))
            .done(function (response) {
                var imageUrl = response.circle && response.circle.image_url ? response.circle.image_url : '';
                if (!imageUrl || String(currentWcid) !== String(wcid)) return;

                circleImageCache[wcid] = imageUrl;
                $marker.data('image', imageUrl);
                $modalImage.attr('src', imageUrl).prop('hidden', false);
            });
    }

    function renderOwnedBooks(books) {
        var $owned = $('.js-c108-map-owned').empty();
        if (!books.length) {
            $owned.prop('hidden', true);
            return;
        }

        $('<div class="muted"></div>').text('已擁有').appendTo($owned);
        var $list = $('<div class="c108-map-owned-list"></div>').appendTo($owned);
        books.forEach(function (book) {
            var $item = $('<div class="c108-map-owned-item"></div>');
            if (book.cover) {
                $('<img alt="">').attr('src', book.cover).appendTo($item);
            } else {
                $('<div class="c108-map-owned-empty">no image</div>').appendTo($item);
            }
            $('<span></span>').text(book.title || '').appendTo($item);
            $item.appendTo($list);
        });
        $owned.prop('hidden', false);
    }

    $('.js-c108-map-load-works').on('click', function () {
        if (!currentWcid) return;

        var $button = $(this);
        $button.prop('disabled', true).text('讀取中...');
        $works.prop('hidden', false).html('<div class="muted">讀取頒布物中...</div>');

        $.getJSON('/c108/works/' + encodeURIComponent(currentWcid))
            .done(function (response) {
                renderWorks(response.items || []);
            })
            .fail(function (xhr) {
                var response = xhr.responseJSON || {};
                $works.html($('<div class="notice error"></div>').text(response.message || '讀取頒布物失敗。'));
            })
            .always(function () {
                $button.prop('disabled', false).text('重新讀取頒布物');
            });
    });

    $('.js-c108-map-link-local').on('click', function () {
        if (!currentC108Id) return;

        var $button = $(this);
        $button.prop('disabled', true).text('處理中...');

        $.ajax({
            url: '/c108/circles/' + encodeURIComponent(currentC108Id) + '/link-local',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: csrfData({})
        }).done(function (response) {
            updateCsrf(response);
            applyLocalCircleState(response.circle || {});
            $button.text(response.created ? '已新增社團' : '已連動社團');
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            updateCsrf(response);
            window.alert(response.message || '加入社團失敗。');
            $button.prop('disabled', false).text('加入社團');
        });
    });

    $('.js-c108-map-toggle-track').on('click', function () {
        if (!currentC108Id || !currentLocalCircleId) return;

        var $button = $(this);
        $button.prop('disabled', true).text('處理中...');

        $.ajax({
            url: '/c108/circles/' + encodeURIComponent(currentC108Id) + '/track',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: csrfData({})
        }).done(function (response) {
            updateCsrf(response);
            applyLocalCircleState(response.circle || {});
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            updateCsrf(response);
            window.alert(response.message || '更新追蹤狀態失敗。');
            applyLocalCircleState({ id: currentLocalCircleId, is_tracked: currentIsTracked, note: $('.js-c108-map-note-input').val() });
        });
    });

    $('.js-c108-map-save-note').on('click', function () {
        if (!currentC108Id || !currentLocalCircleId) return;

        var $button = $(this);
        var note = $('.js-c108-map-note-input').val() || '';
        $('.js-c108-map-note-state').text('儲存中...');
        $button.prop('disabled', true);

        $.ajax({
            url: '/c108/circles/' + encodeURIComponent(currentC108Id) + '/note',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: csrfData({ note: note })
        }).done(function (response) {
            updateCsrf(response);
            applyLocalCircleState(response.circle || {});
            $('.js-c108-map-note-state').text('已儲存');
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            updateCsrf(response);
            window.alert(response.message || '儲存備註失敗。');
            $('.js-c108-map-note-state').text('');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    function renderWorks(items) {
        $works.empty();
        if (!items.length) {
            $works.html('<div class="muted">沒有頒布物資料。</div>');
            return;
        }

        items.forEach(function (item) {
            var $card = $('<article class="c108-work-card"></article>');
            if (item.image_url) {
                $('<img alt="">').attr('src', item.image_url).appendTo($card);
            } else {
                $card.addClass('c108-work-card-no-image');
            }
            var $body = $('<div></div>').appendTo($card);
            $('<h3></h3>').text(item.name || '(no title)').appendTo($body);
            var meta = [];
            if (item.new_book) meta.push('新刊');
            if (item.price !== null && item.price !== undefined) meta.push('¥' + item.price);
            if (item.page) meta.push(item.page + 'p');
            if (item.size) meta.push(item.size);
            if (item.r18) meta.push('R18');
            if (item.update_date) meta.push(item.update_date);
            if (meta.length) $('<div class="muted"></div>').text(meta.join(' / ')).appendTo($body);
            if (item.introduction) $('<p></p>').text(item.introduction).appendTo($body);
            $card.appendTo($works);
        });
    }

    $('.js-c108-map-modal-close').on('click', closeCircleModal);
    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeCircleModal();
        }
    });

    setTimeout(focusSearchMarker, 120);
});
</script>
<?= $this->endSection() ?>
