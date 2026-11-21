<?php
/**
 * Golden Hive — the store's details (Customizer → Golden Hive → Negozio).
 *
 * One place for what store pages repeat: address, a one-line presentation
 * of the shop, phone, WhatsApp number and message, email, P.IVA and legal
 * name, social profiles, opening hours and closures, the map, the trust
 * badges. Blocks use them for any attribute a page leaves out (Contact Info,
 * Vieni in negozio, Orari, Map Embed, WhatsApp Button, Social Buttons,
 * Instagram Feed, Trust Badges: GHB_STORE_BLOCK_FIELDS), so a store types its
 * address once and every page shows it, while a page that writes its own
 * value, even an empty one, keeps it. The block editor shows the store's
 * values as placeholders.
 *
 * Every default is empty: no store ever shows another's details. Updating
 * from an earlier version moves nothing from the code: ghb_store_migrate()
 * gives a store that never saved a WhatsApp number the one its own WhatsApp
 * buttons use, and lists in an admin notice the blocks that relied on a
 * default the plugin no longer has, so the owner knows what to fill in.
 *
 * Code can change any detail: add_filter('ghb_store', fn($s) => ['phone' => '…'] + $s).
 * The rules are in store-core.php (tests/store-test.php).
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/store-core.php';

/** The WhatsApp number, digits with the country code; '' when the store has none (no WhatsApp links). */
function ghb_whatsapp_number(): string
{
    return ghb_store_digits((string) apply_filters('ghb_whatsapp_number', (string) get_option('ghb_whatsapp_number', '')));
}

/**
 * The store's details, cleaned: GHB_STORE_DEFAULTS' keys, plus 'whatsapp'
 * (the number) and 'badges' (the trust badges).
 */
function ghb_store(): array
{
    $saved = get_option('ghb_store', array());
    $store = ghb_store_clean_all(is_array($saved) ? $saved : array());
    $store['whatsapp'] = ghb_whatsapp_number();
    $store['badges']   = ghb_store_clean_badges(get_option('ghb_store_badges', array()));
    $store = (array) apply_filters('ghb_store', $store);
    $store['badges'] = ghb_store_clean_badges($store['badges'] ?? array());
    return $store;
}

/**
 * A block attribute as the page shows it: the page's own value when it wrote
 * one (even empty), the store's when it left it out. For the attributes in
 * GHB_STORE_BLOCK_FIELDS; any other is returned as written, or null.
 */
function ghb_store_attr(array $attributes, string $block, string $attribute)
{
    if (array_key_exists($attribute, $attributes)) {
        return $attributes[$attribute];
    }
    return ghb_store_block_value(ghb_store(), $block, $attribute);
}

/**
 * What a block attribute left out shows on this store; null when the
 * attribute doesn't follow the store. The Store Hub reads a block's fields
 * through it (hub-page.php), as the site renders them.
 */
function ghb_store_block_default(string $block, string $attribute)
{
    if (!isset(GHB_STORE_BLOCK_FIELDS[$block][$attribute])) {
        return null;
    }
    return ghb_store_block_value(ghb_store(), $block, $attribute);
}

/** A detail as typed in the Customizer: WordPress's sanitizer first, then the detail's own rules. */
function ghb_store_sanitize(string $key, $value): string
{
    $value = is_scalar($value) ? (string) $value : '';
    switch ($key) {
        case 'map':
            $value = gh_kses_map_iframe(trim($value));
            break;
        case 'email':
            $value = sanitize_email($value);
            break;
        case 'instagram':
        case 'facebook':
        case 'tiktok':
        case 'youtube':
        case 'x':
            $value = esc_url_raw(trim($value), array('http', 'https'));
            break;
        case 'closed':
            $value = sanitize_textarea_field($value);
            break;
        default:
            $value = sanitize_text_field($value);
    }
    return ghb_store_clean($key, $value);
}

/* -------------------------------------------------------------------- *
 * Customizer
 * -------------------------------------------------------------------- */

function ghb_store_customize_register(WP_Customize_Manager $wp_customize): void
{
    $priority = 0;
    $detail   = static function (string $key, string $label, string $description = '', string $type = 'text', array $input_attrs = array()) use ($wp_customize, &$priority) {
        $id = 'ghb_store[' . $key . ']';
        ghb_customize_setting($wp_customize, $id, GHB_STORE_DEFAULTS[$key], static function ($value) use ($key) {
            return ghb_store_sanitize($key, $value);
        });
        $wp_customize->add_control($id, array(
            'section'     => 'ghb_store',
            'type'        => $type,
            'label'       => $label,
            'description' => $description,
            'input_attrs' => $input_attrs,
            'priority'    => $priority += 10,
        ));
    };

    $detail('address', 'Indirizzo', 'Come lo scriveresti su un biglietto: via, CAP, città. «Indicazioni» apre Google Maps verso questo indirizzo.');
    $detail('intro', 'Presentazione del negozio', 'Una frase sul negozio, sotto il titolo di «Vieni in negozio» e dei Contatti.', 'textarea');
    $detail('phone', 'Telefono', '', 'tel');

    ghb_customize_setting($wp_customize, 'ghb_whatsapp_number', '', static function ($value) {
        return ghb_store_digits(is_scalar($value) ? (string) $value : '');
    });
    $wp_customize->add_control('ghb_whatsapp_number', array(
        'section'     => 'ghb_store',
        'type'        => 'text',
        'label'       => 'Numero WhatsApp',
        'description' => 'Con il prefisso internazionale, senza + né spazi (es. 393331234567). Vuoto: nessun link WhatsApp, né sulla pagina prodotto né nei blocchi.',
        'priority'    => $priority += 10,
    ));
    $detail('whatsapp_message', 'Messaggio WhatsApp', 'Il messaggio già scritto del bottone WhatsApp, quando il blocco non ne ha uno suo.');
    $detail('email', 'Email', '', 'email');
    $detail('vat', 'Partita IVA', 'Solo il numero: i blocchi scrivono «P.IVA» davanti.');
    $detail('legal_name', 'Ragione sociale', 'Facoltativa: compare prima della partita IVA.');

    $socials = array('instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X (Twitter)');
    foreach ($socials as $key => $label) {
        $detail($key, $label, 'instagram' === $key ? 'Gli indirizzi completi dei profili (https://…). I bottoni social e il blocco Instagram usano questi.' : '', 'url');
    }

    foreach (GHB_STORE_DAYS as $n => $day) {
        $detail('hours_' . $n, 'Orari: ' . $day, 1 === $n ? 'Come «10.30 - 13.00 | 15.30 - 19.30» o «Chiuso». Un giorno vuoto è chiuso; tutti vuoti, nessun orario (negozio solo online).' : '');
    }
    ghb_customize_checkbox($wp_customize, 'ghb_store', 'ghb_store[holidays]', GHB_STORE_DEFAULTS['holidays'], 'Chiuso nei giorni festivi', 'Le festività nazionali, Pasquetta compresa.');
    $wp_customize->get_control('ghb_store[holidays]')->priority = $priority += 10;
    $detail('closed', 'Giorni di chiusura', 'Altre date in cui il negozio è chiuso e non spedisce, una per riga: 24/12/2026 oppure 2026-12-24.', 'textarea');
    $detail('hours_note', 'Nota sugli orari', 'Es. «Gli orari possono variare durante le festività.»');
    $detail('map', 'Mappa', 'Il codice da Google Maps → Condividi → Incorpora una mappa. Si carica solo quando il visitatore la apre.', 'textarea');

    ghb_customize_setting($wp_customize, 'ghb_store_badges', array(), 'ghb_store_sanitize_badges', array('sanitize_js_callback' => 'ghb_store_clean_badges'));
    $icons = array();
    foreach (GHB_STORE_BADGE_ICONS as $value => $label) {
        $icons[] = array($value, $label);
    }
    $wp_customize->add_control(new GHB_Customize_List_Control($wp_customize, 'ghb_store_badges', array(
        'section'     => 'ghb_store',
        'label'       => 'Badge di fiducia',
        'description' => 'Le promesse del negozio in breve, per il blocco Trust Badges quando la pagina non ne scrive di sue. Solo cose vere per questo negozio.',
        'fields'      => array(
            array('key' => 'icon', 'label' => 'Icona', 'type' => 'select', 'choices' => $icons),
            array('key' => 'title', 'label' => 'Testo', 'type' => 'text'),
        ),
        'max'         => GHB_STORE_MAX_BADGES,
        'add_label'   => 'Aggiungi un badge',
        'priority'    => $priority += 10,
    )));
}
add_action('customize_register', 'ghb_store_customize_register');

/** The badges list from the Customizer: titles through WordPress's sanitizer, then the rules. */
function ghb_store_sanitize_badges($value): array
{
    if (is_string($value)) {
        $value = json_decode($value, true);
    }
    $rows = array();
    foreach (is_array($value) ? $value : array() as $row) {
        if (is_array($row)) {
            $rows[] = array('icon' => sanitize_key((string) ($row['icon'] ?? '')), 'title' => sanitize_text_field((string) ($row['title'] ?? '')));
        }
    }
    return ghb_store_clean_badges($rows);
}

/* -------------------------------------------------------------------- *
 * Block editor: the store's values as placeholders
 * -------------------------------------------------------------------- */

function ghb_store_editor_data(): void
{
    $store = ghb_store();
    $data  = array(
        'address'         => $store['address'],
        'intro'           => $store['intro'],
        'phone'           => $store['phone'],
        'email'           => $store['email'],
        'vat'             => ghb_store_vat_line($store),
        'whatsapp'        => $store['whatsapp'],
        'whatsappMessage' => $store['whatsapp_message'],
        'instagram'       => $store['instagram'],
        'instagramHandle' => ghb_store_instagram_handle($store['instagram']),
        'facebook'        => $store['facebook'],
        'tiktok'          => $store['tiktok'],
        'youtube'         => $store['youtube'],
        'x'               => $store['x'],
        'hours'           => ghb_store_hours_rows($store),
        'hoursNote'       => $store['hours_note'],
        'holidays'        => 'no' !== $store['holidays'],
        'closed'          => $store['closed'],
        'hasMap'          => '' !== $store['map'],
        'badges'          => $store['badges'],
        'customizeUrl'    => admin_url('customize.php?autofocus[section]=ghb_store'),
    );
    wp_add_inline_script('gh-editor-utils', 'window.ghStore = ' . wp_json_encode($data) . ';', 'before');
}
add_action('enqueue_block_editor_assets', 'ghb_store_editor_data');

/* -------------------------------------------------------------------- *
 * Updating from an earlier version: once
 * -------------------------------------------------------------------- */

/**
 * Once, after WooCommerce has registered its taxonomies. Copies nothing from
 * the code. From the store's own content:
 *   • a store that never saved a WhatsApp number gets the one its own
 *     WhatsApp buttons use (when they all use the same);
 *   • the blocks that left out an attribute whose default changed
 *     (GHB_STORE_FORMER_DEFAULTS) are listed in the update notice.
 */
function ghb_store_migrate(): void
{
    if (get_option('ghb_store_version')) {
        return;
    }
    update_option('ghb_store_version', 1);
    if (!ghb_plugin_ran_before()) {
        return;
    }

    $content = ghb_content_with_blocks();
    $all     = array();
    $lines   = array();
    $types   = WP_Block_Type_Registry::get_instance();
    foreach ($content as $item) {
        $all = array_merge($all, $item['blocks']);
        foreach (ghb_store_relied_defaults($item['blocks']) as $found) {
            list($name, $attrs) = $found;
            $type    = $types->get_registered($name);
            $names   = array_map(static function ($attribute) {
                return GHB_STORE_ATTR_NAMES[$attribute] ?? $attribute;
            }, $attrs);
            $lines[] = sprintf('«%s» — %s: %s', '' !== $item['title'] ? $item['title'] : '#' . $item['id'], $type ? $type->title : $name, implode(', ', $names));
        }
    }

    if (false === get_option('ghb_whatsapp_number', false)) {
        $number = ghb_store_whatsapp_from_blocks($all);
        if ('' !== $number) {
            add_option('ghb_whatsapp_number', $number);
        } else {
            ghb_upgrade_notice_add(
                'store_whatsapp',
                __('Nessun numero WhatsApp salvato: la pagina prodotto non mostra più «Chiedi su WhatsApp» finché non lo scrivi in Negozio.', 'golden-hive-blocks'),
                array(),
                'ghb_store'
            );
        }
    }

    if ($lines) {
        ghb_upgrade_notice_add(
            'store_blocks',
            __('I dati del negozio non sono più scritti nel plugin. Questi blocchi usavano un valore predefinito che ora viene dal negozio (indirizzo, orari, mappa, partita IVA, presentazione, Instagram, badge: compilali in Negozio e le pagine li riprendono da sole) o è un testo neutro (titoli e testi: scrivili nel blocco). Finché non li compili non si vedono.', 'golden-hive-blocks'),
            array_values(array_unique($lines)),
            'ghb_store'
        );
    }
    ghb_purge_page_caches();
}
add_action('init', 'ghb_store_migrate', 20);
