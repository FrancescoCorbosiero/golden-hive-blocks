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
<section class="gh-block gh-cta-banner">
    <?php if (!empty($bg_image)) : ?>
        <div class="gh-cta-banner__bg">
            <img src="<?php echo esc_url($bg_image); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>

    <div class="gh-cta-banner__gradient"></div>

    <?php if ($show_glow) : ?>
        <div class="gh-cta-banner__glow" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="gh-cta-banner__content">
        <?php if (!empty($eyebrow)) : ?>
            <span class="gh-cta-banner__eyebrow" data-gh-reveal="up"><?php echo esc_html($eyebrow); ?></span>
        <?php endif; ?>

        <h2 class="gh-cta-banner__title" data-gh-reveal="up" data-gh-reveal-delay="100"><?php echo esc_html($title); ?></h2>

        <?php if (!empty($text)) : ?>
            <p class="gh-cta-banner__text" data-gh-reveal="up" data-gh-reveal-delay="200"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if (!empty($button_url)) : ?>
            <div data-gh-reveal="up" data-gh-reveal-delay="300">
                <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary gh-btn--large" data-gh-magnetic="0.2">
                    <?php echo esc_html($button_text); ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
