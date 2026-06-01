/**
 * Golden Hive Blocks — Loop "Add to cart" with inline size picker.
 *
 * Simple products add on click; variable products reveal a size picker and add
 * the chosen variation. Adds run through WooCommerce's own wc-ajax=add_to_cart
 * endpoint (posting the variation ID for variable products), so cart fragments
 * and validation are handled natively. See includes/add-to-cart.php.
 */
jQuery(function ($) {
    var cfg = window.ghbAddToCart || {};

    function feedback($trigger) {
        if ($trigger.data('ghbLabel') == null) {
            $trigger.data('ghbLabel', $trigger.text());
        }
        $trigger.addClass('is-done').text('✓ Aggiunto');
        clearTimeout($trigger.data('ghbTimer'));
        $trigger.data('ghbTimer', setTimeout(function () {
            $trigger.removeClass('is-done').text($trigger.data('ghbLabel'));
        }, 1500));
    }

    function add(id, $atc, $trigger) {
        if (!cfg.endpoint || !id) return;
        $atc.addClass('is-busy');
        $.post(cfg.endpoint, { product_id: id, quantity: 1 }, function (data) {
            $atc.removeClass('is-busy');
            // Validation failed (e.g. needs options) → send them to the product page.
            if (data && data.error && data.product_url) {
                window.location = data.product_url;
                return;
            }
            if (data && data.fragments) {
                $.each(data.fragments, function (key, value) {
                    $(key).replaceWith(value);
                });
            }
            $(document.body).trigger('added_to_cart', [
                data ? data.fragments : null,
                data ? data.cart_hash : null,
                $trigger
            ]);
            feedback($trigger);
        });
    }

    // Simple product → add directly.
    $(document).on('click', '.ghb-atc[data-type="simple"] .ghb-atc-trigger', function (e) {
        e.preventDefault();
        var $atc = $(this).closest('.ghb-atc');
        add($(this).data('product-id'), $atc, $(this));
    });

    // Variable product → toggle the size picker.
    $(document).on('click', '.ghb-atc[data-type="variable"] > .ghb-atc-trigger', function (e) {
        e.preventDefault();
        var $atc = $(this).closest('.ghb-atc');
        $('.ghb-atc.is-open').not($atc).removeClass('is-open');
        $atc.toggleClass('is-open');
    });

    // Pick a size → add that variation.
    $(document).on('click', '.ghb-atc-size[data-variation-id]', function (e) {
        e.preventDefault();
        var $atc = $(this).closest('.ghb-atc');
        add($(this).data('variation-id'), $atc, $atc.children('.ghb-atc-trigger'));
        $atc.removeClass('is-open');
    });

    // Close any open picker when clicking elsewhere.
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ghb-atc').length) {
            $('.ghb-atc.is-open').removeClass('is-open');
        }
    });
});
