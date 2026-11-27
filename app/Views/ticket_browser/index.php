<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<style>
.ticket-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}
.ticket-screen{width:100%;max-width:1200px;aspect-ratio:3/2;display:block;background:#e9eeee;border:1px solid #637779;touch-action:none}
.ticket-screen:focus{outline:2px solid #25787e}
.ticket-toolbar input{min-width:0;flex:1 1 180px;max-width:500px}
.ticket-key{width:42px;height:38px;padding:0}
#ticket-status{overflow-wrap:anywhere}
</style>
<section class="page-head"><h1>TicketPay</h1><a href="/">&#x56DE;&#x9996;&#x9801;</a></section>
<form id="ticket-form" class="ticket-toolbar">
<?= csrf_field() ?>
<button type="submit" id="ticket-open" class="button primary">&#x958B;&#x555F;&#x700F;&#x89BD;&#x5668;</button>
<button type="button" id="ticket-close" class="button" disabled>&#x95DC;&#x9589;&#x4E26;&#x6E05;&#x9664;&#x767B;&#x5165;&#x72C0;&#x614B;</button>
</form>
<p id="ticket-status" role="status" aria-live="polite">&#x5C1A;&#x672A;&#x958B;&#x555F;</p>
<canvas id="ticket-screen" class="ticket-screen" width="1200" height="800" tabindex="0" aria-label="TicketPay remote browser"></canvas>
<form id="ticket-text-form" class="ticket-toolbar" autocomplete="off">
<input id="ticket-text" type="password" autocomplete="off" aria-label="Remote input" maxlength="2000">
<button type="submit" class="button">&#x9001;&#x51FA;&#x6587;&#x5B57;</button>
<button type="button" class="button ticket-key" data-key="Tab" title="Tab">&#x21E5;</button>
<button type="button" class="button ticket-key" data-key="Enter" title="Enter">&#x21B5;</button>
<button type="button" class="button ticket-key" data-key="Backspace" title="Backspace">&#x232B;</button>
<button type="button" class="button ticket-key" data-key="Escape" title="Escape">Esc</button>
</form>
<script src="/assets/ticket-browser.js?v=<?= filemtime(FCPATH . 'assets/ticket-browser.js') ?>" defer></script>
<?= $this->endSection() ?>
