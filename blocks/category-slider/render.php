<?php
/**
 * Category Slider Block - Render lato server
 * Shopify-style horizontal scrollable category showcase
 * Uses Swiper.js for carousel functionality
 */

$title = $attributes['title'] ?? '';
$categories = $attributes['categories'] ?? [];
$show_nav = $attributes['showNav'] ?? true;
$show_dots = $attributes['showDots'] ?? false;
$autoplay = $attributes['autoplay'] ?? false;
$loop = $attributes['loop'] ?? true;
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
?>
<section class="gh-block gh-category-slider" id="<?php echo esc_attr($block_id); ?>" style="<?php echo esc_attr($section_style); ?>">
    <div class="gh-category-slider__container">
        <?php if (!empty($title)) : ?>
            <header class="gh-category-slider__header">
                <h2 class="gh-category-slider__title"><?php echo esc_html($title); ?></h2>
            </header>
        <?php endif; ?>

        <div class="gh-category-slider__carousel">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($categories as $index => $category) : ?>
                        <div class="swiper-slide">
                            <a href="<?php echo esc_url($category['url'] ?? '#'); ?>"
                               class="gh-category-slider__item">
                                <div class="gh-category-slider__image-wrapper" style="aspect-ratio: <?php echo esc_attr($image_ratio); ?>">
                                    <?php if (!empty($category['image'])) : ?>
                                        <img src="<?php echo esc_url($category['image']); ?>"
                                             alt="<?php echo esc_attr($category['name'] ?? ''); ?>"
                                             class="gh-category-slider__image"
                                             loading="<?php echo $index < 4 ? 'eager' : 'lazy'; ?>">
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($category['name'])) : ?>
                                    <p class="gh-category-slider__name"><?php echo esc_html($category['name']); ?></p>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($show_nav) : ?>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                <?php endif; ?>

                <?php if ($show_dots) : ?>
                    <div class="swiper-pagination"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swiper !== 'undefined') {
        new Swiper('#<?php echo esc_js($block_id); ?> .swiper', {
            slidesPerView: 2,
            spaceBetween: 16,
            loop: <?php echo $loop ? 'true' : 'false'; ?>,
            autoplay: <?php echo $autoplay ? '{delay:4000,disableOnInteraction:false}' : 'false'; ?>,
            <?php if ($show_nav) : ?>
            navigation: {
                nextEl: '#<?php echo esc_js($block_id); ?> .swiper-button-next',
                prevEl: '#<?php echo esc_js($block_id); ?> .swiper-button-prev',
            },
            <?php endif; ?>
            <?php if ($show_dots) : ?>
            pagination: {
                el: '#<?php echo esc_js($block_id); ?> .swiper-pagination',
                clickable: true,
            },
            <?php endif; ?>
            breakpoints: {
                0: {
                    slidesPerView: 1.4,
                    spaceBetween: 12,
                },
                480: {
                    slidesPerView: 1.8,
                    spaceBetween: 14,
                },
                640: {
                    slidesPerView: 2.2,
                    spaceBetween: 16,
                },
                768: {
                    slidesPerView: 2.8,
                    spaceBetween: 20,
                },
                1024: {
                    slidesPerView: 3.5,
                    spaceBetween: 24,
                },
                1280: {
                    slidesPerView: 4,
                    spaceBetween: 28,
                },
            },
        });
    }
});
</script>
