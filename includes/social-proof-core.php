<?php
/**
 * Golden Hive — social proof, pure core.
 *
 * Plain PHP, no WordPress call: how an order's city is shown in the
 * "Qualcuno ha appena acquistato" toast. Unit-tested in
 * tests/social-proof-test.php; the WordPress side (orders, cache, endpoint)
 * is social-proof.php.
 *
 * @package Golden_Hive_Blocks
 * @since   5.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A city as a person writes it: "MILANO" → "Milano", "sant'angelo
 * lodigiano" → "Sant'Angelo Lodigiano", "san giovanni in persiceto" → "San
 * Giovanni in Persiceto". '' for anything that isn't a plain place name
 * (empty, digits: a postcode typed in the wrong field).
 */
function ghb_sp_city(string $raw): string
{
    $city = trim((string) preg_replace('/\s+/u', ' ', $raw));
    if ('' === $city || preg_match('/\d/', $city)) {
        return '';
    }

    $words = explode(' ', mb_strtolower(mb_substr($city, 0, 40), 'UTF-8'));
    // Particles stay lowercase inside a name ("Reggio nell'Emilia").
    $small = array('di', 'del', 'della', 'dei', 'delle', 'in', 'sul', 'sulla', 'al', 'alla', 'da', 'e');

    foreach ($words as $i => $word) {
        if ($i > 0 && in_array($word, $small, true)) {
            continue;
        }
        // Capitalize each part of "sant'angelo" / "monte-carlo", but leave a
        // leading particle lowercase inside a name: "nell'emilia" → "nell'Emilia".
        $word = preg_replace_callback(
            "/(^|['’\\-])(\\p{Ll})/u",
            static function ($m) {
                return $m[1] . mb_strtoupper($m[2], 'UTF-8');
            },
            $word
        );
        if ($i > 0 && preg_match("/^(Nell|Dell|Sull|All|D)(['’])/u", $word)) {
            $word = mb_strtolower(mb_substr($word, 0, 1), 'UTF-8') . mb_substr($word, 1);
        }
        $words[$i] = $word;
    }

    return implode(' ', $words);
}
