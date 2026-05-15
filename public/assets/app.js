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
});
