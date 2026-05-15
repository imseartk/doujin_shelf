$(function () {
    $('[data-confirm]').on('submit', function (event) {
        var message = $(this).data('confirm') || '確定執行？';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    $('.js-add-source').on('click', function () {
        var template = document.getElementById('source-row-template');
        if (!template) return;
        $('.js-source-list').append(template.innerHTML);
    });

    function showCoverPreview(url) {
        var $preview = $('.js-cover-preview');
        var $empty = $('.js-cover-empty');

        if (!url) {
            $preview.hide();
            $empty.css('display', 'grid');
            return;
        }

        $preview.attr('src', url).show();
        $empty.hide();
    }

    function updateCoverPreview() {
        showCoverPreview($('.js-cover-url').val());
    }

    $('.js-cover-url').on('input', updateCoverPreview);
    $('.js-cover-file').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) {
            updateCoverPreview();
            return;
        }
        showCoverPreview(URL.createObjectURL(file));
    });
    updateCoverPreview();

    $('.js-sortable-table th[data-sort]').on('click', function () {
        var $header = $(this);
        var $table = $header.closest('table');
        var index = $header.index();
        var type = $header.data('sort-type') || 'text';
        var direction = $header.hasClass('sort-asc') ? 'desc' : 'asc';
        var rows = $table.find('tbody tr').not(function () {
            return $(this).find('.empty').length > 0;
        }).get();

        rows.sort(function (a, b) {
            var aValue = getSortValue(a, index, type);
            var bValue = getSortValue(b, index, type);

            if (aValue < bValue) return direction === 'asc' ? -1 : 1;
            if (aValue > bValue) return direction === 'asc' ? 1 : -1;
            return 0;
        });

        $table.find('th').removeClass('sort-asc sort-desc');
        $header.addClass(direction === 'asc' ? 'sort-asc' : 'sort-desc');
        $table.find('tbody').append(rows);
    });

    function getSortValue(row, index, type) {
        var $cell = $(row).children('td').eq(index);
        var raw = $cell.data('sort-value');
        if (raw === undefined || raw === null) raw = $cell.text();
        raw = String(raw).trim();

        if (type === 'number') {
            var number = parseFloat(raw.replace(/[^0-9.-]/g, ''));
            return Number.isNaN(number) ? 0 : number;
        }

        return raw.toLocaleLowerCase();
    }

    $('.js-tag-editor').each(function () {
        var $editor = $(this);
        var $input = $editor.find('.js-tag-input');
        var $value = $editor.find('.js-tag-value');
        var $list = $editor.find('.js-tag-chip-list');
        var tags = splitTags($value.val());

        function render() {
            $list.empty();
            tags.forEach(function (tag) {
                $('<button type="button" class="tag-chip" aria-label="移除 ' + tag + '"></button>')
                    .text(tag)
                    .append('<span aria-hidden="true">×</span>')
                    .on('click', function () {
                        tags = tags.filter(function (item) { return item !== tag; });
                        render();
                    })
                    .appendTo($list);
            });
            $value.val(tags.join(', '));
        }

        function addTag(text) {
            splitTags(text).forEach(function (tag) {
                if (tags.indexOf(tag) === -1) tags.push(tag);
            });
            $input.val('');
            render();
        }

        $input.on('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addTag($input.val());
            }
            if (event.key === 'Backspace' && !$input.val() && tags.length > 0) {
                tags.pop();
                render();
            }
        });

        $input.on('blur', function () {
            if ($input.val().trim()) addTag($input.val());
        });

        $editor.on('click', function () {
            $input.trigger('focus');
        });

        render();
    });

    function splitTags(value) {
        return String(value || '')
            .split(/[,
，、]+/)
            .map(function (tag) { return tag.trim(); })
            .filter(function (tag, index, all) {
                return tag && all.indexOf(tag) === index;
            });
    }
});
