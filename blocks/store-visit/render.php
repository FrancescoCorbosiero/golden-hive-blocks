<?php
/**
 * Vieni in negozio Block - Render lato server
 *
 * Il negozio fisico in un blocco: stato "Aperto ora · chiude alle 19:30" e
 * riga di oggi calcolati nel browser (js/live-blocks.js StoreHours, fuso del
 * sito, festività e chiusure incluse), indirizzo (e P.IVA) con "Indicazioni", WhatsApp
 * (numero del Customizer, Golden Hive — Pagina prodotto) e telefono, e una
 * mappa Google che si carica solo al clic (gh_map_facade).
 */

$eyebrow       = $attributes['eyebrow'] ?? '';
$title         = $attributes['title'] ?? '';
$subtitle      = $attributes['subtitle'] ?? '';
$address       = trim((string) ($attributes['address'] ?? ''));
$vat           = trim((string) ($attributes['vat'] ?? ''));
$phone         = trim((string) ($attributes['phone'] ?? ''));
$hours         = $attributes['hours'] ?? array();
$note          = $attributes['note'] ?? '';
$whatsapp      = (!empty($attributes['showWhatsApp']) && function_exists('ghb_whatsapp_number')) ? ghb_whatsapp_number() : '';
$whatsapp_text = $attributes['whatsappText'] ?? '';

if ('' === $title && '' === $address && empty($hours)) {
    return;
}

$hours_data = gh_hours_data($hours, $attributes['closedOnHolidays'] ?? true, $attributes['closedDates'] ?? '');
$map        = gh_map_facade($attributes['mapEmbed'] ?? '', array(
    'address'   => $address,
    'image_url' => $attributes['mapImage'] ?? '',
    'image_id'  => (int) ($attributes['mapImageId'] ?? 0),
));
$directions = '' !== $address ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address) : '';
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-store-visit' . ('' === $map ? ' gh-store-visit--no-map' : ''))); ?>
         data-ghb-hours="<?php echo esc_attr($hours_data); ?>">
    <div class="gh-store-visit__inner">
        <div class="gh-store-visit__info" data-gh-reveal="up">
            <?php if ('' !== $eyebrow) : ?>
                <span class="gh-eyebrow gh-store-visit__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>
            <?php if ('' !== $title) : ?>
                <h2 class="gh-store-visit__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <p class="gh-hours-status gh-store-visit__status" data-ghb-hours-status hidden>
                <span class="gh-hours-status__dot" aria-hidden="true"></span>
                <span data-ghb-hours-text></span>
            </p>

            <?php if ('' !== $subtitle) : ?>
                <p class="gh-store-visit__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>

            <?php if ('' !== $address) : ?>
                <p class="gh-store-visit__address">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>
                        <?php echo esc_html($address); ?>
                        <?php if ('' !== $vat) : ?>
                            <span class="gh-store-visit__vat"><?php echo esc_html($vat); ?></span>
                        <?php endif; ?>
                    </span>
                </p>
            <?php elseif ('' !== $vat) : ?>
                <p class="gh-store-visit__vat"><?php echo esc_html($vat); ?></p>
            <?php endif; ?>

            <div class="gh-store-visit__actions">
                <?php if ('' !== $directions) : ?>
                    <a class="gh-store-visit__action gh-store-visit__action--primary" href="<?php echo esc_url($directions); ?>" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                        <span>Indicazioni</span>
                    </a>
                <?php endif; ?>
                <?php if ('' !== $whatsapp) : ?>
                    <a class="gh-store-visit__action gh-store-visit__action--whatsapp"
                       href="<?php echo esc_url('https://wa.me/' . $whatsapp . ('' !== $whatsapp_text ? '?text=' . rawurlencode($whatsapp_text) : '')); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91A9.9 9.9 0 0 0 12.04 2Zm5.8 14.13c-.25.69-1.44 1.32-2 1.4-.51.08-1.15.11-1.86-.12-.43-.14-.98-.32-1.68-.62-2.96-1.28-4.89-4.26-5.04-4.46-.15-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.17 1.03-2.46.27-.3.59-.37.79-.37h.57c.18 0 .43-.07.67.51.25.59.84 2.04.91 2.19.07.15.12.32.02.52-.1.2-.15.32-.3.49-.15.17-.31.38-.44.52-.15.15-.3.31-.13.6.17.3.77 1.27 1.65 2.06 1.13 1.01 2.09 1.32 2.38 1.47.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.49.22.57.35.07.12.07.71-.18 1.4Z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                <?php endif; ?>
                <?php if ('' !== $phone) : ?>
                    <a class="gh-store-visit__action" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $phone)); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                        <span><?php echo esc_html($phone); ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($hours)) : ?>
                <dl class="gh-store-visit__hours">
                    <?php foreach ($hours as $row) :
                        $day  = (string) ($row['day'] ?? '');
                        $time = (string) ($row['time'] ?? '');
                        ?>
                        <div class="gh-store-visit__hours-row" data-ghb-day="<?php echo esc_attr($day); ?>">
                            <dt><?php echo esc_html($day); ?> <span class="gh-store-visit__today" data-ghb-today-badge hidden>Oggi</span></dt>
                            <dd class="<?php echo 'chiuso' === strtolower(trim($time)) ? 'is-closed' : ''; ?>"><?php echo esc_html($time); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php if ('' !== $note) : ?>
                <p class="gh-store-visit__note"><?php echo esc_html($note); ?></p>
            <?php endif; ?>
        </div>

        <?php if ('' !== $map) : ?>
            <div class="gh-store-visit__map" data-gh-reveal="up" style="--gh-reveal-delay: 150ms">
                <?php echo $map; // Built and escaped by gh_map_facade(). ?>
            </div>
        <?php endif; ?>
    </div>
</section>
