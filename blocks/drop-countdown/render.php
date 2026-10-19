<?php
/**
 * Drop Countdown Block - Render lato server
 *
 * Collegato a un prodotto (productId) ne usa la data di drop (Dati prodotto →
 * Generale → Drop, includes/drops.php), l'immagine, il prezzo e il link; i
 * campi del blocco restano come alternativa. Prima dell'uscita: conto alla
 * rovescia e "Ricordamelo" (Google Calendar / .ics); all'uscita il blocco
 * passa da solo ad "Acquista ora" (js/live-blocks.js, data-ghb-drop), anche
 * su una pagina in cache.
 */

$product_name  = $attributes['productName'] ?? '';
$product_image = $attributes['productImage'] ?? '';
$release_date  = $attributes['releaseDate'] ?? '';
$button_text   = $attributes['buttonText'] ?? 'Imposta Promemoria';
$button_url    = $attributes['buttonUrl'] ?? '#';
$eyebrow       = $attributes['eyebrow'] ?? 'Prossimo Drop';
$bg_color      = $attributes['backgroundColor'] ?? '#0a0a0a';

$product_id = (int) ($attributes['productId'] ?? 0);
$product    = ($product_id && function_exists('wc_get_product')) ? wc_get_product($product_id) : null;
if ($product && 'publish' !== $product->get_status()) {
    $product = null;
}

$name = '' !== $product_name ? $product_name : ($product ? $product->get_name() : '');
if ('' === $name) {
    return;
}

// The drop time: the product's own, else the block's date (site time zone).
$ts = ($product && function_exists('ghb_drop_ts')) ? ghb_drop_ts($product->get_id()) : 0;
if (!$ts && '' !== $release_date) {
    $date = date_create($release_date, wp_timezone());
    $ts   = $date ? $date->getTimestamp() : 0;
}

$image_id  = (int) ($attributes['productImageId'] ?? 0);
$image_url = $product_image;
if ('' === $image_url && $product) {
    $image_id  = (int) $product->get_image_id();
    $image_url = (string) wp_get_attachment_image_url($image_id, 'large');
}

$link       = $product ? $product->get_permalink() : ('#' !== $button_url ? $button_url : '');
$released   = $ts && $ts <= time();
$block_id   = 'gh-drop-countdown-' . wp_unique_id();
$has_remind = $ts && function_exists('ghb_drop_remind_html');

if ($ts) {
    wp_enqueue_script('ghb-live-blocks');
}
?>
<section <?php echo get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-drop-countdown' . ($released ? ' is-live' : ''),
    'id'    => $block_id,
    'style' => 'background-color: ' . $bg_color,
)); ?><?php echo $ts ? ' data-ghb-drop="' . (int) $ts . '"' : ''; ?>>

    <div class="gh-drop-countdown__inner">
        <div class="gh-drop-countdown__content">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-eyebrow gh-drop-countdown__eyebrow" data-gh-reveal="up"><?php echo esc_html(strtoupper($eyebrow)); ?></span>
            <?php endif; ?>

            <h2 class="gh-section-title gh-drop-countdown__title" data-gh-reveal="up" data-gh-reveal-delay="100" style="--gh-reveal-delay: 100ms"><?php echo esc_html($name); ?></h2>

            <?php if ($ts || ($product && '' !== $product->get_price_html())) : ?>
                <p class="gh-drop-countdown__meta" data-gh-reveal="up" data-gh-reveal-delay="150" style="--gh-reveal-delay: 150ms">
                    <?php if ($ts) : ?>
                        <span class="gh-drop-countdown__date"><?php echo esc_html(function_exists('ghb_drop_long_date') ? ghb_drop_long_date($ts) : wp_date('j F Y, H:i', $ts)); ?></span>
                    <?php endif; ?>
                    <?php if ($product && '' !== $product->get_price_html()) : ?>
                        <span class="gh-drop-countdown__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($ts) : ?>
                <div class="gh-drop-countdown__timer" role="timer" aria-live="polite" aria-atomic="true" data-gh-countdown="<?php echo esc_attr(gmdate('c', $ts)); ?>" data-gh-countdown-expired="DISPONIBILE ORA" data-gh-reveal="up" data-gh-reveal-delay="200" style="--gh-reveal-delay: 200ms">
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

            <div class="gh-drop-countdown__cta" data-gh-reveal="up" data-gh-reveal-delay="300" style="--gh-reveal-delay: 300ms">
                <?php if ($ts) : ?>
                    <div class="gh-drop-countdown__actions" data-ghb-drop-before<?php echo $released ? ' hidden' : ''; ?>>
                        <?php
                        if ($has_remind) {
                            $event = $product && function_exists('ghb_drop_event_for_product') && ghb_drop_ts($product->get_id())
                                ? ghb_drop_event_for_product($product)
                                : array(
                                    'uid'   => 'drop-' . $ts . '-' . md5($name),
                                    'ts'    => $ts,
                                    'title' => 'Drop: ' . $name,
                                    'url'   => $link,
                                );
                            $ics = ($product && ghb_drop_ts($product->get_id()))
                                ? ghb_drop_ics_url($event, $product->get_id())
                                : ghb_drop_ics_url($event);
                            echo ghb_drop_remind_html($event, $ics, 'ghb-remind--dark'); // Escaped inside.
                        }
                        if ('' !== $link) {
                            echo gh_button(array(
                                'url'     => $link,
                                'text'    => $product ? 'Vedi il prodotto' : $button_text,
                                'classes' => 'gh-btn gh-btn--outline gh-btn--large',
                                'icon'    => '',
                            ));
                        }
                        ?>
                    </div>
                    <?php if ('' !== $link) : ?>
                        <div class="gh-drop-countdown__actions" data-ghb-drop-after<?php echo $released ? '' : ' hidden'; ?>>
                            <?php echo gh_button(array(
                                'url'      => $link,
                                'text'     => $product ? 'Acquista ora' : $button_text,
                                'classes'  => 'gh-btn gh-btn--primary gh-btn--large',
                                'magnetic' => '0.2',
                            )); ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ('' !== $link) : ?>
                    <?php echo gh_button(array(
                        'url'      => $link,
                        'text'     => $button_text,
                        'classes'  => 'gh-btn gh-btn--primary gh-btn--large',
                        'magnetic' => '0.2',
                    )); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($image_url)) : ?>
            <div class="gh-drop-countdown__image" data-gh-reveal="up" data-gh-reveal-delay="150" style="--gh-reveal-delay: 150ms">
                <?php echo gh_img($image_id, $image_url, array(
                    'alt'     => $name,
                    'loading' => 'lazy',
                )); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
