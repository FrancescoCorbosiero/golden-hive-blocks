<?php
/**
 * Plugin Name: Golden Hive Blocks
 * Plugin URI: https://goldenhive.it
 * Description: Blocchi Gutenberg premium per e-commerce streetwear e sneakers. Stile moderno e professionale per il tuo store.
 * Version: 5.7.0
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

define('GOLDEN_HIVE_BLOCKS_VERSION', '5.7.0');
define('GOLDEN_HIVE_BLOCKS_PATH', plugin_dir_path(__FILE__));
define('GOLDEN_HIVE_BLOCKS_URL', plugin_dir_url(__FILE__));

/**
 * Shared render helpers (gh_asset_url, gh_button, gh_icon, gh_img,
 * gh_kses_map_iframe) — loaded first, everything below uses them.
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/render-helpers.php';

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
        gh_asset_url('js/animations.js'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}
add_action('wp_enqueue_scripts', 'golden_hive_blocks_enqueue_assets');

/**
 * Animation bootstrap — printed inline in <head>, before the stylesheet.
 *
 * Adds `gh-js` to <html> so the scroll-reveal "hidden" states (opacity:0) apply
 * ONLY while JavaScript is actually running. Server-rendered block content is
 * therefore always painted: if js/animations.js is disabled, blocked, deferred,
 * or throws (a frequent side effect of a CDN / Cloudflare Rocket Loader), the
 * content stays visible instead of being trapped invisible until full load.
 *
 * A failsafe timer adds `gh-anim-failsafe` if the engine hasn't signalled
 * `window.__ghAnimReady` shortly after load, force-revealing everything as a
 * last resort. `data-cfasync="false"` keeps Rocket Loader from deferring this
 * tiny bootstrap, so `gh-js` is set before first paint (no flash).
 *
 * The window is 3000ms (was 1500ms): the no-JS case is already covered by
 * gating hidden states on `html.gh-js`, so the failsafe only needs to catch a
 * script that *errored*, and on slow mobile connections the deferred engine
 * routinely boots after 1.5s — which used to permanently disable animations
 * for the whole pageview. The engine also removes the class if it boots late.
 */
function golden_hive_blocks_anim_bootstrap()
{
    echo '<script data-cfasync="false">(function(d){var h=d.documentElement;h.className+=" gh-js";window.__ghAnimReady=false;window.setTimeout(function(){if(!window.__ghAnimReady){h.className+=" gh-anim-failsafe";}},3000);})(document);</script>' . "\n";
}
add_action('wp_head', 'golden_hive_blocks_anim_bootstrap', 1);

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
 * Shared editor utilities (window.ghEditorUtils) — registered early so each
 * block's editor.asset.php can declare `gh-editor-utils` as a dependency and
 * WordPress loads it before the per-block editor.js files.
 */
function golden_hive_blocks_register_editor_utils()
{
    wp_register_script(
        'gh-editor-utils',
        GOLDEN_HIVE_BLOCKS_URL . 'blocks/shared/editor-utils.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'),
        GOLDEN_HIVE_BLOCKS_VERSION,
        true
    );
}
add_action('init', 'golden_hive_blocks_register_editor_utils', 5);

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
 * Include the newsletter signup endpoint (real subscriptions — the block's
 * JS used to simulate success without sending anything).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/newsletter.php';

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
 * Include the loop add-to-cart button + the quick-add modal it opens.
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/add-to-cart.php';
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/quick-add.php';

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

/**
 * Include the parallax archive hero (shop / category / tag pages).
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/archive-hero.php';
