<?php
/**
 * Golden Hive — Lightweight product rail (CSS scroll-snap carousel).
 *
 * A featherweight alternative to the Swiper-based [carousel_section]: no slider
 * library and no CDN — just native horizontal scroll + CSS scroll-snap
 * (Shopify-style), with optional ‹ › arrows (a few lines of vanilla JS).
 *
 * Cards are rendered through WooCommerce's standard product-loop template
 * (wc_get_template_part('content','product')), so they:
 *   • match the theme's product-card styling exactly, and
 *   • fire the standard loop hooks — which means Quick View and the inline size
 *     picker (registered on those hooks) appear on rail cards automatically.
 *
 * Reuses the existing carousel query builder (ghb_get_carousel_products) so the
 * type / category / brand / tag / ids attributes behave identically.
 *
 * Usage: [gh_product_rail title="Novità" type="recent" limit="12"]
 *        [gh_product_rail brand="supreme" limit="10"]
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'ghb_product_rail_register');
function ghb_product_rail_register()
{
    wp_register_style(
        'golden-hive-product-rail',
        GOLDEN_HIVE_BLOCKS_URL . 'product-rail.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
    wp_register_script(
        'golden-hive-product-rail',
        GOLDEN_HIVE_BLOCKS_URL . 'js/product-rail.js',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}

add_shortcode('gh_product_rail', 'ghb_product_rail_shortcode');
function ghb_product_rail_shortcode($atts)
{
    if (!class_exists('WooCommerce') || !function_exists('ghb_get_carousel_products')) {
        return '';
    }

    $atts = shortcode_atts(
        array(
            'title'    => '',
            'type'     => 'recent',   // recent | featured | best_selling | sale | top_rated
            'category' => '',
            'brand'    => '',
            'tag'      => '',
            'ids'      => '',
            'limit'    => 12,
        ),
        $atts,
        'gh_product_rail'
    );

    $query = ghb_get_carousel_products($atts);
    if (!$query->have_posts()) {
        wp_reset_postdata();
        return '';
    }

    // Rail assets; Quick View + add-to-cart assets load site-wide on their own.
    wp_enqueue_style('golden-hive-product-rail');
    wp_enqueue_script('golden-hive-product-rail');

    ob_start();
    ?>
    <section class="gh-rail">
        <div class="gh-rail__head">
            <?php if ('' !== $atts['title']) : ?>
                <h2 class="gh-rail__title"><?php echo esc_html($atts['title']); ?></h2>
            <?php else : ?>
                <span></span>
            <?php endif; ?>
            <div class="gh-rail__arrows">
                <button type="button" class="gh-rail__nav" data-dir="-1" aria-label="<?php esc_attr_e('Precedente', 'golden-hive-blocks'); ?>">&lsaquo;</button>
                <button type="button" class="gh-rail__nav" data-dir="1" aria-label="<?php esc_attr_e('Successivo', 'golden-hive-blocks'); ?>">&rsaquo;</button>
            </div>
        </div>
        <ul class="products gh-rail__track">
            <?php
            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }
            wp_reset_postdata();
            ?>
        </ul>
    </section>
    <?php
    return ob_get_clean();
}
