<?php
/**
 * About Hero Block - Render lato server
 * Sezione Chi Siamo con storia del brand, valori e immagine.
 *
 * Gli stili vivono in style.css (.gh-about-hero); qui restano solo il
 * markup, gli attributi data-gh-reveal per la coreografia d'entrata e
 * l'immagine via gh_img() (srcset/dimensioni dall'ID quando disponibile).
 */

$eyebrow   = $attributes['eyebrow'] ?? '';
$title     = $attributes['title'] ?? '';
$text      = $attributes['text'] ?? '';
$image_url = $attributes['imageUrl'] ?? '';
$image_id  = (int) ($attributes['imageUrlId'] ?? 0);
$values    = $attributes['values'] ?? [];
$reverse   = $attributes['reverse'] ?? false;

if (empty($title)) {
    return;
}

$reverse_class  = $reverse ? ' gh-about-hero--reverse' : '';
$content_reveal = $reverse ? 'right' : 'left';
$image_reveal   = $reverse ? 'left' : 'right';

$wrapper_attributes = get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-about-hero' . $reverse_class,
));

// Delay progressivo della lista valori: parte dopo eyebrow/titolo/testo.
$value_delay_base = 300;
$value_delay_step = 100;
?>
<section <?php echo $wrapper_attributes; ?>>
    <div class="gh-about-hero__inner">

        <?php /* ---------- Colonna testo ---------- */ ?>
        <div class="gh-about-hero__content" data-gh-reveal="<?php echo esc_attr($content_reveal); ?>">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-eyebrow gh-about-hero__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>

            <h2 class="gh-section-title gh-about-hero__title"><?php echo esc_html($title); ?></h2>

            <?php if (!empty($text)) : ?>
                <p class="gh-about-hero__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>

            <?php if (!empty($values)) : ?>
                <div class="gh-about-hero__values">
                    <?php foreach ($values as $index => $value) : ?>
                        <?php
                        if (empty($value['title'])) {
                            continue;
                        }
                        $value_delay = $value_delay_base + ($index * $value_delay_step);
                        ?>
                        <div class="gh-about-hero__value"
                             data-gh-reveal="up"
                             data-gh-reveal-delay="<?php echo (int) $value_delay; ?>"
                             style="--gh-reveal-delay: <?php echo (int) $value_delay; ?>ms">
                            <span class="gh-icon-circle gh-about-hero__value-icon" aria-hidden="true">
                                <?php echo gh_icon('check', 18); ?>
                            </span>
                            <div class="gh-about-hero__value-body">
                                <strong class="gh-about-hero__value-title"><?php echo esc_html($value['title']); ?></strong>
                                <?php if (!empty($value['text'])) : ?>
                                    <span class="gh-about-hero__value-text"><?php echo esc_html($value['text']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php /* ---------- Colonna immagine ---------- */ ?>
        <div class="gh-about-hero__visual"
             data-gh-reveal="<?php echo esc_attr($image_reveal); ?>"
             data-gh-reveal-delay="150"
             style="--gh-reveal-delay: 150ms"
             <?php echo (!empty($image_url) || $image_id) ? 'data-gh-image-reveal' : ''; ?>>
            <?php echo gh_img($image_id, $image_url, array(
                'alt'     => '',
                'loading' => 'lazy',
                'class'   => 'gh-about-hero__image',
            )); ?>
        </div>

    </div>
</section>
