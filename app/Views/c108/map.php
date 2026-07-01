<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $days = [];
    foreach ($maps as $option) {
        $days[(string) $option['day']] = true;
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
    <select name="day">
        <?php foreach (array_keys($days) as $optionDay): ?>
            <option value="<?= esc($optionDay) ?>" <?= $optionDay === $day ? 'selected' : '' ?>>
                <?= esc($optionDay) ?>日目
            </option>
        <?php endforeach; ?>
    </select>
    <select name="map">
        <?php foreach ($maps as $option): ?>
            <?php if ((string) $option['day'] !== $day) { continue; } ?>
            <?php $optionMap = (string) $option['map_filename']; ?>
            <option value="<?= esc($optionMap) ?>" <?= $optionMap === $map ? 'selected' : '' ?>>
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

<div class="c108-legend">
    <span class="c108-legend-item c108-row-unknown">一般社團</span>
    <span class="c108-legend-item c108-row-known">買過的社團</span>
    <span class="c108-legend-item c108-row-tracked">追蹤中</span>
</div>

<div class="list-summary">
    <span><?= esc($day) ?>日目 / <?= esc($map) ?></span>
    <span><?= number_format(count($rows)) ?> 個點</span>
</div>

<?php if ($image === null): ?>
    <div class="notice error">找不到地圖底圖。請先在 Circle.ms 頁面匯出 common images。</div>
<?php else: ?>
    <section class="c108-map-scroll">
        <div class="c108-map-stage" style="width: <?= (int) $image['width'] ?>px; height: <?= (int) $image['height'] ?>px;">
            <img class="c108-map-image" src="<?= esc($image['url']) ?>" alt="">
            <?php foreach ($rows as $row): ?>
                <?php
                    $left = max(0, (int) ($row['xpos'] ?? 0));
                    $top = max(0, (int) ($row['ypos'] ?? 0));
                    $label = trim((string) ($row['position_label'] ?? '') . ' ' . (string) ($row['circle_name'] ?? ''));
                ?>
                <a
                    class="c108-map-marker <?= esc($markerClass($row)) ?>"
                    href="/c108?q=<?= rawurlencode((string) ($row['circle_name'] ?? '')) ?>"
                    style="left: <?= $left ?>px; top: <?= $top ?>px;"
                    title="<?= esc($label) ?>"
                ></a>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="c108-map-list">
        <?php foreach ($rows as $row): ?>
            <a class="c108-map-list-item <?= esc(! empty($row['is_tracked']) ? 'c108-row-tracked' : (! empty($row['local_circle_id']) ? 'c108-row-known' : 'c108-row-unknown')) ?>" href="/c108?q=<?= rawurlencode((string) ($row['circle_name'] ?? '')) ?>">
                <strong><?= esc($row['circle_name'] ?? '') ?></strong>
                <span><?= esc($row['position_label'] ?? '') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
