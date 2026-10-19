<?php
/**
 * Tests for the size alerts core (includes/size-alerts-core.php).
 *
 * Run from the plugin root:   php tests/size-alerts-test.php
 *
 * Plain PHP, no WordPress: which new arrivals a subscriber hears about.
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/includes/size-alerts-core.php';

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

$live = array(
    10 => array('keys' => array('42', '43'), 't' => 100),
    11 => array('keys' => array('42'), 't' => 300),
    12 => array('keys' => array('40'), 't' => 200),
    13 => array('keys' => array('42'), 't' => 50),
);
$brands_of = array(10 => array('nike', 'jordan'), 11 => array('adidas'), 12 => array('nike'), 13 => array('nike'));

same(ghb_alerts_pick($live, array(), array(), $brands_of, array()), array(), 'no sizes, no alert');
same(ghb_alerts_pick($live, array('42'), array(), $brands_of, array()), array(11 => array('42'), 10 => array('42'), 13 => array('42')), 'every arrival in the size, newest first');
same(array_keys(ghb_alerts_pick($live, array('42'), array('nike'), $brands_of, array())), array(10, 13), 'brand preference narrows it');
same(ghb_alerts_pick($live, array('42', '43'), array(), $brands_of, array('10|42')), array(11 => array('42'), 10 => array('43'), 13 => array('42')), 'a size already told about is not told again, another size of the same product is');
same(ghb_alerts_pick($live, array('44'), array(), $brands_of, array()), array(), 'nothing in the size, nothing to send');
same(array_keys(ghb_alerts_pick($live, array('42'), array('jordan'), $brands_of, array())), array(10), 'a parent brand matches its products');

same(ghb_alerts_merge_sizes(array('40', '41'), array('42')), array('42', '40', '41'), 'a new size goes first');
same(ghb_alerts_merge_sizes(array('40', '41', '42'), array('43')), array('43', '40', '41'), 'at most three, the oldest drops');
same(ghb_alerts_merge_sizes(array('42'), array('42')), array('42'), 'no duplicates');
same(ghb_alerts_merge_sizes(array(''), array('43')), array('43'), 'an unset meta (\'\') is not a size');

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
