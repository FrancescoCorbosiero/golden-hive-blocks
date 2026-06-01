<?php
/**
 * Golden Hive — Shop grid layout alignment.
 *
 * Forces a uniform product-card layout on shop / taxonomy / search loops:
 * flex-column cards, fixed 4:3 image ratio, 2-line clamped titles, and price +
 * add-to-cart pinned to the bottom. A small JS pass equalizes card heights per
 * row (for filters / infinite scroll / late-loading images).
 *
 * Migrated from a Code Snippet. The bottom-align rule targets our `.ghb-atc`
 * add-to-cart control (which replaced the core `.button` / old
 * `.rp-quick-add-btn`). Mostly generic WooCommerce markup.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loop contexts where the grid layout should apply.
 */
function ghb_shop_grid_is_loop()
{
    return function_exists('is_shop')
        && (is_shop() || is_product_taxonomy() || is_search());
}

add_action('wp_enqueue_scripts', 'ghb_shop_grid_assets');
function ghb_shop_grid_assets()
{
    if (!ghb_shop_grid_is_loop()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-shop-grid',
        GOLDEN_HIVE_BLOCKS_URL . 'shop-grid.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-shop-grid',
        GOLDEN_HIVE_BLOCKS_URL . 'js/shop-grid.js',
        array('jquery'),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true)
    );
}
