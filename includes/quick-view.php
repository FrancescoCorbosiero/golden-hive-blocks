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
 * True on the loop contexts where the Quick View UI should load.
 */
function ghb_quick_view_is_loop()
{
    return function_exists('is_shop')
        && (is_shop() || is_product_taxonomy() || is_search());
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
 * AJAX handler — returns the product payload the modal renders.
 */
add_action('wp_ajax_ghb_quick_view', 'ghb_quick_view_handler');
add_action('wp_ajax_nopriv_ghb_quick_view', 'ghb_quick_view_handler');
function ghb_quick_view_handler()
{
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error('Prodotto non trovato');
    }

    // Images
    $images = [];
    $main_img = wp_get_attachment_image_url($product->get_image_id(), 'medium_large');
    if ($main_img) {
        $images[] = $main_img;
    }

    $gallery_ids = $product->get_gallery_image_ids();
    foreach (array_slice($gallery_ids, 0, 4) as $gid) {
        $url = wp_get_attachment_image_url($gid, 'medium_large');
        if ($url) {
            $images[] = $url;
        }
    }

    // Attributes
    $attributes = [];
    foreach ($product->get_attributes() as $attr) {
        $attributes[] = [
            'label' => wc_attribute_label($attr->get_name()),
            'value' => $product->get_attribute($attr->get_name()),
        ];
    }

    // Categories
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

    wp_send_json_success([
        'title'       => $product->get_name(),
        'url'         => get_permalink($product_id),
        'price'       => html_entity_decode(strip_tags($product->get_price_html())),
        'short_desc'  => wpautop($product->get_short_description()),
        'images'      => $images,
        'in_stock'    => $product->is_in_stock(),
        'stock_text'  => $product->is_in_stock() ? 'Disponibile' : 'Esaurito',
        'attributes'  => $attributes,
        'categories'  => implode(', ', $categories),
        'sku'         => $product->get_sku(),
    ]);
}

/**
 * Assets — styles + jQuery-based behaviour, on loop pages only.
 */
add_action('wp_enqueue_scripts', 'ghb_quick_view_assets');
function ghb_quick_view_assets()
{
    if (!ghb_quick_view_is_loop()) {
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
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => 'ghb_quick_view',
        )
    );
}

/**
 * Modal container — server-rendered once per loop page.
 */
add_action('wp_footer', 'ghb_quick_view_modal');
function ghb_quick_view_modal()
{
    if (!ghb_quick_view_is_loop()) {
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
