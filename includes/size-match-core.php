<?php
/**
 * Golden Hive — size matching, pure core.
 *
 * Plain PHP, no WordPress call: how a size label becomes a comparable key,
 * which of a product's sizes answer the shop's size filter, what the card
 * says about them, and how sizes sort and group in the pickers ("Nella tua
 * taglia", the newsletter's size alerts). Unit-tested in
 * tests/size-match-test.php; the WordPress side is size-match.php and
 * size-rail.php, and the size pickers in add-to-cart.php use the same key.
 *
 * @package Golden_Hive_Blocks
 * @since   5.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize a size label into a key that ignores separator style.
 *
 * This store carries the same physical size under two term spellings — a
 * space/slash form ("36 2/3", "37 1/3") and a dash form ("36-2-3", "37-1-3") —
 * plus exact duplicates for whole sizes ("36" twice). Stripping every
 * non-alphanumeric character and lowercasing collapses all of these to one key
 * ("36 2/3", "36-2-3" → "3623"; "36", "36" → "36"), so equal sizes group
 * together regardless of how they were typed.
 */
function ghb_normalize_size_key($label)
{
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $label));
}

/**
 * The in-stock sizes of a product that the size filter asked for, in the
 * product's own size order.
 *
 * @param array    $rows Size rows as ghb_atc_size_rows() builds them (at least
 *                       'label' and 'in_stock').
 * @param string[] $keys Normalized keys of the filtered sizes.
 * @return array The matching rows, re-indexed.
 */
function ghb_size_match_pick(array $rows, array $keys): array
{
    if (!$keys) {
        return array();
    }
    $wanted = array_flip(array_map('ghb_normalize_size_key', $keys));
    $out    = array();
    foreach ($rows as $row) {
        if (!empty($row['in_stock']) && isset($wanted[ghb_normalize_size_key($row['label'] ?? '')])) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * The card line for the matched sizes: one size names its own price (in
 * resell each size has its own), several are listed. '' when nothing matched.
 */
function ghb_size_match_line(array $matches): string
{
    if (!$matches) {
        return '';
    }
    if (1 === count($matches)) {
        $row  = $matches[0];
        $line = 'Taglia ' . $row['label'];
        if (!empty($row['price_text'])) {
            $line .= ' · ' . $row['price_text'];
        }
        return $line;
    }
    return 'Taglie ' . implode(', ', array_column($matches, 'label')) . ' disponibili';
}

/**
 * Where a size label sorts: shoe sizes by number ("36 2/3" and "36-2-3" =
 * 36.67, "42.5" / "42,5" = 42.5, an "EU " prefix ignored), then letter
 * sizes XXS … XXXL, then anything else.
 */
function ghb_size_sort_value(string $label): float
{
    $l = strtolower(trim((string) preg_replace('/^(eu|us|uk)\s*/i', '', trim($label))));

    if (preg_match('/^(\d+(?:[.,]\d+)?)(?:[ -](\d)[\/-](\d))?$/', $l, $m)) {
        $n = (float) str_replace(',', '.', $m[1]);
        if (!empty($m[2]) && (int) $m[3] > 0) {
            $n += (int) $m[2] / (int) $m[3];
        }
        return $n;
    }

    $letters = array('xxs' => 1, 'xs' => 2, 's' => 3, 'm' => 4, 'l' => 5, 'xl' => 6, 'xxl' => 7, '2xl' => 7, 'xxxl' => 8, '3xl' => 8);
    return isset($letters[$l]) ? 1000 + $letters[$l] : 2000;
}

/**
 * Size options for the pickers: the store's size terms grouped by physical
 * size (ghb_normalize_size_key), in size order. Each option keeps every
 * spelling's slug, so a query for "36 2/3" also finds the "36-2-3" pairs,
 * and shows the spelling people read ("36 2/3" over "36-2-3").
 *
 * @param array $terms Rows with slug, name and count (products carrying it).
 * @return array<int,array{key:string,label:string,slugs:string[],count:int}>
 */
function ghb_size_group_options(array $terms): array
{
    $groups = array();
    foreach ($terms as $term) {
        $name = (string) ($term['name'] ?? '');
        $key  = ghb_normalize_size_key($name);
        if ('' === $key) {
            continue;
        }
        if (!isset($groups[$key])) {
            $groups[$key] = array('key' => $key, 'label' => $name, 'slugs' => array(), 'count' => 0);
        }
        $groups[$key]['slugs'][] = (string) ($term['slug'] ?? '');
        $groups[$key]['count']  += (int) ($term['count'] ?? 0);
        if (substr_count($name, '-') < substr_count($groups[$key]['label'], '-')) {
            $groups[$key]['label'] = $name;
        }
    }

    $options = array_values($groups);
    usort($options, static function ($a, $b) {
        $va = ghb_size_sort_value($a['label']);
        $vb = ghb_size_sort_value($b['label']);
        if ($va === $vb) {
            return strcmp($a['label'], $b['label']);
        }
        return $va < $vb ? -1 : 1;
    });
    return $options;
}
