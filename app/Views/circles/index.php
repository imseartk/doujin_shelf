<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $returnTo = '/circles';
    $queryParams = array_filter([
        'q' => $q,
        'tracked' => $tracked,
        'priority' => $priority,
        'page' => $page > 1 ? $page : '',
    ], static fn ($value) => $value !== '' && $value !== null);
    if ($queryParams !== []) {
        $returnTo .= '?' . http_build_query($queryParams);
    }

    $socialLinks = static function (array $circle): array {
        return array_filter([
            'X' => $circle['twitter_url'] ?? null,
            'pixiv' => $circle['pixiv_url'] ?? null,
            'Web' => $circle['website_url'] ?? null,
            'BOOTH' => $circle['booth_url'] ?? null,
            'Melon' => $circle['melonbooks_url'] ?? null,
            'Tora' => $circle['toranoana_url'] ?? null,
        ], static fn ($url) => ! empty($url));
    };

    $pageUrl = static function (int $targetPage) use ($q, $tracked, $priority): string {
        $params = array_filter([
            'q' => $q,
            'tracked' => $tracked,
            'priority' => $priority,
            'page' => $targetPage > 1 ? $targetPage : '',
        ], static fn ($value) => $value !== '' && $value !== null);

        return '/circles' . ($params === [] ? '' : '?' . http_build_query($params));
    };
?>
<section class="page-head">
    <div>
        <h1>社團清單</h1>
        <p>管理常買社團、追蹤優先度、社群連結與備註。</p>
    </div>
</section>

<form class="toolbar" method="get" action="/circles">
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="搜尋社團、首字、備註">
    <select name="tracked">
        <option value="">追蹤狀態</option>
        <option value="1" <?= $tracked === '1' ? 'selected' : '' ?>>追蹤中</option>
        <option value="0" <?= $tracked === '0' ? 'selected' : '' ?>>未追蹤</option>
    </select>
    <select name="priority">
        <option value="">全部優先度</option>
        <?php foreach ($priorityOptions as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $priority === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button primary" type="submit">搜尋</button>
    <a class="button ghost" href="/circles">清除</a>
</form>

<div class="list-summary">
    <span>共 <?= number_format((int) $total) ?> 個社團</span>
    <span>第 <?= number_format((int) $page) ?> / <?= number_format((int) $totalPages) ?> 頁</span>
</div>

<input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

<div class="table-wrap">
    <table class="data-table circles-table">
        <thead>
            <tr>
                <th>是否追蹤</th>
                <th>優先度</th>
                <th>名稱</th>
                <th>Circle.ms</th>
                <th>社群資訊</th>
                <th>備註</th>
                <th>相關書籍</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($circles as $circle): ?>
            <?php
                $circleId = (int) $circle['id'];
                $formId = 'circle-form-' . $circleId;
                $isTracked = ! empty($circle['is_tracked']);
                $links = $socialLinks($circle);
            ?>
            <tr data-circle-row="<?= $circleId ?>">
                <td>
                    <button
                        class="button small circle-track-toggle js-circle-track-toggle <?= $isTracked ? 'primary' : 'ghost' ?>"
                        type="button"
                        data-url="/circles/<?= $circleId ?>/track"
                    ><?= $isTracked ? '追蹤中' : '未追蹤' ?></button>
                </td>
                <td>
                    <select form="<?= esc($formId) ?>" name="priority">
                        <?php foreach ($priorityOptions as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= ($circle['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <div class="circle-name"><?= esc($circle['name']) ?></div>
                    <input class="circle-kana-input" form="<?= esc($formId) ?>" name="name_kana" value="<?= esc($circle['name_kana'] ?? '') ?>" placeholder="首字 / 讀音">
                </td>
                <td>
                    <?php if (! empty($circle['webcatalog_circle_id'])): ?>
                        <div class="social-badge">ID <?= esc($circle['webcatalog_circle_id']) ?></div>
                    <?php else: ?>
                        <div class="muted">未綁定</div>
                    <?php endif; ?>
                    <a class="button small ghost circlems-bind-link" href="/circles/<?= $circleId ?>/circlems">候選</a>
                </td>
                <td>
                    <div class="circle-social-links">
                        <?php foreach ($links as $label => $url): ?>
                            <a class="social-badge" href="<?= esc($url) ?>" target="_blank" rel="noopener noreferrer"><?= esc($label) ?></a>
                        <?php endforeach; ?>
                        <?php if ($links === []): ?>
                            <span class="muted">未設定</span>
                        <?php endif; ?>
                    </div>
                    <details class="circle-social-editor">
                        <summary>編輯連結</summary>
                        <input form="<?= esc($formId) ?>" name="twitter_url" value="<?= esc($circle['twitter_url'] ?? '') ?>" placeholder="X URL">
                        <input form="<?= esc($formId) ?>" name="pixiv_url" value="<?= esc($circle['pixiv_url'] ?? '') ?>" placeholder="pixiv URL">
                        <input form="<?= esc($formId) ?>" name="website_url" value="<?= esc($circle['website_url'] ?? '') ?>" placeholder="Website URL">
                        <input form="<?= esc($formId) ?>" name="booth_url" value="<?= esc($circle['booth_url'] ?? '') ?>" placeholder="BOOTH URL">
                        <input form="<?= esc($formId) ?>" name="melonbooks_url" value="<?= esc($circle['melonbooks_url'] ?? '') ?>" placeholder="Melonbooks URL">
                        <input form="<?= esc($formId) ?>" name="toranoana_url" value="<?= esc($circle['toranoana_url'] ?? '') ?>" placeholder="Toranoana URL">
                    </details>
                </td>
                <td>
                    <textarea form="<?= esc($formId) ?>" name="note" rows="3" placeholder="備註"><?= esc($circle['note'] ?? '') ?></textarea>
                </td>
                <td>
                    <a href="/books?q=<?= rawurlencode($circle['name']) ?>">
                        <?= number_format((int) $circle['book_count']) ?> 本
                    </a>
                    <?php if ((int) $circle['wishlist_count'] > 0): ?>
                        <div class="muted">願望 <?= number_format((int) $circle['wishlist_count']) ?> 本</div>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <form id="<?= esc($formId) ?>" method="post" action="/circles/<?= $circleId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                        <button class="button small" type="submit">儲存</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($circles === []): ?>
            <tr><td colspan="8" class="empty">沒有符合條件的社團。</td></tr>
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
