<?php
/**
 * Golden Hive — full-page cache purge.
 *
 * One place that empties whichever page cache the site runs (LiteSpeed, WP
 * Rocket, W3 Total Cache, WP Super Cache, SiteGround Optimizer), for the
 * moments a cached page is wrong for everyone at once: a Store Hub write
 * (includes/hub-rails.php) and a drop going live (includes/drops.php).
 * A CDN can hook ghb_page_caches_purged.
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function ghb_purge_page_caches(): void
{
    do_action('litespeed_purge_all');
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    if (function_exists('sg_cachepress_purge_cache')) {
        sg_cachepress_purge_cache();
    }
    do_action('ghb_page_caches_purged');
}
