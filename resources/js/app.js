if (window.jQuery) {
    window.jQuery(($) => {
        $(document).on('submit', 'form[data-confirm]', function (event) {
            const message = $(this).data('confirm');

            if (message && ! window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}
