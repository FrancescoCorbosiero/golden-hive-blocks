<?php
/**
 * Golden Hive — Lightweight product rail (CSS scroll-snap carousel).
 *
 * A featherweight alternative to the Swiper-based [carousel_section]: no slider
 * library and no CDN — just native horizontal scroll + CSS scroll-snap
 * (Shopify-style), with optional ‹ › arrows (a few lines of vanilla JS).
 *
 * Cards are rendered through WooCommerce's standard product-loop template
 * (wc_get_template_part('content','product')) inside a `.woocommerce` wrapper,
 * so they:
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
 * Layout controls (all optional):
 *   columns        cards per view on desktop (1–8, default 4)
 *   columns_tablet cards per view 768–1024px (default: inherits `columns`)
 *   columns_mobile cards per view ≤767px (default: a 44vw peek of the next card)
 *   ratio          image aspect-ratio — alias (square|portrait|tall|landscape|
 *                  wide) or a raw "N/N" value like 3/4 (default square)
 *   fit            cover (crop to fill, default) | contain (show whole image)
 *
 *   e.g. [gh_product_rail brand="nike" columns="5" columns_mobile="2"
 *                         ratio="3/4" fit="contain"]
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load the rail assets front-end-wide. They're tiny, and enqueuing from inside
 * the shortcode is unreliable (late enqueue isn't always printed, e.g. via the
 * Gutenberg Shortcode block), which is what left the rail unstyled.
 */
add_action('wp_enqueue_scripts', 'ghb_product_rail_assets');
function ghb_product_rail_assets()
{
    wp_enqueue_style(
        'golden-hive-product-rail',
        GOLDEN_HIVE_BLOCKS_URL . 'product-rail.css',
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
    wp_enqueue_script(
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
            'title'          => '',
            'type'           => 'recent',   // recent | featured | best_selling | sale | top_rated
            'category'       => '',
            'brand'          => '',
            'tag'            => '',
            'ids'            => '',
            'limit'          => 12,
            'columns'        => 4,          // cards visible per view on desktop (1–8)
            'columns_tablet' => '',         // optional; blank ⇒ inherit desktop columns
            'columns_mobile' => '',         // optional; blank ⇒ peek the next card (44vw)
            'ratio'          => '1/1',      // card image ratio: alias or "N/N" (e.g. 3/4)
            'fit'            => 'cover',     // cover (crop) | contain (letterbox, no crop)
        ),
        $atts,
        'gh_product_rail'
    );

    // Cards shown per view on desktop (drives the batch step too). 1–8.
    $cols = max(1, min(8, (int) $atts['columns']));

    // Optional per-breakpoint overrides. 0 ⇒ "not set": tablet inherits desktop
    // columns, mobile keeps the default 44vw peek (see product-rail.css).
    $cols_tablet = ('' !== $atts['columns_tablet']) ? max(1, min(8, (int) $atts['columns_tablet'])) : 0;
    $cols_mobile = ('' !== $atts['columns_mobile']) ? max(1, min(8, (int) $atts['columns_mobile'])) : 0;

    // Card image aspect-ratio: accept a friendly alias or a raw "N/N" value.
    // Anything unrecognised falls back to a square, matching prior behaviour.
    $ratio_aliases = array(
        'square'    => '1 / 1',
        'portrait'  => '3 / 4',
        'tall'      => '4 / 5',
        'landscape' => '4 / 3',
        'wide'      => '16 / 9',
    );
    $ratio_raw = strtolower(trim((string) $atts['ratio']));
    if (isset($ratio_aliases[$ratio_raw])) {
        $ratio = $ratio_aliases[$ratio_raw];
    } elseif (preg_match('#^(\d{1,2})\s*/\s*(\d{1,2})$#', $ratio_raw, $m) && (int) $m[2] > 0) {
        $ratio = $m[1] . ' / ' . $m[2];
    } else {
        $ratio = '1 / 1';
    }

    // How the image fills its box. `cover` crops to fill (uniform cards);
    // `contain` shows the whole image, letterboxed against a neutral backdrop.
    $fit = in_array($atts['fit'], array('cover', 'contain'), true) ? $atts['fit'] : 'cover';

    // Wrapper classes + the CSS custom properties that drive the layout. Only
    // the breakpoints that were explicitly set emit a variable, so the CSS
    // fallbacks (desktop columns / 44vw peek) stay in charge otherwise.
    $rail_classes = 'woocommerce gh-rail gh-rail--fit-' . $fit;
    if ($cols_mobile) {
        $rail_classes .= ' gh-rail--mcols';
    }

    $track_vars = sprintf('--gh-cols: %d; --gh-ratio: %s; --gh-fit: %s;', $cols, $ratio, $fit);
    if ($cols_tablet) {
        $track_vars .= sprintf(' --gh-cols-tablet: %d;', $cols_tablet);
    }
    if ($cols_mobile) {
        $track_vars .= sprintf(' --gh-cols-mobile: %d;', $cols_mobile);
    }

    $query = ghb_get_carousel_products($atts);
    if (!$query->have_posts()) {
        wp_reset_postdata();
        return '';
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($rail_classes); ?>">
        <?php if ('' !== $atts['title']) : ?>
            <h2 class="gh-rail__title"><?php echo esc_html($atts['title']); ?></h2>
        <?php endif; ?>
        <div class="gh-rail__viewport">
            <button type="button" class="gh-rail__nav gh-rail__nav--prev" data-dir="-1" aria-label="<?php esc_attr_e('Precedente', 'golden-hive-blocks'); ?>">&lsaquo;</button>
            <ul class="products gh-rail__track" style="<?php echo esc_attr($track_vars); ?>">
                <?php
                while ($query->have_posts()) {
                    $query->the_post();
                    wc_get_template_part('content', 'product');
                }
                wp_reset_postdata();
                ?>
            </ul>
            <button type="button" class="gh-rail__nav gh-rail__nav--next" data-dir="1" aria-label="<?php esc_attr_e('Successivo', 'golden-hive-blocks'); ?>">&rsaquo;</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
