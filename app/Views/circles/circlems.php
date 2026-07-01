<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $currentLinks = array_filter([
        'X' => $circle['twitter_url'] ?? null,
        'pixiv' => $circle['pixiv_url'] ?? null,
        'Web' => $circle['website_url'] ?? null,
        'BOOTH' => $circle['booth_url'] ?? null,
        'Melon' => $circle['melonbooks_url'] ?? null,
        'Tora' => $circle['toranoana_url'] ?? null,
    ], static fn ($url) => ! empty($url));
?>
<section class="page-head">
    <div>
        <h1>Circle.ms 綁定</h1>
        <p><?= esc($circle['name']) ?></p>
    </div>
    <a class="button ghost" href="/circles">回社團清單</a>
</section>

<?php if ($error): ?>
    <div class="notice error"><?= esc($error) ?></div>
<?php endif; ?>

<section class="panel circlems-binding-panel">
    <h2>本地社團</h2>
    <?php if (! empty($circle['webcatalog_cut_url'])): ?>
        <img class="circlems-cut" src="<?= esc($circle['webcatalog_cut_url']) ?>" alt="">
    <?php endif; ?>
    <dl class="status-list">
        <dt>名稱</dt>
        <dd><?= esc($circle['name']) ?></dd>
        <dt>讀音</dt>
        <dd><?= esc($circle['name_kana'] ?? '未設定') ?></dd>
        <dt>WCID</dt>
        <dd><?= esc($circle['webcatalog_circle_id'] ?? '未綁定') ?></dd>
    </dl>
    <div class="circle-social-links binding-current-links">
        <?php foreach ($currentLinks as $label => $url): ?>
            <a class="social-badge" href="<?= esc($url) ?>" target="_blank" rel="noopener noreferrer"><?= esc($label) ?></a>
        <?php endforeach; ?>
        <?php if ($currentLinks === []): ?>
            <span class="muted">目前沒有社群連結。</span>
        <?php endif; ?>
    </div>
</section>

<form class="toolbar" method="get" action="/circles/<?= (int) $circle['id'] ?>/circlems">
    <select name="event_id">
        <?php foreach ($events as $event): ?>
            <option value="<?= esc((string) $event['eventId']) ?>" <?= (int) $event['eventId'] === (int) $eventId ? 'selected' : '' ?>>
                <?= esc($event['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= esc($q) ?>" placeholder="Circle.ms 搜尋社團名">
    <input class="short-input" type="number" name="page" value="<?= esc((string) $page) ?>" min="1" placeholder="頁">
    <button class="button primary" type="submit">搜尋候選</button>
</form>

<section class="circlems-candidate-list">
    <?php foreach ($candidates as $candidate): ?>
        <article class="circlems-result-card">
            <?php if ($candidate['cut_url'] !== ''): ?>
                <img class="circlems-cut" src="<?= esc($candidate['cut_url']) ?>" alt="">
            <?php endif; ?>
            <div class="circlems-result-body">
                <div class="circlems-result-head">
                    <div>
                        <h3><?= esc($candidate['name'] !== '' ? $candidate['name'] : '(no name)') ?></h3>
                        <?php if ($candidate['name_kana'] !== ''): ?>
                            <div class="muted"><?= esc($candidate['name_kana']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="circlems-meta">
                        <?php if ($candidate['wcid'] !== ''): ?><span>WCID <?= esc($candidate['wcid']) ?></span><?php endif; ?>
                        <?php if ($candidate['genre'] !== ''): ?><span>Genre <?= esc($candidate['genre']) ?></span><?php endif; ?>
                        <?php if ($candidate['circlems_id'] !== ''): ?><span>Circle.ms <?= esc($candidate['circlems_id']) ?></span><?php endif; ?>
                    </div>
                </div>

                <?php if ($candidate['description'] !== ''): ?>
                    <p class="circlems-description"><?= esc(mb_strimwidth($candidate['description'], 0, 180, '...', 'UTF-8')) ?></p>
                <?php endif; ?>

                <?php if ($candidate['tag'] !== ''): ?>
                    <div class="tag-list">
                        <?php foreach (array_filter(array_map('trim', explode(',', $candidate['tag']))) as $tag): ?>
                            <span class="tag-chip"><?= esc($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="circlems-links">
                    <?php if ($candidate['website_url']): ?><a href="<?= esc($candidate['website_url']) ?>" target="_blank" rel="noopener">Web</a><?php endif; ?>
                    <?php if ($candidate['pixiv_url']): ?><a href="<?= esc($candidate['pixiv_url']) ?>" target="_blank" rel="noopener">Pixiv</a><?php endif; ?>
                    <?php if ($candidate['twitter_url']): ?><a href="<?= esc($candidate['twitter_url']) ?>" target="_blank" rel="noopener">X</a><?php endif; ?>
                    <?php foreach ($candidate['stores'] as $store): ?>
                        <a href="<?= esc($store['link']) ?>" target="_blank" rel="noopener"><?= esc($store['name']) ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="circlems-card-actions">
                    <form method="post" action="/circles/<?= (int) $circle['id'] ?>/circlems/bind">
                        <?= csrf_field() ?>
                        <input type="hidden" name="wcid" value="<?= esc($candidate['wcid']) ?>">
                        <input type="hidden" name="circlems_id" value="<?= esc($candidate['circlems_id']) ?>">
                        <input type="hidden" name="webcatalog_cut_url" value="<?= esc($candidate['cut_url'] ?? '') ?>">
                        <button class="button small" type="submit">只綁定</button>
                    </form>
                    <form method="post" action="/circles/<?= (int) $circle['id'] ?>/circlems/bind">
                        <?= csrf_field() ?>
                        <input type="hidden" name="wcid" value="<?= esc($candidate['wcid']) ?>">
                        <input type="hidden" name="circlems_id" value="<?= esc($candidate['circlems_id']) ?>">
                        <input type="hidden" name="webcatalog_cut_url" value="<?= esc($candidate['cut_url'] ?? '') ?>">
                        <input type="hidden" name="import_social" value="1">
                        <input type="hidden" name="name_kana" value="<?= esc($candidate['name_kana']) ?>">
                        <input type="hidden" name="website_url" value="<?= esc($candidate['website_url'] ?? '') ?>">
                        <input type="hidden" name="pixiv_url" value="<?= esc($candidate['pixiv_url'] ?? '') ?>">
                        <input type="hidden" name="twitter_url" value="<?= esc($candidate['twitter_url'] ?? '') ?>">
                        <input type="hidden" name="booth_url" value="<?= esc($candidate['booth_url'] ?? '') ?>">
                        <input type="hidden" name="melonbooks_url" value="<?= esc($candidate['melonbooks_url'] ?? '') ?>">
                        <input type="hidden" name="toranoana_url" value="<?= esc($candidate['toranoana_url'] ?? '') ?>">
                        <button class="button small primary" type="submit">綁定並匯入社群</button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if (! $error && $candidates === []): ?>
        <div class="empty panel">沒有 Circle.ms 候選結果。</div>
    <?php endif; ?>
</section>
<?= $this->endSection() ?>
