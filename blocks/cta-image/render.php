<?php
/**
 * CTA Image Split Block - Render lato server
 */

$image_url = $attributes['imageUrl'] ?? '';
$eyebrow = $attributes['eyebrow'] ?? '';
$title = $attributes['title'] ?? '';
$text = $attributes['text'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Shop Now';
$button_url = $attributes['buttonUrl'] ?? '';
$reverse = $attributes['reverse'] ?? false;

if (empty($title)) {
    return;
}
?>
<section class="gh-block gh-cta-image<?php echo $reverse ? ' gh-cta-image--reverse' : ''; ?>">
    <div class="gh-cta-image__visual" data-gh-reveal="<?php echo $reverse ? 'right' : 'left'; ?>">
        <?php if (!empty($image_url)) : ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy">
        <?php endif; ?>
    </div>

    <div class="gh-cta-image__content" data-gh-reveal="<?php echo $reverse ? 'left' : 'right'; ?>">
        <?php if (!empty($eyebrow)) : ?>
            <span class="gh-cta-image__eyebrow"><?php echo esc_html($eyebrow); ?></span>
        <?php endif; ?>

        <h2 class="gh-cta-image__title"><?php echo esc_html($title); ?></h2>

        <?php if (!empty($text)) : ?>
            <p class="gh-cta-image__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if (!empty($button_url)) : ?>
            <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary gh-btn--large">
                <?php echo esc_html($button_text); ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        <?php endif; ?>
    </div>
</section>
