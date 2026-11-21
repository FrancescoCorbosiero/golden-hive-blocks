<?php
/**
 * Tests for the store's details (includes/store-core.php): cleaning, the
 * "left out takes the store's value" rule, what blocks show from the store,
 * and the one-time move's two decisions.
 *
 * Run from the plugin root:   php tests/store-test.php
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/store-core.php';

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

/** A parsed block, as parse_blocks() returns it. */
function block(string $name, array $attrs = array(), array $inner = array()): array
{
    return array('blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => '', 'innerContent' => array());
}

/* ---------------------------------------------------------------- *
 * No store's data in the defaults
 * ---------------------------------------------------------------- */

foreach (GHB_STORE_DEFAULTS as $key => $default) {
    if (!in_array($key, array('whatsapp_message', 'holidays'), true)) {
        same($default, '', "$key starts empty");
    }
}
same(ghb_store_clean_all(array()), GHB_STORE_DEFAULTS, 'nothing saved: the defaults');

/* ---------------------------------------------------------------- *
 * Cleaning
 * ---------------------------------------------------------------- */

same(ghb_store_clean('address', "  Via Roma 1,\n 00100   Roma <b>RM</b> "), 'Via Roma 1, 00100 Roma RM', 'one line, no tags');
same(ghb_store_clean('vat', 'P.IVA: 01234567890'), '01234567890', 'the label goes from a P.IVA');
same(ghb_store_clean('vat', 'Partita IVA 01234567890'), '01234567890', 'the long label too');
same(ghb_store_clean('vat', 'IT01234567890'), 'IT01234567890', 'a country prefix stays');
same(ghb_store_clean('phone', '+39 (0523) 123-456 <script>'), '+39 (0523) 123-456', 'a phone keeps its punctuation, nothing else');
same(ghb_store_clean('email', 'info@example.com'), 'info@example.com', 'an email');
same(ghb_store_clean('email', 'not an email'), '', 'a bad email is empty');
same(ghb_store_clean('instagram', 'https://www.instagram.com/example/'), 'https://www.instagram.com/example/', 'a profile link');
same(ghb_store_clean('instagram', 'javascript:alert(1)'), '', 'only http(s) links');
same(ghb_store_clean('holidays', 'no'), 'no', 'holidays off');
same(ghb_store_clean('holidays', 'maybe'), 'yes', 'an odd value keeps the default');
same(ghb_store_clean('closed', "24/12/2026\n\n  2026-12-31 \n"), "24/12/2026\n2026-12-31", 'closures: one per line, blanks dropped');
same(ghb_store_clean('map', 'just text'), '', 'a map is an iframe or nothing');
same(ghb_store_clean('nonsense', 'x'), '', 'an unknown detail is empty');
same(ghb_store_digits('+39 351 760 3869'), '393517603869', 'a WhatsApp number: digits only');

same(
    ghb_store_clean_badges(array(array('icon' => 'shipping', 'title' => ' Spedizione tracciata '), array('icon' => 'nope', 'title' => 'Reso'), array('title' => ''), 'x')),
    array(array('icon' => 'shipping', 'title' => 'Spedizione tracciata'), array('icon' => 'authentic', 'title' => 'Reso')),
    'badges: an unknown icon falls back, an empty one is dropped'
);
same(count(ghb_store_clean_badges(array_fill(0, 9, array('icon' => 'quality', 'title' => 'Ok')))), GHB_STORE_MAX_BADGES, 'at most six badges');
same(ghb_store_clean_badges('[{"icon":"returns","title":"Reso"}]'), array(array('icon' => 'returns', 'title' => 'Reso')), 'badges as JSON (the Customizer)');

/* ---------------------------------------------------------------- *
 * What blocks show from the store
 * ---------------------------------------------------------------- */

$store = ghb_store_clean_all(array(
    'address'   => 'Via Roma 1, Roma',
    'vat'       => '01234567890',
    'instagram' => 'https://www.instagram.com/example.shop/',
    'hours_1'   => '9.00 - 13.00',
    'hours_6'   => '10.00 - 19.00',
    'holidays'  => 'no',
)) + array('whatsapp' => '390000000000', 'badges' => array(array('icon' => 'secure', 'title' => 'Pagamenti sicuri')));

same(ghb_store_block_value($store, 'golden-hive/contact-info', 'address'), 'Via Roma 1, Roma', 'Contact Info: the address');
same(ghb_store_block_value($store, 'golden-hive/contact-info', 'vat'), 'P.IVA 01234567890', 'the P.IVA with its label');
same(ghb_store_vat_line(array('vat' => '01234567890', 'legal_name' => 'Esempio srl')), 'Esempio srl · P.IVA 01234567890', 'after the legal name');
same(ghb_store_vat_line(array('vat' => '', 'legal_name' => 'Esempio srl')), '', 'no P.IVA, no line');
same(ghb_store_block_value($store, 'golden-hive/contact-info', 'title'), null, 'the title is the block\'s own');
same(ghb_store_block_value($store, 'golden-hive/store-hours', 'closedOnHolidays'), false, 'holidays as a boolean');
same(ghb_store_block_value($store, 'golden-hive/whatsapp-button', 'phoneNumber'), '390000000000', 'the WhatsApp number');
same(ghb_store_block_value($store, 'golden-hive/whatsapp-button', 'message'), 'Ciao! Vorrei avere informazioni su...', 'the default message');
same(ghb_store_block_value($store, 'golden-hive/social-buttons', 'twitter'), '', 'a profile the store hasn\'t set');
same(ghb_store_block_value($store, 'golden-hive/instagram-feed', 'buttonText'), '@example.shop', 'the Instagram button: the handle');
same(ghb_store_block_value($store, 'golden-hive/trust-badges', 'badges'), $store['badges'], 'the trust badges');

$rows = ghb_store_block_value($store, 'golden-hive/store-visit', 'hours');
same(count($rows), 7, 'hours: the whole week once a day is filled');
same($rows[0], array('day' => 'Lunedì', 'time' => '9.00 - 13.00'), 'Monday as typed');
same($rows[1], array('day' => 'Martedì', 'time' => 'Chiuso'), 'an empty day is closed');
same($rows[6]['day'], 'Domenica', 'the week ends on Sunday');
same(ghb_store_hours_rows(GHB_STORE_DEFAULTS), array(), 'no day filled: no hours at all');

same(ghb_store_instagram_handle('https://instagram.com/name_1?igsh=abc'), '@name_1', 'a handle from a shared link');
same(ghb_store_instagram_handle('https://www.instagram.com/p/Cx123/'), '', 'a post is not a profile');
same(ghb_store_instagram_handle('https://example.com/name'), '', 'another site has no handle');

/* ---------------------------------------------------------------- *
 * The rule: left out, the store's; written, even empty, the page's
 * ---------------------------------------------------------------- */

same(ghb_store_pick(array(), 'address', 'Via Roma 1'), 'Via Roma 1', 'left out: the store\'s');
same(ghb_store_pick(array('address' => ''), 'address', 'Via Roma 1'), '', 'written empty: hidden on that page');
same(ghb_store_pick(array('address' => 'Corso Italia 5'), 'address', 'Via Roma 1'), 'Corso Italia 5', 'written: the page\'s');

/* ---------------------------------------------------------------- *
 * Every store-backed attribute exists and has no default, or WordPress
 * would fill it in and "left out" could never be seen.
 * ---------------------------------------------------------------- */

foreach (GHB_STORE_BLOCK_FIELDS as $name => $fields) {
    $json  = json_decode((string) file_get_contents(dirname(__DIR__) . '/blocks/' . substr($name, strlen('golden-hive/')) . '/block.json'), true);
    $attrs = (array) ($json['attributes'] ?? array());
    foreach ($fields as $attribute => $field) {
        same(isset($attrs[$attribute]), true, "$name has $attribute");
        same(array_key_exists('default', (array) ($attrs[$attribute] ?? array())), false, "$name.$attribute has no default");
        same(isset(GHB_STORE_LABELS[$field]), true, "$field has a name in the Customizer");
    }
}
foreach (GHB_STORE_FORMER_DEFAULTS as $name => $attributes) {
    $json = json_decode((string) file_get_contents(dirname(__DIR__) . '/blocks/' . substr($name, strlen('golden-hive/')) . '/block.json'), true);
    foreach ($attributes as $attribute) {
        same(isset($json['attributes'][$attribute]), true, "former default $name.$attribute exists");
        same(isset(GHB_STORE_ATTR_NAMES[$attribute]), true, "$attribute has a name for the notice");
    }
}

/* ---------------------------------------------------------------- *
 * The one-time move: only the store's own content
 * ---------------------------------------------------------------- */

$page = array(
    block('core/heading'),
    block('core/group', array(), array(
        block('golden-hive/whatsapp-button', array('phoneNumber' => '+39 333 111 2222')),
        block('golden-hive/store-visit', array('title' => 'Vieni a trovarci', 'subtitle' => '')),
    )),
    block('golden-hive/whatsapp-button', array('phoneNumber' => '393331112222', 'iconOnly' => true)),
    block('golden-hive/whatsapp-button', array('message' => 'Ciao')),
    block('golden-hive/contact-info', array('address' => '', 'vat' => '', 'subtitle' => 'Scrivici')),
);
same(count(ghb_store_walk_blocks($page)), 7, 'nested blocks are walked');
same(ghb_store_whatsapp_from_blocks($page), '393331112222', 'every button uses the same number: that one');
same(ghb_store_whatsapp_from_blocks(array_merge($page, array(block('golden-hive/whatsapp-button', array('phoneNumber' => '393339999999'))))), '', 'two numbers: none chosen');
same(ghb_store_whatsapp_from_blocks(array(block('golden-hive/whatsapp-button'))), '', 'no number written: none');
same(
    ghb_store_relied_defaults($page),
    array(array('golden-hive/store-visit', array('address', 'hours', 'mapEmbed'))),
    'listed: the block that left out a former default (written empty does not count)'
);
same(ghb_store_relied_defaults(array(block('golden-hive/contact-info', array('subtitle' => 'x', 'address' => 'y', 'vat' => '')))), array(), 'a block that wrote every former default is fine');

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
