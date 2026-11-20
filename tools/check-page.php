<?php
/**
 * Check a page's block markup before it goes to the site.
 *
 *   php tools/check-page.php page.html [more.html …]
 *   php tools/check-page.php - < page.html
 *
 * Prints every issue (tools/page-check-core.php says what is checked) and
 * exits 1 if any is an error, 0 otherwise. Warnings are worth a look but
 * don't fail.
 */

if (PHP_SAPI !== 'cli') {
    exit; // never runnable over HTTP
}

require __DIR__ . '/page-check-core.php';

$files = array_slice($argv, 1);
if (!$files) {
    fwrite(STDERR, "Usage: php tools/check-page.php page.html [more.html …]   (- reads standard input)\n");
    exit(2);
}

$specs  = ghb_pc_load_specs(dirname(__DIR__));
$errors = 0;
foreach ($files as $file) {
    $markup = '-' === $file ? stream_get_contents(STDIN) : @file_get_contents($file);
    if (false === $markup) {
        fwrite(STDERR, "$file: can't read it\n");
        $errors++;
        continue;
    }
    $issues = ghb_pc_check($markup, $specs);
    $count  = array_count_values(array_column($issues, 'level')) + array('error' => 0, 'warning' => 0);
    $label  = '-' === $file ? 'standard input' : $file;
    echo $label . ': ' . $count['error'] . ' error(s), ' . $count['warning'] . " warning(s)\n";
    foreach ($issues as $issue) {
        echo '  ' . $issue['level'] . '  ' . $issue['where'] . ': ' . $issue['message'] . "\n";
    }
    $errors += $count['error'];
}
exit($errors > 0 ? 1 : 0);
