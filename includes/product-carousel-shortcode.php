<?php
/**
 * Product Carousel Shortcodes + shared WooCommerce helpers.
 *
 * The Swiper-based carousel was retired for performance: [carousel_section],
 * [product_carousel] and the convenience shortcodes (bestsellers / new_arrivals
 * / on_sale / featured_products) now delegate to the lightweight CSS scroll-snap
 * rail (includes/product-rail.php). What remains here is the shared product
 * query builder (ghb_get_carousel_products) and the Quick View / add-to-cart
 * AJAX handlers, which the rail and Quick View depend on.
 *
 * @package Golden_Hive_Blocks
 * @version 5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * ═══════════════════════════════════════════════════════════════
 * 3. MAIN SHORTCODE
 * ═══════════════════════════════════════════════════════════════
 */
add_shortcode('carousel_section', 'ghb_carousel_section_shortcode');
add_shortcode('product_carousel', 'ghb_carousel_section_shortcode'); // Alias

function ghb_carousel_section_shortcode($atts) {
    // Swiper retired for performance — delegate to the lightweight scroll-snap
    // rail, mapping the data-relevant attributes across. The query builder and
    // AJAX handlers below are still used by the rail / Quick View, so they stay.
    if (!class_exists('WooCommerce') || !function_exists('ghb_product_rail_shortcode')) {
        return '';
    }
    $src = is_array($atts) ? $atts : array();
    $rail_atts = array();
    foreach (array('title', 'type', 'limit', 'category', 'tag', 'brand', 'ids', 'columns') as $k) {
        if (isset($src[$k]) && '' !== $src[$k]) {
            $rail_atts[$k] = $src[$k];
        }
    }
    return ghb_product_rail_shortcode($rail_atts);
}
/**
 * ═══════════════════════════════════════════════════════════════
 * 5. PRODUCT QUERY BUILDER
 * ═══════════════════════════════════════════════════════════════
 */
function ghb_get_carousel_products($atts) {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['limit']),
        'post_status'    => 'publish',
    );

    $tax_query = array();

    $tax_query[] = array(
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => 'exclude-from-catalog',
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
        $brand_taxonomies = array('product_brand', 'pwb-brand', 'pa_brand');

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

    return new WP_Query($args);
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 7. CONVENIENCE SHORTCODES
 * ═══════════════════════════════════════════════════════════════
 */

// Best Sellers
add_shortcode('bestsellers', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'Best Sellers', 'style' => 'default'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"best_selling\" limit=\"{$atts['limit']}\" style=\"{$atts['style']}\" link=\"/shop?orderby=popularity\"]");
});

// New Arrivals
add_shortcode('new_arrivals', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'Nuovi Arrivi', 'style' => 'default'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"recent\" limit=\"{$atts['limit']}\" style=\"{$atts['style']}\" link=\"/shop?orderby=date\"]");
});

// On Sale
add_shortcode('on_sale', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'In Saldo', 'style' => 'default'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"sale\" limit=\"{$atts['limit']}\" style=\"{$atts['style']}\" link=\"/shop?on_sale=1\"]");
});

// Featured
add_shortcode('featured_products', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'In Evidenza', 'effect' => 'coverflow'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"featured\" limit=\"{$atts['limit']}\" effect=\"{$atts['effect']}\" nav_style=\"sides\" link=\"/shop\"]");
});


/**
 * ═══════════════════════════════════════════════════════════════
 * 9. QUICK ADD TO CART - AJAX HANDLERS
 * ═══════════════════════════════════════════════════════════════
 */

// AJAX: get variations for variable products
add_action('wp_ajax_ghb_get_variations', 'ghb_get_variations_handler');
add_action('wp_ajax_nopriv_ghb_get_variations', 'ghb_get_variations_handler');

function ghb_get_variations_handler() {
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error('Prodotto non trovato');
    }

    $attributes = [];
    foreach ($product->get_variation_attributes() as $attr_name => $options) {
        $label = wc_attribute_label($attr_name, $product);
        $attributes[] = [
            'name'    => $attr_name,
            'label'   => $label,
            'options' => array_values($options),
        ];
    }

    $variations = [];
    foreach ($product->get_available_variations() as $v) {
        $variations[] = [
            'variation_id' => $v['variation_id'],
            'attributes'   => $v['attributes'],
            'price_html'   => html_entity_decode(strip_tags($v['price_html'])),
            'is_in_stock'  => $v['is_in_stock'],
            'image'        => $v['image']['thumb_src'] ?? '',
        ];
    }

    wp_send_json_success([
        'title'      => $product->get_name(),
        'price'      => html_entity_decode(strip_tags($product->get_price_html())),
        'image'      => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
        'attributes' => $attributes,
        'variations' => $variations,
    ]);
}

// AJAX: add to cart (variable + simple)
add_action('wp_ajax_ghb_add_to_cart', 'ghb_add_to_cart_handler');
add_action('wp_ajax_nopriv_ghb_add_to_cart', 'ghb_add_to_cart_handler');

function ghb_add_to_cart_handler() {
    $product_id   = intval($_POST['product_id'] ?? 0);
    $variation_id = intval($_POST['variation_id'] ?? 0);
    $quantity     = max(1, intval($_POST['quantity'] ?? 1));

    if (!$product_id) {
        wp_send_json_error('Dati mancanti');
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Prodotto non trovato');
    }

    if ($product->is_type('variable')) {
        if (!$variation_id) {
            wp_send_json_error('Seleziona una variante');
        }
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            wp_send_json_error('Variante non trovata');
        }
        $attributes = $variation->get_variation_attributes();
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $attributes);
    } else {
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
    }

    if ($cart_item_key) {
        wp_send_json_success([
            'message'    => 'Prodotto aggiunto al carrello!',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total(),
            'cart_url'   => wc_get_cart_url(),
        ]);
    } else {
        wp_send_json_error('Impossibile aggiungere al carrello');
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 9b. AJAX: Quick View product data
 * ═══════════════════════════════════════════════════════════════
 */
add_action('wp_ajax_ghb_quick_view', 'ghb_quick_view_handler');
add_action('wp_ajax_nopriv_ghb_quick_view', 'ghb_quick_view_handler');

function ghb_quick_view_handler() {
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error('Prodotto non trovato');
    }

    // Images
    $images = [];
    $main_img = wp_get_attachment_image_url($product->get_image_id(), 'medium_large');
    if ($main_img) $images[] = $main_img;

    $gallery_ids = $product->get_gallery_image_ids();
    foreach (array_slice($gallery_ids, 0, 4) as $gid) {
        $url = wp_get_attachment_image_url($gid, 'medium_large');
        if ($url) $images[] = $url;
    }

    // Attributes
    $attributes = [];
    foreach ($product->get_attributes() as $attr) {
        $attributes[] = [
            'label' => wc_attribute_label($attr->get_name()),
            'value' => $product->get_attribute($attr->get_name()),
        ];
    }

    // Categories
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

    wp_send_json_success([
        'title'       => $product->get_name(),
        'url'         => get_permalink($product_id),
        'price'       => html_entity_decode(strip_tags($product->get_price_html())),
        'short_desc'  => wpautop($product->get_short_description()),
        'images'      => $images,
        'in_stock'    => $product->is_in_stock(),
        'stock_text'  => $product->is_in_stock() ? 'Disponibile' : 'Esaurito',
        'attributes'  => $attributes,
        'categories'  => implode(', ', is_array($categories) ? $categories : []),
        'sku'         => $product->get_sku(),
        // Cart-control data so the Quick View modal needs only ONE request.
        'type'        => $product->get_type(),
        'purchasable' => $product->is_purchasable() && $product->is_in_stock(),
        'sizes'       => ($product->is_type('variable') && function_exists('ghb_atc_size_rows'))
            ? ghb_atc_size_rows($product)
            : array(),
    ]);
}

