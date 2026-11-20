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
 * is injected on `woocommerce_before_shop_loop_item_title`. Markup:
 * `.ghb-qv-btn` on the card, `.ghb-qv-*` in the modal (quick-view.css,
 * js/quick-view.js); its data comes from `ghb_quick_view`
 * (includes/product-ajax.php).
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the Quick View UI should load. Delegates to the shared product-UI
 * gate (see includes/add-to-cart.php): WooCommerce pages, cart/checkout,
 * search, or singular content embedding one of our product shortcodes —
 * filterable via `ghb_product_ui_should_load`.
 */
function ghb_quick_view_should_load()
{
    if (function_exists('ghb_product_ui_should_load')) {
        return ghb_product_ui_should_load();
    }

    return class_exists('WooCommerce');
}

/**
 * Quick View button on each product card.
 */
add_action('woocommerce_before_shop_loop_item_title', 'ghb_quick_view_button', 11);
function ghb_quick_view_button()
{
    // Stesso gate degli asset: dove CSS/JS/modale non caricano, nessun
    // bottone quick-view orfano (invisibile senza stili, morto senza JS).
    if (!ghb_quick_view_should_load()) {
        return;
    }

    global $product;
    if (!$product) {
        return;
    }
    // data-size-*: the size the shop filter asked for (includes/size-match.php).
    $size_attrs = ($product instanceof WC_Product && function_exists('ghb_size_match_button_attrs'))
        ? ghb_size_match_button_attrs($product)
        : '';
    echo '<button class="ghb-qv-btn" data-product-id="' . esc_attr($product->get_id()) . '"' . $size_attrs . ' aria-label="' . esc_attr__('Anteprima rapida', 'golden-hive-blocks') . '">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
    </button>';
}

/**
 * AJAX handler note: the `ghb_quick_view` endpoint (and its identical payload)
 * is already registered, site-wide and unconditionally, by
 * includes/product-ajax.php (ghb_quick_view_handler). We
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
 * Assets — styles + vanilla-JS behaviour, on product-UI pages only.
 */
add_action('wp_enqueue_scripts', 'ghb_quick_view_assets');
function ghb_quick_view_assets()
{
    if (!ghb_quick_view_should_load()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-quick-view',
        gh_asset_url('quick-view.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-quick-view',
        gh_asset_url('js/quick-view.js'),
        array(),
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
            'cartUrl'      => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
            // "Chiedi su WhatsApp" in the modal ('' hides it): includes/product-page.php.
            'whatsapp'     => function_exists('ghb_whatsapp_number') ? ghb_whatsapp_number() : '',
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
    <div class="ghb-qv-overlay"></div>
    <div class="ghb-qv-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Anteprima rapida prodotto', 'golden-hive-blocks'); ?>" aria-hidden="true">
        <button class="ghb-qv-close" aria-label="<?php echo esc_attr__('Chiudi', 'golden-hive-blocks'); ?>">&#10005;</button>
        <div class="ghb-qv-content"></div>
    </div>
    <?php
}
