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
            'label' => (string) $option['map_filename'] . ' / ' . (string) ($option['map_name'] ?? ''),
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
    $markerRank = static function (array $row): int {
        if (! empty($row['is_tracked'])) {
            return 3;
        }
        if (! empty($row['local_circle_id'])) {
            return 2;
        }

        return 1;
    };
    $labelText = static function (array $row): string {
        $parts = [];
        if (! empty($row['note'])) {
            $parts[] = (string) $row['note'];
        }
        if (! empty($row['book_name'])) {
            $parts[] = (string) $row['book_name'];
        }

        return implode("\n", array_slice($parts, 0, 2));
    };

    $currentDay = (string) $day;
    $currentMap = (string) $map;
    $mapSelectData = json_encode($mapsByDay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $markerRows = [];
    $printScale = 1.0;
    $printWidth = 0;
    $printHeight = 0;
    if ($image !== null) {
        $printScale = min(
            1.0,
            980 / max(1, (int) $image['width']),
            680 / max(1, (int) $image['height'])
        );
        $printWidth = (int) round((int) $image['width'] * $printScale);
        $printHeight = (int) round((int) $image['height'] * $printScale);

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

        $labelRects = [];
        $labelWidth = 156;
        $labelHeight = 50;
        foreach ($markerRows as &$row) {
            $left = (int) $row['_marker_left'];
            $top = (int) $row['_marker_top'];
            $candidateOffsets = [
                [52, -42],
                [-$labelWidth - 40, -42],
                [52, 12],
                [-$labelWidth - 40, 12],
                [12, -$labelHeight - 36],
                [12, 44],
                [92, -82],
                [-$labelWidth - 82, -82],
                [92, 54],
                [-$labelWidth - 82, 54],
            ];
            $placed = false;
            foreach ($candidateOffsets as $offset) {
                $labelX = max(8, min($left + $offset[0], (int) $image['width'] - $labelWidth - 8));
                $labelY = max(8, min($top + $offset[1], (int) $image['height'] - $labelHeight - 8));
                $rect = ['x' => $labelX, 'y' => $labelY, 'w' => $labelWidth, 'h' => $labelHeight];
                $hasCollision = false;
                foreach ($labelRects as $existingRect) {
                    if (
                        $rect['x'] < $existingRect['x'] + $existingRect['w'] + 8
                        && $rect['x'] + $rect['w'] + 8 > $existingRect['x']
                        && $rect['y'] < $existingRect['y'] + $existingRect['h'] + 8
                        && $rect['y'] + $rect['h'] + 8 > $existingRect['y']
                    ) {
                        $hasCollision = true;
                        break;
                    }
                }
                if ($hasCollision) {
                    continue;
                }

                $row['_label_x'] = $labelX;
                $row['_label_y'] = $labelY;
                $row['_line_x2'] = $labelX < $left ? $labelX + $labelWidth : $labelX;
                $row['_line_y2'] = $labelY + 22;
                $row['_label_visible'] = true;
                $labelRects[] = $rect;
                $placed = true;
                break;
            }

            if (! $placed) {
                $row['_label_visible'] = false;
            }
        }
        unset($row);
    }
?>
<section class="page-head export-map-head">
    <div>
        <h1>C108 離線地圖輸出</h1>
        <p>把追蹤或買過的社團標在館區地圖上，並加上社團名與備註。</p>
    </div>
    <div class="page-actions no-print">
        <button class="button primary" type="button" onclick="window.print()">列印 / 另存 PDF</button>
        <a class="button ghost" href="/c108/map?day=<?= esc($day) ?>&map=<?= esc($map) ?>&relation=<?= esc($relation) ?>">回地圖</a>
    </div>
</section>

<form class="toolbar no-print" method="get" action="/c108/export-map">
    <select name="day" class="js-c108-export-day" data-current-day="<?= esc($currentDay) ?>">
        <?php foreach (array_keys($days) as $optionDay): ?>
            <option value="<?= esc($optionDay) ?>" <?= (string) $optionDay === $currentDay ? 'selected' : '' ?>>
                <?= esc($optionDay) ?>日目
            </option>
        <?php endforeach; ?>
    </select>
    <select name="map" class="js-c108-export-map" data-current-map="<?= esc($currentMap) ?>" data-maps="<?= esc($mapSelectData) ?>">
        <?php foreach ($maps as $option): ?>
            <?php if ((string) $option['day'] !== $currentDay) { continue; } ?>
            <?php $optionMap = (string) $option['map_filename']; ?>
            <option value="<?= esc($optionMap) ?>" <?= $optionMap === $currentMap ? 'selected' : '' ?>>
                <?= esc($optionMap) ?> / <?= esc($option['map_name'] ?? '') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="relation">
        <option value="known" <?= $relation === 'known' ? 'selected' : '' ?>>追蹤中 + 買過的社團</option>
        <option value="tracked" <?= $relation === 'tracked' ? 'selected' : '' ?>>追蹤中</option>
    </select>
    <select name="priority">
        <option value="">全部優先度</option>
        <option value="must" <?= $priority === 'must' ? 'selected' : '' ?>>必看</option>
        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>優先</option>
        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>普通</option>
    </select>
    <button class="button primary" type="submit">產生輸出圖</button>
</form>

<?php if ($image === null): ?>
    <div class="notice error">找不到地圖底圖。請先在 Circle.ms 頁面匯出 common images。</div>
<?php else: ?>
    <section class="export-map-sheet">
        <div class="export-map-title">
            <strong><?= esc($day) ?>日目 / <?= esc($map) ?></strong>
            <span><?= number_format(count($markerRows)) ?> 個標記</span>
        </div>
        <div class="export-map-print-fit" style="--print-scale: <?= esc(number_format($printScale, 6, '.', '')) ?>; --print-width: <?= $printWidth ?>px; --print-height: <?= $printHeight ?>px;">
            <div class="export-map-stage" style="width: <?= (int) $image['width'] ?>px; height: <?= (int) $image['height'] ?>px;">
                <img class="export-map-image" src="<?= esc($image['url']) ?>" alt="">
                <svg class="export-map-lines" viewBox="0 0 <?= (int) $image['width'] ?> <?= (int) $image['height'] ?>" aria-hidden="true">
                    <?php foreach ($markerRows as $row): ?>
                        <?php if (empty($row['_label_visible'])) { continue; } ?>
                        <line
                            class="<?= ! empty($row['is_tracked']) ? 'tracked' : 'known' ?>"
                            x1="<?= (int) $row['_marker_left'] ?>"
                            y1="<?= (int) $row['_marker_top'] ?>"
                            x2="<?= (int) $row['_line_x2'] ?>"
                            y2="<?= (int) $row['_line_y2'] ?>"
                        />
                    <?php endforeach; ?>
                </svg>
                <?php foreach ($markerRows as $row): ?>
                    <div
                        class="c108-map-marker export-map-marker <?= esc($markerClass($row)) ?>"
                        style="left: <?= (int) $row['_marker_left'] ?>px; top: <?= (int) $row['_marker_top'] ?>px;"
                        title="<?= esc(trim((string) ($row['position_label'] ?? '') . ' ' . (string) ($row['circle_name'] ?? ''))) ?>"
                    ></div>
                    <?php if (empty($row['_label_visible'])) { continue; } ?>
                    <article
                        class="export-map-label <?= ! empty($row['is_tracked']) ? 'tracked' : 'known' ?>"
                        style="left: <?= (int) $row['_label_x'] ?>px; top: <?= (int) $row['_label_y'] ?>px;"
                    >
                        <h2><?= esc($row['circle_name'] ?? '') ?></h2>
                        <div class="meta"><?= esc($row['position_label'] ?? '') ?></div>
                        <?php if ($labelText($row) !== ''): ?>
                            <p><?= nl2br(esc($labelText($row))) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
$(function () {
    var $day = $('.js-c108-export-day');
    var $map = $('.js-c108-export-map');
    var mapsByDay = $map.data('maps') || {};

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

    $day.on('change', function () {
        rebuildMapOptions($day.val(), '');
    });
});
</script>
<?= $this->endSection() ?>
