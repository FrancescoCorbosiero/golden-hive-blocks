<?php
/**
 * Drop Countdown Block - Render lato server
 */

$product_name = $attributes['productName'] ?? '';
$product_image = $attributes['productImage'] ?? '';
$release_date = $attributes['releaseDate'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Imposta Promemoria';
$button_url = $attributes['buttonUrl'] ?? '#';
$eyebrow = $attributes['eyebrow'] ?? 'Prossimo Drop';
$bg_color = $attributes['backgroundColor'] ?? '#0a0a0a';

if (empty($product_name)) {
    return;
}

$block_id = 'gh-drop-countdown-' . wp_unique_id();
?>
<section class="gh-block gh-drop-countdown"
    id="<?php echo esc_attr($block_id); ?>"
    style="background-color: <?php echo esc_attr($bg_color); ?>">

    <div class="gh-drop-countdown__inner">
        <div class="gh-drop-countdown__content">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-drop-countdown__eyebrow" data-gh-reveal="up"><?php echo esc_html(strtoupper($eyebrow)); ?></span>
            <?php endif; ?>

            <h2 class="gh-drop-countdown__title" data-gh-reveal="up" data-gh-reveal-delay="100"><?php echo esc_html($product_name); ?></h2>

            <?php if (!empty($release_date)) : ?>
                <div class="gh-drop-countdown__timer" data-gh-countdown="<?php echo esc_attr($release_date); ?>" data-gh-countdown-expired="DISPONIBILE ORA" data-gh-reveal="up" data-gh-reveal-delay="200">
                    <div class="gh-countdown__item">
                        <span class="gh-countdown__value" data-gh-countdown-giorni>00</span>
                        <span class="gh-countdown__label">Giorni</span>
                    </div>
                    <div class="gh-countdown__item">
                        <span class="gh-countdown__value" data-gh-countdown-ore>00</span>
                        <span class="gh-countdown__label">Ore</span>
                    </div>
                    <div class="gh-countdown__item">
                        <span class="gh-countdown__value" data-gh-countdown-minuti>00</span>
                        <span class="gh-countdown__label">Minuti</span>
                    </div>
                    <div class="gh-countdown__item">
                        <span class="gh-countdown__value" data-gh-countdown-secondi>00</span>
                        <span class="gh-countdown__label">Secondi</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($button_url)) : ?>
                <div class="gh-drop-countdown__cta" data-gh-reveal="up" data-gh-reveal-delay="300">
                    <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary gh-btn--large" data-gh-magnetic="0.2">
                        <?php echo esc_html($button_text); ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($product_image)) : ?>
            <div class="gh-drop-countdown__image" data-gh-reveal="up" data-gh-reveal-delay="150">
                <img src="<?php echo esc_url($product_image); ?>"
                     alt="<?php echo esc_attr($product_name); ?>"
                     loading="lazy">
            </div>
        <?php endif; ?>
    </div>
</section>
