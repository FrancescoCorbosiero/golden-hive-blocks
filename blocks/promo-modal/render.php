<?php
/**
 * Promo Modal Block - Render lato server
 */

$modal_id = $attributes['modalId'] ?? 'promo-popup';
$image_url = $attributes['imageUrl'] ?? '';
$badge = $attributes['badge'] ?? 'Offerta Esclusiva';
$title = $attributes['title'] ?? '';
$text = $attributes['text'] ?? '';
$coupon_code = $attributes['couponCode'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Ottieni lo Sconto';
$button_url = $attributes['buttonUrl'] ?? '';
$disclaimer = $attributes['disclaimer'] ?? '';
$auto_show = $attributes['autoShow'] ?? true;
$show_delay = $attributes['showDelay'] ?? 5000;
$show_once = $attributes['showOnce'] ?? true;

if (empty($title)) {
    return;
}
?>
<div class="gh-block gh-promo-modal"
     data-gh-modal="<?php echo esc_attr($modal_id); ?>"
     <?php if ($auto_show) : ?>
     data-gh-promo-modal
     data-gh-promo-delay="<?php echo esc_attr($show_delay); ?>"
     data-gh-promo-once="<?php echo $show_once ? 'true' : 'false'; ?>"
     <?php endif; ?>
     role="dialog"
     aria-modal="true"
     aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">

    <div class="gh-promo-modal__backdrop"></div>

    <div class="gh-promo-modal__container">
        <button class="gh-promo-modal__close" data-gh-modal-close aria-label="Chiudi">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <?php if (!empty($image_url)) : ?>
            <div class="gh-promo-modal__visual">
                <img src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>

        <div class="gh-promo-modal__content">
            <?php if (!empty($badge)) : ?>
                <span class="gh-promo-modal__badge"><?php echo esc_html($badge); ?></span>
            <?php endif; ?>

            <h3 class="gh-promo-modal__title" id="<?php echo esc_attr($modal_id); ?>-title">
                <?php echo esc_html($title); ?>
            </h3>

            <?php if (!empty($text)) : ?>
                <p class="gh-promo-modal__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>

            <?php if (!empty($coupon_code)) : ?>
                <div class="gh-promo-modal__code"><?php echo esc_html($coupon_code); ?></div>
            <?php endif; ?>

            <?php if (!empty($button_url)) : ?>
                <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary gh-btn--large">
                    <?php echo esc_html($button_text); ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endif; ?>

            <?php if (!empty($disclaimer)) : ?>
                <p class="gh-promo-modal__disclaimer"><?php echo esc_html($disclaimer); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
