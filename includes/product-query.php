<?php
/**
 * Golden Hive — product queries and the older rail shortcodes.
 *
 * ghb_get_carousel_products() is the one product query every rail uses
 * (includes/product-rail.php, the Store Hub's rails, "Altre colorway"): a
 * type (recent, best_selling, sale, featured…), category, brand, tag or an
 * explicit ids list, always published and catalogue-visible, sold-out
 * products hidden when the store hides them. A rail with pin / exclude /
 * fallback goes through the Store Hub's ordering (includes/hub-rails.php).
 *
 * The shortcodes below are older names kept so existing pages keep working:
 * [carousel_section], [product_carousel], [bestsellers], [new_arrivals],
 * [on_sale] and [featured_products] all render [gh_product_rail].
 *
 * @package Golden_Hive_Blocks
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------- *
 * The query
 * -------------------------------------------------------------------- */

/**
 * The brand taxonomies a store may use, of which it has whichever exist:
 * WooCommerce's own (product_brand), Perfect Brands (pwb-brand), a brand
 * attribute (pa_brand). The rails, the Store Hub, size alerts and the live
 * search all start from this list; add_filter('ghb_brand_taxonomies', …)
 * changes it for all of them.
 */
function ghb_brand_taxonomies(): array
{
    return array_values(array_unique(array_map('strval', (array) apply_filters('ghb_brand_taxonomies', array('product_brand', 'pwb-brand', 'pa_brand')))));
}

function ghb_get_carousel_products($atts) {
    // A rail edited from the Store Hub's Vetrina carries pin / exclude /
    // fallback (see hub-rails.php); an explicit ids list keeps its own order.
    if (empty($atts['ids']) && function_exists('ghb_hub_rail_has_ordering') && ghb_hub_rail_has_ordering($atts)) {
        return ghb_hub_rail_query($atts);
    }
    return new WP_Query(ghb_carousel_query_args($atts));
}

/**
 * The WP_Query arguments of a rail: which products it may show (published,
 * visible, in the term) and today's order. Split out of
 * ghb_get_carousel_products() so the Vetrina ordering filters exactly the same
 * product set.
 */
function ghb_carousel_query_args($atts) {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['limit']),
        'post_status'    => 'publish',
    );

    $tax_query = array();

    // Mirror WooCommerce's own catalog queries: when the store hides sold-out
    // products (Settings → Products → Inventory), the rails hide them too.
    // Without this the homepage rails surfaced products the shop itself hides:
    // no price, the theme's "Out of stock" label, a dead add-to-cart button.
    $visibility_not_in = array('exclude-from-catalog');
    if (apply_filters('ghb_carousel_hide_out_of_stock', 'yes' === get_option('woocommerce_hide_out_of_stock_items'), $atts)) {
        $visibility_not_in[] = 'outofstock';
    }

    $tax_query[] = array(
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => $visibility_not_in,
        'operator' => 'NOT IN',
    );

    switch ($atts['type']) {
        case 'best_selling':
            $args['meta_key'] = 'total_sales';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'featured':
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'featured',
            );
            $args['orderby'] = 'menu_order title';
            $args['order']   = 'ASC';
            break;
        case 'sale':
            $args['meta_query'][] = array(
                'key'     => '_sale_price',
                'value'   => '',
                'compare' => '!=',
            );
            break;
        case 'top_rated':
            $args['meta_key'] = '_wc_average_rating';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'recent':
        default:
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
            break;
    }

    // When filtering by category, use menu_order to preserve the manual
    // sort order from WooCommerce admin (matching the category page).
    // Only skip if an explicit metric-based sort (best_selling, top_rated) is active.
    if ((!empty($atts['category']) || !empty($atts['brand'])) && !in_array($atts['type'], array('best_selling', 'top_rated'), true)) {
        $args['orderby'] = 'menu_order title';
        $args['order']   = 'ASC';
    }

    if (!empty($atts['category'])) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => array_map('trim', explode(',', $atts['category'])),
        );
    }

    if (!empty($atts['tag'])) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => array_map('trim', explode(',', $atts['tag'])),
        );
    }

    if (!empty($atts['brand'])) {
        // Resolve the brand slug(s) to terms in WHICHEVER brand taxonomy holds
        // them (a site can have several), instead of only querying the first
        // registered one. Match across all of them with OR + include_children,
        // so nested/child brand terms (e.g. nike-travis-scott) work too.
        $brand_slugs = array_filter(array_map('trim', explode(',', $atts['brand'])));
        $brand_taxonomies = ghb_brand_taxonomies();

        $brand_clauses = array();
        foreach ($brand_taxonomies as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $ids = array();
            foreach ($brand_slugs as $slug) {
                $term = get_term_by('slug', $slug, $tax);
                if ($term && !is_wp_error($term)) {
                    $ids[] = (int) $term->term_id;
                }
            }
            if (!empty($ids)) {
                $brand_clauses[] = array(
                    'taxonomy'         => $tax,
                    'field'            => 'term_id',
                    'terms'            => array_unique($ids),
                    'include_children' => true,
                );
            }
        }

        if (count($brand_clauses) === 1) {
            $tax_query[] = $brand_clauses[0];
        } elseif (count($brand_clauses) > 1) {
            $tax_query[] = array_merge(array('relation' => 'OR'), $brand_clauses);
        } else {
            // Slug didn't resolve in any brand taxonomy — fall back to the
            // original slug query against the first existing one.
            foreach ($brand_taxonomies as $tax) {
                if (taxonomy_exists($tax)) {
                    $tax_query[] = array(
                        'taxonomy' => $tax,
                        'field'    => 'slug',
                        'terms'    => $brand_slugs,
                    );
                    break;
                }
            }
        }
    }

    if (!empty($tax_query)) {
        $args['tax_query'] = array_merge(array('relation' => 'AND'), $tax_query);
    }

    if (!empty($atts['ids'])) {
        $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
        $args['orderby']  = 'post__in';
    }

    return $args;
}

/* -------------------------------------------------------------------- *
 * Older shortcode names, all rendered by the product rail
 * -------------------------------------------------------------------- */

add_shortcode('carousel_section', 'ghb_carousel_section_shortcode');
add_shortcode('product_carousel', 'ghb_carousel_section_shortcode');

function ghb_carousel_section_shortcode($atts) {
    // Map the attributes the rail understands; the old carousel's styling
    // attributes (style, effect, nav_style…) are dropped.
    if (!class_exists('WooCommerce') || !function_exists('ghb_product_rail_shortcode')) {
        return '';
    }
    $src = is_array($atts) ? $atts : array();
    $rail_atts = array();
    foreach (array('title', 'type', 'limit', 'category', 'tag', 'brand', 'ids', 'pin', 'exclude', 'fallback', 'columns', 'columns_tablet', 'columns_mobile', 'ratio', 'fit') as $k) {
        if (isset($src[$k]) && '' !== $src[$k]) {
            $rail_atts[$k] = $src[$k];
        }
    }
    return ghb_product_rail_shortcode($rail_atts);
}

/** [bestsellers], [new_arrivals], [on_sale], [featured_products]: a rail of one type. */
function ghb_legacy_rail_shortcode(string $type, string $title, $atts): string
{
    $atts = shortcode_atts(array('limit' => 8, 'title' => $title), $atts);
    return ghb_carousel_section_shortcode(array('type' => $type, 'title' => $atts['title'], 'limit' => $atts['limit']));
}
add_shortcode('bestsellers', function ($atts) {
    return ghb_legacy_rail_shortcode('best_selling', 'Best Sellers', $atts);
});
add_shortcode('new_arrivals', function ($atts) {
    return ghb_legacy_rail_shortcode('recent', 'Nuovi Arrivi', $atts);
});
add_shortcode('on_sale', function ($atts) {
    return ghb_legacy_rail_shortcode('sale', 'In Saldo', $atts);
});
add_shortcode('featured_products', function ($atts) {
    return ghb_legacy_rail_shortcode('featured', 'In Evidenza', $atts);
});
