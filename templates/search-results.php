<?php
/**
 * Live Ajax Search result-card template (bundled in Golden Hive Blocks).
 *
 * Loaded by absolute path via the `relevanssi_live_search_results_template`
 * filter (see includes/live-search.php). Written mode-agnostic, so it works
 * whether or not the site sets `relevanssi_live_search_mode = 'wp_query'`:
 * uses $relevanssi_query in WP_Query mode, otherwise the global query
 * (default query_posts mode).
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $relevanssi_query;
$query = (isset($relevanssi_query) && $relevanssi_query instanceof WP_Query)
    ? $relevanssi_query
    : $GLOBALS['wp_query'];

if ($query->have_posts()) : ?>
    <ul class="rlv-results-list">
        <?php
        while ($query->have_posts()) :
            $query->the_post();

            $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
            if (!$product) {
                continue;
            }

            // Optional brand eyebrow. Tries the common brand taxonomies; skips silently if none exist.
            $brand = '';
            foreach (array('yith_product_brand', 'product_brand', 'pwb-brand', 'pa_brand') as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    $terms = get_the_terms(get_the_ID(), $taxonomy);
                    if ($terms && !is_wp_error($terms)) {
                        $brand = $terms[0]->name;
                        break;
                    }
                }
            }
            ?>
            <li class="rlv-result">
                <a class="rlv-result-link" href="<?php the_permalink(); ?>">
                    <span class="rlv-result-thumb"><?php echo $product->get_image('woocommerce_thumbnail'); ?></span>
                    <span class="rlv-result-body">
                        <?php if ($brand) : ?>
                            <span class="rlv-result-brand"><?php echo esc_html($brand); ?></span>
                        <?php endif; ?>
                        <span class="rlv-result-title"><?php the_title(); ?></span>
                        <span class="rlv-result-price"><?php echo $product->get_price_html(); ?></span>
                    </span>
                </a>
            </li>
            <?php
        endwhile;
        wp_reset_postdata();
        ?>
    </ul>
<?php else : ?>
    <div class="rlv-no-results">
        Nessun risultato per &ldquo;<?php echo esc_html(get_search_query()); ?>&rdquo;
    </div>
    <?php
endif;
