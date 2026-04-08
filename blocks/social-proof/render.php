<?php
/**
 * Social Proof Block - Shopify-style purchase notification
 */

$notifications = $attributes['notifications'] ?? [];
$interval = $attributes['interval'] ?? 30000;
$initial_delay = $attributes['initialDelay'] ?? 15000;
$display_duration = $attributes['displayDuration'] ?? 6000;
$show_verified = $attributes['showVerified'] ?? true;

if (empty($notifications)) {
    return;
}
?>
<div class="gh-block gh-social-proof"
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
            Someone purchased <strong></strong>
        </p>
        <p class="gh-social-proof__meta" data-gh-social-proof-meta></p>
        <?php if ($show_verified) : ?>
        <span class="gh-social-proof__verified">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
            </svg>
            Verified purchase
        </span>
        <?php endif; ?>
    </div>

    <button class="gh-social-proof__close" data-gh-social-proof-close aria-label="Close notification">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>
</div>
