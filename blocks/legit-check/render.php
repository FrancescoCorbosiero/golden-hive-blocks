<?php
/**
 * Legit Check Block - Render lato server
 */

$eyebrow = $attributes['eyebrow'] ?? '';
$title = $attributes['title'] ?? '';
$subtitle = $attributes['subtitle'] ?? '';
$checks = $attributes['checks'] ?? [];
$button_text = $attributes['buttonText'] ?? '';
$button_url = $attributes['buttonUrl'] ?? '';

if (empty($title)) {
    return;
}
?>
<section class="gh-block gh-legit-check">
    <div class="gh-legit-check__container">
        <div class="gh-legit-check__header" data-gh-reveal="up">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-legit-check__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>

            <h2 class="gh-legit-check__title"><?php echo esc_html($title); ?></h2>

            <?php if (!empty($subtitle)) : ?>
                <p class="gh-legit-check__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($checks)) : ?>
            <div class="gh-legit-check__grid" data-gh-reveal="up">
                <?php foreach ($checks as $check) : ?>
                    <div class="gh-legit-check__card">
                        <h3 class="gh-legit-check__card-area"><?php echo esc_html($check['area'] ?? ''); ?></h3>

                        <div class="gh-legit-check__columns">
                            <div class="gh-legit-check__col gh-legit-check__col--real">
                                <div class="gh-legit-check__col-header">
                                    <span class="gh-legit-check__icon gh-legit-check__icon--check">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </span>
                                    <span class="gh-legit-check__col-label">AUTENTICO</span>
                                </div>
                                <p class="gh-legit-check__col-text"><?php echo esc_html($check['real'] ?? ''); ?></p>
                            </div>

                            <div class="gh-legit-check__col gh-legit-check__col--fake">
                                <div class="gh-legit-check__col-header">
                                    <span class="gh-legit-check__icon gh-legit-check__icon--x">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </span>
                                    <span class="gh-legit-check__col-label">FALSO</span>
                                </div>
                                <p class="gh-legit-check__col-text"><?php echo esc_html($check['fake'] ?? ''); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($button_url) && !empty($button_text)) : ?>
            <div class="gh-legit-check__cta" data-gh-reveal="up">
                <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary">
                    <?php echo esc_html($button_text); ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
