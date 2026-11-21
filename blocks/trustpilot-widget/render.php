<?php
/**
 * Trustpilot Widget block — server render.
 *
 * Shows the rating the page writes, as it stands on the store's Trustpilot
 * page; without one, nothing (the plugin never invents a rating). The
 * official TrustBox embed stays the better choice: it's always current.
 */

$rating = (float) ($attributes['rating'] ?? 0);
$review_count = (int) ($attributes['reviewCount'] ?? 0);
$label = (string) ($attributes['label'] ?? '');
$trustpilot_url = $attributes['trustpilotUrl'] ?? '';

if ($rating <= 0) {
    return;
}

$rating = max(0, min(5, $rating));

$full_stars = (int) floor($rating);
$has_half = ($rating - $full_stars) >= 0.5;
$empty_stars = 5 - $full_stars - ($has_half ? 1 : 0);
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-trustpilot')); ?> data-gh-reveal="up">
    <div class="gh-trustpilot__container">
        <?php if (!empty($trustpilot_url)) : ?>
            <a class="gh-trustpilot__link" href="<?php echo esc_url($trustpilot_url); ?>" target="_blank" rel="noopener noreferrer">
        <?php endif; ?>

        <div class="gh-trustpilot__header">
            <?php if ('' !== $label) : ?>
                <span class="gh-trustpilot__label"><?php echo esc_html($label); ?></span>
            <?php endif; ?>

            <div class="gh-trustpilot__stars">
                <?php for ($i = 0; $i < $full_stars; $i++) : ?>
                    <span class="gh-trustpilot__star gh-tp-star gh-tp-star--full" aria-hidden="true"><?php echo gh_icon('star', 20); ?></span>
                <?php endfor; ?>

                <?php if ($has_half) : ?>
                    <span class="gh-trustpilot__star gh-tp-star gh-tp-star--half" aria-hidden="true"><?php echo gh_icon('star', 20); ?></span>
                <?php endif; ?>

                <?php for ($i = 0; $i < $empty_stars; $i++) : ?>
                    <span class="gh-trustpilot__star gh-tp-star gh-tp-star--empty" aria-hidden="true"><?php echo gh_icon('star', 20); ?></span>
                <?php endfor; ?>

                <span class="gh-sr-only"><?php echo esc_html(number_format_i18n($rating, 1)); ?> su 5</span>
            </div>

            <div class="gh-trustpilot__rating">
                <?php echo esc_html(number_format_i18n($rating, 1)); ?> / 5
            </div>

            <?php if ($review_count > 0) : ?>
                <p class="gh-trustpilot__count">
                    Basato su <strong><?php echo esc_html(number_format_i18n($review_count)); ?></strong> recensioni
                </p>
            <?php endif; ?>

            <div class="gh-trustpilot__logo">
                <?php echo gh_icon('star', 16); ?>
                <span class="gh-trustpilot__logo-text">Trustpilot</span>
            </div>
        </div>

        <?php if (!empty($trustpilot_url)) : ?>
            </a>
        <?php endif; ?>
    </div>
</section>
