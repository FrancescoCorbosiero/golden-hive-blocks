/**
 * Golden Hive Blocks — Shop grid card-height equalizer.
 *
 * Equalizes product-card heights per row on shop/taxonomy/search loops, re-run
 * on load, resize, AJAX (filters / infinite scroll) and after images finish
 * loading. Migrated from a Code Snippet. See includes/shop-grid.php.
 */
jQuery(function($) {
    function equalizeCards() {
        var $cards = $('ul.products li.product');
        if (!$cards.length) return;

        // Reset heights
        $cards.css('min-height', '');

        // Group cards by row (same offsetTop)
        var rows = {};
        $cards.each(function() {
            var top = Math.round($(this).offset().top);
            if (!rows[top]) rows[top] = [];
            rows[top].push(this);
        });

        // Set each row to tallest card
        $.each(rows, function(top, cards) {
            var maxHeight = 0;
            $(cards).each(function() {
                var h = $(this).outerHeight();
                if (h > maxHeight) maxHeight = h;
            });
            $(cards).css('min-height', maxHeight + 'px');
        });
    }

    // Run on load, resize, and after AJAX (infinite scroll, filters)
    equalizeCards();
    $(window).on('resize', $.debounce ? $.debounce(200, equalizeCards) : function() {
        clearTimeout(window.rpEqTimer);
        window.rpEqTimer = setTimeout(equalizeCards, 200);
    });
    $(document).on('ajaxComplete', function() {
        setTimeout(equalizeCards, 300);
    });
    // Re-run after all images loaded
    $('ul.products img').on('load', equalizeCards);
});
