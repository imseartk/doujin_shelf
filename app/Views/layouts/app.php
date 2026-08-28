<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Doujin Shelf') ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(FCPATH . 'assets/app.css') ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body data-hide-covers="<?= covers_hidden() ? '1' : '0' ?>" data-cover-placeholder="<?= esc(cover_placeholder_url()) ?>">
    <?php
        $request = service('request');
        $currentPath = '/' . ltrim($request->getUri()->getPath(), '/');
        $currentQuery = $request->getUri()->getQuery();
        $returnTo = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
        $headerSearch = $currentPath === '/books' ? (string) $request->getGet('q') : '';
    ?>
    <header class="topbar">
        <a class="brand" href="/books">Doujin Shelf</a>
        <nav class="nav">
            <a href="/books">藏書清單</a>
            <?php if (admin_unlocked()): ?>
                <a href="/wishlist">願望清單</a>
                <a href="/circles">社團清單</a>
                <a href="/c108/map">C108</a>
                <a href="/sources">購物車</a>
                <a href="/orders">訂單</a>
                <a href="/shops">店鋪</a>
                <a href="/locations">位置</a>
            <?php endif; ?>
            <a href="/manage"><?= admin_unlocked() ? '管理中' : '管理' ?></a>
        </nav>
        <div class="topbar-tools">
            <form class="topbar-search" method="get" action="/books">
                <input type="search" name="q" value="<?= esc($headerSearch) ?>" placeholder="搜尋藏書">
                <button class="button small" type="submit">搜尋</button>
            </form>
            <button class="button small ghost kana-keyboard-toggle js-kana-keyboard-toggle" type="button">かな</button>
            <form class="cover-privacy-form" method="post" action="/preferences/cover-privacy">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                <input type="hidden" name="hide_covers" value="<?= covers_hidden() ? '0' : '1' ?>">
                <button class="button small <?= covers_hidden() ? 'primary' : 'ghost' ?>" type="submit"><?= covers_hidden() ? '顯示圖片' : '遮蔽圖片' ?></button>
            </form>
        </div>
    </header>

    <div class="kana-keyboard js-kana-keyboard" hidden>
        <div class="kana-keyboard-head">
            <strong>五十音</strong>
            <div class="kana-keyboard-actions">
                <button class="button tiny primary js-kana-mode" type="button" data-mode="hiragana">平</button>
                <button class="button tiny ghost js-kana-mode" type="button" data-mode="katakana">片</button>
                <button class="kana-keyboard-close js-kana-keyboard-close" type="button" aria-label="關閉">×</button>
            </div>
        </div>
        <div class="kana-keyboard-grid js-kana-keyboard-grid"></div>
    </div>

    <main class="page">
        <?php if (session('message')): ?>
            <div class="notice success"><?= esc(session('message')) ?></div>
        <?php endif; ?>
        <?php if (session('error')): ?>
            <div class="notice error"><?= esc(session('error')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </main>

    <script src="/assets/app.js?v=<?= filemtime(FCPATH . 'assets/app.js') ?>"></script>
</body>
</html>
