<?php
/**
 * Parallax archive hero for WooCommerce shop / category / tag pages.
 *
 * Renders a cinematic hero band above the product loop: the term's image
 * (Prodotti → Categorie → Miniatura — already supported by WooCommerce core)
 * with a scroll parallax, gradient scrim, term name, description and product
 * count. Categories without an image get a branded gradient band instead.
 *
 * The parallax is pure CSS (scroll-driven animations inside @supports):
 * browsers without support — and prefers-reduced-motion users — get a static
 * image. No JavaScript is loaded for this feature.
 *
 * Customization:
 * - add_filter('ghb_archive_hero_enabled', '__return_false') kills the whole
 *   feature (default title/description come back automatically).
 * - 'ghb_archive_hero_image_id' filters the attachment used (e.g. to give
 *   product tags or the shop page a curated image).
 * - 'ghb_archive_hero_eyebrow' filters the small label above the title.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the hero should render on the current request.
 */
function ghb_archive_hero_enabled()
{
    $enabled = function_exists('is_woocommerce')
        && !is_search()
        && (is_shop() || is_product_taxonomy());

    return (bool) apply_filters('ghb_archive_hero_enabled', $enabled);
}

/**
 * Conditional stylesheet.
 */
function ghb_archive_hero_assets()
{
    if (!ghb_archive_hero_enabled()) {
        return;
    }

    wp_enqueue_style(
        'ghb-archive-hero',
        gh_asset_url('archive-hero.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'ghb_archive_hero_assets');

/**
 * Suppress the default archive title/description — the hero owns them.
 */
function ghb_archive_hero_hide_default_title($show)
{
    return ghb_archive_hero_enabled() ? false : $show;
}
add_filter('woocommerce_show_page_title', 'ghb_archive_hero_hide_default_title', 20);

function ghb_archive_hero_remove_default_description()
{
    if (!ghb_archive_hero_enabled()) {
        return;
    }

    remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
    remove_action('woocommerce_archive_description', 'woocommerce_product_archive_description', 10);
}
add_action('wp', 'ghb_archive_hero_remove_default_description');

/**
 * Render the hero above the product loop (after the breadcrumb, prio 20).
 */
function ghb_archive_hero_render()
{
    if (!ghb_archive_hero_enabled()) {
        return;
    }

    $title       = '';
    $description = '';
    $image_id    = 0;
    $count       = 0;

    if (is_product_taxonomy()) {
        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return;
        }
        $title       = $term->name;
        $description = term_description();
        $image_id    = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
        $count       = (int) $term->count;
    } elseif (is_shop()) {
        $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        if ($shop_page_id > 0) {
            $title       = get_the_title($shop_page_id);
            $description = apply_filters('the_content', get_post_field('post_excerpt', $shop_page_id));
            $image_id    = (int) get_post_thumbnail_id($shop_page_id);
        }
        if ($title === '') {
            $title = __('Shop', 'golden-hive-blocks');
        }
    }

    $image_id = (int) apply_filters('ghb_archive_hero_image_id', $image_id);
    $eyebrow  = (string) apply_filters(
        'ghb_archive_hero_eyebrow',
        is_shop() ? __('Il nostro catalogo', 'golden-hive-blocks') : __('Collezione', 'golden-hive-blocks')
    );

    $classes = 'gh-archive-hero' . ($image_id ? ' gh-archive-hero--has-image' : '');
    ?>
    <section class="<?php echo esc_attr($classes); ?>">
        <?php if ($image_id) : ?>
            <div class="gh-archive-hero__media" aria-hidden="true">
                <?php
                // The hero is the archive's LCP element: eager + high priority.
                echo gh_img($image_id, '', array(
                    'size'          => 'full',
                    'loading'       => 'eager',
                    'fetchpriority' => 'high',
                    'sizes'         => '100vw',
                    'alt'           => '',
                ));
                ?>
            </div>
        <?php endif; ?>

        <div class="gh-archive-hero__scrim" aria-hidden="true"></div>

        <div class="gh-archive-hero__content">
            <?php if ($eyebrow !== '') : ?>
                <span class="gh-archive-hero__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>

            <h1 class="gh-archive-hero__title"><?php echo esc_html($title); ?></h1>

            <?php if (!empty($description)) : ?>
                <div class="gh-archive-hero__description"><?php echo wp_kses_post($description); ?></div>
            <?php endif; ?>

            <?php if ($count > 0) : ?>
                <span class="gh-archive-hero__count">
                    <?php
                    printf(
                        /* translators: %s: number of products in the term. */
                        esc_html(_n('%s prodotto', '%s prodotti', $count, 'golden-hive-blocks')),
                        esc_html(number_format_i18n($count))
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
add_action('woocommerce_before_main_content', 'ghb_archive_hero_render', 25);
