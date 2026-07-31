<?php
/**
 * Authenticity Guarantee Block - Render lato server
 */

$eyebrow = $attributes['eyebrow'] ?? '';
$title = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$badge_text = $attributes['badgeText'] ?? 'Certified Authentic';
$steps = $attributes['steps'] ?? [];
$button_text = $attributes['buttonText'] ?? '';
$button_url = $attributes['buttonUrl'] ?? '';

if (empty($title)) {
    return;
}
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-authenticity')); ?>>
    <div class="gh-authenticity__container">
        <div class="gh-authenticity__visual" data-gh-reveal="left">
            <div class="gh-authenticity__badge-large">
                <div class="gh-icon-circle gh-authenticity__badge-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
                <span class="gh-authenticity__badge-text"><?php echo esc_html($badge_text); ?></span>
                <span class="gh-authenticity__badge-sub">Golden Hive</span>
            </div>
        </div>

        <div class="gh-authenticity__content" data-gh-reveal="right">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-eyebrow gh-authenticity__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>

            <h2 class="gh-section-title gh-authenticity__title"><?php echo esc_html($title); ?></h2>

            <?php if (!empty($description)) : ?>
                <p class="gh-authenticity__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <?php if (!empty($steps)) : ?>
                <div class="gh-authenticity__steps">
                    <?php foreach ($steps as $index => $step) : ?>
                        <div class="gh-authenticity__step">
                            <span class="gh-icon-circle gh-authenticity__step-number"><?php echo $index + 1; ?></span>
                            <div class="gh-authenticity__step-content">
                                <?php if (!empty($step['title'])) : ?>
                                    <h4><?php echo esc_html($step['title']); ?></h4>
                                <?php endif; ?>
                                <?php if (!empty($step['text'])) : ?>
                                    <p><?php echo esc_html($step['text']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($button_url) && !empty($button_text)) :
                echo gh_button(array(
                    'url'     => $button_url,
                    'text'    => $button_text,
                    'classes' => 'gh-btn gh-btn--primary',
                    'attrs'   => array('style' => 'margin-top: var(--gh-space-xl);'),
                ));
            endif; ?>
        </div>
    </div>
</section>
