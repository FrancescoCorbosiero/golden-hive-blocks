<?php
/**
 * Golden Hive — sold-out products after the available ones.
 *
 * WooCommerce can hide sold-out products (Impostazioni → Prodotti → Inventario
 * → "Nascondi gli articoli esauriti") but has no setting to keep them listed
 * after the ones you can buy. This does that on the shop, category, brand,
 * tag and attribute pages, and on the filters' AJAX refresh of them, on top
 * of whatever order is in force: the default sort, the visitor's pick
 * (price, popularity…) or the pins of the category's homepage rail. The
 * ordering happens in SQL, so the sold-out products fill the last pages
 * rather than the bottom of each page, and pagination stays consistent.
 *
 * "Sold out" is WooCommerce's own flag: the product_visibility "outofstock"
 * term, which it keeps in step with the stock status — exactly the products
 * "Nascondi gli articoli esauriti" would hide, a variable product once every
 * size is gone. On backorder counts as available. Search results keep their
 * relevance order (Relevanssi decides it), and the homepage rails keep theirs
 * (the Store Hub mirrors that order: hub-rails-core.php).
 *
 * Switch: Customizer → WooCommerce → Catalogo prodotti → "Prodotti esauriti
 * in fondo" (on by default). In code: add_filter('ghb_sold_out_last', '__return_false').
 *
 * @package Golden_Hive_Blocks
 * @since   5.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function ghb_sold_out_last_enabled(): bool
{
    $on = 'no' !== get_option('ghb_sold_out_last', 'yes')
        // Hidden products need no ordering.
        && 'yes' !== get_option('woocommerce_hide_out_of_stock_items');
    return (bool) apply_filters('ghb_sold_out_last', $on);
}

/** Flag WooCommerce's catalogue query (the shop and its archives, not search). */
add_action('woocommerce_product_query', 'ghb_sold_out_last_flag');
function ghb_sold_out_last_flag($query)
{
    if ($query instanceof WP_Query && !$query->is_search() && ghb_sold_out_last_enabled()) {
        $query->set('ghb_sold_out_last', true);
    }
}

/**
 * Sold-out last, ahead of every other sort key. Priority 50: after
 * WooCommerce's price / popularity / rating clauses (10), which replace the
 * ORDER BY, and the homepage-rail pins (20), which prepend to it. One
 * primary-key lookup per product; no join, so no duplicate rows to group.
 */
add_filter('posts_clauses', 'ghb_sold_out_last_clauses', 50, 2);
function ghb_sold_out_last_clauses($clauses, $query)
{
    if (!$query instanceof WP_Query || !$query->get('ghb_sold_out_last') || !function_exists('wc_get_product_visibility_term_ids')) {
        return $clauses;
    }
    $term_ids = wc_get_product_visibility_term_ids();
    $sold_out = (int) ($term_ids['outofstock'] ?? 0);
    if ($sold_out <= 0) {
        return $clauses;
    }
    global $wpdb;
    $key  = "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} ghb_oos"
        . " WHERE ghb_oos.object_id = {$wpdb->posts}.ID AND ghb_oos.term_taxonomy_id = {$sold_out}) ASC";
    $rest = trim((string) $clauses['orderby']);
    $clauses['orderby'] = $key . ('' !== $rest ? ', ' . $rest : '');
    return $clauses;
}

/* -------------------------------------------------------------------- *
 * Customizer
 * -------------------------------------------------------------------- */

/**
 * Next to WooCommerce's own "Ordinamento predefinito dei prodotti" (its
 * section is registered at priority 10); the plugin's section if that one
 * isn't there.
 */
add_action('customize_register', 'ghb_sold_out_last_customize_register', 20);
function ghb_sold_out_last_customize_register(WP_Customize_Manager $wp_customize)
{
    $section = $wp_customize->get_section('woocommerce_product_catalog') ? 'woocommerce_product_catalog' : 'ghb_design';
    $to_bool = static function ($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return is_scalar($value) && in_array(strtolower((string) $value), array('yes', 'true', '1'), true);
    };

    $wp_customize->add_setting('ghb_sold_out_last', array(
        'type'                 => 'option',
        'capability'           => 'manage_woocommerce',
        'default'              => 'yes',
        'transport'            => 'refresh',
        // Stored as yes / no like WooCommerce's own options, a boolean for the checkbox.
        'sanitize_callback'    => static function ($value) use ($to_bool) {
            return $to_bool($value) ? 'yes' : 'no';
        },
        'sanitize_js_callback' => $to_bool,
    ));
    $wp_customize->add_control('ghb_sold_out_last', array(
        'section'     => $section,
        'type'        => 'checkbox',
        'label'       => 'Prodotti esauriti in fondo',
        'description' => 'Nel negozio e nelle pagine di categoria, marca e tag i prodotti esauriti restano visibili ma compaiono dopo quelli disponibili, con qualsiasi ordinamento. La ricerca resta ordinata per pertinenza.',
    ));
}
