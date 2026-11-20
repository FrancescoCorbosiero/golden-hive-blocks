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
 * Design tokens: the store's colours (principale, scuro, chiaro), fonts
 * (titoli, testo) and title style (maiuscolo, peso), as the custom
 * properties every stylesheet reads (design-core.php says which). Nothing
 * belongs to one store: the defaults are neutral, and stores that ran the
 * plugin before these were settings get their old look saved once
 * (ghb_design_migrate_tokens), so updating changes nothing. Code can force
 * any of them: add_filter('ghb_design_tokens', fn($t) => ['accent' => '#1e40af'] + $t).
 *
 * "Filtri su desktop": the shop sidebar as the theme lays it out, or a drawer
 * opened from a "Filtri" button in the shop toolbar, with the products using
 * the full width. Phones keep the theme's own filter drawer either way. The
 * drawer is Shoptimizer's #secondary sidebar restyled by theme-bridge.css, so
 * the option exists only where that stylesheet loads.
 *
 * @package Golden_Hive_Blocks
 * @since   5.11.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/design-core.php';

/** Token key => the option that stores it. */
const GHB_TOKEN_OPTIONS = array(
    'accent'       => 'ghb_accent_color',
    'dark'         => 'ghb_color_dark',
    'light'        => 'ghb_color_light',
    'font_display' => 'ghb_font_display',
    'font_body'    => 'ghb_font_body',
    'title_case'   => 'ghb_title_case',
    'title_weight' => 'ghb_title_weight',
);

/** The store's tokens, cleaned (design-core.php). */
function ghb_design_tokens(): array
{
    $tokens = array();
    foreach (GHB_TOKEN_OPTIONS as $key => $option) {
        $tokens[$key] = get_option($option, GHB_TOKEN_DEFAULTS[$key]);
    }
    $tokens = (array) apply_filters('ghb_design_tokens', $tokens);
    foreach (GHB_TOKEN_DEFAULTS as $key => $default) {
        $tokens[$key] = ghb_token_clean($key, $tokens[$key] ?? $default);
    }
    return $tokens;
}

/** The store's accent colour, #rrggbb (emails and other places CSS can't reach). */
function ghb_design_accent(): string
{
    return ghb_design_tokens()['accent'];
}

/**
 * Once: a store that ran the plugin before the tokens were settings gets its
 * old look saved as settings (the burgundy accent, uppercase heavy titles),
 * so updating changes nothing, and the Customizer shows what the store looks
 * like. add_option leaves anything already saved alone. A new install keeps
 * the neutral defaults.
 */
function ghb_design_migrate_tokens()
{
    if (get_option('ghb_tokens_version')) {
        return;
    }
    $traces = array('ghb_accent_color', 'ghb_rail_cache_gen', 'ghb_sp_generation', 'ghb_design_shape', 'ghb_filters_layout',
        'ghb_sold_out_last', 'ghb_size_arrivals', 'ghb_whatsapp_number', 'ghb_pp_sold_count', 'ghb_delivery_cutoff');
    foreach ($traces as $option) {
        if (false !== get_option($option, false)) {
            foreach (GHB_TOKEN_LEGACY as $key => $value) {
                add_option(GHB_TOKEN_OPTIONS[$key], $value);
            }
            break;
        }
    }
    update_option('ghb_tokens_version', 1);
}
add_action('init', 'ghb_design_migrate_tokens', 1);

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

/**
 * theme-bridge.css loads: Shoptimizer as the parent theme (theme module
 * 'theme-bridge'), unless add_filter('ghb_design_theme_bridge',
 * '__return_false') turns it off.
 */
function ghb_design_theme_bridge_enabled(): bool
{
    return ghb_theme_module('theme-bridge') && apply_filters('ghb_design_theme_bridge', true);
}

/**
 * The drawer layout applies to product archives only, and needs
 * theme-bridge.css: without it the "Filtri" button would open nothing.
 */
function ghb_filters_drawer_active(): bool
{
    return 'drawer' === ghb_filters_layout()
        && ghb_design_theme_bridge_enabled()
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

    $token_setting = function (string $key) use ($wp_customize) {
        $wp_customize->add_setting(GHB_TOKEN_OPTIONS[$key], array(
            'type'              => 'option',
            'capability'        => 'edit_theme_options',
            'default'           => GHB_TOKEN_DEFAULTS[$key],
            'transport'         => 'refresh',
            'sanitize_callback' => function ($value) use ($key) {
                return ghb_token_clean($key, $value);
            },
        ));
    };
    $colours = array(
        'accent' => array('Colore principale', 'Pulsanti, prezzi, etichette sopra i titoli, taglie selezionate. Deve leggersi bene su chiaro e su scuro.'),
        'dark'   => array('Colore scuro', 'Testi e sezioni scure.'),
        'light'  => array('Colore chiaro', 'Sfondi chiari e testi sulle sezioni scure.'),
    );
    foreach ($colours as $key => $copy) {
        $token_setting($key);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, GHB_TOKEN_OPTIONS[$key], array(
            'section'     => 'ghb_design',
            'label'       => $copy[0],
            'description' => $copy[1],
        )));
    }
    $fonts = array(
        'font_display' => array('Font dei titoli', 'Il nome del font, es. "Bebas Neue", sans-serif. Vuoto: quello del tema. Il font deve essere già caricato sul sito (dal tema o da un plugin).'),
        'font_body'    => array('Font del testo', 'Come sopra, per i testi dei blocchi. Vuoto: quello del tema.'),
    );
    foreach ($fonts as $key => $copy) {
        $token_setting($key);
        $wp_customize->add_control(GHB_TOKEN_OPTIONS[$key], array(
            'section'     => 'ghb_design',
            'type'        => 'text',
            'label'       => $copy[0],
            'description' => $copy[1],
        ));
    }
    $token_setting('title_case');
    $wp_customize->add_control(GHB_TOKEN_OPTIONS['title_case'], array(
        'section' => 'ghb_design',
        'type'    => 'radio',
        'label'   => 'Titoli delle sezioni',
        'choices' => array(
            'none'      => 'Come sono scritti',
            'uppercase' => 'Tutto maiuscolo',
        ),
    ));
    $token_setting('title_weight');
    $wp_customize->add_control(GHB_TOKEN_OPTIONS['title_weight'], array(
        'section' => 'ghb_design',
        'type'    => 'select',
        'label'   => 'Spessore dei titoli',
        'choices' => array(
            '400' => 'Normale (400)',
            '500' => 'Medio (500)',
            '600' => 'Semi-grassetto (600)',
            '700' => 'Grassetto (700)',
            '800' => 'Extra-grassetto (800)',
            '900' => 'Nero (900)',
        ),
    ));

    $wp_customize->add_setting('ghb_rail_nav', array(
        'type'              => 'option',
        'capability'        => 'edit_theme_options',
        'default'           => 'below',
        'transport'         => 'refresh',
        'sanitize_callback' => function ($value) {
            return in_array($value, array('below', 'sides'), true) ? $value : 'below';
        },
    ));
    $wp_customize->add_control('ghb_rail_nav', array(
        'section'     => 'ghb_design',
        'type'        => 'radio',
        'label'       => 'Frecce delle sezioni prodotto',
        'description' => 'Su telefono le sezioni si scorrono col dito in entrambi i casi.',
        'choices'     => array(
            'below' => 'Sotto la fila, con la linea di avanzamento',
            'sides' => 'Ai lati, sopra le foto',
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
        'section'         => 'ghb_design',
        'type'            => 'radio',
        'label'           => 'Filtri del negozio su desktop',
        'description'     => 'Con il pannello, i prodotti occupano tutta la larghezza e i filtri si aprono dal pulsante «Filtri». Su telefono non cambia nulla.',
        'choices'         => array(
            'sidebar' => 'Barra laterale sempre aperta',
            'drawer'  => 'Pulsante «Filtri» e pannello a scomparsa',
        ),
        // The drawer is theme-bridge.css: hidden where that doesn't load.
        'active_callback' => 'ghb_design_theme_bridge_enabled',
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
 * The shape and the design tokens: custom properties. html:root outranks the
 * stylesheet's :root defaults whatever the order they are printed in.
 */
function ghb_design_print_shape()
{
    echo '<style id="ghb-shape">html:root{--gh-round:' . ('sharp' === ghb_design_shape() ? '0' : '1') . "}</style>\n";
    echo '<style id="ghb-tokens">' . ghb_design_tokens_css(ghb_design_tokens()) . "</style>\n";
}
add_action('wp_head', 'ghb_design_print_shape', 2);

/** The same tokens in the block editor, so blocks look there as on the site. */
function ghb_design_editor_tokens()
{
    wp_add_inline_style('golden-hive-blocks-editor', ghb_design_tokens_css(ghb_design_tokens()));
}
add_action('enqueue_block_editor_assets', 'ghb_design_editor_tokens', 20);

/**
 * Shoptimizer's own surfaces on the same scale, and the drawer layout.
 * Enqueued late so it follows the theme.
 */
function ghb_design_theme_bridge_assets()
{
    if (!ghb_design_theme_bridge_enabled()) {
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
    echo '<button type="button" class="ghb-filters-toggle ghb-filter-trigger" aria-controls="secondary" aria-expanded="false">'
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
