$(function () {
    $('[data-confirm]').on('submit', function (event) {
        var message = $(this).data('confirm') || '確定執行？';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    initBookListState();
    initBookReturnTo();

    $('.js-add-source').on('click', function () {
        var template = document.getElementById('source-row-template');
        if (!template) return;
        $('.js-source-list').append(template.innerHTML);
    });

    $('.js-wishlist-source-edit, .js-wishlist-add-toggle').on('click', function () {
        var $form = $($(this).data('form'));
        var $summary = $($(this).data('summary'));
        if (!$form.length) return;

        $form.prop('hidden', false);
        if ($summary.length) $summary.prop('hidden', true);
        $form.find('select, input').filter(':visible').first().trigger('focus');
    });

    $('.js-wishlist-source-cancel').on('click', function () {
        var $form = $($(this).data('form'));
        var $summary = $($(this).data('summary'));
        $form.prop('hidden', true);
        if ($summary.length) $summary.prop('hidden', false);
    });

    $('.js-wishlist-add-cancel').on('click', function () {
        $($(this).data('form')).prop('hidden', true);
    });

    $('.js-cart-remove').on('click', function () {
        var $item = $(this).closest('.js-cart-item');
        var $row = $item.closest('.js-cart-shop-row');
        $item.prop('hidden', true);
        updateCartRow($row);
    });

    function updateCartRow($row) {
        var total = 0;
        var visibleItems = 0;

        $row.find('.js-cart-item').each(function () {
            var $item = $(this);
            if ($item.prop('hidden')) return;

            visibleItems += 1;
            total += parseInt($item.data('price'), 10) || 0;
        });

        $row.find('.js-cart-total').text(total.toLocaleString());
        $row.find('.js-cart-shop-empty').prop('hidden', visibleItems > 0);
    }

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
    initCirclePicker();

    function initCirclePicker() {
        var $picker = $('.js-circle-picker');
        if (!$picker.length) return;

        var $id = $picker.find('.js-circle-id');
        var $input = $picker.find('.js-circle-input');
        var $suggestions = $picker.find('.js-circle-suggestions');
        var selectedName = normalizeTaxonomyName($input.val());
        var searchTimer = null;

        function search() {
            var q = normalizeTaxonomyName($input.val());
            if (!q) {
                $id.val('');
                selectedName = '';
                $suggestions.prop('hidden', true).empty();
                return;
            }

            $.getJSON('/books/circles/search', { q: q })
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
                var $button = $('<button type="button" class="taxonomy-suggestion"></button>');
                $('<span class="circle-suggestion-name"></span>').text(item.name).appendTo($button);
                if (item.name_kana) {
                    $('<span class="circle-suggestion-kana"></span>').text(item.name_kana).appendTo($button);
                }
                $button.on('click', function () {
                    $id.val(item.id);
                    $input.val(item.name);
                    selectedName = normalizeTaxonomyName(item.name);
                    $suggestions.prop('hidden', true).empty();
                });
                $button.appendTo($suggestions);
            });
            $suggestions.prop('hidden', false);
        }

        $input.on('input', function () {
            if (normalizeTaxonomyName($input.val()) !== selectedName) {
                $id.val('');
            }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(search, 180);
        });
        $input.on('keydown', function (event) {
            if (event.key === 'Escape') {
                $suggestions.prop('hidden', true).empty();
            }
        });
        $input.on('blur', function () {
            setTimeout(function () { $suggestions.prop('hidden', true); }, 160);
        });
    }

    function initBookListState() {
        var $table = $('.js-sortable-table');
        if (!$table.length) return;

        var params = new URLSearchParams(window.location.search);
        var initialSort = params.get('sort');
        var initialDirection = params.get('dir') === 'desc' ? 'desc' : 'asc';

        if (initialSort) {
            var $initialHeader = $table.find('th[data-sort="' + cssEscape(initialSort) + '"]').first();
            if ($initialHeader.length) sortTableByHeader($initialHeader, initialDirection, false);
        }

        updateBookReturnLinks();
        restoreBookScrollPosition();

        $(window).on('pagehide', saveBookScrollPosition);
        $('.books-table .actions a, .page-head a[href="/books/new"]').on('click', function () {
            saveBookScrollPosition();
            updateBookReturnLinks();
        });
    }

    $('.js-sortable-table th[data-sort]').on('click', function () {
        var $header = $(this);
        var direction = $header.hasClass('sort-asc') ? 'desc' : 'asc';
        sortTableByHeader($header, direction, true);
        updateBookReturnLinks();
    });

    function sortTableByHeader($header, direction, updateUrl) {
        var $table = $header.closest('table');
        var index = $header.index();
        var type = $header.data('sort-type') || 'text';
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

        if (updateUrl) {
            var url = new URL(window.location.href);
            url.searchParams.set('sort', $header.data('sort'));
            url.searchParams.set('dir', direction);
            window.history.replaceState(null, '', url.pathname + url.search);
        }
    }

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

    function initBookReturnTo() {
        var returnTo = getReturnToFromUrl();
        var $form = $('#book-form');

        if (!returnTo || !$form.length) return;

        $('.page-actions a[href="/books"], .form-actions a[href="/books"]').attr('href', returnTo);

        if ($form.find('input[name="return_to"]').length === 0) {
            $('<input>', {
                type: 'hidden',
                name: 'return_to',
                value: returnTo
            }).appendTo($form);
        }
    }

    function getReturnToFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var returnTo = params.get('return_to') || '';
        return isSafeBookReturnPath(returnTo) ? returnTo : '';
    }

    function updateBookReturnLinks() {
        var returnTo = currentBookListPath();

        $('.books-table .actions a[href*="/edit"], .page-head a[href="/books/new"]').each(function () {
            var url = new URL(this.getAttribute('href'), window.location.origin);
            url.searchParams.set('return_to', returnTo);
            this.setAttribute('href', url.pathname + url.search);
        });
    }

    function currentBookListPath() {
        return window.location.pathname + window.location.search;
    }

    function bookScrollKey() {
        return 'doujin.books.scroll:' + currentBookListPath();
    }

    function saveBookScrollPosition() {
        try {
            window.sessionStorage.setItem(bookScrollKey(), String(window.scrollY || window.pageYOffset || 0));
        } catch (error) {
            // Ignore private browsing/session storage limitations.
        }
    }

    function restoreBookScrollPosition() {
        try {
            var scrollY = parseInt(window.sessionStorage.getItem(bookScrollKey()) || '', 10);
            if (!Number.isNaN(scrollY) && scrollY > 0) {
                setTimeout(function () {
                    window.scrollTo(0, scrollY);
                }, 80);
            }
        } catch (error) {
            // Ignore private browsing/session storage limitations.
        }
    }

    function isSafeBookReturnPath(path) {
        return path.indexOf('/books') === 0 && path.indexOf('//') !== 0;
    }

    function cssEscape(value) {
        if (window.CSS && window.CSS.escape) return window.CSS.escape(value);
        return String(value).replace(/"/g, '\\"');
    }

    $('.js-taxonomy-editor').each(function () {
        var $editor = $(this);
        var taxonomy = $editor.data('taxonomy');
        var $input = $editor.find('.js-taxonomy-input');
        var $add = $editor.find('.js-taxonomy-add');
        var $value = $editor.find('.js-taxonomy-value');
        var $list = $editor.find('.js-taxonomy-list');
        var $suggestions = $editor.find('.js-taxonomy-suggestions');
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
            render();
        }

        function createAndAdd() {
            var name = normalizeTaxonomyName($input.val());
            if (!name) return;

            $add.prop('disabled', true);

            $.ajax({
                url: '/books/taxonomy/' + taxonomy,
                method: 'POST',
                data: withCsrf({ name: name }),
                dataType: 'json'
            }).done(function (response) {
                refreshCsrf(response);
                if (response && response.item && response.item.name) {
                    addExisting(response.item.name);
                }
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
            .split(new RegExp('[,\\n，、]+'))
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
