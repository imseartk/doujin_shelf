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
        <h2>活動與社團搜尋</h2>
        <p>選擇活動後搜尋社團；社團名留空時會列出該活動的樣本資料。</p>
        <?php if ($eventError): ?>
            <div class="notice error"><?= esc($eventError) ?></div>
        <?php endif; ?>
        <form class="inline-form" method="post" action="/circlems/search-circle">
            <?= csrf_field() ?>
            <select name="event_id">
                <?php foreach ($events as $event): ?>
                    <?php $selectedEventId = (int) ($circleSearch['eventId'] ?? $latestEventId ?? 0); ?>
                    <option value="<?= esc((string) $event['eventId']) ?>" <?= (int) $event['eventId'] === $selectedEventId ? 'selected' : '' ?>>
                        <?= esc($event['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="circle_name" value="<?= esc($circleSearch['query'] ?? '08BASE') ?>" placeholder="社團名稱">
            <input class="short-input" type="number" name="page" value="<?= esc((string) ($circleSearch['page'] ?? 1)) ?>" min="1" placeholder="頁">
            <button class="button" type="submit">搜尋社團</button>
        </form>
        <form class="inline-form" method="post" action="/circlems/sample-circles">
            <?= csrf_field() ?>
            <button class="button ghost" type="submit">抓樣本社團</button>
        </form>
        <form class="inline-form" method="post" action="/circlems/catalog-base">
            <?= csrf_field() ?>
            <select name="event_id">
                <?php foreach ($events as $event): ?>
                    <?php $selectedEventId = (int) ($circleSearch['eventId'] ?? $latestEventId ?? 0); ?>
                    <option value="<?= esc((string) $event['eventId']) ?>" <?= (int) $event['eventId'] === $selectedEventId ? 'selected' : '' ?>>
                        <?= esc($event['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button ghost" type="submit">取得初期資料庫 URL</button>
        </form>
        <form class="inline-form" method="post" action="/circlems/catalog-download-text">
            <?= csrf_field() ?>
            <select name="event_id">
                <?php foreach ($events as $event): ?>
                    <?php $selectedEventId = (int) ($circleSearch['eventId'] ?? $latestEventId ?? 0); ?>
                    <option value="<?= esc((string) $event['eventId']) ?>" <?= (int) $event['eventId'] === $selectedEventId ? 'selected' : '' ?>>
                        <?= esc($event['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button ghost" type="submit">下載並檢查 text DB</button>
        </form>

        <?php if (! empty($circleSearch)): ?>
            <dl class="status-list">
                <dt>搜尋社團</dt>
                <dd><?= esc($circleSearch['circleName']) ?></dd>
                <dt>活動 ID</dt>
                <dd><?= esc((string) $circleSearch['eventId']) ?></dd>
                <dt>命中筆數</dt>
                <dd><?= esc((string) $circleSearch['count']) ?> / <?= esc((string) $circleSearch['maxCount']) ?></dd>
                <dt>頁數</dt>
                <dd><?= esc((string) ($circleSearch['page'] ?? 1)) ?></dd>
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

            <?php if (! empty($circleSearch['rows'])): ?>
                <div class="circlems-result-list">
                    <?php foreach ($circleSearch['rows'] as $row): ?>
                        <article class="circlems-result-card">
                            <?php if ($row['cutUrl'] !== ''): ?>
                                <img class="circlems-cut" src="<?= esc($row['cutUrl']) ?>" alt="">
                            <?php endif; ?>
                            <div class="circlems-result-body">
                                <div class="circlems-result-head">
                                    <div>
                                        <h3><?= esc($row['name'] !== '' ? $row['name'] : '(no name)') ?></h3>
                                        <?php if ($row['nameKana'] !== ''): ?>
                                            <div class="muted"><?= esc($row['nameKana']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="circlems-meta">
                                        <?php if ($row['wcid'] !== ''): ?><span>WCID <?= esc($row['wcid']) ?></span><?php endif; ?>
                                        <?php if ($row['genre'] !== ''): ?><span>Genre <?= esc($row['genre']) ?></span><?php endif; ?>
                                        <?php if ($row['circlemsId'] !== ''): ?><span>Circle.ms <?= esc($row['circlemsId']) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($row['wcid'] !== ''): ?>
                                    <div class="circlems-card-actions">
                                        <form method="post" action="/circlems/circle-detail">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= esc((string) $circleSearch['eventId']) ?>">
                                            <input type="hidden" name="wcid" value="<?= esc($row['wcid']) ?>">
                                            <button class="button ghost small" type="submit">詳細</button>
                                        </form>
                                        <form method="post" action="/circlems/circle-books">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= esc((string) $circleSearch['eventId']) ?>">
                                            <input type="hidden" name="wcid" value="<?= esc($row['wcid']) ?>">
                                            <input type="hidden" name="page" value="1">
                                            <button class="button ghost small" type="submit">頒布物</button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <?php if ($row['description'] !== ''): ?>
                                    <p class="circlems-description"><?= esc(mb_strimwidth($row['description'], 0, 180, '...', 'UTF-8')) ?></p>
                                <?php endif; ?>

                                <?php if ($row['tag'] !== ''): ?>
                                    <div class="tag-list">
                                        <?php foreach (array_filter(array_map('trim', explode(',', $row['tag']))) as $tag): ?>
                                            <span class="tag-chip"><?= esc($tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="circlems-links">
                                    <?php if ($row['url'] !== ''): ?><a href="<?= esc($row['url']) ?>" target="_blank" rel="noopener">Web</a><?php endif; ?>
                                    <?php if ($row['pixivUrl'] !== ''): ?><a href="<?= esc($row['pixivUrl']) ?>" target="_blank" rel="noopener">Pixiv</a><?php endif; ?>
                                    <?php if ($row['twitterUrl'] !== ''): ?><a href="<?= esc($row['twitterUrl']) ?>" target="_blank" rel="noopener">X</a><?php endif; ?>
                                    <?php if ($row['clipstudioUrl'] !== ''): ?><a href="<?= esc($row['clipstudioUrl']) ?>" target="_blank" rel="noopener">Clip Studio</a><?php endif; ?>
                                    <?php if ($row['niconicoUrl'] !== ''): ?><a href="<?= esc($row['niconicoUrl']) ?>" target="_blank" rel="noopener">Niconico</a><?php endif; ?>
                                    <?php foreach ($row['stores'] as $store): ?>
                                        <?php if ($store['link'] !== ''): ?>
                                            <a href="<?= esc($store['link']) ?>" target="_blank" rel="noopener"><?= esc($store['name']) ?></a>
                                        <?php else: ?>
                                            <span><?= esc($store['name']) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <pre class="api-preview"><?= esc(json_encode($circleSearch['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
        <?php endif; ?>
    </section>

    <?php if (! empty($circleProbe)): ?>
        <section class="panel circlems-search-panel">
            <h2><?= esc($circleProbe['title'] ?? 'API 測試結果') ?></h2>
            <dl class="status-list">
                <dt>活動 ID</dt>
                <dd><?= esc((string) ($circleProbe['eventId'] ?? '')) ?></dd>
                <dt>WCID</dt>
                <dd><?= esc((string) ($circleProbe['wcid'] ?? '')) ?></dd>
                <?php if (($circleProbe['type'] ?? '') === 'books'): ?>
                    <dt>命中筆數</dt>
                    <dd><?= esc((string) ($circleProbe['count'] ?? 0)) ?> / <?= esc((string) ($circleProbe['maxCount'] ?? 0)) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (($circleProbe['type'] ?? '') === 'catalog' && ! empty($circleProbe['catalogUrls'])): ?>
                <div class="catalog-url-list">
                    <?php foreach ($circleProbe['catalogUrls'] as $catalogUrl): ?>
                        <div class="catalog-url-item">
                            <div>
                                <strong><?= esc($catalogUrl['key']) ?></strong>
                                <div class="muted"><?= esc($catalogUrl['kind']) ?></div>
                            </div>
                            <a class="button small ghost" href="<?= esc($catalogUrl['url']) ?>" target="_blank" rel="noopener">開啟</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (($circleProbe['type'] ?? '') === 'catalog_download' && ! empty($circleProbe['catalogDownload'])): ?>
                <?php $download = $circleProbe['catalogDownload']; ?>
                <dl class="status-list catalog-download-status">
                    <dt>URL Key</dt>
                    <dd><?= esc($download['urlKey']) ?></dd>
                    <dt>壓縮檔</dt>
                    <dd><?= esc($download['archivePath']) ?> / <?= number_format((int) $download['archiveSize']) ?> bytes</dd>
                    <dt>SQLite DB</dt>
                    <dd><?= esc($download['dbPath']) ?> / <?= number_format((int) $download['dbSize']) ?> bytes</dd>
                    <dt>MD5</dt>
                    <dd><?= $download['md5Ok'] ? 'OK' : 'NG' ?> / <?= esc($download['actualMd5']) ?></dd>
                    <dt>SQLite3</dt>
                    <dd><?= ! empty($download['sqlite']['available']) ? '可用' : esc($download['sqlite']['message'] ?? '不可用') ?></dd>
                </dl>

                <?php if (! empty($download['sqlite']['counts'])): ?>
                    <div class="catalog-url-list">
                        <?php foreach ($download['sqlite']['counts'] as $table => $count): ?>
                            <div class="catalog-url-item">
                                <strong><?= esc($table) ?></strong>
                                <span><?= number_format((int) $count) ?> rows</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (! empty($download['sqlite']['tables'])): ?>
                    <div class="circlems-sample-names">
                        <div class="field-label">Tables</div>
                        <div class="tag-list">
                            <?php foreach ($download['sqlite']['tables'] as $table): ?>
                                <span class="tag-chip"><?= esc($table) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (! empty($circleProbe['circle'])): ?>
                <?php $row = $circleProbe['circle']; ?>
                <article class="circlems-result-card">
                    <?php if ($row['cutUrl'] !== ''): ?>
                        <img class="circlems-cut" src="<?= esc($row['cutUrl']) ?>" alt="">
                    <?php endif; ?>
                    <div class="circlems-result-body">
                        <div class="circlems-result-head">
                            <div>
                                <h3><?= esc($row['name'] !== '' ? $row['name'] : '(no name)') ?></h3>
                                <?php if ($row['nameKana'] !== ''): ?>
                                    <div class="muted"><?= esc($row['nameKana']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="circlems-meta">
                                <?php if ($row['wcid'] !== ''): ?><span>WCID <?= esc($row['wcid']) ?></span><?php endif; ?>
                                <?php if ($row['genre'] !== ''): ?><span>Genre <?= esc($row['genre']) ?></span><?php endif; ?>
                                <?php if ($row['circlemsId'] !== ''): ?><span>Circle.ms <?= esc($row['circlemsId']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($row['description'] !== ''): ?>
                            <p class="circlems-description"><?= esc(mb_strimwidth($row['description'], 0, 240, '...', 'UTF-8')) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endif; ?>

            <?php if (! empty($circleProbe['books'])): ?>
                <div class="circlems-book-list">
                    <?php foreach ($circleProbe['books'] as $book): ?>
                        <article class="circlems-book-card">
                            <?php if ($book['imageUrl'] !== ''): ?>
                                <img class="circlems-book-cover" src="<?= esc($book['imageUrl']) ?>" alt="">
                            <?php endif; ?>
                            <div class="circlems-result-body">
                                <div class="circlems-result-head">
                                    <div>
                                        <h3><?= esc($book['name'] !== '' ? $book['name'] : '(no title)') ?></h3>
                                        <div class="muted">
                                            <?php if ($book['newBook'] === 1): ?>新刊<?php else: ?>既刊/其他<?php endif; ?>
                                            <?php if ($book['price'] !== ''): ?> / <?= esc($book['price']) ?><?php endif; ?>
                                            <?php if ($book['distDate'] !== ''): ?> / <?= esc($book['distDate']) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="circlems-meta">
                                        <?php if ($book['workId'] !== ''): ?><span>Work <?= esc($book['workId']) ?></span><?php endif; ?>
                                        <?php if ($book['genre'] !== ''): ?><span><?= esc($book['genre']) ?></span><?php endif; ?>
                                        <?php if ($book['r18'] === 1): ?><span>R18</span><?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($book['introduction'] !== ''): ?>
                                    <p class="circlems-description"><?= esc(mb_strimwidth($book['introduction'], 0, 220, '...', 'UTF-8')) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <pre class="api-preview"><?= esc(json_encode($circleProbe['result'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
