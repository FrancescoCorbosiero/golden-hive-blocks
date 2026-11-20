<?php
/**
 * Golden Hive — design tokens, pure core.
 *
 * Plain PHP, no WordPress call: the store's accent colour as the custom
 * properties every stylesheet reads (--gh-accent, its darker hover shade
 * --gh-accent-dark, and --gh-accent-rgb for rgba() tints). Unit-tested in
 * tests/design-test.php; the setting itself is in design.php.
 *
 * @package Golden_Hive_Blocks
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The accent when a store hasn't chosen one: the plugin's original colour. */
const GHB_ACCENT_DEFAULT = '#721124';

/** How much darker the hover shade is: each channel × this. */
const GHB_ACCENT_DARKEN = 0.72;

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
 * The accent's tokens: the colour, its hover shade and its RGB triplet.
 * Anything that isn't a hex colour falls back to the default.
 *
 * @return array{accent: string, dark: string, rgb: string}
 */
function ghb_accent_tokens(string $hex): array
{
    $hex = ghb_hex_normalize($hex) ?? GHB_ACCENT_DEFAULT;
    $rgb = array_map('hexdec', str_split(substr($hex, 1), 2));
    $dark = '#' . implode('', array_map(function ($c) {
        return str_pad(dechex((int) round($c * GHB_ACCENT_DARKEN)), 2, '0', STR_PAD_LEFT);
    }, $rgb));
    return array('accent' => $hex, 'dark' => $dark, 'rgb' => implode(', ', $rgb));
}

/** The tokens as CSS. html:root outranks the stylesheet's :root defaults. */
function ghb_accent_css(string $hex): string
{
    $t = ghb_accent_tokens($hex);
    return 'html:root{--gh-accent:' . $t['accent'] . ';--gh-accent-dark:' . $t['dark'] . ';--gh-accent-rgb:' . $t['rgb'] . '}';
}
