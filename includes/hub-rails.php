<?php
/**
 * Golden Hive — Store Hub bridge ("Vetrina").
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
 * This file is the WordPress half: the rail query that honours those
 * attributes, the REST routes the Hub reads and writes through, the category
 * pages that follow their homepage rail, and cache purging. The decisions
 * themselves are pure functions in hub-rails-core.php.
 *
 * REST namespace: wc-gh/v1. WooCommerce authenticates any "wc-*" namespace
 * with its REST API keys, so the Hub uses the consumer key it already holds —
 * no Application Password. Every route requires manage_woocommerce.
 *
 * @package Golden_Hive_Blocks
 * @since   5.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/hub-rails-core.php';

/** REST namespace of the bridge. */
const GHB_HUB_NS = 'wc-gh/v1';

/** Bumped when a response shape changes incompatibly. */
const GHB_HUB_API = 1;

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
 * 2. Reading products and terms for the Hub
 * ==================================================================== */

/**
 * The taxonomy and terms a rail shows, resolved the way the rail query
 * resolves them (brand slugs may live in any of the brand taxonomies).
 */
function ghb_hub_rail_terms(array $atts): array
{
    $out = array('taxonomy' => null, 'terms' => array());
    if (!empty($atts['category'])) {
        $out['taxonomy'] = 'product_cat';
        $slugs           = array_map('trim', explode(',', $atts['category']));
        $taxonomies      = array('product_cat');
    } elseif (!empty($atts['brand'])) {
        $slugs      = array_map('trim', explode(',', $atts['brand']));
        $taxonomies = array('product_brand', 'pwb-brand', 'pa_brand');
    } else {
        return $out;
    }
    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if (!$term || is_wp_error($term)) {
                continue;
            }
            $out['taxonomy'] = $taxonomy;
            $link            = get_term_link($term);
            $out['terms'][]  = array(
                'id'    => (int) $term->term_id,
                'slug'  => $term->slug,
                'name'  => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
                'count' => (int) $term->count,
                'link'  => is_wp_error($link) ? null : $link,
            );
        }
        if (array() !== $out['terms']) {
            break;
        }
    }
    return $out;
}

/**
 * Cards for a set of products, in the given order: what the Hub needs to draw
 * and join them — id, SKU (the Hub's own key), name, the shop's thumbnail
 * size (never the full image on mobile data), price and stock state.
 *
 * @param int[] $ids
 */
function ghb_hub_product_cards(array $ids): array
{
    if (array() === $ids) {
        return array();
    }
    // One query primes post, meta and term caches for every card.
    $query = new WP_Query(array(
        'post_type'      => array('product'),
        'post_status'    => 'any',
        'post__in'       => $ids,
        'orderby'        => 'post__in',
        'posts_per_page' => count($ids),
        'no_found_rows'  => true,
    ));
    unset($query);

    $cards = array();
    foreach ($ids as $id) {
        $product = wc_get_product($id);
        if (!$product) {
            $cards[] = array('id' => (int) $id, 'missing' => true);
            continue;
        }
        $image_id = $product->get_image_id();
        $src      = $image_id ? wp_get_attachment_image_src((int) $image_id, 'woocommerce_thumbnail') : false;
        $price    = $product->get_price();
        $max      = $product->is_type('variable') ? $product->get_variation_price('max') : $price;
        $cards[]  = array(
            'id'           => $product->get_id(),
            'sku'          => (string) $product->get_sku(),
            'name'         => html_entity_decode(wp_strip_all_tags($product->get_name()), ENT_QUOTES, 'UTF-8'),
            'type'         => $product->get_type(),
            'status'       => $product->get_status(),
            'visibility'   => $product->get_catalog_visibility(),
            'stock_status' => $product->get_stock_status(),
            'price'        => '' === $price ? null : (float) $price,
            'price_max'    => '' === $max || null === $max ? null : (float) $max,
            'on_sale'      => $product->is_on_sale(),
            'image'        => $src ? $src[0] : wc_placeholder_img_src('woocommerce_thumbnail'),
            'permalink'    => get_permalink($product->get_id()),
            'edit_link'    => admin_url('post.php?post=' . $product->get_id() . '&action=edit'),
            'created'      => $product->get_date_created() ? $product->get_date_created()->date('c') : null,
        );
    }
    return $cards;
}

/**
 * Why a pinned product is not in its rail — the answer the Vetrina shows next
 * to the greyed-out card.
 */
function ghb_hub_hidden_reason(int $id, array $atts, array $terms): string
{
    $product = wc_get_product($id);
    if (!$product) {
        return 'missing';
    }
    if ('publish' !== $product->get_status()) {
        return 'unpublished';
    }
    if (in_array($id, ghb_hub_parse_id_list($atts['exclude'] ?? ''), true)) {
        return 'excluded';
    }
    if (!empty($terms['taxonomy']) && array() !== $terms['terms']) {
        $wanted = array();
        foreach ($terms['terms'] as $term) {
            $wanted[]  = $term['id'];
            $children  = get_term_children($term['id'], $terms['taxonomy']);
            if (!is_wp_error($children)) {
                foreach ($children as $child) {
                    $wanted[] = (int) $child;
                }
            }
        }
        $has = wp_get_post_terms($id, $terms['taxonomy'], array('fields' => 'ids'));
        if (is_wp_error($has) || array() === array_intersect($wanted, array_map('intval', $has))) {
            return 'not_in_section';
        }
    }
    if (in_array($product->get_catalog_visibility(), array('hidden', 'search'), true)) {
        return 'hidden';
    }
    if ('outofstock' === $product->get_stock_status()) {
        return 'outofstock';
    }
    return 'unknown';
}

/* ==================================================================== *
 * 3. The homepage page
 * ==================================================================== */

/** The page the Vetrina edits: the given id, else the static front page. */
function ghb_hub_get_page($page_id)
{
    $page_id = (int) $page_id;
    if ($page_id <= 0) {
        $page_id = (int) get_option('page_on_front');
    }
    if ($page_id <= 0) {
        return new WP_Error('ghb_no_front_page', 'Il sito non ha una homepage statica (Impostazioni → Lettura).', array('status' => 404));
    }
    $page = get_post($page_id);
    if (!$page instanceof WP_Post || 'page' !== $page->post_type) {
        return new WP_Error('ghb_page_not_found', 'Pagina non trovata.', array('status' => 404));
    }
    return $page;
}

/** The rail payload the Hub reads: settings, terms and — optionally — rendered products. */
function ghb_hub_rail_payload(string $key, string $path, array $block, array $rail, bool $with_products): array
{
    $atts  = ghb_hub_rail_atts($rail['atts']);
    $terms = ghb_hub_rail_terms($atts);
    $attrs = $block['attrs'];

    $payload = array(
        'key'              => $key,
        'path'             => $path,
        'attrs_hash'       => ghb_hub_attrs_hash($attrs),
        'title'            => isset($attrs['title']) ? (string) $attrs['title'] : '',
        'eyebrow'          => isset($attrs['eyebrow']) ? (string) $attrs['eyebrow'] : '',
        'background'       => isset($attrs['backgroundColor']) ? (string) $attrs['backgroundColor'] : 'white',
        'button'           => array(
            'text' => isset($attrs['buttonText']) ? (string) $attrs['buttonText'] : '',
            'url'  => isset($attrs['buttonUrl']) ? (string) $attrs['buttonUrl'] : '',
        ),
        'shortcode'        => $rail['shortcode'],
        'atts'             => $rail['atts'],
        'limit'            => max(1, (int) $atts['limit']),
        'taxonomy'         => $terms['taxonomy'],
        'terms'            => $terms['terms'],
        'pin'              => $rail['pin'],
        'exclude'          => $rail['exclude'],
        'fallback'         => $rail['fallback'],
        'fallback_default' => $rail['fallback_default'],
        'editable'         => empty($atts['ids']),
    );
    if ($with_products) {
        $payload['products'] = ghb_hub_product_cards(ghb_hub_rail_ids($atts, true));
    }
    return $payload;
}

/* ==================================================================== *
 * 4. REST routes
 * ==================================================================== */

function ghb_hub_permission()
{
    if (current_user_can('manage_woocommerce')) {
        return true;
    }
    return new WP_Error('ghb_forbidden', 'Servono i permessi manage_woocommerce.', array('status' => rest_authorization_required_code()));
}

add_action('rest_api_init', 'ghb_hub_register_routes');
function ghb_hub_register_routes()
{
    register_rest_route(GHB_HUB_NS, '/capabilities', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_capabilities',
    ));
    register_rest_route(GHB_HUB_NS, '/homepage', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_homepage',
        'args'                => array('page_id' => array('type' => 'integer', 'required' => false)),
    ));
    register_rest_route(GHB_HUB_NS, '/rail', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_rail',
        'args'                => array(
            'page_id' => array('type' => 'integer', 'required' => false),
            'path'    => array('type' => 'string', 'required' => true),
            'offset'  => array('type' => 'integer', 'required' => false, 'default' => 0, 'minimum' => 0),
            'count'   => array('type' => 'integer', 'required' => false, 'default' => 60, 'minimum' => 1, 'maximum' => 200),
        ),
    ));
    register_rest_route(GHB_HUB_NS, '/homepage/block', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_write_block',
    ));
    register_rest_route(GHB_HUB_NS, '/homepage/history', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_history',
        'args'                => array(
            'page_id' => array('type' => 'integer', 'required' => false),
            'key'     => array('type' => 'string', 'required' => true),
        ),
    ));
}

function ghb_hub_rest_capabilities()
{
    return rest_ensure_response(array(
        'plugin'            => 'golden-hive-blocks',
        'version'           => GOLDEN_HIVE_BLOCKS_VERSION,
        'api'               => GHB_HUB_API,
        'features'          => array('homepage', 'rail', 'block-write', 'history', 'rail-pin', 'rail-exclude', 'rail-fallback', 'archive-follow'),
        'fallbacks'         => ghb_hub_fallbacks(),
        'max_ids'           => GHB_HUB_MAX_IDS,
        'hide_out_of_stock' => ghb_hub_hide_out_of_stock(),
        'front_page_id'     => (int) get_option('page_on_front'),
        'site_url'          => home_url('/'),
    ));
}

function ghb_hub_rest_homepage(WP_REST_Request $request)
{
    $page = ghb_hub_get_page($request->get_param('page_id'));
    if (is_wp_error($page)) {
        return $page;
    }
    $blocks = parse_blocks($page->post_content);
    $rails  = ghb_hub_page_rails($blocks);
    $by_path = array();
    foreach ($rails as $key => $entry) {
        $by_path[$entry['path']] = $key;
    }

    $items = array();
    foreach (ghb_hub_leaf_blocks($blocks) as $leaf) {
        list($path, $block) = $leaf;
        if (isset($by_path[$path])) {
            $key     = $by_path[$path];
            $items[] = array(
                'path' => $path,
                'name' => $block['blockName'],
                'kind' => 'rail',
                'rail' => ghb_hub_rail_payload($key, $path, $block, $rails[$key]['rail'], true),
            );
            continue;
        }
        $items[] = array(
            'path'    => $path,
            'name'    => $block['blockName'],
            'kind'    => 'static',
            'summary' => ghb_hub_block_summary($block),
        );
    }

    return rest_ensure_response(array(
        'page_id'      => (int) $page->ID,
        'title'        => get_the_title($page),
        'link'         => get_permalink($page),
        'edit_link'    => admin_url('post.php?post=' . $page->ID . '&action=edit'),
        'modified_gmt' => $page->post_modified_gmt,
        'blocks'       => $items,
    ));
}

function ghb_hub_rest_rail(WP_REST_Request $request)
{
    $page = ghb_hub_get_page($request->get_param('page_id'));
    if (is_wp_error($page)) {
        return $page;
    }
    $path   = (string) $request->get_param('path');
    $blocks = parse_blocks($page->post_content);
    $key    = null;
    foreach (ghb_hub_page_rails($blocks) as $rail_key => $entry) {
        if ($entry['path'] === $path) {
            $key  = $rail_key;
            $rail = $entry['rail'];
            $block = $entry['block'];
            break;
        }
    }
    if (null === $key) {
        return new WP_Error('ghb_not_a_rail', 'Questo blocco non è una sezione prodotti.', array('status' => 404));
    }

    $atts     = ghb_hub_rail_atts($rail['atts']);
    $terms    = ghb_hub_rail_terms($atts);
    $args     = ghb_carousel_query_args($atts);
    $visible  = ghb_hub_rail_visible_ids($args, $rail['fallback'], true);
    $ordered  = ghb_hub_order_ids($visible, $rail['pin'], $rail['exclude']);
    $offset   = (int) $request->get_param('offset');
    $count    = (int) $request->get_param('count');
    $page_ids = array_slice($ordered, $offset, $count);

    $pinned_set = array_fill_keys($rail['pin'], true);
    $items      = array();
    foreach (ghb_hub_product_cards($page_ids) as $i => $card) {
        $card['position'] = $offset + $i + 1;
        $card['pinned']   = isset($pinned_set[$card['id']]);
        $items[]          = $card;
    }

    // Hidden products the Vetrina must still show: excluded members (so they
    // can be brought back) and pins the site cannot show (with the reason).
    $visible_set = array_fill_keys($visible, true);
    $hidden_ids  = array();
    foreach ($rail['exclude'] as $id) {
        if (isset($visible_set[$id])) {
            $hidden_ids[$id] = 'excluded';
        }
    }
    foreach ($rail['pin'] as $id) {
        if (!isset($visible_set[$id]) && !isset($hidden_ids[$id])) {
            $hidden_ids[$id] = ghb_hub_hidden_reason((int) $id, $atts, $terms);
        }
    }
    $hidden = array();
    foreach (ghb_hub_product_cards(array_keys($hidden_ids)) as $card) {
        $card['reason'] = $hidden_ids[$card['id']];
        $card['pinned'] = isset($pinned_set[$card['id']]);
        $hidden[]       = $card;
    }

    $payload = ghb_hub_rail_payload($key, $path, $block, $rail, false);
    return rest_ensure_response(array_merge($payload, array(
        'page_id'           => (int) $page->ID,
        'modified_gmt'      => $page->post_modified_gmt,
        'hide_out_of_stock' => ghb_hub_hide_out_of_stock($atts),
        'total'             => count($ordered),
        'offset'            => $offset,
        'items'             => $items,
        'hidden'            => $hidden,
    )));
}

/** Validate the rail values a write sends. */
function ghb_hub_validate_rail_input($input)
{
    if (!is_array($input)) {
        return new WP_Error('ghb_bad_input', 'Dati della sezione mancanti.', array('status' => 400));
    }
    foreach (array('pin', 'exclude') as $key) {
        if (isset($input[$key]) && !is_array($input[$key])) {
            return new WP_Error('ghb_bad_input', "{$key} deve essere una lista di id.", array('status' => 400));
        }
        if (isset($input[$key]) && count($input[$key]) > GHB_HUB_MAX_IDS) {
            return new WP_Error('ghb_too_many', "{$key}: al massimo " . GHB_HUB_MAX_IDS . ' prodotti.', array('status' => 400));
        }
    }
    $fallback = isset($input['fallback']) ? (string) $input['fallback'] : '';
    if ('' !== $fallback && !in_array($fallback, ghb_hub_fallbacks(), true)) {
        return new WP_Error('ghb_bad_input', 'Ordinamento non riconosciuto.', array('status' => 400));
    }
    return array(
        'pin'      => ghb_hub_parse_id_list($input['pin'] ?? array()),
        'exclude'  => ghb_hub_parse_id_list($input['exclude'] ?? array()),
        'fallback' => $fallback,
    );
}

/**
 * Write one rail's pin / exclude / fallback into the page.
 *
 * Refuses rather than guesses at every step: the page must be the one the Hub
 * read (modified time), the block must be the one it read (path, name and an
 * attribute fingerprint), and its delimiter must be found exactly once. Only
 * the rail shortcode changes; the write is a normal wp_update_post, so the
 * page gets a revision.
 */
function ghb_hub_rest_write_block(WP_REST_Request $request)
{
    $page = ghb_hub_get_page($request->get_param('page_id'));
    if (is_wp_error($page)) {
        return $page;
    }
    if ((string) $request->get_param('expected_modified_gmt') !== $page->post_modified_gmt) {
        return new WP_Error('ghb_stale_page', 'La homepage è stata modificata nel frattempo: ricarica.', array('status' => 409, 'modified_gmt' => $page->post_modified_gmt));
    }

    $path   = (string) $request->get_param('path');
    $blocks = parse_blocks($page->post_content);
    $block  = ghb_hub_block_at_path($blocks, $path);
    if (!$block || (string) $request->get_param('block_name') !== $block['blockName']) {
        return new WP_Error('ghb_block_moved', 'La sezione non è più in quella posizione: ricarica.', array('status' => 409));
    }
    if ((string) $request->get_param('expected_attrs_hash') !== ghb_hub_attrs_hash($block['attrs'])) {
        return new WP_Error('ghb_block_changed', 'La sezione è stata modificata nel frattempo: ricarica.', array('status' => 409));
    }
    $rail = ghb_hub_rail_from_block($block);
    if (!$rail) {
        return new WP_Error('ghb_not_a_rail', 'Questo blocco non è una sezione prodotti.', array('status' => 422));
    }
    if (!empty($rail['atts']['ids'])) {
        return new WP_Error('ghb_fixed_list', 'Questa sezione mostra una lista fissa di prodotti.', array('status' => 422));
    }

    $values = ghb_hub_validate_rail_input($request->get_param('rail'));
    if (is_wp_error($values)) {
        return $values;
    }
    $new_shortcode = ghb_hub_set_rail_atts($rail['shortcode'], $values);
    if (null === $new_shortcode) {
        return new WP_Error('ghb_unwritable', 'Lo shortcode della sezione non si può riscrivere in sicurezza.', array('status' => 422));
    }

    $new_attrs              = $block['attrs'];
    $new_attrs['shortcode'] = $new_shortcode;
    $dry_run                = (bool) $request->get_param('dry_run');
    $response               = array(
        'dry_run' => $dry_run,
        'changed' => $new_shortcode !== $rail['shortcode'],
        'path'    => $path,
        'before'  => $rail['shortcode'],
        'after'   => $new_shortcode,
    );

    $replaced = ghb_hub_replace_block_attrs($page->post_content, $block['blockName'], $block['attrs'], $new_attrs, 'serialize_block_attributes');
    if (!$replaced['ok']) {
        return new WP_Error(
            'ghb_block_not_unique',
            'ambiguous' === $replaced['error']
                ? 'La stessa sezione compare più volte nella pagina: modificala dall\'editor di WordPress.'
                : 'La sezione non si trova nel contenuto salvato: ricarica.',
            array('status' => 422)
        );
    }

    if ($dry_run || !$response['changed']) {
        $response['modified_gmt'] = $page->post_modified_gmt;
        $response['attrs_hash']   = ghb_hub_attrs_hash($block['attrs']);
        return rest_ensure_response($response);
    }

    // The content is written back exactly as read except for one attribute we
    // validated, so the page's own markup (the <style> block included) must
    // not be re-filtered by kses for a user without unfiltered_html.
    $kses = false !== has_filter('content_save_pre', 'wp_filter_post_kses');
    if ($kses) {
        kses_remove_filters();
    }
    // wp_update_post() unslashes its input: slash it, or every \u0022 escape in
    // the block attributes would lose its backslash and break the page.
    $result = wp_update_post(array('ID' => $page->ID, 'post_content' => wp_slash($replaced['content'])), true);
    if ($kses) {
        kses_init_filters();
    }
    if (is_wp_error($result)) {
        return new WP_Error('ghb_write_failed', $result->get_error_message(), array('status' => 500));
    }

    ghb_hub_bump_cache_gen();
    ghb_hub_purge_page_cache((int) $page->ID);

    $page = get_post($page->ID);
    $response['modified_gmt'] = $page->post_modified_gmt;
    $response['attrs_hash']   = ghb_hub_attrs_hash($new_attrs);
    $response['rendered']     = ghb_hub_rail_ids(ghb_hub_rail_atts(ghb_hub_parse_shortcode($new_shortcode)['atts']), true);
    return rest_ensure_response($response);
}

/**
 * Earlier states of one rail, newest first, from the page's revisions. The
 * rail is found by key (what it shows), not by path, so a block added above
 * it later does not hide its history.
 */
function ghb_hub_rest_history(WP_REST_Request $request)
{
    $page = ghb_hub_get_page($request->get_param('page_id'));
    if (is_wp_error($page)) {
        return $page;
    }
    $key       = (string) $request->get_param('key');
    $revisions = wp_get_post_revisions($page->ID, array('posts_per_page' => 30));

    $states = array();
    $last   = null;
    foreach ($revisions as $revision) {
        $rails = ghb_hub_page_rails(parse_blocks($revision->post_content));
        if (!isset($rails[$key])) {
            continue;
        }
        $rail  = $rails[$key]['rail'];
        $state = array('pin' => $rail['pin'], 'exclude' => $rail['exclude'], 'fallback' => $rail['fallback']);
        if ($state === $last) {
            continue; // an edit elsewhere on the page: nothing changed for this rail
        }
        $last     = $state;
        $author   = get_userdata((int) $revision->post_author);
        $states[] = array_merge($state, array(
            'revision_id' => (int) $revision->ID,
            'date_gmt'    => $revision->post_modified_gmt,
            'author'      => $author ? $author->display_name : '',
        ));
    }
    return rest_ensure_response(array('key' => $key, 'states' => $states));
}

/* ==================================================================== *
 * 5. Cache purge
 * ==================================================================== */

/**
 * After a write, the rails change on the homepage and on the category pages
 * that follow them: purge whole-site caches where a known plugin runs, and
 * let the site hook its own (a CDN) on ghb_hub_page_published.
 */
function ghb_hub_purge_page_cache(int $page_id): void
{
    clean_post_cache($page_id);
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
    do_action('ghb_hub_page_published', $page_id);
}

/* ==================================================================== *
 * 6. Category pages follow their homepage rail
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
    $apply = function ($clauses, $target) use ($query, $pins, &$apply) {
        if ($target !== $query) {
            return $clauses;
        }
        remove_filter('posts_clauses', $apply, 20);
        global $wpdb;
        $field              = "FIELD({$wpdb->posts}.ID, {$pins})";
        $rest               = trim((string) $clauses['orderby']);
        $clauses['orderby'] = "{$field} = 0, {$field}" . ('' !== $rest ? ', ' . $rest : '');
        return $clauses;
    };
    add_filter('posts_clauses', $apply, 20, 2);
}
