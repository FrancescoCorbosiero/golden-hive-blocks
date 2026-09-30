<?php
/**
 * Tests for the Store Hub bridge core (includes/hub-rails-core.php).
 *
 * Run from the plugin root:   php tests/hub-rails-test.php
 *
 * No framework and no WordPress install: the core is plain PHP, and the one
 * WordPress piece it leans on — the block parser and the attribute serializer —
 * is loaded from tests/wp/ (verbatim copies of WordPress core, GPL-2.0+).
 * The fixture is the real homepage markup, so "the rewrite touches only one
 * block" is proven on the page it will actually run against.
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/wp/class-wp-block-parser.php';

function parse_blocks($content)
{
    $parser = new WP_Block_Parser();
    return $parser->parse($content);
}

function wp_json_encode($data, $options = 0, $depth = 512)
{
    return json_encode($data, $options, $depth);
}

/** WordPress core's serialize_block_attributes() (wp-includes/blocks.php), verbatim. */
function serialize_block_attributes($block_attributes)
{
    $encoded_attributes = wp_json_encode($block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return strtr(
        $encoded_attributes,
        array(
            '\\\\' => '\\u005c',
            '--'   => '\\u002d\\u002d',
            '<'    => '\\u003c',
            '>'    => '\\u003e',
            '&'    => '\\u0026',
            '\\"'  => '\\u0022',
        )
    );
}

require dirname(__DIR__) . '/includes/hub-rails-core.php';

/* ---------------------------------------------------------------- *
 * Tiny runner.
 * ---------------------------------------------------------------- */

$GLOBALS['ghb_failures'] = 0;
$GLOBALS['ghb_passes']   = 0;

function check(bool $ok, string $what, $detail = null): void
{
    if ($ok) {
        $GLOBALS['ghb_passes']++;
        return;
    }
    $GLOBALS['ghb_failures']++;
    echo "FAIL: {$what}\n";
    if (null !== $detail) {
        echo '      ' . (is_string($detail) ? $detail : var_export($detail, true)) . "\n";
    }
}

function same($actual, $expected, string $what): void
{
    check($actual === $expected, $what, array('expected' => $expected, 'actual' => $actual));
}

$html   = file_get_contents(__DIR__ . '/fixtures/homepage.html');
$blocks = parse_blocks($html);

/* ---------------------------------------------------------------- *
 * Reading the page.
 * ---------------------------------------------------------------- */

$rails = ghb_hub_page_rails($blocks);
same(array_keys($rails), array(
    'category:featured-sneakers-originali-streetwear#0',
    'category:saldi-sneakers-outlet#0',
    'category:saldi-sneakers-in-offerta#0',
    'category:new-nuove-release#0',
    'brand:nike-off-white#0',
    'brand:nike-air-force-1#0',
    'brand:adidas#0',
    'brand:new-balance#0',
    'brand:asics#0',
), 'the homepage has its nine rails, in page order');

same($rails['category:saldi-sneakers-outlet#0']['path'], '8.1', 'SALDI sits inside the first group (inner blocks carry no whitespace entries)');
same($rails['brand:asics#0']['path'], '12.2', 'ASICS sits inside the second group');

$saldi = $rails['category:saldi-sneakers-outlet#0']['rail'];
same($saldi['atts']['limit'], '18', 'rail attributes are read');
same($saldi['fallback'], 'menu_order', 'a category rail falls back to the custom order');
same($saldi['pin'], array(), 'no pins yet');

$leaves = ghb_hub_leaf_blocks($blocks);
$names  = array_map(static fn($leaf) => $leaf[1]['blockName'], $leaves);
same(count($leaves), 19, 'every leaf block is listed');
same($names[0], 'core/html', 'the style block comes first');
check(!in_array('core/group', $names, true), 'groups are walked, not listed');

$hero = ghb_hub_block_summary($leaves[1][1]);
same($hero['items'], 5, 'the hero summary counts its slides');
same($hero['labels'][0], 'AP x SWATCH', 'the hero summary names its slides');

$shortcode = ghb_hub_block_summary($leaves[3][1]);
same($shortcode['title'], '[wd_hustle id="3" type="embedded"/]', 'a shortcode block is summarised by its shortcode');

same(ghb_hub_block_at_path($blocks, '8.1')['attrs']['title'], 'SALDI', 'paths address parse_blocks output');
same(ghb_hub_block_at_path($blocks, '99'), null, 'a missing path is null');
same(ghb_hub_block_at_path($blocks, '8.x'), null, 'a malformed path is null');

/* ---------------------------------------------------------------- *
 * Shortcodes.
 * ---------------------------------------------------------------- */

foreach ($rails as $key => $entry) {
    $original = $entry['rail']['shortcode'];
    $parsed   = ghb_hub_parse_shortcode($original);
    same(ghb_hub_build_shortcode($parsed['tag'], $parsed['atts']), $original, "shortcode round-trips unchanged ({$key})");
}

same(
    ghb_hub_parse_shortcode('[gh_product_rail brand=\'adidas\' limit=15 "x"]'),
    array('tag' => 'gh_product_rail', 'atts' => array('brand' => 'adidas', 'limit' => '15', 0 => 'x')),
    'single-quoted, bare and positional attributes are read like WordPress reads them'
);
same(ghb_hub_parse_shortcode('text [gh_product_rail]'), null, 'text around a shortcode is refused');
same(ghb_hub_parse_shortcode('[a][b]'), null, 'two shortcodes are refused');
same(ghb_hub_build_shortcode('gh_product_rail', array('title' => 'say "hi"')), null, 'a value with a quote cannot be written');

$with_pins = ghb_hub_set_rail_atts(
    $saldi['shortcode'],
    array('pin' => array(1201, 877, '1543', 'x', 877), 'exclude' => array(990), 'fallback' => 'popularity')
);
same(
    $with_pins,
    '[gh_product_rail category="saldi-sneakers-outlet" limit="18" columns="4" columns_tablet="3" pin="1201,877,1543" exclude="990" fallback="popularity"]',
    'pins, exclusions and the fallback are appended, everything else untouched'
);

$cleared = ghb_hub_set_rail_atts($with_pins, array('pin' => array(), 'exclude' => array(), 'fallback' => 'menu_order'));
same($cleared, $saldi['shortcode'], 'clearing everything gives back the original shortcode');

$reordered = ghb_hub_set_rail_atts($with_pins, array('pin' => array(877, 1201), 'exclude' => array(990), 'fallback' => 'popularity'));
same(
    $reordered,
    '[gh_product_rail category="saldi-sneakers-outlet" limit="18" columns="4" columns_tablet="3" pin="877,1201" exclude="990" fallback="popularity"]',
    'rewriting existing values keeps their place'
);

same(
    ghb_hub_set_rail_atts($saldi['shortcode'], array('pin' => array(), 'exclude' => array(), 'fallback' => 'nonsense')),
    $saldi['shortcode'],
    'an unknown fallback means the default'
);
same(ghb_hub_set_rail_atts('[wd_hustle id="3"]', array()), null, 'only gh_product_rail is rewritten');

/* ---------------------------------------------------------------- *
 * Ordering.
 * ---------------------------------------------------------------- */

same(ghb_hub_parse_id_list('12, 5,abc,9,5,-3,0, 7 '), array(12, 5, 9, 7), 'id lists keep positive integers, first occurrence');
same(count(ghb_hub_parse_id_list(range(1, 500))), GHB_HUB_MAX_IDS, 'id lists are capped');
same(ghb_hub_parse_id_list(null), array(), 'no list, no ids');

same(
    ghb_hub_order_ids(array(10, 20, 30, 40, 50), array(40, 99, 10), array(20)),
    array(40, 10, 30, 50),
    'visible pins first in pin order, then the rest in fallback order, exclusions never'
);
same(ghb_hub_order_ids(array(1, 2), array(2, 2, 1), array()), array(2, 1), 'duplicate pins count once');
same(ghb_hub_order_ids(array(1, 2), array(), array(1, 2)), array(), 'everything excluded is an empty rail');

same(ghb_hub_default_fallback(array('brand' => 'adidas')), 'menu_order', 'brand rails default to the custom order');
same(ghb_hub_default_fallback(array('type' => 'best_selling', 'category' => 'x')), 'popularity', 'bestseller rails keep their metric');
same(ghb_hub_default_fallback(array()), 'date', 'a plain rail defaults to newest');

/* ---------------------------------------------------------------- *
 * Writing one block back.
 * ---------------------------------------------------------------- */

// Identity: every block's delimiter is found exactly once as serialized —
// the precondition for touching any of them.
foreach ($rails as $key => $entry) {
    $block  = $entry['block'];
    $result = ghb_hub_replace_block_attrs($html, $block['blockName'], $block['attrs'], $block['attrs'], 'serialize_block_attributes');
    check($result['ok'], "rail block located exactly once ({$key})", $result);
    same($result['content'] ?? null, $html, "an identity rewrite changes nothing ({$key})");
}

$saldi_block          = $rails['category:saldi-sneakers-outlet#0']['block'];
$new_attrs            = $saldi_block['attrs'];
$new_attrs['shortcode'] = $with_pins;
$written = ghb_hub_replace_block_attrs($html, $saldi_block['blockName'], $saldi_block['attrs'], $new_attrs, 'serialize_block_attributes');
check($written['ok'], 'the SALDI rail can be rewritten', $written);

$after_blocks = parse_blocks($written['content']);
$after_rails  = ghb_hub_page_rails($after_blocks);
same($after_rails['category:saldi-sneakers-outlet#0']['rail']['pin'], array(1201, 877, 1543), 'the new pins read back');
same($after_rails['category:saldi-sneakers-outlet#0']['rail']['fallback'], 'popularity', 'the new fallback reads back');

// Nothing else moved: every other leaf block is byte-for-byte the same parse.
$before_leaves = ghb_hub_leaf_blocks($blocks);
$after_leaves  = ghb_hub_leaf_blocks($after_blocks);
same(count($after_leaves), count($before_leaves), 'the page keeps its blocks');
foreach ($before_leaves as $i => $leaf) {
    if ($rails['category:saldi-sneakers-outlet#0']['path'] === $leaf[0]) {
        continue;
    }
    same($after_leaves[$i], $leaf, 'untouched block ' . $leaf[0] . ' is unchanged');
}
same(
    strlen($written['content']) - strlen($html),
    strlen(serialize_block_attributes($new_attrs)) - strlen(serialize_block_attributes($saldi_block['attrs'])),
    'the content grew by exactly the serialized attribute change'
);

// The same rail twice on a page: refusing beats guessing.
$doubled   = $html . "\n\n" . '<!-- wp:golden-hive/shortcode-wrapper ' . serialize_block_attributes($saldi_block['attrs']) . ' /-->';
$ambiguous = ghb_hub_replace_block_attrs($doubled, $saldi_block['blockName'], $saldi_block['attrs'], $new_attrs, 'serialize_block_attributes');
same($ambiguous['ok'], false, 'a block present twice is not rewritten');
same($ambiguous['error'], 'ambiguous', 'and the refusal says why');

$missing = ghb_hub_replace_block_attrs($html, $saldi_block['blockName'], array('shortcode' => 'gone'), $new_attrs, 'serialize_block_attributes');
same($missing['error'], 'not_found', 'a block that is not there is not rewritten');

same(ghb_hub_attrs_hash($saldi_block['attrs']), ghb_hub_attrs_hash(parse_blocks($html)[8]['innerBlocks'][1]['attrs']), 'the attribute hash is stable across parses');
check(ghb_hub_attrs_hash($saldi_block['attrs']) !== ghb_hub_attrs_hash($new_attrs), 'the attribute hash sees a change');

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
