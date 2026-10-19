<?php
/**
 * Tests for the social proof core (includes/social-proof-core.php).
 *
 * Run from the plugin root:   php tests/social-proof-test.php
 *
 * Plain PHP, no WordPress: how an order's city reads in the toast.
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/includes/social-proof-core.php';

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

same(ghb_sp_city('MILANO'), 'Milano', 'shouted city is title-cased');
same(ghb_sp_city('  piacenza '), 'Piacenza', 'spaces trimmed');
same(ghb_sp_city("sant'angelo lodigiano"), "Sant'Angelo Lodigiano", 'apostrophe starts a new capital');
same(ghb_sp_city('san giovanni in persiceto'), 'San Giovanni in Persiceto', 'particles stay lowercase inside');
same(ghb_sp_city("REGGIO NELL'EMILIA"), "Reggio nell'Emilia", "nell' stays lowercase, the name after it is capitalized");
same(ghb_sp_city('castel san giovanni'), 'Castel San Giovanni', 'every word of a name');
same(ghb_sp_city('29121'), '', 'a postcode is not a city');
same(ghb_sp_city(''), '', 'nothing stays nothing');
same(ghb_sp_city('città di castello'), 'Città di Castello', 'accented letters survive');

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
