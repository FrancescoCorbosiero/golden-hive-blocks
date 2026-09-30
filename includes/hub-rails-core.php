<?php
/**
 * Golden Hive — Store Hub bridge, pure core.
 *
 * Everything here is plain PHP: no WordPress call, no database. It is the part
 * of the "Vetrina" bridge that decides things — how a rail orders its products,
 * how a shortcode is read and rewritten, how one block is found in the page
 * content and replaced — so it is unit-tested against the real homepage markup
 * (tests/hub-rails-test.php) without a WordPress install.
 *
 * The WordPress side (REST routes, queries, caches) lives in hub-rails.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Most product ids a rail keeps in `pin` or `exclude`. */
const GHB_HUB_MAX_IDS = 100;

/** The shortcode the Vetrina edits. */
const GHB_HUB_RAIL_TAG = 'gh_product_rail';

/** The block that wraps a rail on the homepage. */
const GHB_HUB_RAIL_BLOCK = 'golden-hive/shortcode-wrapper';

/**
 * Orderings a rail may use after its pinned products. The values are
 * WooCommerce's own catalog orderings — the "Ordina per" options of the shop.
 */
function ghb_hub_fallbacks(): array
{
    return array('menu_order', 'date', 'popularity', 'price', 'price-desc', 'rating');
}

/**
 * A list of product ids from a shortcode value ("12, 5,9") or an array:
 * positive integers only, first occurrence wins, capped.
 *
 * @param mixed $raw
 * @return int[]
 */
function ghb_hub_parse_id_list($raw, int $max = GHB_HUB_MAX_IDS): array
{
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    if (!is_array($raw)) {
        return array();
    }
    $out = array();
    foreach ($raw as $value) {
        if (is_string($value)) {
            $value = trim($value);
            if (!preg_match('/^\d+$/', $value)) {
                continue;
            }
        } elseif (!is_int($value)) {
            continue;
        }
        $id = (int) $value;
        if ($id <= 0 || isset($out[$id])) {
            continue;
        }
        $out[$id] = $id;
        if (count($out) >= $max) {
            break;
        }
    }
    return array_values($out);
}

/**
 * The order a rail falls back to when it names none: what the rail does today.
 * A category or brand rail sorts by the shop's custom order (menu_order, then
 * title); the metric types keep their metric.
 */
function ghb_hub_default_fallback(array $atts): string
{
    $type = isset($atts['type']) ? (string) $atts['type'] : 'recent';
    if ('best_selling' === $type) {
        return 'popularity';
    }
    if ('top_rated' === $type) {
        return 'rating';
    }
    if (!empty($atts['category']) || !empty($atts['brand'])) {
        return 'menu_order';
    }
    return 'date';
}

/** A fallback name, or the rail's default when it is missing or unknown. */
function ghb_hub_normalize_fallback($raw, array $atts): string
{
    $value = is_string($raw) ? strtolower(trim($raw)) : '';
    return in_array($value, ghb_hub_fallbacks(), true) ? $value : ghb_hub_default_fallback($atts);
}

/**
 * THE ordering rule of a Vetrina rail, in one place:
 * pinned products first, in pin order, but only those actually visible;
 * then every other visible product in the order the fallback produced;
 * excluded products never.
 *
 * @param int[] $visible Visible products of the rail, already in fallback order.
 * @param int[] $pin
 * @param int[] $exclude
 * @return int[]
 */
function ghb_hub_order_ids(array $visible, array $pin, array $exclude): array
{
    $visible_set = array_fill_keys(array_map('intval', $visible), true);
    $excluded    = array_fill_keys(array_map('intval', $exclude), true);

    $out  = array();
    $seen = array();
    foreach ($pin as $id) {
        $id = (int) $id;
        if (isset($visible_set[$id]) && !isset($excluded[$id]) && !isset($seen[$id])) {
            $out[]     = $id;
            $seen[$id] = true;
        }
    }
    foreach ($visible as $id) {
        $id = (int) $id;
        if (!isset($excluded[$id]) && !isset($seen[$id])) {
            $out[]     = $id;
            $seen[$id] = true;
        }
    }
    return $out;
}

/**
 * Read one self-closing shortcode ("[tag a="1" b='2' c=3]"), keeping the
 * attributes in the order they were written. The attribute grammar is
 * WordPress's own (shortcode_parse_atts), repeated here so the rewrite below
 * can be tested without WordPress.
 *
 * @return array{tag: string, atts: array}|null Null when the text is not exactly one shortcode.
 */
function ghb_hub_parse_shortcode(string $text): ?array
{
    $text = trim($text);
    if (!preg_match('/^\[([\w-]+)((?:\s[^\[\]]*)?)\]$/s', $text, $m)) {
        return null;
    }
    $atts    = array();
    $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
    $body    = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $m[2]);
    if (preg_match_all($pattern, (string) $body, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $atts[strtolower($match[1])] = stripcslashes($match[2]);
            } elseif (!empty($match[3])) {
                $atts[strtolower($match[3])] = stripcslashes($match[4]);
            } elseif (!empty($match[5])) {
                $atts[strtolower($match[5])] = stripcslashes($match[6]);
            } elseif (isset($match[7]) && strlen($match[7])) {
                $atts[] = stripcslashes($match[7]);
            } elseif (isset($match[8]) && strlen($match[8])) {
                $atts[] = stripcslashes($match[8]);
            } elseif (isset($match[9])) {
                $atts[] = stripcslashes($match[9]);
            }
        }
    }
    return array('tag' => $m[1], 'atts' => $atts);
}

/**
 * Write a shortcode back: [tag k="v" …], attributes in the given order.
 * Null when a value cannot be written safely (a quote or a bracket would end
 * the attribute or the shortcode early).
 */
function ghb_hub_build_shortcode(string $tag, array $atts): ?string
{
    $parts = array('[' . $tag);
    foreach ($atts as $key => $value) {
        $value = (string) $value;
        if (false !== strpbrk($value, "\"[]")) {
            return null;
        }
        $parts[] = is_int($key) ? '"' . $value . '"' : $key . '="' . $value . '"';
    }
    return implode(' ', $parts) . ']';
}

/**
 * Rewrite a rail shortcode with new pin / exclude / fallback values. Every
 * other attribute keeps its value and its place; values that say "nothing"
 * (no pins, no exclusions, the default order) are removed rather than written,
 * so an untouched rail keeps an untouched shortcode.
 *
 * @param array{pin: int[], exclude: int[], fallback: string} $values
 * @return string|null Null when the text is not a gh_product_rail shortcode.
 */
function ghb_hub_set_rail_atts(string $shortcode, array $values): ?string
{
    $parsed = ghb_hub_parse_shortcode($shortcode);
    if (!$parsed || GHB_HUB_RAIL_TAG !== $parsed['tag']) {
        return null;
    }
    $atts = $parsed['atts'];

    $pin     = implode(',', ghb_hub_parse_id_list($values['pin'] ?? array()));
    $exclude = implode(',', ghb_hub_parse_id_list($values['exclude'] ?? array()));
    $default = ghb_hub_default_fallback($atts);
    $wanted  = isset($values['fallback']) ? (string) $values['fallback'] : '';
    $fallback = in_array($wanted, ghb_hub_fallbacks(), true) ? $wanted : $default;

    foreach (array('pin' => $pin, 'exclude' => $exclude) as $key => $value) {
        if ('' === $value) {
            unset($atts[$key]);
        } else {
            $atts[$key] = $value;
        }
    }
    if ($fallback === $default) {
        unset($atts['fallback']);
    } else {
        $atts['fallback'] = $fallback;
    }
    // How many products the rail shows: only when asked, never inferred.
    if (isset($values['limit'])) {
        $atts['limit'] = (string) max(1, (int) $values['limit']);
    }

    return ghb_hub_build_shortcode($parsed['tag'], $atts);
}

/**
 * The block fields the Hub may write, and how each is checked. Anything not
 * listed here cannot be changed through wc-gh/v1, whatever the Hub sends:
 * the list is the upper bound, the Hub's config picks from it.
 *
 * type "text": plain text (tags stripped), at most max characters;
 * type "url":  an http(s) or site-relative link, or empty;
 * type "enum": one of options.
 */
function ghb_hub_field_specs(): array
{
    return array(
        GHB_HUB_RAIL_BLOCK            => array(
            'eyebrow'         => array('type' => 'text', 'max' => 80),
            'title'           => array('type' => 'text', 'max' => 120),
            'backgroundColor' => array('type' => 'enum', 'options' => array('white', 'gray', 'black')),
            'buttonText'      => array('type' => 'text', 'max' => 60),
            'buttonUrl'       => array('type' => 'url', 'max' => 500),
        ),
        'golden-hive/category-slider' => array(
            'title' => array('type' => 'text', 'max' => 120),
        ),
        'golden-hive/brand-marquee'   => array(
            'title' => array('type' => 'text', 'max' => 120),
        ),
        'golden-hive/faq-schema'      => array(
            'title'    => array('type' => 'text', 'max' => 120),
            'subtitle' => array('type' => 'text', 'max' => 300),
        ),
        'golden-hive/social-proof'    => array(
            'title' => array('type' => 'text', 'max' => 120),
        ),
        'golden-hive/whatsapp-button' => array(
            'buttonText' => array('type' => 'text', 'max' => 60),
            'message'    => array('type' => 'text', 'max' => 300),
        ),
    );
}

/**
 * A block's editable fields as the site renders them: the saved value, or the
 * block's default when the attribute is not saved.
 *
 * @param array<string, array>  $spec     field => rule (ghb_hub_field_specs)
 * @param array<string, string> $defaults field => the block type's default
 * @return array<string, string>
 */
function ghb_hub_block_fields(array $attrs, array $spec, array $defaults): array
{
    $fields = array();
    foreach (array_keys($spec) as $field) {
        $value          = array_key_exists($field, $attrs) ? $attrs[$field] : ($defaults[$field] ?? '');
        $fields[$field] = is_scalar($value) ? (string) $value : '';
    }
    return $fields;
}

/**
 * Apply field edits to a block's attributes. Every field must be in the
 * block's spec; each value goes through $clean (WordPress's sanitizers,
 * injected so this stays testable without WordPress) and is then checked
 * against its rule. A value equal to the block's default is removed rather
 * than written, as the rail shortcode does.
 *
 * @param array<string, mixed>  $changes  field => new value
 * @param array<string, array>  $spec     field => rule
 * @param array<string, string> $defaults field => the block type's default
 * @param callable(string, string): string $clean (rule type, raw value) => clean value
 * @return array{ok: true, attrs: array, changed: string[]}|array{ok: false, error: string, field: string}
 */
function ghb_hub_apply_fields(array $attrs, array $changes, array $spec, array $defaults, callable $clean): array
{
    $current = ghb_hub_block_fields($attrs, $spec, $defaults);
    $changed = array();
    foreach ($changes as $field => $raw) {
        $field = (string) $field;
        if (!isset($spec[$field])) {
            return array('ok' => false, 'error' => 'not_editable', 'field' => $field);
        }
        if (!is_string($raw)) {
            return array('ok' => false, 'error' => 'not_text', 'field' => $field);
        }
        $rule  = $spec[$field];
        $value = (string) $clean($rule['type'], $raw);
        if ('enum' === $rule['type'] && !in_array($value, $rule['options'], true)) {
            return array('ok' => false, 'error' => 'not_an_option', 'field' => $field);
        }
        if ('url' === $rule['type'] && '' === $value && '' !== trim($raw)) {
            return array('ok' => false, 'error' => 'bad_url', 'field' => $field);
        }
        if (isset($rule['max']) && mb_strlen($value) > (int) $rule['max']) {
            return array('ok' => false, 'error' => 'too_long', 'field' => $field);
        }
        if ($value === $current[$field]) {
            continue;
        }
        if ($value === (string) ($defaults[$field] ?? '')) {
            unset($attrs[$field]);
        } else {
            $attrs[$field] = $value;
        }
        $current[$field] = $value;
        $changed[]       = $field;
    }
    return array('ok' => true, 'attrs' => $attrs, 'changed' => $changed);
}

/**
 * The rail inside a block, when the block is a shortcode wrapper holding a
 * gh_product_rail. Pure: taxonomy names are left for the WordPress side.
 *
 * @return array|null { shortcode, atts, pin, exclude, fallback, fallback_default, key_base }
 */
function ghb_hub_rail_from_block(array $block): ?array
{
    if (GHB_HUB_RAIL_BLOCK !== ($block['blockName'] ?? null)) {
        return null;
    }
    $shortcode = isset($block['attrs']['shortcode']) ? (string) $block['attrs']['shortcode'] : '';
    $parsed    = ghb_hub_parse_shortcode($shortcode);
    if (!$parsed || GHB_HUB_RAIL_TAG !== $parsed['tag']) {
        return null;
    }
    $atts = $parsed['atts'];

    // A stable name for the rail that survives blocks being added above it:
    // what it shows, not where it sits. "#n" tells apart two rails showing
    // the same thing (added by the caller, which sees the whole page).
    if (!empty($atts['category'])) {
        $key_base = 'category:' . $atts['category'];
    } elseif (!empty($atts['brand'])) {
        $key_base = 'brand:' . $atts['brand'];
    } elseif (!empty($atts['tag'])) {
        $key_base = 'tag:' . $atts['tag'];
    } elseif (!empty($atts['ids'])) {
        $key_base = 'ids';
    } else {
        $key_base = 'type:' . (isset($atts['type']) ? $atts['type'] : 'recent');
    }

    return array(
        'shortcode'        => $shortcode,
        'atts'             => $atts,
        'pin'              => ghb_hub_parse_id_list($atts['pin'] ?? ''),
        'exclude'          => ghb_hub_parse_id_list($atts['exclude'] ?? ''),
        'fallback'         => ghb_hub_normalize_fallback($atts['fallback'] ?? '', $atts),
        'fallback_default' => ghb_hub_default_fallback($atts),
        'key_base'         => $key_base,
    );
}

/**
 * Leaf blocks of a parsed page in document order, each with its path: the
 * chain of indexes into parse_blocks() output ("3.0" = fourth top-level block,
 * its first inner block). Containers (a group) are walked, not listed;
 * whitespace between blocks (blockName null) is skipped but still counted, so
 * a path always addresses parse_blocks() output directly.
 *
 * @return array<int, array{0: string, 1: array}>
 */
function ghb_hub_leaf_blocks(array $blocks, string $prefix = ''): array
{
    $out = array();
    foreach ($blocks as $index => $block) {
        if (empty($block['blockName'])) {
            continue;
        }
        $path = '' === $prefix ? (string) $index : $prefix . '.' . $index;
        if (!empty($block['innerBlocks'])) {
            foreach (ghb_hub_leaf_blocks($block['innerBlocks'], $path) as $leaf) {
                $out[] = $leaf;
            }
            continue;
        }
        $out[] = array($path, $block);
    }
    return $out;
}

/** The block at a path produced by ghb_hub_leaf_blocks(), or null. */
function ghb_hub_block_at_path(array $blocks, string $path): ?array
{
    if (!preg_match('/^\d+(\.\d+)*$/', $path)) {
        return null;
    }
    $node = null;
    $list = $blocks;
    foreach (explode('.', $path) as $index) {
        $index = (int) $index;
        if (!isset($list[$index]) || !is_array($list[$index])) {
            return null;
        }
        $node = $list[$index];
        $list = isset($node['innerBlocks']) && is_array($node['innerBlocks']) ? $node['innerBlocks'] : array();
    }
    return $node;
}

/**
 * Rails of a parsed page, keyed: "category:saldi-sneakers-outlet#0" is the
 * first rail showing that category. The key is how a rail is found again in
 * an older revision, where paths may differ.
 *
 * @return array<string, array{path: string, block: array, rail: array}>
 */
function ghb_hub_page_rails(array $blocks): array
{
    $rails = array();
    $seen  = array();
    foreach (ghb_hub_leaf_blocks($blocks) as $leaf) {
        list($path, $block) = $leaf;
        $rail = ghb_hub_rail_from_block($block);
        if (!$rail) {
            continue;
        }
        $n = isset($seen[$rail['key_base']]) ? $seen[$rail['key_base']] : 0;
        $seen[$rail['key_base']] = $n + 1;
        $rails[$rail['key_base'] . '#' . $n] = array('path' => $path, 'block' => $block, 'rail' => $rail);
    }
    return $rails;
}

/** Fingerprint of a block's attributes — "is it still the block you read?" */
function ghb_hub_attrs_hash(array $attrs): string
{
    return md5((string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Replace ONE block's attributes in raw post content, touching nothing else.
 *
 * The block's comment delimiter is rebuilt from its current attributes with
 * WordPress's own serializer — the same escaping the editor used to write it —
 * and must be found exactly once. Anything else (not found, found twice) is a
 * refusal, never a guess: a whole-page re-serialization would risk changing
 * blocks nobody meant to touch.
 *
 * @param callable $serialize serialize_block_attributes().
 * @return array{ok: bool, content?: string, error?: string, count?: int}
 */
function ghb_hub_replace_block_attrs(string $content, string $name, array $old_attrs, array $new_attrs, callable $serialize): array
{
    $serialized_name = 0 === strpos($name, 'core/') ? substr($name, 5) : $name;
    $old_json        = $serialize($old_attrs);
    $new_json        = $serialize($new_attrs);

    $forms = array(
        "<!-- wp:{$serialized_name} {$old_json} /-->" => "<!-- wp:{$serialized_name} {$new_json} /-->",
        "<!-- wp:{$serialized_name} {$old_json} -->"  => "<!-- wp:{$serialized_name} {$new_json} -->",
    );

    $count       = 0;
    $needle      = '';
    $replacement = '';
    foreach ($forms as $old => $new) {
        $n = substr_count($content, $old);
        if ($n > 0) {
            $count      += $n;
            $needle      = $old;
            $replacement = $new;
        }
    }
    if (1 !== $count) {
        return array('ok' => false, 'error' => 0 === $count ? 'not_found' : 'ambiguous', 'count' => $count);
    }

    $pos = strpos($content, $needle);
    return array('ok' => true, 'content' => substr_replace($content, $replacement, (int) $pos, strlen($needle)));
}

/**
 * A short description of a block that is not a rail, for the Vetrina's
 * placeholders: its title if it has one, and how many entries its first list
 * attribute holds (slides, cards, logos, questions).
 */
function ghb_hub_block_summary(array $block): array
{
    $attrs   = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
    $summary = array('title' => null, 'items' => null, 'labels' => array());

    foreach (array('title', 'eyebrow') as $key) {
        if (!empty($attrs[$key]) && is_string($attrs[$key])) {
            $summary['title'] = $attrs[$key];
            break;
        }
    }
    foreach ($attrs as $value) {
        if (!is_array($value) || array() === $value || !array_key_exists(0, $value)) {
            continue;
        }
        $summary['items'] = count($value);
        foreach (array_slice($value, 0, 12) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (array('title', 'name', 'question', 'product') as $label_key) {
                if (!empty($entry[$label_key]) && is_string($entry[$label_key])) {
                    $summary['labels'][] = $entry[$label_key];
                    break;
                }
            }
        }
        break;
    }
    if ('core/shortcode' === ($block['blockName'] ?? '')) {
        $summary['title'] = trim(mb_substr(trim((string) ($block['innerHTML'] ?? '')), 0, 80));
    }
    return $summary;
}
