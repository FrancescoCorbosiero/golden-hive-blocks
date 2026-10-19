<?php
/**
 * Golden Hive — "Nella tua taglia": a product rail of the shopper's size.
 *
 *   [gh_size_rail]                          → title "Nella tua taglia", 12 products
 *   [gh_size_rail title="La tua misura" limit="8" columns="5"]
 *
 * The page stays cacheable: the shortcode prints the frame and a size
 * picker, and js/live-blocks.js fills the rail for the size this browser
 * remembers (localStorage, written by js/animations.js SizeMemory when the
 * shopper picks a size anywhere: shop filter, Quick View, Quick Add,
 * product page, this picker, the newsletter). The rail lists products with
 * that size in stock, newest first, and every card says "Taglia 42 · 180 €"
 * and opens Quick View / Quick Add on that size (includes/size-match.php).
 * No product in that size: the rail offers the newsletter's size alert.
 *
 * Sizes are grouped by physical size across the store's duplicate spellings
 * ("36 2/3" and "36-2-3" are one choice): ghb_size_options().
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------- *
 * Size options
 * -------------------------------------------------------------------- */

function ghb_size_attribute(): string
{
    return (string) apply_filters('ghb_atc_size_attribute', 'pa_taglia');
}

/**
 * Every size the catalogue uses (terms carried by at least one product),
 * grouped and sorted: key, label, slugs, count. Cached; any change to the
 * size terms drops the cache.
 *
 * @return array<int,array{key:string,label:string,slugs:string[],count:int}>
 */
function ghb_size_options(): array
{
    $cached = get_transient('ghb_size_options');
    if (is_array($cached)) {
        return $cached;
    }

    $taxonomy = ghb_size_attribute();
    if (!taxonomy_exists($taxonomy)) {
        return array();
    }
    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));
    if (is_wp_error($terms)) {
        return array();
    }

    $rows = array();
    foreach ($terms as $term) {
        $rows[] = array('slug' => $term->slug, 'name' => $term->name, 'count' => (int) $term->count);
    }
    $options = ghb_size_group_options($rows);

    set_transient('ghb_size_options', $options, 6 * HOUR_IN_SECONDS);
    return $options;
}

add_action('created_term', 'ghb_size_options_flush', 10, 3);
add_action('edited_term', 'ghb_size_options_flush', 10, 3);
add_action('delete_term', 'ghb_size_options_flush', 10, 3);
function ghb_size_options_flush($term_id, $tt_id, $taxonomy)
{
    if ($taxonomy === ghb_size_attribute()) {
        delete_transient('ghb_size_options');
    }
}

/** The option for a size key ("3623"), or null. */
function ghb_size_option(string $key): ?array
{
    foreach (ghb_size_options() as $option) {
        if ($option['key'] === $key) {
            return $option;
        }
    }
    return null;
}

/**
 * Published products with this size in stock: variable products through an
 * in-stock variation of one of the size's slugs (as the shop's size filter
 * matches), simple products through the size term.
 *
 * @param string[] $slugs
 * @return int[]
 */
function ghb_size_in_stock_product_ids(array $slugs): array
{
    $slugs = array_values(array_filter(array_map('strtolower', $slugs)));
    if (!$slugs) {
        return array();
    }

    global $wpdb;
    $taxonomy = ghb_size_attribute();
    $in       = implode(',', array_fill(0, count($slugs), '%s'));
    $parents  = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT v.post_parent
         FROM {$wpdb->posts} v
         INNER JOIN {$wpdb->posts} p ON p.ID = v.post_parent AND p.post_status = 'publish'
         INNER JOIN {$wpdb->postmeta} a ON a.post_id = v.ID AND a.meta_key = %s AND LOWER(a.meta_value) IN ($in)
         INNER JOIN {$wpdb->postmeta} s ON s.post_id = v.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock'
         WHERE v.post_type = 'product_variation' AND v.post_status = 'publish'",
        array_merge(array('attribute_' . $taxonomy), $slugs)
    ));

    $simple = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 200,
        'no_found_rows'  => true,
        'tax_query'      => array(
            'relation' => 'AND',
            array('taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'simple'),
            array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slugs),
        ),
        'meta_query'     => array(array('key' => '_stock_status', 'value' => 'instock')),
    ));

    return array_values(array_unique(array_map('intval', array_merge($parents, $simple))));
}

/* -------------------------------------------------------------------- *
 * Shortcode
 * -------------------------------------------------------------------- */

add_shortcode('gh_size_rail', 'ghb_size_rail_shortcode');
function ghb_size_rail_shortcode($atts)
{
    if (!class_exists('WooCommerce')) {
        return '';
    }

    $atts = shortcode_atts(
        array(
            'title'   => __('Nella tua taglia', 'golden-hive-blocks'),
            'text'    => __('Scegli la tua taglia: ti mostriamo solo i paia disponibili nella tua misura.', 'golden-hive-blocks'),
            'limit'   => 12,
            'columns' => 4,
        ),
        $atts,
        'gh_size_rail'
    );

    $options = ghb_size_options();
    if (!$options) {
        return '';
    }

    wp_enqueue_script('ghb-live-blocks');

    $src = add_query_arg(
        array(
            'action'  => 'ghb_size_rail',
            'limit'   => max(1, min(24, (int) $atts['limit'])),
            'columns' => max(1, min(8, (int) $atts['columns'])),
        ),
        admin_url('admin-ajax.php')
    );

    ob_start();
    ?>
    <section class="ghb-size-rail" data-ghb-size-rail data-src="<?php echo esc_url($src); ?>" data-gh-reveal="up">
        <div class="ghb-size-rail__head">
            <h2 class="gh-rail__title ghb-size-rail__title">
                <?php echo esc_html($atts['title']); ?>
                <span class="ghb-size-rail__current" data-ghb-size-current hidden></span>
            </h2>
            <button type="button" class="ghb-size-rail__change" data-ghb-size-change hidden><?php esc_html_e('Cambia taglia', 'golden-hive-blocks'); ?></button>
        </div>

        <div class="ghb-size-rail__picker" data-ghb-size-picker>
            <?php if ('' !== $atts['text']) : ?>
                <p class="ghb-size-rail__text"><?php echo esc_html($atts['text']); ?></p>
            <?php endif; ?>
            <div class="ghb-size-rail__chips" role="group" aria-label="<?php esc_attr_e('La tua taglia', 'golden-hive-blocks'); ?>">
                <?php foreach ($options as $option) : ?>
                    <button type="button" class="ghb-size-rail__chip"
                            data-ghb-size-key="<?php echo esc_attr($option['key']); ?>"
                            data-ghb-size-label="<?php echo esc_attr($option['label']); ?>"><?php echo esc_html($option['label']); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ghb-size-rail__body" data-ghb-size-body aria-live="polite"></div>
    </section>
    <?php
    return (string) ob_get_clean();
}

/* -------------------------------------------------------------------- *
 * AJAX rail
 * -------------------------------------------------------------------- */

/**
 * The rail for one size. Public catalogue data and no nonce, like the
 * plugin's other read-only endpoints, so a cached page can call it.
 */
add_action('wp_ajax_ghb_size_rail', 'ghb_size_rail_ajax');
add_action('wp_ajax_nopriv_ghb_size_rail', 'ghb_size_rail_ajax');
function ghb_size_rail_ajax()
{
    $key    = isset($_GET['size']) && is_string($_GET['size']) ? ghb_normalize_size_key(wp_unslash($_GET['size'])) : '';
    $option = '' !== $key ? ghb_size_option($key) : null;
    if (!$option || !function_exists('ghb_product_rail_render')) {
        wp_send_json_error(array('message' => 'size'), 404);
    }

    $limit   = isset($_GET['limit']) ? max(1, min(24, absint($_GET['limit']))) : 12;
    $columns = isset($_GET['columns']) ? max(1, min(8, absint($_GET['columns']))) : 4;

    $cache_key = 'ghb_size_rail_' . md5($key);
    $ids       = get_transient($cache_key);
    if (!is_array($ids)) {
        $ids = ghb_size_in_stock_product_ids($option['slugs']);
        set_transient($cache_key, $ids, 10 * MINUTE_IN_SECONDS);
    }
    // A drop not released yet can't be bought: not "available in your size".
    if (function_exists('ghb_drop_is_locked')) {
        $ids = array_values(array_filter($ids, static function ($id) {
            return !ghb_drop_is_locked((int) $id);
        }));
    }

    $html = '';
    if ($ids) {
        // Not a WooCommerce page: switch the cards' Quick View and add-to-cart
        // on, and let every card name this size and its price.
        add_filter('ghb_product_ui_should_load', '__return_true');
        if (function_exists('ghb_atc_replace_core_button')) {
            ghb_atc_replace_core_button();
        }
        if (function_exists('ghb_size_match_slugs')) {
            $param = 'filter_' . preg_replace('/^pa_/', '', ghb_size_attribute());
            ghb_size_match_slugs($param . '=' . implode(',', $option['slugs']));
        }

        $atts = array_merge(ghb_product_rail_defaults(), array(
            'title'   => '',
            'limit'   => $limit,
            'columns' => $columns,
        ));
        $args = ghb_carousel_query_args($atts);
        $args['post__in']      = $ids;
        $args['no_found_rows'] = true;
        $html = ghb_product_rail_render(new WP_Query($args), $atts);
    }

    wp_send_json_success(array(
        'key'   => $option['key'],
        'label' => $option['label'],
        'html'  => $html,
        'empty' => '' === $html ? ghb_size_rail_empty_html($option) : '',
    ));
}

/**
 * Nothing in this size right now: say so, and offer the newsletter's size
 * alert (includes/size-alerts.php) for it — the same form contract as the
 * newsletter block, so js/animations.js submits it.
 */
function ghb_size_rail_empty_html(array $option): string
{
    ob_start();
    ?>
    <div class="ghb-size-rail__empty">
        <p class="ghb-size-rail__empty-title">
            <?php echo esc_html(sprintf(__('Nessun paio disponibile in taglia %s, per ora.', 'golden-hive-blocks'), $option['label'])); ?>
        </p>
        <?php if (function_exists('ghb_size_alerts_enabled') && ghb_size_alerts_enabled()) : ?>
            <p class="ghb-size-rail__empty-text"><?php esc_html_e('Lasciaci la tua email: ti scriviamo quando arriva un paio nella tua taglia.', 'golden-hive-blocks'); ?></p>
            <form class="ghb-size-rail__alert" data-gh-newsletter-form
                  data-gh-newsletter-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                <input type="hidden" name="sizes[]" value="<?php echo esc_attr($option['key']); ?>" data-gh-newsletter-size>
                <input type="email" class="ghb-size-rail__alert-input" placeholder="<?php esc_attr_e('La tua email', 'golden-hive-blocks'); ?>"
                       aria-label="<?php esc_attr_e('Il tuo indirizzo email', 'golden-hive-blocks'); ?>" autocomplete="email" required data-gh-newsletter-input>
                <button type="submit" class="ghb-size-rail__alert-btn"><?php esc_html_e('Avvisami', 'golden-hive-blocks'); ?></button>
            </form>
            <p class="ghb-size-rail__alert-feedback" data-gh-newsletter-feedback aria-live="polite"></p>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
