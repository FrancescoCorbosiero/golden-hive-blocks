<?php
/**
 * CTA Banner Block - Render lato server
 */

$eyebrow = $attributes['eyebrow'] ?? '';
$title = $attributes['title'] ?? '';
$text = $attributes['text'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Shop Now';
$button_url = $attributes['buttonUrl'] ?? '';
$bg_image = $attributes['backgroundImage'] ?? '';
$show_glow = $attributes['showGlow'] ?? true;

if (empty($title)) {
    return;
}
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-cta-banner')); ?>>
    <?php if (!empty($bg_image)) : ?>
        <div class="gh-cta-banner__bg">
            <?php echo gh_img((int) ($attributes['backgroundImageId'] ?? 0), $bg_image, array(
                'alt'     => '',
                'loading' => 'lazy',
            )); ?>
        </div>
    <?php endif; ?>

    <div class="gh-cta-banner__gradient"></div>

    <?php if ($show_glow) : ?>
        <div class="gh-cta-banner__glow" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="gh-cta-banner__content">
        <?php if (!empty($eyebrow)) : ?>
            <span class="gh-eyebrow gh-cta-banner__eyebrow" data-gh-reveal="up"><?php echo esc_html($eyebrow); ?></span>
        <?php endif; ?>

        <h2 class="gh-section-title gh-cta-banner__title" data-gh-reveal="up" data-gh-reveal-delay="100" style="--gh-reveal-delay: 100ms"><?php echo esc_html($title); ?></h2>

        <?php if (!empty($text)) : ?>
            <p class="gh-cta-banner__text" data-gh-reveal="up" data-gh-reveal-delay="200" style="--gh-reveal-delay: 200ms"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if (!empty($button_url)) : ?>
            <div data-gh-reveal="up" data-gh-reveal-delay="300" style="--gh-reveal-delay: 300ms">
                <?php echo gh_button(array(
                    'url'      => $button_url,
                    'text'     => $button_text,
                    'classes'  => 'gh-btn gh-btn--primary gh-btn--large',
                    'magnetic' => '0.2',
                )); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
