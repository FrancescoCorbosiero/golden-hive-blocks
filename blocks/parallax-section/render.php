<?php
/**
 * Parallax Section Block - Render lato server
 */

$bg_image = $attributes['backgroundImage'] ?? '';
$fg_image = $attributes['foregroundImage'] ?? '';
$title = $attributes['title'] ?? '';
$text = $attributes['text'] ?? '';
$button_text = $attributes['buttonText'] ?? '';
$button_url = $attributes['buttonUrl'] ?? '';
$enable_mouse = $attributes['enableMouseParallax'] ?? true;

if (empty($title) && empty($bg_image)) {
    return;
}
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-parallax-section')); ?><?php echo $enable_mouse ? ' data-gh-mouse-parallax' : ''; ?>>
    <?php if (!empty($bg_image)) : ?>
        <div class="gh-parallax-section__layer gh-parallax-section__layer--bg" data-gh-mouse-layer="10">
            <?php
            echo gh_img((int) ($attributes['backgroundImageId'] ?? 0), $bg_image, array(
                'alt'      => $title,
                'loading'  => 'lazy',
                'decoding' => 'async',
            ));
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fg_image)) : ?>
        <div class="gh-parallax-section__layer gh-parallax-section__layer--mid" data-gh-mouse-layer="30">
            <?php
            echo gh_img((int) ($attributes['foregroundImageId'] ?? 0), $fg_image, array(
                'alt'      => $title,
                'loading'  => 'lazy',
                'decoding' => 'async',
            ));
            ?>
        </div>
    <?php endif; ?>

    <div class="gh-parallax-section__particles" aria-hidden="true">
        <?php for ($i = 0; $i < 20; $i++) : ?>
            <?php
            // Posizioni deterministiche (derivate dall'indice): l'HTML resta
            // identico tra i render, quindi cache di pagina/ETag funzionano.
            $p_left     = ($i * 37) % 101;
            $p_delay    = ($i * 53) % 21;
            $p_duration = 15 + (($i * 29) % 11);
            ?>
            <span class="gh-parallax-section__particle" style="
                left: <?php echo (int) $p_left; ?>%;
                animation-delay: <?php echo (int) $p_delay; ?>s;
                animation-duration: <?php echo (int) $p_duration; ?>s;
            "></span>
        <?php endfor; ?>
    </div>

    <div class="gh-parallax-section__content" data-gh-reveal="up">
        <?php if (!empty($title)) : ?>
            <h2 class="gh-section-title gh-parallax-section__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if (!empty($text)) : ?>
            <p class="gh-parallax-section__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if (!empty($button_url) && !empty($button_text)) : ?>
            <?php
            echo gh_button(array(
                'url'      => $button_url,
                'text'     => $button_text,
                'classes'  => 'gh-btn gh-btn--primary gh-btn--large',
                'icon'     => '',
                'magnetic' => '0.2',
            ));
            ?>
        <?php endif; ?>
    </div>
</section>
