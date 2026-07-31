<?php
/**
 * Golden Hive — Loop "Add to cart" with inline size picker.
 *
 * Replaces WooCommerce's core loop add-to-cart button on shop / taxonomy /
 * search cards with our own:
 *   • Simple products  → one-click AJAX add to cart.
 *   • Variable products → an inline size picker (built from the `pa_taglia`
 *                         attribute); picking a size adds that variation.
 *   • Anything we can't resolve sizes for → a "Seleziona opzioni" link to the
 *                         product page (graceful fallback).
 *
 * Adds go through WooCommerce's native `wc-ajax=add_to_cart` endpoint by
 * posting the variation ID as product_id — WooCommerce resolves the parent and
 * variation attributes itself, so cart validation and fragment refresh are all
 * native (no custom cart writes).
 *
 * The size attribute defaults to `pa_taglia` (Shoptimizer/CommerceKit) and is
 * filterable via `ghb_atc_size_attribute`. Removal of the core loop button is
 * filterable via `ghb_atc_remove_core_loop_button` (default true).
 *
 * NOTE: sizes are resolved server-side per variable card, which loads that
 * product's variations on render. If shop pages get heavy, we can switch to
 * lazy-loading the picker on first click.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared gate for the whole product-UI cluster (quick view, quick add, loop
 * add-to-cart). Product cards render on WooCommerce pages, cart/checkout
 * cross-sells, search results, and any singular content embedding one of our
 * product shortcodes — everywhere else the assets and modal shells are dead
 * weight, so we skip them.
 *
 * Defined here because includes/quick-view.php, includes/quick-add.php and
 * this file are all require_once'd from the main plugin file; they all call
 * this one helper at hook time.
 *
 * The final decision goes through the `ghb_product_ui_should_load` filter so
 * edge cases (FSE templates, widgets or reusable blocks containing product
 * shortcodes) can force it on:
 *
 *     add_filter('ghb_product_ui_should_load', '__return_true');
 */
function ghb_product_ui_should_load()
{
    // Called per loop product (button render) oltre che dagli enqueue:
    // memoizza la decisione per request. Il filtro gira comunque una volta.
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }

    if (!class_exists('WooCommerce')) {
        return $memo = (bool) apply_filters('ghb_product_ui_should_load', false);
    }

    $load = (function_exists('is_woocommerce') && is_woocommerce())
        || is_cart()
        || is_checkout()
        || is_search();

    if (!$load && is_singular()) {
        $post = get_post();
        if ($post instanceof WP_Post) {
            $tags = array(
                // Shortcode del plugin…
                'gh_product_rail',
                'product_carousel',
                'carousel_section',
                'bestsellers',
                'new_arrivals',
                'on_sale',
                'featured_products',
                // …e gli shortcode loop nativi di WooCommerce, che sparano
                // gli stessi hook woocommerce_after_shop_loop_item.
                'products',
                'product_category',
                'sale_products',
                'best_selling_products',
                'recent_products',
                'top_rated_products',
                'related_products',
            );
            foreach ($tags as $tag) {
                if (has_shortcode($post->post_content, $tag)) {
                    $load = true;
                    break;
                }
            }

            // Blocchi prodotto Gutenberg (woocommerce/product-collection,
            // product-on-sale, handpicked-products, …): match sul prefisso.
            if (!$load && strpos($post->post_content, '<!-- wp:woocommerce/') !== false) {
                $load = true;
            }
        }
    }

    return $memo = (bool) apply_filters('ghb_product_ui_should_load', $load);
}

/**
 * Whether the add-to-cart UI should load its assets. Kept as a named wrapper
 * for back-compat; the real decision lives in ghb_product_ui_should_load().
 */
function ghb_atc_is_loop()
{
    return ghb_product_ui_should_load();
}

/**
 * Swap the core loop button for ours (filterable).
 */
add_action('wp', 'ghb_atc_replace_core_button');
function ghb_atc_replace_core_button()
{
    // Stesso gate degli asset: dove CSS/JS/modali non caricano, il bottone
    // core di WooCommerce resta al suo posto (fail-safe: mai bottoni morti).
    if (!ghb_product_ui_should_load()) {
        return;
    }

    if (apply_filters('ghb_atc_remove_core_loop_button', true)) {
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
    }
}

add_action('woocommerce_after_shop_loop_item', 'ghb_atc_render_button', 10);

/**
 * Render our add-to-cart control for the current loop product. Clicking it opens
 * the bottom-sheet quick-add modal (see includes/quick-add.php): simple products
 * add directly (.ghb-simple-add-btn), variable products open the size picker
 * (.ghb-quick-add-btn).
 */
function ghb_atc_render_button()
{
    // Niente bottoni senza gli asset che li fanno funzionare (vedi gate).
    if (!ghb_product_ui_should_load()) {
        return;
    }

    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }

    // Simple (and other directly-purchasable) products → direct add.
    if ($product->is_type('simple')) {
        if (!$product->is_purchasable() || !$product->is_in_stock()) {
            echo '<div class="ghb-atc ghb-atc--disabled"><span class="ghb-atc-trigger" aria-disabled="true">'
                . esc_html__('Esaurito', 'golden-hive-blocks') . '</span></div>';
            return;
        }
        printf(
            '<div class="ghb-atc"><button type="button" class="ghb-atc-trigger ghb-simple-add-btn" data-product-id="%d">%s</button></div>',
            (int) $product->get_id(),
            esc_html__('Aggiungi al carrello', 'golden-hive-blocks')
        );
        return;
    }

    // Variable products → open the quick-add modal to choose a size.
    if ($product->is_type('variable')) {
        printf(
            '<div class="ghb-atc"><button type="button" class="ghb-atc-trigger ghb-quick-add-btn" data-product-id="%d">%s</button></div>',
            (int) $product->get_id(),
            esc_html__('Aggiungi al carrello', 'golden-hive-blocks')
        );
        return;
    }

    // Grouped / external / etc. — just link to the product.
    printf(
        '<div class="ghb-atc"><a class="ghb-atc-trigger" href="%s">%s</a></div>',
        esc_url($product->get_permalink()),
        esc_html__('Vedi prodotto', 'golden-hive-blocks')
    );
}

/**
 * Normalize a size label into a key that ignores separator style.
 *
 * This store carries the same physical size under two term spellings — a
 * space/slash form ("36 2/3", "37 1/3") and a dash form ("36-2-3", "37-1-3") —
 * plus exact duplicates for whole sizes ("36" twice). Stripping every
 * non-alphanumeric character and lowercasing collapses all of these to one key
 * ("36 2/3", "36-2-3" → "3623"; "36", "36" → "36"), so equal sizes group
 * together regardless of how they were typed.
 */
function ghb_normalize_size_key($label)
{
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $label));
}

/**
 * Deduplicated size options for a variable product.
 *
 * Collapses the duplicate size spellings described in ghb_normalize_size_key()
 * to a single option per physical size. When more than one variation maps to
 * the same size ("real conflict") we keep the one with the HIGHEST price (an
 * in-stock, purchasable variation breaks a price tie), which drops the stray
 * low-priced/low-stock import duplicates in favour of the real listing.
 *
 * @param WC_Product $product   Variable product.
 * @param string     $attribute Size attribute (defaults to the filtered pa_taglia).
 * @param int[]|null $only_ids  When given, only these variation IDs are considered
 *                              (e.g. the modal's available set) so the kept winner
 *                              is always a real, selectable variation.
 * @return array<int,array> Ordered unique sizes, each:
 *   [ variation_id, label, value (slug, for JS matching), price (float), in_stock (bool) ].
 */
function ghb_atc_unique_size_options($product, $attribute = null, $only_ids = null)
{
    $attribute = $attribute ?: apply_filters('ghb_atc_size_attribute', 'pa_taglia');
    $allow = is_array($only_ids) ? array_map('intval', $only_ids) : null;
    $groups = array(); // normalized key => winning entry (first-seen position preserved)

    foreach ($product->get_children() as $variation_id) {
        if (null !== $allow && !in_array((int) $variation_id, $allow, true)) {
            continue;
        }
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            continue;
        }
        $label = $variation->get_attribute($attribute);
        if ('' === $label) {
            continue;
        }
        $key = ghb_normalize_size_key($label);
        if ('' === $key) {
            continue;
        }

        $price    = (float) $variation->get_price();
        $in_stock = $variation->is_in_stock() && $variation->is_purchasable();
        $attrs    = $variation->get_variation_attributes();
        $entry = array(
            'variation_id' => (int) $variation_id,
            'label'        => $label,
            'value'        => $attrs['attribute_' . $attribute] ?? $label,
            'price'        => $price,
            'in_stock'     => $in_stock,
        );

        if (!isset($groups[$key])) {
            $groups[$key] = $entry;
            continue;
        }

        // Real conflict: same size, different variation. Highest price wins;
        // on a price tie prefer one that is actually in stock / purchasable.
        $current = $groups[$key];
        if ($price > $current['price']
            || ($price === $current['price'] && $in_stock && !$current['in_stock'])) {
            $groups[$key] = $entry;
        }
    }

    return array_values($groups);
}

/**
 * Build the size rows for a variable product: [ variation_id, label, in_stock ].
 *
 * Sizes are deduplicated (see ghb_atc_unique_size_options) so each physical
 * size appears once, keeping the highest-priced variation on a conflict.
 */
function ghb_atc_size_rows($product)
{
    $rows = array();
    foreach (ghb_atc_unique_size_options($product) as $opt) {
        $rows[] = array(
            'variation_id' => $opt['variation_id'],
            'label'        => $opt['label'],
            'in_stock'     => $opt['in_stock'],
        );
    }

    return $rows;
}

/**
 * Assets — just the button styles. The behaviour now lives in the quick-add
 * modal (includes/quick-add.php), so there's no separate add-to-cart script.
 */
add_action('wp_enqueue_scripts', 'ghb_atc_assets');
function ghb_atc_assets()
{
    if (!ghb_atc_is_loop()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-add-to-cart',
        gh_asset_url('add-to-cart.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
}
