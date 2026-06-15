<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<section class="page-head">
    <div>
        <h1>管理</h1>
        <p>輸入暗碼後可使用新增、編輯、上傳與後台管理功能。</p>
    </div>
</section>

<?php if (admin_unlocked()): ?>
    <section class="panel compact-panel">
        <h2>已解鎖</h2>
        <p>目前可以使用完整管理功能。</p>
        <div class="form-actions standalone">
            <a class="button primary" href="/books">回藏書清單</a>
        </div>
    </section>
<?php else: ?>
    <form class="panel compact-panel" method="post" action="/manage">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
        <label>管理暗碼
            <input type="password" name="passcode" autocomplete="current-password" required autofocus <?= $configured ? '' : 'disabled' ?>>
        </label>
        <?php if (! $configured): ?>
            <p class="field-hint">尚未在 .env 設定 admin.passwordHash。</p>
        <?php endif; ?>
        <div class="form-actions standalone">
            <button class="button primary" type="submit" <?= $configured ? '' : 'disabled' ?>>解鎖</button>
            <a class="button ghost" href="/books">回藏書清單</a>
        </div>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>
