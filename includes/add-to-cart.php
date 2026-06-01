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
 * Whether the add-to-cart UI should load its assets. The picker renders via the
 * standard WooCommerce loop hook, which can appear on any front-end page
 * (shop, category, search, related products, native sliders, [gh_product_rail]),
 * so load the assets wherever WooCommerce is active.
 */
function ghb_atc_is_loop()
{
    return class_exists('WooCommerce');
}

/**
 * Swap the core loop button for ours (filterable).
 */
add_action('wp', 'ghb_atc_replace_core_button');
function ghb_atc_replace_core_button()
{
    if (apply_filters('ghb_atc_remove_core_loop_button', true)) {
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
    }
}

add_action('woocommerce_after_shop_loop_item', 'ghb_atc_render_button', 10);

/**
 * Render our add-to-cart control for the current loop product.
 */
function ghb_atc_render_button()
{
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }

    // Simple (and other directly-purchasable) products.
    if ($product->is_type('simple')) {
        if (!$product->is_purchasable() || !$product->is_in_stock()) {
            echo '<div class="ghb-atc ghb-atc--disabled"><span class="ghb-atc-trigger" aria-disabled="true">'
                . esc_html__('Esaurito', 'golden-hive-blocks') . '</span></div>';
            return;
        }
        printf(
            '<div class="ghb-atc" data-type="simple"><button type="button" class="ghb-atc-trigger" data-product-id="%d">%s</button></div>',
            (int) $product->get_id(),
            esc_html__('Aggiungi al carrello', 'golden-hive-blocks')
        );
        return;
    }

    // Variable products → inline size picker.
    if ($product->is_type('variable')) {
        $rows = ghb_atc_size_rows($product);

        if (empty($rows)) {
            // Couldn't resolve sizes — send them to the product page to choose.
            printf(
                '<div class="ghb-atc"><a class="ghb-atc-trigger" href="%s">%s</a></div>',
                esc_url($product->get_permalink()),
                esc_html__('Seleziona opzioni', 'golden-hive-blocks')
            );
            return;
        }

        $pills = '';
        foreach ($rows as $row) {
            if ($row['in_stock']) {
                $pills .= sprintf(
                    '<button type="button" class="ghb-atc-size" data-variation-id="%d">%s</button>',
                    (int) $row['variation_id'],
                    esc_html($row['label'])
                );
            } else {
                $pills .= sprintf(
                    '<span class="ghb-atc-size is-oos">%s</span>',
                    esc_html($row['label'])
                );
            }
        }

        printf(
            '<div class="ghb-atc" data-type="variable"><button type="button" class="ghb-atc-trigger">%s</button><div class="ghb-atc-panel">%s</div></div>',
            esc_html__('Aggiungi al carrello', 'golden-hive-blocks'),
            $pills // already escaped per-pill above
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
 * Build the size rows for a variable product: [ variation_id, label, in_stock ].
 */
function ghb_atc_size_rows($product)
{
    $attribute = apply_filters('ghb_atc_size_attribute', 'pa_taglia');
    $rows = array();

    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            continue;
        }
        $label = $variation->get_attribute($attribute);
        if ('' === $label) {
            continue;
        }
        $rows[] = array(
            'variation_id' => $variation_id,
            'label'        => $label,
            'in_stock'     => $variation->is_in_stock() && $variation->is_purchasable(),
        );
    }

    return $rows;
}

/**
 * Assets — styles + jQuery-based behaviour, on loop contexts only.
 */
add_action('wp_enqueue_scripts', 'ghb_atc_assets');
function ghb_atc_assets()
{
    if (!ghb_atc_is_loop()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-add-to-cart',
        GOLDEN_HIVE_BLOCKS_URL . 'add-to-cart.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-add-to-cart',
        GOLDEN_HIVE_BLOCKS_URL . 'js/add-to-cart.js',
        array('jquery'),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true)
    );

    wp_localize_script(
        'golden-hive-add-to-cart',
        'ghbAddToCart',
        array(
            'endpoint' => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('add_to_cart') : '',
        )
    );
}
