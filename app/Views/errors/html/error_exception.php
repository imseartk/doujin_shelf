<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Application Error') ?></title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f7f8f5; color: #1d252c; }
        main { width: min(1100px, calc(100% - 32px)); margin: 32px auto; background: #fff; border: 1px solid #d9ded8; border-radius: 8px; padding: 24px; }
        h1 { margin: 0 0 12px; font-size: 26px; }
        p { color: #6b747d; line-height: 1.6; }
        code, pre { font-family: Consolas, monospace; }
        pre { overflow: auto; background: #1f2933; color: #f5f7f8; border-radius: 6px; padding: 14px; }
    </style>
</head>
<body>
    <main>
        <h1><?= esc($title ?? 'Application Error') ?></h1>
        <p><?= nl2br(esc($message ?? ($exception->getMessage() ?? 'Unknown error'))) ?></p>
        <?php if (isset($file, $line)): ?>
            <p><code><?= esc(clean_path($file)) ?>:<?= esc((string) $line) ?></code></p>
        <?php endif; ?>
        <?php if (defined('SHOW_DEBUG_BACKTRACE') && SHOW_DEBUG_BACKTRACE && isset($exception)): ?>
            <pre><?= esc($exception) ?></pre>
        <?php endif; ?>
    </main>
</body>
</html>
