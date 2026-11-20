<?php
/**
 * Markup the plugin prints survives WordPress's text filters.
 *
 * Run from the plugin root:   php tests/markup-test.php
 *
 * wptexturize, which runs on post content and shortcode output, reads every
 * ">" as the end of an HTML tag, even inside a quoted attribute value. An
 * attribute like data-gh-stagger=".gh-rail__track > li" then gets its closing
 * quote turned into a curly one and swallows the markup after it: in 5.17.0
 * that moved the rail arrows out of the rail and left them dead on every
 * store. This scans the plugin's templates for HTML attribute values holding
 * a ">" (PHP's => and -> are not HTML and are skipped).
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/includes/*.php'),
    glob($root . '/blocks/*/render.php'),
    glob($root . '/templates/*.php')
);

$failures = 0;
$checked  = 0;
foreach ($files as $file) {
    foreach (file($file) as $n => $line) {
        // Attribute values in literal HTML: name="…" with a ">" inside the quotes,
        // where that ">" isn't part of PHP's => or -> or a PHP tag.
        if (!preg_match_all('/\s[a-z][a-z0-9_:-]*="([^"<]*)"/i', $line, $m)) {
            continue;
        }
        foreach ($m[1] as $value) {
            $checked++;
            $html = preg_replace('/=>|->|\?>/', '', $value);
            if (false !== strpos($html, '>')) {
                $failures++;
                echo 'FAIL: ' . substr($file, strlen($root) + 1) . ':' . ($n + 1) . ' has an attribute holding ">": ' . trim($value) . "\n";
            }
        }
    }
}

// The scan itself catches the 5.17.0 attribute.
$probe = ' data-gh-stagger=".gh-rail__track > li"';
preg_match_all('/\s[a-z][a-z0-9_:-]*="([^"<]*)"/i', $probe, $m);
$caught = isset($m[1][0]) && false !== strpos(preg_replace('/=>|->|\?>/', '', $m[1][0]), '>');
if (!$caught) {
    $failures++;
    echo "FAIL: the scan doesn't catch the attribute that broke 5.17.0\n";
}

echo sprintf("%d passed, %d failed\n", $failures ? $checked - $failures : $checked + 1, $failures);
exit($failures > 0 ? 1 : 0);
