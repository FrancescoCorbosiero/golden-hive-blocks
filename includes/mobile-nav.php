<?php
/**
 * Golden Hive — Mobile nav caret (+/-) toggle + submenu polish.
 *
 * ⚠ THEME-SPECIFIC — Shoptimizer / CommerceGurus.
 * ──────────────────────────────────────────────────────────────────
 * Improves the mobile navigation accordion: a clean +/- caret toggle, roomier
 * submenu rows with proper tap targets and hierarchy, accordion behaviour
 * (one open branch per level), ARIA + keyboard support, and a gentle entrance
 * animation. Relies on Shoptimizer markup that is NOT generic WordPress:
 * `.main-navigation`, `.menu-item-has-children`, `.caret`, `.sub-menu-wrapper`,
 * and Shoptimizer's own `.cg-open` class (which controls submenu visibility —
 * we deliberately do not override that, only layer styling/behaviour on top).
 *
 * Loads site-wide on the front end (the nav is in every header). Fails harmlessly
 * if the markup isn't present. Migrated from a Code Snippet — disable that
 * snippet once this is active.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'ghb_mobile_nav_assets');
function ghb_mobile_nav_assets()
{
    wp_enqueue_style(
        'golden-hive-mobile-nav',
        GOLDEN_HIVE_BLOCKS_URL . 'mobile-nav.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-mobile-nav',
        GOLDEN_HIVE_BLOCKS_URL . 'js/mobile-nav.js',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}
