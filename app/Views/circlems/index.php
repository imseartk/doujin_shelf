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
<?= $this->endSection() ?>
