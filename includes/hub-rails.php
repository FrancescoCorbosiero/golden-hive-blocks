<?php
/**
 * Golden Hive — Store Hub bridge ("Vetrina"): the rails on the storefront.
 *
 * The Store Hub's Vetrina is a mobile editor for the homepage. The page stays
 * the single source of truth: what a rail shows and in what order lives in its
 * own shortcode —
 *
 *   [gh_product_rail category="saldi" limit="18" pin="12,5,9" exclude="4" fallback="date"]
 *
 *   pin       products shown first, in this order (only members that are visible)
 *   exclude   products never shown in this rail (they stay in the category)
 *   fallback  order of everything after the pins — a WooCommerce catalog
 *             ordering: menu_order | date | popularity | price | price-desc | rating
 *
 * This file is what the storefront runs: the rail query that honours those
 * attributes, its cache, and the category pages that follow their homepage
 * rail. The REST API the Hub reads and writes through is hub-rest.php; the
 * decisions both make are pure functions in hub-rails-core.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/hub-rails-core.php';

/** Ceiling on the products one rail considers — a runaway guard, not a limit. */
const GHB_HUB_MAX_CANDIDATES = 2000;

/* ==================================================================== *
 * 1. Rail query
 * ==================================================================== */

/** Whether the rail asks for the Vetrina ordering at all. */
function ghb_hub_rail_has_ordering(array $atts): bool
{
    return !empty($atts['pin']) || !empty($atts['exclude']) || !empty($atts['fallback']);
}

/** Mirrors ghb_get_carousel_products(): the shop setting that hides sold-out products. */
function ghb_hub_hide_out_of_stock(array $atts = array()): bool
{
    return (bool) apply_filters('ghb_carousel_hide_out_of_stock', 'yes' === get_option('woocommerce_hide_out_of_stock_items'), $atts);
}

/** Changes whenever a product, its stock or its terms change — part of every cache key. */
function ghb_hub_cache_gen(): int
{
    return (int) get_option('ghb_rail_cache_gen', 1);
}

function ghb_hub_bump_cache_gen(): void
{
    update_option('ghb_rail_cache_gen', ghb_hub_cache_gen() + 1, false);
}

add_action('save_post_product', 'ghb_hub_bump_cache_gen');
add_action('woocommerce_product_set_stock_status', 'ghb_hub_bump_cache_gen');
add_action('woocommerce_variation_set_stock_status', 'ghb_hub_bump_cache_gen');
add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) {
    if (in_array($taxonomy, array('product_cat', 'product_brand', 'pwb-brand', 'pa_brand', 'product_visibility', 'product_tag'), true)) {
        ghb_hub_bump_cache_gen();
    }
}, 10, 4);
add_action('trashed_post', function ($post_id) {
    if ('product' === get_post_type($post_id)) {
        ghb_hub_bump_cache_gen();
    }
});

/**
 * WP_Query ordering args for a fallback, through WooCommerce's own catalog
 * ordering so "popularity" and "price" mean exactly what the shop's sort menu
 * means. Those two attach posts_clauses filters: call
 * ghb_hub_fallback_cleanup() right after the query.
 */
function ghb_hub_fallback_order_args(string $fallback): array
{
    $map = array(
        'menu_order' => array('menu_order', 'ASC'),
        'date'       => array('date', 'DESC'),
        'popularity' => array('popularity', 'DESC'),
        'price'      => array('price', 'ASC'),
        'price-desc' => array('price', 'DESC'),
        'rating'     => array('rating', 'DESC'),
    );
    list($orderby, $order) = isset($map[$fallback]) ? $map[$fallback] : $map['menu_order'];

    if (function_exists('WC') && isset(WC()->query) && WC()->query instanceof WC_Query) {
        $args = WC()->query->get_catalog_ordering_args($orderby, $order);
        return array(
            'orderby'  => $args['orderby'],
            'order'    => $args['order'],
            'meta_key' => isset($args['meta_key']) ? $args['meta_key'] : '', // phpcs:ignore WordPress.DB.SlowDBQuery
        );
    }
    if ('date' === $fallback) {
        return array('orderby' => 'date ID', 'order' => 'DESC', 'meta_key' => '');
    }
    return array('orderby' => 'menu_order title', 'order' => 'ASC', 'meta_key' => '');
}

function ghb_hub_fallback_cleanup(): void
{
    if (function_exists('WC') && isset(WC()->query) && WC()->query instanceof WC_Query) {
        WC()->query->remove_ordering_args();
    }
}

/**
 * Every visible member of a rail, in fallback order, before pins and
 * exclusions are applied. "Visible" is exactly what the rail query filters:
 * published, in the term (children included), not hidden from the catalog,
 * in stock when the shop hides sold-out products.
 *
 * @param array $args ghb_carousel_query_args() output for the rail.
 * @return int[]
 */
function ghb_hub_rail_visible_ids(array $args, string $fallback, bool $fresh = false): array
{
    $key = 'ghb_rail_ids_' . md5((string) wp_json_encode(array($args, $fallback, ghb_hub_cache_gen())));
    if (!$fresh) {
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $query_args = $args;
    unset($query_args['post__in'], $query_args['meta_key'], $query_args['orderby'], $query_args['order']);
    $query_args['fields']              = 'ids';
    $query_args['posts_per_page']      = GHB_HUB_MAX_CANDIDATES;
    $query_args['no_found_rows']       = true;
    $query_args['ignore_sticky_posts'] = true;

    $ordering                = ghb_hub_fallback_order_args($fallback);
    $query_args['orderby']   = $ordering['orderby'];
    $query_args['order']     = $ordering['order'];
    if ('' !== $ordering['meta_key']) {
        $query_args['meta_key'] = $ordering['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery
    }

    $query = new WP_Query($query_args);
    ghb_hub_fallback_cleanup();

    $ids = array_map('intval', $query->posts);
    set_transient($key, $ids, 10 * MINUTE_IN_SECONDS);
    return $ids;
}

/**
 * The ids a rail renders, in order: the effective order of hub-rails-core
 * applied to the visible members, cut at the rail's limit.
 *
 * @return int[]
 */
function ghb_hub_rail_ids(array $atts, bool $fresh = false): array
{
    $args  = ghb_carousel_query_args($atts);
    $limit = max(1, (int) $atts['limit']);

    if (!empty($atts['ids'])) {
        // An explicit list is its own order; pins do not apply.
        $args['fields']        = 'ids';
        $args['no_found_rows'] = true;
        return array_map('intval', (new WP_Query($args))->posts);
    }

    if (!ghb_hub_rail_has_ordering($atts)) {
        // Untouched rail: exactly today's query.
        $args['fields']        = 'ids';
        $args['no_found_rows'] = true;
        return array_map('intval', (new WP_Query($args))->posts);
    }

    $fallback = ghb_hub_normalize_fallback($atts['fallback'] ?? '', $atts);
    $ordered  = ghb_hub_order_ids(
        ghb_hub_rail_visible_ids($args, $fallback, $fresh),
        ghb_hub_parse_id_list($atts['pin'] ?? ''),
        ghb_hub_parse_id_list($atts['exclude'] ?? '')
    );
    return array_slice($ordered, 0, $limit);
}

/**
 * The rail query when the rail uses pin / exclude / fallback: the ordered ids,
 * rendered through post__in so the template loop sees them in that order.
 */
function ghb_hub_rail_query(array $atts): WP_Query
{
    $ids = ghb_hub_rail_ids($atts);
    if (array() === $ids) {
        return new WP_Query(array('post_type' => 'product', 'post__in' => array(0), 'posts_per_page' => 1));
    }
    return new WP_Query(array(
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'post__in'            => $ids,
        'orderby'             => 'post__in',
        'posts_per_page'      => count($ids),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));
}

/** Shortcode attributes of a rail, with the same defaults the shortcode applies. */
function ghb_hub_rail_atts(array $raw): array
{
    return shortcode_atts(ghb_product_rail_defaults(), $raw, GHB_HUB_RAIL_TAG);
}

/* ==================================================================== *
 * 2. Category pages follow their homepage rail
 * ==================================================================== */

/**
 * Pins and exclusions of the front page's rails, keyed "taxonomy:slug".
 * Only rails showing exactly one term; the first rail wins. Cached until the
 * front page changes.
 */
function ghb_hub_archive_rail_map(): array
{
    $page_id = (int) get_option('page_on_front');
    if ($page_id <= 0) {
        return array();
    }
    $page = get_post($page_id);
    if (!$page instanceof WP_Post) {
        return array();
    }
    $cache_key = 'ghb_hub_archive_map_' . md5($page_id . '|' . $page->post_modified_gmt);
    $cached    = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $map = array();
    foreach (ghb_hub_page_rails(parse_blocks($page->post_content)) as $entry) {
        $rail = $entry['rail'];
        if (array() === $rail['pin'] && array() === $rail['exclude']) {
            continue;
        }
        $atts  = ghb_hub_rail_atts($rail['atts']);
        $terms = ghb_hub_rail_terms($atts);
        if (null === $terms['taxonomy'] || 1 !== count($terms['terms'])) {
            continue;
        }
        $term_key = $terms['taxonomy'] . ':' . $terms['terms'][0]['slug'];
        if (!isset($map[$term_key])) {
            $map[$term_key] = array('pin' => $rail['pin'], 'exclude' => $rail['exclude']);
        }
    }
    set_transient($cache_key, $map, DAY_IN_SECONDS);
    return $map;
}

add_action('woocommerce_product_query', 'ghb_hub_archive_follow', 20, 1);
function ghb_hub_archive_follow($query)
{
    if (is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
        return;
    }
    if (!apply_filters('ghb_rail_archive_follow', true)) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!empty($_GET['orderby'])) {
        return; // the visitor picked a sort: theirs wins
    }
    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return;
    }
    $map      = ghb_hub_archive_rail_map();
    $term_key = $term->taxonomy . ':' . $term->slug;
    if (!isset($map[$term_key])) {
        return;
    }
    $state = $map[$term_key];

    if (array() !== $state['exclude']) {
        $query->set('post__not_in', array_merge((array) $query->get('post__not_in'), $state['exclude']));
    }
    if (array() === $state['pin']) {
        return;
    }
    $pins = implode(',', array_map('intval', $state['pin']));
    // Stays hooked and acts on this query only. It used to remove itself
    // mid-run, and WP_Hook then skips the next priority (sold-out-last.php's
    // ordering at 50, or any other plugin's clauses).
    $apply = function ($clauses, $target) use ($query, $pins) {
        if ($target !== $query) {
            return $clauses;
        }
        global $wpdb;
        $field              = "FIELD({$wpdb->posts}.ID, {$pins})";
        $rest               = trim((string) $clauses['orderby']);
        $clauses['orderby'] = "{$field} = 0, {$field}" . ('' !== $rest ? ', ' . $rest : '');
        return $clauses;
    };
    add_filter('posts_clauses', $apply, 20, 2);
}
