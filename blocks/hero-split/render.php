<?php
/**
 * Hero Split Block — Render lato server.
 *
 * Editorial two-panel hero (media + content). Not a carousel: a single static
 * hero, mobile-first (stacks with the image on top), with theme-aware buttons.
 * Reuses the global .gh-btn system; the content panel theme (light/dark) and
 * button style are handled in style.css (.gh-hero-split*).
 */

$eyebrow      = $attributes['eyebrow'] ?? '';
$title        = $attributes['title'] ?? '';
$subtitle     = $attributes['subtitle'] ?? '';
$image_url    = $attributes['imageUrl'] ?? '';
$image_pos    = $attributes['imagePosition'] ?? 'center center';
$media_side   = ($attributes['mediaSide'] ?? 'left') === 'right' ? 'right' : 'left';
$theme        = ($attributes['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$height       = $attributes['height'] ?? 'tall';
$button_text  = $attributes['buttonText'] ?? '';
$button_url   = $attributes['buttonUrl'] ?? '';
$button_style = ($attributes['buttonStyle'] ?? 'solid') === 'outline' ? 'outline' : 'solid';

if (empty($title) && empty($image_url)) {
    return;
}

// Normalise object-position shorthand (e.g. "left" → "left center").
if (strpos($image_pos, ' ') === false) {
    $image_pos .= ' center';
}

$allowed_heights = array('auto', 'tall', 'full');
if (!in_array($height, $allowed_heights, true)) {
    $height = 'tall';
}

$section_classes = sprintf(
    'gh-block gh-hero-split gh-hero-split--media-%s gh-hero-split--%s gh-hero-split--h-%s',
    $media_side,
    $theme,
    $height
);

// Button class: solid is the theme-aware primary, outline the theme-aware ghost
// (both restyled per theme in style.css).
$btn_class = $button_style === 'outline'
    ? 'gh-btn gh-btn--outline gh-btn--large'
    : 'gh-btn gh-btn--primary gh-btn--large';
?>
<section class="<?php echo esc_attr($section_classes); ?>">
    <div class="gh-hero-split__media" data-gh-reveal="<?php echo $media_side === 'right' ? 'right' : 'left'; ?>">
        <?php if (!empty($image_url)) : ?>
            <img src="<?php echo esc_url($image_url); ?>"
                 alt="<?php echo esc_attr($title); ?>"
                 style="object-position: <?php echo esc_attr($image_pos); ?>;"
                 loading="lazy"
                 decoding="async">
        <?php endif; ?>
    </div>

    <div class="gh-hero-split__content" data-gh-reveal="<?php echo $media_side === 'right' ? 'left' : 'right'; ?>">
        <div class="gh-hero-split__inner">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-hero-split__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>

            <?php if (!empty($title)) : ?>
                <h2 class="gh-hero-split__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <?php if (!empty($subtitle)) : ?>
                <p class="gh-hero-split__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>

            <?php if (!empty($button_url) && !empty($button_text)) : ?>
                <div class="gh-hero-split__cta">
                    <a href="<?php echo esc_url($button_url); ?>" class="<?php echo esc_attr($btn_class); ?>">
                        <?php echo esc_html($button_text); ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
