<?php
/**
 * Social Proof Block - Notifica acquisto stile Shopify
 */

$notifications = $attributes['notifications'] ?? [];
$interval = $attributes['interval'] ?? 30000;
$initial_delay = $attributes['initialDelay'] ?? 15000;
$display_duration = $attributes['displayDuration'] ?? 6000;
$show_verified = $attributes['showVerified'] ?? true;
$title = $attributes['title'] ?? 'Qualcuno ha appena acquistato';

if (empty($notifications)) {
    return;
}
?>
<div <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-social-proof')); ?>
     data-gh-social-proof
     data-gh-social-proof-items="<?php echo esc_attr(wp_json_encode($notifications)); ?>"
     data-gh-social-proof-interval="<?php echo esc_attr($interval); ?>"
     data-gh-social-proof-delay="<?php echo esc_attr($initial_delay); ?>"
     data-gh-social-proof-duration="<?php echo esc_attr($display_duration); ?>"
     aria-live="polite"
     aria-atomic="true">

    <div class="gh-social-proof__image">
        <img src="" alt="" data-gh-social-proof-image>
    </div>

    <div class="gh-social-proof__body">
        <p class="gh-social-proof__text" data-gh-social-proof-text>
            <?php echo esc_html($title); ?> <strong data-gh-social-proof-product></strong>
        </p>
        <p class="gh-social-proof__meta" data-gh-social-proof-meta></p>
        <?php if ($show_verified) : ?>
        <span class="gh-social-proof__verified">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
            </svg>
            Acquisto verificato
        </span>
        <?php endif; ?>
    </div>

    <button class="gh-social-proof__close" data-gh-social-proof-close aria-label="Chiudi notifica">
        <?php echo gh_icon('close'); ?>
    </button>
</div>
