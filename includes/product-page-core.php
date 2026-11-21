<?php
/**
 * Golden Hive — product page additions, pure core.
 *
 * Plain PHP, no WordPress call: the «Acquista con fiducia» tiles a store
 * writes (Customizer → Golden Hive → Pagina prodotto, read in
 * product-page.php), cleaned, and what the one-time move of 5.20 decides
 * for the "Altre colorway" rail. No tile is written here: every store writes
 * its own. Unit-tested in tests/product-page-test.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The tiles' icons (ghb_pp_icon() draws them). */
const GHB_PP_TRUST_ICONS = array(
    'authentic' => 'Autenticità',
    'shipping'  => 'Spedizione',
    'returns'   => 'Reso',
    'secure'    => 'Pagamento',
);

const GHB_PP_MAX_TILES = 4;

/** Plain text of at most $max characters. */
function ghb_pp_text($value, int $max): string
{
    $text = trim((string) preg_replace('/[ \t]+/u', ' ', strip_tags(is_scalar($value) ? (string) $value : '')));
    return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}

/**
 * The tiles, cleaned: icon (one of GHB_PP_TRUST_ICONS), title, the line
 * under it, the text a tap opens. A tile without a title is dropped; at most
 * four.
 */
function ghb_pp_clean_trust_items($raw): array
{
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    $items = array();
    foreach (is_array($raw) ? $raw : array() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = ghb_pp_text($row['title'] ?? '', 60);
        if ('' === $title) {
            continue;
        }
        $icon    = (string) ($row['icon'] ?? '');
        $items[] = array(
            'icon'  => isset(GHB_PP_TRUST_ICONS[$icon]) ? $icon : 'authentic',
            'title' => $title,
            'sub'   => ghb_pp_text($row['sub'] ?? '', 80),
            'text'  => ghb_pp_text($row['text'] ?? '', 600),
        );
        if (count($items) >= GHB_PP_MAX_TILES) {
            break;
        }
    }
    return $items;
}

/**
 * The attribute "Altre colorway" groups by, for a store moving to 5.20:
 * pa_modello (the attribute the code used) when the rail was on and the
 * store has it; none otherwise, as the rail showed nothing there.
 */
function ghb_pp_colorways_before_520(bool $was_on, array $taxonomies): string
{
    return $was_on && in_array('pa_modello', $taxonomies, true) ? 'pa_modello' : '';
}
