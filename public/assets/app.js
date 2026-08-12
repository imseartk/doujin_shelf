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

    $('.js-circle-track-toggle').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: $button.data('url'),
            method: 'POST',
            data: withCsrf({}),
            dataType: 'json'
        }).done(function (response) {
            refreshCsrf(response);
            var tracked = !!(response && parseInt(response.is_tracked, 10));
            $button
                .toggleClass('primary', tracked)
                .toggleClass('ghost', !tracked)
                .text(response.label || (tracked ? '追蹤中' : '未追蹤'));
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    var circlemsModalState = {
        circleId: null,
        circleName: '',
        url: '',
        bindUrl: '',
        $row: null
    };
    var $circlemsModal = $('.js-circlems-bind-modal');
    var c108ModalState = {
        circleId: null,
        circleName: '',
        url: '',
        bindUrl: '',
        $row: null
    };
    var $c108Modal = $('.js-c108-bind-modal');

    $('.js-circlems-bind-open').on('click', function () {
        var $button = $(this);
        circlemsModalState = {
            circleId: $button.data('circle-id'),
            circleName: String($button.data('circle-name') || ''),
            url: String($button.data('url') || ''),
            bindUrl: String($button.data('bind-url') || ''),
            $row: $button.closest('tr')
        };

        $('.js-circlems-bind-current').text(circlemsModalState.circleName);
        $('.js-circlems-bind-q').val(circlemsModalState.circleName);
        $('.js-circlems-bind-page').val('1');
        $('.js-circlems-bind-error').prop('hidden', true).text('');
        $('.js-circlems-bind-results').html('<div class="empty">搜尋中...</div>');
        $circlemsModal.prop('hidden', false);
        loadCirclemsCandidates();
    });

    $('.js-circlems-bind-close').on('click', function () {
        $circlemsModal.prop('hidden', true);
    });

    $('.js-circlems-bind-search').on('submit', function (event) {
        event.preventDefault();
        loadCirclemsCandidates();
    });

    $('.js-circlems-bind-results').on('click', '.js-circlems-bind-candidate', function () {
        var candidate = $(this).data('candidate') || {};
        bindCirclemsCandidate(candidate, $(this).data('import-social') === 1);
    });

    $('.js-c108-bind-open').on('click', function () {
        var $button = $(this);
        c108ModalState = {
            circleId: $button.data('circle-id'),
            circleName: String($button.data('circle-name') || ''),
            url: String($button.data('url') || ''),
            bindUrl: String($button.data('bind-url') || ''),
            $row: $button.closest('tr')
        };

        $('.js-c108-bind-current').text(c108ModalState.circleName);
        $('.js-c108-bind-q').val(c108ModalState.circleName);
        $('.js-c108-bind-day').val('');
        $('.js-c108-bind-error').prop('hidden', true).text('');
        $('.js-c108-bind-results').html('<div class="empty">搜尋中...</div>');
        $c108Modal.prop('hidden', false);
        loadC108Candidates();
    });

    $('.js-c108-bind-close').on('click', function () {
        $c108Modal.prop('hidden', true);
    });

    $('.js-c108-bind-search').on('submit', function (event) {
        event.preventDefault();
        loadC108Candidates();
    });

    $('.js-c108-bind-results').on('click', '.js-c108-bind-candidate', function () {
        bindC108Candidate($(this).data('candidate') || {});
    });

    function loadCirclemsCandidates() {
        if (!circlemsModalState.url) return;

        $.ajax({
            url: circlemsModalState.url,
            method: 'GET',
            dataType: 'json',
            data: {
                event_id: $('.js-circlems-bind-event').val() || '',
                q: $('.js-circlems-bind-q').val() || circlemsModalState.circleName,
                page: $('.js-circlems-bind-page').val() || 1
            }
        }).done(function (response) {
            refreshCsrf(response);
            renderCirclemsEvents(response.events || [], response.event_id);
            renderCirclemsCandidates(response.candidates || []);
            $('.js-circlems-bind-error').prop('hidden', true).text('');
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            refreshCsrf(response);
            $('.js-circlems-bind-results').empty();
            $('.js-circlems-bind-error').prop('hidden', false).text(response.message || 'Circle.ms 搜尋失敗。');
        });
    }

    function renderCirclemsEvents(events, selectedEventId) {
        var $event = $('.js-circlems-bind-event');
        var previousValue = $event.val();
        $event.empty();
        events.forEach(function (event) {
            $('<option></option>')
                .val(event.eventId)
                .text(event.label)
                .prop('selected', String(event.eventId) === String(selectedEventId || previousValue))
                .appendTo($event);
        });
    }

    function renderCirclemsCandidates(candidates) {
        var $results = $('.js-circlems-bind-results').empty();
        if (!candidates.length) {
            $results.html('<div class="empty">沒有 Circle.ms 候選結果。</div>');
            return;
        }

        candidates.forEach(function (candidate) {
            var $card = $('<article class="circlems-result-card circlems-bind-result-card"></article>');
            if (candidate.cut_url) {
                $('<img class="circlems-cut" alt="">').attr('src', candidate.cut_url).appendTo($card);
            }

            var $body = $('<div class="circlems-result-body"></div>').appendTo($card);
            var $head = $('<div class="circlems-result-head"></div>').appendTo($body);
            var $title = $('<div></div>').appendTo($head);
            $('<h3></h3>').text(candidate.name || '(no name)').appendTo($title);
            if (candidate.name_kana) $('<div class="muted"></div>').text(candidate.name_kana).appendTo($title);

            var $meta = $('<div class="circlems-meta"></div>').appendTo($head);
            if (candidate.wcid) $('<span></span>').text('WCID ' + candidate.wcid).appendTo($meta);
            if (candidate.genre) $('<span></span>').text('Genre ' + candidate.genre).appendTo($meta);
            if (candidate.circlems_id) $('<span></span>').text('Circle.ms ' + candidate.circlems_id).appendTo($meta);

            if (candidate.description) {
                $('<p class="circlems-description"></p>').text(truncateText(candidate.description, 180)).appendTo($body);
            }

            var tags = String(candidate.tag || '').split(',').map(function (tag) { return tag.trim(); }).filter(Boolean);
            if (tags.length) {
                var $tags = $('<div class="tag-list"></div>').appendTo($body);
                tags.forEach(function (tag) { $('<span class="tag-chip"></span>').text(tag).appendTo($tags); });
            }

            var $links = $('<div class="circlems-links"></div>').appendTo($body);
            addCirclemsLink($links, 'Web', candidate.website_url);
            addCirclemsLink($links, 'Pixiv', candidate.pixiv_url);
            addCirclemsLink($links, 'X', candidate.twitter_url);
            (candidate.stores || []).forEach(function (store) {
                addCirclemsLink($links, store.name, store.link);
            });

            var $actions = $('<div class="circlems-card-actions"></div>').appendTo($body);
            $('<button class="button small ghost js-circlems-bind-candidate" type="button">只綁定</button>')
                .data('candidate', candidate)
                .data('import-social', 0)
                .appendTo($actions);
            $('<button class="button small primary js-circlems-bind-candidate" type="button">綁定並匯入社群</button>')
                .data('candidate', candidate)
                .data('import-social', 1)
                .appendTo($actions);

            $results.append($card);
        });
    }

    function bindCirclemsCandidate(candidate, importSocial) {
        if (!circlemsModalState.bindUrl || !candidate.circlems_id) return;

        $.ajax({
            url: circlemsModalState.bindUrl,
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: withCsrf({
                wcid: candidate.wcid || '',
                circlems_id: candidate.circlems_id || '',
                webcatalog_cut_url: candidate.cut_url || '',
                import_social: importSocial ? '1' : '0',
                name_kana: candidate.name_kana || '',
                website_url: candidate.website_url || '',
                pixiv_url: candidate.pixiv_url || '',
                twitter_url: candidate.twitter_url || '',
                booth_url: candidate.booth_url || '',
                melonbooks_url: candidate.melonbooks_url || '',
                toranoana_url: candidate.toranoana_url || ''
            })
        }).done(function (response) {
            refreshCsrf(response);
            updateCircleRowAfterBind(response.circle || {});
            $circlemsModal.prop('hidden', true);
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            refreshCsrf(response);
            $('.js-circlems-bind-error').prop('hidden', false).text(response.message || 'Circle.ms 綁定失敗。');
        });
    }

    function loadC108Candidates() {
        if (!c108ModalState.url) return;

        $.ajax({
            url: c108ModalState.url,
            method: 'GET',
            dataType: 'json',
            data: {
                day: $('.js-c108-bind-day').val() || '',
                q: $('.js-c108-bind-q').val() || c108ModalState.circleName
            }
        }).done(function (response) {
            refreshCsrf(response);
            renderC108Candidates(response.candidates || []);
            $('.js-c108-bind-error').prop('hidden', true).text('');
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            refreshCsrf(response);
            $('.js-c108-bind-results').empty();
            $('.js-c108-bind-error').prop('hidden', false).text(response.message || xhr.responseText || 'C108 攤位搜尋失敗。');
        });
    }

    function renderC108Candidates(candidates) {
        var $results = $('.js-c108-bind-results').empty();
        if (!candidates.length) {
            $results.html('<div class="empty">沒有 C108 攤位候選結果。</div>');
            return;
        }

        candidates.forEach(function (candidate) {
            var $card = $('<article class="circlems-result-card circlems-bind-result-card"></article>');
            if (candidate.cut_url) {
                $('<img class="circlems-cut" alt="">').attr('src', candidate.cut_url).appendTo($card);
            }

            var $body = $('<div class="circlems-result-body"></div>').appendTo($card);
            var $head = $('<div class="circlems-result-head"></div>').appendTo($body);
            var $title = $('<div></div>').appendTo($head);
            $('<h3></h3>').text(candidate.name || '(no name)').appendTo($title);
            $('<div class="muted"></div>').text([candidate.name_kana, candidate.pen_name].filter(Boolean).join(' / ')).appendTo($title);

            var $meta = $('<div class="circlems-meta"></div>').appendTo($head);
            if (candidate.position) $('<span></span>').text(candidate.position).appendTo($meta);
            if (candidate.wcid) $('<span></span>').text('WCID ' + candidate.wcid).appendTo($meta);
            if (candidate.local_circle_name) $('<span></span>').text('已連動 ' + candidate.local_circle_name).appendTo($meta);

            if (candidate.book_name) {
                $('<p class="circlems-description"></p>').text('預定發布物：' + truncateText(candidate.book_name, 160)).appendTo($body);
            }
            if (candidate.description) {
                $('<p class="circlems-description"></p>').text(truncateText(candidate.description, 180)).appendTo($body);
            }

            var $actions = $('<div class="circlems-card-actions"></div>').appendTo($body);
            $('<button class="button small primary js-c108-bind-candidate" type="button">連動此攤</button>')
                .data('candidate', candidate)
                .appendTo($actions);

            $results.append($card);
        });
    }

    function bindC108Candidate(candidate) {
        if (!c108ModalState.bindUrl || !candidate.id) return;

        $.ajax({
            url: c108ModalState.bindUrl,
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: withCsrf({
                c108_id: candidate.id
            })
        }).done(function (response) {
            refreshCsrf(response);
            if (c108ModalState.$row && c108ModalState.$row.length) {
                c108ModalState.$row.find('.js-c108-binding-state').text('C108 ' + (response.binding_count || 0) + ' 攤位');
            }
            $c108Modal.prop('hidden', true);
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            refreshCsrf(response);
            $('.js-c108-bind-error').prop('hidden', false).text(response.message || 'C108 攤位連動失敗。');
        });
    }

    function updateCircleRowAfterBind(circle) {
        var $row = circlemsModalState.$row;
        if (!$row || !$row.length) return;

        if (circle.webcatalog_cut_url) {
            $row.find('td:first').html($('<img class="circle-list-cut" alt="">').attr('src', circle.webcatalog_cut_url));
        }
        $row.find('.js-circlems-binding-state').text(circle.webcatalog_circle_id ? 'Circle.ms ' + circle.webcatalog_circle_id : '未連動 Circle.ms');
        $row.find('input[name="name_kana"]').val(circle.name_kana || '');

        var links = {
            'X': circle.twitter_url,
            'pixiv': circle.pixiv_url,
            'Web': circle.website_url,
            'BOOTH': circle.booth_url,
            'Melon': circle.melonbooks_url,
            'Tora': circle.toranoana_url
        };
        var $links = $row.find('.circle-social-links').first().empty();
        var hasLink = false;
        Object.keys(links).forEach(function (label) {
            if (!links[label]) return;
            hasLink = true;
            $('<a class="social-badge" target="_blank" rel="noopener noreferrer"></a>')
                .attr('href', links[label])
                .text(label)
                .appendTo($links);
        });
        if (!hasLink) $('<span class="muted">未設定</span>').appendTo($links);

        var fieldMap = { 'X': 'twitter_url', 'pixiv': 'pixiv_url', 'Web': 'website_url', 'BOOTH': 'booth_url', 'Melon': 'melonbooks_url', 'Tora': 'toranoana_url' };
        Object.keys(fieldMap).forEach(function (label) {
            $row.find('input[name="' + fieldMap[label] + '"]').val(links[label] || '');
        });
    }

    function addCirclemsLink($container, label, url) {
        if (!url) return;
        $('<a target="_blank" rel="noopener"></a>').attr('href', url).text(label).appendTo($container);
    }

    function truncateText(value, width) {
        value = String(value || '');
        return value.length > width ? value.slice(0, width) + '...' : value;
    }

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
        var $author = $('input[name="author"]').first();
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
                if (item.author) {
                    $('<span class="circle-suggestion-kana"></span>').text('作者: ' + item.author).appendTo($button);
                }
                $button.on('click', function () {
                    $id.val(item.id);
                    $input.val(item.name);
                    if (item.author) $author.val(item.author);
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

        if ($header.closest('table').hasClass('books-table')) {
            saveBookScrollPosition();
            var url = new URL(window.location.href);
            url.searchParams.set('sort', $header.data('sort'));
            url.searchParams.set('dir', direction);
            url.searchParams.set('page', '1');
            window.location.href = url.pathname + url.search;
            return;
        }

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
        $editor.find('.js-taxonomy-quick-add').on('click', function () {
            addExisting($(this).data('name'));
        });
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
        if (!response || !response.csrf) return;

        var $csrf = csrfField();
        if (!$csrf.length) return;

        $('input[type="hidden"][name="' + $csrf.attr('name') + '"]').val(response.csrf);
    }
});
