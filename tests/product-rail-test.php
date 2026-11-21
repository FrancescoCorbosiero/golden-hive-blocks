<?php
/**
 * Tests for the product rails' defaults (includes/product-rail-core.php): the
 * store's rail settings, cleaned, and photo ratios as CSS.
 *
 * Run from the plugin root:   php tests/product-rail-test.php
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/product-rail-core.php';

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

// Nothing saved: what rails have always done.
same(ghb_rail_settings(array()), GHB_RAIL_DEFAULTS, 'nothing saved: the defaults');
same(GHB_RAIL_DEFAULTS['columns'], 4, 'four per row on desktop');
same(GHB_RAIL_DEFAULTS['limit'], 12, 'twelve products');
same(GHB_RAIL_DEFAULTS['fit'], 'contain', 'whole photos');

$rail = ghb_rail_settings(array('columns' => '5', 'columns_tablet' => '3', 'columns_mobile' => '2', 'limit' => '18', 'ratio' => 'Portrait', 'fit' => 'cover', 'hover' => 'no'));
same($rail['columns'], 5, 'desktop columns');
same($rail['columns_tablet'], 3, 'tablet columns');
same($rail['columns_mobile'], 2, 'phone columns');
same($rail['limit'], 18, 'products per rail');
same($rail['ratio'], 'portrait', 'a ratio by name, any case');
same($rail['fit'], 'cover', 'cropped photos');
same($rail['hover'], 'no', 'no second photo');

same(ghb_rail_setting_clean('columns', '9'), 4, 'nine columns: the default');
same(ghb_rail_setting_clean('columns_tablet', ''), '', 'tablet: as on desktop');
same(ghb_rail_setting_clean('columns_mobile', '6'), '', 'phones: at most four, else the peek');
same(ghb_rail_setting_clean('limit', '500'), GHB_RAIL_MAX_LIMIT, 'a rail asks for at most 100');
same(ghb_rail_setting_clean('limit', '-3'), 12, 'a negative limit: the default');
same(ghb_rail_setting_clean('ratio', '3/4'), 'square', 'the setting offers names only');
same(ghb_rail_setting_clean('fit', 'stretch'), 'contain', 'an unknown fit: the default');
same(ghb_rail_setting_clean('nonsense', 'x'), null, 'an unknown setting');

same(ghb_rail_ratio_css('square'), '1 / 1', 'square');
same(ghb_rail_ratio_css('tall'), '4 / 5', 'tall');
same(ghb_rail_ratio_css(' 3 / 4 '), '3 / 4', 'a shortcode\'s own N/N');
same(ghb_rail_ratio_css('1/1'), '1 / 1', 'the old default value');
same(ghb_rail_ratio_css('0/4'), '1 / 1', 'a zero side: a square');
same(ghb_rail_ratio_css('banana'), '1 / 1', 'anything else: a square');

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
