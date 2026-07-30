<?php
/**
 * Golden Hive — Quick Add to Cart modal (bottom sheet).
 *
 * A single button on each product card opens a bottom-sheet modal to pick the
 * variation (size) and add to cart — simple products add directly. Triggered by:
 *   • .ghb-quick-add-btn[data-product-id]  → variable products (opens modal)
 *   • .ghb-simple-add-btn[data-product-id] → simple products (direct add)
 * Both buttons are rendered by includes/add-to-cart.php on the loop cards.
 *
 * Variations come from the read-only ghb_get_variations AJAX handler in
 * product-carousel-shortcode.php; cart writes go through WooCommerce's native
 * wc-ajax=add_to_cart endpoint (posting the variation ID as product_id), so
 * validation, notices and fragment refresh are all native.
 *
 * Styles live in quick-add.css and behaviour in js/quick-add.js — only the
 * small modal shell is printed in wp_footer.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets — gated on the shared product-UI check (see includes/add-to-cart.php).
 */
add_action('wp_enqueue_scripts', 'ghb_quick_add_assets');
function ghb_quick_add_assets()
{
    if (!function_exists('ghb_product_ui_should_load') || !ghb_product_ui_should_load()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-quick-add',
        gh_asset_url('quick-add.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-quick-add',
        gh_asset_url('js/quick-add.js'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true)
    );

    wp_localize_script(
        'golden-hive-quick-add',
        'ghbQuickAdd',
        array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'cartEndpoint' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('add_to_cart') : '',
        )
    );
}

/**
 * Modal shell — server-rendered once per gated page. Content is built by
 * js/quick-add.js; the aria-hidden toggle follows the shared modal contract
 * (ships "true", JS flips it on open/close).
 */
add_action('wp_footer', 'ghb_quick_add_to_cart_frontend');
function ghb_quick_add_to_cart_frontend()
{
    if (!function_exists('ghb_product_ui_should_load') || !ghb_product_ui_should_load()) {
        return;
    }
    ?>
    <!-- GHB Quick Add Modal -->
    <div class="ghb-qa-overlay"></div>
    <div class="ghb-qa-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Aggiungi al carrello', 'golden-hive-blocks'); ?>" aria-hidden="true">
        <div class="ghb-qa-handle"></div>
        <button class="ghb-qa-close" aria-label="<?php echo esc_attr__('Chiudi', 'golden-hive-blocks'); ?>">&times;</button>
        <div class="ghb-qa-content"></div>
    </div>
    <?php
}
