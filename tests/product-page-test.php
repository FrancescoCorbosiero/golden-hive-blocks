<?php
/**
 * Tests for the product page additions' rules (includes/product-page-core.php):
 * the «Acquista con fiducia» tiles a store writes, and the colorways rail's
 * attribute after the move to 5.20.
 *
 * Run from the plugin root:   php tests/product-page-test.php
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/product-page-core.php';

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

same(ghb_pp_clean_trust_items(array()), array(), 'no tile until the store writes one');
same(ghb_pp_clean_trust_items(null), array(), 'nothing saved: none');
same(
    ghb_pp_clean_trust_items(array(array('icon' => 'shipping', 'title' => ' Spedizione tracciata ', 'sub' => 'In tutta Italia', 'text' => "Una <b>riga</b>.\nE un'altra."))),
    array(array('icon' => 'shipping', 'title' => 'Spedizione tracciata', 'sub' => 'In tutta Italia', 'text' => "Una riga.\nE un'altra.")),
    'a tile: no tags, lines kept'
);
same(ghb_pp_clean_trust_items(array(array('icon' => 'rocket', 'title' => 'Reso')))[0]['icon'] ?? null, 'authentic', 'an unknown icon falls back');
same(ghb_pp_clean_trust_items(array(array('title' => ''), array('title' => 'Ok'))), array(array('icon' => 'authentic', 'title' => 'Ok', 'sub' => '', 'text' => '')), 'a tile without a title is dropped');
same(count(ghb_pp_clean_trust_items(array_fill(0, 7, array('title' => 'x')))), GHB_PP_MAX_TILES, 'at most four tiles');
same(ghb_pp_clean_trust_items('[{"icon":"secure","title":"Pagamenti"}]')[0]['icon'], 'secure', 'tiles as JSON (the Customizer)');

same(ghb_pp_colorways_before_520(true, array('pa_modello', 'pa_taglia')), 'pa_modello', 'the rail was on and the attribute exists: kept');
same(ghb_pp_colorways_before_520(true, array('pa_model', 'pa_taglia')), '', 'no such attribute: the rail showed nothing, still nothing');
same(ghb_pp_colorways_before_520(false, array('pa_modello')), '', 'switched off: stays off');

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
