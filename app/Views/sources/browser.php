<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<style>
.browser-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 12px 0; }
.browser-tools select { max-width: 100%; min-width: 0; }
.browser-tools .browser-product { flex: 1 1 320px; }
.browser-screen { width: 100%; max-width: 1200px; aspect-ratio: 3 / 2; display: block; background: #eceff0; border: 1px solid #66797c; touch-action: none; }
.browser-screen:focus { outline: 2px solid #25787e; outline-offset: 2px; }
.browser-status, .browser-result { overflow-wrap: anywhere; }
.browser-result { padding: 12px 0; border-bottom: 1px solid var(--line); }
.browser-tools input { max-width: 100%; min-width: 0; flex: 1 1 220px; }
.browser-key { width: 40px; height: 36px; padding: 0; }
</style>
<section class="page-head"><div><h1>店家驗證</h1><span class="muted">唯讀試跑 · 不更新價格或庫存</span></div><a href="/sources">回購物車</a></section>
<form id="browser-form" class="browser-tools">
    <?= csrf_field() ?>
    <label for="browser-site">平台</label>
    <select id="browser-site"><?php foreach ($groups as $name => $items): ?><option value="<?= esc($name) ?>"><?= esc($name) ?></option><?php endforeach; ?></select>
    <select id="browser-product" class="browser-product" aria-label="商品">
        <?php foreach ($groups as $name => $items): ?>
        <?php foreach ($items as $item): ?><option data-site="<?= esc($name) ?>" value="<?= (int) $item['id'] ?>"><?= esc($item['shop_name'] . ' · ' . $item['title']) ?></option><?php endforeach; ?>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="button primary" id="browser-open">開啟商品</button>
    <button type="button" class="button" id="browser-read" disabled>完成驗證，讀取結果</button>
    <button type="button" class="button ghost" id="browser-close" disabled>關閉工作階段</button>
</form>
<p id="browser-status" class="browser-status" role="status" aria-live="polite">尚未開啟商品</p>
<div id="browser-result" class="browser-result" hidden></div>
<canvas id="browser-screen" class="browser-screen" width="1200" height="800" tabindex="0" aria-label="AWS 遠端商品頁"></canvas>
<form id="browser-text-form" class="browser-tools">
    <input id="browser-text" type="password" autocomplete="off" aria-label="輸入到遠端欄位的文字" placeholder="輸入文字">
    <button type="submit" class="button">送出文字</button>
    <button type="button" class="button browser-key" data-key="Tab" title="下一個欄位" aria-label="下一個欄位">⇥</button>
    <button type="button" class="button browser-key" data-key="Enter" title="Enter" aria-label="Enter">↵</button>
    <button type="button" class="button browser-key" data-key="Backspace" title="刪除前一字" aria-label="刪除前一字">⌫</button>
    <button type="button" class="button browser-key" data-key="ArrowUp" title="向上" aria-label="向上">↑</button>
    <button type="button" class="button browser-key" data-key="ArrowDown" title="向下" aria-label="向下">↓</button>
</form>
<script src="/assets/shop-browser.js?v=<?= filemtime(FCPATH . 'assets/shop-browser.js') ?>" defer></script>
<?= $this->endSection() ?>
