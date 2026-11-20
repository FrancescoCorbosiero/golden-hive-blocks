<?php
/**
 * Golden Hive — page checker, core.
 *
 * Reads a page's block markup (what Gutenberg's code editor shows, or what
 * the REST API stores in post_content) and reports what WordPress would get
 * wrong or silently ignore, before the page goes anywhere near the site:
 *
 *   - a block delimiter whose attributes aren't valid JSON (WordPress drops
 *     every attribute of that block, without a word);
 *   - a Golden Hive block that doesn't exist, an attribute it doesn't have, a
 *     value of the wrong type or outside its allowed values, a list item key
 *     its template never reads;
 *   - a [gh_product_rail] / [gh_size_rail] attribute the rail doesn't know or
 *     a value it would fall back from, and a product rail the Store Hub can't
 *     see (outside a Shortcode Wrapper block);
 *   - shortcodes no Golden Hive code provides (fine if another plugin does);
 *   - motion hooks with values motion.js doesn't know, in raw HTML blocks;
 *   - HTML outside any block (WordPress makes it a Classic block).
 *
 * Plain PHP, no WordPress: the block parser is WordPress's own, copied in
 * tests/wp/. The command line is tools/check-page.php; the tests are
 * tests/page-check-test.php.
 *
 * @package Golden_Hive_Blocks
 */

if (PHP_SAPI !== 'cli') {
    exit; // a command-line tool: never runnable over HTTP
}
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!class_exists('WP_Block_Parser')) {
    require dirname(__DIR__) . '/tests/wp/class-wp-block-parser.php';
}

/** Attributes WordPress itself handles on every block (block supports). */
const GHB_PC_SUPPORT_ATTRS = array('className', 'anchor', 'align', 'style', 'lock', 'metadata');

/** Motion hook values js/motion.js and js/animations.js understand. */
const GHB_PC_REVEALS = array('', 'up', 'down', 'left', 'right', 'scale', 'stagger');
const GHB_PC_SPLITS = array('chars', 'words', 'lines');

/** [gh_product_rail]: values the rail understands (includes/product-rail.php, product-query.php). */
const GHB_PC_RAIL_TYPES = array('recent', 'featured', 'best_selling', 'sale', 'top_rated');
const GHB_PC_RAIL_FALLBACKS = array('menu_order', 'date', 'popularity', 'price', 'price-desc', 'rating');
const GHB_PC_RAIL_RATIOS = array('square', 'portrait', 'tall', 'landscape', 'wide');
const GHB_PC_RAIL_FITS = array('cover', 'contain');

/**
 * What the checker knows about the plugin, read from its files: every
 * block's attributes (block.json) and the list item keys its render.php
 * reads, the shortcodes the plugin registers, and the attributes each rail
 * shortcode accepts.
 */
function ghb_pc_load_specs(string $root): array
{
    $blocks = array();
    foreach (glob($root . '/blocks/*/block.json') as $file) {
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json) || empty($json['name'])) {
            continue;
        }
        // List item keys the template reads ($slide['image']), not the
        // block's own attributes ($attributes['title']).
        $render = (string) @file_get_contents(dirname($file) . '/render.php');
        preg_match_all('/\$(?!attributes\b)[a-z_]+\[\'([A-Za-z0-9_]+)\'\]/', $render, $m);
        $read = array_values(array_unique($m[1]));

        $attrs = array();
        foreach ((array) ($json['attributes'] ?? array()) as $key => $spec) {
            $items = array();
            if (isset($spec['items']['properties']) && is_array($spec['items']['properties'])) {
                $items = array_keys($spec['items']['properties']);
            }
            foreach ((array) ($spec['default'] ?? array()) as $item) {
                if (is_array($item)) {
                    $items = array_merge($items, array_keys($item));
                }
            }
            $attrs[$key] = array(
                'type'  => $spec['type'] ?? null,
                'enum'  => $spec['enum'] ?? null,
                'items' => ('array' === ($spec['type'] ?? null)) ? array_values(array_unique(array_merge($items, $read))) : null,
            );
        }
        $blocks[$json['name']] = array(
            'attrs'       => $attrs,
            'title'       => $json['title'] ?? $json['name'],
            'description' => $json['description'] ?? '',
            'defaults'    => array_map(function ($spec) {
                return $spec['default'] ?? null;
            }, (array) ($json['attributes'] ?? array())),
        );
    }

    $shortcodes = array();
    foreach (glob($root . '/includes/*.php') as $file) {
        preg_match_all('/add_shortcode\(\s*\'([a-z0-9_]+)\'/', (string) file_get_contents($file), $m);
        $shortcodes = array_merge($shortcodes, $m[1]);
    }

    $rail = (string) @file_get_contents($root . '/includes/product-rail.php');
    $rail_atts = array();
    if (preg_match('/function ghb_product_rail_defaults\(\)\s*\{(.*?)\n\}/s', $rail, $m)) {
        preg_match_all('/^\s*\'([a-z_]+)\'\s*=>/m', $m[1], $k);
        $rail_atts = $k[1];
    }
    $size = (string) @file_get_contents($root . '/includes/size-rail.php');
    $size_atts = array();
    if (preg_match('/shortcode_atts\(\s*array\((.*?)\),\s*\$atts,\s*\'gh_size_rail\'/s', $size, $m)) {
        preg_match_all('/^\s*\'([a-z_]+)\'\s*=>/m', $m[1], $k);
        $size_atts = $k[1];
    }

    return array(
        'blocks'     => $blocks,
        'shortcodes' => array_values(array_unique($shortcodes)),
        'rail'       => $rail_atts,
        'size_rail'  => $size_atts,
    );
}

/**
 * Check a page. Returns a list of issues, each
 * array('level' => 'error'|'warning', 'where' => string, 'message' => string).
 * An error is something WordPress or the plugin would get wrong or drop; a
 * warning is worth a look.
 */
function ghb_pc_check(string $markup, array $specs): array
{
    $issues = array();
    $parser = new WP_Block_Parser();
    $blocks = $parser->parse($markup);

    foreach ($blocks as $block) {
        if (null === $block['blockName'] && '' !== trim(preg_replace('/<!--.*?-->/s', '', (string) $block['innerHTML']))) {
            $issues[] = ghb_pc_issue('warning', 'page', 'HTML outside any block: WordPress turns it into a Classic block. Wrap it in <!-- wp:html -->.');
        }
    }

    ghb_pc_walk($blocks, function (array $block, string $where) use (&$issues, $specs) {
        $name = (string) $block['blockName'];

        if (null === $block['attrs']) {
            $issues[] = ghb_pc_issue('error', $where, 'its attributes are not valid JSON, so WordPress would drop all of them. Check quotes inside values: write " as \u0022.');
            return;
        }

        if (0 === strpos($name, 'golden-hive/')) {
            $issues = array_merge($issues, ghb_pc_check_block($block, $where, $specs));
        }

        // Shortcodes: a Shortcode Wrapper's attribute, a Shortcode block's content.
        $text = '';
        if ('golden-hive/shortcode-wrapper' === $name) {
            $text = (string) ($block['attrs']['shortcode'] ?? '');
        } elseif ('core/shortcode' === $name || 'core/html' === $name || 'core/paragraph' === $name) {
            $text = (string) $block['innerHTML'];
        }
        if ('' !== $text) {
            $issues = array_merge($issues, ghb_pc_check_shortcodes($text, $name, $where, $specs));
        }

        if ('core/html' === $name) {
            $issues = array_merge($issues, ghb_pc_check_motion((string) $block['innerHTML'], $where));
        }
    });

    return $issues;
}

/** Visit every block, depth first, with a readable location. */
function ghb_pc_walk(array $blocks, callable $visit, string $prefix = ''): void
{
    $n = 0;
    foreach ($blocks as $block) {
        if (null === $block['blockName']) {
            continue;
        }
        $n++;
        $where = ('' === $prefix ? '' : $prefix . ' > ') . '#' . $n . ' ' . preg_replace('#^core/#', '', $block['blockName']);
        $visit($block, $where);
        if (!empty($block['innerBlocks'])) {
            ghb_pc_walk($block['innerBlocks'], $visit, $where);
        }
    }
}

function ghb_pc_issue(string $level, string $where, string $message): array
{
    return array('level' => $level, 'where' => $where, 'message' => $message);
}

/** A Golden Hive block against its block.json. */
function ghb_pc_check_block(array $block, string $where, array $specs): array
{
    $issues = array();
    $name = $block['blockName'];
    if (!isset($specs['blocks'][$name])) {
        return array(ghb_pc_issue('error', $where, 'there is no block "' . $name . '" in Golden Hive Blocks.'));
    }
    $known = $specs['blocks'][$name]['attrs'];

    if ('' !== trim((string) $block['innerHTML'])) {
        $issues[] = ghb_pc_issue('warning', $where, 'Golden Hive blocks are drawn by the server: the HTML inside it is ignored. Write it as <!-- wp:' . $name . ' {...} /-->.');
    }

    foreach ($block['attrs'] as $key => $value) {
        if (in_array($key, GHB_PC_SUPPORT_ATTRS, true)) {
            continue;
        }
        if (!isset($known[$key])) {
            $issues[] = ghb_pc_issue('error', $where, 'unknown attribute "' . $key . '": the block ignores it. Known: ' . implode(', ', array_keys($known)) . '.');
            continue;
        }
        $spec = $known[$key];
        if (!ghb_pc_type_ok($spec['type'], $value)) {
            $issues[] = ghb_pc_issue('error', $where, '"' . $key . '" should be a ' . (is_array($spec['type']) ? implode(' or ', $spec['type']) : $spec['type']) . ', not ' . ghb_pc_type_of($value) . '.');
            continue;
        }
        if (is_array($spec['enum']) && !in_array($value, $spec['enum'], true)) {
            $issues[] = ghb_pc_issue('error', $where, '"' . $key . '" must be one of: ' . implode(', ', $spec['enum']) . '.');
        }
        if (is_array($spec['items']) && $spec['items'] && is_array($value)) {
            $unknown = array();
            foreach ($value as $item) {
                if (is_array($item)) {
                    $unknown = array_merge($unknown, array_diff(array_keys($item), $spec['items']));
                }
            }
            foreach (array_unique($unknown) as $k) {
                $issues[] = ghb_pc_issue('warning', $where, '"' . $key . '" items have "' . $k . '", which the block never reads. Known: ' . implode(', ', $spec['items']) . '.');
            }
        }
    }
    return $issues;
}

function ghb_pc_type_ok($type, $value): bool
{
    if (null === $type) {
        return true;
    }
    foreach ((array) $type as $t) {
        $ok = ('string' === $t && is_string($value))
            || ('boolean' === $t && is_bool($value))
            || ('number' === $t && (is_int($value) || is_float($value)))
            || ('integer' === $t && is_int($value))
            || ('array' === $t && is_array($value) && array_values($value) === $value)
            || ('object' === $t && is_array($value))
            || ('null' === $t && null === $value);
        if ($ok) {
            return true;
        }
    }
    return false;
}

function ghb_pc_type_of($value): string
{
    if (is_array($value)) {
        return array_values($value) === $value ? 'a list' : 'an object';
    }
    if (is_bool($value)) {
        return 'true/false';
    }
    if (is_int($value) || is_float($value)) {
        return 'a number';
    }
    return null === $value ? 'null' : 'text';
}

/** Every [shortcode] in a piece of text: name and attributes. */
function ghb_pc_shortcodes(string $text): array
{
    $out = array();
    preg_match_all('/\[([a-z0-9_-]+)((?:\s+[^\]]*?)?)\s*\/?\]/i', html_entity_decode($text, ENT_QUOTES), $m, PREG_SET_ORDER);
    foreach ($m as $sc) {
        $atts = array();
        preg_match_all('/([a-z0-9_-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $sc[2], $a, PREG_SET_ORDER);
        foreach ($a as $att) {
            $atts[strtolower($att[1])] = $att[2] !== '' ? $att[2] : ($att[3] ?? '') . ($att[4] ?? '');
        }
        $out[] = array('name' => strtolower($sc[1]), 'atts' => $atts);
    }
    return $out;
}

function ghb_pc_check_shortcodes(string $text, string $block, string $where, array $specs): array
{
    $issues = array();
    foreach (ghb_pc_shortcodes($text) as $sc) {
        $name = $sc['name'];
        if (!in_array($name, $specs['shortcodes'], true)) {
            $issues[] = ghb_pc_issue('warning', $where, '[' . $name . '] is not a Golden Hive shortcode: fine if another plugin on the site provides it.');
            continue;
        }
        if ('gh_product_rail' === $name) {
            if ('golden-hive/shortcode-wrapper' !== $block) {
                $issues[] = ghb_pc_issue('warning', $where, '[gh_product_rail] outside a Shortcode Wrapper block: it renders, but the Store Hub can\'t see or edit it.');
            }
            $issues = array_merge($issues, ghb_pc_check_rail($sc['atts'], $specs['rail'], $where));
        } elseif ('gh_size_rail' === $name) {
            foreach (array_diff(array_keys($sc['atts']), $specs['size_rail']) as $k) {
                $issues[] = ghb_pc_issue('error', $where, '[gh_size_rail] has no "' . $k . '" attribute. Known: ' . implode(', ', $specs['size_rail']) . '.');
            }
        }
    }
    return $issues;
}

/** [gh_product_rail] attributes: names, and values the rail would fall back from. */
function ghb_pc_check_rail(array $atts, array $known, string $where): array
{
    $issues = array();
    $err = function (string $m) use (&$issues, $where) {
        $issues[] = ghb_pc_issue('error', $where, '[gh_product_rail] ' . $m);
    };
    foreach ($atts as $k => $v) {
        if (!in_array($k, $known, true)) {
            $err('has no "' . $k . '" attribute (ignored). Known: ' . implode(', ', $known) . '.');
            continue;
        }
        $v = trim($v);
        if ('' === $v) {
            continue;
        }
        switch ($k) {
            case 'type':
                if (!in_array($v, GHB_PC_RAIL_TYPES, true)) {
                    $err('type="' . $v . '" is not one of ' . implode(', ', GHB_PC_RAIL_TYPES) . ' (it would show the newest products).');
                }
                break;
            case 'fallback':
                if (!in_array($v, GHB_PC_RAIL_FALLBACKS, true)) {
                    $err('fallback="' . $v . '" is not one of ' . implode(', ', GHB_PC_RAIL_FALLBACKS) . '.');
                }
                break;
            case 'fit':
                if (!in_array($v, GHB_PC_RAIL_FITS, true)) {
                    $err('fit="' . $v . '" is not cover or contain.');
                }
                break;
            case 'ratio':
                if (!in_array(strtolower($v), GHB_PC_RAIL_RATIOS, true) && !preg_match('#^\d{1,2}\s*/\s*[1-9]\d?$#', $v)) {
                    $err('ratio="' . $v . '" is not ' . implode(', ', GHB_PC_RAIL_RATIOS) . ' or N/N (it would fall back to a square).');
                }
                break;
            case 'limit':
                if (!ctype_digit($v) || (int) $v < 1) {
                    $err('limit="' . $v . '" is not a whole number of 1 or more.');
                }
                break;
            case 'columns':
            case 'columns_tablet':
            case 'columns_mobile':
                if (!ctype_digit($v) || (int) $v < 1 || (int) $v > 8) {
                    $err($k . '="' . $v . '" must be a whole number from 1 to 8.');
                }
                break;
            case 'ids':
            case 'pin':
            case 'exclude':
                if (!preg_match('/^\d+(\s*,\s*\d+)*$/', $v)) {
                    $err($k . '="' . $v . '" must be product IDs separated by commas.');
                }
                break;
            case 'category':
            case 'brand':
            case 'tag':
                if (!preg_match('/^[a-z0-9-]+(\s*,\s*[a-z0-9-]+)*$/', $v)) {
                    $err($k . '="' . $v . '" must be slugs (lowercase, hyphens), not names.');
                }
                break;
        }
    }
    return $issues;
}

/** Motion hooks in raw HTML: values the scripts know. */
function ghb_pc_check_motion(string $html, string $where): array
{
    $issues = array();
    preg_match_all('/data-gh-reveal(?:="([^"]*)")?/', $html, $m);
    foreach ($m[1] as $v) {
        if (!in_array($v, GHB_PC_REVEALS, true)) {
            $issues[] = ghb_pc_issue('error', $where, 'data-gh-reveal="' . $v . '" is not one of up, down, left, right, scale, stagger.');
        }
    }
    preg_match_all('/data-gh-split="([^"]*)"/', $html, $m);
    foreach ($m[1] as $v) {
        if (!in_array($v, GHB_PC_SPLITS, true)) {
            $issues[] = ghb_pc_issue('error', $where, 'data-gh-split="' . $v . '" is not chars, words or lines.');
        }
    }
    return $issues;
}
