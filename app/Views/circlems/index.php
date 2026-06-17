<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head">
    <div>
        <h1>Circle.ms 連線</h1>
        <p>連線 sandbox API，確認 OAuth token 與基本 API 是否可用。</p>
    </div>
</section>

<?php if (! $configured): ?>
    <div class="notice error">
        <div>Circle.ms 設定不完整。</div>
        <?php foreach ($missingConfigKeys as $key): ?>
            <div><?= esc($key) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="panel compact-panel">
    <h2>連線狀態</h2>
    <?php if ($token): ?>
        <p>已取得 token。</p>
        <dl class="status-list">
            <dt>到期時間</dt>
            <dd><?= esc($token['expires_at'] ?? '未知') ?><?= $isExpired ? '（需要更新）' : '' ?></dd>
            <dt>最後測試</dt>
            <dd><?= esc($token['last_tested_at'] ?? '尚未測試') ?></dd>
            <?php if (! empty($token['last_error'])): ?>
                <dt>最後錯誤</dt>
                <dd><?= esc($token['last_error']) ?></dd>
            <?php endif; ?>
        </dl>
    <?php else: ?>
        <p>尚未連線 Circle.ms。</p>
    <?php endif; ?>

    <div class="form-actions standalone">
        <?php if ($configured): ?>
            <a class="button primary" href="/circlems/connect">連線 Circle.ms</a>
            <?php if ($token): ?>
                <form method="post" action="/circlems/test">
                    <?= csrf_field() ?>
                    <button class="button" type="submit">測試 API</button>
                </form>
                <form method="post" action="/circlems/refresh">
                    <?= csrf_field() ?>
                    <button class="button ghost" type="submit">更新 token</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($token): ?>
    <section class="panel circlems-search-panel">
        <h2>社團搜尋測試</h2>
        <p>使用目前 token 查詢最新活動中的社團資料。</p>
        <form class="inline-form" method="post" action="/circlems/search-circle">
            <?= csrf_field() ?>
            <input type="text" name="circle_name" value="<?= esc($circleSearch['query'] ?? '08BASE') ?>" placeholder="社團名稱">
            <button class="button" type="submit">搜尋社團</button>
        </form>
        <form class="inline-form" method="post" action="/circlems/sample-circles">
            <?= csrf_field() ?>
            <button class="button ghost" type="submit">抓樣本社團</button>
        </form>

        <?php if (! empty($circleSearch)): ?>
            <dl class="status-list">
                <dt>搜尋社團</dt>
                <dd><?= esc($circleSearch['circleName']) ?></dd>
                <dt>活動 ID</dt>
                <dd><?= esc((string) $circleSearch['eventId']) ?></dd>
                <dt>命中筆數</dt>
                <dd><?= esc((string) $circleSearch['count']) ?> / <?= esc((string) $circleSearch['maxCount']) ?></dd>
            </dl>
            <?php if (! empty($circleSearch['names'])): ?>
                <div class="circlems-sample-names">
                    <div class="field-label">可測試社團</div>
                    <div class="tag-list">
                        <?php foreach ($circleSearch['names'] as $name): ?>
                            <span class="tag-chip"><?= esc($name) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <pre class="api-preview"><?= esc(json_encode($circleSearch['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?= $this->endSection() ?>
