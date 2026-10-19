<?php
/**
 * Golden Hive — social proof from real orders.
 *
 * The "Qualcuno ha appena acquistato" toast (blocks/social-proof) used to
 * cycle purchases typed into the editor, with a fixed "1 ora fa" and an
 * "Acquisto verificato" badge. It now shows the store's latest paid orders:
 *
 *   • the feed (ghb_sp_feed): orders in Processing or Completed from the
 *     last N days, one line per order — the product (name, image, link), the
 *     shipping city and when it was paid. Never a name, an email or a total;
 *   • served by a public, read-only endpoint (ghb_social_proof) that js/
 *     animations.js fetches, so a cached page still shows today's orders;
 *     cached for 10 minutes and dropped whenever an order changes status;
 *   • the times are worked out in the browser from the payment timestamp,
 *     so "2 ore fa" stays true however old the page is.
 *
 * Also here: "Venduto 3 volte negli ultimi 30 giorni" under a product's
 * price, counted from WooCommerce's order lookup tables (Customizer → Golden
 * Hive — Pagina prodotto).
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Furthest back the feed reaches, and most purchases it returns. */
const GHB_SP_MAX_DAYS  = 30;
const GHB_SP_MAX_ITEMS = 20;

/**
 * The latest paid orders, newest first: product, image, url, city, ts.
 *
 * @return array<int,array{product:string,image:string,url:string,city:string,ts:int}>
 */
function ghb_sp_feed(int $days, int $limit, bool $with_city): array
{
    $days  = max(1, min(GHB_SP_MAX_DAYS, $days));
    $limit = max(1, min(GHB_SP_MAX_ITEMS, $limit));

    // The generation moves on whenever an order changes status, so a new
    // purchase shows up at once instead of after the cache expires.
    $generation = (int) get_option('ghb_sp_generation', 0);
    $key        = 'ghb_sp_' . md5($generation . '|' . $days . '|' . $limit . '|' . (int) $with_city);
    $cached     = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }

    $items = array();
    if (function_exists('wc_get_orders')) {
        $orders = wc_get_orders(array(
            'type'         => 'shop_order',
            'status'       => array('wc-processing', 'wc-completed'),
            'date_created' => '>' . (time() - $days * DAY_IN_SECONDS),
            'orderby'      => 'date',
            'order'        => 'DESC',
            // Some orders hold nothing showable (a deleted product).
            'limit'        => $limit * 2,
        ));
        foreach ($orders as $order) {
            if (count($items) >= $limit) {
                break;
            }
            $item = $order instanceof WC_Order ? ghb_sp_order_item($order, $with_city) : null;
            if ($item) {
                $items[] = $item;
            }
        }
    }

    set_transient($key, $items, 10 * MINUTE_IN_SECONDS);
    return $items;
}

/** One order as a toast: its first product that still has a public page. */
function ghb_sp_order_item(WC_Order $order, bool $with_city): ?array
{
    foreach ($order->get_items() as $line) {
        if (!$line instanceof WC_Order_Item_Product) {
            continue;
        }
        $product = $line->get_product();
        if (!$product) {
            continue;
        }
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : $product;
        if (!$parent || 'publish' !== $parent->get_status() || 'hidden' === $parent->get_catalog_visibility()) {
            continue;
        }

        $paid = $order->get_date_paid() ?: $order->get_date_created();
        return array(
            'product' => $parent->get_name(),
            'image'   => (string) (wp_get_attachment_image_url($parent->get_image_id(), 'thumbnail') ?: ''),
            'url'     => $parent->get_permalink(),
            'city'    => $with_city ? ghb_sp_city($order->get_shipping_city() ?: $order->get_billing_city()) : '',
            'ts'      => $paid ? $paid->getTimestamp() : 0,
        );
    }
    return null;
}

/** Any status change (paid, completed, refunded, cancelled) refreshes the feed. */
add_action('woocommerce_order_status_changed', 'ghb_sp_bump_generation');
function ghb_sp_bump_generation()
{
    update_option('ghb_sp_generation', (int) get_option('ghb_sp_generation', 0) + 1);
}

/**
 * The feed for the toast. Public and nonce-less like the plugin's other
 * read-only endpoints, so a cached page can call it; the browser may keep
 * the answer for a few minutes.
 */
add_action('wp_ajax_ghb_social_proof', 'ghb_sp_ajax');
add_action('wp_ajax_nopriv_ghb_social_proof', 'ghb_sp_ajax');
function ghb_sp_ajax()
{
    if (!apply_filters('ghb_social_proof_enabled', true)) {
        wp_send_json_success(array('items' => array()));
    }

    $days  = isset($_GET['days']) ? absint($_GET['days']) : 14;
    $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 10;
    $city  = !isset($_GET['city']) || '0' !== $_GET['city'];

    header('Cache-Control: public, max-age=300');
    wp_send_json_success(array('items' => ghb_sp_feed($days, $limit, $city)));
}

/* -------------------------------------------------------------------- *
 * "Venduto 3 volte negli ultimi 30 giorni" on the product page
 * -------------------------------------------------------------------- */

/** WooCommerce's analytics lookup tables exist (WooCommerce 4.0+). */
function ghb_sp_lookup_ready(): bool
{
    static $ready = null;
    if (null !== $ready) {
        return $ready;
    }
    $cached = get_transient('ghb_sp_lookup_ready');
    if (false !== $cached) {
        return $ready = ('yes' === $cached);
    }

    global $wpdb;
    $ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_order_product_lookup')) !== null
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_order_stats')) !== null;
    set_transient('ghb_sp_lookup_ready', $ready ? 'yes' : 'no', DAY_IN_SECONDS);
    return $ready;
}

/** Paid orders of this product (any size) in the last $days days. */
function ghb_sp_sold_recently(int $product_id, int $days = 30): int
{
    $key    = 'ghb_sold_' . $product_id . '_' . $days;
    $cached = get_transient($key);
    if (false !== $cached) {
        return (int) $cached;
    }
    if (!ghb_sp_lookup_ready()) {
        return 0;
    }

    global $wpdb;
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT l.order_id)
         FROM {$wpdb->prefix}wc_order_product_lookup l
         INNER JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = l.order_id
         WHERE l.product_id = %d
           AND s.status IN ('wc-processing', 'wc-completed')
           AND s.date_created >= %s",
        $product_id,
        // date_created is the site's local time.
        wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)
    ));

    set_transient($key, $count, 6 * HOUR_IN_SECONDS);
    return $count;
}

add_action('woocommerce_single_product_summary', 'ghb_sp_sold_count_line', 11);
function ghb_sp_sold_count_line()
{
    global $product;
    if (!$product instanceof WC_Product || 'no' === get_option('ghb_pp_sold_count', 'yes')) {
        return;
    }

    $days  = 30;
    $count = ghb_sp_sold_recently($product->get_id(), $days);
    // One sale says little; from two on it shows demand.
    if ($count < (int) apply_filters('ghb_sold_count_min', 2)) {
        return;
    }

    printf(
        '<p class="ghb-sold-count"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>%s</p>',
        esc_html(sprintf(
            /* translators: 1: times sold, 2: days */
            __('Venduto %1$d volte negli ultimi %2$d giorni', 'golden-hive-blocks'),
            $count,
            $days
        ))
    );
}
