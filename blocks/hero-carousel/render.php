<?php
/**
 * Hero Carousel Block - Render lato server
 */

$slides = $attributes['slides'] ?? [];
$autoplay = $attributes['autoplay'] ?? 6000;
$show_dots = $attributes['showDots'] ?? true;
$show_arrows = $attributes['showArrows'] ?? true;
$layout = $attributes['layout'] ?? 'left';

if (empty($slides)) {
    return;
}

// Centered variant: symmetric layout + the refined glass-pill CTA. The classic
// (default) layout keeps the left-aligned content and solid primary button.
$is_centered = ($layout === 'centered');
$layout_class = $is_centered ? ' gh-hero-carousel--centered' : '';
$btn_class = $is_centered ? 'gh-btn gh-btn--hero gh-btn--large' : 'gh-btn gh-btn--primary gh-btn--large';

$block_id = 'gh-hero-' . wp_unique_id();
$slide_count = count($slides);
?>
<section <?php echo get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-hero-carousel' . $layout_class,
    'id'    => $block_id,
)); ?>
    data-gh-hero-carousel
    data-gh-hero-autoplay="<?php echo esc_attr($autoplay); ?>"
    aria-roledescription="carousel">

    <?php foreach ($slides as $index => $slide) : ?>
        <div class="gh-hero-slide<?php echo $index === 0 ? ' gh-hero-slide--active' : ''; ?>"
             data-gh-hero-slide
             role="group"
             aria-roledescription="slide"
             aria-label="<?php echo esc_attr(sprintf('Slide %d di %d', $index + 1, $slide_count)); ?>"
             <?php echo $index === 0 ? '' : 'aria-hidden="true" inert'; ?>>
            <div class="gh-hero-slide__bg">
                <?php if (!empty($slide['image'])) :
                    $img_pos = !empty($slide['objectPosition']) ? $slide['objectPosition'] : 'center center';
                    // Normalize shorthand values (e.g. "left" → "left center")
                    if (strpos($img_pos, ' ') === false) {
                        $img_pos .= ' center';
                    }
                    echo gh_img(
                        (int) ($slide['imageId'] ?? 0),
                        $slide['image'],
                        array(
                            'alt'           => '',
                            'style'         => 'object-position: ' . $img_pos . ';',
                            'loading'       => $index === 0 ? 'eager' : 'lazy',
                            'fetchpriority' => $index === 0 ? 'high' : 'low',
                            'decoding'      => 'async',
                        )
                    );
                endif; ?>
            </div>
            <div class="gh-hero-slide__overlay"></div>

            <div class="gh-hero-slide__content">
                <?php if (!empty($slide['eyebrow'])) : ?>
                    <span class="gh-eyebrow gh-hero-slide__eyebrow"><?php echo esc_html($slide['eyebrow']); ?></span>
                <?php endif; ?>

                <?php if (!empty($slide['title'])) : ?>
                    <h2 class="gh-section-title gh-hero-slide__title" data-gh-split="words"><?php echo esc_html($slide['title']); ?></h2>
                <?php endif; ?>

                <?php if (!empty($slide['subtitle'])) : ?>
                    <p class="gh-hero-slide__subtitle"><?php echo esc_html($slide['subtitle']); ?></p>
                <?php endif; ?>

                <?php if (!empty($slide['buttonUrl']) && !empty($slide['buttonText'])) : ?>
                    <div class="gh-hero-slide__cta">
                        <?php echo gh_button(array(
                            'url'      => $slide['buttonUrl'],
                            'text'     => $slide['buttonText'],
                            'classes'  => $btn_class,
                            'magnetic' => '0.2',
                        )); ?>
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
            <button class="gh-icon-circle gh-hero-arrow" data-gh-hero-prev aria-label="Slide precedente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <button class="gh-icon-circle gh-hero-arrow" data-gh-hero-next aria-label="Slide successiva">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    <?php endif; ?>
</section>
