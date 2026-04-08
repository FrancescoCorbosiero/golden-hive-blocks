<?php
/**
 * Newsletter Block - Render lato server
 */

$title = $attributes['title'] ?? '';
$text = $attributes['text'] ?? '';
$placeholder = $attributes['placeholder'] ?? 'La tua email';
$button_text = $attributes['buttonText'] ?? 'Iscriviti';
$disclaimer = $attributes['disclaimer'] ?? '';

if (empty($title)) {
    return;
}
?>
<section class="gh-block gh-newsletter">
    <div class="gh-newsletter__container" data-gh-reveal="up">
        <div class="gh-newsletter__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>

        <h2 class="gh-newsletter__title"><?php echo esc_html($title); ?></h2>

        <?php if (!empty($text)) : ?>
            <p class="gh-newsletter__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <form class="gh-newsletter__form" data-gh-newsletter-form>
            <input
                type="email"
                class="gh-newsletter__input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                required
                data-gh-newsletter-input
            >
            <button type="submit" class="gh-btn gh-btn--dark" data-gh-newsletter-submit>
                <?php echo esc_html($button_text); ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </form>

        <p class="gh-newsletter__feedback" data-gh-newsletter-feedback aria-live="polite"></p>

        <?php if (!empty($disclaimer)) : ?>
            <p class="gh-newsletter__disclaimer"><?php echo esc_html($disclaimer); ?></p>
        <?php endif; ?>
    </div>
</section>
