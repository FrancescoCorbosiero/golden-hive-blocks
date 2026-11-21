<?php
/**
 * Golden Hive — the product AJAX endpoints Quick View and Quick Add read.
 *
 *   ghb_quick_view      everything the Quick View modal shows, in one request:
 *                       gallery, price, stock, description, attributes, the
 *                       size rows and the drop date (js/quick-view.js)
 *   ghb_get_variations  a variable product's sizes for the Quick Add sheet,
 *                       one per physical size (js/quick-add.js)
 *
 * Both are public, read-only and nonce-less so they stay cacheable, which is
 * why they answer only for products the shop itself shows
 * (ghb_product_is_public). Cart writes never come here: they go through
 * WooCommerce's own wc-ajax=add_to_cart, posting the variation ID as
 * product_id, so validation, notices and cart fragments are WooCommerce's.
 *
 * @package Golden_Hive_Blocks
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether anyone may see this product through the public AJAX handlers below.
 *
 * They answer any product ID without a nonce, so they serve only what the shop
 * itself shows: a published product with no password. A variation also needs
 * a published parent, because WooCommerce keeps a draft product's variations
 * in "publish". The answer never depends on who is asking, so a cached
 * response is the same for everyone.
 */
function ghb_product_is_public($product): bool
{
    if (!$product instanceof WC_Product) {
        return false;
    }
    $ids = array_filter(array($product->get_id(), $product->is_type('variation') ? $product->get_parent_id() : 0));
    foreach ($ids as $id) {
        if ('publish' !== get_post_status($id) || '' !== (string) get_post_field('post_password', $id)) {
            return false;
        }
    }
    return true;
}

// AJAX: get variations for variable products
add_action('wp_ajax_ghb_get_variations', 'ghb_get_variations_handler');
add_action('wp_ajax_nopriv_ghb_get_variations', 'ghb_get_variations_handler');

function ghb_get_variations_handler() {
    // Deliberately nonce-less: read-only public catalog data, kept cache-friendly,
    // so it answers only for products the shop shows (ghb_product_is_public).
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!ghb_product_is_public($product) || !$product->is_type('variable')) {
        wp_send_json_error('Prodotto non trovato');
    }

    $available = $product->get_available_variations();

    // Collapse the store's duplicate size spellings ("36 2/3" vs "36-2-3", plus
    // exact "36"/"36" duplicates) to a single option per physical size, keeping
    // the highest-priced variation on a conflict. Only the size attribute is
    // de-duplicated; any other variation attribute passes through untouched.
    // Winners are chosen from the available set so the kept option always maps
    // to a selectable variation.
    $size_attribute = ghb_size_attribute();
    $available_ids  = array();
    foreach ($available as $v) {
        $available_ids[] = (int) $v['variation_id'];
    }
    $unique_sizes = function_exists('ghb_atc_unique_size_options')
        ? ghb_atc_unique_size_options($product, $size_attribute, $available_ids)
        : array();

    $kept_size_values   = array(); // winning slug per size, in display order
    $kept_variation_ids = null;    // null = no size dedup; array = keep only these
    foreach ($unique_sizes as $opt) {
        $kept_size_values[]   = $opt['value'];
        $kept_variation_ids[] = $opt['variation_id'];
    }

    $attributes = [];
    foreach ($product->get_variation_attributes() as $attr_name => $options) {
        $label = wc_attribute_label($attr_name, $product);
        $attributes[] = [
            'name'    => $attr_name,
            'label'   => $label,
            'options' => ($attr_name === $size_attribute && !empty($kept_size_values))
                ? $kept_size_values
                : array_values($options),
        ];
    }

    $variations = [];
    foreach ($available as $v) {
        // Drop the losing duplicate size variations so the matcher only ever
        // resolves to the kept (highest-priced) one.
        if (is_array($kept_variation_ids)
            && !in_array((int) $v['variation_id'], $kept_variation_ids, true)) {
            continue;
        }
        // max_qty: WC returns '' when there is no purchase cap — normalise to -1.
        $max_qty = isset($v['max_qty']) && '' !== $v['max_qty'] && (int) $v['max_qty'] > 0
            ? (int) $v['max_qty']
            : -1;

        $variations[] = [
            'variation_id' => $v['variation_id'],
            'attributes'   => $v['attributes'],
            // Raw WC-generated price markup (del/ins for sales) — safe HTML.
            'price_html'   => $v['price_html'],
            'price_text'   => html_entity_decode(strip_tags($v['price_html'])),
            'is_in_stock'  => $v['is_in_stock'],
            'image'        => $v['image']['thumb_src'] ?? '',
            'max_qty'      => $max_qty,
        ];
    }

    wp_send_json_success([
        'title'      => $product->get_name(),
        'price'      => html_entity_decode(strip_tags($product->get_price_html())),
        // Raw WC-generated price markup so sale prices keep their del/ins styling.
        'price_html' => $product->get_price_html(),
        'image'      => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
        'attributes' => $attributes,
        'variations' => $variations,
    ]);
}

/* Quick View */
add_action('wp_ajax_ghb_quick_view', 'ghb_quick_view_handler');
add_action('wp_ajax_nopriv_ghb_quick_view', 'ghb_quick_view_handler');

function ghb_quick_view_handler() {
    // Deliberately nonce-less: read-only public catalog data, kept cache-friendly,
    // so it answers only for products the shop shows (ghb_product_is_public).
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!ghb_product_is_public($product)) {
        wp_send_json_error('Prodotto non trovato');
    }

    // Images
    $images = [];
    $main_img = wp_get_attachment_image_url($product->get_image_id(), 'medium_large');
    if ($main_img) $images[] = $main_img;

    $gallery_ids = $product->get_gallery_image_ids();
    foreach (array_slice($gallery_ids, 0, 4) as $gid) {
        $url = wp_get_attachment_image_url($gid, 'medium_large');
        if ($url) $images[] = $url;
    }

    // Size picker rows (variable products) — also used to skip the redundant
    // size chip below, since the picker already lists every size.
    $sizes = ($product->is_type('variable') && function_exists('ghb_atc_size_rows'))
        ? ghb_atc_size_rows($product)
        : array();
    $size_attribute = ghb_size_attribute();

    // Attributes
    $attributes = [];
    foreach ($product->get_attributes() as $attr) {
        if ($sizes && $attr->get_name() === $size_attribute) {
            continue;
        }
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
        // Raw WC-generated price markup so sale prices keep their del/ins styling.
        'price_html'  => $product->get_price_html(),
        'short_desc'  => wpautop($product->get_short_description()),
        'images'      => $images,
        'in_stock'    => $product->is_in_stock(),
        'stock_text'  => $product->is_in_stock() ? 'Disponibile' : 'Esaurito',
        'attributes'  => $attributes,
        'categories'  => implode(', ', is_array($categories) ? $categories : []),
        'sku'         => $product->get_sku(),
        // Cart-control data so the Quick View modal needs only ONE request.
        'type'        => $product->get_type(),
        'purchasable' => $product->is_purchasable() && $product->is_in_stock(),
        'sizes'       => $sizes,
        // A drop not out yet (includes/drops.php): the modal says when.
        'drop'        => (function_exists('ghb_drop_is_locked') && ghb_drop_is_locked($product_id))
            ? ['ts' => ghb_drop_ts($product_id), 'label' => ghb_drop_card_label(ghb_drop_ts($product_id))]
            : null,
    ]);
}
