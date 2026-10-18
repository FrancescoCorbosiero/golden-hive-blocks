<?php
/**
 * Golden Hive — product page additions.
 *
 * Until now the plugin drew the shop and the homepage and left the product
 * page to the theme. These sit on WooCommerce's own single-product hooks, so
 * they are theme-agnostic, and none of them duplicates a CommerceKit module
 * (wishlist, waitlist, size guides, sticky add-to-cart, free-shipping bar stay
 * CommerceKit's):
 *
 *   • "Chiedi su WhatsApp" + "Condividi" under the add-to-cart form
 *     (woocommerce_single_product_summary 35). The WhatsApp message names the
 *     product, the size picked and the link.
 *   • Delivery estimate under the add-to-cart form (summary 31): "Ordina
 *     entro 2 h 15 min e spediamo oggi · Consegna stimata mar 21 – gio 23 ott".
 *     Computed in the browser (js/product-page.js) because product pages are
 *     page-cached, and off until switched on: it is a promise to customers.
 *   • "Altre colorway": the other products of the same model (pa_modello),
 *     as a product rail (woocommerce_after_single_product_summary 12, before
 *     WooCommerce's upsells and related products).
 *   • "Visti di recente": the products this shopper looked at, kept in their
 *     browser (localStorage) and rendered over AJAX into a product rail
 *     (after_single_product_summary 25, after related products).
 *
 * Settings: Customizer → "Golden Hive — Pagina prodotto".
 *
 * @package Golden_Hive_Blocks
 * @since   5.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Most products the "Visti di recente" rail shows. Mirrored in js/product-page.js. */
const GHB_RECENT_MAX = 12;

/* -------------------------------------------------------------------- *
 * Settings
 * -------------------------------------------------------------------- */

function ghb_pp_flag(string $option, string $default): bool
{
    return 'no' !== get_option($option, $default);
}

/** "Altre colorway" rail on/off (default on). */
function ghb_pp_colorways_enabled(): bool
{
    return (bool) apply_filters('ghb_colorways_rail', ghb_pp_flag('ghb_pp_colorways', 'yes'));
}

/** "Visti di recente" rail on/off (default on). */
function ghb_pp_recent_enabled(): bool
{
    return (bool) apply_filters('ghb_recent_rail', ghb_pp_flag('ghb_pp_recent', 'yes'));
}

/** WhatsApp number, digits only with the country code ('' hides the button). */
function ghb_whatsapp_number(): string
{
    $number = (string) apply_filters('ghb_whatsapp_number', (string) get_option('ghb_whatsapp_number', '393517603869'));
    return preg_replace('/\D+/', '', $number);
}

/**
 * Delivery estimate settings, or null while the estimate is off.
 *
 * @return array{cutoff:string,min:int,max:int,closed:string[],tz:string}|null
 */
function ghb_delivery_config(): ?array
{
    if (!apply_filters('ghb_delivery_estimate', ghb_pp_flag('ghb_delivery_estimate', 'no'))) {
        return null;
    }

    $cutoff = (string) get_option('ghb_delivery_cutoff', '13:00');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $cutoff)) {
        $cutoff = '13:00';
    }
    $min = max(0, min(30, (int) get_option('ghb_delivery_days_min', 1)));
    $max = max($min, min(30, (int) get_option('ghb_delivery_days_max', 3)));

    // Days the store doesn't ship (holiday closures), as Y-m-d. Weekends and
    // Italian national holidays are already skipped by the script.
    $closed = array_values(array_filter(
        (array) apply_filters('ghb_delivery_closed_dates', array()),
        static function ($d) {
            return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        }
    ));

    return array(
        'cutoff' => $cutoff,
        'min'    => $min,
        'max'    => $max,
        'closed' => $closed,
        'tz'     => function_exists('wp_timezone_string') ? wp_timezone_string() : 'Europe/Rome',
    );
}

/* -------------------------------------------------------------------- *
 * Assets
 * -------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', 'ghb_product_page_assets');
function ghb_product_page_assets()
{
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    wp_enqueue_style(
        'golden-hive-product-page',
        gh_asset_url('product-page.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    // After product-rail.js (window.ghbProductRail wires the fetched rail).
    wp_enqueue_script(
        'golden-hive-product-page',
        gh_asset_url('js/product-page.js'),
        array('golden-hive-product-rail'),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );

    wp_localize_script(
        'golden-hive-product-page',
        'ghbProductPage',
        array('ajaxUrl' => admin_url('admin-ajax.php'))
    );
}

/* -------------------------------------------------------------------- *
 * WhatsApp + share
 * -------------------------------------------------------------------- */

/** The WhatsApp chat link with a message about a product. */
function ghb_whatsapp_product_url(string $number, string $name, string $url): string
{
    $text = sprintf('Ciao! Vorrei informazioni su %s: %s', $name, $url);
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

add_action('woocommerce_single_product_summary', 'ghb_product_actions', 35);
function ghb_product_actions()
{
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }

    $number = ghb_whatsapp_number();
    $name   = $product->get_name();
    $url    = $product->get_permalink();
    ?>
    <div class="ghb-pp-actions" data-name="<?php echo esc_attr($name); ?>" data-url="<?php echo esc_url($url); ?>"
         data-size-field="<?php echo esc_attr('attribute_' . apply_filters('ghb_atc_size_attribute', 'pa_taglia')); ?>">
        <?php if ('' !== $number) : ?>
            <a class="ghb-pp-action ghb-pp-action--whatsapp" data-ghb-whatsapp="<?php echo esc_attr($number); ?>"
               href="<?php echo esc_url(ghb_whatsapp_product_url($number, $name, $url)); ?>" target="_blank" rel="noopener noreferrer">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91A9.9 9.9 0 0 0 12.04 2Zm5.8 14.13c-.25.69-1.44 1.32-2 1.4-.51.08-1.15.11-1.86-.12-.43-.14-.98-.32-1.68-.62-2.96-1.28-4.89-4.26-5.04-4.46-.15-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.17 1.03-2.46.27-.3.59-.37.79-.37h.57c.18 0 .43-.07.67.51.25.59.84 2.04.91 2.19.07.15.12.32.02.52-.1.2-.15.32-.3.49-.15.17-.31.38-.44.52-.15.15-.3.31-.13.6.17.3.77 1.27 1.65 2.06 1.13 1.01 2.09 1.32 2.38 1.47.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.49.22.57.35.07.12.07.71-.18 1.4Z"/></svg>
                <span><?php esc_html_e('Chiedi su WhatsApp', 'golden-hive-blocks'); ?></span>
            </a>
        <?php endif; ?>
        <button type="button" class="ghb-pp-action ghb-pp-action--share" data-ghb-share hidden>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
            <span><?php esc_html_e('Condividi', 'golden-hive-blocks'); ?></span>
        </button>
    </div>
    <?php
}

/* -------------------------------------------------------------------- *
 * Delivery estimate
 * -------------------------------------------------------------------- */

add_action('woocommerce_single_product_summary', 'ghb_delivery_estimate', 31);
function ghb_delivery_estimate()
{
    global $product;
    $config = ghb_delivery_config();
    if (!$config || !$product instanceof WC_Product || !$product->is_in_stock()) {
        return;
    }

    // Filled in by js/product-page.js: the time and dates are the visitor's
    // "now", which a cached page can't know.
    printf(
        '<div class="ghb-delivery" data-ghb-delivery="%s" hidden></div>',
        esc_attr(wp_json_encode($config))
    );
}

/* -------------------------------------------------------------------- *
 * Rails
 * -------------------------------------------------------------------- */

/** Attributes for the product page's rails: the shortcode's defaults plus these. */
function ghb_pp_rail_atts(array $atts): array
{
    return array_merge(ghb_product_rail_defaults(), array('columns' => 4, 'limit' => GHB_RECENT_MAX), $atts);
}

add_action('woocommerce_after_single_product_summary', 'ghb_colorways_rail', 12);
function ghb_colorways_rail()
{
    global $product;
    if (!$product instanceof WC_Product || !ghb_pp_colorways_enabled()
        || !function_exists('ghb_product_rail_render') || !function_exists('ghb_carousel_query_args')) {
        return;
    }

    $taxonomy = (string) apply_filters('ghb_colorways_taxonomy', 'pa_modello');
    if (!taxonomy_exists($taxonomy)) {
        return;
    }
    $terms = wc_get_product_terms($product->get_id(), $taxonomy, array('fields' => 'ids'));
    if (!$terms) {
        return;
    }

    $atts = ghb_pp_rail_atts(array('title' => __('Altre colorway', 'golden-hive-blocks')));
    $args = ghb_carousel_query_args($atts);
    $args['tax_query'][]  = array('taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array_map('intval', $terms));
    $args['post__not_in'] = array($product->get_id());
    $args['no_found_rows'] = true;
    // Available colorways first, as on the shop (includes/sold-out-last.php).
    if (function_exists('ghb_sold_out_last_enabled') && ghb_sold_out_last_enabled()) {
        $args['ghb_sold_out_last'] = true;
    }

    echo '<section class="ghb-pp-rail ghb-pp-rail--colorways">'
        . ghb_product_rail_render(new WP_Query($args), $atts)
        . '</section>';
}

add_action('woocommerce_after_single_product_summary', 'ghb_recent_rail_slot', 25);
function ghb_recent_rail_slot()
{
    global $product;
    if (!$product instanceof WC_Product || !ghb_pp_recent_enabled()) {
        return;
    }
    // Empty until js/product-page.js fetches the shopper's own products.
    printf('<section class="ghb-pp-rail ghb-pp-rail--recent" data-ghb-recent="%d"></section>', (int) $product->get_id());
}

/**
 * The "Visti di recente" rail for a list of product ids, in that order. Public
 * catalogue data and no nonce, like the plugin's other read-only endpoints,
 * so a cached page can call it.
 */
add_action('wp_ajax_ghb_recent_rail', 'ghb_recent_rail_ajax');
add_action('wp_ajax_nopriv_ghb_recent_rail', 'ghb_recent_rail_ajax');
function ghb_recent_rail_ajax()
{
    $raw = isset($_GET['ids']) && is_string($_GET['ids']) ? $_GET['ids'] : '';
    $ids = array_slice(array_values(array_unique(array_filter(array_map('absint', explode(',', $raw))))), 0, GHB_RECENT_MAX);
    if (!$ids || !ghb_pp_recent_enabled() || !function_exists('ghb_product_rail_render')) {
        wp_send_json_success(array('html' => ''));
    }

    // Not a WooCommerce page: switch the cards' Quick View and add-to-cart on,
    // as the filters' AJAX refresh does.
    add_filter('ghb_product_ui_should_load', '__return_true');
    if (function_exists('ghb_atc_replace_core_button')) {
        ghb_atc_replace_core_button();
    }

    $atts = ghb_pp_rail_atts(array(
        'title' => __('Visti di recente', 'golden-hive-blocks'),
        'ids'   => implode(',', $ids),
        'limit' => count($ids),
    ));
    $args = ghb_carousel_query_args($atts);
    $args['no_found_rows'] = true;

    wp_send_json_success(array('html' => ghb_product_rail_render(new WP_Query($args), $atts)));
}

/* -------------------------------------------------------------------- *
 * Customizer
 * -------------------------------------------------------------------- */

add_action('customize_register', 'ghb_product_page_customize_register');
function ghb_product_page_customize_register(WP_Customize_Manager $wp_customize)
{
    $wp_customize->add_section('ghb_product_page', array(
        'title'    => 'Golden Hive — Pagina prodotto',
        'priority' => 31,
    ));

    $to_bool = static function ($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return is_scalar($value) && in_array(strtolower((string) $value), array('yes', 'true', '1'), true);
    };
    $checkbox = static function (string $id, string $default, string $label, string $description) use ($wp_customize, $to_bool) {
        $wp_customize->add_setting($id, array(
            'type'                 => 'option',
            'capability'           => 'manage_woocommerce',
            'default'              => $default,
            'transport'            => 'refresh',
            // Stored as yes / no like WooCommerce's own options, a boolean for the checkbox.
            'sanitize_callback'    => static function ($value) use ($to_bool) {
                return $to_bool($value) ? 'yes' : 'no';
            },
            'sanitize_js_callback' => $to_bool,
        ));
        $wp_customize->add_control($id, array(
            'section'     => 'ghb_product_page',
            'type'        => 'checkbox',
            'label'       => $label,
            'description' => $description,
        ));
    };
    $number = static function (string $id, int $default, string $label, string $description) use ($wp_customize) {
        $wp_customize->add_setting($id, array(
            'type'              => 'option',
            'capability'        => 'manage_woocommerce',
            'default'           => $default,
            'transport'         => 'refresh',
            'sanitize_callback' => static function ($value) {
                return max(0, min(30, (int) $value));
            },
        ));
        $wp_customize->add_control($id, array(
            'section'     => 'ghb_product_page',
            'type'        => 'number',
            'label'       => $label,
            'description' => $description,
            'input_attrs' => array('min' => 0, 'max' => 30, 'step' => 1),
        ));
    };

    $checkbox('ghb_pp_colorways', 'yes', 'Mostra "Altre colorway"', 'Sotto il prodotto, gli altri prodotti dello stesso modello (attributo Modello).');
    $checkbox('ghb_pp_recent', 'yes', 'Mostra "Visti di recente"', 'In fondo alla pagina, i prodotti che il cliente ha guardato. Restano nel suo browser: nessun dato personale sul sito.');

    $wp_customize->add_setting('ghb_whatsapp_number', array(
        'type'              => 'option',
        'capability'        => 'manage_woocommerce',
        'default'           => '393517603869',
        'transport'         => 'refresh',
        'sanitize_callback' => static function ($value) {
            return preg_replace('/\D+/', '', (string) $value);
        },
    ));
    $wp_customize->add_control('ghb_whatsapp_number', array(
        'section'     => 'ghb_product_page',
        'type'        => 'text',
        'label'       => 'Numero WhatsApp',
        'description' => 'Con il prefisso internazionale, senza + né spazi (es. 393331234567). Vuoto nasconde il bottone "Chiedi su WhatsApp", anche nell\'anteprima rapida.',
    ));

    $checkbox('ghb_delivery_estimate', 'no', 'Mostra la stima di consegna', 'Sotto "Aggiungi al carrello": entro che ora ordinare per la spedizione in giornata e tra quali giorni arriva. Weekend e festività nazionali sono esclusi da soli.');

    $wp_customize->add_setting('ghb_delivery_cutoff', array(
        'type'              => 'option',
        'capability'        => 'manage_woocommerce',
        'default'           => '13:00',
        'transport'         => 'refresh',
        'sanitize_callback' => static function ($value) {
            $value = trim((string) $value);
            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '13:00';
        },
    ));
    $wp_customize->add_control('ghb_delivery_cutoff', array(
        'section'     => 'ghb_product_page',
        'type'        => 'text',
        'label'       => 'Orario limite per la spedizione in giornata',
        'description' => 'Formato HH:MM, ora del negozio (es. 13:00). Dopo quest\'ora l\'ordine parte il giorno lavorativo successivo.',
    ));
    $number('ghb_delivery_days_min', 1, 'Consegna: giorni lavorativi minimi', 'Dalla spedizione alla consegna, nel caso migliore.');
    $number('ghb_delivery_days_max', 3, 'Consegna: giorni lavorativi massimi', 'Dalla spedizione alla consegna, nel caso peggiore.');
}
