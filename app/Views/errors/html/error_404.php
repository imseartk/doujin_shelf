<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Arial, sans-serif; background: #f7f8f5; color: #1d252c; }
        main { width: min(560px, calc(100% - 32px)); background: #fff; border: 1px solid #d9ded8; border-radius: 8px; padding: 28px; }
        h1 { margin: 0 0 10px; font-size: 36px; }
        p { margin: 0; color: #6b747d; line-height: 1.6; }
    </style>
</head>
<body>
    <main>
        <h1>404</h1>
        <p><?= ENVIRONMENT !== 'production' ? esc($message ?? 'Page not found') : '找不到這個頁面。' ?></p>
    </main>
</body>
</html>
