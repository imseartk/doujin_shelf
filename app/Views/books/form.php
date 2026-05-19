<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $isEdit = ! empty($book['id']);
    $returnTo = (string) service('request')->getGet('return_to');
    if ($returnTo === '' || str_starts_with($returnTo, '//') || ! str_starts_with($returnTo, '/books') || preg_match('#^/books/\d+/edit#', $returnTo) === 1) {
        $returnTo = '/books';
    }
?>
<section class="page-head">
    <div>
        <h1><?= $isEdit ? '編輯書本' : '新增書本' ?></h1>
        <p>維護基本資料、分類、原作、角色，以及願望清單來源。</p>
    </div>
    <div class="page-actions">
        <button class="button primary" type="submit" form="book-form">儲存</button>
        <a class="button ghost" href="<?= esc($returnTo) ?>">回清單</a>
    </div>
</section>

<?php if ($errors): ?>
    <div class="notice error">
        <?php foreach ($errors as $error): ?><div><?= esc($error) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="book-form" class="form-grid" method="post" action="<?= $isEdit ? '/books/' . (int) $book['id'] : '/books' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">

    <section class="panel wide">
        <h2>基本資料</h2>
        <div class="fields two">
            <label>標題
                <input name="title" value="<?= esc($book['title'] ?? '') ?>" required maxlength="255">
            </label>
            <label>狀態
                <select name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($book['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>類型
                <select name="type">
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($book['type'] ?? 'doujin') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>社團首字
                <input name="circle_kana" value="<?= esc($book['circle_kana'] ?? '') ?>" maxlength="20">
            </label>
            <label>社團
                <input name="circle" value="<?= esc($book['circle'] ?? '') ?>" maxlength="255">
            </label>
            <label>作者
                <input name="author" value="<?= esc($book['author'] ?? '') ?>" maxlength="255">
            </label>
            <label>活動 / 來源事件
                <input name="event" value="<?= esc($book['event'] ?? '') ?>" maxlength="100">
            </label>
            <label>放置位置
                <select name="location_id">
                    <option value="0">未設定</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= (int) $location['id'] ?>" <?= (int) ($book['location_id'] ?? 0) === (int) $location['id'] ? 'selected' : '' ?>><?= esc($location['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">封面圖片 URL
                <input class="js-cover-url" name="cover_url" value="<?= esc($book['cover_url'] ?? '') ?>" maxlength="500" placeholder="可貼外部 URL，或使用下方上傳">
            </label>
            <label class="span-2">上傳封面
                <input class="js-cover-file" type="file" name="cover_file" accept="image/jpeg,image/png,image/webp,image/gif">
                <span class="field-hint">支援 JPG、PNG、WEBP、GIF，最大 8MB。上傳後會自動覆蓋封面 URL。</span>
            </label>
            <label class="span-2">備註
                <textarea name="note" rows="3"><?= esc($book['note'] ?? '') ?></textarea>
            </label>
        </div>
        <div class="cover-preview-wrap">
            <div class="cover-empty js-cover-empty">cover preview</div>
            <img class="cover-preview js-cover-preview" src="<?= esc($book['cover_url'] ?? '') ?>" alt="">
        </div>
    </section>

    <section class="panel">
        <h2>分類</h2>
        <div class="taxonomy-field js-taxonomy-editor" data-taxonomy="tags">
            <div class="field-label">一般 tag</div>
            <div class="taxonomy-add-row">
                <input class="js-taxonomy-input" type="text" placeholder="輸入分類名稱">
                <button class="button small js-taxonomy-add" type="button">加入</button>
            </div>
            <div class="taxonomy-suggestions js-taxonomy-suggestions" hidden></div>
            <div class="taxonomy-list js-taxonomy-list"></div>
            <textarea class="js-taxonomy-value" name="tags_text" hidden><?= esc($tagsText) ?></textarea>
            <div class="field-hint js-taxonomy-status"></div>
        </div>
        <div class="taxonomy-field js-taxonomy-editor" data-taxonomy="works">
            <div class="field-label">原作</div>
            <div class="taxonomy-add-row">
                <input class="js-taxonomy-input" type="text" placeholder="例如 ブルーアーカイブ">
                <button class="button small js-taxonomy-add" type="button">加入</button>
            </div>
            <div class="taxonomy-suggestions js-taxonomy-suggestions" hidden></div>
            <div class="taxonomy-list js-taxonomy-list"></div>
            <textarea class="js-taxonomy-value" name="works_text" hidden><?= esc($worksText) ?></textarea>
            <div class="field-hint js-taxonomy-status"></div>
        </div>
        <div class="taxonomy-field js-taxonomy-editor" data-taxonomy="characters">
            <div class="field-label">角色</div>
            <div class="taxonomy-add-row">
                <input class="js-taxonomy-input" type="text" placeholder="輸入角色名稱">
                <button class="button small js-taxonomy-add" type="button">加入</button>
            </div>
            <div class="taxonomy-suggestions js-taxonomy-suggestions" hidden></div>
            <div class="taxonomy-list js-taxonomy-list"></div>
            <textarea class="js-taxonomy-value" name="characters_text" hidden><?= esc($charactersText) ?></textarea>
            <div class="field-hint js-taxonomy-status"></div>
        </div>
    </section>

    <section class="panel wide">
        <div class="panel-head">
            <h2>願望清單來源</h2>
            <button class="button small js-add-source" type="button">新增來源列</button>
        </div>
        <div class="source-list js-source-list">
            <?php $sourceRows = $sources ?: [['id' => '', 'shop_id' => '', 'price' => '', 'item_url' => '', 'note' => '']]; ?>
            <?php foreach ($sourceRows as $source): ?>
                <div class="source-row">
                    <input type="hidden" name="source_id[]" value="<?= esc($source['id'] ?? '') ?>">
                    <label>店鋪
                        <select name="source_shop_id[]">
                            <option value="0">未設定</option>
                            <?php foreach ($shops as $shop): ?>
                                <option value="<?= (int) $shop['id'] ?>" <?= (int) ($source['shop_id'] ?? 0) === (int) $shop['id'] ? 'selected' : '' ?>><?= esc($shop['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>價格
                        <input name="source_price[]" inputmode="numeric" value="<?= esc($source['price'] ?? '') ?>" placeholder="JPY">
                    </label>
                    <label class="url-field">商品 URL
                        <input name="source_item_url[]" value="<?= esc($source['item_url'] ?? '') ?>">
                    </label>
                    <label>備註
                        <input name="source_note[]" value="<?= esc($source['note'] ?? '') ?>">
                    </label>
                    <?php if (! empty($source['id'])): ?>
                        <label class="checkline"><input type="checkbox" name="source_delete[]" value="<?= (int) $source['id'] ?>"> 刪除</label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="form-actions">
        <button class="button primary" type="submit">儲存</button>
        <a class="button ghost" href="<?= esc($returnTo) ?>">取消</a>
    </div>
</form>

<?php if ($isEdit): ?>
<form class="danger-form" method="post" action="/books/<?= (int) $book['id'] ?>/delete" data-confirm="確定要刪除這本書？">
    <?= csrf_field() ?>
    <button class="button danger" type="submit">刪除這本書</button>
</form>
<?php endif; ?>

<template id="source-row-template">
    <div class="source-row">
        <input type="hidden" name="source_id[]" value="">
        <label>店鋪
            <select name="source_shop_id[]">
                <option value="0">未設定</option>
                <?php foreach ($shops as $shop): ?>
                    <option value="<?= (int) $shop['id'] ?>"><?= esc($shop['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>價格<input name="source_price[]" inputmode="numeric" placeholder="JPY"></label>
        <label class="url-field">商品 URL<input name="source_item_url[]"></label>
        <label>備註<input name="source_note[]"></label>
    </div>
</template>
<?= $this->endSection() ?>
