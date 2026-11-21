<?php
/**
 * Golden Hive — Store Hub bridge ("Vetrina"): the homepage as the Hub sees it.
 *
 * What the REST routes (hub-rest.php) are built from: product cards and
 * terms for the Hub's screens, why a pinned product isn't showing, the
 * block fields the Hub may edit and their sanitizers, a section's new source
 * and the button that follows it, and reading and saving the page itself.
 *
 * @package Golden_Hive_Blocks
 * @since   5.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ==================================================================== *
 * 1. Reading products and terms for the Hub
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
 * 2. The homepage page
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
        'fields'           => (object) (ghb_hub_fields_of($block) ?? array()),
        // Switched off from the Vetrina: on the page, rendering nothing. (Not
        // "hidden": /rail's answer lists the products hidden from it under that.)
        'switched_off'     => !empty($attrs['hidden']),
    );
    if ($with_products) {
        $payload['products'] = ghb_hub_product_cards(ghb_hub_rail_ids($atts, true));
    }
    return $payload;
}

/**
 * The block fields the Hub may edit (ghb_hub_field_specs), filterable for a
 * site that wants fewer. Whatever the filter returns is still sanitized by
 * type on write.
 */
function ghb_hub_editable_fields(): array
{
    return (array) apply_filters('ghb_hub_editable_fields', ghb_hub_field_specs());
}

/**
 * What each field shows when the block leaves it out: the store's value for
 * a field that follows the store (the WhatsApp message: includes/store.php),
 * otherwise the block type's own default (block.json).
 */
function ghb_hub_field_defaults(string $block_name, array $spec): array
{
    $type     = WP_Block_Type_Registry::get_instance()->get_registered($block_name);
    $defaults = array();
    foreach (array_keys($spec) as $field) {
        $default = function_exists('ghb_store_block_default') ? ghb_store_block_default($block_name, $field) : null;
        if (null === $default) {
            $default = $type && isset($type->attributes[$field]['default']) ? $type->attributes[$field]['default'] : '';
        }
        $defaults[$field] = is_scalar($default) ? (string) $default : '';
    }
    return $defaults;
}

/**
 * WordPress's sanitizer for each field type. The page is written back with
 * kses off (it must keep its own markup), so this is what stands between a
 * field and the page: text loses its tags, a link must be http(s) or local.
 */
function ghb_hub_clean_field(string $type, string $value): string
{
    switch ($type) {
        case 'url':
            $value = trim($value);
            return '' === $value ? '' : esc_url_raw($value, array('http', 'https'));
        case 'enum':
            return trim($value);
        default:
            return sanitize_text_field($value);
    }
}

/** A block's editable fields with their current values; null when it has none. */
function ghb_hub_fields_of(array $block): ?array
{
    $specs = ghb_hub_editable_fields();
    $name  = (string) ($block['blockName'] ?? '');
    if (empty($specs[$name])) {
        return null;
    }
    $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
    return ghb_hub_block_fields($attrs, $specs[$name], ghb_hub_field_defaults($name, $specs[$name]));
}

/** What went wrong with a field, in the words the Hub shows. */
function ghb_hub_field_error(array $applied, array $spec): WP_Error
{
    $field = $applied['field'];
    $max   = isset($spec[$field]['max']) ? (int) $spec[$field]['max'] : 0;
    switch ($applied['error']) {
        case 'not_editable':
            $message = "Il campo {$field} non si può modificare dalla Vetrina.";
            break;
        case 'bad_url':
            $message = 'Il link non è valido: usa un indirizzo http(s) o un percorso del sito (/…).';
            break;
        case 'too_long':
            $message = "Il testo di {$field} è troppo lungo (al massimo {$max} caratteri).";
            break;
        default:
            $message = "Valore non valido per {$field}.";
    }
    return new WP_Error('ghb_bad_field', $message, array('status' => 400, 'field' => $field, 'reason' => $applied['error']));
}

/**
 * The term a section's new source names, looked up the way the rail query
 * reads it: a category in product_cat; a brand in whichever brand taxonomy
 * holds that slug (product_brand, pwb-brand, pa_brand); a tag in product_tag.
 *
 * @return WP_Term|WP_Error
 */
function ghb_hub_source_term(array $source)
{
    $taxonomies = array(
        'category' => array('product_cat'),
        'brand'    => array('product_brand', 'pwb-brand', 'pa_brand'),
        'tag'      => array('product_tag'),
    );
    foreach ($taxonomies[$source['taxonomy']] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }
        $term = get_term_by('slug', $source['slug'], $taxonomy);
        if ($term instanceof WP_Term) {
            return $term;
        }
    }
    $where = array('category' => 'tra le categorie', 'brand' => 'tra i brand', 'tag' => 'tra i tag');
    return new WP_Error(
        'ghb_bad_source',
        "Non trovo «{$source['slug']}» {$where[$source['taxonomy']]} del negozio.",
        array('status' => 400)
    );
}

/** A source the Hub sent, checked for shape and — a term — for existence. */
function ghb_hub_checked_source($raw)
{
    $source = ghb_hub_parse_source($raw);
    if (!$source) {
        return new WP_Error('ghb_bad_source', 'Cosa mostra la sezione non è valido.', array('status' => 400));
    }
    if ('term' === $source['kind']) {
        $term = ghb_hub_source_term($source);
        if (is_wp_error($term)) {
            return $term;
        }
        $source['term'] = $term;
    }
    return $source;
}

/**
 * Where a section's button should point once the section shows something
 * else — only when it pointed at what the section showed before ("Esplora le
 * tendenze" still opening the old category would send customers to the wrong
 * page). The new term's page, or the shop's for a shop-wide list; a link that
 * was site-relative stays site-relative. Null: leave the button alone.
 */
function ghb_hub_followed_button(string $url, array $old_terms, array $source): ?string
{
    $path = static function ($link): string {
        return untrailingslashit((string) wp_parse_url((string) $link, PHP_URL_PATH));
    };
    $current = $path($url);
    if ('' === trim($url) || '' === $current) {
        return null;
    }
    $pointed = false;
    foreach ($old_terms['terms'] ?? array() as $term) {
        if (!empty($term['link']) && $path($term['link']) === $current) {
            $pointed = true;
        }
    }
    if (!$pointed) {
        return null;
    }
    if ('term' === $source['kind']) {
        $link = get_term_link($source['term']);
    } else {
        $orderby = array('recent' => 'date', 'best_selling' => 'popularity', 'top_rated' => 'rating');
        $link    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
        if (isset($orderby[$source['type']])) {
            $link = add_query_arg('orderby', $orderby[$source['type']], $link);
        }
    }
    if (is_wp_error($link) || !is_string($link) || '' === $link) {
        return null;
    }
    return 0 === strpos(ltrim($url), '/') ? wp_make_link_relative($link) : $link;
}

/**
 * Save the page's content as edited here. It is written back exactly as
 * given — every change in it was made and checked by this bridge — so kses
 * must not re-filter the page's own markup (its <style> block included) for a
 * user without unfiltered_html. A normal wp_update_post, so the page gets a
 * revision; then the rails' caches and the page caches are purged.
 *
 * @return WP_Post|WP_Error The page as saved.
 */
function ghb_hub_save_page_content(WP_Post $page, string $content)
{
    $kses = false !== has_filter('content_save_pre', 'wp_filter_post_kses');
    if ($kses) {
        kses_remove_filters();
    }
    // wp_update_post() unslashes its input: slash it, or every \u0022 escape in
    // the block attributes would lose its backslash and break the page.
    $result = wp_update_post(array('ID' => $page->ID, 'post_content' => wp_slash($content)), true);
    if ($kses) {
        kses_init_filters();
    }
    if (is_wp_error($result)) {
        return new WP_Error('ghb_write_failed', $result->get_error_message(), array('status' => 500));
    }

    ghb_hub_bump_cache_gen();
    ghb_hub_purge_page_cache((int) $page->ID);
    $saved = get_post($page->ID);
    return $saved instanceof WP_Post ? $saved : new WP_Error('ghb_write_failed', 'Pagina non trovata dopo il salvataggio.', array('status' => 500));
}

/** A rail's key in the page as saved, by the place it was written at. */
function ghb_hub_key_at(WP_Post $page, string $path): ?string
{
    foreach (ghb_hub_page_rails(parse_blocks($page->post_content)) as $key => $entry) {
        if ($entry['path'] === $path) {
            return $key;
        }
    }
    return null;
}

/** What this bridge can do: the Hub offers only that. */
function ghb_hub_features(): array
{
    return array(
        'homepage', 'rail', 'rail-visible', 'products', 'block-write', 'history',
        'rail-pin', 'rail-exclude', 'rail-fallback', 'rail-limit', 'block-fields', 'archive-follow',
        // 5.15.0: what a section shows, its switch, and the page's sections themselves.
        'rail-source', 'rail-hidden', 'layout-insert', 'layout-remove', 'layout-arrange',
        // 5.20.0: rails' layout defaults are the store's (capabilities: rail_defaults),
        // and block fields left out read as the store's details.
        'rail-defaults', 'store-defaults',
    );
}
