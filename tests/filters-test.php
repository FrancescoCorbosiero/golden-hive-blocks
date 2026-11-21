<?php
/**
 * Tests for the shop filters' facets (includes/filters-core.php): what a store
 * gets before it chooses, its own list, and what the one-time move keeps.
 *
 * Run from the plugin root:   php tests/filters-test.php
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/filters-core.php';

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

// Three stores' attributes, as WooCommerce lists them.
$one   = array('pa_colorway' => 'Colorway', 'pa_marca' => 'Marca', 'pa_modello' => 'Modello', 'pa_taglia' => 'Taglia');
$two   = array('pa_brand' => 'Brand', 'pa_color' => 'Color', 'pa_gender' => 'Gender', 'pa_model' => 'Model', 'pa_taglia' => 'Taglia');
$three = array('pa_gender' => 'Gender', 'pa_marca' => 'Marca', 'pa_taglia' => 'Taglia');

/* ---------------------------------------------------------------- *
 * Nothing saved: every facet, named as in the shop
 * ---------------------------------------------------------------- */

$facets = ghb_filters_facets(null, $two, 'pa_taglia');
same(array_keys($facets), array('price', 'cat', 'stock', 'brand', 'color', 'gender', 'model', 'taglia'), 'every facet, attributes in the store\'s order');
same($facets['brand'], array('type' => 'attribute', 'taxonomy' => 'pa_brand', 'label' => 'Brand', 'chip' => 'Brand', 'collapsible' => true), 'an attribute under its own name');
same($facets['taglia']['variation'] ?? false, true, 'the size matches in-stock variations');
same($facets['taglia']['ui'] ?? '', 'chips', 'and shows as chips');
same($facets['stock']['text'], 'Disponibile', 'stock: "Disponibile"');

/* ---------------------------------------------------------------- *
 * The store's own list
 * ---------------------------------------------------------------- */

$rows = array(
    array('key' => 'pa_taglia', 'label' => 'La tua taglia', 'on' => true),
    array('key' => 'price', 'label' => '', 'on' => 'yes'),
    array('key' => 'pa_gender', 'label' => 'Genere', 'on' => false),
    array('key' => 'pa_anno', 'label' => 'Anno', 'on' => true),
    array('key' => 'pa_taglia', 'label' => 'Again', 'on' => true),
);
$facets = ghb_filters_facets($rows, $three, 'pa_taglia');
same(array_keys($facets), array('taglia', 'price'), 'only the facets switched on, in the store\'s order');
same($facets['taglia']['label'], 'La tua taglia', 'a renamed facet');
same($facets['taglia']['chip'], 'Taglia', 'its chip keeps the attribute\'s name');
same($facets['price']['label'], 'Prezzo', 'an empty label: the facet\'s own name');
same(ghb_filters_facets(array(), $three, 'pa_taglia'), array(), 'everything switched off: no facets');

same(
    ghb_filters_clean_rows('[{"key":"cat","label":"<b>Categorie</b>","on":1},{"key":"nope","on":true}]', $three),
    array(array('key' => 'cat', 'label' => 'Categorie', 'on' => true)),
    'from the Customizer: known keys only, no tags'
);

/* ---------------------------------------------------------------- *
 * The one-time move: the pre-5.20 panel, as far as each store has it
 * ---------------------------------------------------------------- */

same(
    array_column(ghb_filters_rows_before_520($one), 'key'),
    array('price', 'cat', 'stock', 'pa_modello', 'pa_taglia', 'pa_marca', 'pa_colorway'),
    'a store with those attributes keeps them all'
);
same(array_keys(ghb_filters_facets(ghb_filters_rows_before_520($two), $two, 'pa_taglia')), array('price', 'cat', 'stock', 'taglia'), 'a store with other attributes: the size only, as before');
same(array_keys(ghb_filters_facets(ghb_filters_rows_before_520($three), $three, 'pa_taglia')), array('price', 'cat', 'stock', 'taglia', 'marca'), 'and one with brand and size: those two');
same(ghb_filters_facets(ghb_filters_rows_before_520($three), $three, 'pa_taglia')['marca']['label'], 'Filtra per Marca', 'with the labels it had');
same(ghb_filters_facets(ghb_filters_rows_before_520($three), $three, 'pa_taglia')['stock']['label'], 'Stato', 'stock as it was called');

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
