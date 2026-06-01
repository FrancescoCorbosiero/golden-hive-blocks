<?php
/**
 * Golden Hive — On-sale highlight on variation swatches.
 *
 * ⚠ THEME-SPECIFIC — Shoptimizer + CommerceKit ("CK").
 * ──────────────────────────────────────────────────────────────────
 * On a variable product page, finds which variation attribute values are on
 * sale and injects wp_head CSS that gives the matching CommerceKit swatches a
 * red border plus a small "%" badge. Relies on CommerceKit swatch markup
 * (`.cgkit-swatch` and `[data-attribute="..."] button[data-attribute-value="..."]`)
 * that is NOT WooCommerce core. If you switch away from Shoptimizer/CommerceKit
 * it simply stops applying — the CSS targets selectors that won't exist, so
 * there is no error and nothing else is affected.
 *
 * Migrated from a Code Snippet. Disable that snippet once this is active, or the
 * <style> block will be emitted twice.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp', 'ghb_swatch_sale_badges_init');
function ghb_swatch_sale_badges_init()
{
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    // Use a local product (don't clobber the global $product this early).
    $product = wc_get_product(get_the_ID());
    if (!$product || !$product->is_type('variable')) {
        return;
    }

    $on_sale = [];
    foreach ($product->get_children() as $var_id) {
        $variation = wc_get_product($var_id);
        if (!$variation || !$variation->is_on_sale()) {
            continue;
        }
        foreach ($variation->get_attributes() as $attr => $value) {
            if (!$value) {
                continue;
            }
            $on_sale['attribute_' . $attr][] = $value;
        }
    }

    if (empty($on_sale)) {
        return;
    }

    $selectors = [];
    foreach ($on_sale as $attr => $values) {
        foreach (array_unique($values) as $value) {
            $selectors[] = sprintf(
                '[data-attribute="%s"] button[data-attribute-value="%s"]',
                esc_attr($attr),
                esc_attr($value)
            );
        }
    }

    $selector_str = implode(', ', $selectors);

    add_action('wp_head', function () use ($selector_str) {
        $after_selectors = implode(', ', array_map(
            fn($s) => $s . '::after',
            explode(', ', $selector_str)
        ));

        echo '<style>
            .cgkit-swatch {
                position: relative;
                z-index: 0;
            }
            ' . $selector_str . ' {
                border-color: #e44 !important;
                z-index: 1;
            }
            ' . $after_selectors . ' {
                content: "%";
                position: absolute;
                top: 2px; right: 2px;
                background: #e44; color: #fff;
                font-size: 8px; font-weight: 700; line-height: 1;
                padding: 1px 2px; border-radius: 2px;
                pointer-events: none; z-index: 2;
            }
        </style>';
    });
}
