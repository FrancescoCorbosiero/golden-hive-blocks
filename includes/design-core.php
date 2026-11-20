<?php
/**
 * Golden Hive — design tokens, pure core.
 *
 * Plain PHP, no WordPress call: the store's design settings (Customizer →
 * Golden Hive — Stile, read in design.php) turned into the custom properties
 * every stylesheet reads. Nothing here belongs to one store: the defaults are
 * neutral, and each store sets its own. Unit-tested in tests/design-test.php.
 *
 *   accent        --gh-accent, --gh-accent-dark (hovers), --gh-accent-rgb (tints):
 *                 buttons, prices, eyebrows, selected sizes, focus rings
 *   dark          --gh-black: text and dark sections
 *   light         --gh-white: light surfaces and text on dark
 *   font_display  --gh-font-display: titles ('' = the theme's)
 *   font_body     --gh-font-body: the blocks' text ('' = the theme's)
 *   title_case    --gh-title-transform (+ --gh-title-tracking): 'uppercase' | 'none'
 *   title_weight  --gh-title-weight: 400–900
 *
 * @package Golden_Hive_Blocks
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Neutral defaults: what a store gets before it chooses. */
const GHB_TOKEN_DEFAULTS = array(
    'accent'       => '#111111',
    'dark'         => '#0a0a0a',
    'light'        => '#ffffff',
    'font_display' => '',
    'font_body'    => '',
    'title_case'   => 'none',
    'title_weight' => '700',
);

/**
 * The plugin's look before the tokens were settings. Stores that already ran
 * the plugin get these saved once (design.php), so updating changes nothing.
 */
const GHB_TOKEN_LEGACY = array(
    'accent'       => '#721124',
    'title_case'   => 'uppercase',
    'title_weight' => '800',
);

/** How much darker the accent's hover shade is: each channel × this. */
const GHB_ACCENT_DARKEN = 0.72;

const GHB_TITLE_WEIGHTS = array('400', '500', '600', '700', '800', '900');

/** A hex colour as #rrggbb (lowercase), or null when it isn't one. Accepts #rgb. */
function ghb_hex_normalize(string $hex): ?string
{
    $hex = strtolower(trim($hex));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $hex, $m)) {
        return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }
    return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : null;
}

/**
 * A font-family list a person typed ("Inter", 'Bebas Neue', sans-serif), or ''
 * for the theme's. Only letters, digits, spaces, hyphens, commas and quotes
 * survive, so nothing can break out of the CSS declaration.
 */
function ghb_font_normalize(string $font): string
{
    $font = preg_replace('/[^A-Za-z0-9 ,\'"\-]/', '', $font);
    $font = trim(preg_replace('/\s+/', ' ', (string) $font), " ,");
    return substr($font, 0, 200);
}

/** One token, cleaned; anything invalid falls back to its default. */
function ghb_token_clean(string $key, $value): string
{
    $value = is_scalar($value) ? (string) $value : '';
    switch ($key) {
        case 'accent':
        case 'dark':
        case 'light':
            return ghb_hex_normalize($value) ?? GHB_TOKEN_DEFAULTS[$key];
        case 'font_display':
        case 'font_body':
            return ghb_font_normalize($value);
        case 'title_case':
            return in_array($value, array('uppercase', 'none'), true) ? $value : GHB_TOKEN_DEFAULTS[$key];
        case 'title_weight':
            return in_array($value, GHB_TITLE_WEIGHTS, true) ? $value : GHB_TOKEN_DEFAULTS[$key];
    }
    return '';
}

/** The accent's derived tokens: the colour, its hover shade, its RGB triplet. */
function ghb_accent_tokens(string $hex): array
{
    $hex = ghb_hex_normalize($hex) ?? GHB_TOKEN_DEFAULTS['accent'];
    $rgb = array_map('hexdec', str_split(substr($hex, 1), 2));
    $dark = '#' . implode('', array_map(function ($c) {
        return str_pad(dechex((int) round($c * GHB_ACCENT_DARKEN)), 2, '0', STR_PAD_LEFT);
    }, $rgb));
    return array('accent' => $hex, 'dark' => $dark, 'rgb' => implode(', ', $rgb));
}

/**
 * Every token as CSS, from the store's settings (missing keys take their
 * defaults). html:root outranks the stylesheet's :root defaults whatever the
 * order they are printed in.
 */
function ghb_design_tokens_css(array $settings): string
{
    $t = array();
    foreach (GHB_TOKEN_DEFAULTS as $key => $default) {
        $t[$key] = ghb_token_clean($key, $settings[$key] ?? $default);
    }
    $accent = ghb_accent_tokens($t['accent']);
    $upper  = 'uppercase' === $t['title_case'];

    $vars = array(
        '--gh-accent'          => $accent['accent'],
        '--gh-accent-dark'     => $accent['dark'],
        '--gh-accent-rgb'      => $accent['rgb'],
        '--gh-black'           => $t['dark'],
        '--gh-white'           => $t['light'],
        '--gh-font-display'    => '' === $t['font_display'] ? 'inherit' : $t['font_display'],
        '--gh-font-body'       => '' === $t['font_body'] ? 'inherit' : $t['font_body'],
        '--gh-title-transform' => $upper ? 'uppercase' : 'none',
        '--gh-title-tracking'  => $upper ? '0.02em' : '0',
        '--gh-title-weight'    => $t['title_weight'],
    );
    $css = '';
    foreach ($vars as $name => $value) {
        $css .= $name . ':' . $value . ';';
    }
    return 'html:root{' . rtrim($css, ';') . '}';
}
