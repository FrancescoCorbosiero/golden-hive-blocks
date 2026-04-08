<?php
/**
 * Plugin Name: Golden Hive Blocks
 * Plugin URI: https://goldenhive.it
 * Description: Blocchi Gutenberg premium per e-commerce streetwear e sneakers. Stile moderno e professionale per il tuo store.
 * Version: 5.0.0
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

define('GOLDEN_HIVE_BLOCKS_VERSION', '5.0.0');
define('GOLDEN_HIVE_BLOCKS_PATH', plugin_dir_path(__FILE__));
define('GOLDEN_HIVE_BLOCKS_URL', plugin_dir_url(__FILE__));

/**
 * Registra gli assets frontend
 */
function golden_hive_blocks_enqueue_assets()
{
    wp_enqueue_script(
        'golden-hive-animations',
        GOLDEN_HIVE_BLOCKS_URL . 'js/animations.js',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        true
    );

    wp_enqueue_style(
        'golden-hive-blocks-style',
        GOLDEN_HIVE_BLOCKS_URL . 'style.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'golden_hive_blocks_enqueue_assets');

/**
 * Registra gli assets per l'editor
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
 * Registra tutti i blocchi automaticamente dalla directory blocks/
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
 * Registra la categoria personalizzata per i blocchi
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
 * Carica le traduzioni
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
 * Include shortcodes
 */
require_once GOLDEN_HIVE_BLOCKS_PATH . 'includes/product-carousel-shortcode.php';