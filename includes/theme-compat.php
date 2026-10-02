<?php
/**
 * Golden Hive — theme coupling, in one place.
 *
 * The plugin is written against WordPress + WooCommerce and runs on any
 * theme. A few modules are written against Shoptimizer (CommerceGurus) and
 * CommerceKit, the companion plugin it ships with, because they style or
 * script that markup and nothing else:
 *
 *   theme-bridge        theme-bridge.css: the theme's product cards, buttons,
 *                       fields and pagination on the shape scale, and the
 *                       desktop filters drawer (the theme's #secondary
 *                       sidebar turned into a panel)
 *   mobile-nav          mobile-nav.css / js/mobile-nav.js: the theme's mobile
 *                       menu accordion (.main-navigation, .caret, .cg-open)
 *   swatch-prices       prices under CommerceKit's size swatches (.cgkit-*)
 *   swatch-sale-badges  "%" badge on CommerceKit's swatches on sale
 *
 * Each one asks ghb_theme_module() before it loads anything, so on another
 * theme they stay off: no CSS or JS shipped for markup that isn't there, no
 * Customizer option that can't work. THEME-COUPLING.md maps every coupling
 * point, the softer ones too (classic WooCommerce templates, pa_taglia,
 * Relevanssi), and what a theme change would take.
 *
 * Force a module either way, e.g. CommerceKit kept on another theme:
 *   add_filter('ghb_theme_module', function ($on, $module) {
 *       return in_array($module, array('swatch-prices', 'swatch-sale-badges'), true) ? true : $on;
 *   }, 10, 2);
 *
 * @package Golden_Hive_Blocks
 * @since   5.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Shoptimizer is the parent theme (a child theme of it counts). */
function ghb_theme_is_shoptimizer(): bool
{
    return 'shoptimizer' === get_template();
}

/**
 * Whether a theme-specific module runs: 'theme-bridge', 'mobile-nav',
 * 'swatch-prices' or 'swatch-sale-badges'. Ask from a hook, not when the file
 * loads: the Customizer's theme preview switches the theme after plugins load.
 */
function ghb_theme_module(string $module): bool
{
    return (bool) apply_filters('ghb_theme_module', ghb_theme_is_shoptimizer(), $module);
}
