<?php
/**
 * Tests for the design tokens (includes/design-core.php).
 *
 * Run from the plugin root:   php tests/design-test.php
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/design-core.php';

$GLOBALS['ghb_failures'] = 0;
$GLOBALS['ghb_passes']   = 0;

function same($actual, $expected, string $what): void
{
    if ($actual === $expected) {
        $GLOBALS['ghb_passes']++;
        return;
    }
    $GLOBALS['ghb_failures']++;
    echo "FAIL: $what\n      expected " . var_export($expected, true) . "\n      actual   " . var_export($actual, true) . "\n";
}

function has(string $css, string $needle, string $what): void
{
    same(false !== strpos($css, $needle), true, $what . " ($needle)");
}

/* ---------------------------------------------------------------- *
 * Colours
 * ---------------------------------------------------------------- */

// The legacy accent reproduces the stylesheet's original tokens exactly, so
// a store migrated to it looks as it always did.
same(ghb_accent_tokens(GHB_TOKEN_LEGACY['accent']), array('accent' => '#721124', 'dark' => '#520c1a', 'rgb' => '114, 17, 36'), 'the legacy accent and its hover shade');
same(ghb_accent_tokens('#000000'), array('accent' => '#000000', 'dark' => '#000000', 'rgb' => '0, 0, 0'), 'black stays black');
same(ghb_accent_tokens('#FFFFFF')['dark'], '#b8b8b8', 'white darkens to a grey, uppercase input accepted');
same(ghb_accent_tokens('#1e40af')['rgb'], '30, 64, 175', 'the RGB triplet');
same(ghb_accent_tokens('#0af')['accent'], '#00aaff', 'short hex is expanded');
same(ghb_hex_normalize('red'), null, 'a colour name is not a hex colour');
same(ghb_hex_normalize('#12345'), null, 'five digits are not a colour');
same(ghb_hex_normalize(' #ABCDEF '), '#abcdef', 'spaces and case are tidied');
same(ghb_token_clean('accent', 'nonsense'), GHB_TOKEN_DEFAULTS['accent'], 'a bad colour falls back to the default');

/* ---------------------------------------------------------------- *
 * Fonts and titles
 * ---------------------------------------------------------------- */

same(ghb_font_normalize("'Bebas Neue', sans-serif"), "'Bebas Neue', sans-serif", 'a font list survives');
same(ghb_font_normalize('Inter; } body { display:none'), 'Inter body displaynone', 'nothing breaks out of the declaration');
same(ghb_font_normalize('  , '), '', 'blank means the theme');
same(ghb_token_clean('title_weight', '650'), GHB_TOKEN_DEFAULTS['title_weight'], 'an unknown weight falls back');
same(ghb_token_clean('title_case', 'shout'), GHB_TOKEN_DEFAULTS['title_case'], 'an unknown title case falls back');

/* ---------------------------------------------------------------- *
 * The CSS
 * ---------------------------------------------------------------- */

$neutral = ghb_design_tokens_css(array());
has($neutral, '--gh-accent:#111111', 'defaults are neutral');
has($neutral, '--gh-font-display:inherit', 'no font: the theme\'s');
has($neutral, '--gh-title-transform:none', 'titles as typed by default');
has($neutral, '--gh-title-tracking:0', 'no tracking without uppercase');
same(0 === strpos($neutral, 'html:root{') && '}' === substr($neutral, -1), true, 'one html:root rule');

$store = ghb_design_tokens_css(array(
    'accent'       => '#1e40af',
    'dark'         => '#1b1b1f',
    'light'        => '#fbf8f3',
    'font_display' => "'Bebas Neue', sans-serif",
    'title_case'   => 'uppercase',
    'title_weight' => '900',
));
has($store, '--gh-accent:#1e40af;--gh-accent-dark:#162e7e;--gh-accent-rgb:30, 64, 175', 'the accent and its derived tokens');
has($store, '--gh-black:#1b1b1f', 'the dark colour');
has($store, '--gh-white:#fbf8f3', 'the light colour');
has($store, "--gh-font-display:'Bebas Neue', sans-serif", 'the titles\' font');
has($store, '--gh-font-body:inherit', 'an unset font stays the theme\'s');
has($store, '--gh-title-transform:uppercase;--gh-title-tracking:0.02em;--gh-title-weight:900', 'the title style');

$legacy = ghb_design_tokens_css(GHB_TOKEN_LEGACY);
has($legacy, '--gh-accent:#721124;--gh-accent-dark:#520c1a', 'the legacy settings give the old accent');
has($legacy, '--gh-title-transform:uppercase', 'and the old uppercase titles');
has($legacy, '--gh-title-weight:800', 'and the old weight');

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
