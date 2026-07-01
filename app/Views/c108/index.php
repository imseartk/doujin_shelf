<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $pageUrl = static function (int $targetPage) use ($q, $day, $relation, $priority): string {
        $params = array_filter([
            'q' => $q,
            'day' => $day,
            'relation' => $relation,
            'priority' => $priority,
            'page' => $targetPage > 1 ? $targetPage : '',
        ], static fn ($value) => $value !== '' && $value !== null);

        return '/c108' . ($params === [] ? '' : '?' . http_build_query($params));
    };

    $rowClass = static function (array $row): string {
        if (! empty($row['is_tracked'])) {
            return 'c108-row-tracked';
        }
        if (! empty($row['local_circle_id'])) {
            return 'c108-row-known';
        }

        return 'c108-row-unknown';
    };
?>
<section class="page-head">
    <div>
        <h1>C108</h1>
        <p>確認這次活動的社團、攤位與巡迴候選。</p>
    </div>
    <a class="button ghost" href="/c108/map">地圖</a>
</section>

<section class="c108-summary">
    <div><strong><?= number_format((int) $summary['total']) ?></strong><span>全部社團</span></div>
    <div><strong><?= number_format((int) $summary['known']) ?></strong><span>買過 / 已建檔</span></div>
    <div><strong><?= number_format((int) $summary['tracked']) ?></strong><span>追蹤中</span></div>
</section>

<form class="toolbar" method="get" action="/c108">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋社團、作者、攤位、備註">
    <select name="day">
        <option value="">全部日別</option>
        <option value="1" <?= $day === '1' ? 'selected' : '' ?>>1日目</option>
        <option value="2" <?= $day === '2' ? 'selected' : '' ?>>2日目</option>
    </select>
    <select name="relation">
        <option value="">全部社團</option>
        <option value="unknown" <?= $relation === 'unknown' ? 'selected' : '' ?>>一般社團</option>
        <option value="known" <?= $relation === 'known' ? 'selected' : '' ?>>買過的社團</option>
        <option value="tracked" <?= $relation === 'tracked' ? 'selected' : '' ?>>追蹤中</option>
    </select>
    <select name="priority">
        <option value="">全部優先度</option>
        <option value="must" <?= $priority === 'must' ? 'selected' : '' ?>>必看</option>
        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>優先</option>
        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>普通</option>
    </select>
    <button class="button primary" type="submit">搜尋</button>
    <a class="button ghost" href="/c108">清除</a>
</form>

<div class="c108-legend">
    <span class="c108-legend-item c108-row-unknown">一般社團</span>
    <span class="c108-legend-item c108-row-known">買過的社團</span>
    <span class="c108-legend-item c108-row-tracked">追蹤中</span>
</div>

<div class="list-summary">
    <span>共 <?= number_format((int) $total) ?> 筆</span>
    <span>第 <?= number_format((int) $page) ?> / <?= number_format((int) $totalPages) ?> 頁</span>
</div>

<div class="table-wrap">
    <table class="data-table c108-table">
        <thead>
            <tr>
                <th>圖片</th>
                <th>社團名</th>
                <th>是否追蹤</th>
                <th>優先度</th>
                <th>C108資訊</th>
                <th>備註</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr class="<?= esc($rowClass($row)) ?>">
                    <td>
                        <?php if (! empty($row['webcatalog_cut_url'])): ?>
                            <img class="c108-cut" src="<?= esc($row['webcatalog_cut_url']) ?>" alt="">
                        <?php else: ?>
                            <div class="c108-cut-empty">no image</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="title-main"><?= esc($row['circle_name']) ?></div>
                        <?php if (! empty($row['circle_kana'])): ?>
                            <div class="muted"><?= esc($row['circle_kana']) ?></div>
                        <?php endif; ?>
                        <?php if (! empty($row['pen_name'])): ?>
                            <div class="muted"><?= esc($row['pen_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (! empty($row['is_tracked'])): ?>
                            <span class="social-badge">追蹤中</span>
                        <?php elseif (! empty($row['local_circle_id'])): ?>
                            <span class="social-badge">買過</span>
                        <?php else: ?>
                            <span class="muted">一般</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (! empty($row['priority'])): ?>
                            <?= esc(['must' => '必看', 'high' => '優先', 'normal' => '普通'][$row['priority']] ?? $row['priority']) ?>
                        <?php else: ?>
                            <span class="muted">未設定</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="title-main"><?= esc($row['position_label'] ?? '') ?></div>
                        <div class="muted">
                            <?= esc(trim((string) ($row['map_name'] ?? '') . ' ' . (string) ($row['area_name'] ?? '') . ' ' . (string) ($row['space_label'] ?? ''))) ?>
                        </div>
                        <div class="muted">x/y <?= esc((string) ($row['xpos'] ?? '')) ?>, <?= esc((string) ($row['ypos'] ?? '')) ?></div>
                    </td>
                    <td>
                        <?php if (! empty($row['note'])): ?>
                            <?= nl2br(esc($row['note'])) ?>
                        <?php else: ?>
                            <span class="muted">未設定</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="empty">沒有符合條件的 C108 社團。</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <a class="button small <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : esc($pageUrl($page - 1)) ?>">上一頁</a>
        <?php
            $start = max(1, $page - 3);
            $end = min($totalPages, $page + 3);
        ?>
        <?php if ($start > 1): ?>
            <a class="button small" href="<?= esc($pageUrl(1)) ?>">1</a>
            <?php if ($start > 2): ?><span class="pagination-gap">...</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a class="button small <?= $i === $page ? 'primary' : '' ?>" href="<?= esc($pageUrl($i)) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="pagination-gap">...</span><?php endif; ?>
            <a class="button small" href="<?= esc($pageUrl($totalPages)) ?>"><?= $totalPages ?></a>
        <?php endif; ?>
        <a class="button small <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : esc($pageUrl($page + 1)) ?>">下一頁</a>
    </nav>
<?php endif; ?>
<?= $this->endSection() ?>
