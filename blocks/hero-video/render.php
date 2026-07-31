<?php
/**
 * Hero Video Block - Render lato server
 */

$media_type = $attributes['mediaType'] ?? 'image';
$video_url = $attributes['videoUrl'] ?? '';
$image_url = $attributes['imageUrl'] ?? '';
$poster_url = $attributes['posterUrl'] ?? '';
$badge = $attributes['badge'] ?? '';
$title = $attributes['title'] ?? '';
$subtitle = $attributes['subtitle'] ?? '';
$primary_btn_text = $attributes['primaryButtonText'] ?? '';
$primary_btn_url = $attributes['primaryButtonUrl'] ?? '';
$secondary_btn_text = $attributes['secondaryButtonText'] ?? '';
$secondary_btn_url = $attributes['secondaryButtonUrl'] ?? '';
$show_scroll = $attributes['showScrollIndicator'] ?? true;

if (empty($title)) {
    return;
}

$block_id = 'gh-hero-video-' . wp_unique_id();
?>
<section <?php echo get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-hero-video',
    'id'    => $block_id,
)); ?> data-gh-hero-video>
    <div class="gh-hero-video__media">
        <?php if ($media_type === 'video' && !empty($video_url)) : ?>
            <video
                autoplay
                muted
                loop
                playsinline
                aria-hidden="true"
                data-gh-video-bg
                <?php if (!empty($poster_url)) : ?>poster="<?php echo esc_url($poster_url); ?>"<?php endif; ?>
            >
                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
            </video>
        <?php elseif (!empty($image_url)) : ?>
            <?php echo gh_img((int) ($attributes['imageUrlId'] ?? 0), $image_url, array(
                'alt'           => '',
                'loading'       => 'eager',
                'fetchpriority' => 'high',
            )); ?>
        <?php endif; ?>
    </div>

    <div class="gh-hero-video__overlay"></div>

    <div class="gh-hero-video__content">
        <?php if (!empty($badge)) : ?>
            <span class="gh-hero-video__badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <?php echo esc_html($badge); ?>
            </span>
        <?php endif; ?>

        <h2 class="gh-section-title gh-hero-video__title" data-gh-split="chars"><?php echo esc_html($title); ?></h2>

        <?php if (!empty($subtitle)) : ?>
            <p class="gh-hero-video__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>

        <?php if (!empty($primary_btn_url) || !empty($secondary_btn_url)) : ?>
            <div class="gh-hero-video__cta">
                <?php if (!empty($primary_btn_url) && !empty($primary_btn_text)) :
                    echo gh_button(array(
                        'url'     => $primary_btn_url,
                        'text'    => $primary_btn_text,
                        'classes' => 'gh-btn gh-btn--primary gh-btn--large',
                    ));
                endif; ?>

                <?php if (!empty($secondary_btn_url) && !empty($secondary_btn_text)) :
                    echo gh_button(array(
                        'url'     => $secondary_btn_url,
                        'text'    => $secondary_btn_text,
                        'classes' => 'gh-btn gh-btn--secondary gh-btn--large',
                        'icon'    => '',
                    ));
                endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($show_scroll) : ?>
        <div class="gh-hero-video__scroll">
            <span>Scorri</span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M19 12l-7 7-7-7"/>
            </svg>
        </div>
    <?php endif; ?>
</section>
