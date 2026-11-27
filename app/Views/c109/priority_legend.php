<link rel="stylesheet" href="/assets/c109-priority.css?v=<?= filemtime(FCPATH . 'assets/c109-priority.css') ?>">
<div class="c109-priority-legend" aria-label="地圖圖例">
<?php foreach (['unknown' => '一般社團', 'known' => '買過／普通', 'high' => '優先', 'must' => '必看', 'ignore' => '忽略', 'blacklist' => '黑名單'] as $key => $label): ?>
    <span class="c109-priority-<?= esc($key) ?>"><i aria-hidden="true"></i><?= esc($label) ?></span>
<?php endforeach; ?>
</div>
