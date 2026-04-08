<?php
/**
 * Hero Carousel Block - Render lato server
 */

$slides = $attributes['slides'] ?? [];
$autoplay = $attributes['autoplay'] ?? 6000;
$show_dots = $attributes['showDots'] ?? true;
$show_arrows = $attributes['showArrows'] ?? true;

if (empty($slides)) {
    return;
}

$block_id = 'gh-hero-' . wp_unique_id();
?>
<section class="gh-block gh-hero-carousel"
    data-gh-hero-carousel
    data-gh-hero-autoplay="<?php echo esc_attr($autoplay); ?>"
    id="<?php echo esc_attr($block_id); ?>">

    <?php foreach ($slides as $index => $slide) : ?>
        <div class="gh-hero-slide<?php echo $index === 0 ? ' gh-hero-slide--active' : ''; ?>" data-gh-hero-slide>
            <div class="gh-hero-slide__bg">
                <?php if (!empty($slide['image'])) :
                    $img_pos = !empty($slide['objectPosition']) ? $slide['objectPosition'] : 'center center';
                    // Normalize shorthand values (e.g. "left" → "left center")
                    if (strpos($img_pos, ' ') === false) {
                        $img_pos .= ' center';
                    }
                ?>
                    <img src="<?php echo esc_url($slide['image']); ?>"
                         alt=""
                         style="object-position: <?php echo esc_attr($img_pos); ?>;"
                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                <?php endif; ?>
            </div>
            <div class="gh-hero-slide__overlay"></div>

            <div class="gh-hero-slide__content">
                <?php if (!empty($slide['eyebrow'])) : ?>
                    <span class="gh-hero-slide__eyebrow"><?php echo esc_html($slide['eyebrow']); ?></span>
                <?php endif; ?>

                <?php if (!empty($slide['title'])) : ?>
                    <h2 class="gh-hero-slide__title" data-gh-split="words"><?php echo esc_html($slide['title']); ?></h2>
                <?php endif; ?>

                <?php if (!empty($slide['subtitle'])) : ?>
                    <p class="gh-hero-slide__subtitle"><?php echo esc_html($slide['subtitle']); ?></p>
                <?php endif; ?>

                <?php if (!empty($slide['buttonUrl']) && !empty($slide['buttonText'])) : ?>
                    <div class="gh-hero-slide__cta">
                        <a href="<?php echo esc_url($slide['buttonUrl']); ?>" class="gh-btn gh-btn--primary gh-btn--large" data-gh-magnetic="0.2">
                            <?php echo esc_html($slide['buttonText']); ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($show_dots) : ?>
        <nav class="gh-hero-nav" data-gh-hero-dots aria-label="Navigazione slides"></nav>
    <?php endif; ?>

    <?php if ($show_arrows) : ?>
        <div class="gh-hero-arrows">
            <button class="gh-hero-arrow" data-gh-hero-prev aria-label="Slide precedente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <button class="gh-hero-arrow" data-gh-hero-next aria-label="Slide successiva">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    <?php endif; ?>
</section>
