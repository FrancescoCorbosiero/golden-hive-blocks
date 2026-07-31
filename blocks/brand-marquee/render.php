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
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-brand-marquee')); ?>>
    <?php if (!empty($title)) : ?>
        <div class="gh-section-header gh-brand-marquee__header">
            <span class="gh-eyebrow gh-brand-marquee__title"><?php echo esc_html($title); ?></span>
        </div>
    <?php endif; ?>

    <div class="gh-brand-marquee__track-wrapper"
         data-gh-marquee
         data-gh-marquee-speed="<?php echo esc_attr($speed); ?>"
         data-gh-marquee-direction="<?php echo esc_attr($direction); ?>"
         <?php echo $pause_on_hover ? 'data-gh-marquee-pause="true"' : ''; ?>>

        <div class="gh-brand-marquee__track" data-gh-marquee-track>
            <?php foreach ($brands as $brand) : ?>
                <?php
                if (empty($brand['logo'])) {
                    continue;
                }
                $brand_img = gh_img(
                    (int) ($brand['logoId'] ?? 0),
                    $brand['logo'],
                    array(
                        'alt'     => $brand['name'] ?? 'Brand',
                        'loading' => 'lazy',
                    )
                );
                ?>
                <?php if (!empty($brand['url'])) : ?>
                    <a href="<?php echo esc_url($brand['url']); ?>"
                       class="gh-brand-marquee__item">
                        <?php echo $brand_img; ?>
                    </a>
                <?php else : ?>
                    <span class="gh-brand-marquee__item">
                        <?php echo $brand_img; ?>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
