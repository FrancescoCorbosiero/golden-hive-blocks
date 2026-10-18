<?php
/**
 * Golden Hive — size matching, pure core.
 *
 * Plain PHP, no WordPress call: how a size label becomes a comparable key,
 * which of a product's sizes answer the shop's size filter, and what the card
 * says about them. Unit-tested in tests/size-match-test.php; the WordPress
 * side (reading the filter, rendering the card line) is size-match.php, and
 * the size pickers in add-to-cart.php use the same key.
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
