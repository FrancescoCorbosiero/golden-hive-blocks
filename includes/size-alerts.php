<?php
/**
 * Golden Hive — size alerts: "È arrivato un paio nella tua taglia".
 *
 * Signup: the newsletter block (and the "Nella tua taglia" rail, when a size
 * has nothing in stock) can ask for up to three sizes, optionally brands.
 * The subscriber (the newsletter's ghb_subscriber post) gets them as
 * *pending* and an email to confirm: alerts go only to confirmed addresses
 * (double opt-in), and every alert carries a link to stop them. Confirm /
 * stop run on admin-ajax.php (ghb_alerts), which page caches leave alone;
 * opening a link shows a button and only the button (a POST) acts, so mail
 * scanners that open every link change nothing. Mail providers' one-click
 * unsubscribe (RFC 8058) POSTs straight to it.
 *
 * Arrivals: a size counts as arrived when it comes into stock on a published
 * product (a variation's or simple product's stock status turns "instock")
 * or when a product with that size in stock is published — and when a drop
 * is released (includes/drops.php). Each arrival waits in a short queue
 * (ghb_size_arrivals, 7 days); 20 minutes after the first one, a run checks
 * every arrival is still true (published, visible, that size still in stock)
 * and sends each confirmed subscriber one digest of their matches
 * (size-alerts-core.php), through WooCommerce's email template. At most one
 * email per subscriber every 12 hours and 40 emails per run (the rest follow
 * in the next run), and never the same product and size twice.
 *
 * Action Scheduler (bundled with WooCommerce) runs the queue; WP-Cron where
 * it is missing.
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

const GHB_ALERTS_MAX_SIZES = 3;
const GHB_ALERTS_COOLDOWN  = 12 * HOUR_IN_SECONDS;
const GHB_ALERTS_BATCH     = 40;
const GHB_ALERTS_KEEP      = 7 * DAY_IN_SECONDS;
const GHB_ALERTS_HOOK      = 'ghb_size_alerts_run';

/** Size alerts on/off (default on): add_filter('ghb_size_alerts', '__return_false'). */
function ghb_size_alerts_enabled(): bool
{
    return (bool) apply_filters('ghb_size_alerts', class_exists('WooCommerce'));
}

/* -------------------------------------------------------------------- *
 * Signup and consent
 * -------------------------------------------------------------------- */

/** Size keys the store actually carries, at most three. */
function ghb_size_alerts_clean_sizes(array $raw): array
{
    $known = array();
    foreach (function_exists('ghb_size_options') ? ghb_size_options() : array() as $option) {
        $known[$option['key']] = true;
    }
    $keys = array();
    foreach ($raw as $value) {
        $key = is_string($value) ? ghb_normalize_size_key($value) : '';
        if ('' !== $key && isset($known[$key])) {
            $keys[] = $key;
        }
    }
    return array_slice(array_values(array_unique($keys)), 0, GHB_ALERTS_MAX_SIZES);
}

/** Brand slugs among the ones the block offers. */
function ghb_size_alerts_clean_brands(array $raw): array
{
    $known  = wp_list_pluck(ghb_size_alerts_brand_options(), 'slug');
    $brands = array();
    foreach ($raw as $value) {
        $slug = is_string($value) ? sanitize_title($value) : '';
        if ('' !== $slug && in_array($slug, $known, true)) {
            $brands[] = $slug;
        }
    }
    return array_values(array_unique($brands));
}

/** "42", "42 e 43", "40, 42 e 43". */
function ghb_size_alerts_labels(array $keys): string
{
    $labels = array();
    foreach ($keys as $key) {
        $option   = function_exists('ghb_size_option') ? ghb_size_option($key) : null;
        $labels[] = $option ? $option['label'] : $key;
    }
    if (count($labels) < 2) {
        return (string) ($labels[0] ?? '');
    }
    $last = array_pop($labels);
    return implode(', ', $labels) . ' e ' . $last;
}

/**
 * Store a signup's sizes as pending and ask the address to confirm them.
 * Returns the message for the shopper, '' when no usable size was sent.
 */
function ghb_size_alerts_signup(int $subscriber_id, string $email, array $sizes, array $brands): string
{
    if (!ghb_size_alerts_enabled()) {
        return '';
    }
    $keys = ghb_size_alerts_clean_sizes($sizes);
    if (!$keys) {
        return '';
    }

    update_post_meta($subscriber_id, '_ghb_sizes_pending', $keys);
    update_post_meta($subscriber_id, '_ghb_brands_pending', ghb_size_alerts_clean_brands($brands));
    $token = ghb_size_alerts_token($subscriber_id);
    if ('on' !== get_post_meta($subscriber_id, '_ghb_alerts', true)) {
        update_post_meta($subscriber_id, '_ghb_alerts', 'pending');
    }

    // One confirmation email per address every 10 minutes.
    $last = (int) get_post_meta($subscriber_id, '_ghb_confirm_sent_at', true);
    if (time() - $last > 10 * MINUTE_IN_SECONDS) {
        ghb_size_alerts_send_confirmation($email, $token, $keys);
        update_post_meta($subscriber_id, '_ghb_confirm_sent_at', time());
    }

    return sprintf(
        /* translators: %s: sizes */
        __('Ci siamo quasi: conferma dalla tua email gli avvisi per la taglia %s.', 'golden-hive-blocks'),
        ghb_size_alerts_labels($keys)
    );
}

function ghb_size_alerts_token(int $subscriber_id): string
{
    $token = (string) get_post_meta($subscriber_id, '_ghb_token', true);
    if ('' === $token) {
        $token = wp_generate_password(32, false, false);
        update_post_meta($subscriber_id, '_ghb_token', $token);
    }
    return $token;
}

function ghb_size_alerts_link(string $do, string $token): string
{
    return add_query_arg(array('action' => 'ghb_alerts', 'do' => $do, 't' => $token), admin_url('admin-ajax.php'));
}

/** Confirm or stop alerts from an email link. */
add_action('wp_ajax_ghb_alerts', 'ghb_size_alerts_link_handler');
add_action('wp_ajax_nopriv_ghb_alerts', 'ghb_size_alerts_link_handler');
function ghb_size_alerts_link_handler()
{
    $do    = isset($_GET['do']) && is_string($_GET['do']) ? sanitize_key($_GET['do']) : '';
    $token = isset($_GET['t']) && is_string($_GET['t']) ? preg_replace('/[^A-Za-z0-9]/', '', $_GET['t']) : '';

    $found = '' === $token ? array() : get_posts(array(
        'post_type'      => 'ghb_subscriber',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_key'       => '_ghb_token',
        'meta_value'     => $token,
    ));
    if (!$found || !in_array($do, array('confirm', 'off'), true)) {
        ghb_size_alerts_page(__('Link non valido', 'golden-hive-blocks'), __('Questo link è scaduto o non è completo. Iscriviti di nuovo dal sito per ricevere gli avvisi.', 'golden-hive-blocks'));
    }
    $id = (int) $found[0];

    // Opening the link only asks; the button (POST) acts.
    if ('POST' !== strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
        if ('off' === $do) {
            ghb_size_alerts_page(
                __('Disattivare gli avvisi?', 'golden-hive-blocks'),
                __('Non riceverai più le email quando arriva la tua taglia.', 'golden-hive-blocks'),
                __('Disattiva gli avvisi', 'golden-hive-blocks')
            );
        }
        $pending = array_filter((array) get_post_meta($id, '_ghb_sizes_pending', true));
        ghb_size_alerts_page(
            __('Conferma gli avvisi', 'golden-hive-blocks'),
            $pending
                ? sprintf(
                    /* translators: %s: sizes */
                    __('Ti scriviamo quando arriva un paio nella taglia %s.', 'golden-hive-blocks'),
                    ghb_size_alerts_labels($pending)
                )
                : __('Ti scriviamo quando arriva un paio nella tua taglia.', 'golden-hive-blocks'),
            __('Conferma gli avvisi', 'golden-hive-blocks')
        );
    }

    if ('off' === $do) {
        update_post_meta($id, '_ghb_alerts', 'off');
        delete_post_meta($id, '_ghb_sizes');
        delete_post_meta($id, '_ghb_sizes_pending');
        ghb_size_alerts_page(__('Avvisi disattivati', 'golden-hive-blocks'), __('Non ti scriveremo più quando arriva la tua taglia. Puoi riattivarli quando vuoi dal sito.', 'golden-hive-blocks'));
    }

    $pending = (array) get_post_meta($id, '_ghb_sizes_pending', true);
    $sizes   = ghb_alerts_merge_sizes((array) get_post_meta($id, '_ghb_sizes', true), array_filter($pending), GHB_ALERTS_MAX_SIZES);
    if (!$sizes) {
        ghb_size_alerts_page(__('Nessuna taglia da confermare', 'golden-hive-blocks'), __('Scegli la tua taglia dal sito per ricevere gli avvisi.', 'golden-hive-blocks'));
    }
    $brands = array_filter((array) get_post_meta($id, '_ghb_brands_pending', true));

    update_post_meta($id, '_ghb_sizes', $sizes);
    if ($brands) {
        update_post_meta($id, '_ghb_brands', $brands);
    }
    delete_post_meta($id, '_ghb_sizes_pending');
    delete_post_meta($id, '_ghb_brands_pending');
    update_post_meta($id, '_ghb_alerts', 'on');
    update_post_meta($id, '_ghb_confirmed_at', time());

    ghb_size_alerts_page(
        __('Avvisi attivati', 'golden-hive-blocks'),
        sprintf(
            /* translators: %s: sizes */
            __('Ti scriviamo quando arriva un paio nella taglia %s. Ogni email ha il link per disattivare gli avvisi.', 'golden-hive-blocks'),
            ghb_size_alerts_labels($sizes)
        )
    );
}

/**
 * A small standalone page for the email links (admin-ajax prints no theme).
 * With $button, the page holds a form that POSTs back to the same link.
 */
function ghb_size_alerts_page(string $title, string $text, string $button = ''): void
{
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html($title . ' — ' . get_bloginfo('name')); ?></title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f5;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#171717}
main{max-width:440px;margin:24px;padding:36px 28px;border-radius:16px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.08);text-align:center}
.brand{margin:0 0 18px;color:#721124;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
h1{margin:0 0 10px;font-size:24px}
p{margin:0 0 22px;color:#525252;line-height:1.55}
a,button{display:inline-block;padding:12px 22px;border:0;border-radius:8px;background:#721124;color:#fff;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
form{margin:0 0 14px}
form+a{background:none;color:#525252;font-weight:600;text-decoration:underline}
</style>
</head>
<body>
<main>
<p class="brand"><?php echo esc_html(get_bloginfo('name')); ?></p>
<h1><?php echo esc_html($title); ?></h1>
<p><?php echo esc_html($text); ?></p>
<?php if ('' !== $button) : ?>
<form method="post" action="<?php echo esc_url(add_query_arg(array())); ?>">
<button type="submit"><?php echo esc_html($button); ?></button>
</form>
<?php endif; ?>
<a href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Vai al negozio', 'golden-hive-blocks'); ?></a>
</main>
</body>
</html>
    <?php
    exit;
}

/* -------------------------------------------------------------------- *
 * Emails
 * -------------------------------------------------------------------- */

/** HTML email in WooCommerce's template (Settings → Email), or plain wp_mail. */
function ghb_size_alerts_mail(string $to, string $subject, string $heading, string $body, string $unsubscribe = ''): bool
{
    $headers = "Content-Type: text/html; charset=UTF-8\r\n";
    if ('' !== $unsubscribe) {
        // One-click unsubscribe (RFC 8058): the mail provider POSTs to the link.
        $headers .= 'List-Unsubscribe: <' . esc_url_raw($unsubscribe) . ">\r\n";
        $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
    }
    if (function_exists('WC') && WC()->mailer()) {
        $mailer = WC()->mailer();
        return (bool) $mailer->send($to, $subject, $mailer->wrap_message($heading, $body), $headers);
    }
    return (bool) wp_mail($to, $subject, $body, $headers);
}

function ghb_size_alerts_button(string $url, string $label): string
{
    return sprintf(
        '<p style="margin:24px 0;"><a href="%s" style="display:inline-block;padding:12px 22px;border-radius:6px;background:#721124;color:#ffffff;font-weight:700;text-decoration:none;">%s</a></p>',
        esc_url($url),
        esc_html($label)
    );
}

function ghb_size_alerts_send_confirmation(string $email, string $token, array $keys): bool
{
    $body = '<p>' . sprintf(
        /* translators: %s: sizes */
        esc_html__('Hai chiesto di ricevere un\'email quando arriva un paio nella taglia %s.', 'golden-hive-blocks'),
        '<strong>' . esc_html(ghb_size_alerts_labels($keys)) . '</strong>'
    ) . '</p>';
    $body .= ghb_size_alerts_button(ghb_size_alerts_link('confirm', $token), __('Conferma gli avvisi', 'golden-hive-blocks'));
    $body .= '<p style="color:#737373;font-size:13px;">' . esc_html__('Se non sei stato tu, ignora questa email: senza conferma non ti scriveremo.', 'golden-hive-blocks') . '</p>';

    return ghb_size_alerts_mail(
        $email,
        __('Conferma gli avvisi per la tua taglia', 'golden-hive-blocks'),
        __('Un ultimo passo', 'golden-hive-blocks'),
        $body
    );
}

/**
 * One digest: the products that arrived in the subscriber's sizes (the first
 * six shown), a link to the shop filtered on those sizes, the way out.
 *
 * @param array<int,string[]> $picks Product id => size keys.
 */
function ghb_size_alerts_send_digest(int $subscriber_id, array $picks): bool
{
    $email = (string) get_post_meta($subscriber_id, '_ghb_email', true);
    if (!is_email($email) || !$picks) {
        return false;
    }

    $keys = array();
    foreach ($picks as $pick_keys) {
        $keys = array_merge($keys, $pick_keys);
    }
    $keys  = array_values(array_unique($keys));
    $count = count($picks);
    $label = ghb_size_alerts_labels($keys);

    $rows = '';
    foreach (array_slice($picks, 0, 6, true) as $pid => $pick_keys) {
        $product = wc_get_product($pid);
        if (!$product) {
            continue;
        }
        $line  = ghb_size_alerts_size_line($product, $pick_keys);
        $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
        $rows .= '<tr>'
            . '<td style="padding:10px 12px 10px 0;width:96px;vertical-align:middle;">'
            . ($image ? '<img src="' . esc_url($image) . '" width="96" height="96" alt="" style="display:block;width:96px;height:96px;object-fit:contain;border-radius:8px;background:#f5f5f5;">' : '')
            . '</td><td style="padding:10px 0;vertical-align:middle;">'
            . '<strong style="display:block;color:#171717;font-size:15px;">' . esc_html($product->get_name()) . '</strong>'
            . '<span style="display:block;margin:4px 0 8px;color:#721124;font-weight:700;">' . esc_html($line) . '</span>'
            . '<a href="' . esc_url($product->get_permalink()) . '" style="color:#171717;font-weight:700;">' . esc_html__('Vedi il prodotto', 'golden-hive-blocks') . ' &rarr;</a>'
            . '</td></tr>';
    }
    if ('' === $rows) {
        return false;
    }

    $token = ghb_size_alerts_token($subscriber_id);
    $off   = ghb_size_alerts_link('off', $token);

    $body  = '<p>' . sprintf(
        /* translators: %s: sizes */
        esc_html__('Sono appena arrivati, disponibili nella taglia %s:', 'golden-hive-blocks'),
        '<strong>' . esc_html($label) . '</strong>'
    ) . '</p>';
    $body .= '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">' . $rows . '</table>';
    $body .= ghb_size_alerts_button(ghb_size_alerts_shop_url($keys), $count > 6
        ? sprintf(__('Vedi tutti i %d nella tua taglia', 'golden-hive-blocks'), $count)
        : __('Vedi tutto nella tua taglia', 'golden-hive-blocks'));
    $body .= '<p style="color:#737373;font-size:12px;">' . sprintf(
        /* translators: 1: sizes, 2: stop link */
        esc_html__('Ricevi questa email perché hai attivato gli avvisi per la taglia %1$s. %2$s', 'golden-hive-blocks'),
        esc_html($label),
        '<a href="' . esc_url($off) . '" style="color:#737373;">' . esc_html__('Disattiva gli avvisi', 'golden-hive-blocks') . '</a>'
    ) . '</p>';

    $subject = 1 === $count
        ? sprintf(__('È arrivato un paio nella tua taglia %s', 'golden-hive-blocks'), $label)
        : sprintf(__('%1$d nuovi arrivi nella tua taglia %2$s', 'golden-hive-blocks'), $count, $label);

    return ghb_size_alerts_mail($email, $subject, __('Nuovi arrivi nella tua taglia', 'golden-hive-blocks'), $body, $off);
}

/** "Taglia 42 · 180 €" for the size(s) a product arrived in. */
function ghb_size_alerts_size_line(WC_Product $product, array $keys): string
{
    if ($product->is_type('variable') && function_exists('ghb_atc_size_rows')) {
        $matches = ghb_size_match_pick(ghb_atc_size_rows($product), $keys);
        if ($matches) {
            return ghb_size_match_line($matches);
        }
    }
    $price = html_entity_decode(wp_strip_all_tags(wc_price(wc_get_price_to_display($product))));
    return sprintf(__('Taglia %1$s · %2$s', 'golden-hive-blocks'), ghb_size_alerts_labels($keys), $price);
}

/** The shop filtered on these sizes (every spelling of each). */
function ghb_size_alerts_shop_url(array $keys): string
{
    $slugs = array();
    foreach ($keys as $key) {
        $option = function_exists('ghb_size_option') ? ghb_size_option($key) : null;
        if ($option) {
            $slugs = array_merge($slugs, $option['slugs']);
        }
    }
    $shop  = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    $param = 'filter_' . preg_replace('/^pa_/', '', ghb_size_attribute());
    return $slugs ? add_query_arg(array($param => implode(',', $slugs), 'query_type_' . substr($param, 7) => 'or'), $shop) : $shop;
}

/* -------------------------------------------------------------------- *
 * Arrivals
 * -------------------------------------------------------------------- */

/** Size keys a product has in stock now. */
function ghb_size_alerts_product_keys(WC_Product $product): array
{
    $keys = array();
    if ($product->is_type('variable')) {
        if (function_exists('ghb_atc_size_rows')) {
            foreach (ghb_atc_size_rows($product) as $row) {
                if ($row['in_stock']) {
                    $keys[] = ghb_normalize_size_key($row['label']);
                }
            }
        }
    } elseif ($product->is_in_stock()) {
        foreach (wc_get_product_terms($product->get_id(), ghb_size_attribute(), array('fields' => 'names')) as $name) {
            $keys[] = ghb_normalize_size_key($name);
        }
    }
    return array_values(array_unique(array_filter($keys)));
}

/** Queue "this product arrived in these sizes", then make sure a run is due. */
function ghb_size_alerts_record(int $product_id, array $keys): void
{
    $keys = array_values(array_filter($keys));
    if (!$keys || !ghb_size_alerts_enabled()) {
        return;
    }
    // A drop that is not out yet arrives when it is released (includes/drops.php).
    if (function_exists('ghb_drop_is_locked') && ghb_drop_is_locked($product_id)) {
        return;
    }

    $queue = ghb_size_alerts_queue();
    foreach ($keys as $key) {
        $queue[$product_id . '|' . $key] = time();
    }
    arsort($queue);
    update_option('ghb_size_arrivals', array_slice($queue, 0, 500, true), false);

    ghb_size_alerts_schedule(20 * MINUTE_IN_SECONDS, false);
}

/** The queue, without arrivals older than a week: "productId|key" => when. */
function ghb_size_alerts_queue(): array
{
    $queue = get_option('ghb_size_arrivals', array());
    if (!is_array($queue)) {
        return array();
    }
    $since = time() - GHB_ALERTS_KEEP;
    return array_filter($queue, static function ($t) use ($since) {
        return (int) $t >= $since;
    });
}

/** All the in-stock sizes of a product (published, drop released). */
function ghb_size_alerts_record_product(WC_Product $product): void
{
    if ('publish' === $product->get_status()) {
        ghb_size_alerts_record($product->get_id(), ghb_size_alerts_product_keys($product));
    }
}

// A variation comes into stock: that one size arrived.
add_action('woocommerce_variation_set_stock_status', 'ghb_size_alerts_on_variation_stock', 10, 3);
function ghb_size_alerts_on_variation_stock($variation_id, $status, $variation = null)
{
    if ('instock' !== $status) {
        return;
    }
    $variation = $variation instanceof WC_Product ? $variation : wc_get_product($variation_id);
    if (!$variation) {
        return;
    }
    $parent_id = $variation->get_parent_id();
    if (!$parent_id || 'publish' !== get_post_status($parent_id)) {
        return;
    }
    $label = $variation->get_attribute(ghb_size_attribute());
    if ('' !== $label) {
        ghb_size_alerts_record($parent_id, array(ghb_normalize_size_key($label)));
    }
}

// A simple product comes into stock: its size(s) arrived.
add_action('woocommerce_product_set_stock_status', 'ghb_size_alerts_on_product_stock', 10, 3);
function ghb_size_alerts_on_product_stock($product_id, $status, $product = null)
{
    if ('instock' !== $status) {
        return;
    }
    $product = $product instanceof WC_Product ? $product : wc_get_product($product_id);
    if ($product && !$product->is_type('variable')) {
        ghb_size_alerts_record_product($product);
    }
}

// A product goes live: every size it has in stock arrived.
add_action('transition_post_status', 'ghb_size_alerts_on_publish', 20, 3);
function ghb_size_alerts_on_publish($new_status, $old_status, $post)
{
    if ('publish' !== $new_status || 'publish' === $old_status || !$post instanceof WP_Post || 'product' !== $post->post_type) {
        return;
    }
    $product = wc_get_product($post->ID);
    if ($product) {
        ghb_size_alerts_record_product($product);
    }
}

/* -------------------------------------------------------------------- *
 * Sending
 * -------------------------------------------------------------------- */

/**
 * Schedule a run. $force adds one even when a run is already due (a run
 * scheduling its own continuation sees itself as "due").
 */
function ghb_size_alerts_schedule(int $delay, bool $force): void
{
    if (function_exists('as_schedule_single_action')) {
        if ($force || false === as_next_scheduled_action(GHB_ALERTS_HOOK, array(), 'ghb')) {
            as_schedule_single_action(time() + $delay, GHB_ALERTS_HOOK, array(), 'ghb');
        }
        return;
    }
    if ($force || !wp_next_scheduled(GHB_ALERTS_HOOK)) {
        wp_schedule_single_event(time() + $delay, GHB_ALERTS_HOOK);
    }
}

/** Brand slugs of a product, parent brands included, across the brand taxonomies. */
function ghb_size_alerts_product_brands(int $product_id): array
{
    $slugs = array();
    foreach (ghb_size_alerts_brand_taxonomies() as $taxonomy) {
        $terms = get_the_terms($product_id, $taxonomy);
        if (!is_array($terms)) {
            continue;
        }
        foreach ($terms as $term) {
            $slugs[] = $term->slug;
            foreach (get_ancestors($term->term_id, $taxonomy, 'taxonomy') as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $taxonomy);
                if ($ancestor instanceof WP_Term) {
                    $slugs[] = $ancestor->slug;
                }
            }
        }
    }
    return array_values(array_unique($slugs));
}

/** The brand taxonomies this store registers (as the rails' brand filter). */
function ghb_size_alerts_brand_taxonomies(): array
{
    return array_values(array_filter(
        (array) apply_filters('ghb_size_alerts_brand_taxonomies', array('product_brand', 'pwb-brand', 'pa_brand', 'pa_marca')),
        'taxonomy_exists'
    ));
}

/** Top-level brands for the newsletter block, most stocked first (cached). */
function ghb_size_alerts_brand_options(int $limit = 10): array
{
    $cached = get_transient('ghb_alert_brands');
    if (is_array($cached)) {
        return array_slice($cached, 0, $limit);
    }
    $options    = array();
    $taxonomies = ghb_size_alerts_brand_taxonomies();
    if ($taxonomies) {
        $terms = get_terms(array(
            'taxonomy'   => $taxonomies[0],
            'parent'     => 0,
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 20,
        ));
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[] = array('slug' => $term->slug, 'name' => $term->name);
            }
        }
    }
    set_transient('ghb_alert_brands', $options, 12 * HOUR_IN_SECONDS);
    return array_slice($options, 0, $limit);
}

add_action(GHB_ALERTS_HOOK, 'ghb_size_alerts_run');
function ghb_size_alerts_run()
{
    $queue = ghb_size_alerts_queue();
    update_option('ghb_size_arrivals', $queue, false);
    if (!$queue || !ghb_size_alerts_enabled()) {
        return;
    }

    // Arrivals still true now: published, visible, released, size in stock.
    $by = array();
    foreach ($queue as $pair => $t) {
        list($pid, $key) = array_pad(explode('|', (string) $pair, 2), 2, '');
        $pid = (int) $pid;
        if (!$pid || '' === $key) {
            continue;
        }
        $by[$pid]['keys'][] = $key;
        $by[$pid]['t']      = max((int) ($by[$pid]['t'] ?? 0), (int) $t);
    }
    $live      = array();
    $brands_of = array();
    foreach ($by as $pid => $arrival) {
        $product = wc_get_product($pid);
        if (!$product || 'publish' !== $product->get_status() || 'hidden' === $product->get_catalog_visibility()
            || (function_exists('ghb_drop_is_locked') && ghb_drop_is_locked($pid))) {
            continue;
        }
        $keys = array_values(array_intersect($arrival['keys'], ghb_size_alerts_product_keys($product)));
        if ($keys) {
            $live[$pid]      = array('keys' => $keys, 't' => $arrival['t']);
            $brands_of[$pid] = ghb_size_alerts_product_brands($pid);
        }
    }
    if (!$live) {
        return;
    }

    $subscribers = get_posts(array(
        'post_type'      => 'ghb_subscriber',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'meta_key'       => '_ghb_alerts',
        'meta_value'     => 'on',
    ));

    $sent     = 0;
    $deferred = false;
    $more     = false;
    foreach ($subscribers as $sid) {
        $picks = ghb_alerts_pick(
            $live,
            array_filter((array) get_post_meta($sid, '_ghb_sizes', true)),
            array_filter((array) get_post_meta($sid, '_ghb_brands', true)),
            $brands_of,
            array_filter((array) get_post_meta($sid, '_ghb_alerted', true))
        );
        if (!$picks) {
            continue;
        }
        $last = (int) get_post_meta($sid, '_ghb_alert_sent_at', true);
        if ($last && time() - $last < GHB_ALERTS_COOLDOWN) {
            $deferred = true;
            continue;
        }
        if ($sent >= GHB_ALERTS_BATCH) {
            $more = true;
            break;
        }
        if (ghb_size_alerts_send_digest((int) $sid, $picks)) {
            $sent++;
            $told = (array) get_post_meta($sid, '_ghb_alerted', true);
            foreach ($picks as $pid => $keys) {
                foreach ($keys as $key) {
                    $told[] = $pid . '|' . $key;
                }
            }
            update_post_meta($sid, '_ghb_alerted', array_slice(array_values(array_unique(array_filter($told))), -300));
            update_post_meta($sid, '_ghb_alert_sent_at', time());
        }
    }

    if ($more) {
        ghb_size_alerts_schedule(10 * MINUTE_IN_SECONDS, true);
    } elseif ($deferred) {
        ghb_size_alerts_schedule(6 * HOUR_IN_SECONDS, true);
    }
}

/* -------------------------------------------------------------------- *
 * Admin: Strumenti → Iscritti newsletter
 * -------------------------------------------------------------------- */

add_filter('manage_ghb_subscriber_posts_columns', 'ghb_size_alerts_columns');
function ghb_size_alerts_columns($columns)
{
    $columns['ghb_sizes']  = __('Avvisi taglia', 'golden-hive-blocks');
    $columns['ghb_brands'] = __('Marchi', 'golden-hive-blocks');
    return $columns;
}

add_action('manage_ghb_subscriber_posts_custom_column', 'ghb_size_alerts_column', 10, 2);
function ghb_size_alerts_column($column, $post_id)
{
    if ('ghb_sizes' === $column) {
        $state = (string) get_post_meta($post_id, '_ghb_alerts', true);
        $sizes = 'pending' === $state
            ? (array) get_post_meta($post_id, '_ghb_sizes_pending', true)
            : (array) get_post_meta($post_id, '_ghb_sizes', true);
        $sizes = array_filter($sizes);
        if (!$sizes || 'off' === $state) {
            echo 'off' === $state ? esc_html__('Disattivati', 'golden-hive-blocks') : '—';
            return;
        }
        echo esc_html(ghb_size_alerts_labels($sizes));
        echo ' <em>(' . esc_html('on' === $state ? __('confermati', 'golden-hive-blocks') : __('da confermare', 'golden-hive-blocks')) . ')</em>';
    } elseif ('ghb_brands' === $column) {
        $brands = array_filter((array) get_post_meta($post_id, '_ghb_brands', true));
        echo $brands ? esc_html(implode(', ', $brands)) : '—';
    }
}
