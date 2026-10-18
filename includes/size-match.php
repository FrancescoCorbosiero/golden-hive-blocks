<?php
/**
 * Golden Hive — product cards that follow the size filter.
 *
 * With a size picked in the shop filters (filter_taglia=42, WooCommerce's own
 * layered-nav param, which [bfl_filters] writes), every card in the grid has
 * that size in stock: the filters match at variation level. The card now says
 * so and acts on it:
 *
 *   • a line under the price, "Taglia 42 · 180 €": the price of that size,
 *     where the card itself shows the product's whole range;
 *   • Quick View and Quick Add open with that size already picked
 *     (data-size-* on their buttons, read by js/quick-view.js and
 *     js/quick-add.js), so adding it is one tap fewer.
 *
 * With several sizes filtered, the line lists the ones the product has and
 * nothing is pre-picked. The filters' AJAX refresh passes its query string in
 * through ghb_size_match_slugs() (includes/filters.php), so refreshed cards
 * carry the same line as a plain page load.
 *
 * WooCommerce loop hooks and the plugin's own buttons only: theme-agnostic.
 * The decisions are in size-match-core.php (tested without WordPress).
 *
 * @package Golden_Hive_Blocks
 * @since   5.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Most sizes read from one filter URL. */
const GHB_SIZE_MATCH_MAX = 20;

/**
 * Slugs of the active size filter, read once from the page's query string.
 * The filters' AJAX refresh renders cards outside the page request, so it
 * passes its own query string: that replaces what the page URL said.
 *
 * @return string[]
 */
function ghb_size_match_slugs(?string $query_string = null): array
{
    static $slugs = null;

    if (null === $query_string && null !== $slugs) {
        return $slugs;
    }

    $attr  = (string) apply_filters('ghb_atc_size_attribute', 'pa_taglia');
    $param = 'filter_' . (0 === strpos($attr, 'pa_') ? substr($attr, 3) : $attr);

    if (null !== $query_string) {
        $params = array();
        wp_parse_str($query_string, $params);
        $raw = $params[$param] ?? '';
    } else {
        $raw = wp_unslash($_GET[$param] ?? '');
    }

    // A filter URL is user input: a stray filter_taglia[]= array is no filter.
    $slugs = is_string($raw)
        ? array_slice(array_values(array_unique(array_filter(array_map('sanitize_title', explode(',', $raw))))), 0, GHB_SIZE_MATCH_MAX)
        : array();

    return $slugs;
}

/**
 * Normalized keys of the filtered sizes. A slug is matched through its term's
 * name, so "36-2-3" and a duplicate term's "36-2" still reach the size they
 * name, the same way the size pickers group spellings.
 *
 * @return string[]
 */
function ghb_size_match_keys(): array
{
    static $memo = array();

    $slugs = ghb_size_match_slugs();
    $id    = implode(',', $slugs);
    if (isset($memo[$id])) {
        return $memo[$id];
    }

    $attr = (string) apply_filters('ghb_atc_size_attribute', 'pa_taglia');
    $keys = array();
    foreach ($slugs as $slug) {
        $term   = get_term_by('slug', $slug, $attr);
        $keys[] = ghb_normalize_size_key($term instanceof WP_Term ? $term->name : $slug);
    }

    return $memo[$id] = array_values(array_unique(array_filter($keys)));
}

/**
 * The filtered sizes this product has in stock (size rows, see
 * ghb_atc_size_rows()). Empty without a size filter or for simple products.
 */
function ghb_size_match_for(WC_Product $product): array
{
    static $memo = array();

    $keys = ghb_size_match_keys();
    if (!$keys || !$product->is_type('variable') || !function_exists('ghb_atc_size_rows')) {
        return array();
    }

    $id = $product->get_id() . '|' . implode(',', $keys);
    if (isset($memo[$id])) {
        return $memo[$id];
    }

    // The rows load every variation: fetch their posts and meta in two
    // queries instead of two per variation.
    $children = $product->get_children();
    if ($children) {
        _prime_post_caches($children, false, true);
    }

    return $memo[$id] = ghb_size_match_pick(ghb_atc_size_rows($product), $keys);
}

/**
 * data-size-* for the Quick View / Quick Add buttons: set only when exactly
 * one filtered size matched, since that is the one the shopper wants.
 */
function ghb_size_match_button_attrs(WC_Product $product): string
{
    if (!ghb_size_match_slugs()) {
        return '';
    }
    $matches = ghb_size_match_for($product);
    if (1 !== count($matches)) {
        return '';
    }

    return sprintf(
        ' data-size-variation="%d" data-size-attr="%s" data-size-value="%s"',
        (int) $matches[0]['variation_id'],
        esc_attr((string) apply_filters('ghb_atc_size_attribute', 'pa_taglia')),
        esc_attr((string) $matches[0]['value'])
    );
}

/**
 * The line under the card's price. Same gate as the rest of the product UI:
 * its styles ship with add-to-cart.css.
 */
add_action('woocommerce_after_shop_loop_item_title', 'ghb_size_match_card_line', 15);
function ghb_size_match_card_line()
{
    if (!ghb_size_match_slugs() || !function_exists('ghb_product_ui_should_load') || !ghb_product_ui_should_load()) {
        return;
    }

    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }

    $line = ghb_size_match_line(ghb_size_match_for($product));
    if ('' !== $line) {
        echo '<div class="ghb-size-match">' . esc_html($line) . '</div>';
    }
}
