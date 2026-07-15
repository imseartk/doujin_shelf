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
    $priorityLabel = static function (?string $priority): string {
        $labels = ['must' => '必看', 'high' => '優先', 'normal' => '普通'];
        return $labels[$priority ?? ''] ?? '未設定';
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
        foreach ($rows as $row) {
            $left = max(0, (int) ($row['xpos2'] ?? 0) + (int) ($image['marker_offset_x'] ?? 0));
            $top = max(0, (int) ($row['ypos2'] ?? 0) + (int) ($image['marker_offset_y'] ?? 0));
            $markerKey = $left . ':' . $top;
            $markerRow = $row;
            $markerRow['_marker_left'] = $left;
            $markerRow['_marker_top'] = $top;
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
    <a class="button ghost" href="/c108">回 C108 清單</a>
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
                ?>
                <button
                    type="button"
                    class="c108-map-marker <?= esc($markerClass($row)) ?> <?= $q !== '' && $markerIndex === 1 ? 'c108-map-marker-focus js-c108-map-focus' : '' ?> js-c108-map-marker"
                    style="left: <?= $left ?>px; top: <?= $top ?>px;"
                    title="<?= esc($label) ?>"
                    data-name="<?= esc($row['circle_name'] ?? '') ?>"
                    data-kana="<?= esc($row['circle_kana'] ?? '') ?>"
                    data-pen-name="<?= esc($row['pen_name'] ?? '') ?>"
                    data-status="<?= esc($statusLabel($row)) ?>"
                    data-priority="<?= esc($priorityLabel($row['priority'] ?? null)) ?>"
                    data-position="<?= esc($row['position_label'] ?? '') ?>"
                    data-map="<?= esc(trim((string) ($row['map_name'] ?? '') . ' ' . (string) ($row['area_name'] ?? '') . ' ' . (string) ($row['space_label'] ?? ''))) ?>"
                    data-note="<?= esc($row['note'] ?? '') ?>"
                    data-image="<?= esc($imageUrl) ?>"
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
                            <dt>C108</dt>
                            <dd class="js-c108-map-modal-position"></dd>
                        </div>
                        <div>
                            <dt>館區</dt>
                            <dd class="js-c108-map-modal-map"></dd>
                        </div>
                        <div>
                            <dt>分類</dt>
                            <dd class="js-c108-map-modal-status"></dd>
                        </div>
                        <div>
                            <dt>優先度</dt>
                            <dd class="js-c108-map-modal-priority"></dd>
                        </div>
                    </dl>
                    <div class="c108-map-modal-note js-c108-map-modal-note"></div>
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

    function closeCircleModal() {
        $modal.prop('hidden', true);
        $modalImage.attr('src', '').prop('hidden', true);
    }

    $('.js-c108-map-marker').on('click', function () {
        var $marker = $(this);
        var subtitle = [$marker.data('kana'), $marker.data('pen-name')].filter(Boolean).join(' / ');
        var note = $marker.data('note') || '未設定';
        var image = $marker.data('image') || '';

        $('.js-c108-map-modal-title').text($marker.data('name') || '');
        $('.js-c108-map-modal-subtitle').text(subtitle);
        $('.js-c108-map-modal-position').text($marker.data('position') || '未設定');
        $('.js-c108-map-modal-map').text($marker.data('map') || '未設定');
        $('.js-c108-map-modal-status').text($marker.data('status') || '一般社團');
        $('.js-c108-map-modal-priority').text($marker.data('priority') || '未設定');
        $('.js-c108-map-modal-note').text(note);

        if (image) {
            $modalImage.attr('src', image).prop('hidden', false);
        } else {
            $modalImage.attr('src', '').prop('hidden', true);
        }

        $modal.prop('hidden', false);
    });

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
