<?php
/**
 * Golden Hive — the store's details, pure core.
 *
 * Plain PHP, no WordPress call: what a store's details are (Customizer →
 * Golden Hive → Negozio, read in store.php), how each is cleaned, and the one
 * rule every block follows with them: an attribute the page leaves out takes
 * the store's value; an attribute the page writes, even empty, is the page's.
 * Nothing here belongs to any store: every default is empty, and the one-time
 * move to these settings works only from each store's own data.
 * Unit-tested in tests/store-test.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The details a store fills in, all empty to start with (the WhatsApp number
 * is its own option, ghb_whatsapp_number; the badges are ghb_store_badges).
 * hours_1 … hours_7 are Monday to Sunday, as typed: "10.30 - 13.00 | 15.30 -
 * 19.30" or "Chiuso".
 */
const GHB_STORE_DEFAULTS = array(
    'address'          => '',
    'intro'            => '',
    'phone'            => '',
    'email'            => '',
    'vat'              => '',
    'legal_name'       => '',
    'whatsapp_message' => 'Ciao! Vorrei avere informazioni su...',
    'instagram'        => '',
    'facebook'         => '',
    'tiktok'           => '',
    'youtube'          => '',
    'x'                => '',
    'hours_1'          => '',
    'hours_2'          => '',
    'hours_3'          => '',
    'hours_4'          => '',
    'hours_5'          => '',
    'hours_6'          => '',
    'hours_7'          => '',
    'holidays'         => 'yes',
    'closed'           => '',
    'hours_note'       => '',
    'map'              => '',
);

/** Monday (1) to Sunday (7), as the hours rows name them. */
const GHB_STORE_DAYS = array(1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica');

/** The trust badges' icons (blocks/trust-badges/render.php draws them). */
const GHB_STORE_BADGE_ICONS = array(
    'authentic' => 'Autenticità',
    'shipping'  => 'Spedizione',
    'returns'   => 'Reso',
    'secure'    => 'Pagamento sicuro',
    'support'   => 'Assistenza',
    'quality'   => 'Qualità',
);

const GHB_STORE_MAX_BADGES = 6;

/** Most characters each detail keeps. */
const GHB_STORE_MAX_LENGTH = array(
    'address' => 200, 'intro' => 300, 'phone' => 40, 'email' => 120, 'vat' => 40, 'legal_name' => 120,
    'whatsapp_message' => 300, 'closed' => 1000, 'hours_note' => 200, 'map' => 3000,
);

/**
 * Which block attributes follow which store detail when a page leaves them
 * out. 'whatsapp' is the WhatsApp number; 'badges' the trust badges.
 */
const GHB_STORE_BLOCK_FIELDS = array(
    'golden-hive/contact-info'    => array(
        'subtitle' => 'intro', 'address' => 'address', 'vat' => 'vat', 'phone' => 'phone', 'email' => 'email', 'mapEmbed' => 'map',
    ),
    'golden-hive/store-visit'     => array(
        'subtitle' => 'intro', 'address' => 'address', 'vat' => 'vat', 'phone' => 'phone', 'hours' => 'hours',
        'note' => 'hours_note', 'closedOnHolidays' => 'holidays', 'closedDates' => 'closed', 'mapEmbed' => 'map',
    ),
    'golden-hive/store-hours'     => array(
        'hours' => 'hours', 'note' => 'hours_note', 'closedOnHolidays' => 'holidays', 'closedDates' => 'closed',
    ),
    'golden-hive/map-embed'       => array('embedCode' => 'map', 'address' => 'address'),
    'golden-hive/whatsapp-button' => array('phoneNumber' => 'whatsapp', 'message' => 'whatsapp_message'),
    'golden-hive/social-buttons'  => array(
        'instagram' => 'instagram', 'facebook' => 'facebook', 'tiktok' => 'tiktok', 'youtube' => 'youtube', 'twitter' => 'x',
    ),
    'golden-hive/instagram-feed'  => array('instagramUrl' => 'instagram', 'buttonText' => 'instagram_handle'),
    'golden-hive/trust-badges'    => array('badges' => 'badges'),
);

/** The details' names in the Customizer (docs/blocks.md and the editor show them). */
const GHB_STORE_LABELS = array(
    'address' => 'Indirizzo', 'intro' => 'Presentazione del negozio', 'phone' => 'Telefono', 'email' => 'Email',
    'vat' => 'Partita IVA', 'legal_name' => 'Ragione sociale', 'whatsapp' => 'Numero WhatsApp',
    'whatsapp_message' => 'Messaggio WhatsApp', 'instagram' => 'Instagram', 'instagram_handle' => 'Instagram',
    'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X (Twitter)', 'hours' => 'Orari',
    'holidays' => 'Chiuso nei giorni festivi', 'closed' => 'Giorni di chiusura', 'hours_note' => 'Nota sugli orari',
    'map' => 'Mappa', 'badges' => 'Badge di fiducia',
);

/**
 * Attributes whose default changed in 5.20: before, a page that left one out
 * showed a value written in the plugin; now it shows the store's own detail,
 * or a neutral text. The one-time move lists the blocks that left them out,
 * so the shop owner knows what to fill in. Only the names: no old value.
 */
const GHB_STORE_FORMER_DEFAULTS = array(
    'golden-hive/contact-info'      => array('subtitle', 'address', 'vat'),
    'golden-hive/store-visit'       => array('subtitle', 'address', 'hours', 'mapEmbed'),
    'golden-hive/store-hours'       => array('hours'),
    'golden-hive/map-embed'         => array('embedCode'),
    'golden-hive/instagram-feed'    => array('subtitle', 'instagramUrl', 'buttonText'),
    'golden-hive/about-hero'        => array('title', 'text'),
    'golden-hive/trust-badges'      => array('badges'),
    'golden-hive/trustpilot-widget' => array('rating', 'reviewCount', 'label'),
    // Sample texts that promised what a store may not do (free shipping over
    // an amount, a coupon, delivery days, a price): neutral now.
    'golden-hive/cta-banner'        => array('title', 'text'),
    'golden-hive/promo-modal'       => array('title', 'text', 'couponCode', 'buttonText', 'disclaimer'),
    'golden-hive/faq-schema'        => array('items'),
    'golden-hive/product-spotlight' => array('currentPrice'),
);

/** How the update notice names an attribute. */
const GHB_STORE_ATTR_NAMES = array(
    'subtitle' => 'sottotitolo', 'address' => 'indirizzo', 'vat' => 'partita IVA', 'hours' => 'orari',
    'mapEmbed' => 'mappa', 'embedCode' => 'mappa', 'instagramUrl' => 'link a Instagram', 'buttonText' => 'testo del pulsante',
    'title' => 'titolo', 'text' => 'testo', 'badges' => 'badge', 'rating' => 'valutazione', 'reviewCount' => 'numero di recensioni',
    'label' => 'giudizio', 'items' => 'domande', 'couponCode' => 'codice sconto', 'currentPrice' => 'prezzo',
    'disclaimer' => 'condizioni',
);

function ghb_store_truncate(string $value, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

/** Digits only: a WhatsApp number with its country code. */
function ghb_store_digits(string $value): string
{
    return (string) preg_replace('/\D+/', '', $value);
}

/** The number of a P.IVA typed with its label ("P.IVA: 0123…", "Partita IVA 0123…"). */
function ghb_store_vat_number(string $value): string
{
    $value = (string) preg_replace('/^\s*(?:p\.?\s*iva|partita\s+iva|vat(?:\s+number)?)\s*[:.]?\s*/iu', '', $value);
    return trim((string) preg_replace('/[^A-Za-z0-9 ]+/', '', $value));
}

/** One detail, cleaned; anything unusable becomes its default. */
function ghb_store_clean(string $key, $value): string
{
    if (!array_key_exists($key, GHB_STORE_DEFAULTS)) {
        return '';
    }
    $value = is_scalar($value) ? trim((string) $value) : '';
    $max   = GHB_STORE_MAX_LENGTH[$key] ?? 80;

    switch ($key) {
        case 'map':
            // The iframe itself is cleaned by WordPress on save (store.php).
            return false !== stripos($value, '<iframe') ? ghb_store_truncate($value, $max) : '';
        case 'instagram':
        case 'facebook':
        case 'tiktok':
        case 'youtube':
        case 'x':
            return preg_match('#^https?://[^\s"<>]+$#i', $value) ? ghb_store_truncate($value, 300) : '';
        case 'email':
            return false !== filter_var($value, FILTER_VALIDATE_EMAIL) ? ghb_store_truncate($value, $max) : '';
        case 'phone':
            return ghb_store_truncate(trim((string) preg_replace('/[^0-9+()\/.\s-]+/', '', $value)), $max);
        case 'vat':
            return ghb_store_truncate(ghb_store_vat_number($value), $max);
        case 'holidays':
            return in_array(strtolower($value), array('yes', '1', 'true'), true) ? 'yes'
                : (in_array(strtolower($value), array('no', '0', 'false', ''), true) ? 'no' : GHB_STORE_DEFAULTS['holidays']);
        case 'closed':
            $lines = preg_split('/\R/', strip_tags($value)) ?: array();
            return ghb_store_truncate(implode("\n", array_filter(array_map('trim', $lines), 'strlen')), $max);
    }
    // Plain one-line text: address, presentation, names, hours, notes, the message.
    return ghb_store_truncate(trim((string) preg_replace('/\s+/u', ' ', strip_tags($value))), $max);
}

/** Every detail, cleaned, from the saved option (missing keys take their defaults). */
function ghb_store_clean_all(array $saved): array
{
    $store = array();
    foreach (GHB_STORE_DEFAULTS as $key => $default) {
        $store[$key] = array_key_exists($key, $saved) ? ghb_store_clean($key, $saved[$key]) : $default;
    }
    return $store;
}

/** The trust badges, cleaned: icon (one of GHB_STORE_BADGE_ICONS) and text; empty rows dropped. */
function ghb_store_clean_badges($raw): array
{
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    $badges = array();
    foreach (is_array($raw) ? $raw : array() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = ghb_store_truncate(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($row['title'] ?? '')))), 60);
        if ('' === $title) {
            continue;
        }
        $icon     = (string) ($row['icon'] ?? '');
        $badges[] = array('icon' => isset(GHB_STORE_BADGE_ICONS[$icon]) ? $icon : 'authentic', 'title' => $title);
        if (count($badges) >= GHB_STORE_MAX_BADGES) {
            break;
        }
    }
    return $badges;
}

/** "P.IVA 01234567890", after the legal name when there is one; '' without a P.IVA. */
function ghb_store_vat_line(array $store): string
{
    $vat = (string) ($store['vat'] ?? '');
    if ('' === $vat) {
        return '';
    }
    $name = (string) ($store['legal_name'] ?? '');
    return ('' !== $name ? $name . ' · ' : '') . 'P.IVA ' . $vat;
}

/** "@name" for an Instagram profile link; '' for anything else (a post, another site). */
function ghb_store_instagram_handle(string $url): string
{
    if (!preg_match('#^(?:https?://)?(?:www\.)?instagram\.com/([A-Za-z0-9._]{1,30})/?(?:[?\#].*)?$#i', trim($url), $m)) {
        return '';
    }
    return in_array(strtolower($m[1]), array('p', 'reel', 'reels', 'explore', 'stories', 'tv', 'accounts'), true) ? '' : '@' . $m[1];
}

/**
 * The opening hours as block rows ({day, time}): all seven days once any is
 * filled, an empty day closed. None at all when every day is empty (a store
 * with no shop to visit).
 */
function ghb_store_hours_rows(array $store): array
{
    $times = array();
    foreach (GHB_STORE_DAYS as $n => $day) {
        $times[$n] = trim((string) ($store['hours_' . $n] ?? ''));
    }
    if ('' === implode('', $times)) {
        return array();
    }
    $rows = array();
    foreach (GHB_STORE_DAYS as $n => $day) {
        $rows[] = array('day' => $day, 'time' => '' !== $times[$n] ? $times[$n] : 'Chiuso');
    }
    return $rows;
}

/**
 * What a block attribute left out shows on this store, from the store's
 * details ($store: ghb_store_clean_all() plus 'whatsapp' and 'badges'); null
 * for an attribute that doesn't follow the store.
 *
 * @return string|bool|array|null
 */
function ghb_store_block_value(array $store, string $block, string $attribute)
{
    $field = GHB_STORE_BLOCK_FIELDS[$block][$attribute] ?? null;
    if (null === $field) {
        return null;
    }
    switch ($field) {
        case 'vat':
            return ghb_store_vat_line($store);
        case 'hours':
            return ghb_store_hours_rows($store);
        case 'holidays':
            return 'no' !== ($store['holidays'] ?? 'yes');
        case 'instagram_handle':
            return ghb_store_instagram_handle((string) ($store['instagram'] ?? ''));
        case 'badges':
            return isset($store['badges']) && is_array($store['badges']) ? $store['badges'] : array();
    }
    return (string) ($store[$field] ?? '');
}

/** The rule: an attribute the page wrote, even empty, is the page's; one it left out is the store's. */
function ghb_store_pick(array $attributes, string $attribute, $store_value)
{
    return array_key_exists($attribute, $attributes) ? $attributes[$attribute] : $store_value;
}

/* -------------------------------------------------------------------- *
 * The one-time move (store.php runs it on the store's own content)
 * -------------------------------------------------------------------- */

/** Every block of a parsed page, nested ones included, in page order. */
function ghb_store_walk_blocks(array $blocks): array
{
    $out = array();
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (!empty($block['blockName'])) {
            $out[] = $block;
        }
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $out = array_merge($out, ghb_store_walk_blocks($block['innerBlocks']));
        }
    }
    return $out;
}

/**
 * The WhatsApp number this store's own WhatsApp buttons use: '' unless every
 * button that writes a number writes the same one.
 */
function ghb_store_whatsapp_from_blocks(array $blocks): string
{
    $numbers = array();
    foreach (ghb_store_walk_blocks($blocks) as $block) {
        if ('golden-hive/whatsapp-button' !== $block['blockName']) {
            continue;
        }
        $number = ghb_store_digits((string) ($block['attrs']['phoneNumber'] ?? ''));
        if ('' !== $number) {
            $numbers['n' . $number] = $number;
        }
    }
    return 1 === count($numbers) ? (string) reset($numbers) : '';
}

/**
 * The blocks of a page that leave out an attribute whose default changed:
 * [[block name, [attributes]], …], in page order.
 */
function ghb_store_relied_defaults(array $blocks, array $former = GHB_STORE_FORMER_DEFAULTS): array
{
    $out = array();
    foreach (ghb_store_walk_blocks($blocks) as $block) {
        $name = (string) $block['blockName'];
        if (!isset($former[$name])) {
            continue;
        }
        $attrs   = is_array($block['attrs'] ?? null) ? $block['attrs'] : array();
        $missing = array_values(array_filter($former[$name], static function ($attribute) use ($attrs) {
            return !array_key_exists($attribute, $attrs);
        }));
        if ($missing) {
            $out[] = array($name, $missing);
        }
    }
    return $out;
}
