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
 *   • A band under the product description (after_single_product_summary
 *     15): "Non trovi la tua taglia?", the sold-out sizes as chips that open
 *     WhatsApp with the request written, next to "Acquista con fiducia"
 *     tiles (authenticity, shipping, returns, payments) that open on a tap.
 *   • "Altre colorway": the other products of the same model (pa_modello),
 *     as a product rail (woocommerce_after_single_product_summary 19, right
 *     before WooCommerce's related products).
 *   • "Visti di recente": the products this shopper looked at, kept in their
 *     browser (localStorage) and rendered over AJAX into a product rail
 *     (after_single_product_summary 25, after related products).
 *
 * The band and the rails sit inside ghb_pp_container_class(): on Shoptimizer
 * the theme's content container, so they line up with "Prodotti correlati".
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

/** "Non trovi la tua taglia?" + "Acquista con fiducia" band on/off (default on). */
function ghb_pp_band_enabled(): bool
{
    return (bool) apply_filters('ghb_pp_band', ghb_pp_flag('ghb_pp_band', 'yes'));
}

/**
 * Classes of the box that holds the product page's sections (band, rails).
 *
 * THEME-SPECIFIC (theme module 'theme-bridge'): WooCommerce runs
 * after_single_product_summary inside a full-width area on Shoptimizer, and
 * the theme gives each of its own sections its .col-full container. Ours take
 * the same class, so they keep the width the theme's Customizer sets. On
 * other themes the plugin's own max-width applies (product-page.css).
 */
function ghb_pp_container_class(): string
{
    $class = 'ghb-pp-inner';
    if (function_exists('ghb_theme_module') && ghb_theme_module('theme-bridge')) {
        $class .= ' col-full';
    }
    return (string) apply_filters('ghb_pp_container_class', $class);
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
    $min = max(0, min(30, (int) get_option('ghb_delivery_days_min', 2)));
    $max = max($min, min(30, (int) get_option('ghb_delivery_days_max', 5)));

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

    // After product-rail.js (window.ghbProductRail wires the fetched rail) and
    // calendar.js (the delivery estimate's days and holidays).
    wp_enqueue_script(
        'golden-hive-product-page',
        gh_asset_url('js/product-page.js'),
        array('golden-hive-product-rail', 'ghb-calendar'),
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

/** A WhatsApp chat link with a message already written. */
function ghb_whatsapp_url(string $number, string $text): string
{
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

/** The WhatsApp chat link with a message about a product. */
function ghb_whatsapp_product_url(string $number, string $name, string $url): string
{
    return ghb_whatsapp_url($number, sprintf('Ciao! Vorrei informazioni su %s: %s', $name, $url));
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
 * Band: "Non trovi la tua taglia?" + "Acquista con fiducia"
 * -------------------------------------------------------------------- */

/**
 * The "Acquista con fiducia" tiles: icon, title, line under it, and the text
 * a tap opens. The defaults restate the store's own FAQ (homepage) — change
 * them with this filter when the policies change.
 */
function ghb_pp_trust_items(): array
{
    return (array) apply_filters('ghb_pp_trust_items', array(
        array(
            'icon'  => 'authentic',
            'title' => 'Originali al 100%',
            'sub'   => 'Verificati uno per uno',
            'text'  => 'Ogni articolo passa il controllo di autenticità del nostro team prima della spedizione: materiali, cuciture, etichette e ogni dettaglio che distingue un originale da un falso.',
        ),
        array(
            'icon'  => 'shipping',
            'title' => 'Spedizione assicurata',
            'sub'   => 'DHL o UPS, 2–5 giorni lavorativi',
            'text'  => 'Spediamo in tutta Italia con DHL o UPS. Gli ordini vengono preparati entro 24–48 ore dal pagamento e ricevi il codice di tracking via email.',
        ),
        array(
            'icon'  => 'returns',
            'title' => 'Reso entro 14 giorni',
            'sub'   => 'Dalla consegna',
            'text'  => 'Hai 14 giorni dalla consegna per il reso: il prodotto deve essere nelle condizioni originali, non indossato e con tutti i tag. Scrivici via email o WhatsApp per avviarlo.',
        ),
        array(
            'icon'  => 'secure',
            'title' => 'Paga come vuoi',
            'sub'   => 'Anche in 3 rate senza interessi',
            'text'  => 'Carte di credito (Visa, Mastercard, American Express), PayPal, Apple Pay, Google Pay e bonifico bancario. Oppure in 3 rate senza interessi con Klarna o Scalapay.',
        ),
    ));
}

function ghb_pp_icon(string $name): string
{
    $icons = array(
        'authentic' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'shipping'  => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'returns'   => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>',
        'secure'    => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
    );
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($icons[$name] ?? $icons['authentic']) . '</svg>';
}

/**
 * "Non trovi la tua taglia?": this product's sold-out sizes as chips, each a
 * WhatsApp chat asking the store to source that size, plus "Un'altra
 * taglia". Variable products only, and only with a WhatsApp number. Not the
 * CommerceKit waitlist (that waits for a restock): this asks the store to
 * look for the pair.
 */
function ghb_pp_size_request_card(WC_Product $product, string $number): string
{
    if ('' === $number || !$product->is_type('variable') || !function_exists('ghb_atc_size_rows')) {
        return '';
    }

    $name = $product->get_name();
    $url  = $product->get_permalink();

    $chips = '';
    $shown = 0;
    foreach (ghb_atc_size_rows($product) as $row) {
        if ($row['in_stock'] || $shown >= 12) {
            continue;
        }
        $shown++;
        $text   = sprintf('Ciao! Cerco %s in taglia %s. Riuscite a trovarla? %s', $name, $row['label'], $url);
        $chips .= sprintf(
            '<a class="ghb-pp-size-card__chip" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(ghb_whatsapp_url($number, $text)),
            esc_html($row['label'])
        );
    }
    $chips .= sprintf(
        '<a class="ghb-pp-size-card__chip ghb-pp-size-card__chip--other" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url(ghb_whatsapp_url($number, sprintf('Ciao! Cerco %s in una taglia che non vedo disponibile: %s', $name, $url))),
        $shown ? esc_html__('Un\'altra taglia', 'golden-hive-blocks') : esc_html__('Scrivici la tua taglia', 'golden-hive-blocks')
    );

    ob_start();
    ?>
    <div class="ghb-pp-size-card">
        <p class="ghb-pp-size-card__eyebrow"><?php esc_html_e('Su richiesta', 'golden-hive-blocks'); ?></p>
        <h3 class="ghb-pp-size-card__title"><?php esc_html_e('Non trovi la tua taglia?', 'golden-hive-blocks'); ?></h3>
        <p class="ghb-pp-size-card__text">
            <?php echo $shown
                ? esc_html__('Te la cerchiamo noi. Tocca la taglia che ti serve e scrivici su WhatsApp: ti diciamo se riusciamo a trovarla e a che prezzo.', 'golden-hive-blocks')
                : esc_html__('Te la cerchiamo noi. Scrivici su WhatsApp la taglia che ti serve: ti diciamo se riusciamo a trovarla e a che prezzo.', 'golden-hive-blocks'); ?>
        </p>
        <div class="ghb-pp-size-card__chips"><?php echo $chips; // Escaped while built above. ?></div>
    </div>
    <?php
    return (string) ob_get_clean();
}

add_action('woocommerce_after_single_product_summary', 'ghb_pp_band', 15);
function ghb_pp_band()
{
    global $product;
    if (!$product instanceof WC_Product || !ghb_pp_band_enabled()) {
        return;
    }

    $card  = ghb_pp_size_request_card($product, ghb_whatsapp_number());
    $items = ghb_pp_trust_items();
    if ('' === $card && !$items) {
        return;
    }
    ?>
    <section class="ghb-pp-band" data-gh-reveal="up">
        <div class="<?php echo esc_attr(ghb_pp_container_class()); ?>">
            <div class="ghb-pp-band__grid<?php echo '' === $card ? ' ghb-pp-band__grid--trust-only' : ''; ?>">
                <?php echo $card; // Escaped in ghb_pp_size_request_card(). ?>
                <?php if ($items) : ?>
                    <div class="ghb-pp-trust">
                        <h3 class="ghb-pp-trust__title"><?php esc_html_e('Acquista con fiducia', 'golden-hive-blocks'); ?></h3>
                        <div class="ghb-pp-trust__grid">
                            <?php foreach ($items as $item) : ?>
                                <details class="ghb-pp-trust__item" name="ghb-pp-trust">
                                    <summary>
                                        <span class="ghb-pp-trust__icon"><?php echo ghb_pp_icon((string) ($item['icon'] ?? '')); // Fixed SVG markup. ?></span>
                                        <span class="ghb-pp-trust__head">
                                            <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong>
                                            <?php if (!empty($item['sub'])) : ?>
                                                <span><?php echo esc_html((string) $item['sub']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="ghb-pp-trust__chevron" aria-hidden="true"></span>
                                    </summary>
                                    <p><?php echo esc_html((string) ($item['text'] ?? '')); ?></p>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}

/* -------------------------------------------------------------------- *
 * Rails
 * -------------------------------------------------------------------- */

/**
 * Attributes for the product page's rails: the shortcode's defaults plus
 * these. Five cards per view, like the theme's "Prodotti correlati".
 */
function ghb_pp_rail_atts(array $atts): array
{
    return array_merge(
        ghb_product_rail_defaults(),
        array('columns' => 5, 'columns_tablet' => 3, 'limit' => GHB_RECENT_MAX),
        $atts
    );
}

add_action('woocommerce_after_single_product_summary', 'ghb_colorways_rail', 19);
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

    $rail = ghb_product_rail_render(new WP_Query($args), $atts);
    if ('' !== $rail) {
        printf(
            '<section class="ghb-pp-rail ghb-pp-rail--colorways"><div class="%s">%s</div></section>',
            esc_attr(ghb_pp_container_class()),
            $rail // Rendered by the rail template, escaped there.
        );
    }
}

add_action('woocommerce_after_single_product_summary', 'ghb_recent_rail_slot', 25);
function ghb_recent_rail_slot()
{
    global $product;
    if (!$product instanceof WC_Product || !ghb_pp_recent_enabled()) {
        return;
    }
    // Empty until js/product-page.js fetches the shopper's own products.
    printf(
        '<section class="ghb-pp-rail ghb-pp-rail--recent"><div class="%s" data-ghb-recent="%d"></div></section>',
        esc_attr(ghb_pp_container_class()),
        (int) $product->get_id()
    );
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
    $checkbox('ghb_pp_band', 'yes', 'Mostra "Non trovi la tua taglia?" e "Acquista con fiducia"', 'Sotto la descrizione del prodotto: le taglie esaurite diventano richieste su WhatsApp, accanto ad autenticità, spedizione, reso e pagamenti. I testi riprendono le FAQ della homepage.');
    $checkbox('ghb_pp_sold_count', 'yes', 'Mostra "Venduto N volte negli ultimi 30 giorni"', 'Sotto il prezzo, da 2 vendite in su: conta gli ordini pagati di quel prodotto (qualsiasi taglia).');
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
    $number('ghb_delivery_days_min', 2, 'Consegna: giorni lavorativi minimi', 'Dalla spedizione alla consegna, nel caso migliore. Le FAQ dicono 2–5 giorni lavorativi.');
    $number('ghb_delivery_days_max', 5, 'Consegna: giorni lavorativi massimi', 'Dalla spedizione alla consegna, nel caso peggiore.');
}
