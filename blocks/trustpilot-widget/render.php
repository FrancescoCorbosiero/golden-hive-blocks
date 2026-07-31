<?php
/**
 * Trustpilot Widget Block - Render lato server
 */

$rating = $attributes['rating'] ?? 4.8;
$review_count = $attributes['reviewCount'] ?? 250;
$label = $attributes['label'] ?? 'Eccezionale';
$trustpilot_url = $attributes['trustpilotUrl'] ?? '';

$rating = max(0, min(5, (float) $rating));

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
            <span class="gh-trustpilot__label"><?php echo esc_html($label); ?></span>

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

                <span class="gh-sr-only"><?php echo esc_html(number_format($rating, 1)); ?> su 5</span>
            </div>

            <div class="gh-trustpilot__rating">
                <?php echo number_format($rating, 1); ?> / 5
            </div>

            <p class="gh-trustpilot__count">
                Basato su <strong><?php echo number_format($review_count); ?></strong> recensioni
            </p>

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
