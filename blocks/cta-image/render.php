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
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-cta-image' . ($reverse ? ' gh-cta-image--reverse' : ''))); ?>>
    <div class="gh-cta-image__visual" data-gh-reveal="<?php echo $reverse ? 'right' : 'left'; ?>" data-gh-image-reveal>
        <?php if (!empty($image_url)) : ?>
            <?php echo gh_img((int) ($attributes['imageUrlId'] ?? 0), $image_url, array(
                'alt'     => $title,
                'loading' => 'lazy',
            )); ?>
        <?php endif; ?>
    </div>

    <div class="gh-cta-image__content" data-gh-reveal="<?php echo $reverse ? 'left' : 'right'; ?>">
        <?php if (!empty($eyebrow)) : ?>
            <span class="gh-eyebrow gh-cta-image__eyebrow"><?php echo esc_html($eyebrow); ?></span>
        <?php endif; ?>

        <h2 class="gh-section-title gh-cta-image__title"><?php echo esc_html($title); ?></h2>

        <?php if (!empty($text)) : ?>
            <p class="gh-cta-image__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if (!empty($button_url)) :
            echo gh_button(array(
                'url'     => $button_url,
                'text'    => $button_text,
                'classes' => 'gh-btn gh-btn--primary gh-btn--large',
            ));
        endif; ?>
    </div>
</section>
