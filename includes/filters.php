<?php
/**
 * Golden Hive — AJAX product filters (the "Advanced Filters" panel).
 *
 * Migrated from the "BLACKOUT FASHION LAB — Modern AJAX Filter Drawer (v3)"
 * Code Snippet, with its scope bug fixed: the snippet only recognised product
 * CATEGORY archives, so on every other collection — brand pages such as
 * /marchio/nike/nike-travis-scott/, tags, attribute archives — it listed the
 * whole catalogue's categories / models / sizes, and its AJAX refresh and
 * pagination queried the whole catalogue too. Everything now follows the
 * collection being viewed: the queried term on ANY product taxonomy archive.
 *
 *   [bfl_filters]                → "Filtri" button + its own drawer
 *   [bfl_filters mode="inline"]  → the panel inline (sidebar / theme drawer)
 *   [gh_filters …]               → same, plugin-prefixed alias
 *
 * Facets: Prezzo (slider + € inputs), Categoria (pills), Stato → Disponibile,
 * plus product attributes (config below). Options that can't narrow the
 * collection — carried by every product in it, like the category you're in —
 * are left out; sizes are exempt ("is my size here?" is the question).
 *
 * Runs on WooCommerce's own layered-nav params (filter_{attr}, query_type_*,
 * min_price / max_price) plus filter_cat / instock handled below, so every
 * filtered URL is also a plain page load. On search results the panel reloads
 * the page instead of refreshing over AJAX, so the search engine (Relevanssi)
 * keeps deciding what matches.
 *
 * Kept from the snippet: the .bfl-* markup and classes (custom CSS keeps
 * working) and the URL params. Renamed: the PHP class and the AJAX action
 * (ghb_filter), so the snippet's copy can never collide with this one. While
 * the snippet is still active this module stands down (see ghb_filters_boot)
 * — deactivate it in Code Snippets to switch over.
 *
 * @package Golden_Hive_Blocks
 * @since   5.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'ghb_filters_boot');
function ghb_filters_boot()
{
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Code Snippets runs active snippets on plugins_loaded, before init. While
    // the original snippet is on it keeps [bfl_filters] and this copy stays
    // off: two copies would register the same shortcode and query hook.
    if (class_exists('BFL_Ajax_Filters')) {
        add_action('admin_notices', 'ghb_filters_snippet_notice');
        return;
    }

    new GHB_Filters();
}

function ghb_filters_snippet_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="notice notice-warning"><p>'
        . esc_html__('Golden Hive Blocks: i filtri prodotto ora sono inclusi nel plugin (limitati alla collezione che si sta guardando). Disattiva lo snippet "Advanced Filters" in Code Snippets per passare alla nuova versione.', 'golden-hive-blocks')
        . '</p></div>';
}

class GHB_Filters
{
    /** @var array|null Memoized config (read on every facet/option). */
    private $config = null;

    public function __construct()
    {
        add_shortcode('bfl_filters', array($this, 'shortcode'));
        add_shortcode('gh_filters', array($this, 'shortcode'));
        add_action('wp_ajax_ghb_filter', array($this, 'ajax_filter'));
        add_action('wp_ajax_nopriv_ghb_filter', array($this, 'ajax_filter'));
        add_action('woocommerce_product_query', array($this, 'apply_extra_query_vars'), 20);
        add_action('wp_enqueue_scripts', array($this, 'assets'));
    }

    /* ============================= CONFIG ============================= */
    private function config()
    {
        if (null !== $this->config) {
            return $this->config;
        }

        $this->config = apply_filters('ghb_filters_config', array(

            /**
             * Facets in display order. Types: price | category | stock | attribute.
             * An attribute facet's 'taxonomy' may be a single pa_* taxonomy OR an
             * array of them (rendered merged under one heading). Each taxonomy's
             * URL param is filter_{slug-without-pa} — WooCommerce-core compatible.
             */
            'facets' => array(
                'price'    => array('type' => 'price', 'label' => 'Prezzo', 'chip' => 'Prezzo'),
                'cat'      => array('type' => 'category', 'label' => 'Categoria', 'chip' => 'Categoria'),
                'stock'    => array('type' => 'stock', 'label' => 'Stato', 'chip' => '', 'text' => 'Disponibile'),
                'modello'  => array('type' => 'attribute', 'taxonomy' => 'pa_modello', 'label' => 'Filtra per Modello', 'chip' => 'Modello', 'collapsible' => true),
                'taglia'   => array('type' => 'attribute', 'taxonomy' => 'pa_taglia', 'label' => 'Filtra per Taglia', 'chip' => 'Taglia', 'collapsible' => true, 'variation' => true),
                'anno'     => array('type' => 'attribute', 'taxonomy' => 'pa_anno', 'label' => 'Filtra per Anno', 'chip' => 'Anno', 'collapsible' => true),
                'marca'    => array('type' => 'attribute', 'taxonomy' => 'pa_marca', 'label' => 'Filtra per Marca', 'chip' => 'Marca', 'collapsible' => true),
                'colorway' => array('type' => 'attribute', 'taxonomy' => 'pa_colorway', 'label' => 'Filtra per Colorway', 'chip' => 'Colorway', 'collapsible' => true),
            ),

            'grid_selector'       => 'ul.products',
            'count_selector'      => '.woocommerce-result-count',
            'pagination_selector' => '.woocommerce-pagination',

            'show_counts' => true,
            'count_limit' => 4000,
            'cache'       => true,
            'accent'      => '#111111',

            // When true, facets flagged 'variation' => true match at VARIATION level:
            // selecting a value only returns products with an in-stock variation for
            // it (so the customer never needs to also tick "Disponibile" for sizes).
            'variant_stock_aware' => true,

            'button_label' => 'Filtri',
            'exclude_cats' => array('uncategorized'),
        ));

        return $this->config;
    }
    /* =========================== END CONFIG =========================== */

    /* ------------------------- helpers ------------------------- */

    /** A query-string value as a string: filter URLs are user input, and a
     *  stray `filter_taglia[]=` array used to reach explode() (a fatal). */
    private static function str($value)
    {
        return is_string($value) ? $value : '';
    }

    private function suffix_for($tax)
    {
        if ('product_cat' === $tax) {
            return 'cat';
        }
        if (0 === strpos($tax, 'pa_')) {
            return substr($tax, 3);
        }
        return $tax;
    }

    // Flat map: url-suffix => taxonomy, across every facet.
    private function all_taxonomies()
    {
        $out = array();
        foreach ($this->config()['facets'] as $f) {
            if ('attribute' === $f['type']) {
                foreach ((array) $f['taxonomy'] as $t) {
                    $out[$this->suffix_for($t)] = $t;
                }
            } elseif ('category' === $f['type']) {
                $out['cat'] = 'product_cat';
            }
        }
        return $out;
    }

    // URL-suffixes whose facet is flagged as a product-variation attribute.
    private function variation_suffixes()
    {
        $out = array();
        foreach ($this->config()['facets'] as $f) {
            if ('attribute' === $f['type'] && !empty($f['variation'])) {
                foreach ((array) $f['taxonomy'] as $t) {
                    $out[] = $this->suffix_for($t);
                }
            }
        }
        return $out;
    }

    private function is_variation_suffix($sfx)
    {
        return in_array($sfx, $this->variation_suffixes(), true);
    }

    /* ------------------------- scope ------------------------- */

    /**
     * The collection being viewed: the queried term on any product taxonomy
     * archive — category, tag, brand (/marchio/…), attribute archive — or null
     * on the shop page and search results (the whole catalogue).
     *
     * @return array{taxonomy:string,term_id:int}|null
     */
    private function current_scope()
    {
        if (!is_product_taxonomy()) {
            return null;
        }
        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return null;
        }
        return $this->scope_from($term->taxonomy, $term->term_id);
    }

    /**
     * Validated scope from a taxonomy + term id (also the AJAX input, so the
     * taxonomy must be a public product taxonomy and the term must exist).
     */
    private function scope_from($taxonomy, $term_id)
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        $term_id  = absint($term_id);
        if ('' === $taxonomy || !$term_id || !in_array($taxonomy, $this->scope_taxonomies(), true)) {
            return null;
        }
        $term = get_term($term_id, $taxonomy);
        if (!$term instanceof WP_Term) {
            return null;
        }
        return array('taxonomy' => $taxonomy, 'term_id' => $term_id);
    }

    /** Public product taxonomies a collection page can be built on. */
    private function scope_taxonomies()
    {
        $out = array();
        foreach (get_object_taxonomies('product', 'objects') as $tax) {
            if ($tax->public && $tax->publicly_queryable) {
                $out[] = $tax->name;
            }
        }
        return $out;
    }

    /** Same match as the archive's own query: children included (/marchio/nike/ ⊃ nike-travis-scott). */
    private function scope_tax_clause($scope)
    {
        return array(
            'taxonomy'         => $scope['taxonomy'],
            'field'            => 'term_id',
            'terms'            => (int) $scope['term_id'],
            'include_children' => true,
        );
    }

    /* ----------------------- matching ----------------------- */

    /**
     * Parent product IDs that have an IN-STOCK variation matching every active
     * variation-flagged attribute (values within a facet = OR, across facets = AND),
     * unioned with any simple products carrying the term(s) at product level.
     * Returns null when the feature is off or no variation facet is active
     * (meaning: apply no variation restriction).
     */
    private function variant_matched_ids($filters, $exclude_key = null)
    {
        if (empty($this->config()['variant_stock_aware'])) {
            return null;
        }
        $active = array();
        foreach ($filters['tax'] as $sfx => $d) {
            if ($this->is_variation_suffix($sfx) && (null === $exclude_key || $sfx !== $exclude_key)) {
                $active[$d['taxonomy']] = $d;
            }
        }
        if (empty($active)) {
            return null;
        }

        global $wpdb;
        $sql = "SELECT DISTINCT v.post_parent FROM {$wpdb->posts} v";
        $a   = array();
        $i   = 0;
        foreach ($active as $tax => $d) {
            $i++;
            $ph   = implode(',', array_fill(0, count($d['terms']), '%s'));
            $sql .= " INNER JOIN {$wpdb->postmeta} m{$i} ON m{$i}.post_id = v.ID AND m{$i}.meta_key = %s AND LOWER(m{$i}.meta_value) IN ($ph)";
            $a[]  = 'attribute_' . $tax;
            foreach ($d['terms'] as $t) {
                $a[] = strtolower($t);
            }
        }
        $sql .= " INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = v.ID AND ms.meta_key = '_stock_status' AND ms.meta_value = 'instock'";
        $sql .= " WHERE v.post_type = 'product_variation' AND v.post_status = 'publish'";
        $parents = array_map('intval', $wpdb->get_col($wpdb->prepare($sql, $a)));

        // Union simple (non-variable) products that carry the term(s) in stock.
        $tax_clauses = array('relation' => 'AND', array('taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'simple'));
        foreach ($active as $tax => $d) {
            $tax_clauses[] = array('taxonomy' => $tax, 'field' => 'slug', 'terms' => $d['terms'], 'operator' => $d['operator']);
        }
        $sq = new WP_Query(array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'tax_query'      => $tax_clauses,
            'meta_query'     => array(array('key' => '_stock_status', 'value' => 'instock')),
        ));
        return array_values(array_unique(array_merge($parents, array_map('intval', $sq->posts))));
    }

    // Distinct in-stock parent counts per variation-attribute term, among $parent_ids.
    private function variation_term_counts($parent_ids, $taxonomy)
    {
        if (empty($parent_ids)) {
            return array();
        }
        global $wpdb;
        $in   = implode(',', array_map('absint', $parent_ids));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT LOWER(a.meta_value) slug, COUNT(DISTINCT v.post_parent) c
             FROM {$wpdb->posts} v
             INNER JOIN {$wpdb->postmeta} a ON a.post_id = v.ID AND a.meta_key = %s AND a.meta_value <> ''
             INNER JOIN {$wpdb->postmeta} s ON s.post_id = v.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock'
             WHERE v.post_type = 'product_variation' AND v.post_status = 'publish' AND v.post_parent IN ($in)
             GROUP BY slug",
            'attribute_' . $taxonomy
        ));
        $out = array();
        foreach ($rows as $r) {
            $out[$r->slug] = (int) $r->c;
        }
        return $out;
    }

    private function visibility_tax_query()
    {
        $excluded = array('exclude-from-catalog');
        if ('yes' === get_option('woocommerce_hide_out_of_stock_items')) {
            $excluded[] = 'outofstock';
        }
        return array('taxonomy' => 'product_visibility', 'field' => 'slug', 'terms' => $excluded, 'operator' => 'NOT IN');
    }

    private function empty_filters()
    {
        return array('tax' => array(), 'min_price' => null, 'max_price' => null, 'instock' => false);
    }

    private function has_filters($filters)
    {
        return !empty($filters['tax'])
            || null !== $filters['min_price']
            || null !== $filters['max_price']
            || !empty($filters['instock']);
    }

    private function parse_filters($query_string)
    {
        $raw = array();
        wp_parse_str((string) $query_string, $raw);
        $out = $this->empty_filters();

        foreach ($this->all_taxonomies() as $sfx => $tax) {
            $value = self::str($raw['filter_' . $sfx] ?? '');
            if ('' === $value) {
                continue;
            }
            $terms = array_values(array_filter(array_map('sanitize_title', explode(',', $value))));
            if (!$terms) {
                continue;
            }
            $op = 'or' === strtolower(self::str($raw['query_type_' . $sfx] ?? '')) ? 'IN' : 'AND';
            $out['tax'][$sfx] = array('taxonomy' => $tax, 'terms' => $terms, 'operator' => $op);
        }
        $min = self::str($raw['min_price'] ?? '');
        $max = self::str($raw['max_price'] ?? '');
        if ('' !== $min) {
            $out['min_price'] = (float) $min;
        }
        if ('' !== $max) {
            $out['max_price'] = (float) $max;
        }
        $out['instock'] = '1' === self::str($raw['instock'] ?? '');
        return $out;
    }

    private function build_query_args($filters, $scope, $exclude_key = null, $variant_ids = null)
    {
        $tax_query  = array('relation' => 'AND', $this->visibility_tax_query());
        $meta_query = array();

        if ($scope) {
            $tax_query[] = $this->scope_tax_clause($scope);
        }
        foreach ($filters['tax'] as $key => $data) {
            if ($exclude_key && $key === $exclude_key) {
                continue;
            }
            // Variation attributes are resolved via post__in (in-stock variation match), not here.
            if (null !== $variant_ids && $this->is_variation_suffix($key)) {
                continue;
            }
            $tax_query[] = array('taxonomy' => $data['taxonomy'], 'field' => 'slug', 'terms' => $data['terms'], 'operator' => $data['operator']);
        }
        if (null !== $filters['min_price'] || null !== $filters['max_price']) {
            $min = null !== $filters['min_price'] ? $filters['min_price'] : 0;
            $max = null !== $filters['max_price'] ? $filters['max_price'] : 999999999;
            $meta_query[] = array('key' => '_price', 'value' => array($min, $max), 'compare' => 'BETWEEN', 'type' => 'NUMERIC');
        }
        if (!empty($filters['instock'])) {
            $meta_query[] = array('key' => '_stock_status', 'value' => 'instock');
        }
        $args = array('post_type' => 'product', 'post_status' => 'publish', 'tax_query' => $tax_query, 'meta_query' => $meta_query);
        if (null !== $variant_ids) {
            $args['post__in'] = !empty($variant_ids) ? $variant_ids : array(0);
        }
        return $args;
    }

    private function matching_ids($filters, $scope, $exclude_key = null)
    {
        $variant_ids = $this->variant_matched_ids($filters, $exclude_key);
        $args = $this->build_query_args($filters, $scope, $exclude_key, $variant_ids);
        $args['fields']         = 'ids';
        $args['posts_per_page'] = -1;
        $args['no_found_rows']  = true;
        $q = new WP_Query($args);
        return $q->posts;
    }

    private function price_range($scope)
    {
        global $wpdb;
        $cache = !empty($this->config()['cache']);
        $key   = 'ghb_flt_price_' . md5(wp_json_encode($scope));
        if ($cache && false !== ($c = get_transient($key))) {
            return $c;
        }
        $lookup = $wpdb->prefix . 'wc_product_meta_lookup';
        if ($scope) {
            $ids = $this->matching_ids($this->empty_filters(), $scope);
            if (!$ids) {
                $range = array(0, 0);
            } else {
                $in    = implode(',', array_map('absint', $ids));
                $row   = $wpdb->get_row("SELECT MIN(min_price) lo, MAX(max_price) hi FROM {$lookup} WHERE product_id IN ({$in}) AND min_price IS NOT NULL");
                $range = $row ? array((float) $row->lo, (float) $row->hi) : array(0, 0);
            }
        } else {
            $row   = $wpdb->get_row("SELECT MIN(min_price) lo, MAX(max_price) hi FROM {$lookup} WHERE min_price IS NOT NULL");
            $range = $row ? array((float) $row->lo, (float) $row->hi) : array(0, 0);
        }
        $range = array((int) floor($range[0]), (int) ceil($range[1]));
        if ($cache) {
            set_transient($key, $range, HOUR_IN_SECONDS);
        }
        return $range;
    }

    /**
     * Option counts for every facet, within the scope:
     *   total   → products in the collection
     *   instock → how many of them are in stock (unfiltered calls only)
     *   terms   → [url-suffix => [term slug => count]]
     * Null when counts are off or the collection exceeds count_limit.
     */
    private function facet_counts($filters, $scope)
    {
        $cfg = $this->config();
        if (empty($cfg['show_counts'])) {
            return null;
        }
        $ck = 'ghb_flt_cnt_' . md5(wp_json_encode(array($filters, $scope)));
        if (!empty($cfg['cache']) && false !== ($c = get_transient($ck))) {
            return $c;
        }

        $base = $this->matching_ids($this->empty_filters(), $scope);
        if (count($base) > $cfg['count_limit']) {
            return null;
        }

        $filtered = $this->has_filters($filters);
        $out = array(
            'total'   => count($base),
            'instock' => $filtered
                ? null
                : count($this->matching_ids(array('instock' => true) + $this->empty_filters(), $scope)),
            'terms'   => array(),
        );
        foreach ($this->all_taxonomies() as $sfx => $tax) {
            // Each facet counts against the results with its own selection
            // removed (values within a facet are OR'd). With nothing selected
            // that is just the collection, so the one base query serves all.
            $ids = $filtered ? $this->matching_ids($filters, $scope, $sfx) : $base;
            if ($this->is_variation_suffix($sfx) && !empty($cfg['variant_stock_aware'])) {
                // Count distinct products with an IN-STOCK variation of each term.
                $out['terms'][$sfx] = $this->variation_term_counts($ids, $tax);
                continue;
            }
            $out['terms'][$sfx] = array();
            if ($ids) {
                $terms = wp_get_object_terms($ids, $tax, array('fields' => 'all_with_object_id'));
                if (!is_wp_error($terms)) {
                    foreach ($terms as $t) {
                        $out['terms'][$sfx][$t->slug] = ($out['terms'][$sfx][$t->slug] ?? 0) + 1;
                    }
                }
            }
        }
        if (!empty($cfg['cache'])) {
            set_transient($ck, $out, 5 * MINUTE_IN_SECONDS);
        }
        return $out;
    }

    private function term_names($taxonomy)
    {
        $map   = array();
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $map[$t->slug] = $t->name;
            }
        }
        return $map;
    }

    /* ------------------------- page loads ------------------------- */

    /**
     * Direct loads (shared links, the back button, search results): add what
     * WooCommerce's own layered nav doesn't know — filter_cat, instock and the
     * variant-stock-aware size match — to the main product query.
     */
    public function apply_extra_query_vars($q)
    {
        if (is_admin()) {
            return;
        }
        $tax        = (array) $q->get('tax_query');
        $filter_cat = self::str(wp_unslash($_GET['filter_cat'] ?? ''));
        if ('' !== $filter_cat) {
            $terms = array_filter(array_map('sanitize_title', explode(',', $filter_cat)));
            if ($terms) {
                $op    = 'or' === strtolower(self::str($_GET['query_type_cat'] ?? '')) ? 'IN' : 'AND';
                $tax[] = array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $terms, 'operator' => $op);
            }
        }
        if (!empty($tax)) {
            $q->set('tax_query', $tax);
        }
        if ('1' === self::str($_GET['instock'] ?? '')) {
            $meta   = (array) $q->get('meta_query');
            $meta[] = array('key' => '_stock_status', 'value' => 'instock');
            $q->set('meta_query', $meta);
        }

        // Variant-stock-aware size filtering on direct loads (incl. the back-button
        // reload after visiting a product) so the server-rendered grid matches AJAX.
        $filters     = $this->parse_filters(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
        $variant_ids = $this->variant_matched_ids($filters, null);
        if (null !== $variant_ids) {
            $existing = array_filter(array_map('absint', (array) $q->get('post__in')));
            $final    = $existing ? array_values(array_intersect($existing, $variant_ids)) : $variant_ids;
            $q->set('post__in', !empty($final) ? $final : array(0));
        }
    }

    /* ------------------------------ AJAX ----------------------------- */
    public function ajax_filter()
    {
        // Deliberately nonce-less, like the plugin's other read-only catalogue
        // endpoints: public data, and a cached page's nonce goes stale.
        $query = self::str(wp_unslash($_POST['params'] ?? ''));
        $paged = max(1, absint($_POST['paged'] ?? 1));

        $scope     = null;
        $scope_tax = self::str(wp_unslash($_POST['scope_tax'] ?? ''));
        if ('' !== $scope_tax) {
            $scope = $this->scope_from($scope_tax, $_POST['scope_term'] ?? 0);
            if (!$scope) {
                // Unknown collection: the panel falls back to a plain page load.
                wp_send_json_error('scope', 400);
            }
        }

        $parsed = array();
        parse_str($query, $parsed);
        $orderby = sanitize_text_field(self::str($parsed['orderby'] ?? ''));
        if ('' === $orderby) {
            $orderby = (string) apply_filters('woocommerce_default_catalog_orderby', get_option('woocommerce_default_catalog_orderby', 'menu_order'));
        }

        $filters     = $this->parse_filters($query);
        $per_page    = (int) apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page());
        $variant_ids = $this->variant_matched_ids($filters, null);
        $args = $this->build_query_args($filters, $scope, null, $variant_ids);
        $args['posts_per_page'] = $per_page;
        $args['paged']          = $paged;

        // Same ordering machinery as the page itself (price / popularity /
        // rating sort on WooCommerce's lookup table), so a refresh never
        // orders differently from a plain load of the same URL.
        $ordering = null;
        if (isset(WC()->query) && WC()->query instanceof WC_Query) {
            $parts    = explode('-', $orderby, 2);
            $ordering = WC()->query->get_catalog_ordering_args($parts[0], $parts[1] ?? '');
            $args['orderby'] = $ordering['orderby'];
            $args['order']   = $ordering['order'];
            if (!empty($ordering['meta_key'])) {
                $args['meta_key'] = $ordering['meta_key'];
            }
        } else {
            $args['orderby'] = 'menu_order title';
            $args['order']   = 'ASC';
        }

        $q = new WP_Query($args);
        if ($ordering) {
            WC()->query->remove_ordering_args();
        }

        // Render the same cards the page does. This request isn't a
        // WooCommerce page, so the plugin's product UI (loop add-to-cart +
        // Quick View) is switched on through its own filter and the core loop
        // button dropped as on the page — refreshed cards used to come back
        // with WooCommerce's stock button and no Quick View.
        add_filter('ghb_product_ui_should_load', '__return_true');
        if (function_exists('ghb_atc_replace_core_button')) {
            ghb_atc_replace_core_button();
        }

        wc_setup_loop(array(
            'columns'      => wc_get_default_products_per_row(),
            'is_paginated' => true,
            'total'        => (int) $q->found_posts,
            'total_pages'  => (int) $q->max_num_pages,
            'per_page'     => $per_page,
            'current_page' => $paged,
        ));
        ob_start();
        while ($q->have_posts()) {
            $q->the_post();
            $GLOBALS['product'] = wc_get_product(get_the_ID());
            wc_get_template_part('content', 'product');
        }
        $items = ob_get_clean();
        wp_reset_postdata();
        wc_reset_loop();

        $counts = $this->facet_counts($filters, $scope);

        wp_send_json_success(array(
            'items'      => $items,
            'found'      => (int) $q->found_posts,
            'count_html' => $this->result_count_html((int) $q->found_posts, $per_page, $paged),
            'pagination' => $this->pagination_html((int) $q->max_num_pages, $paged, $scope, $query),
            'counts'     => $counts ? $counts['terms'] : null,
        ));
    }

    private function result_count_html($found, $per_page, $paged)
    {
        if ($found < 1) {
            return esc_html__('Nessun prodotto trovato', 'golden-hive-blocks');
        }
        if (1 === $found) {
            return esc_html__('1 prodotto', 'golden-hive-blocks');
        }
        $first = (($paged - 1) * $per_page) + 1;
        $last  = min($found, $paged * $per_page);
        return sprintf(esc_html__('Mostrando %1$d-%2$d di %3$d prodotti', 'golden-hive-blocks'), $first, $last, $found);
    }

    private function pagination_html($pages, $current, $scope, $query)
    {
        if ($pages < 2) {
            return '';
        }
        // Page links stay on the collection (brand / tag / category archive),
        // not the shop page.
        $base = $scope ? get_term_link((int) $scope['term_id'], $scope['taxonomy']) : wc_get_page_permalink('shop');
        if (is_wp_error($base) || !$base) {
            $base = home_url('/');
        }
        $add_args = array();
        $parsed   = array();
        parse_str((string) $query, $parsed);
        foreach ($parsed as $k => $v) {
            if (0 === strpos($k, 'filter_') || 0 === strpos($k, 'query_type_') || in_array($k, array('min_price', 'max_price', 'orderby', 'instock'), true)) {
                $add_args[$k] = is_array($v) ? implode(',', array_map('sanitize_text_field', $v)) : sanitize_text_field($v);
            }
        }
        $links = paginate_links(array(
            'base'      => trailingslashit($base) . 'page/%#%/',
            'format'    => '',
            'current'   => max(1, $current),
            'total'     => $pages,
            'type'      => 'list',
            'add_args'  => $add_args,
            'prev_text' => '&larr;',
            'next_text' => '&rarr;',
        ));
        return '<nav class="woocommerce-pagination">' . $links . '</nav>';
    }

    /* ----------------------------- assets ---------------------------- */
    public function assets()
    {
        wp_register_style('golden-hive-filters', gh_asset_url('filters.css'), array(), GOLDEN_HIVE_BLOCKS_VERSION);
        wp_register_script('golden-hive-filters', gh_asset_url('js/filters.js'), array(), GOLDEN_HIVE_BLOCKS_VERSION, array('in_footer' => true));

        $accent = sanitize_hex_color($this->config()['accent']);
        if ($accent) {
            wp_add_inline_style('golden-hive-filters', ':root{--gh-filters-accent:' . $accent . '}');
        }

        // The panel lives on product archives (sidebar / theme drawer): load
        // its stylesheet in <head> there so it never paints unstyled. Other
        // pages get it from the shortcode (printed in the footer).
        if (is_shop() || is_product_taxonomy() || (is_search() && 'product' === get_query_var('post_type'))) {
            wp_enqueue_style('golden-hive-filters');
        }
    }

    /* --------------------------- shortcode --------------------------- */
    public function shortcode($atts)
    {
        if (is_admin()) {
            return '';
        }
        $atts   = shortcode_atts(array('mode' => 'drawer'), $atts, 'bfl_filters');
        $inline = ('inline' === $atts['mode']);
        $cfg    = $this->config();

        $scope = $this->current_scope();
        // Search results come from the search engine (Relevanssi on this
        // site), which a catalogue query can't reproduce: there the panel
        // reloads the page with the new filters instead of fetching.
        $ajax = !is_search();

        $current     = $this->parse_filters(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
        $base_counts = $this->facet_counts($this->empty_filters(), $scope);
        $cur_counts  = $this->has_filters($current) ? $this->facet_counts($current, $scope) : $base_counts;
        $price       = $this->price_range($scope);

        $names = array();
        foreach ($this->all_taxonomies() as $sfx => $tax) {
            $names[$sfx] = $this->term_names($tax);
        }

        $chip_map = array();
        foreach ($cfg['facets'] as $f) {
            if ('attribute' === $f['type']) {
                foreach ((array) $f['taxonomy'] as $t) {
                    $chip_map[$this->suffix_for($t)] = isset($f['chip']) ? $f['chip'] : $f['label'];
                }
            } elseif ('category' === $f['type']) {
                $chip_map['cat'] = isset($f['chip']) ? $f['chip'] : $f['label'];
            } elseif ('price' === $f['type']) {
                $chip_map['price'] = isset($f['chip']) ? $f['chip'] : $f['label'];
            } elseif ('stock' === $f['type']) {
                $chip_map['stock'] = isset($f['chip']) ? $f['chip'] : '';
            }
        }

        $labels      = array(); // names of the options actually rendered (chip labels)
        $facets_html = $this->render_facets_html($cfg, $current, $base_counts, $cur_counts, $price, $names, $labels);

        $js_cfg = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax'     => $ajax,
            'scope'    => $scope ? array('tax' => $scope['taxonomy'], 'term' => $scope['term_id']) : null,
            'grid'     => $cfg['grid_selector'],
            'countSel' => $cfg['count_selector'],
            'pagSel'   => $cfg['pagination_selector'],
            'taxKeys'  => array_keys($this->all_taxonomies()),
            'terms'    => $labels,
            'chip'     => $chip_map,
            'price'    => array('min' => $price[0], 'max' => $price[1], 'symbol' => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8')),
            'i18n'     => array(
                'empty' => __('Nessun prodotto trovato.', 'golden-hive-blocks'),
                'fino'  => __('fino a', 'golden-hive-blocks'),
                'da'    => __('da', 'golden-hive-blocks'),
            ),
        );

        wp_enqueue_style('golden-hive-filters');
        wp_enqueue_script('golden-hive-filters');
        wp_add_inline_script('golden-hive-filters', 'window.BFL_CFG = ' . wp_json_encode($js_cfg) . ';', 'before');

        ob_start();
        if ($inline) {
            echo '<div class="bfl-inline" data-bfl-root>' . $facets_html . '</div>';
        } else {
            ?>
            <button type="button" class="bfl-trigger" data-bfl-open>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span><?php echo esc_html($cfg['button_label']); ?></span>
                <span class="bfl-badge" data-bfl-badge hidden>0</span>
            </button>
            <?php
            static $drawer_done = false;
            if (!$drawer_done) :
                $drawer_done = true; ?>
                <div class="bfl-drawer" data-bfl-drawer aria-hidden="true">
                    <div class="bfl-backdrop" data-bfl-close></div>
                    <aside class="bfl-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Filtri', 'golden-hive-blocks'); ?>">
                        <header class="bfl-dhead">
                            <span class="bfl-dtitle"><?php esc_html_e('Filtra per:', 'golden-hive-blocks'); ?></span>
                            <button type="button" class="bfl-x" data-bfl-close aria-label="<?php esc_attr_e('Chiudi', 'golden-hive-blocks'); ?>">&times;</button>
                        </header>
                        <div class="bfl-dbody" data-bfl-root><?php echo $facets_html; ?></div>
                        <footer class="bfl-dfoot">
                            <button type="button" class="bfl-apply" data-bfl-close><?php esc_html_e('Vedi risultati', 'golden-hive-blocks'); ?></button>
                        </footer>
                    </aside>
                </div>
            <?php endif;
        }
        return ob_get_clean();
    }

    /* --------- facets markup (shared by drawer + inline) --------- */
    private function render_facets_html($cfg, $current, $base_counts, $cur_counts, $price, $names, &$labels)
    {
        $total = is_array($base_counts) ? (int) $base_counts['total'] : 0;

        ob_start(); ?>
        <div class="bfl-activewrap" data-bfl-activewrap hidden>
            <div class="bfl-sub"><?php esc_html_e('Filtri', 'golden-hive-blocks'); ?></div>
            <div class="bfl-active" data-bfl-active></div>
            <button type="button" class="bfl-reset" data-bfl-reset><?php esc_html_e('Cancella filtri', 'golden-hive-blocks'); ?></button>
        </div>

        <?php foreach ($cfg['facets'] as $key => $f) : ?>
            <?php if ('price' === $f['type']) :
                if ($price[1] <= $price[0]) {
                    continue;
                }
                $cmin = null !== $current['min_price'] ? (int) $current['min_price'] : $price[0];
                $cmax = null !== $current['max_price'] ? (int) $current['max_price'] : $price[1]; ?>
                <div class="bfl-facet">
                    <div class="bfl-flabel"><?php echo esc_html($f['label']); ?></div>
                    <div class="bfl-slider" data-bfl-slider data-floor="<?php echo esc_attr($price[0]); ?>" data-ceil="<?php echo esc_attr($price[1]); ?>">
                        <div class="bfl-track"><div class="bfl-fill" data-fill></div></div>
                        <input type="range" data-range="min" min="<?php echo esc_attr($price[0]); ?>" max="<?php echo esc_attr($price[1]); ?>" value="<?php echo esc_attr($cmin); ?>" aria-label="<?php esc_attr_e('Prezzo minimo', 'golden-hive-blocks'); ?>">
                        <input type="range" data-range="max" min="<?php echo esc_attr($price[0]); ?>" max="<?php echo esc_attr($price[1]); ?>" value="<?php echo esc_attr($cmax); ?>" aria-label="<?php esc_attr_e('Prezzo massimo', 'golden-hive-blocks'); ?>">
                    </div>
                    <div class="bfl-nums">
                        <div class="bfl-num"><input type="number" data-price-input="min" min="<?php echo esc_attr($price[0]); ?>" max="<?php echo esc_attr($price[1]); ?>" value="<?php echo esc_attr($cmin); ?>" aria-label="<?php esc_attr_e('Prezzo minimo', 'golden-hive-blocks'); ?>"><span>&euro;</span></div>
                        <div class="bfl-num"><input type="number" data-price-input="max" min="<?php echo esc_attr($price[0]); ?>" max="<?php echo esc_attr($price[1]); ?>" value="<?php echo esc_attr($cmax); ?>" aria-label="<?php esc_attr_e('Prezzo massimo', 'golden-hive-blocks'); ?>"><span>&euro;</span></div>
                    </div>
                </div>

            <?php elseif ('stock' === $f['type']) :
                // Every product in the collection is already in stock (e.g. the
                // store hides sold-out items): "Disponibile" would do nothing.
                if (empty($current['instock']) && is_array($base_counts) && null !== $base_counts['instock']
                    && $base_counts['instock'] >= $total) {
                    continue;
                } ?>
                <div class="bfl-facet">
                    <div class="bfl-flabel"><?php echo esc_html($f['label']); ?></div>
                    <label class="bfl-check">
                        <input type="checkbox" data-bfl-stock <?php checked(!empty($current['instock'])); ?>>
                        <span class="bfl-box"></span>
                        <span><?php echo esc_html(isset($f['text']) ? $f['text'] : __('Disponibile', 'golden-hive-blocks')); ?></span>
                    </label>
                </div>

            <?php else :
                $taxes   = ('category' === $f['type']) ? array('product_cat') : (array) $f['taxonomy'];
                $options = array();
                foreach ($taxes as $tax) {
                    $sfx      = $this->suffix_for($tax);
                    $active   = isset($current['tax'][$sfx]) ? $current['tax'][$sfx]['terms'] : array();
                    $base     = (is_array($base_counts) && isset($base_counts['terms'][$sfx])) ? $base_counts['terms'][$sfx] : null;
                    // Slugs as strings: numeric ones ("44") come back from array
                    // keys as ints, which broke the strict checks below (a
                    // selected size was listed twice and never shown ticked).
                    $present  = array_map('strval', array_keys(null !== $base ? $base : (isset($names[$sfx]) ? $names[$sfx] : array())));
                    $can_hide = null !== $base && !$this->is_variation_suffix($sfx);
                    foreach ($active as $a) {
                        if (!in_array($a, $present, true)) {
                            $present[] = $a;
                        }
                    }
                    if ('category' === $f['type']) {
                        $present = array_values(array_diff($present, (array) $cfg['exclude_cats']));
                    }
                    // The store's own order (get_terms honours WooCommerce's
                    // attribute / category ordering: 42, 44, 44.5…), not the
                    // order the counts happened to come back in.
                    $order = array_flip(array_map('strval', array_keys(isset($names[$sfx]) ? $names[$sfx] : array())));
                    usort($present, function ($a, $b) use ($order) {
                        return ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX);
                    });
                    foreach ($present as $slug) {
                        $checked = in_array($slug, $active, true);
                        // Carried by every product in the collection (the
                        // category you're in, the brand of a brand page): can't
                        // narrow anything, so it isn't offered.
                        if (!$checked && $can_hide && $total > 0 && ($base[$slug] ?? 0) >= $total) {
                            continue;
                        }
                        $c    = (is_array($cur_counts) && isset($cur_counts['terms'][$sfx][$slug])) ? (int) $cur_counts['terms'][$sfx][$slug] : (is_array($cur_counts) ? 0 : null);
                        $name = isset($names[$sfx][$slug]) ? $names[$sfx][$slug] : $slug;
                        $labels[$sfx][$slug] = $name;
                        $options[] = array(
                            'sfx'     => $sfx,
                            'slug'    => $slug,
                            'name'    => $name,
                            'count'   => $c,
                            'dis'     => (null !== $c && 0 === $c && !$checked),
                            'checked' => $checked,
                        );
                    }
                }
                if (empty($options)) {
                    continue;
                }
                $ui          = ('category' === $f['type']) ? 'pills' : 'list';
                $collapsible = !empty($f['collapsible']); ?>
                <div class="bfl-facet bfl-facet--<?php echo esc_attr($ui); ?>">
                    <?php if ($collapsible) : ?>
                        <button type="button" class="bfl-flabel bfl-toggle" aria-expanded="true"><span><?php echo esc_html($f['label']); ?></span><i class="bfl-pm" aria-hidden="true"></i></button>
                    <?php else : ?>
                        <div class="bfl-flabel"><?php echo esc_html($f['label']); ?></div>
                    <?php endif; ?>
                    <div class="bfl-opts">
                        <?php foreach ($options as $o) : ?>
                            <label class="bfl-opt<?php echo $o['dis'] ? ' is-disabled' : ''; ?>">
                                <input type="checkbox" data-bfl-term data-key="<?php echo esc_attr($o['sfx']); ?>" value="<?php echo esc_attr($o['slug']); ?>" <?php checked($o['checked']); ?> <?php disabled($o['dis']); ?>>
                                <span class="bfl-mark"></span>
                                <span class="bfl-name"><?php echo esc_html($o['name']); ?></span>
                                <?php if (null !== $o['count']) : ?><span class="bfl-count" data-count><?php echo esc_html($o['count']); ?></span><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="bfl-loading" data-bfl-spinner aria-hidden="true"><span></span></div>
        <?php
        return ob_get_clean();
    }
}
