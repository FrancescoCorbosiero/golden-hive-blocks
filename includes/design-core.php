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
 *   hero_size     --gh-hero-scale: the opening titles (Hero Carousel, Split,
 *                 Video) 'large' (as they always were) | 'medium' | 'small'
 *   section_gutter --gh-section-gutter: space beside product sections,
 *                 'none' (the theme's container decides) | 'standard'
 *
 * @package Golden_Hive_Blocks
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Neutral defaults: what a store gets before it chooses. */
const GHB_TOKEN_DEFAULTS = array(
    'accent'         => '#111111',
    'dark'           => '#0a0a0a',
    'light'          => '#ffffff',
    'font_display'   => '',
    'font_body'      => '',
    'title_case'     => 'none',
    'title_weight'   => '700',
    'hero_size'      => 'large',
    'section_gutter' => 'none',
);

/** The opening titles' scale, by size. */
const GHB_HERO_SCALES = array('large' => '1', 'medium' => '0.82', 'small' => '0.68');

/** Space beside product sections, by choice. */
const GHB_SECTION_GUTTERS = array('none' => '0', 'standard' => 'clamp(16px, 3vw, 40px)');

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

/**
 * Darkening a near-black accent changes nothing you can see (black × 0.72 is
 * black), so below this luminance (0–1) the hover shade lightens instead:
 * each channel this far toward white (black becomes #2b2b2b).
 */
const GHB_ACCENT_DARK_LUMINANCE = 0.08;
const GHB_ACCENT_LIGHTEN        = 0.17;

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
        case 'hero_size':
            return isset(GHB_HERO_SCALES[$value]) ? $value : GHB_TOKEN_DEFAULTS[$key];
        case 'section_gutter':
            return isset(GHB_SECTION_GUTTERS[$value]) ? $value : GHB_TOKEN_DEFAULTS[$key];
    }
    return '';
}

/**
 * The accent's derived tokens: the colour, its hover shade, its RGB triplet.
 * The hover shade is darker, or lighter for an accent too dark to darken.
 */
function ghb_accent_tokens(string $hex): array
{
    $hex       = ghb_hex_normalize($hex) ?? GHB_TOKEN_DEFAULTS['accent'];
    $rgb       = array_map('hexdec', str_split(substr($hex, 1), 2));
    $luminance = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255;
    $lighten   = $luminance < GHB_ACCENT_DARK_LUMINANCE;
    $dark      = '#' . implode('', array_map(function ($c) use ($lighten) {
        $shade = $lighten ? $c + (255 - $c) * GHB_ACCENT_LIGHTEN : $c * GHB_ACCENT_DARKEN;
        return str_pad(dechex((int) round($shade)), 2, '0', STR_PAD_LEFT);
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
        '--gh-hero-scale'      => GHB_HERO_SCALES[$t['hero_size']],
        '--gh-section-gutter'  => GHB_SECTION_GUTTERS[$t['section_gutter']],
    );
    $css = '';
    foreach ($vars as $name => $value) {
        $css .= $name . ':' . $value . ';';
    }
    return 'html:root{' . rtrim($css, ';') . '}';
}
