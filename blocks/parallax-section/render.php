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
<section class="gh-block gh-parallax-section"<?php echo $enable_mouse ? ' data-gh-mouse-parallax' : ''; ?>>
    <?php if (!empty($bg_image)) : ?>
        <div class="gh-parallax-section__layer gh-parallax-section__layer--bg" data-gh-mouse-layer="10">
            <img src="<?php echo esc_url($bg_image); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>

    <?php if (!empty($fg_image)) : ?>
        <div class="gh-parallax-section__layer gh-parallax-section__layer--mid" data-gh-mouse-layer="30">
            <img src="<?php echo esc_url($fg_image); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>

    <div class="gh-parallax-section__particles" aria-hidden="true">
        <?php for ($i = 0; $i < 20; $i++) : ?>
            <span class="gh-parallax-section__particle" style="
                left: <?php echo rand(0, 100); ?>%;
                animation-delay: <?php echo rand(0, 20); ?>s;
                animation-duration: <?php echo rand(15, 25); ?>s;
            "></span>
        <?php endfor; ?>
    </div>

    <div class="gh-parallax-section__content" data-gh-reveal="up">
        <?php if (!empty($title)) : ?>
            <h2 class="gh-parallax-section__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if (!empty($text)) : ?>
            <p class="gh-parallax-section__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <?php if (!empty($button_url) && !empty($button_text)) : ?>
            <a href="<?php echo esc_url($button_url); ?>" class="gh-btn gh-btn--primary gh-btn--large" data-gh-magnetic="0.2">
                <?php echo esc_html($button_text); ?>
            </a>
        <?php endif; ?>
    </div>
</section>
