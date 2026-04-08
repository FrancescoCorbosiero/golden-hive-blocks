<?php
/**
 * Brand Marquee Block - Render lato server
 */

$title = $attributes['title'] ?? 'I Nostri Brand';
$brands = $attributes['brands'] ?? [];
$speed = $attributes['speed'] ?? 50;
$direction = $attributes['direction'] ?? 'left';
$pause_on_hover = $attributes['pauseOnHover'] ?? true;

if (empty($brands)) {
    return;
}
?>
<section class="gh-block gh-brand-marquee">
    <?php if (!empty($title)) : ?>
        <div class="gh-brand-marquee__header">
            <span class="gh-brand-marquee__title"><?php echo esc_html($title); ?></span>
        </div>
    <?php endif; ?>

    <div class="gh-brand-marquee__track-wrapper"
         data-gh-marquee
         data-gh-marquee-speed="<?php echo esc_attr($speed); ?>"
         data-gh-marquee-direction="<?php echo esc_attr($direction); ?>"
         data-gh-marquee-pause="<?php echo $pause_on_hover ? 'true' : 'false'; ?>">

        <div class="gh-brand-marquee__track" data-gh-marquee-track>
            <?php foreach ($brands as $brand) : ?>
                <?php if (!empty($brand['logo'])) : ?>
                    <a href="<?php echo !empty($brand['url']) ? esc_url($brand['url']) : '#'; ?>"
                       class="gh-brand-marquee__item"
                       <?php echo !empty($brand['url']) ? '' : 'aria-disabled="true"'; ?>>
                        <img src="<?php echo esc_url($brand['logo']); ?>"
                             alt="<?php echo esc_attr($brand['name'] ?? 'Brand'); ?>"
                             loading="lazy">
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
