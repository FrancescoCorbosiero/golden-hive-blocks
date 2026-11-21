<?php
/**
 * Golden Hive — Lightweight product rail (CSS scroll-snap carousel).
 *
 * A featherweight alternative to the Swiper-based [carousel_section]: no slider
 * library and no CDN — just native horizontal scroll + CSS scroll-snap
 * (Shopify-style), with optional ‹ › arrows (a few lines of vanilla JS).
 *
 * Cards are rendered through WooCommerce's standard product-loop template
 * (wc_get_template_part('content','product')) inside a `.woocommerce` wrapper,
 * so they fire the standard loop hooks — Quick View, the add-to-cart button,
 * "Esaurito", drop dates and the filtered size appear on rail cards
 * automatically. The markup stays WooCommerce's; product-rail.css gives the
 * cards the rail's own look (photo on a plate, two-line titles, one price
 * baseline, an outlined button) and the arrows sit under the row, next to a
 * progress line. Each arrow still moves a whole row.
 *
 * Reuses the existing carousel query builder (ghb_get_carousel_products) so the
 * type / category / brand / tag / ids attributes behave identically.
 *
 * Usage: [gh_product_rail title="Novità" type="recent" limit="12"]
 *        [gh_product_rail brand="supreme" limit="10"]
 *
 * Layout controls (all optional; left out, they take the store's rail settings,
 * Customizer → Golden Hive → Prodotti e filtri, product-rail-core.php):
 *   columns        cards per view on desktop (1–8, default 4)
 *   columns_tablet cards per view 768–1024px (default: inherits `columns`)
 *   columns_mobile cards per view ≤767px (default: a 44vw peek of the next card)
 *   ratio          image aspect-ratio — alias (square|portrait|tall|landscape|
 *                  wide) or a raw "N/N" value like 3/4 (default square)
 *   fit            contain (whole image, default) | cover (crop to fill)
 *
 *   e.g. [gh_product_rail brand="nike" columns="5" columns_mobile="2"
 *                         ratio="3/4" fit="contain"]
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/product-rail-core.php';

/** The store's rail settings (Customizer → Golden Hive → Prodotti e filtri), cleaned. */
function ghb_rail_store_settings(): array
{
    $saved = get_option('ghb_rail', array());
    return ghb_rail_settings(is_array($saved) ? $saved : array());
}

/**
 * Load the rail assets front-end-wide. They're tiny, and enqueuing from inside
 * the shortcode is unreliable (late enqueue isn't always printed, e.g. via the
 * Gutenberg Shortcode block), which is what left the rail unstyled.
 */
add_action('wp_enqueue_scripts', 'ghb_product_rail_assets');
function ghb_product_rail_assets()
{
    wp_enqueue_style(
        'golden-hive-product-rail',
        gh_asset_url('product-rail.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
    wp_enqueue_script(
        'golden-hive-product-rail',
        gh_asset_url('js/product-rail.js'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}

/**
 * Where the rails' arrows sit (Customizer → Golden Hive — Stile): 'below' the
 * row, next to a progress line, or on its 'sides', over the photos.
 */
function ghb_rail_nav_style(): string
{
    $style = (string) apply_filters('ghb_rail_nav_style', (string) get_option('ghb_rail_nav', 'below'));
    return in_array($style, array('below', 'sides'), true) ? $style : 'below';
}

/** One rail arrow: -1 back, 1 forward. js/product-rail.js moves the row. */
function ghb_rail_nav_button(int $dir): string
{
    $back = $dir < 0;
    return sprintf(
        '<button type="button" class="gh-rail__nav gh-rail__nav--%s" data-dir="%d" aria-label="%s"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg></button>',
        $back ? 'prev' : 'next',
        $back ? -1 : 1,
        esc_attr($back ? __('Precedenti', 'golden-hive-blocks') : __('Successivi', 'golden-hive-blocks')),
        $back ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7'
    );
}

/**
 * Every attribute [gh_product_rail] accepts, with its default: the layout and
 * the number of products from the store's rail settings. Shared with the
 * Store Hub bridge (hub-rails.php), which must read a rail exactly the way the
 * shortcode does.
 */
function ghb_product_rail_defaults()
{
    $rail = ghb_rail_store_settings();
    return array(
        'title'          => '',
        'type'           => 'recent',   // recent | featured | best_selling | sale | top_rated
        'category'       => '',
        'brand'          => '',
        'tag'            => '',
        'ids'            => '',
        // Written by the Store Hub's Vetrina (see hub-rails.php): products
        // shown first in this order, products never shown, and the order of
        // everything after the pinned ones.
        'pin'            => '',
        'exclude'        => '',
        'fallback'       => '',
        'limit'          => $rail['limit'],
        'columns'        => $rail['columns'],        // cards visible per view on desktop (1–8)
        'columns_tablet' => $rail['columns_tablet'], // blank ⇒ inherit desktop columns
        'columns_mobile' => $rail['columns_mobile'], // blank ⇒ peek the next card (44vw)
        'ratio'          => $rail['ratio'],          // card image ratio: alias or "N/N" (e.g. 3/4)
        // contain (whole image) | cover (crop). Contain by default: product
        // photos are framed differently, and a crop cut them unevenly from
        // card to card. Code can still force it: add_filter('ghb_rail_default_fit', …).
        'fit'            => apply_filters('ghb_rail_default_fit', $rail['fit']),
    );
}

add_shortcode('gh_product_rail', 'ghb_product_rail_shortcode');
function ghb_product_rail_shortcode($atts)
{
    if (!class_exists('WooCommerce') || !function_exists('ghb_get_carousel_products')) {
        return '';
    }

    $atts = shortcode_atts(
        ghb_product_rail_defaults(),
        $atts,
        'gh_product_rail'
    );

    return ghb_product_rail_render(ghb_get_carousel_products($atts), $atts);
}

/**
 * A rail's markup for an already-run product query. The shortcode's rails and
 * the product page's own (includes/product-page.php) share it, so every rail
 * has the same cards, layout and arrows. $atts is a full attribute set
 * (ghb_product_rail_defaults() plus overrides). Empty query, empty string.
 */
function ghb_product_rail_render(WP_Query $query, array $atts): string
{
    $atts = array_merge(ghb_product_rail_defaults(), $atts);

    // Cards shown per view on desktop (drives the batch step too). 1–8.
    $cols = max(1, min(8, (int) $atts['columns']));

    // Optional per-breakpoint overrides. 0 ⇒ "not set": tablet inherits desktop
    // columns, mobile keeps the default 44vw peek (see product-rail.css).
    $cols_tablet = ('' !== $atts['columns_tablet']) ? max(1, min(8, (int) $atts['columns_tablet'])) : 0;
    $cols_mobile = ('' !== $atts['columns_mobile']) ? max(1, min(8, (int) $atts['columns_mobile'])) : 0;

    // Card image aspect-ratio: an alias or a raw "N/N" value; a square otherwise.
    $ratio = ghb_rail_ratio_css((string) $atts['ratio']);

    // How the image fills its box. `cover` crops to fill (uniform cards);
    // `contain` shows the whole image, letterboxed against a neutral backdrop.
    $fit = in_array($atts['fit'], array('cover', 'contain'), true) ? $atts['fit'] : 'contain';

    // Wrapper classes + the CSS custom properties that drive the layout. Only
    // the breakpoints that were explicitly set emit a variable, so the CSS
    // fallbacks (desktop columns / 44vw peek) stay in charge otherwise.
    $nav_sides    = 'sides' === ghb_rail_nav_style();
    $rail_classes = 'woocommerce gh-rail gh-rail--fit-' . $fit . ($nav_sides ? ' gh-rail--nav-sides' : '');
    if ($cols_mobile) {
        $rail_classes .= ' gh-rail--mcols';
    }

    $track_vars = sprintf('--gh-cols: %d; --gh-ratio: %s; --gh-fit: %s;', $cols, $ratio, $fit);
    if ($cols_tablet) {
        $track_vars .= sprintf(' --gh-cols-tablet: %d;', $cols_tablet);
    }
    if ($cols_mobile) {
        $track_vars .= sprintf(' --gh-cols-mobile: %d;', $cols_mobile);
    }

    if (!$query->have_posts()) {
        wp_reset_postdata();
        return '';
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($rail_classes); ?>" data-gh-reveal="stagger" data-gh-stagger="li.product">
        <?php if ('' !== $atts['title']) : ?>
            <h2 class="gh-rail__title" data-gh-split="lines"><?php echo esc_html($atts['title']); ?></h2>
        <?php endif; ?>
        <div class="gh-rail__viewport">
            <?php if ($nav_sides) : ?>
                <?php echo ghb_rail_nav_button(-1); // Escaped in the builder. ?>
            <?php endif; ?>
            <ul class="products gh-rail__track" style="<?php echo esc_attr($track_vars); ?>">
                <?php
                ghb_rail_in_loop(true);
                while ($query->have_posts()) {
                    $query->the_post();
                    wc_get_template_part('content', 'product');
                }
                ghb_rail_in_loop(false);
                wp_reset_postdata();
                ?>
            </ul>
            <?php if ($nav_sides) : ?>
                <?php echo ghb_rail_nav_button(1); // Escaped in the builder. ?>
            <?php endif; ?>
        </div>
        <?php if (!$nav_sides) : ?>
            <div class="gh-rail__bar">
                <div class="gh-rail__progress" aria-hidden="true"><span></span></div>
                <?php echo ghb_rail_nav_button(-1) . ghb_rail_nav_button(1); // Escaped in the builder. ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Whether a rail is printing its cards right now (nested-safe counter), so
 * the loop hooks below touch rail cards only and leave the shop grid alone.
 */
function ghb_rail_in_loop(?bool $enter = null): bool
{
    static $depth = 0;
    if (true === $enter) {
        $depth++;
    } elseif (false === $enter) {
        $depth = max(0, $depth - 1);
    }
    return $depth > 0;
}

/**
 * The product's second photo (its first gallery image) on rail cards, shown
 * on hover by product-rail.css. Devices without hover never load it: the
 * image is lazy and stays display:none there. Off in the Customizer (Golden
 * Hive → Prodotti e filtri) or with add_filter('ghb_rail_hover_image', '__return_false').
 */
add_action('woocommerce_before_shop_loop_item_title', 'ghb_rail_hover_image', 12);
function ghb_rail_hover_image()
{
    if (!ghb_rail_in_loop() || !apply_filters('ghb_rail_hover_image', 'no' !== ghb_rail_store_settings()['hover'])) {
        return;
    }
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $ids = $product->get_gallery_image_ids();
    if (!$ids) {
        return;
    }
    echo wp_get_attachment_image((int) $ids[0], 'woocommerce_thumbnail', false, array(
        'class'       => 'gh-rail__alt',
        'alt'         => '',
        'aria-hidden' => 'true',
        'loading'     => 'lazy',
        'decoding'    => 'async',
    ));
}

/* -------------------------------------------------------------------- *
 * Customizer: Golden Hive → Prodotti e filtri
 * -------------------------------------------------------------------- */

function ghb_rail_customize_register(WP_Customize_Manager $wp_customize): void
{
    $setting = static function (string $key) use ($wp_customize) {
        ghb_customize_setting($wp_customize, 'ghb_rail[' . $key . ']', GHB_RAIL_DEFAULTS[$key], static function ($value) use ($key) {
            return ghb_rail_setting_clean($key, $value);
        });
    };
    $count = static function (int $from, int $to): array {
        $choices = array();
        for ($n = $from; $n <= $to; $n++) {
            $choices[(string) $n] = (string) $n;
        }
        return $choices;
    };

    $setting('columns');
    $wp_customize->add_control('ghb_rail[columns]', array(
        'section'     => 'ghb_catalog',
        'type'        => 'number',
        'label'       => 'Prodotti per riga su desktop',
        'description' => 'Ogni sezione prodotto ne mostra tanti affiancati; le frecce scorrono una riga intera.',
        'input_attrs' => array('min' => 1, 'max' => 8, 'step' => 1),
        'priority'    => 10,
    ));
    $setting('columns_tablet');
    $wp_customize->add_control('ghb_rail[columns_tablet]', array(
        'section'  => 'ghb_catalog',
        'type'     => 'select',
        'label'    => 'Prodotti per riga su tablet',
        'choices'  => array('' => 'Come su desktop') + $count(1, 8),
        'priority' => 20,
    ));
    $setting('columns_mobile');
    $wp_customize->add_control('ghb_rail[columns_mobile]', array(
        'section'  => 'ghb_catalog',
        'type'     => 'select',
        'label'    => 'Prodotti per riga su telefono',
        'choices'  => array('' => 'Due e un pezzo del successivo') + $count(1, 4),
        'priority' => 30,
    ));
    $setting('limit');
    $wp_customize->add_control('ghb_rail[limit]', array(
        'section'     => 'ghb_catalog',
        'type'        => 'number',
        'label'       => 'Prodotti in ogni sezione',
        'input_attrs' => array('min' => 1, 'max' => GHB_RAIL_MAX_LIMIT, 'step' => 1),
        'priority'    => 40,
    ));
    $setting('ratio');
    $wp_customize->add_control('ghb_rail[ratio]', array(
        'section'  => 'ghb_catalog',
        'type'     => 'select',
        'label'    => 'Formato delle foto',
        'choices'  => array(
            'square'    => 'Quadrato (1:1)',
            'portrait'  => 'Verticale (3:4)',
            'tall'      => 'Verticale (4:5)',
            'landscape' => 'Orizzontale (4:3)',
            'wide'      => 'Panoramico (16:9)',
        ),
        'priority' => 50,
    ));
    $setting('fit');
    $wp_customize->add_control('ghb_rail[fit]', array(
        'section'  => 'ghb_catalog',
        'type'     => 'radio',
        'label'    => 'Foto',
        'choices'  => array(
            'contain' => 'Intera, su fondo neutro',
            'cover'   => 'Ritagliata a tutto riquadro',
        ),
        'priority' => 60,
    ));
    ghb_customize_checkbox($wp_customize, 'ghb_catalog', 'ghb_rail[hover]', GHB_RAIL_DEFAULTS['hover'], 'Seconda foto al passaggio del mouse', 'Sui computer, la seconda foto del prodotto quando il mouse passa sulla scheda.');
    $wp_customize->get_control('ghb_rail[hover]')->priority = 70;

    ghb_customize_setting($wp_customize, 'ghb_rail_nav', 'below', static function ($value) {
        return in_array($value, array('below', 'sides'), true) ? $value : 'below';
    }, array('capability' => 'edit_theme_options'));
    $wp_customize->add_control('ghb_rail_nav', array(
        'section'     => 'ghb_catalog',
        'type'        => 'radio',
        'label'       => 'Frecce delle sezioni prodotto',
        'description' => 'Su telefono le sezioni si scorrono col dito in entrambi i casi.',
        'choices'     => array(
            'below' => 'Sotto la fila, con la linea di avanzamento',
            'sides' => 'Ai lati, sopra le foto',
        ),
        'priority'    => 80,
    ));
}
add_action('customize_register', 'ghb_rail_customize_register');
