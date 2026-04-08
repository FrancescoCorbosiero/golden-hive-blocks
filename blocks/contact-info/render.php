<?php
/**
 * Contact Info Block - Render lato server
 */

$title    = $attributes['title'] ?? '';
$subtitle = $attributes['subtitle'] ?? '';
$address  = $attributes['address'] ?? '';
$vat      = $attributes['vat'] ?? '';
$phone    = $attributes['phone'] ?? '';
$email    = $attributes['email'] ?? '';
$map_url  = $attributes['mapUrl'] ?? '';
$map_embed = $attributes['mapEmbed'] ?? '';

if (empty($title)) {
    return;
}

$has_map = !empty($map_embed) || !empty($map_url);
?>
<section class="gh-block gh-contact-info">
    <div class="gh-contact-info__container">

        <div class="gh-contact-info__header" data-gh-reveal="up">
            <h2 class="gh-contact-info__title"><?php echo esc_html($title); ?></h2>
            <?php if (!empty($subtitle)) : ?>
                <p class="gh-contact-info__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>

        <div class="gh-contact-info__body<?php echo $has_map ? ' gh-contact-info__body--with-map' : ''; ?>">

            <div class="gh-contact-info__details">

                <?php if (!empty($address)) : ?>
                    <div class="gh-contact-info__item" data-gh-reveal="up" data-gh-reveal-delay="100">
                        <span class="gh-contact-info__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </span>
                        <span class="gh-contact-info__text"><?php echo esc_html($address); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($vat)) : ?>
                    <div class="gh-contact-info__item" data-gh-reveal="up" data-gh-reveal-delay="200">
                        <span class="gh-contact-info__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>
                            </svg>
                        </span>
                        <span class="gh-contact-info__text"><?php echo esc_html($vat); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($phone)) : ?>
                    <div class="gh-contact-info__item" data-gh-reveal="up" data-gh-reveal-delay="300">
                        <span class="gh-contact-info__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                        </span>
                        <a href="tel:<?php echo esc_attr($phone); ?>" class="gh-contact-info__link"><?php echo esc_html($phone); ?></a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($email)) : ?>
                    <div class="gh-contact-info__item" data-gh-reveal="up" data-gh-reveal-delay="400">
                        <span class="gh-contact-info__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <a href="mailto:<?php echo esc_attr($email); ?>" class="gh-contact-info__link"><?php echo esc_html($email); ?></a>
                    </div>
                <?php endif; ?>

            </div>

            <?php if (!empty($map_embed)) : ?>
                <div class="gh-contact-info__map" data-gh-reveal="up" data-gh-reveal-delay="200">
                    <div class="gh-contact-info__map-wrapper">
                        <?php echo wp_kses_post($map_embed); ?>
                    </div>
                </div>
            <?php elseif (!empty($map_url)) : ?>
                <div class="gh-contact-info__map" data-gh-reveal="up" data-gh-reveal-delay="200">
                    <a href="<?php echo esc_url($map_url); ?>" class="gh-contact-info__map-link" target="_blank" rel="noopener noreferrer">
                        <span class="gh-contact-info__map-link-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                                <line x1="8" y1="2" x2="8" y2="18"/>
                                <line x1="16" y1="6" x2="16" y2="22"/>
                            </svg>
                        </span>
                        <span>Apri in Google Maps</span>
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>
</section>
