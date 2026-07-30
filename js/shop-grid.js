/**
 * Golden Hive Blocks — Shop grid card-height equalizer.
 *
 * Equalizes product-card heights per row on shop/taxonomy/search loops, re-run
 * on load, resize, WooCommerce grid updates (filters / fragment refreshes) and
 * after images finish loading. Migrated from a Code Snippet. See
 * includes/shop-grid.php.
 */
jQuery(function($) {
    function equalizeCards() {
        var cards = $('ul.products li.product').get();
        if (!cards.length) return;

        // WRITE pass: reset heights so the measurement is natural.
        cards.forEach(function(card) {
            card.style.minHeight = '';
        });

        // READ pass: one getBoundingClientRect per card gives both the row
        // grouping (top) and the height — no interleaved reads/writes, so the
        // browser lays out once instead of once per card.
        var rows = {};
        cards.forEach(function(card) {
            var rect = card.getBoundingClientRect();
            var top = Math.round(rect.top);
            if (!rows[top]) rows[top] = { max: 0, cards: [] };
            rows[top].cards.push(card);
            if (rect.height > rows[top].max) rows[top].max = rect.height;
        });

        // WRITE pass: set each row to its tallest card.
        Object.keys(rows).forEach(function(top) {
            var row = rows[top];
            row.cards.forEach(function(card) {
                card.style.minHeight = row.max + 'px';
            });
        });
    }

    var resizeTimer;
    function debouncedEqualize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(equalizeCards, 200);
    }

    equalizeCards();
    $(window).on('resize', debouncedEqualize);

    // Re-run only when WooCommerce actually swaps the product grid (ordering,
    // filters, fragment refreshes) — not on every AJAX request on the page.
    $(document.body).on('updated_wc_div wc_fragments_refreshed', function() {
        setTimeout(equalizeCards, 300);
    });

    // Re-run after all images loaded
    $('ul.products img').on('load', equalizeCards);
});
