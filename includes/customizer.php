<?php
/**
 * Golden Hive — the Customizer panel (Aspetto → Personalizza → Golden Hive).
 *
 * Every setting the plugin has sits in one panel, four sections:
 *
 *   Negozio           the store's details: includes/store.php
 *   Stile             design tokens and switches: includes/design.php
 *   Prodotti e filtri product rails and shop filters: includes/product-rail.php,
 *                     includes/filters.php, includes/archive-hero.php
 *   Pagina prodotto   the product page additions: includes/product-page.php
 *
 * This file adds the panel and its sections (each feature file adds its own
 * settings to them), the two controls the Customizer lacks
 * (includes/customizer-controls.php: a list of rows and a checklist,
 * behaviour in js/customizer-controls.js), and a page-cache purge after any
 * Golden Hive setting is saved: pages are cached, and a store's new address
 * must show at once.
 *
 * Nothing to switch off: without the Customizer the settings keep their saved
 * values and their defaults.
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The panel's sections, in order: id => [title, description]. */
function ghb_customize_sections(): array
{
    return array(
        'ghb_store'        => array(
            'Negozio',
            'I dati del negozio, scritti una volta sola. Contatti, Vieni in negozio, Orari, Mappa, WhatsApp, Social, Instagram e Badge di fiducia li usano quando la pagina non ne scrive di suoi.',
        ),
        'ghb_design'       => array('Stile', ''),
        'ghb_catalog'      => array(
            'Prodotti e filtri',
            'Le sezioni prodotto usano questi valori quando lo shortcode non ne scrive di suoi. Sotto, i filtri del negozio.',
        ),
        'ghb_product_page' => array('Pagina prodotto', ''),
    );
}

function ghb_customize_panel(WP_Customize_Manager $wp_customize): void
{
    require_once __DIR__ . '/customizer-controls.php';

    $wp_customize->add_panel('ghb', array(
        'title'    => 'Golden Hive',
        'priority' => 30,
    ));
    $priority = 10;
    foreach (ghb_customize_sections() as $id => $copy) {
        $wp_customize->add_section($id, array(
            'title'       => $copy[0],
            'description' => $copy[1],
            'panel'       => 'ghb',
            'priority'    => $priority,
        ));
        $priority += 10;
    }
}
add_action('customize_register', 'ghb_customize_panel', 5);

/**
 * One option-backed setting, refreshed in the preview. Shop managers edit the
 * store's settings (manage_woocommerce), as the product page ones always did.
 */
function ghb_customize_setting(WP_Customize_Manager $wp_customize, string $id, $default, callable $sanitize, array $extra = array()): void
{
    $wp_customize->add_setting($id, array_merge(array(
        'type'              => 'option',
        'capability'        => 'manage_woocommerce',
        'default'           => $default,
        'transport'         => 'refresh',
        'sanitize_callback' => $sanitize,
    ), $extra));
}

/** A checkbox kept as yes / no, like WooCommerce's own options. */
function ghb_customize_checkbox(WP_Customize_Manager $wp_customize, string $section, string $id, string $default, string $label, string $description = ''): void
{
    $to_bool = static function ($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return is_scalar($value) && in_array(strtolower((string) $value), array('yes', 'true', '1'), true);
    };
    ghb_customize_setting($wp_customize, $id, $default, static function ($value) use ($to_bool) {
        return $to_bool($value) ? 'yes' : 'no';
    }, array('sanitize_js_callback' => $to_bool));
    $wp_customize->add_control($id, array(
        'section'     => $section,
        'type'        => 'checkbox',
        'label'       => $label,
        'description' => $description,
    ));
}

/** The list and checklist controls' behaviour and look. */
function ghb_customize_controls_assets(): void
{
    wp_enqueue_script('ghb-customizer-controls', gh_asset_url('js/customizer-controls.js'), array('customize-controls'), GOLDEN_HIVE_BLOCKS_VERSION, true);
    wp_enqueue_style('ghb-customizer-controls', gh_asset_url('customizer.css'), array('customize-controls'), GOLDEN_HIVE_BLOCKS_VERSION);
}
add_action('customize_controls_enqueue_scripts', 'ghb_customize_controls_assets');

/** Saved a Golden Hive setting: every cached page may show it, so empty the caches. */
function ghb_customize_purge_caches(WP_Customize_Manager $manager): void
{
    foreach (array_keys($manager->unsanitized_post_values()) as $id) {
        if (0 === strpos((string) $id, 'ghb_')) {
            ghb_purge_page_caches();
            return;
        }
    }
}
add_action('customize_save_after', 'ghb_customize_purge_caches');

/**
 * Whether this store ran the plugin before 5.20: a setting or a counter only
 * an earlier version writes, or content with Golden Hive blocks. Asked by the
 * one-time moves of 5.20 (store.php, filters.php, product-page.php), and
 * worked out once per request, before any of them writes.
 */
function ghb_plugin_ran_before(): bool
{
    static $ran = null;
    if (null !== $ran) {
        return $ran;
    }
    $traces = array(
        'ghb_accent_color', 'ghb_color_dark', 'ghb_color_light', 'ghb_font_display', 'ghb_font_body', 'ghb_title_case',
        'ghb_title_weight', 'ghb_design_shape', 'ghb_rail_nav', 'ghb_filters_layout', 'ghb_rail_cache_gen', 'ghb_sp_generation',
        'ghb_sold_out_last', 'ghb_size_arrivals', 'ghb_whatsapp_number', 'ghb_pp_colorways', 'ghb_pp_band', 'ghb_pp_recent',
        'ghb_pp_sold_count', 'ghb_delivery_estimate', 'ghb_delivery_cutoff',
    );
    foreach ($traces as $option) {
        if (false !== get_option($option, false)) {
            return $ran = true;
        }
    }
    return $ran = array() !== ghb_content_with_blocks(1);
}

/**
 * Content that holds Golden Hive blocks, parsed: pages, posts, synced
 * patterns and block templates in any state but trashed, then block widgets.
 * Each item: id (0 for a widget), title, blocks.
 *
 * @return array<int, array{id:int, title:string, blocks:array}>
 */
function ghb_content_with_blocks(int $limit = 0): array
{
    global $wpdb;
    $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts}
            WHERE post_type IN ('page', 'post', 'wp_block', 'wp_template', 'wp_template_part')
              AND post_status IN ('publish', 'private', 'future', 'draft', 'pending')
              AND post_content LIKE '%wp:golden-hive/%'
            ORDER BY ID";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $items = array();
    foreach ((array) $wpdb->get_results($sql) as $row) {
        $items[] = array('id' => (int) $row->ID, 'title' => (string) $row->post_title, 'blocks' => parse_blocks((string) $row->post_content));
    }
    if ($limit > 0 && count($items) >= $limit) {
        return $items;
    }
    foreach ((array) get_option('widget_block', array()) as $widget) {
        $content = is_array($widget) ? (string) ($widget['content'] ?? '') : '';
        if (false !== strpos($content, 'wp:golden-hive/')) {
            $items[] = array('id' => 0, 'title' => __('Widget', 'golden-hive-blocks'), 'blocks' => parse_blocks($content));
        }
    }
    return $limit > 0 ? array_slice($items, 0, $limit) : $items;
}

/* -------------------------------------------------------------------- *
 * One notice for the shop owner after an update
 * -------------------------------------------------------------------- */

/**
 * Add a paragraph to the update notice: a plain-text title and lines, plus an
 * optional Customizer section to open. Shown to shop managers until one of
 * them dismisses it. Used once, by the one-time moves.
 */
function ghb_upgrade_notice_add(string $key, string $title, array $lines = array(), string $section = ''): void
{
    $notice       = (array) get_option('ghb_upgrade_notice', array());
    $notice[$key] = array('title' => $title, 'lines' => array_values(array_map('strval', $lines)), 'section' => $section);
    update_option('ghb_upgrade_notice', $notice, false);
}

function ghb_upgrade_notice_render(): void
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    $notice = get_option('ghb_upgrade_notice');
    if (!$notice || !is_array($notice)) {
        return;
    }
    $dismiss = wp_nonce_url(add_query_arg('ghb-dismiss-notice', '1'), 'ghb_dismiss_notice');
    echo '<div class="notice notice-warning"><p><strong>Golden Hive</strong></p>';
    foreach ($notice as $part) {
        echo '<p>' . esc_html((string) ($part['title'] ?? '')) . '</p>';
        if (!empty($part['lines'])) {
            echo '<ul style="list-style:disc;margin-left:2em">';
            foreach ((array) $part['lines'] as $line) {
                echo '<li>' . esc_html((string) $line) . '</li>';
            }
            echo '</ul>';
        }
        if (!empty($part['section'])) {
            $url = admin_url('customize.php?autofocus[section]=' . rawurlencode((string) $part['section']));
            echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Apri in Personalizza', 'golden-hive-blocks') . '</a></p>';
        }
    }
    echo '<p><a href="' . esc_url($dismiss) . '">' . esc_html__('Fatto, nascondi questo avviso', 'golden-hive-blocks') . '</a></p></div>';
}
add_action('admin_notices', 'ghb_upgrade_notice_render');

function ghb_upgrade_notice_dismiss(): void
{
    if (!isset($_GET['ghb-dismiss-notice']) || !current_user_can('manage_woocommerce')) {
        return;
    }
    check_admin_referer('ghb_dismiss_notice');
    delete_option('ghb_upgrade_notice');
    wp_safe_redirect(remove_query_arg(array('ghb-dismiss-notice', '_wpnonce')));
    exit;
}
add_action('admin_init', 'ghb_upgrade_notice_dismiss');
