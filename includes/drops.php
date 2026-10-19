<?php
/**
 * Golden Hive — drops: a release date on the product.
 *
 * Product data → General → "Drop": the date and time it goes live, and
 * "Blocca l'acquisto fino al drop" (on by default). Until then:
 *
 *   • the product can't be bought (WooCommerce's is_purchasable, variations
 *     too), so a cached page or a direct add-to-cart can't jump the gun;
 *   • its card says "In arrivo · 24 ott" (includes/add-to-cart.php) and
 *     Quick View says when (includes/product-carousel-shortcode.php);
 *   • the product page shows the release with a live countdown and
 *     "Ricordamelo": Google Calendar, or an .ics file for Apple Calendar,
 *     Outlook and phones (ghb_drop_ics, built in drops-core.php).
 *
 * At the release time Action Scheduler (WP-Cron without it) runs
 * ghb_drop_release: it empties the page caches, so cached "In arrivo" pages
 * go, and queues the size alerts for the sizes that just came out
 * (includes/size-alerts.php). A product page left open reloads itself when
 * its countdown ends.
 *
 * The Drop Countdown block can follow a product (its date, image, price and
 * link) and the Drop Calendar block lists the coming drops
 * (ghb_drop_upcoming).
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

const GHB_DROP_HOOK = 'ghb_drop_release';

/** UTC timestamp of a product's drop, 0 when it has none. */
function ghb_drop_ts(int $product_id): int
{
    return (int) get_post_meta($product_id, '_ghb_drop_ts', true);
}

/** The product (or a variation's parent) can't be bought yet. */
function ghb_drop_is_locked(int $product_id): bool
{
    $ts = ghb_drop_ts($product_id);
    return $ts > time() && 'no' !== get_post_meta($product_id, '_ghb_drop_lock', true);
}

/** "In arrivo · 24 ott" for cards. */
function ghb_drop_card_label(int $ts): string
{
    return sprintf(__('In arrivo · %s', 'golden-hive-blocks'), wp_date('j M', $ts));
}

/** "venerdì 24 ottobre · ore 10:00", in the site's language and time zone. */
function ghb_drop_long_date(int $ts): string
{
    return wp_date('l j F', $ts) . ' · ' . sprintf(__('ore %s', 'golden-hive-blocks'), wp_date('H:i', $ts));
}

/* -------------------------------------------------------------------- *
 * Product data
 * -------------------------------------------------------------------- */

add_action('woocommerce_product_options_general_product_data', 'ghb_drop_product_fields');
function ghb_drop_product_fields()
{
    global $post;
    $id = $post ? (int) $post->ID : 0;
    $ts = $id ? ghb_drop_ts($id) : 0;

    echo '<div class="options_group ghb-drop-fields">';
    woocommerce_wp_text_input(array(
        'id'          => '_ghb_drop_at',
        'label'       => __('Drop: uscita', 'golden-hive-blocks'),
        'type'        => 'datetime-local',
        'value'       => $ts ? wp_date('Y-m-d\TH:i', $ts) : '',
        'desc_tip'    => true,
        'description' => __('Data e ora (fuso del sito) in cui il prodotto esce. Le schede mostrano "In arrivo", la pagina prodotto il conto alla rovescia e "Ricordamelo"; il blocco Calendario drop lo elenca. Vuoto: nessun drop.', 'golden-hive-blocks'),
    ));
    woocommerce_wp_checkbox(array(
        'id'          => '_ghb_drop_lock',
        'label'       => __('Blocca fino al drop', 'golden-hive-blocks'),
        'value'       => $id && '' !== get_post_meta($id, '_ghb_drop_lock', true) ? get_post_meta($id, '_ghb_drop_lock', true) : 'yes',
        'description' => __('Non acquistabile prima dell\'uscita (consigliato).', 'golden-hive-blocks'),
    ));
    echo '</div>';
}

add_action('woocommerce_admin_process_product_object', 'ghb_drop_save_product');
function ghb_drop_save_product(WC_Product $product)
{
    // WooCommerce has checked its own nonce before this hook runs.
    $raw = isset($_POST['_ghb_drop_at']) ? sanitize_text_field(wp_unslash($_POST['_ghb_drop_at'])) : '';
    $ts  = 0;
    if ('' !== $raw) {
        $date = date_create_immutable_from_format('Y-m-d\TH:i', $raw, wp_timezone());
        $ts   = $date ? $date->getTimestamp() : 0;
    }

    if ($ts) {
        $product->update_meta_data('_ghb_drop_ts', $ts);
    } else {
        $product->delete_meta_data('_ghb_drop_ts');
    }
    $product->update_meta_data('_ghb_drop_lock', isset($_POST['_ghb_drop_lock']) ? 'yes' : 'no');

    if ($product->get_id()) {
        ghb_drop_schedule_release($product->get_id(), $ts);
    }
}

/** One release action per product, at its drop time (none for a past or no drop). */
function ghb_drop_schedule_release(int $product_id, int $ts): void
{
    $args = array('product_id' => $product_id);
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions(GHB_DROP_HOOK, $args, 'ghb');
        if ($ts > time()) {
            as_schedule_single_action($ts + 5, GHB_DROP_HOOK, $args, 'ghb');
        }
        return;
    }
    wp_clear_scheduled_hook(GHB_DROP_HOOK, array($product_id));
    if ($ts > time()) {
        wp_schedule_single_event($ts + 5, GHB_DROP_HOOK, array($product_id));
    }
}

add_action(GHB_DROP_HOOK, 'ghb_drop_release');
function ghb_drop_release($product_id)
{
    $product = wc_get_product((int) $product_id);
    if (!$product) {
        return;
    }
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product->get_id());
    }
    clean_post_cache($product->get_id());
    ghb_purge_page_caches();
    if (function_exists('ghb_size_alerts_record_product')) {
        ghb_size_alerts_record_product($product);
    }
    do_action('ghb_drop_released', $product->get_id());
}

/* -------------------------------------------------------------------- *
 * Not for sale before the drop
 * -------------------------------------------------------------------- */

add_filter('woocommerce_is_purchasable', 'ghb_drop_purchasable', 20, 2);
add_filter('woocommerce_variation_is_purchasable', 'ghb_drop_purchasable', 20, 2);
function ghb_drop_purchasable($purchasable, $product)
{
    if (!$purchasable || !$product instanceof WC_Product) {
        return $purchasable;
    }
    $id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
    return ghb_drop_is_locked($id) ? false : $purchasable;
}

/* -------------------------------------------------------------------- *
 * "Ricordamelo"
 * -------------------------------------------------------------------- */

/** The calendar event of a product's drop. */
function ghb_drop_event_for_product(WC_Product $product): array
{
    $ts = ghb_drop_ts($product->get_id());
    return array(
        'uid'   => 'drop-' . $product->get_id() . '-' . $ts . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
        'ts'    => $ts,
        'title' => sprintf(__('Drop: %s', 'golden-hive-blocks'), $product->get_name()),
        'url'   => $product->get_permalink(),
    );
}

/**
 * The .ics link. A product's drop is looked up by id; an event typed in a
 * block (no product) is signed, so the endpoint never serves a calendar
 * file with text someone else wrote.
 */
function ghb_drop_ics_url(array $event, int $product_id = 0): string
{
    $args = array('action' => 'ghb_drop_ics');
    if ($product_id) {
        $args['id'] = $product_id;
    } else {
        // Signed as the endpoint will read them back.
        $args['ts']    = (int) $event['ts'];
        $args['title'] = sanitize_text_field((string) $event['title']);
        $args['url']   = esc_url_raw((string) $event['url']);
        $args['sig']   = ghb_drop_ics_sig($args['ts'], $args['title'], $args['url']);
    }
    return add_query_arg(array_map('rawurlencode', $args), admin_url('admin-ajax.php'));
}

function ghb_drop_ics_sig(int $ts, string $title, string $url): string
{
    return substr(wp_hash($ts . '|' . $title . '|' . $url, 'nonce'), 0, 16);
}

add_action('wp_ajax_ghb_drop_ics', 'ghb_drop_ics_endpoint');
add_action('wp_ajax_nopriv_ghb_drop_ics', 'ghb_drop_ics_endpoint');
function ghb_drop_ics_endpoint()
{
    $id    = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $event = null;
    if ($id) {
        $product = wc_get_product($id);
        if ($product && 'publish' === $product->get_status() && ghb_drop_ts($id)) {
            $event = ghb_drop_event_for_product($product);
        }
    } else {
        $ts    = isset($_GET['ts']) ? absint($_GET['ts']) : 0;
        $title = isset($_GET['title']) && is_string($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '';
        $url   = isset($_GET['url']) && is_string($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : '';
        $sig   = isset($_GET['sig']) && is_string($_GET['sig']) ? $_GET['sig'] : '';
        if ($ts && '' !== $title && hash_equals(ghb_drop_ics_sig($ts, $title, $url), $sig)) {
            $event = array('uid' => 'drop-' . $ts . '-' . md5($title) . '@' . wp_parse_url(home_url(), PHP_URL_HOST), 'ts' => $ts, 'title' => $title, 'url' => $url);
        }
    }
    if (!$event) {
        status_header(404);
        exit;
    }

    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="drop-' . gmdate('Ymd', (int) $event['ts']) . '.ics"');
    echo ghb_drop_ics($event, time()); // Built and escaped in drops-core.php.
    exit;
}

/**
 * "Ricordamelo": a <details> menu, so it works without JavaScript —
 * Google Calendar or the .ics file.
 */
function ghb_drop_remind_html(array $event, string $ics_url, string $class = ''): string
{
    ob_start();
    ?>
    <details class="ghb-remind <?php echo esc_attr($class); ?>">
        <summary class="ghb-remind__btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span><?php esc_html_e('Ricordamelo', 'golden-hive-blocks'); ?></span>
        </summary>
        <div class="ghb-remind__menu">
            <a href="<?php echo esc_url(ghb_drop_google_url($event)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Google Calendar', 'golden-hive-blocks'); ?></a>
            <a href="<?php echo esc_url($ics_url); ?>"><?php esc_html_e('Apple, Outlook, altro (.ics)', 'golden-hive-blocks'); ?></a>
        </div>
    </details>
    <?php
    return (string) ob_get_clean();
}

/* -------------------------------------------------------------------- *
 * Product page
 * -------------------------------------------------------------------- */

add_action('woocommerce_single_product_summary', 'ghb_drop_product_panel', 25);
function ghb_drop_product_panel()
{
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $ts = ghb_drop_ts($product->get_id());
    if ($ts <= time()) {
        return;
    }
    wp_enqueue_script('ghb-live-blocks');
    $event  = ghb_drop_event_for_product($product);
    $locked = ghb_drop_is_locked($product->get_id());
    ?>
    <div class="ghb-drop-panel" data-ghb-drop="<?php echo (int) $ts; ?>"<?php echo $locked ? ' data-ghb-drop-reload' : ''; ?>>
        <div class="ghb-drop-panel__head">
            <span class="ghb-drop-panel__eyebrow"><?php esc_html_e('Drop', 'golden-hive-blocks'); ?></span>
            <span class="ghb-drop-panel__date"><?php echo esc_html(ghb_drop_long_date($ts)); ?></span>
        </div>
        <div class="ghb-drop-panel__timer" data-ghb-drop-timer role="timer" aria-label="<?php esc_attr_e('Tempo all\'uscita', 'golden-hive-blocks'); ?>">
            <span><b data-unit="d">00</b><small><?php esc_html_e('giorni', 'golden-hive-blocks'); ?></small></span>
            <span><b data-unit="h">00</b><small><?php esc_html_e('ore', 'golden-hive-blocks'); ?></small></span>
            <span><b data-unit="m">00</b><small><?php esc_html_e('min', 'golden-hive-blocks'); ?></small></span>
            <span><b data-unit="s">00</b><small><?php esc_html_e('sec', 'golden-hive-blocks'); ?></small></span>
        </div>
        <?php if ($locked) : ?>
            <p class="ghb-drop-panel__note"><?php esc_html_e('L\'acquisto si apre all\'uscita: questa pagina si aggiorna da sola.', 'golden-hive-blocks'); ?></p>
        <?php endif; ?>
        <?php echo ghb_drop_remind_html($event, ghb_drop_ics_url($event, $product->get_id())); // Escaped inside. ?>
    </div>
    <?php
}

/* -------------------------------------------------------------------- *
 * Coming drops (Drop Calendar block)
 * -------------------------------------------------------------------- */

/**
 * Products with a drop from $past_days days ago on, in date order.
 *
 * @return WC_Product[]
 */
function ghb_drop_upcoming(int $limit, int $past_days = 0): array
{
    $query = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, min(24, $limit)),
        'no_found_rows'  => true,
        'meta_key'       => '_ghb_drop_ts',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => array(array(
            'key'     => '_ghb_drop_ts',
            'value'   => time() - max(0, $past_days) * DAY_IN_SECONDS,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        )),
        'tax_query'      => array(array(
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => array('exclude-from-catalog'),
            'operator' => 'NOT IN',
        )),
    ));
    return array_values(array_filter(array_map('wc_get_product', $query->posts)));
}
