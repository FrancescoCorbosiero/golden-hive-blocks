<?php
/**
 * Category Slider block — server render.
 *
 * Shopify-style horizontal category showcase. Uses native CSS scroll-snap
 * (no slider library) — the Swiper dependency was removed for performance.
 * Reuses the .gh-category-slider__item/__image-wrapper/__image/__name
 * styles; the scroll-snap layout (.gh-cs) is in style.css too, and each
 * picture keeps the block's ratio (imageRatio).
 *
 * Note: the legacy autoplay/loop/dots options no longer apply (they were
 * Swiper features); the rail scrolls/snaps natively with optional arrows.
 */

$title = $attributes['title'] ?? '';
$categories = $attributes['categories'] ?? [];
$show_nav = $attributes['showNav'] ?? true;
$image_ratio = $attributes['imageRatio'] ?? '4 / 5';
$padding_top = $attributes['paddingTop'] ?? 40;
$padding_bottom = $attributes['paddingBottom'] ?? 40;

if (empty($categories)) {
    return;
}

$block_id = 'gh-cat-slider-' . wp_unique_id();
$section_style = sprintf(
    'padding-top: %dpx; padding-bottom: %dpx;',
    absint($padding_top),
    absint($padding_bottom)
);

// The scroll-snap layout and the arrows' look are in style.css (.gh-cs); the
// behaviour is js/product-rail.js, which drives both .gh-rail and .gh-cs.
// That shared, readyState-safe script (instead of an inline DOMContentLoaded
// handler) keeps the arrows working when an optimizer defers inline scripts.
?>
<section <?php echo get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-category-slider',
    'id'    => $block_id,
    'style' => $section_style,
)); ?>>
    <div class="gh-category-slider__container">
        <?php if (!empty($title)) : ?>
            <header class="gh-category-slider__header">
                <h2 class="gh-category-slider__title" data-gh-split="lines"><?php echo esc_html($title); ?></h2>
            </header>
        <?php endif; ?>

        <div class="gh-cs">
            <?php if ($show_nav) : ?>
                <button type="button" class="gh-cs__nav gh-cs__nav--prev" data-dir="-1" aria-label="Precedente">&lsaquo;</button>
            <?php endif; ?>

            <ul class="gh-cs__track">
                <?php foreach ($categories as $index => $category) : ?>
                    <li class="gh-cs__slide">
                        <a href="<?php echo esc_url($category['url'] ?? '#'); ?>" class="gh-category-slider__item">
                            <div class="gh-category-slider__image-wrapper" style="aspect-ratio: <?php echo esc_attr($image_ratio); ?>">
                                <?php if (!empty($category['image'])) :
                                    echo gh_img(
                                        (int) ($category['imageId'] ?? 0),
                                        $category['image'],
                                        array(
                                            'class'    => 'gh-category-slider__image',
                                            'alt'      => $category['name'] ?? '',
                                            'loading'  => 'lazy',
                                            'decoding' => 'async',
                                        )
                                    );
                                endif; ?>
                            </div>
                            <?php if (!empty($category['name'])) : ?>
                                <p class="gh-category-slider__name"><?php echo esc_html($category['name']); ?></p>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($show_nav) : ?>
                <button type="button" class="gh-cs__nav gh-cs__nav--next" data-dir="1" aria-label="Successivo">&rsaquo;</button>
            <?php endif; ?>
        </div>
    </div>
</section>
