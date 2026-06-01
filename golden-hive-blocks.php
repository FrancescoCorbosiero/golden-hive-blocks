<?php
/**
 * Plugin Name: Golden Hive Blocks
 * Plugin URI: https://goldenhive.it
 * Description: Blocchi Gutenberg premium per e-commerce streetwear e sneakers. Stile moderno e professionale per il tuo store.
 * Version: 5.3.0
 * Author: Golden Hive
 * Author URI: https://goldenhive.it
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: golden-hive-blocks
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GOLDEN_HIVE_BLOCKS_VERSION', '5.3.0');
define('GOLDEN_HIVE_BLOCKS_PATH', plugin_dir_path(__FILE__));
define('GOLDEN_HIVE_BLOCKS_URL', plugin_dir_url(__FILE__));

/**
 * Frontend assets.
 *
 * The previous "conditional" gate relied on `has_block($post)` which silently
 * skipped the stylesheet on archives, FSE templates, pages where `$post` isn't
 * the primary query, custom HTML blocks using `.gh-*` classes, etc. — leaving
 * those pages unstyled. CSS is small (~80KB, cached) so we always enqueue it,
 * and the animations script is loaded with `defer` so it never blocks paint.
 */
function golden_hive_blocks_enqueue_assets()
{
    // Prefer the minified stylesheet (run `npm run build:css` after editing
    // style.css); fall back to the source if it hasn't been built.
    $style_file = file_exists(GOLDEN_HIVE_BLOCKS_PATH . 'style.min.css')
        ? 'style.min.css'
        : 'style.css';

    wp_enqueue_style(
        'golden-hive-blocks-style',
        GOLDEN_HIVE_BLOCKS_URL . $style_file,
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-animations',
        GOLDEN_HIVE_BLOCKS_URL . 'js/animations.js',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}
add_action('wp_enqueue_scripts', 'golden_hive_blocks_enqueue_assets');

/**
 * Editor assets — only loaded inside the block editor.
 */
function golden_hive_blocks_editor_assets()
{
    wp_enqueue_style(
        'golden-hive-blocks-editor',
        GOLDEN_HIVE_BLOCKS_URL . 'editor.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
}
add_action('enqueue_block_editor_assets', 'golden_hive_blocks_editor_assets');

/**
 * Register all blocks from the blocks/ directory.
 */
function golden_hive_blocks_register()
{
    $blocks_dir = GOLDEN_HIVE_BLOCKS_PATH . 'blocks/';

    if (!is_dir($blocks_dir)) {
        return;
    }

    $block_folders = array_filter(glob($blocks_dir . '*'), 'is_dir');

    foreach ($block_folders as $block) {
        $block_json = $block . '/block.json';
        if (file_exists($block_json)) {
            register_block_type($block);
        }
    }
}
add_action('init', 'golden_hive_blocks_register');

/**
 * Register the custom block category.
 */
function golden_hive_blocks_category($categories)
{
    return array_merge(
        array(
            array(
                'slug' => 'golden-hive',
                'title' => __('Golden Hive', 'golden-hive-blocks'),
                'icon' => 'star-filled',
            ),
        ),
        $categories
    );
}
add_filter('block_categories_all', 'golden_hive_blocks_category', 10, 1);

/**
 * Load translations.
 */
function golden_hive_blocks_load_textdomain()
{
    load_plugin_textdomain(
        'golden-hive-blocks',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'golden_hive_blocks_load_textdomain');

/**
 * Include shortcodes.
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/product-carousel-shortcode.php';

/**
 * Include preload + speculation rules (Tools → GH Preload).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/preload-speculation.php';

/**
 * Include the Live Ajax Search modal (UI layer for Relevanssi Live Ajax Search).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/live-search.php';

/**
 * Include per-variation prices on size swatches (Shoptimizer + CommerceKit).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/variation-swatch-prices.php';

/**
 * Include on-sale highlight on variation swatches (Shoptimizer + CommerceKit).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/swatch-sale-badges.php';

/**
 * Include the WooCommerce Quick View modal.
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/quick-view.php';

/**
 * Include the loop "Add to cart" with inline size picker.
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/add-to-cart.php';

/**
 * Include the shop grid layout alignment.
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/shop-grid.php';

/**
 * Include the mobile nav caret toggle + submenu polish (Shoptimizer).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/mobile-nav.php';

/**
 * Include the lightweight product rail (CSS scroll-snap carousel).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/product-rail.php';
