<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Doujin Shelf</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Arial, "Noto Sans TC", sans-serif;
            background: #f6f4ef;
            color: #1f2933;
        }
        main {
            width: min(720px, calc(100% - 32px));
        }
        h1 {
            margin: 0 0 12px;
            font-size: 32px;
            font-weight: 700;
        }
        p {
            margin: 0;
            font-size: 17px;
            line-height: 1.7;
        }
        .meta {
            margin-top: 20px;
            color: #5f6b7a;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <main>
        <h1>Doujin Shelf</h1>
        <p>CI4 is ready. The shelf system will start here.</p>
        <p class="meta">Environment: <?= esc(ENVIRONMENT) ?></p>
    </main>
</body>
</html>
