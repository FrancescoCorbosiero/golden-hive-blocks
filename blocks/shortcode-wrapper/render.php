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

if (empty($shortcode)) {
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
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-shortcode-wrapper', 'style' => $bg_class)); ?>>
    <?php if (!empty($eyebrow) || !empty($title)) : ?>
        <div class="gh-shortcode-wrapper__header" data-gh-reveal="up">
            <?php if (!empty($eyebrow)) : ?>
                <span class="gh-eyebrow gh-shortcode-wrapper__eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>
            <?php if (!empty($title)) : ?>
                <h2 class="gh-section-title gh-shortcode-wrapper__title"><?php echo esc_html($title); ?></h2>
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

    <?php if (!empty($button_url) && !empty($button_text)) : ?>
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
