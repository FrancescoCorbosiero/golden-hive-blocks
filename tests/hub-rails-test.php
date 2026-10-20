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

/* ---------------------------------------------------------------- *
 * Rail size and editable fields.
 * ---------------------------------------------------------------- */

$saldi_shortcode = $saldi_block['attrs']['shortcode'];
$nothing         = array('pin' => array(), 'exclude' => array(), 'fallback' => '');
$resized         = ghb_hub_set_rail_atts($saldi_shortcode, $nothing + array('limit' => 24));
same(ghb_hub_parse_shortcode($resized)['atts']['limit'], '24', 'limit is written when asked');
$kept_before = ghb_hub_parse_shortcode($saldi_shortcode)['atts'];
$kept_after  = ghb_hub_parse_shortcode($resized)['atts'];
unset($kept_before['limit'], $kept_after['limit']);
same($kept_after, $kept_before, 'resizing keeps every other rail attribute');
same(ghb_hub_set_rail_atts($saldi_shortcode, $nothing), $saldi_shortcode, 'without limit the rail is untouched');

// Stand-in for WordPress's sanitizers (hub-rails.php injects the real ones).
$clean = function (string $type, string $value): string {
    if ('url' === $type) {
        $value = trim($value);
        return ('' === $value || preg_match('#^(https?://|/)#', $value)) ? $value : '';
    }
    return 'enum' === $type ? trim($value) : trim(strip_tags($value));
};
$wrap = ghb_hub_field_specs()['golden-hive/shortcode-wrapper'];
$defs = array('eyebrow' => '', 'title' => '', 'backgroundColor' => 'white', 'buttonText' => '', 'buttonUrl' => '');

$read = ghb_hub_block_fields($saldi_block['attrs'], $wrap, $defs);
same($read['title'], 'SALDI', 'fields read the saved title');
same($read['eyebrow'], 'Saldi primaverili', 'and the saved eyebrow');
same($read['buttonUrl'], $saldi_block['attrs']['buttonUrl'] ?? '', 'an unsaved field reads as its default');
check(!isset($wrap['shortcode']), 'the shortcode itself is never an editable field');

$applied = ghb_hub_apply_fields($saldi_block['attrs'], array('title' => '  Saldi <b>d\'autunno</b> ', 'eyebrow' => 'Fino al -50%'), $wrap, $defs, $clean);
same($applied['ok'], true, 'text fields apply');
same($applied['attrs']['title'], "Saldi d'autunno", 'text is cleaned before it is written');
same($applied['changed'], array('title', 'eyebrow'), 'the changed fields are reported');
same($applied['attrs']['shortcode'], $saldi_shortcode, 'editing texts leaves the rail alone');

$refused = array(
    'not_editable'  => array('shortcode' => '[gh_product_rail ids="1"]'),
    'not_text'      => array('title' => array('x')),
    'not_an_option' => array('backgroundColor' => 'pink'),
    'bad_url'       => array('buttonUrl' => 'javascript:alert(1)'),
    'too_long'      => array('title' => str_repeat('x', 121)),
);
foreach ($refused as $reason => $change) {
    $result = ghb_hub_apply_fields($saldi_block['attrs'], $change, $wrap, $defs, $clean);
    same($result['ok'] ? 'ok' : $result['error'], $reason, "refused: {$reason}");
}

$reset = ghb_hub_apply_fields(array('backgroundColor' => 'gray'), array('backgroundColor' => 'white'), $wrap, $defs, $clean);
check(!array_key_exists('backgroundColor', $reset['attrs']), 'a field set back to its default is removed, not written');
$noop = ghb_hub_apply_fields($saldi_block['attrs'], array('title' => 'SALDI'), $wrap, $defs, $clean);
same($noop['changed'], array(), 'writing the current value changes nothing');
same($noop['attrs'], $saldi_block['attrs'], 'and leaves the attributes identical');

// On the real page: new texts for SALDI rewrite that block and nothing else.
$texts    = ghb_hub_apply_fields($saldi_block['attrs'], array('title' => 'SALDI AUTUNNO', 'buttonText' => 'Vedi tutti', 'buttonUrl' => '/saldi'), $wrap, $defs, $clean);
$retitled = ghb_hub_replace_block_attrs($html, $saldi_block['blockName'], $saldi_block['attrs'], $texts['attrs'], 'serialize_block_attributes');
check($retitled['ok'], 'field edits are written into the page', $retitled);
$saldi_path = $rails['category:saldi-sneakers-outlet#0']['path'];
$reread     = ghb_hub_block_at_path(parse_blocks($retitled['content']), $saldi_path);
same($reread['attrs']['title'], 'SALDI AUTUNNO', 'the page now carries the new title');
same($reread['attrs']['buttonUrl'], '/saldi', 'and the new button link');
same($reread['attrs']['shortcode'], $saldi_shortcode, 'and the same rail');
foreach (ghb_hub_leaf_blocks(parse_blocks($retitled['content'])) as $i => $leaf) {
    if ($leaf[0] !== $saldi_path) {
        same($leaf, $before_leaves[$i], 'untouched by the retitle: ' . $leaf[0]);
    }
}

/* ---------------------------------------------------------------- *
 * The section's switch.
 * ---------------------------------------------------------------- */

$defs_hidden = $defs + array('hidden' => '');
$off = ghb_hub_apply_fields($saldi_block['attrs'], array('hidden' => 'yes'), $wrap, $defs_hidden, $clean);
same($off['attrs']['hidden'] ?? null, 'yes', 'a section can be switched off');
same($off['attrs']['shortcode'], $saldi_shortcode, 'switching off keeps the rail as it is');
$on = ghb_hub_apply_fields($off['attrs'], array('hidden' => ''), $wrap, $defs_hidden, $clean);
check(!array_key_exists('hidden', $on['attrs']), 'switched on again, the attribute goes (it is the default)');
same($on['attrs'], $saldi_block['attrs'], 'and the block is as it was');
same(ghb_hub_apply_fields($saldi_block['attrs'], array('hidden' => 'no'), $wrap, $defs_hidden, $clean)['error'] ?? null, 'not_an_option', 'the switch takes "yes" or nothing');

/* ---------------------------------------------------------------- *
 * What a section shows.
 * ---------------------------------------------------------------- */

same(ghb_hub_parse_source(array('taxonomy' => 'category', 'slug' => 'saldi')), array('kind' => 'term', 'taxonomy' => 'category', 'slug' => 'saldi'), 'a category source is read');
same(ghb_hub_parse_source(array('taxonomy' => 'brand', 'slug' => 'nike-dunk')), array('kind' => 'term', 'taxonomy' => 'brand', 'slug' => 'nike-dunk'), 'a brand source is read');
same(ghb_hub_parse_source(array('type' => 'Best_Selling ')), array('kind' => 'type', 'type' => 'best_selling'), 'a shop-wide list is read, any case');
same(ghb_hub_parse_source(array('taxonomy' => 'category', 'slug' => 'caff%c3%a8')), array('kind' => 'term', 'taxonomy' => 'category', 'slug' => 'caff%c3%a8'), 'a slug with an accented letter, as WordPress escapes it, is read');
foreach (
    array(
        'no source'          => null,
        'unknown taxonomy'   => array('taxonomy' => 'product_cat', 'slug' => 'saldi'),
        'empty slug'         => array('taxonomy' => 'category', 'slug' => ''),
        'a quote'            => array('taxonomy' => 'category', 'slug' => 'a"b'),
        'a bracket'          => array('taxonomy' => 'brand', 'slug' => 'nike]'),
        'a space'            => array('taxonomy' => 'tag', 'slug' => 'two words'),
        'unknown list'       => array('type' => 'random'),
        'a list that is not' => array('type' => array('recent')),
    ) as $what => $raw
) {
    same(ghb_hub_parse_source($raw), null, "a source is refused: {$what}");
}

$offerta = $rails['category:saldi-sneakers-in-offerta#0']['rail'];
$as_dunk = ghb_hub_set_rail_source($with_pins, array('kind' => 'term', 'taxonomy' => 'brand', 'slug' => 'nike-dunk'));
same(
    $as_dunk,
    '[gh_product_rail brand="nike-dunk" limit="18" columns="4" columns_tablet="3"]',
    'a new source takes the old one\'s place; pins, exclusions and fallback go with the old products'
);
same(
    ghb_hub_set_rail_source($offerta['shortcode'], array('kind' => 'type', 'type' => 'best_selling')),
    '[gh_product_rail type="best_selling" limit="18" columns="4" columns_tablet="3"]',
    'a section can show a shop-wide list'
);
same(
    ghb_hub_set_rail_source('[gh_product_rail type="sale" category="saldi" limit="9"]', array('kind' => 'term', 'taxonomy' => 'tag', 'slug' => 'estate')),
    '[gh_product_rail tag="estate" limit="9"]',
    'every attribute that said what the rail showed goes, a type alongside a category too'
);
same(
    ghb_hub_set_rail_source('[gh_product_rail limit="9"]', array('kind' => 'term', 'taxonomy' => 'category', 'slug' => 'saldi')),
    '[gh_product_rail category="saldi" limit="9"]',
    'a rail that named no source gets one, first'
);
same(ghb_hub_set_rail_source('[gh_size_rail limit="9"]', array('kind' => 'type', 'type' => 'recent')), null, 'only gh_product_rail is pointed elsewhere');
same(
    ghb_hub_set_rail_atts($as_dunk, array('pin' => array(5, 6), 'exclude' => array(), 'fallback' => 'date')),
    '[gh_product_rail brand="nike-dunk" limit="18" columns="4" columns_tablet="3" pin="5,6" fallback="date"]',
    'the new products\' pins and fallback are written over the new source'
);
same(ghb_hub_default_fallback(ghb_hub_parse_shortcode(
    (string) ghb_hub_set_rail_source($saldi['shortcode'], array('kind' => 'type', 'type' => 'recent'))
)['atts']), 'date', 'a shop-wide list of the newest falls back to the newest');

/* ---------------------------------------------------------------- *
 * A new section.
 * ---------------------------------------------------------------- */

same(ghb_hub_clean_layout_value('columns', '5'), '5', 'columns 1-8 are kept');
same(ghb_hub_clean_layout_value('columns', '9'), null, 'columns past 8 are refused');
same(ghb_hub_clean_layout_value('ratio', '3/4'), '3/4', 'a ratio is kept');
same(ghb_hub_clean_layout_value('ratio', '3/4"]'), null, 'a ratio that breaks out is refused');
same(ghb_hub_clean_layout_value('fit', 'Cover'), 'cover', 'a fit is kept');

$new_shortcode = ghb_hub_new_rail_shortcode(
    array('kind' => 'term', 'taxonomy' => 'brand', 'slug' => 'jordan'),
    12,
    $saldi['atts'],
    array(),
    array()
);
same($new_shortcode, '[gh_product_rail brand="jordan" limit="12" columns="4" columns_tablet="3"]', 'a new rail takes its neighbour\'s layout');
same(
    ghb_hub_new_rail_shortcode(array('kind' => 'type', 'type' => 'sale'), 500, $saldi['atts'], array('columns' => '5', 'fit' => 'cover', 'ratio' => 'x'), array('pin' => array(3, 1), 'fallback' => 'popularity')),
    '[gh_product_rail type="sale" limit="100" columns="5" columns_tablet="3" fit="cover" pin="3,1" fallback="popularity"]',
    'its own layout wins over its neighbour\'s, a bad value is left out, the size is capped, its order is written'
);

$texts_new = ghb_hub_apply_fields(array(), array('title' => 'JORDAN', 'eyebrow' => 'Air Jordan 1, 4, 11', 'backgroundColor' => 'gray'), $wrap, $defs_hidden, $clean);
$jordan    = array('blockName' => GHB_HUB_RAIL_BLOCK, 'attrs' => ghb_hub_new_rail_attrs((string) $new_shortcode, $texts_new['attrs']));
same(array_keys($jordan['attrs']), array('eyebrow', 'title', 'shortcode', 'backgroundColor'), 'a new rail block lists its attributes as the editor does');

$inserted = ghb_hub_insert_block($html, $saldi_block, 'after', $jordan, 'serialize_block_attributes');
check($inserted['ok'], 'a section is added after SALDI', $inserted);
$inserted_blocks = parse_blocks($inserted['content']);
$inserted_rails  = ghb_hub_page_rails($inserted_blocks);
same(count($inserted_rails), 10, 'the page has one more rail');
same($inserted_rails['brand:jordan#0']['path'], '8.2', 'right after SALDI, inside the same group');
same($inserted_rails['brand:jordan#0']['block']['attrs'], $jordan['attrs'], 'with exactly the attributes written');
same($inserted_rails['category:saldi-sneakers-in-offerta#0']['path'], '8.3', 'the next section moved one place down');
same(count(ghb_hub_leaf_blocks($inserted_blocks)), count($before_leaves) + 1, 'every other block is still there');
same(
    $inserted['content'],
    str_replace(
        ghb_hub_block_delimiter($saldi_block['blockName'], $saldi_block['attrs'], 'serialize_block_attributes'),
        ghb_hub_block_delimiter($saldi_block['blockName'], $saldi_block['attrs'], 'serialize_block_attributes') . "\n\n" . ghb_hub_block_delimiter($jordan['blockName'], $jordan['attrs'], 'serialize_block_attributes'),
        $html
    ),
    'and nothing but the new block and its blank line was added'
);

$tendenza_block = $rails['category:featured-sneakers-originali-streetwear#0']['block'];
$first          = ghb_hub_insert_block($html, $tendenza_block, 'before', $jordan, 'serialize_block_attributes');
same(ghb_hub_page_rails(parse_blocks($first['content']))['brand:jordan#0']['path'], '8.0', 'a section can go first');
same(array_keys(ghb_hub_page_rails(parse_blocks($first['content'])))[0], 'brand:jordan#0', 'and is the first rail of the page');

$asics_block = $rails['brand:asics#0']['block'];
$last        = ghb_hub_insert_block($html, $asics_block, 'after', $jordan, 'serialize_block_attributes');
same(ghb_hub_page_rails(parse_blocks($last['content']))['brand:jordan#0']['path'], '12.3', 'or last, still inside the group');
check(false !== strpos($last['content'], ghb_hub_block_delimiter($jordan['blockName'], $jordan['attrs'], 'serialize_block_attributes') . '</div>'), 'closing the group right after it');

same(ghb_hub_insert_block($html, $tendenza_block, 'after', $saldi_block, 'serialize_block_attributes')['error'] ?? null, 'duplicate', 'a section identical to one on the page is refused');
same(ghb_hub_insert_block($html, array('blockName' => GHB_HUB_RAIL_BLOCK, 'attrs' => array('shortcode' => 'gone')), 'after', $jordan, 'serialize_block_attributes')['error'] ?? null, 'not_found', 'next to a block that is not there: refused');
same(ghb_hub_insert_block($doubled, $saldi_block, 'after', $jordan, 'serialize_block_attributes')['error'] ?? null, 'ambiguous', 'next to a block present twice: refused');

/* ---------------------------------------------------------------- *
 * Taking a section away.
 * ---------------------------------------------------------------- */

$removed = ghb_hub_remove_block($html, $saldi_block, 'serialize_block_attributes');
check($removed['ok'], 'SALDI can be taken off the page', $removed);
$removed_rails = ghb_hub_page_rails(parse_blocks($removed['content']));
check(!isset($removed_rails['category:saldi-sneakers-outlet#0']), 'SALDI is gone');
same(count($removed_rails), 8, 'the other eight rails stay');
same($removed_rails['category:saldi-sneakers-in-offerta#0']['path'], '8.1', 'the next section moved up into its place');
same(
    strlen($html) - strlen($removed['content']),
    strlen(ghb_hub_block_delimiter($saldi_block['blockName'], $saldi_block['attrs'], 'serialize_block_attributes')) + 2,
    'exactly the block and one blank line went'
);
same(ghb_hub_insert_block($removed['content'], $tendenza_block, 'after', $saldi_block, 'serialize_block_attributes')['content'] ?? null, $html, 'put back after its old neighbour, the page is byte for byte as before');

$af1_block = $rails['brand:nike-air-force-1#0']['block'];
$no_af1    = ghb_hub_remove_block($html, $af1_block, 'serialize_block_attributes');
check(false !== strpos($no_af1['content'], ghb_hub_block_delimiter($rails['brand:nike-off-white#0']['block']['blockName'], $rails['brand:nike-off-white#0']['block']['attrs'], 'serialize_block_attributes') . '</div>'), 'the last section of a group goes with the blank line before it');
same(count(ghb_hub_page_rails(parse_blocks($no_af1['content']))), 8, 'and the page parses with eight rails');
same(ghb_hub_remove_block($doubled, $saldi_block, 'serialize_block_attributes')['error'] ?? null, 'ambiguous', 'a block present twice is not taken away');

/* ---------------------------------------------------------------- *
 * The sections in another order.
 * ---------------------------------------------------------------- */

$slots  = array_values(array_map(static fn($entry) => $entry['block'], $rails));
$keys   = array_keys($rails);
$paths  = array_values(array_map(static fn($entry) => $entry['path'], $rails));
// NUOVI ARRIVI first, the rest as they were.
$order    = array(3, 0, 1, 2, 4, 5, 6, 7, 8);
$arranged = ghb_hub_arrange_blocks($html, $slots, $order, 'serialize_block_attributes');
check($arranged['ok'], 'the sections can be put in another order', $arranged);
$arranged_blocks = parse_blocks($arranged['content']);
$arranged_rails  = ghb_hub_page_rails($arranged_blocks);
same(array_keys($arranged_rails), array_map(static fn($i) => $keys[$i], $order), 'they read back in the new order');
same(array_values(array_map(static fn($entry) => $entry['path'], $arranged_rails)), $paths, 'in the same places of the page: no section left its group');
same(ghb_hub_block_at_path($arranged_blocks, '8.3')['blockName'], 'golden-hive/category-slider', 'the category slider did not move');
foreach (ghb_hub_leaf_blocks($arranged_blocks) as $i => $leaf) {
    if (!ghb_hub_rail_from_block($leaf[1])) {
        same($leaf, $before_leaves[$i], 'not a section, not moved: ' . $leaf[0]);
    }
}
same(strlen($arranged['content']), strlen($html), 'the page is as long as before');
$back = ghb_hub_arrange_blocks($arranged['content'], array_map(static fn($i) => $slots[$i], $order), array(1, 2, 3, 0, 4, 5, 6, 7, 8), 'serialize_block_attributes');
same($back['content'] ?? null, $html, 'arranged back, the page is byte for byte as before');
$reversed = ghb_hub_arrange_blocks($html, $slots, array_reverse(range(0, 8)), 'serialize_block_attributes');
same(array_keys(ghb_hub_page_rails(parse_blocks($reversed['content']))), array_reverse($keys), 'any order works, across groups too');
same(ghb_hub_arrange_blocks($html, $slots, array(0, 0, 1, 2, 3, 4, 5, 6, 7), 'serialize_block_attributes')['error'], 'not_a_permutation', 'a rail twice is refused');
same(ghb_hub_arrange_blocks($html, $slots, array(0, 1), 'serialize_block_attributes')['error'], 'not_a_permutation', 'every rail must be placed');
same(ghb_hub_arrange_blocks($doubled, $slots, range(0, 8), 'serialize_block_attributes')['error'], 'ambiguous', 'a rail present twice is not moved');

/* ---------------------------------------------------------------- *
 * The page template, pages/homepage.html.
 * ---------------------------------------------------------------- */

// The template adds blocks around the rails ("Nella tua taglia", the drop
// calendar, the newsletter): the Hub still finds the same nine rails, and a
// shortcode wrapper holding [gh_size_rail] is not one of them.
$template = parse_blocks(file_get_contents(dirname(__DIR__) . '/pages/homepage.html'));
same(array_keys(ghb_hub_page_rails($template)), array_keys(ghb_hub_page_rails($blocks)), 'the page template has the same nine rails');
$size_wrapper = null;
foreach (ghb_hub_leaf_blocks($template) as $leaf) {
    if (false !== strpos((string) ($leaf[1]['attrs']['shortcode'] ?? ''), '[gh_size_rail')) {
        $size_wrapper = $leaf[1];
    }
}
check(null !== $size_wrapper, 'the template has a "Nella tua taglia" wrapper');
same(ghb_hub_rail_from_block($size_wrapper ?? array()), null, 'a wrapper holding [gh_size_rail] is not a Hub rail');

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
