<?php
/**
 * Golden Hive — size alerts, pure core.
 *
 * Plain PHP, no WordPress call: which new arrivals a subscriber hears about.
 * Unit-tested in tests/size-alerts-test.php; the WordPress side (signup,
 * confirmation, the arrivals queue, the emails) is size-alerts.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The arrivals that match a subscriber, newest first.
 *
 * @param array    $live      Arrivals still true now: product id => ['keys' => size keys, 't' => when].
 * @param string[] $sizes     The subscriber's size keys.
 * @param string[] $brands    The subscriber's brand slugs; empty = any brand.
 * @param array    $brands_of Product id => its brand slugs (parents included).
 * @param string[] $already   "productId|sizeKey" pairs this subscriber was already told about.
 * @return array<int,string[]> Product id => the subscriber's sizes it arrived in.
 */
function ghb_alerts_pick(array $live, array $sizes, array $brands, array $brands_of, array $already): array
{
    if (!$sizes) {
        return array();
    }

    uasort($live, static function ($a, $b) {
        return ((int) ($b['t'] ?? 0)) <=> ((int) ($a['t'] ?? 0));
    });

    $told = array_flip($already);
    $out  = array();
    foreach ($live as $pid => $arrival) {
        $keys = array();
        foreach (array_intersect((array) ($arrival['keys'] ?? array()), $sizes) as $key) {
            if (!isset($told[$pid . '|' . $key])) {
                $keys[] = $key;
            }
        }
        if (!$keys) {
            continue;
        }
        if ($brands && !array_intersect($brands, (array) ($brands_of[$pid] ?? array()))) {
            continue;
        }
        $out[$pid] = array_values(array_unique($keys));
    }
    return $out;
}

/**
 * Sizes after a new choice: the new ones first, then the ones already
 * confirmed, at most $max.
 *
 * @return string[]
 */
function ghb_alerts_merge_sizes(array $current, array $new, int $max = 3): array
{
    // Unset meta reads back as '': never a size.
    $sizes = array_filter(array_map('strval', array_merge($new, $current)), 'strlen');
    return array_slice(array_values(array_unique($sizes)), 0, $max);
}
