<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Doujin Shelf') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/books">Doujin Shelf</a>
        <nav class="nav">
            <a href="/books">書本</a>
            <a href="/books/new">新增</a>
            <a href="/sources">來源統計</a>
            <a href="/shops">店鋪</a>
            <a href="/locations">位置</a>
        </nav>
    </header>

    <main class="page">
        <?php if (session('message')): ?>
            <div class="notice success"><?= esc(session('message')) ?></div>
        <?php endif; ?>
        <?php if (session('error')): ?>
            <div class="notice error"><?= esc(session('error')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </main>

    <script src="/assets/app.js"></script>
</body>
</html>
