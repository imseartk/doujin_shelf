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

    $mapSelectData = json_encode($mapsByDay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $currentDay = (string) $day;
    $currentMap = (string) $map;
    $boothRows = [];
    $blockLabels = [];
    $world = ['width' => 1600, 'height' => 1200, 'offsetX' => 80, 'offsetY' => 80];
    $isE123 = strtoupper($currentMap) === 'E123';
    $positionScaleX = $isE123 ? 3.20 : 1.15;
    $positionScaleY = $isE123 ? 6.10 : 1.15;
    if ($image !== null) {
        $image['position_rows'] = $positionRows ?? [];
        $positions = \App\Controllers\C108::markerPositions($rows, $image);
        $maxLeft = 0;
        $maxTop = 0;
        foreach ($rows as $index => $row) {
            $position = $positions[$index] ?? \App\Controllers\C108::markerPosition($row, $image);
            $left = max(0, (int) round(((int) ($position['left'] ?? 0)) * $positionScaleX));
            $top = max(0, (int) round(((int) ($position['top'] ?? 0)) * $positionScaleY));
            $axis = (string) ($position['axis'] ?? 'x');
            $space = str_pad((string) (int) ($row['space_no'] ?? 0), 2, '0', STR_PAD_LEFT) . (string) ($row['space_no_sub'] ?? '');
            $blockName = (string) ($row['block_name'] ?? '');
            $detailImage = (string) ($row['cut_image_url'] ?? '');
            if ($detailImage === '') {
                $detailImage = (string) ($row['webcatalog_cut_url'] ?? '');
            }
            if ($detailImage === '') {
                $detailImage = (string) ($row['owned_book_1_cover'] ?? '');
            }
            if ($detailImage === '') {
                $detailImage = (string) ($row['owned_book_2_cover'] ?? '');
            }
            $status = 'unknown';
            if (! empty($row['is_tracked'])) {
                $status = 'tracked';
            } elseif (! empty($row['local_circle_id'])) {
                $status = 'known';
            }

            $boothRows[] = $row + [
                '_booth_left' => $left,
                '_booth_top' => $top,
                '_booth_axis' => $axis,
                '_booth_space' => $blockName . $space,
                '_booth_status' => $status,
                '_booth_image' => $detailImage,
            ];
            $maxLeft = max($maxLeft, $left);
            $maxTop = max($maxTop, $top);
        }

        $blockLabels = [];
        $maxLeft = 0;
        $maxTop = 0;
        foreach ($boothRows as $row) {
            $left = (int) $row['_booth_left'];
            $top = (int) $row['_booth_top'];
            $blockName = (string) ($row['block_name'] ?? '');

            if ($isE123 && $blockName !== '') {
                if (! isset($blockLabels[$blockName])) {
                    $blockLabels[$blockName] = [
                        'name' => $blockName,
                        'minLeft' => $left,
                        'maxLeft' => $left,
                        'minTop' => $top,
                        'maxTop' => $top,
                        'count' => 0,
                    ];
                }
                $blockLabels[$blockName]['minLeft'] = min($blockLabels[$blockName]['minLeft'], $left);
                $blockLabels[$blockName]['maxLeft'] = max($blockLabels[$blockName]['maxLeft'], $left);
                $blockLabels[$blockName]['minTop'] = min($blockLabels[$blockName]['minTop'], $top);
                $blockLabels[$blockName]['maxTop'] = max($blockLabels[$blockName]['maxTop'], $top);
                $blockLabels[$blockName]['count']++;
            }

            $maxLeft = max($maxLeft, $left);
            $maxTop = max($maxTop, $top);
        }

        foreach ($blockLabels as $labelKey => $label) {
            $blockLabels[$labelKey]['left'] = (int) round(($label['minLeft'] + $label['maxLeft']) / 2);
            $blockLabels[$labelKey]['top'] = (int) round(($label['minTop'] + $label['maxTop']) / 2);
        }

        $world['width'] = max(1200, $maxLeft + ($isE123 ? 420 : 260));
        $world['height'] = max(900, $maxTop + ($isE123 ? 420 : 260));
    }
?>
<section class="page-head">
    <div>
        <h1>C108 自製地圖實驗</h1>
        <p>用 C108 資料直接畫攤位格，測試拖曳、縮放與旋轉的現場操作手感。</p>
    </div>
    <div class="page-actions">
        <a class="button ghost" href="/c108/map?day=<?= esc($day) ?>&map=<?= esc($map) ?>&relation=<?= esc($relation) ?>&priority=<?= esc($priority) ?>">回官方底圖版</a>
        <a class="button ghost" href="/c108">回 C108 清單</a>
    </div>
</section>

<form class="toolbar" method="get" action="/c108/custom-map">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋社團、作者、攤位、備註">
    <select name="day" class="js-c108-custom-day" data-current-day="<?= esc($currentDay) ?>">
        <?php foreach (array_keys($days) as $optionDay): ?>
            <option value="<?= esc($optionDay) ?>" <?= (string) $optionDay === $currentDay ? 'selected' : '' ?>>
                <?= esc($optionDay) ?>日目
            </option>
        <?php endforeach; ?>
    </select>
    <select name="map" class="js-c108-custom-map" data-current-map="<?= esc($currentMap) ?>" data-maps="<?= esc($mapSelectData) ?>">
        <?php foreach ($maps as $option): ?>
            <?php if ((string) $option['day'] !== $currentDay) { continue; } ?>
            <?php $optionMap = (string) $option['map_filename']; ?>
            <option value="<?= esc($optionMap) ?>" <?= $optionMap === $currentMap ? 'selected' : '' ?>>
                <?= esc($optionMap) ?> / <?= esc($option['map_name'] ?? '') ?>
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
    <a class="button ghost" href="/c108/custom-map">重置</a>
</form>

<section class="custom-map-shell">
    <div class="custom-map-toolbar">
        <strong><?= esc($day) ?>日目 / <?= esc($map) ?></strong>
        <span><?= number_format(count($boothRows)) ?> 個攤位</span>
        <button class="button small ghost js-custom-map-zoom-in" type="button">放大</button>
        <button class="button small ghost js-custom-map-zoom-out" type="button">縮小</button>
        <button class="button small ghost js-custom-map-rotate-left" type="button">左轉</button>
        <button class="button small ghost js-custom-map-rotate-right" type="button">右轉</button>
        <button class="button small ghost js-custom-map-reset" type="button">重置視角</button>
    </div>
    <div class="custom-map-viewport js-custom-map-viewport">
        <div
            class="custom-map-world <?= $isE123 ? 'custom-map-world-e123' : '' ?> js-custom-map-world"
            style="width: <?= (int) $world['width'] ?>px; height: <?= (int) $world['height'] ?>px;"
        >
            <div class="custom-map-hall-label"><?= esc($day) ?>日目 / <?= esc($map) ?></div>
            <?php foreach ($blockLabels as $label): ?>
                <?php if ((int) ($label['count'] ?? 0) < 8) { continue; } ?>
                <div
                    class="custom-map-block-label"
                    style="left: <?= (int) $label['left'] ?>px; top: <?= (int) $label['top'] ?>px;"
                    aria-hidden="true"
                >
                    <?= esc((string) $label['name']) ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ($boothRows as $row): ?>
                <?php
                    $class = 'custom-map-booth-' . (string) $row['_booth_status'];
                    $axisClass = (string) $row['_booth_axis'] === 'y' ? 'custom-map-booth-y' : 'custom-map-booth-x';
                    $title = trim((string) ($row['circle_name'] ?? ''));
                    $position = trim((string) ($row['position_label'] ?? ''));
                    if ($position === '') {
                        $position = (string) $row['_booth_space'];
                    }
                    $detailImage = (string) ($row['_booth_image'] ?? '');
                    $shortTitle = mb_strlen($title) > 18 ? mb_substr($title, 0, 17) . '…' : $title;
                ?>
                <button
                    type="button"
                    class="custom-map-booth <?= esc($axisClass) ?> <?= esc($class) ?> js-custom-map-booth"
                    style="left: <?= (int) $row['_booth_left'] ?>px; top: <?= (int) $row['_booth_top'] ?>px;"
                    data-name="<?= esc($title) ?>"
                    data-position="<?= esc($position) ?>"
                    data-note="<?= esc($row['note'] ?? '') ?>"
                    data-status="<?= esc((string) $row['_booth_status']) ?>"
                    data-book-name="<?= esc($row['book_name'] ?? '') ?>"
                    data-description="<?= esc($row['description'] ?? '') ?>"
                >
                    <span class="custom-map-booth-code"><?= esc((string) $row['_booth_space']) ?></span>
                    <?php if ($detailImage !== ''): ?>
                        <img class="custom-map-booth-image" src="<?= esc($detailImage) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="custom-map-booth-placeholder"><?= esc($shortTitle !== '' ? $shortTitle : (string) $row['_booth_space']) ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <aside class="custom-map-sheet js-custom-map-sheet" hidden>
        <button class="custom-map-sheet-close js-custom-map-sheet-close" type="button">×</button>
        <div class="muted js-custom-map-sheet-position"></div>
        <h2 class="js-custom-map-sheet-title"></h2>
        <div class="custom-map-sheet-tags">
            <span class="js-custom-map-sheet-status"></span>
        </div>
        <dl class="compact-detail-list">
            <div>
                <dt>預定發布物</dt>
                <dd class="js-custom-map-sheet-book"></dd>
            </div>
            <div>
                <dt>簡介</dt>
                <dd class="js-custom-map-sheet-description"></dd>
            </div>
            <div>
                <dt>備註</dt>
                <dd class="js-custom-map-sheet-note"></dd>
            </div>
        </dl>
    </aside>
</section>

<script>
$(function () {
    var $day = $('.js-c108-custom-day');
    var $map = $('.js-c108-custom-map');
    var mapsByDay = $map.data('maps') || {};
    var currentDay = String($day.data('current-day') || '');
    var currentMap = String($map.data('current-map') || '');

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

    var $viewport = $('.js-custom-map-viewport');
    var $world = $('.js-custom-map-world');
    var initialScale = $world.hasClass('custom-map-world-e123') ? 0.24 : 0.75;
    var state = { x: 40, y: 40, scale: initialScale, rotation: 0 };
    var pointerCache = new Map();
    var dragStart = null;
    var gestureStart = null;

    function clampScale(scale) {
        return Math.max(0.25, Math.min(4, scale));
    }

    function renderTransform() {
        $world.css('transform', 'translate(' + state.x + 'px, ' + state.y + 'px) rotate(' + state.rotation + 'deg) scale(' + state.scale + ')');
        $world
            .toggleClass('custom-map-world-overview', state.scale < 0.62)
            .toggleClass('custom-map-world-detail', state.scale >= 1.12);
    }

    function viewportPoint(clientX, clientY) {
        var rect = $viewport[0].getBoundingClientRect();
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function viewportCenter() {
        var rect = $viewport[0].getBoundingClientRect();
        return { x: rect.width / 2, y: rect.height / 2 };
    }

    function viewportToWorld(point, sourceState) {
        sourceState = sourceState || state;
        var angle = -sourceState.rotation * Math.PI / 180;
        var cos = Math.cos(angle);
        var sin = Math.sin(angle);
        var dx = point.x - sourceState.x;
        var dy = point.y - sourceState.y;

        return {
            x: (dx * cos - dy * sin) / sourceState.scale,
            y: (dx * sin + dy * cos) / sourceState.scale
        };
    }

    function anchorTransform(anchorWorld, anchorViewport, nextScale, nextRotation) {
        nextScale = clampScale(nextScale);
        var angle = nextRotation * Math.PI / 180;
        var cos = Math.cos(angle);
        var sin = Math.sin(angle);

        state.scale = nextScale;
        state.rotation = nextRotation;
        state.x = anchorViewport.x - nextScale * (anchorWorld.x * cos - anchorWorld.y * sin);
        state.y = anchorViewport.y - nextScale * (anchorWorld.x * sin + anchorWorld.y * cos);
        renderTransform();
    }

    function zoomBy(factor, centerX, centerY) {
        var center = centerX === undefined ? viewportCenter() : viewportPoint(centerX, centerY);
        var anchorWorld = viewportToWorld(center);
        anchorTransform(anchorWorld, center, state.scale * factor, state.rotation);
    }

    function rotateBy(degrees) {
        var center = viewportCenter();
        var anchorWorld = viewportToWorld(center);
        anchorTransform(anchorWorld, center, state.scale, state.rotation + degrees);
    }

    function pointerDistance(a, b) {
        return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
    }

    function pointerAngle(a, b) {
        return Math.atan2(b.clientY - a.clientY, b.clientX - a.clientX) * 180 / Math.PI;
    }

    function pointerCenter(a, b) {
        return viewportPoint((a.clientX + b.clientX) / 2, (a.clientY + b.clientY) / 2);
    }

    $viewport.on('wheel', function (event) {
        event.preventDefault();
        zoomBy(event.originalEvent.deltaY < 0 ? 1.12 : 0.88, event.originalEvent.clientX, event.originalEvent.clientY);
    });

    $viewport.on('pointerdown', function (event) {
        this.setPointerCapture(event.originalEvent.pointerId);
        pointerCache.set(event.originalEvent.pointerId, event.originalEvent);
        if (pointerCache.size === 1) {
            dragStart = { x: event.originalEvent.clientX, y: event.originalEvent.clientY, stateX: state.x, stateY: state.y };
        } else if (pointerCache.size === 2) {
            var points = Array.from(pointerCache.values());
            var center = pointerCenter(points[0], points[1]);
            gestureStart = {
                distance: pointerDistance(points[0], points[1]),
                angle: pointerAngle(points[0], points[1]),
                scale: state.scale,
                rotation: state.rotation,
                anchorWorld: viewportToWorld(center),
            };
        }
    });

    $viewport.on('pointermove', function (event) {
        if (!pointerCache.has(event.originalEvent.pointerId)) return;
        pointerCache.set(event.originalEvent.pointerId, event.originalEvent);

        if (pointerCache.size === 1 && dragStart) {
            state.x = dragStart.stateX + event.originalEvent.clientX - dragStart.x;
            state.y = dragStart.stateY + event.originalEvent.clientY - dragStart.y;
            renderTransform();
        } else if (pointerCache.size === 2 && gestureStart) {
            var points = Array.from(pointerCache.values());
            var center = pointerCenter(points[0], points[1]);
            var nextScale = gestureStart.scale * (pointerDistance(points[0], points[1]) / gestureStart.distance);
            var nextRotation = gestureStart.rotation + pointerAngle(points[0], points[1]) - gestureStart.angle;
            anchorTransform(gestureStart.anchorWorld, center, nextScale, nextRotation);
        }
    });

    $viewport.on('pointerup pointercancel pointerleave', function (event) {
        pointerCache.delete(event.originalEvent.pointerId);
        if (pointerCache.size === 0) {
            dragStart = null;
            gestureStart = null;
        } else if (pointerCache.size === 1) {
            var point = Array.from(pointerCache.values())[0];
            dragStart = { x: point.clientX, y: point.clientY, stateX: state.x, stateY: state.y };
            gestureStart = null;
        }
    });

    $('.js-custom-map-zoom-in').on('click', function () { zoomBy(1.2); });
    $('.js-custom-map-zoom-out').on('click', function () { zoomBy(0.8); });
    $('.js-custom-map-rotate-left').on('click', function () { rotateBy(-90); });
    $('.js-custom-map-rotate-right').on('click', function () { rotateBy(90); });
    $('.js-custom-map-reset').on('click', function () {
        state = { x: 40, y: 40, scale: initialScale, rotation: 0 };
        renderTransform();
    });

    $('.js-custom-map-booth').on('click', function (event) {
        event.stopPropagation();
        var $booth = $(this);
        var status = $booth.data('status') || 'unknown';
        var statusLabel = status === 'tracked' ? '追蹤中' : (status === 'known' ? '買過的社團' : '一般社團');
        $('.js-custom-map-sheet-position').text($booth.data('position') || '');
        $('.js-custom-map-sheet-title').text($booth.data('name') || '(未命名)');
        $('.js-custom-map-sheet-status').text(statusLabel);
        $('.js-custom-map-sheet-book').text($booth.data('book-name') || '未設定');
        $('.js-custom-map-sheet-description').text($booth.data('description') || '未設定');
        $('.js-custom-map-sheet-note').text($booth.data('note') || '未設定');
        $('.js-custom-map-sheet').prop('hidden', false);
    });

    $('.js-custom-map-sheet-close').on('click', function () {
        $('.js-custom-map-sheet').prop('hidden', true);
    });

    renderTransform();
});
</script>
<?= $this->endSection() ?>
