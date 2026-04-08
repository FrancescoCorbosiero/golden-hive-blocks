<?php
/**
 * Shortcode Wrapper Block - Render lato server
 */

$eyebrow = $attributes['eyebrow'] ?? '';
$title = $attributes['title'] ?? '';
$shortcode = $attributes['shortcode'] ?? '';
$bg_color = $attributes['backgroundColor'] ?? 'white';
$button_text = $attributes['buttonText'] ?? '';
$button_url = $attributes['buttonUrl'] ?? '';

if (empty($shortcode)) {
    return;
}

$bg_class = '';
switch ($bg_color) {
    case 'gray':
        $bg_class = 'background: var(--gh-gray-50);';
        break;
    case 'black':
        $bg_class = 'background: var(--gh-black); color: var(--gh-white);';
        break;
    default:
        $bg_class = 'background: var(--gh-white);';
}
?>
<section class="gh-block gh-shortcode-wrapper" style="<?php echo esc_attr($bg_class); ?>">
    <?php if (!empty($eyebrow) || !empty($title)) : ?>
        <div class="gh-shortcode-wrapper__header" data-gh-reveal="up">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-shortcode-wrapper__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>
            <?php if (!empty($title)) : ?>
                <h2 class="gh-shortcode-wrapper__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="gh-shortcode-wrapper__content">
        <?php echo do_shortcode($shortcode); ?>
    </div>

    <?php if (!empty($button_url) && !empty($button_text)) : ?>
        <div class="gh-shortcode-wrapper__cta">
            <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary gh-btn--large">
                <?php echo esc_html($button_text); ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    <?php endif; ?>
</section>
