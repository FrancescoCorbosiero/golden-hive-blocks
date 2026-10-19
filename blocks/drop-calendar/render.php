<?php
/**
 * Drop Calendar Block - Render lato server
 *
 * I prodotti con una data di drop (includes/drops.php, ghb_drop_upcoming),
 * in ordine di uscita: data, immagine, nome, prezzo, stato ("Esce tra 3
 * giorni", "Disponibile ora") e azioni ("Ricordamelo" prima, "Acquista ora"
 * dopo). Lo stato si calcola nel browser (js/live-blocks.js, data-ghb-drop),
 * così una pagina in cache non dice mai "in arrivo" a un drop già uscito.
 */

if (!function_exists('ghb_drop_upcoming')) {
    return;
}

$eyebrow    = $attributes['eyebrow'] ?? '';
$title      = $attributes['title'] ?? '';
$subtitle   = $attributes['subtitle'] ?? '';
$limit      = max(1, min(12, (int) ($attributes['limit'] ?? 6)));
$past_days  = !empty($attributes['showReleased']) ? 7 : 0;
$empty_text = $attributes['emptyText'] ?? '';

$drops = ghb_drop_upcoming($limit, $past_days);
if (!$drops && '' === $empty_text) {
    return;
}

wp_enqueue_script('ghb-live-blocks');
$now = time();
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-drop-calendar')); ?>>
    <div class="gh-drop-calendar__inner">
        <div class="gh-drop-calendar__header" data-gh-reveal="up">
            <?php if ('' !== $eyebrow) : ?>
                <span class="gh-eyebrow gh-drop-calendar__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>
            <?php if ('' !== $title) : ?>
                <h2 class="gh-drop-calendar__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ('' !== $subtitle) : ?>
                <p class="gh-drop-calendar__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>

        <?php if (!$drops) : ?>
            <p class="gh-drop-calendar__empty"><?php echo esc_html($empty_text); ?></p>
        <?php else : ?>
            <ol class="gh-drop-calendar__list">
                <?php foreach ($drops as $index => $product) :
                    $ts       = ghb_drop_ts($product->get_id());
                    $released = $ts <= $now;
                    $event    = ghb_drop_event_for_product($product);
                    $delay    = $index * 80;
                    ?>
                    <li class="gh-drop-cal<?php echo $released ? ' is-live' : ''; ?>" data-ghb-drop="<?php echo (int) $ts; ?>"
                        data-gh-reveal="up" style="--gh-reveal-delay: <?php echo (int) $delay; ?>ms">
                        <div class="gh-drop-cal__date" aria-hidden="true">
                            <span class="gh-drop-cal__day"><?php echo esc_html(wp_date('j', $ts)); ?></span>
                            <span class="gh-drop-cal__month"><?php echo esc_html(wp_date('M', $ts)); ?></span>
                            <span class="gh-drop-cal__time"><?php echo esc_html(wp_date('D · H:i', $ts)); ?></span>
                        </div>

                        <a class="gh-drop-cal__media" href="<?php echo esc_url($product->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
                            <?php echo $product->get_image('woocommerce_thumbnail', array('loading' => 'lazy', 'alt' => '')); // WooCommerce image markup. ?>
                        </a>

                        <div class="gh-drop-cal__body">
                            <h3 class="gh-drop-cal__name">
                                <a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a>
                            </h3>
                            <p class="gh-drop-cal__when"><?php echo esc_html(ghb_drop_long_date($ts)); ?></p>
                            <?php if ('' !== $product->get_price_html()) : ?>
                                <p class="gh-drop-cal__price"><?php echo wp_kses_post($product->get_price_html()); ?></p>
                            <?php endif; ?>
                            <p class="gh-drop-cal__status" data-ghb-drop-status aria-live="off">
                                <?php echo $released ? esc_html__('Disponibile ora', 'golden-hive-blocks') : esc_html__('In arrivo', 'golden-hive-blocks'); ?>
                            </p>
                        </div>

                        <div class="gh-drop-cal__actions">
                            <div data-ghb-drop-before<?php echo $released ? ' hidden' : ''; ?>>
                                <?php echo ghb_drop_remind_html($event, ghb_drop_ics_url($event, $product->get_id()), 'ghb-remind--dark'); // Escaped inside. ?>
                            </div>
                            <div data-ghb-drop-after<?php echo $released ? '' : ' hidden'; ?>>
                                <a class="gh-drop-cal__buy" href="<?php echo esc_url($product->get_permalink()); ?>"><?php esc_html_e('Acquista ora', 'golden-hive-blocks'); ?></a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
