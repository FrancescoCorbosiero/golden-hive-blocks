<?php
/**
 * Golden Hive — WooCommerce Quick View.
 *
 * Adds a hover "quick view" button to product cards on shop / product-taxonomy
 * / search loops. Clicking it fetches the product over AJAX and shows a modal
 * with gallery, price, stock, short description, attributes and a link to the
 * full product page.
 *
 * Standard WooCommerce loop hooks + data, so it is theme-agnostic. The button
 * is injected on `woocommerce_before_shop_loop_item_title`. The `.rp-qv-*` /
 * `.rp-quick-view-btn` class names are preserved from the original site snippet
 * this was migrated from; the AJAX action and PHP functions use the plugin's
 * `ghb_` prefix so they never collide with that snippet if it is still active.
 *
 * Migrated from a Code Snippet. Disable that snippet once this is active, or
 * the button/modal will be duplicated.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the Quick View UI should load. The buttons render via standard
 * WooCommerce loop hooks (shop, category, search, related products AND native
 * product sliders / our [gh_product_rail]), which can appear on any front-end
 * page — so load the modal + assets wherever WooCommerce is active.
 */
function ghb_quick_view_should_load()
{
    return class_exists('WooCommerce');
}

/**
 * Quick View button on each product card.
 */
add_action('woocommerce_before_shop_loop_item_title', 'ghb_quick_view_button', 11);
function ghb_quick_view_button()
{
    global $product;
    if (!$product) {
        return;
    }
    echo '<button class="rp-quick-view-btn" data-product-id="' . esc_attr($product->get_id()) . '" aria-label="Quick View">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
    </button>';
}

/**
 * AJAX handler note: the `ghb_quick_view` endpoint (and its identical payload)
 * is already registered, site-wide and unconditionally, by
 * includes/product-carousel-shortcode.php (ghb_quick_view_handler). We
 * deliberately reuse it here rather than declare a second copy — the JS below
 * fetches that same `ghb_quick_view` action. Do NOT redeclare the handler.
 */

/**
 * The Quick View modal's cart-control data (product type, purchasability, size
 * rows) is bundled into the `ghb_quick_view` payload itself (see
 * ghb_quick_view_handler), so the modal opens with a single request — no
 * separate endpoint needed.
 */

/**
 * Assets — styles + jQuery-based behaviour, on loop pages only.
 */
add_action('wp_enqueue_scripts', 'ghb_quick_view_assets');
function ghb_quick_view_assets()
{
    if (!ghb_quick_view_should_load()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-quick-view',
        GOLDEN_HIVE_BLOCKS_URL . 'quick-view.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-quick-view',
        GOLDEN_HIVE_BLOCKS_URL . 'js/quick-view.js',
        array('jquery'),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true)
    );

    wp_localize_script(
        'golden-hive-quick-view',
        'ghbQuickView',
        array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'action'       => 'ghb_quick_view',
            'cartEndpoint' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('add_to_cart') : '',
        )
    );
}

/**
 * Modal container — server-rendered once per loop page.
 */
add_action('wp_footer', 'ghb_quick_view_modal');
function ghb_quick_view_modal()
{
    if (!ghb_quick_view_should_load()) {
        return;
    }
    ?>
    <div class="rp-qv-overlay"></div>
    <div class="rp-qv-modal">
        <button class="rp-qv-close" aria-label="Chiudi">&#10005;</button>
        <div class="rp-qv-content"></div>
    </div>
    <?php
}
