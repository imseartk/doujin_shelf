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

    function updateCoverPreview() {
        var url = $('.js-cover-url').val();
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

    $('.js-cover-url').on('input', updateCoverPreview);
    updateCoverPreview();
});
