<?php
/**
 * Golden Hive — Store Hub bridge ("Vetrina"): the REST API.
 *
 * What the Store Hub app reads and writes through, in namespace wc-gh/v1:
 * the homepage's sections and rails, product cards, one section's edit,
 * the page's layout, a rail's history. What the routes are built from (the
 * page, its fields, product cards) is hub-page.php. WooCommerce authenticates any "wc-*"
 * namespace with its REST API keys, so the Hub uses the consumer key it
 * already holds — no Application Password. Every route requires
 * manage_woocommerce.
 *
 * Writes refuse rather than guess: the page and the block must be the ones
 * the Hub read. The rail query the storefront runs is hub-rails.php; the
 * decisions are pure functions in hub-rails-core.php, which the Hub app
 * mirrors.
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

/* ==================================================================== *
 * 1. REST routes
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
            // Either the block path read from /homepage, or the rail's stable
            // key ("category:saldi-sneakers-outlet#0").
            'path'    => array('type' => 'string', 'required' => false),
            'key'     => array('type' => 'string', 'required' => false),
            'offset'  => array('type' => 'integer', 'required' => false, 'default' => 0, 'minimum' => 0),
            'count'   => array('type' => 'integer', 'required' => false, 'default' => 60, 'minimum' => 1, 'maximum' => 200),
            // Preview another automatic order without saving it.
            'fallback' => array('type' => 'string', 'required' => false, 'enum' => ghb_hub_fallbacks()),
        ),
    ));
    register_rest_route(GHB_HUB_NS, '/products', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_products',
        'args'                => array('ids' => array('type' => 'string', 'required' => true)),
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
    register_rest_route(GHB_HUB_NS, '/homepage/layout', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'permission_callback' => 'ghb_hub_permission',
        'callback'            => 'ghb_hub_rest_layout',
    ));
}

function ghb_hub_rest_capabilities()
{
    return rest_ensure_response(array(
        'plugin'            => 'golden-hive-blocks',
        'version'           => GOLDEN_HIVE_BLOCKS_VERSION,
        'api'               => GHB_HUB_API,
        'features'          => ghb_hub_features(),
        'rail_types'        => ghb_hub_rail_types(),
        'fields'            => (object) ghb_hub_editable_fields(),
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
        $item = array(
            'path'    => $path,
            'name'    => $block['blockName'],
            'kind'    => 'static',
            'summary' => ghb_hub_block_summary($block),
        );
        $fields = ghb_hub_fields_of($block);
        if (null !== $fields) {
            // Editable from the Vetrina: its fields, and the fingerprint a
            // write must quote back.
            $item['fields']     = $fields;
            $item['attrs_hash'] = ghb_hub_attrs_hash($block['attrs']);
        }
        $items[] = $item;
    }

    return rest_ensure_response(array(
        'page_id'      => (int) $page->ID,
        'title'        => get_the_title($page),
        'link'         => get_permalink($page),
        'edit_link'    => admin_url('post.php?post=' . $page->ID . '&action=edit'),
        'modified_gmt' => $page->post_modified_gmt,
        // With the page, so the Hub knows what it may offer without asking twice.
        'features'     => ghb_hub_features(),
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
    $wanted = (string) $request->get_param('key');
    $blocks = parse_blocks($page->post_content);
    $key    = null;
    foreach (ghb_hub_page_rails($blocks) as $rail_key => $entry) {
        if (('' !== $wanted && $rail_key === $wanted) || ('' === $wanted && $entry['path'] === $path)) {
            $path = $entry['path'];
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
    $preview  = (string) $request->get_param('fallback');
    $fallback = '' !== $preview ? ghb_hub_normalize_fallback($preview, $rail['atts']) : $rail['fallback'];
    $visible  = ghb_hub_rail_visible_ids($args, $fallback, true);
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
        'page_link'         => get_permalink($page),
        'modified_gmt'      => $page->post_modified_gmt,
        'hide_out_of_stock' => ghb_hub_hide_out_of_stock($atts),
        'total'             => count($ordered),
        'offset'            => $offset,
        // Every visible member in automatic order (pins not applied): with it
        // the Hub recomputes the rail after any pin or exclusion locally,
        // using the same rule as ghb_hub_order_ids().
        'visible'           => $visible,
        'preview_fallback'  => $fallback,
        'items'             => $items,
        'hidden'            => $hidden,
    )));
}

/** Cards for up to 100 products by id — what the editor needs when a product scrolls into view. */
function ghb_hub_rest_products(WP_REST_Request $request)
{
    $ids = ghb_hub_parse_id_list((string) $request->get_param('ids'));
    return rest_ensure_response(array('products' => ghb_hub_product_cards($ids)));
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
    $limit = null;
    if (isset($input['limit'])) {
        $limit = filter_var($input['limit'], FILTER_VALIDATE_INT);
        if (false === $limit || $limit < 1 || $limit > GHB_HUB_MAX_IDS) {
            return new WP_Error('ghb_bad_input', 'Numero di prodotti non valido (da 1 a ' . GHB_HUB_MAX_IDS . ').', array('status' => 400));
        }
    }
    return array(
        'pin'      => ghb_hub_parse_id_list($input['pin'] ?? array()),
        'exclude'  => ghb_hub_parse_id_list($input['exclude'] ?? array()),
        'fallback' => $fallback,
        'limit'    => $limit,
    );
}

/**
 * Write one block of the page: a rail's pin / exclude / fallback / limit
 * ("rail") — and, since 5.15.0, what it shows ("rail.source": another
 * category, brand or tag, or a shop-wide list) — and/or the block's editable
 * fields ("attrs", checked against ghb_hub_editable_fields and sanitized by
 * type).
 *
 * Refuses rather than guesses at every step: the page must be the one the Hub
 * read (modified time), the block must be the one it read (path, name and an
 * attribute fingerprint), and its delimiter must be found exactly once. Only
 * that block's attributes change; the write is a normal wp_update_post, so
 * the page gets a revision.
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
    $rail_input  = $request->get_param('rail');
    $field_input = $request->get_param('attrs');
    if (null === $rail_input && null === $field_input) {
        return new WP_Error('ghb_bad_input', 'Niente da modificare.', array('status' => 400));
    }

    $rail      = ghb_hub_rail_from_block($block);
    $new_attrs = $block['attrs'];
    $source    = null;

    if (null !== $rail_input) {
        if (!$rail) {
            return new WP_Error('ghb_not_a_rail', 'Questo blocco non è una sezione prodotti.', array('status' => 422));
        }
        $values = ghb_hub_validate_rail_input($rail_input);
        if (is_wp_error($values)) {
            return $values;
        }
        // A new source replaces what the rail showed — a fixed list too, which
        // otherwise only WordPress's editor changes.
        if (is_array($rail_input) && array_key_exists('source', $rail_input)) {
            $source = ghb_hub_checked_source($rail_input['source']);
            if (is_wp_error($source)) {
                return $source;
            }
        } elseif (!empty($rail['atts']['ids'])) {
            return new WP_Error('ghb_fixed_list', 'Questa sezione mostra una lista fissa di prodotti.', array('status' => 422));
        }
        $base          = $source ? ghb_hub_set_rail_source($rail['shortcode'], $source) : $rail['shortcode'];
        $new_shortcode = null === $base ? null : ghb_hub_set_rail_atts($base, $values);
        if (null === $new_shortcode) {
            return new WP_Error('ghb_unwritable', 'Lo shortcode della sezione non si può riscrivere in sicurezza.', array('status' => 422));
        }
        $new_attrs['shortcode'] = $new_shortcode;
    }

    if (null !== $field_input) {
        $specs = ghb_hub_editable_fields();
        $name  = $block['blockName'];
        if (!is_array($field_input)) {
            return new WP_Error('ghb_bad_input', 'I campi vanno inviati come oggetto.', array('status' => 400));
        }
        if (empty($specs[$name])) {
            return new WP_Error('ghb_no_fields', 'Questo blocco non ha campi modificabili dalla Vetrina.', array('status' => 422));
        }
        $applied = ghb_hub_apply_fields($new_attrs, $field_input, $specs[$name], ghb_hub_field_defaults($name, $specs[$name]), 'ghb_hub_clean_field');
        if (!$applied['ok']) {
            return ghb_hub_field_error($applied, $specs[$name]);
        }
        $new_attrs = $applied['attrs'];
    }

    // Pointed elsewhere, the section's button follows — unless this write sets it.
    $followed = null;
    if ($source && !(is_array($field_input) && array_key_exists('buttonUrl', $field_input))) {
        $followed = ghb_hub_followed_button((string) ($new_attrs['buttonUrl'] ?? ''), ghb_hub_rail_terms(ghb_hub_rail_atts($rail['atts'])), $source);
        if (null !== $followed) {
            $new_attrs['buttonUrl'] = $followed;
        }
    }

    $dry_run  = (bool) $request->get_param('dry_run');
    $response = array(
        'dry_run' => $dry_run,
        'changed' => $new_attrs !== $block['attrs'],
        'path'    => $path,
        'before'  => $rail ? $rail['shortcode'] : '',
        'after'   => $rail ? (string) ($new_attrs['shortcode'] ?? '') : '',
        'fields'  => (object) (ghb_hub_fields_of(array('blockName' => $block['blockName'], 'attrs' => $new_attrs)) ?? array()),
    );
    if (null !== $followed) {
        $response['button_url'] = $followed;
    }

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

    $page = ghb_hub_save_page_content($page, $replaced['content']);
    if (is_wp_error($page)) {
        return $page;
    }
    $response['modified_gmt'] = $page->post_modified_gmt;
    $response['attrs_hash']   = ghb_hub_attrs_hash($new_attrs);
    $response['rendered']     = $rail
        ? ghb_hub_rail_ids(ghb_hub_rail_atts(ghb_hub_parse_shortcode((string) $new_attrs['shortcode'])['atts']), true)
        : array();
    if ($rail) {
        // What the rail is called now: a new source is a new key.
        $response['key'] = ghb_hub_key_at($page, $path);
    }
    return rest_ensure_response($response);
}

/** A rail of the page as the Hub read it ({ path, attrs_hash }), or why it is not that any more. */
function ghb_hub_rail_ref(array $rails, $ref)
{
    if (!is_array($ref)) {
        return new WP_Error('ghb_bad_input', 'Sezione mancante.', array('status' => 400));
    }
    $path = (string) ($ref['path'] ?? '');
    foreach ($rails as $key => $entry) {
        if ($entry['path'] !== $path) {
            continue;
        }
        if ((string) ($ref['attrs_hash'] ?? '') !== ghb_hub_attrs_hash($entry['block']['attrs'])) {
            return new WP_Error('ghb_block_changed', 'La sezione è stata modificata nel frattempo: ricarica.', array('status' => 409));
        }
        return $entry + array('key' => $key);
    }
    return new WP_Error('ghb_block_moved', 'La sezione non è più in quella posizione: ricarica.', array('status' => 409));
}

/**
 * A new rail block from what the Hub sends, every value checked: what it
 * shows (a term must exist in the shop), how many products, its layout (the
 * rail it is placed next to, unless given), its pins, exclusions and
 * fallback, and its texts — the same fields, cleaned the same way, as an edit.
 *
 * @return array|WP_Error ['blockName' => …, 'attrs' => …]
 */
function ghb_hub_build_new_rail($rail_input, $field_input, array $neighbour_atts)
{
    if (!is_array($rail_input)) {
        return new WP_Error('ghb_bad_input', 'Dati della sezione mancanti.', array('status' => 400));
    }
    $source = ghb_hub_checked_source($rail_input['source'] ?? null);
    if (is_wp_error($source)) {
        return $source;
    }
    $values = ghb_hub_validate_rail_input($rail_input);
    if (is_wp_error($values)) {
        return $values;
    }
    $limit     = $values['limit'] ?? max(1, (int) ($neighbour_atts['limit'] ?? 12));
    $layout    = isset($rail_input['layout']) && is_array($rail_input['layout']) ? $rail_input['layout'] : array();
    $shortcode = ghb_hub_new_rail_shortcode($source, $limit, $neighbour_atts, $layout, $values);
    if (null === $shortcode) {
        return new WP_Error('ghb_unwritable', 'Lo shortcode della sezione non si può scrivere in sicurezza.', array('status' => 422));
    }
    $spec    = ghb_hub_editable_fields()[GHB_HUB_RAIL_BLOCK] ?? array();
    $applied = ghb_hub_apply_fields(
        array(),
        is_array($field_input) ? $field_input : array(),
        $spec,
        ghb_hub_field_defaults(GHB_HUB_RAIL_BLOCK, $spec),
        'ghb_hub_clean_field'
    );
    if (!$applied['ok']) {
        return ghb_hub_field_error($applied, $spec);
    }
    return array('blockName' => GHB_HUB_RAIL_BLOCK, 'attrs' => ghb_hub_new_rail_attrs($shortcode, $applied['attrs']));
}

/** Why a layout edit was refused, in the words the Hub shows. */
function ghb_hub_layout_error(array $edit): WP_Error
{
    switch ($edit['error'] ?? '') {
        case 'duplicate':
            return new WP_Error('ghb_duplicate_section', 'Nella homepage c\'è già una sezione identica: cambia il titolo o cosa mostra.', array('status' => 422));
        case 'ambiguous':
            return new WP_Error('ghb_block_not_unique', 'La stessa sezione compare più volte nella pagina: modificala dall\'editor di WordPress.', array('status' => 422));
        case 'not_a_permutation':
            return new WP_Error('ghb_bad_input', 'Ogni sezione va messa una volta sola.', array('status' => 400));
        default:
            return new WP_Error('ghb_block_not_unique', 'La sezione non si trova nel contenuto salvato: ricarica.', array('status' => 422));
    }
}

/**
 * The page's sections themselves: add one ("insert", next to a section the
 * Hub read), take one away ("remove"), or put them in another order
 * ("arrange": every section of the page, each once, in its new order — the
 * page keeps a place for each where it is, and each place takes the section
 * that goes there now).
 *
 * The same refusals as a block write: the page must be the one the Hub read,
 * every section named must be as the Hub read it, every delimiter is found
 * exactly once. Only those delimiters change, through wp_update_post: the
 * page gets a revision.
 */
function ghb_hub_rest_layout(WP_REST_Request $request)
{
    $page = ghb_hub_get_page($request->get_param('page_id'));
    if (is_wp_error($page)) {
        return $page;
    }
    if ((string) $request->get_param('expected_modified_gmt') !== $page->post_modified_gmt) {
        return new WP_Error('ghb_stale_page', 'La homepage è stata modificata nel frattempo: ricarica.', array('status' => 409, 'modified_gmt' => $page->post_modified_gmt));
    }

    $rails     = ghb_hub_page_rails(parse_blocks($page->post_content));
    $action    = (string) $request->get_param('action');
    $serialize = 'serialize_block_attributes';
    $added     = null;

    switch ($action) {
        case 'insert':
            $anchor = ghb_hub_rail_ref($rails, $request->get_param('anchor'));
            if (is_wp_error($anchor)) {
                return $anchor;
            }
            $added = ghb_hub_build_new_rail($request->get_param('rail'), $request->get_param('attrs'), $anchor['rail']['atts']);
            if (is_wp_error($added)) {
                return $added;
            }
            $place = 'before' === $request->get_param('place') ? 'before' : 'after';
            $edit  = ghb_hub_insert_block($page->post_content, $anchor['block'], $place, $added, $serialize);
            break;

        case 'remove':
            $target = ghb_hub_rail_ref($rails, $request->get_param('target'));
            if (is_wp_error($target)) {
                return $target;
            }
            // The last one stays: a new section is always placed next to another.
            if (count($rails) <= 1) {
                return new WP_Error('ghb_last_rail', 'È l\'ultima sezione di prodotti della homepage: si toglie dall\'editor di WordPress.', array('status' => 422));
            }
            $edit = ghb_hub_remove_block($page->post_content, $target['block'], $serialize);
            break;

        case 'arrange':
            $wanted = $request->get_param('order');
            if (!is_array($wanted) || count($wanted) !== count($rails)) {
                return new WP_Error('ghb_stale_page', 'Le sezioni della homepage sono cambiate nel frattempo: ricarica.', array('status' => 409));
            }
            $slots = array_values($rails);
            $paths = array_column($slots, 'path');
            $order = array();
            foreach ($wanted as $ref) {
                $entry = ghb_hub_rail_ref($rails, $ref);
                if (is_wp_error($entry)) {
                    return $entry;
                }
                $order[] = (int) array_search($entry['path'], $paths, true);
            }
            $edit = ghb_hub_arrange_blocks($page->post_content, array_column($slots, 'block'), $order, $serialize);
            break;

        default:
            return new WP_Error('ghb_bad_input', 'Azione non riconosciuta.', array('status' => 400));
    }
    if (!$edit['ok']) {
        return ghb_hub_layout_error($edit);
    }

    $dry_run  = (bool) $request->get_param('dry_run');
    $response = array(
        'dry_run' => $dry_run,
        'changed' => $edit['content'] !== $page->post_content,
        'action'  => $action,
    );
    if ($dry_run || !$response['changed']) {
        $response['modified_gmt'] = $page->post_modified_gmt;
        return rest_ensure_response($response);
    }

    $page = ghb_hub_save_page_content($page, $edit['content']);
    if (is_wp_error($page)) {
        return $page;
    }
    $response['modified_gmt'] = $page->post_modified_gmt;
    if ($added) {
        // The new section as the page now reads it: its key and its place.
        foreach (ghb_hub_page_rails(parse_blocks($page->post_content)) as $key => $entry) {
            if ($entry['block']['attrs'] === $added['attrs']) {
                $response['key']  = $key;
                $response['path'] = $entry['path'];
                break;
            }
        }
    }
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
 * 2. Cache purge
 * ==================================================================== */

/**
 * After a write, the rails change on the homepage and on the category pages
 * that follow them: purge whole-site caches where a known plugin runs, and
 * let the site hook its own (a CDN) on ghb_hub_page_published.
 */
function ghb_hub_purge_page_cache(int $page_id): void
{
    clean_post_cache($page_id);
    ghb_purge_page_caches();
    do_action('ghb_hub_page_published', $page_id);
}
