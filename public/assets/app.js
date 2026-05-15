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

    $('.js-taxonomy-editor').each(function () {
        var $editor = $(this);
        var taxonomy = $editor.data('taxonomy');
        var $input = $editor.find('.js-taxonomy-input');
        var $add = $editor.find('.js-taxonomy-add');
        var $value = $editor.find('.js-taxonomy-value');
        var $list = $editor.find('.js-taxonomy-list');
        var $suggestions = $editor.find('.js-taxonomy-suggestions');
        var $status = $editor.find('.js-taxonomy-status');
        var items = splitTaxonomyNames($value.val());
        var searchTimer = null;

        function render() {
            $list.empty();
            items.forEach(function (name) {
                var $box = $('<div class="taxonomy-item"></div>');
                $('<span></span>').text(name).appendTo($box);
                $('<button type="button" class="taxonomy-remove" aria-label="解除綁定"></button>')
                    .text('刪除')
                    .on('click', function () {
                        items = items.filter(function (item) { return item !== name; });
                        setStatus('已從這本書解除綁定，儲存後生效。');
                        render();
                    })
                    .appendTo($box);
                $box.appendTo($list);
            });
            $value.val(items.join(', '));
        }

        function addExisting(name) {
            name = normalizeTaxonomyName(name);
            if (!name) return;
            if (items.indexOf(name) === -1) items.push(name);
            $input.val('');
            $suggestions.prop('hidden', true).empty();
            setStatus('已加入，儲存後會綁定到這本書。');
            render();
        }

        function createAndAdd() {
            var name = normalizeTaxonomyName($input.val());
            if (!name) {
                setStatus('請先輸入名稱。');
                return;
            }

            $add.prop('disabled', true);
            setStatus('確認分類中...');

            $.ajax({
                url: '/books/taxonomy/' + taxonomy,
                method: 'POST',
                data: withCsrf({ name: name }),
                dataType: 'json'
            }).done(function (response) {
                refreshCsrf(response);
                if (response && response.item && response.item.name) {
                    addExisting(response.item.name);
                    setStatus(response.created ? '已新增分類並加入。' : '已找到既有分類並加入。');
                }
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '分類加入失敗。';
                setStatus(message);
            }).always(function () {
                $add.prop('disabled', false);
            });
        }

        function search() {
            var q = normalizeTaxonomyName($input.val());
            if (!q) {
                $suggestions.prop('hidden', true).empty();
                return;
            }

            $.getJSON('/books/taxonomy/' + taxonomy + '/search', { q: q })
                .done(function (response) {
                    renderSuggestions(response.items || []);
                });
        }

        function renderSuggestions(results) {
            $suggestions.empty();
            if (results.length === 0) {
                $suggestions.prop('hidden', true);
                return;
            }

            results.forEach(function (item) {
                $('<button type="button" class="taxonomy-suggestion"></button>')
                    .text(item.name)
                    .on('click', function () {
                        addExisting(item.name);
                    })
                    .appendTo($suggestions);
            });
            $suggestions.prop('hidden', false);
        }

        function setStatus(message) {
            $status.text(message || '');
        }

        $add.on('click', createAndAdd);
        $input.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                createAndAdd();
            }
            if (event.key === 'Escape') {
                $suggestions.prop('hidden', true).empty();
            }
        });
        $input.on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(search, 180);
        });
        $input.on('blur', function () {
            setTimeout(function () { $suggestions.prop('hidden', true); }, 160);
        });

        render();
    });

    function splitTaxonomyNames(value) {
        return String(value || '')
            .split(/[,
，、]+/)
            .map(normalizeTaxonomyName)
            .filter(function (name, index, all) {
                return name && all.indexOf(name) === index;
            });
    }

    function normalizeTaxonomyName(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function csrfField() {
        return $('form input[type="hidden"][name*="csrf"]').first();
    }

    function withCsrf(data) {
        var $csrf = csrfField();
        if ($csrf.length) data[$csrf.attr('name')] = $csrf.val();
        return data;
    }

    function refreshCsrf(response) {
        if (response && response.csrf) csrfField().val(response.csrf);
    }
});
