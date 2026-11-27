<?php $statuses = ['owned' => '已擁有', 'ordered' => '已訂購', 'wishlist' => '願望清單', 'blacklisted' => '黑名單']; ?>
<?php if (! $books): ?>
    <p>尚無相關書籍。</p>
<?php else: ?>
    <p class="muted"><?= count($books) ?> 本</p>
    <div class="circle-books-list">
    <?php foreach ($books as $book): ?>
        <article class="circle-book">
            <?php $cover = cover_display_url($book['cover_url']); ?>
            <?php if ($cover !== ''): ?>
                <img class="circle-book-cover" src="<?= esc($cover, 'attr') ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="circle-book-cover circle-book-empty">無封面</span>
            <?php endif; ?>
            <div class="circle-book-details">
                <span class="status"><?= esc($statuses[$book['status']] ?? $book['status']) ?></span>
                <h3><?= esc($book['title']) ?></h3>
                <p>作者：<?= esc($book['author'] ?: '未填寫') ?></p>
                <p>分類：<?= esc(implode(' / ', $tags[$book['id']] ?? []) ?: '未分類') ?></p>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
