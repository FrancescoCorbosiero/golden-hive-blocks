<?php
/**
 * Golden Hive — design switches (Customizer → "Golden Hive — Stile").
 *
 * "Forma degli elementi": Creativo (rounded — the blocks' original look) or
 * Elegante (sharp corners). Every radius in the plugin is written as
 * calc(N * var(--gh-round)), so one custom property re-shapes the blocks, the
 * WooCommerce UI the plugin draws (filters, quick view, quick add, buttons)
 * and — through theme-bridge.css — Shoptimizer's own product cards, buttons
 * and fields. Code can force it: add_filter('ghb_design_shape', fn() => 'sharp').
 *
 * "Filtri su desktop": the shop sidebar as the theme lays it out, or a drawer
 * opened from a "Filtri" button in the shop toolbar, with the products using
 * the full width. Phones keep the theme's own filter drawer either way.
 *
 * @package Golden_Hive_Blocks
 * @since   5.11.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** 'round' (Creativo) or 'sharp' (Elegante). */
function ghb_design_shape(): string
{
    $shape = (string) apply_filters('ghb_design_shape', (string) get_option('ghb_design_shape', 'round'));
    return in_array($shape, array('round', 'sharp'), true) ? $shape : 'round';
}

/** 'sidebar' or 'drawer'. */
function ghb_filters_layout(): string
{
    $layout = (string) apply_filters('ghb_filters_layout', (string) get_option('ghb_filters_layout', 'sidebar'));
    return in_array($layout, array('sidebar', 'drawer'), true) ? $layout : 'sidebar';
}

/** The drawer layout applies to product archives only. */
function ghb_filters_drawer_active(): bool
{
    return 'drawer' === ghb_filters_layout()
        && function_exists('is_shop')
        && (is_shop() || is_product_taxonomy());
}

/* -------------------------------------------------------------------- *
 * Customizer
 * -------------------------------------------------------------------- */

function ghb_design_customize_register(WP_Customize_Manager $wp_customize)
{
    $wp_customize->add_section('ghb_design', array(
        'title'    => 'Golden Hive — Stile',
        'priority' => 30,
    ));

    $wp_customize->add_setting('ghb_design_shape', array(
        'type'              => 'option',
        'capability'        => 'edit_theme_options',
        'default'           => 'round',
        'transport'         => 'postMessage',
        'sanitize_callback' => function ($value) {
            return in_array($value, array('round', 'sharp'), true) ? $value : 'round';
        },
    ));
    $wp_customize->add_control('ghb_design_shape', array(
        'section'     => 'ghb_design',
        'type'        => 'radio',
        'label'       => 'Forma degli elementi',
        'description' => 'Pulsanti, schede prodotto, campi, filtri e blocchi cambiano forma insieme.',
        'choices'     => array(
            'round' => 'Creativo — angoli arrotondati',
            'sharp' => 'Elegante — spigoli vivi',
        ),
    ));

    $wp_customize->add_setting('ghb_filters_layout', array(
        'type'              => 'option',
        'capability'        => 'edit_theme_options',
        'default'           => 'sidebar',
        'transport'         => 'refresh',
        'sanitize_callback' => function ($value) {
            return in_array($value, array('sidebar', 'drawer'), true) ? $value : 'sidebar';
        },
    ));
    $wp_customize->add_control('ghb_filters_layout', array(
        'section'     => 'ghb_design',
        'type'        => 'radio',
        'label'       => 'Filtri del negozio su desktop',
        'description' => 'Con il pannello, i prodotti occupano tutta la larghezza e i filtri si aprono dal pulsante «Filtri». Su telefono non cambia nulla.',
        'choices'     => array(
            'sidebar' => 'Barra laterale sempre aperta',
            'drawer'  => 'Pulsante «Filtri» e pannello a scomparsa',
        ),
    ));
}
add_action('customize_register', 'ghb_design_customize_register');

/** Live preview of the shape: swap the one custom property, no reload. */
function ghb_design_customize_preview()
{
    wp_enqueue_script('customize-preview');
    wp_add_inline_script(
        'customize-preview',
        "wp.customize('ghb_design_shape', function (setting) { setting.bind(function (shape) {"
        . " var el = document.getElementById('ghb-shape'); if (el) { el.textContent = 'html:root{--gh-round:' + (shape === 'sharp' ? 0 : 1) + '}'; }"
        . ' }); });'
    );
}
add_action('customize_preview_init', 'ghb_design_customize_preview');

/* -------------------------------------------------------------------- *
 * Front end
 * -------------------------------------------------------------------- */

/**
 * The shape: one custom property. html:root outranks the stylesheet's :root
 * default whatever the order the two are printed in.
 */
function ghb_design_print_shape()
{
    echo '<style id="ghb-shape">html:root{--gh-round:' . ('sharp' === ghb_design_shape() ? '0' : '1') . "}</style>\n";
}
add_action('wp_head', 'ghb_design_print_shape', 2);

/**
 * Shoptimizer's own surfaces on the same scale, and the drawer layout. Only
 * with Shoptimizer as the parent theme; add_filter('ghb_design_theme_bridge',
 * '__return_false') turns it off. Enqueued late so it follows the theme.
 */
function ghb_design_theme_bridge_assets()
{
    if ('shoptimizer' !== get_template() || !apply_filters('ghb_design_theme_bridge', true)) {
        return;
    }
    wp_enqueue_style('ghb-theme-bridge', gh_asset_url('theme-bridge.css'), array(), GOLDEN_HIVE_BLOCKS_VERSION);
}
add_action('wp_enqueue_scripts', 'ghb_design_theme_bridge_assets', 20);

function ghb_design_body_class($classes)
{
    if (ghb_filters_drawer_active()) {
        $classes[] = 'ghb-filters-drawer';
    }
    return $classes;
}
add_filter('body_class', 'ghb_design_body_class');

/** The "Filtri" button, in the shop toolbar next to the sorting. */
function ghb_filters_drawer_toggle()
{
    if (!ghb_filters_drawer_active()) {
        return;
    }
    echo '<button type="button" class="ghb-filters-toggle bfl-trigger" aria-controls="secondary" aria-expanded="false">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>'
        . '<span>' . esc_html__('Filtri', 'golden-hive-blocks') . '</span></button>';
}
add_action('woocommerce_before_shop_loop', 'ghb_filters_drawer_toggle', 25);

/** Open / close the sidebar drawer: the button, a backdrop, Esc, a close button. */
function ghb_filters_drawer_script()
{
    if (!ghb_filters_drawer_active()) {
        return;
    }
    ?>
    <script>
    (function () {
        var body = document.body;
        var toggle = document.querySelector('.ghb-filters-toggle');
        var panel = document.getElementById('secondary');
        if (!toggle) { return; }
        if (!panel) { toggle.hidden = true; return; } // no sidebar: nothing to open
        var backdrop = document.createElement('div');
        backdrop.className = 'ghb-filters-backdrop';
        body.appendChild(backdrop);
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ghb-filters-close';
        close.setAttribute('aria-label', <?php echo wp_json_encode(__('Chiudi i filtri', 'golden-hive-blocks')); ?>);
        close.innerHTML = '&times;';
        panel.insertBefore(close, panel.firstChild);
        function set(open) {
            body.classList.toggle('ghb-filters-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { close.focus(); } else { toggle.focus(); }
        }
        toggle.addEventListener('click', function () { set(true); });
        backdrop.addEventListener('click', function () { set(false); });
        close.addEventListener('click', function () { set(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && body.classList.contains('ghb-filters-open')) { set(false); }
        });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'ghb_filters_drawer_script', 30);
