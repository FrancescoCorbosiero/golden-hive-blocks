<?php
/**
 * Tests for the drops core (includes/drops-core.php).
 *
 * Run from the plugin root:   php tests/drops-test.php
 *
 * Plain PHP, no WordPress: the drop reminders (.ics file, Google Calendar link).
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/includes/drops-core.php';

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

$event = array(
    'uid'   => 'drop-42@resellpiacenza.shop',
    'ts'    => 1792828800, // 2026-10-24 08:00:00 UTC (10:00 in Italy)
    'title' => 'Drop: Jordan 4 "Bred", Reimagined; 2026',
    'url'   => 'https://resellpiacenza.shop/prodotto/jordan-4/',
);
$ics = ghb_drop_ics($event, 1792000000);

check(0 === strpos($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"), 'a calendar, CRLF line endings');
check(false !== strpos($ics, "DTSTART:20261024T080000Z\r\n"), 'starts at the release, in UTC', $ics);
check(false !== strpos($ics, "DTEND:20261024T083000Z\r\n"), 'lasts 30 minutes');
check(false !== strpos($ics, 'SUMMARY:Drop: Jordan 4 "Bred"\, Reimagined\; 2026'), 'commas and semicolons escaped', $ics);
check(false !== strpos($ics, "TRIGGER:-PT15M\r\n"), 'a reminder 15 minutes before');
check(substr($ics, -15) === "END:VCALENDAR\r\n", 'ends the calendar');
same(ghb_drop_ics_text("a\nb"), 'a\nb', 'a newline becomes \n');

$long = ghb_drop_ics_fold('SUMMARY:' . str_repeat('è', 60));
$ok   = true;
foreach (explode("\r\n", $long) as $part) {
    $ok = $ok && strlen($part) <= 75;
}
check($ok && false !== strpos($long, "\r\n "), 'long lines folded under 75 octets, never inside a character', $long);

$google = ghb_drop_google_url($event);
check(0 === strpos($google, 'https://calendar.google.com/calendar/render?action=TEMPLATE'), 'a Google Calendar template link');
check(false !== strpos($google, 'dates=20261024T080000Z%2F20261024T083000Z'), 'with the same times', $google);
check(false !== strpos($google, 'text=Drop%3A%20Jordan%204'), 'and the title', $google);

/* ---------------------------------------------------------------- */

echo sprintf("%d passed, %d failed\n", $GLOBALS['ghb_passes'], $GLOBALS['ghb_failures']);
exit($GLOBALS['ghb_failures'] > 0 ? 1 : 0);
