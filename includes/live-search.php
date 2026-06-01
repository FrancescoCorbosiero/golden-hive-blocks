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

/* ══════════════════════════════════════════════════════════════════
   LIVE SEARCH FILTERS — scope & configure the as-you-type query
   ══════════════════════════════════════════════════════════════════ */

// Scope the live search to published products.
add_filter('relevanssi_live_search_query_args', function ($args) {
    $args['post_type']   = 'product';
    $args['post_status'] = 'publish';
    return $args;
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
   ASSETS — modal styles + behaviour (plain front-end files)
   ══════════════════════════════════════════════════════════════════ */

add_action('wp_enqueue_scripts', 'ghb_live_search_enqueue_assets');
function ghb_live_search_enqueue_assets()
{
    wp_enqueue_style(
        'golden-hive-live-search',
        GOLDEN_HIVE_BLOCKS_URL . 'live-search.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'golden-hive-live-search',
        GOLDEN_HIVE_BLOCKS_URL . 'js/live-search.js',
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
        array('triggerSelector' => $trigger_selector)
    );
}

/* ══════════════════════════════════════════════════════════════════
   MARKUP — server-rendered so the live-search JS can bind on init
   ══════════════════════════════════════════════════════════════════ */

add_action('wp_footer', 'ghb_live_search_render_modal', 99);
function ghb_live_search_render_modal()
{
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
            <div id="rlv-modal-results" class="rlv-results" aria-live="polite"></div>
            <div class="rlv-panel-footer">
                <button type="submit" class="rlv-seeall">Visualizza tutti i risultati</button>
            </div>
        </form>
    </div>
</div>
    <?php
}
