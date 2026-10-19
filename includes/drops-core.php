<?php
/**
 * Golden Hive — drops, pure core.
 *
 * Plain PHP, no WordPress call: the "Ricordamelo" reminders for a drop — an
 * .ics calendar file (Apple Calendar, Outlook, any phone) and a Google
 * Calendar link. Unit-tested in tests/drops-test.php; the WordPress side is
 * drops.php.
 *
 * An event is: uid, ts (UTC timestamp of the release), title, url, and
 * optionally minutes (length, default 30) and alarm (minutes before, default
 * 15).
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Text for an iCalendar property: backslash, comma, semicolon, newline escaped. */
function ghb_drop_ics_text(string $text): string
{
    $text = str_replace(array('\\', ';', ',', "\r\n", "\r", "\n"), array('\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'), $text);
    return $text;
}

/** Long lines folded at 75 octets, as RFC 5545 asks. */
function ghb_drop_ics_fold(string $line): string
{
    if (strlen($line) <= 75) {
        return $line;
    }
    $out   = '';
    $chunk = '';
    // Split on characters, never inside a multi-byte one.
    foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        if (strlen($chunk . $char) > ('' === $out ? 75 : 74)) {
            $out  .= $chunk . "\r\n ";
            $chunk = '';
        }
        $chunk .= $char;
    }
    return $out . $chunk;
}

function ghb_drop_ics(array $event, int $stamp): string
{
    $ts      = (int) $event['ts'];
    $minutes = max(5, (int) ($event['minutes'] ?? 30));
    $alarm   = max(0, (int) ($event['alarm'] ?? 15));
    $url     = (string) ($event['url'] ?? '');

    $lines = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Golden Hive Blocks//Drop//IT',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . ghb_drop_ics_text((string) $event['uid']),
        'DTSTAMP:' . gmdate('Ymd\THis\Z', $stamp),
        'DTSTART:' . gmdate('Ymd\THis\Z', $ts),
        'DTEND:' . gmdate('Ymd\THis\Z', $ts + $minutes * 60),
        'SUMMARY:' . ghb_drop_ics_text((string) $event['title']),
    );
    if ('' !== $url) {
        $lines[] = 'DESCRIPTION:' . ghb_drop_ics_text($url);
        $lines[] = 'URL:' . $url;
    }
    if ($alarm > 0) {
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:' . ghb_drop_ics_text((string) $event['title']);
        $lines[] = 'TRIGGER:-PT' . $alarm . 'M';
        $lines[] = 'END:VALARM';
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('ghb_drop_ics_fold', $lines)) . "\r\n";
}

function ghb_drop_google_url(array $event): string
{
    $ts      = (int) $event['ts'];
    $minutes = max(5, (int) ($event['minutes'] ?? 30));

    return 'https://calendar.google.com/calendar/render?' . http_build_query(array(
        'action'  => 'TEMPLATE',
        'text'    => (string) $event['title'],
        'dates'   => gmdate('Ymd\THis\Z', $ts) . '/' . gmdate('Ymd\THis\Z', $ts + $minutes * 60),
        'details' => (string) ($event['url'] ?? ''),
    ), '', '&', PHP_QUERY_RFC3986);
}
