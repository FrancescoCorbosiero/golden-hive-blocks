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

// The section's slider look: its own choice, else the Customizer's. Rails in
// the shortcode read it (ghb_rail_style()); the header follows it too.
$rail_style = in_array($attributes['railStyle'] ?? '', array('modern', 'classic'), true)
    ? $attributes['railStyle']
    : (function_exists('ghb_rail_style_default') ? ghb_rail_style_default() : 'modern');
$classic = 'classic' === $rail_style;

$has_header = !empty($eyebrow) || !empty($title);
$has_link = !empty($button_url) && !empty($button_text);
// Modern: the link sits in the header. Classic, or no header: the button below.
$link_in_header = $has_header && !$classic;
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-shortcode-wrapper gh-shortcode-wrapper--' . $rail_style, 'style' => $bg_class)); ?>>
    <?php if ($has_header) : ?>
        <?php /* Modern: title on the left, the section's link on the same line. */ ?>
        <div class="gh-shortcode-wrapper__header" data-gh-reveal="up">
            <div class="gh-shortcode-wrapper__titles">
                <?php if (!empty($eyebrow)) : ?>
                    <span class="gh-eyebrow gh-shortcode-wrapper__eyebrow"><?php echo esc_html($eyebrow); ?></span>
                <?php endif; ?>
                <?php if (!empty($title)) : ?>
                    <h2 class="gh-section-title gh-shortcode-wrapper__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
            </div>
            <?php if ($has_link && $link_in_header) : ?>
                <a class="gh-shortcode-wrapper__link" href="<?php echo esc_url($button_url); ?>"><?php echo esc_html($button_text); ?><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="gh-shortcode-wrapper__content">
        <?php
        /* Decisione documentata: il contenuto arriva solo dall'editor
           (utenti fidati) e i shortcode WooCommerce/CommerceKit devono
           passare intatti — do_shortcode resta invariato. */
        ghb_rail_style_context($rail_style);
        echo do_shortcode($shortcode);
        ghb_rail_style_context('');
        ?>
    </div>

    <?php if ($has_link && !$link_in_header) : ?>
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
