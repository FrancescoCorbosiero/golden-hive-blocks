<?php
/**
 * Trustpilot Widget Block - Render lato server
 */

$rating = $attributes['rating'] ?? 4.8;
$review_count = $attributes['reviewCount'] ?? 250;
$label = $attributes['label'] ?? 'Eccezionale';
$trustpilot_url = $attributes['trustpilotUrl'] ?? '';

$full_stars = floor($rating);
$has_half = ($rating - $full_stars) >= 0.5;
?>
<section class="gh-block gh-trustpilot" data-gh-reveal="up">
    <div class="gh-trustpilot__container">
        <div class="gh-trustpilot__header">
            <span class="gh-trustpilot__label"><?php echo esc_html($label); ?></span>

            <div class="gh-trustpilot__stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="gh-trustpilot__star">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                        </svg>
                    </span>
                <?php endfor; ?>
            </div>

            <div class="gh-trustpilot__rating">
                <?php echo number_format($rating, 1); ?> / 5
            </div>

            <p class="gh-trustpilot__count">
                Basato su <strong><?php echo number_format($review_count); ?></strong> recensioni
            </p>
        </div>
    </div>
</section>