<?php
/**
 * Shortcode Wrapper Block - Render lato server
 */

$eyebrow = $attributes['eyebrow'] ?? '';
$title = $attributes['title'] ?? '';
$shortcode = $attributes['shortcode'] ?? '';
$bg_color = $attributes['backgroundColor'] ?? 'white';
$button_text = $attributes['buttonText'] ?? '';
$button_url = $attributes['buttonUrl'] ?? '';

// Spenta dalla Vetrina dello Store Hub: la sezione resta nella pagina, con i
// suoi prodotti e il loro ordine, ma sul sito non mostra nulla.
if (empty($shortcode) || !empty($attributes['hidden'])) {
    return;
}

$bg_class = '';
switch ($bg_color) {
    case 'gray':
        $bg_class = 'background: var(--gh-gray-50);';
        break;
    case 'black':
        $bg_class = 'background: var(--gh-black); color: var(--gh-white);';
        break;
    default:
        $bg_class = 'background: var(--gh-white);';
}

$has_header = !empty($eyebrow) || !empty($title);
$has_link = !empty($button_url) && !empty($button_text);
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-shortcode-wrapper', 'style' => $bg_class)); ?>>
    <?php if ($has_header) : ?>
        <?php /* Title on the left, the section's link on the same line. */ ?>
        <div class="gh-shortcode-wrapper__header" data-gh-reveal="up">
            <div class="gh-shortcode-wrapper__titles">
                <?php if (!empty($eyebrow)) : ?>
                    <span class="gh-eyebrow gh-shortcode-wrapper__eyebrow"><?php echo esc_html($eyebrow); ?></span>
                <?php endif; ?>
                <?php if (!empty($title)) : ?>
                    <h2 class="gh-section-title gh-shortcode-wrapper__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
            </div>
            <?php if ($has_link) : ?>
                <a class="gh-shortcode-wrapper__link" href="<?php echo esc_url($button_url); ?>"><?php echo esc_html($button_text); ?><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="gh-shortcode-wrapper__content">
        <?php
        /* Decisione documentata: il contenuto arriva solo dall'editor
           (utenti fidati) e i shortcode WooCommerce/CommerceKit devono
           passare intatti — do_shortcode resta invariato. */
        echo do_shortcode($shortcode);
        ?>
    </div>

    <?php if ($has_link && !$has_header) : ?>
        <div class="gh-shortcode-wrapper__cta">
            <?php
            echo gh_button(array(
                'url'     => $button_url,
                'text'    => $button_text,
                'classes' => 'gh-btn gh-btn--primary gh-btn--large',
            ));
            ?>
        </div>
    <?php endif; ?>
</section>
