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
 *   • the size attribute being ghb_size_attribute() (pa_taglia unless filtered;
 *     the stylesheet's selectors follow it)
 *
 * It formats prices in the store's currency and language (e.g. "120 €"),
 * passed to the script with the size attribute. It loads with Shoptimizer
 * as the theme only (theme module 'swatch-prices', theme-compat.php; force it
 * on there if CommerceKit is kept on another theme). If you rename the size
 * attribute or change locale it stops applying — silently (no errors;
 * swatches just render without the price labels).
 *
 * What it does: reads WooCommerce's own data-product_variations JSON off the
 * variations form and renders the matching price under each in-stock size
 * swatch, greying out the out-of-stock sizes. Greyed sizes stay clickable:
 * CommerceKit's Waitlist shows its form when a sold-out size is selected, so
 * blocking the click (as this module did until 5.13.0) hid the waitlist.
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
    if (!function_exists('is_product') || !is_product() || !ghb_theme_module('swatch-prices')) {
        return;
    }

    // The stylesheet's selectors name CommerceKit's pa_taglia swatches. With
    // another size attribute (ghb_atc_size_attribute) the same rules are
    // printed with its name instead.
    $attribute = ghb_size_attribute();
    if ('pa_taglia' === $attribute) {
        wp_enqueue_style(
            'golden-hive-variation-swatch-prices',
            gh_asset_url('variation-swatch-prices.css'),
            array(),
            GOLDEN_HIVE_BLOCKS_VERSION
        );
    } else {
        $file = GOLDEN_HIVE_BLOCKS_PATH . (file_exists(GOLDEN_HIVE_BLOCKS_PATH . 'variation-swatch-prices.min.css') ? 'variation-swatch-prices.min.css' : 'variation-swatch-prices.css');
        wp_register_style('golden-hive-variation-swatch-prices', false, array(), GOLDEN_HIVE_BLOCKS_VERSION);
        wp_enqueue_style('golden-hive-variation-swatch-prices');
        wp_add_inline_style('golden-hive-variation-swatch-prices', str_replace('attribute_pa_taglia', 'attribute_' . $attribute, (string) file_get_contents($file)));
    }

    wp_enqueue_script(
        'golden-hive-variation-swatch-prices',
        gh_asset_url('js/variation-swatch-prices.js'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );

    // Note shown in a sold-out size's price slot ('' = none). E.g. with the
    // Waitlist on: add_filter('ghb_swatch_oos_label', fn() => 'Avvisami').
    wp_localize_script(
        'golden-hive-variation-swatch-prices',
        'ghbSwatchPrices',
        array(
            'oosLabel'  => (string) apply_filters('ghb_swatch_oos_label', __('Esaurito', 'golden-hive-blocks')),
            'attribute' => ghb_size_attribute(),
            'currency'  => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
            'locale'    => str_replace('_', '-', get_locale()),
        )
    );
}
