<?php
/**
 * Instagram Feed Block - Render lato server
 */

$title = $attributes['title'] ?? 'Seguici su Instagram';
$subtitle = $attributes['subtitle'] ?? '';
$instagram_url = $attributes['instagramUrl'] ?? 'https://www.instagram.com/resellpiacenza/';
$button_text = $attributes['buttonText'] ?? '@resellpiacenza';
$images = $attributes['images'] ?? [];
$columns = $attributes['columns'] ?? 3;

$instagram_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>';
$instagram_icon_filled = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>';
?>
<section class="gh-block gh-instagram-feed" data-gh-reveal="up">
    <div class="gh-instagram-feed__header">
        <?php if (!empty($title)) : ?>
            <h2 class="gh-instagram-feed__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if (!empty($subtitle)) : ?>
            <p class="gh-instagram-feed__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </div>

    <div class="gh-instagram-feed__grid" style="--gh-ig-columns: <?php echo esc_attr($columns); ?>;">
        <?php foreach ($images as $index => $image) : ?>
            <a href="<?php echo esc_url($instagram_url); ?>"
               class="gh-instagram-feed__cell"
               target="_blank"
               rel="noopener noreferrer"
               data-gh-reveal="up"
               style="--gh-ig-delay: <?php echo esc_attr($index * 0.1); ?>s;">

                <?php if (!empty($image['url'])) : ?>
                    <img
                        src="<?php echo esc_url($image['url']); ?>"
                        alt="<?php echo esc_attr($image['alt'] ?? ''); ?>"
                        class="gh-instagram-feed__image"
                        loading="lazy"
                    >
                <?php else : ?>
                    <div class="gh-instagram-feed__placeholder">
                        <span class="gh-instagram-feed__placeholder-icon">
                            <?php echo $instagram_icon; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="gh-instagram-feed__overlay">
                    <span class="gh-instagram-feed__overlay-icon">
                        <?php echo $instagram_icon_filled; ?>
                    </span>
                    <span class="gh-instagram-feed__overlay-text">Vedi su Instagram</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($instagram_url)) : ?>
        <div class="gh-instagram-feed__cta" data-gh-reveal="up">
            <a href="<?php echo esc_url($instagram_url); ?>"
               class="gh-btn gh-btn--outline"
               target="_blank"
               rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                </svg>
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
    <?php endif; ?>
</section>
