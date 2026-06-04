<?php
/**
 * Golden Hive — Per-variation prices on size swatches.
 *
 * ⚠ THEME-SPECIFIC — Shoptimizer + CommerceKit ("CK").
 * ──────────────────────────────────────────────────────────────────
 * Unlike the rest of this plugin (which targets WooCommerce generically), this
 * feature is tightly coupled to the Shoptimizer theme's CommerceKit attribute
 * swatches. It depends on markup and classes that are NOT WooCommerce core:
 *
 *   • .cgkit-attribute-swatches[data-attribute="attribute_pa_taglia"]
 *   • .cgkit-swatch  with  data-attribute-value / data-attribute-text
 *   • the size attribute being the `pa_taglia` taxonomy
 *
 * It also formats prices for it-IT (e.g. "120 €"). If you switch away from
 * Shoptimizer/CommerceKit, rename the size attribute, or change locale, this
 * stops applying — but it fails silently (no errors; swatches just render
 * without the price labels), so it is safe to leave enabled.
 *
 * What it does: reads WooCommerce's own data-product_variations JSON off the
 * variations form and renders the matching price under each in-stock size
 * swatch, greying out the out-of-stock sizes.
 *
 * Migrated here from a Code Snippet so it lives in version control alongside
 * the rest of the store's UI. NOTE: disable the old Code Snippet once this is
 * active, or the script will run twice (harmless but redundant).
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'ghb_variation_swatch_prices_assets');
function ghb_variation_swatch_prices_assets()
{
    // Single-product pages only — nothing to enhance elsewhere.
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-variation-swatch-prices',
        GOLDEN_HIVE_BLOCKS_URL . 'variation-swatch-prices.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-variation-swatch-prices',
        GOLDEN_HIVE_BLOCKS_URL . 'js/variation-swatch-prices.js',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}
