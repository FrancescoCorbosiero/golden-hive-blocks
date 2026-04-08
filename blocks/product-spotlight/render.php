<?php
/**
 * Product Spotlight Block - Render lato server
 */

$image_url = $attributes['imageUrl'] ?? '';
$category = $attributes['category'] ?? '';
$title = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$condition = $attributes['condition'] ?? '';
$size = $attributes['size'] ?? '';
$authenticity = $attributes['authenticity'] ?? '';
$current_price = $attributes['currentPrice'] ?? '';
$original_price = $attributes['originalPrice'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Acquista Ora';
$button_url = $attributes['buttonUrl'] ?? '';

if (empty($title)) {
    return;
}
?>
<section class="gh-block gh-product-spotlight">
    <div class="gh-product-spotlight__bg-pattern" aria-hidden="true"></div>

    <div class="gh-product-spotlight__container">
        <div class="gh-product-spotlight__image-wrapper" data-gh-reveal="left">
            <div class="gh-product-spotlight__image">
                <?php if (!empty($image_url)) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                <?php endif; ?>

                <?php if (!empty($authenticity)) : ?>
                    <span class="gh-product-spotlight__verified">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M9 12l2 2 4-4"/>
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <?php echo esc_html($authenticity); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="gh-product-spotlight__content" data-gh-reveal="right">
            <?php if (!empty($category)) : ?>
                <span class="gh-product-spotlight__category"><?php echo esc_html($category); ?></span>
            <?php endif; ?>

            <h2 class="gh-product-spotlight__title"><?php echo esc_html($title); ?></h2>

            <?php if (!empty($description)) : ?>
                <p class="gh-product-spotlight__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <?php if (!empty($condition) || !empty($size) || !empty($authenticity)) : ?>
                <div class="gh-product-spotlight__meta">
                    <?php if (!empty($condition)) : ?>
                        <div class="gh-product-spotlight__meta-item">
                            <span class="gh-product-spotlight__meta-label">Condizione</span>
                            <span class="gh-product-spotlight__meta-value"><?php echo esc_html($condition); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($size)) : ?>
                        <div class="gh-product-spotlight__meta-item">
                            <span class="gh-product-spotlight__meta-label">Taglia</span>
                            <span class="gh-product-spotlight__meta-value"><?php echo esc_html($size); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($authenticity)) : ?>
                        <div class="gh-product-spotlight__meta-item">
                            <span class="gh-product-spotlight__meta-label">Status</span>
                            <span class="gh-product-spotlight__meta-value"><?php echo esc_html($authenticity); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($current_price)) : ?>
                <div class="gh-product-spotlight__price">
                    <span class="gh-product-spotlight__price-current"><?php echo esc_html($current_price); ?></span>
                    <?php if (!empty($original_price)) : ?>
                        <span class="gh-product-spotlight__price-original"><?php echo esc_html($original_price); ?></span>
                    <?php endif; ?>
                </div>
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
    </div>
</section>
