<?php
/**
 * Golden Hive — product rails' defaults, pure core.
 *
 * Plain PHP, no WordPress call: the store's rail settings (Customizer →
 * Golden Hive → Prodotti e filtri, read in product-rail.php), cleaned into the
 * values [gh_product_rail] uses for any layout attribute a shortcode leaves
 * out, and how a photo ratio reads as CSS. The defaults are what the rails
 * have always done. Unit-tested in tests/product-rail-test.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A rail when neither the shortcode nor the store says otherwise.
 * columns_tablet '' = as on desktop; columns_mobile '' = two cards and a
 * peek of the next (product-rail.css).
 */
const GHB_RAIL_DEFAULTS = array(
    'columns'        => 4,
    'columns_tablet' => '',
    'columns_mobile' => '',
    'limit'          => 12,
    'ratio'          => 'square',
    'fit'            => 'contain',
    'hover'          => 'yes',
);

/** Photo ratios by name, as CSS. */
const GHB_RAIL_RATIOS = array(
    'square'    => '1 / 1',
    'portrait'  => '3 / 4',
    'tall'      => '4 / 5',
    'landscape' => '4 / 3',
    'wide'      => '16 / 9',
);

/** Most products a rail can be asked for in the settings (the Store Hub's own cap). */
const GHB_RAIL_MAX_LIMIT = 100;

/** One rail setting, cleaned; anything unusable is its default. */
function ghb_rail_setting_clean(string $key, $value)
{
    if (!array_key_exists($key, GHB_RAIL_DEFAULTS)) {
        return null;
    }
    $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
    switch ($key) {
        case 'columns':
            return preg_match('/^[1-8]$/', $value) ? (int) $value : GHB_RAIL_DEFAULTS['columns'];
        case 'columns_tablet':
            return preg_match('/^[1-8]$/', $value) ? (int) $value : '';
        case 'columns_mobile':
            return preg_match('/^[1-4]$/', $value) ? (int) $value : '';
        case 'limit':
            return ctype_digit($value) && (int) $value >= 1 ? min(GHB_RAIL_MAX_LIMIT, (int) $value) : GHB_RAIL_DEFAULTS['limit'];
        case 'ratio':
            return isset(GHB_RAIL_RATIOS[$value]) ? $value : GHB_RAIL_DEFAULTS['ratio'];
        case 'fit':
            return in_array($value, array('cover', 'contain'), true) ? $value : GHB_RAIL_DEFAULTS['fit'];
        case 'hover':
            return in_array($value, array('no', '0', 'false'), true) ? 'no' : 'yes';
    }
    return null;
}

/** Every rail setting, from the saved option (missing keys take their defaults). */
function ghb_rail_settings(array $saved): array
{
    $rail = array();
    foreach (GHB_RAIL_DEFAULTS as $key => $default) {
        $rail[$key] = array_key_exists($key, $saved) ? ghb_rail_setting_clean($key, $saved[$key]) : $default;
    }
    return $rail;
}

/** A photo ratio as CSS ("3 / 4"): a name or "N/N"; a square for anything else. */
function ghb_rail_ratio_css(string $ratio): string
{
    $ratio = strtolower(trim($ratio));
    if (isset(GHB_RAIL_RATIOS[$ratio])) {
        return GHB_RAIL_RATIOS[$ratio];
    }
    if (preg_match('#^(\d{1,2})\s*/\s*(\d{1,2})$#', $ratio, $m) && (int) $m[1] > 0 && (int) $m[2] > 0) {
        return $m[1] . ' / ' . $m[2];
    }
    return GHB_RAIL_RATIOS['square'];
}
