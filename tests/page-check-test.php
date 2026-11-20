<?php
/**
 * Tests for the page checker (tools/page-check-core.php).
 *
 * Run from the plugin root:   php tests/page-check-test.php
 *
 * The checker reads the plugin's own files for what it knows (block.json,
 * render.php, the rail shortcodes), so these tests also prove it reads them
 * right: a clean page passes, and each kind of mistake is caught.
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

require dirname(__DIR__) . '/tools/page-check-core.php';

$GLOBALS['ghb_failures'] = 0;
$GLOBALS['ghb_passes']   = 0;

function check($cond, string $what, $detail = null): void
{
    if ($cond) {
        $GLOBALS['ghb_passes']++;
        return;
    }
    $GLOBALS['ghb_failures']++;
    echo "FAIL: $what\n";
    if (null !== $detail) {
        echo '      ' . str_replace("\n", "\n      ", var_export($detail, true)) . "\n";
    }
}

$specs = ghb_pc_load_specs(dirname(__DIR__));

/** The issues of one level for some markup. */
function issues(string $markup, string $level = 'error'): array
{
    return array_values(array_filter(ghb_pc_check($markup, $GLOBALS['specs']), function ($i) use ($level) {
        return $i['level'] === $level;
    }));
}

/** Whether exactly one issue of that level came back, mentioning $needle. */
function caught(string $markup, string $needle, string $what, string $level = 'error'): void
{
    $found = issues($markup, $level);
    check(1 === count($found) && false !== strpos($found[0]['message'], $needle), $what, $found);
}

/* ---------------------------------------------------------------- *
 * What it reads from the plugin.
 * ---------------------------------------------------------------- */

check(count($specs['blocks']) === count(glob(dirname(__DIR__) . '/blocks/*/block.json')), 'every block is known');
check(isset($specs['blocks']['golden-hive/hero-carousel']['attrs']['slides']), 'block attributes come from block.json');
check(in_array('objectPosition', $specs['blocks']['golden-hive/hero-carousel']['attrs']['slides']['items'], true), 'list item keys include what render.php reads');
foreach (array('gh_product_rail', 'gh_size_rail', 'gh_filters', 'bfl_filters') as $sc) {
    check(in_array($sc, $specs['shortcodes'], true), "[$sc] is a known shortcode");
}
foreach (array('category', 'brand', 'pin', 'exclude', 'fallback', 'limit', 'columns_mobile', 'ratio', 'fit') as $att) {
    check(in_array($att, $specs['rail'], true), "[gh_product_rail] knows $att");
}
check(array('title', 'text', 'limit', 'columns') === $specs['size_rail'], '[gh_size_rail] attributes come from size-rail.php', $specs['size_rail']);

/* ---------------------------------------------------------------- *
 * Clean pages pass.
 * ---------------------------------------------------------------- */

foreach (array('homepage.html', 'homepage-sections.html') as $fixture) {
    $errors = issues((string) file_get_contents(__DIR__ . '/fixtures/' . $fixture));
    check(array() === $errors, "$fixture: no errors", $errors);
}

// The skeletons in docs/pages.md are the spec's own examples: they must pass.
preg_match_all('/```html\n(.*?)```/s', (string) file_get_contents(dirname(__DIR__) . '/docs/pages.md'), $doc);
check(count($doc[1]) >= 5, 'docs/pages.md has its skeletons', count($doc[1]));
foreach ($doc[1] as $i => $example) {
    $found = ghb_pc_check($example, $specs);
    check(array() === $found, 'docs/pages.md example ' . ($i + 1) . ': no errors or warnings', $found);
}

/* ---------------------------------------------------------------- *
 * Each mistake is caught.
 * ---------------------------------------------------------------- */

// The one that loses a block's settings without a word: bare quotes in JSON.
caught('<!-- wp:golden-hive/shortcode-wrapper {"shortcode":"[gh_product_rail category="saldi"]"} /-->',
    'not valid JSON', 'bare quotes inside a JSON value');

caught('<!-- wp:golden-hive/hero-banner {"title":"X"} /-->', 'no block', 'a block that does not exist');
caught('<!-- wp:golden-hive/cta-banner {"titel":"X"} /-->', 'unknown attribute "titel"', 'a misspelt attribute');
caught('<!-- wp:golden-hive/whatsapp-button {"iconOnly":"yes"} /-->', 'should be a boolean', 'a value of the wrong type');
caught('<!-- wp:golden-hive/trust-badges {"variant":"grid"} /-->', 'must be one of', 'a value outside its enum');
caught('<!-- wp:golden-hive/faq-schema {"items":[{"question":"Q","answer":"A","icon":"x"}]} /-->',
    'never reads', 'a list item key the template ignores', 'warning');
caught("<!-- wp:golden-hive/cta-banner {\"title\":\"X\"} -->\n<div>ciao</div>\n<!-- /wp:golden-hive/cta-banner -->",
    'ignored', 'HTML inside a server-drawn block', 'warning');
check(array() === issues('<!-- wp:golden-hive/cta-banner {"title":"X","className":"is-dark","align":"full"} /-->'),
    'block supports (className, align) are fine');

// Rails.
$wrap = function (string $sc): string {
    return '<!-- wp:golden-hive/shortcode-wrapper ' . json_encode(array('shortcode' => $sc), JSON_HEX_QUOT) . ' /-->';
};
check(array() === issues($wrap('[gh_product_rail category="saldi-outlet" limit="18" columns="4" pin="12,5" fallback="date" ratio="3/4" fit="cover"]')),
    'a well-formed rail passes');
caught($wrap('[gh_product_rail categoria="saldi"]'), 'no "categoria"', 'an unknown rail attribute');
caught($wrap('[gh_product_rail type="newest"]'), 'type="newest"', 'a rail type it would fall back from');
caught($wrap('[gh_product_rail columns="9"]'), '1 to 8', 'too many columns');
caught($wrap('[gh_product_rail category="Saldi Estivi"]'), 'slugs', 'a category name instead of a slug');
caught($wrap('[gh_product_rail pin="12, abc"]'), 'product IDs', 'pins that are not IDs');
caught($wrap('[gh_product_rail fallback="newest"]'), 'fallback="newest"', 'an unknown fallback order');
caught($wrap('[gh_size_rail size="42"]'), 'no "size"', 'an unknown size-rail attribute');
caught("<!-- wp:shortcode -->\n[gh_product_rail brand=\"nike\"]\n<!-- /wp:shortcode -->",
    'Store Hub', 'a rail the Store Hub cannot see', 'warning');
caught("<!-- wp:shortcode -->\n[contact-form-7 id=\"1\"]\n<!-- /wp:shortcode -->",
    'not a Golden Hive shortcode', "another plugin's shortcode", 'warning');

// Motion hooks in raw HTML.
caught("<!-- wp:html -->\n<h2 data-gh-split=\"line\">X</h2>\n<!-- /wp:html -->", 'data-gh-split="line"', 'a split value motion.js does not know');
caught("<!-- wp:html -->\n<div data-gh-reveal=\"fade\">X</div>\n<!-- /wp:html -->", 'data-gh-reveal="fade"', 'a reveal value motion.js does not know');
check(array() === issues("<!-- wp:html -->\n<div data-gh-reveal=\"up\"><h2 data-gh-split=\"lines\">X</h2></div>\n<!-- /wp:html -->"),
    'known motion hooks pass');

// Outside any block.
caught("<p>loose</p>\n\n<!-- wp:golden-hive/cta-banner {\"title\":\"X\"} /-->", 'Classic block', 'HTML outside any block', 'warning');

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
