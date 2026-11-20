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

// The default reproduces the stylesheet's original tokens exactly, so a store
// that never picks a colour looks as it always did.
same(ghb_accent_tokens(GHB_ACCENT_DEFAULT), array('accent' => '#721124', 'dark' => '#520c1a', 'rgb' => '114, 17, 36'), 'the default is the original burgundy and its hover shade');

same(ghb_accent_tokens('#000000'), array('accent' => '#000000', 'dark' => '#000000', 'rgb' => '0, 0, 0'), 'black stays black');
same(ghb_accent_tokens('#FFFFFF')['dark'], '#b8b8b8', 'white darkens to a grey, uppercase input accepted');
same(ghb_accent_tokens('#1e40af')['rgb'], '30, 64, 175', 'the RGB triplet');
same(ghb_accent_tokens('#0af')['accent'], '#00aaff', 'short hex is expanded');

same(ghb_hex_normalize('red'), null, 'a colour name is not a hex colour');
same(ghb_hex_normalize('#12345'), null, 'five digits are not a colour');
same(ghb_hex_normalize(' #ABCDEF '), '#abcdef', 'spaces and case are tidied');
same(ghb_accent_tokens('nonsense')['accent'], '#721124', 'garbage falls back to the default');

same(ghb_accent_css('#1e40af'), 'html:root{--gh-accent:#1e40af;--gh-accent-dark:#162e7e;--gh-accent-rgb:30, 64, 175}', 'the CSS the page gets');

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
