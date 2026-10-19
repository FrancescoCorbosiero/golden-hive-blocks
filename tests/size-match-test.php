<?php
/**
 * Tests for the size-matching core (includes/size-match-core.php).
 *
 * Run from the plugin root:   php tests/size-match-test.php
 *
 * Plain PHP, no WordPress: the core decides which of a product's sizes answer
 * the shop's size filter and what the card says about them.
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/includes/size-match-core.php';

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

/* ---------------------------------------------------------------- */

same(ghb_normalize_size_key('36 2/3'), '3623', 'slash spelling collapses');
same(ghb_normalize_size_key('36-2-3'), '3623', 'dash spelling collapses to the same key');
same(ghb_normalize_size_key('XL'), 'xl', 'letters are lowercased');

$rows = array(
    array('variation_id' => 11, 'label' => '40', 'value' => '40', 'in_stock' => true, 'price_text' => '150 €'),
    array('variation_id' => 12, 'label' => '42', 'value' => '42', 'in_stock' => true, 'price_text' => '180 €'),
    array('variation_id' => 13, 'label' => '43', 'value' => '43', 'in_stock' => false, 'price_text' => '190 €'),
    array('variation_id' => 14, 'label' => '36 2/3', 'value' => '36-2-3', 'in_stock' => true, 'price_text' => '210 €'),
);

same(ghb_size_match_pick($rows, array()), array(), 'no filter, no match');
same(array_column(ghb_size_match_pick($rows, array('42')), 'variation_id'), array(12), 'the filtered size');
same(ghb_size_match_pick($rows, array('43')), array(), 'a sold-out size never matches');
same(array_column(ghb_size_match_pick($rows, array('3623')), 'variation_id'), array(14), 'a key matches the other spelling');
same(array_column(ghb_size_match_pick($rows, array('42', '40', '99')), 'variation_id'), array(11, 12), 'several sizes keep the product order');

same(ghb_size_match_line(array()), '', 'nothing matched, no line');
same(ghb_size_match_line(array($rows[1])), 'Taglia 42 · 180 €', 'one size names its price');
same(ghb_size_match_line(array(array('label' => '42', 'price_text' => ''))), 'Taglia 42', 'an unpriced size names just the size');
same(ghb_size_match_line(array($rows[0], $rows[1])), 'Taglie 40, 42 disponibili', 'several sizes are listed');

// Size order and grouping (pickers, newsletter alerts).
check(ghb_size_sort_value('36 2/3') > ghb_size_sort_value('36'), '36 2/3 after 36');
check(ghb_size_sort_value('36-2-3') === ghb_size_sort_value('36 2/3'), 'both spellings sort together');
same(ghb_size_sort_value('42,5'), 42.5, 'a comma decimal');
same(ghb_size_sort_value('EU 44'), 44.0, 'an EU prefix is ignored');
check(ghb_size_sort_value('XL') > ghb_size_sort_value('48'), 'letter sizes after shoe sizes');
check(ghb_size_sort_value('M') < ghb_size_sort_value('XL'), 'letter sizes in their own order');

$options = ghb_size_group_options(array(
    array('slug' => '42', 'name' => '42', 'count' => 5),
    array('slug' => '36-2-3', 'name' => '36-2-3', 'count' => 1),
    array('slug' => '36-2-3-2', 'name' => '36 2/3', 'count' => 2),
    array('slug' => 'xl', 'name' => 'XL', 'count' => 3),
    array('slug' => '40-5', 'name' => '40.5', 'count' => 4),
    array('slug' => 'junk', 'name' => '', 'count' => 9),
));
same(array_column($options, 'label'), array('36 2/3', '40.5', '42', 'XL'), 'grouped, in size order, readable spelling');
same($options[0]['slugs'], array('36-2-3', '36-2-3-2'), 'a group keeps every spelling');
same($options[0]['count'], 3, 'and adds their products up');

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
