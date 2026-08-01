<?php
/**
 * Golden Hive — Live Ajax Search modal (UI layer).
 *
 * Adds the UI for Relevanssi Live Ajax Search: a slide-in search modal, the
 * product result-card template the live-search plugin renders into it, and the
 * filters that scope/configure the live search. The search engine itself
 * (Relevanssi) and the as-you-type AJAX (Relevanssi Live Ajax Search) live in
 * their own plugins and are not touched here — this is purely the UI layer.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Is the Relevanssi Live Ajax Search plugin actually active? Checked
 * defensively against several of its symbols (class, bootstrap function,
 * version constant) so a plugin update renaming one of them doesn't break the
 * detection. When the plugin is missing we bail out of the enqueue, the modal
 * markup and the search hijack, so the theme's native search keeps working.
 */
function ghb_live_search_is_active()
{
    return class_exists('Relevanssi_Live_Search')
        || function_exists('relevanssi_live_search_init')
        || defined('RLV_VERSION');
}

/* ══════════════════════════════════════════════════════════════════
   LIVE SEARCH FILTERS — scope & configure the as-you-type query
   ══════════════════════════════════════════════════════════════════ */

// Scope the live search to published products.
add_filter('relevanssi_live_search_query_args', function ($args) {
    $args['post_type']   = 'product';
    $args['post_status'] = 'publish';
    return $args;
});

/*
 * Snappier as-you-type: the plugin's default engine config waits 500ms after
 * the last keystroke and wants 3+ characters before firing. 250ms + 2 chars
 * shaves a quarter second off EVERY search and lets short sneaker queries
 * ("aj", "af") work. Defensive merge: only the two knobs are touched, and the
 * filter is harmless if a plugin update renames the config keys.
 */
add_filter('relevanssi_live_search_configs', function ($configs) {
    if (is_array($configs)) {
        foreach ($configs as $key => $config) {
            if (is_array($config) && isset($config['input']) && is_array($config['input'])) {
                $configs[$key]['input']['delay']     = 250;
                $configs[$key]['input']['min_chars'] = 2;
            }
        }
    }
    return $configs;
});

// Products shown in the panel.
add_filter('relevanssi_live_search_posts_per_page', function () {
    return 6;
});

// Only OUR modal input is enhanced; leave the theme's other search forms as native WP search.
add_filter('relevanssi_live_search_hijack_get_search_form', '__return_false');

// Our CSS owns all styling — drop the plugin's two stylesheets.
add_filter('relevanssi_live_search_base_styles', '__return_false');
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('relevanssi-live-search');
}, 100);

// Load OUR result-card template from inside this plugin (absolute path, NOT the theme).
// This filter runs inside the plugin's locate_template(): it receives the resolved
// absolute path (string) and lets us swap in our bundled template.
add_filter('relevanssi_live_search_results_template', function ($template) {
    $custom = GOLDEN_HIVE_BLOCKS_PATH . 'templates/search-results.php';
    return file_exists($custom) ? $custom : $template;
});

/* ══════════════════════════════════════════════════════════════════
   PERFORMANCE — micro-cache + worker warmup for the live search AJAX
   ══════════════════════════════════════════════════════════════════ */

/**
 * Micro-cache for the live-search AJAX response.
 *
 * Every keystroke-search pays a full WordPress bootstrap through
 * admin-ajax.php plus a Relevanssi index query — typically the dominant cost
 * of the "slow drawer". Identical queries within the TTL (popular terms,
 * back-and-forth typing, other visitors searching the same thing) are served
 * straight from a transient before the search plugin even runs.
 *
 * Trade-off: a result card's price/stock can be up to TTL minutes stale
 * INSIDE THE DRAWER ONLY (product pages are always live). Tune or disable:
 *     add_filter('ghb_live_search_cache_ttl', fn() => 0);     // off
 *     add_filter('ghb_live_search_cache_ttl', fn() => 300);   // 5 min
 *
 * Implementation notes: hooked on admin_init (fires for every admin-ajax
 * request, before wp_ajax_* handlers) and keyed on the plugin's AJAX action —
 * name checked against the known SearchWP-fork spellings, filterable via
 * ghb_live_search_cache_actions. Cache misses capture the plugin's HTML
 * fragment with an output buffer callback that runs when the handler die()s.
 * Logged-in users bypass the cache entirely (admin bar, previews).
 */
function ghb_live_search_microcache()
{
    if (!wp_doing_ajax() || is_user_logged_in()) {
        return;
    }

    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    $known  = apply_filters('ghb_live_search_cache_actions', array(
        'relevanssi_live_search',
        'rlv_live_search',
        'searchwp_live_search',
    ));
    if (!in_array($action, $known, true)) {
        return;
    }

    $ttl = (int) apply_filters('ghb_live_search_cache_ttl', 10 * MINUTE_IN_SECONDS);
    if ($ttl <= 0) {
        return;
    }

    // The SearchWP fork family posts the term as rlvquery/swpquery; fall back
    // to s/q so a rename doesn't silently disable the cache.
    $query = '';
    foreach (array('rlvquery', 'swpquery', 's', 'q') as $param) {
        if (isset($_REQUEST[$param]) && $_REQUEST[$param] !== '') {
            $query = sanitize_text_field(wp_unslash($_REQUEST[$param]));
            break;
        }
    }
    if ($query === '') {
        return;
    }

    $key = 'ghb_ls_' . md5(mb_strtolower(trim($query)) . '|' . $action);

    $hit = get_transient($key);
    if (is_string($hit) && $hit !== '') {
        header('X-GHB-Live-Search-Cache: HIT');
        echo $hit; // Already-rendered HTML fragment, escaped at render time.
        wp_die();
    }

    header('X-GHB-Live-Search-Cache: MISS');
    ob_start(function ($buffer) use ($key, $ttl) {
        // Sanity bounds: never cache an empty error response or a runaway one.
        if (is_string($buffer) && $buffer !== '' && strlen($buffer) < 200000) {
            set_transient($key, $buffer, $ttl);
        }
        return $buffer;
    });
}
add_action('admin_init', 'ghb_live_search_microcache', 0);

/**
 * Warmup endpoint: opening the modal fires a throwaway request (see
 * js/live-search.js) so the TLS handshake, PHP worker, opcache and object
 * cache are all hot BEFORE the first real keystroke-search — shaving the
 * cold-start cost off the first query a visitor makes.
 */
function ghb_live_search_warm()
{
    wp_die('1');
}
add_action('wp_ajax_ghb_ls_warm', 'ghb_live_search_warm');
add_action('wp_ajax_nopriv_ghb_ls_warm', 'ghb_live_search_warm');

/* ══════════════════════════════════════════════════════════════════
   ASSETS — modal styles + behaviour (plain front-end files)
   ══════════════════════════════════════════════════════════════════ */

add_action('wp_enqueue_scripts', 'ghb_live_search_enqueue_assets');
function ghb_live_search_enqueue_assets()
{
    if (!ghb_live_search_is_active()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-live-search',
        gh_asset_url('live-search.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-live-search',
        gh_asset_url('js/live-search.js'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );

    // Theme search form(s) that should open our modal instead of behaving as a
    // plain inline field. Defaults to the header's WooCommerce search wrapper;
    // filterable so the target can change without editing JS. Leave it scoped
    // narrowly so the theme's other search forms stay as native WP search.
    $trigger_selector = apply_filters('ghb_live_search_trigger_selector', '.site-search');
    wp_localize_script(
        'golden-hive-live-search',
        'ghbLiveSearch',
        array(
            'triggerSelector' => $trigger_selector,
            'warmUrl'         => admin_url('admin-ajax.php?action=ghb_ls_warm'),
        )
    );
}

/* ══════════════════════════════════════════════════════════════════
   MARKUP — server-rendered so the live-search JS can bind on init
   ══════════════════════════════════════════════════════════════════ */

add_action('wp_footer', 'ghb_live_search_render_modal', 99);
function ghb_live_search_render_modal()
{
    if (!ghb_live_search_is_active()) {
        return;
    }
    ?>
<div id="rlv-search-modal" class="rlv-modal" role="dialog" aria-modal="true" aria-label="Cerca prodotti">
    <div class="rlv-backdrop" data-rlv-close></div>
    <div class="rlv-panel">
        <form class="rlv-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <input type="hidden" name="post_type" value="product">
            <div class="rlv-panel-header">
                <svg class="rlv-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <label for="rlv-input" class="rlv-sr-only">Cerca prodotti</label>
                <input id="rlv-input" class="rlv-input" type="search" name="s" placeholder="Cerca prodotti&hellip;"
                    autocomplete="off" data-rlvlive="true" data-rlvconfig="default" data-rlvparentel="#rlv-modal-results">
                <button type="button" class="rlv-close" data-rlv-close aria-label="Chiudi la ricerca">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="rlv-results-wrap">
                <?php /* Announced status: the visual status cards below live in an
                         aria-hidden overlay, so screen readers never heard them.
                         js/live-search.js mirrors "Ricerca in corso…" into this
                         live region while a search is in flight. */ ?>
                <span id="rlv-live-status" class="rlv-sr-only" role="status" aria-live="polite"></span>
                <div id="rlv-modal-results" class="rlv-results" aria-live="polite" aria-busy="false"></div>

                <?php /* State layer — sits over the results region; CSS reveals the right one.
                         Kept OUTSIDE #rlv-modal-results so the live-search plugin's DOM
                         writes (which replace that node's contents) never wipe it. */ ?>
                <div class="rlv-status" aria-hidden="true">
                    <div class="rlv-status-card rlv-status-idle">
                        <svg class="rlv-status-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <p class="rlv-status-text">Digita per cercare tra i nostri prodotti</p>
                    </div>
                    <div class="rlv-status-card rlv-status-loading">
                        <span class="rlv-spinner"></span>
                        <p class="rlv-status-text">Ricerca in corso&hellip;</p>
                    </div>
                </div>
                <span class="rlv-progress" aria-hidden="true"></span>
            </div>
            <div class="rlv-panel-footer">
                <button type="submit" class="rlv-seeall">Visualizza tutti i risultati</button>
            </div>
        </form>
    </div>
</div>
    <?php
}
