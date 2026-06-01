<?php
/**
 * Category Slider Block — Render lato server.
 *
 * Shopify-style horizontal category showcase. Uses native CSS scroll-snap
 * (no slider library) — the Swiper dependency was removed for performance.
 * Reuses the existing .gh-category-slider__item/__image-wrapper/__image/__name
 * styles; the scroll-snap layout + arrow behaviour are printed once per page.
 *
 * Note: the legacy autoplay/loop/dots options no longer apply (they were
 * Swiper features); the rail scrolls/snaps natively with optional arrows.
 */

$title = $attributes['title'] ?? '';
$categories = $attributes['categories'] ?? [];
$show_nav = $attributes['showNav'] ?? true;
$image_ratio = $attributes['imageRatio'] ?? '4 / 5';
$padding_top = $attributes['paddingTop'] ?? 40;
$padding_bottom = $attributes['paddingBottom'] ?? 40;

if (empty($categories)) {
    return;
}

$block_id = 'gh-cat-slider-' . wp_unique_id();
$section_style = sprintf(
    'padding-top: %dpx; padding-bottom: %dpx;',
    absint($padding_top),
    absint($padding_bottom)
);

// Scroll-snap layout + arrow behaviour — printed once per page (any number of
// category sliders share it).
if (empty($GLOBALS['gh_cs_assets_done'])) {
    $GLOBALS['gh_cs_assets_done'] = true;
    ?>
    <style>
    .gh-cs{position:relative}
    .gh-cs__track{display:flex;gap:16px;margin:0;padding:0 2px 8px;list-style:none;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none}
    .gh-cs__track::-webkit-scrollbar{display:none}
    .gh-cs__slide{flex:0 0 auto;width:clamp(140px,42vw,220px);margin:0;scroll-snap-align:start}
    .gh-cs__nav{position:absolute;top:42%;transform:translateY(-50%);z-index:5;width:40px;height:40px;border-radius:50%;border:1px solid #e2e2e6;background:#fff;color:#1f2532;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,.1);transition:border-color .15s,color .15s,opacity .15s}
    .gh-cs__nav:hover{border-color:#721124;color:#721124}
    .gh-cs__nav[disabled]{opacity:.35;cursor:default}
    .gh-cs__nav--prev{left:6px}.gh-cs__nav--next{right:6px}
    .gh-cs:not(.is-scrollable) .gh-cs__nav{display:none}
    @media(max-width:767px){.gh-cs__nav{display:none}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.gh-cs').forEach(function (rail) {
            var track = rail.querySelector('.gh-cs__track');
            if (!track) return;
            var navs = rail.querySelectorAll('.gh-cs__nav');
            function step() {
                var c = track.querySelector('.gh-cs__slide');
                return c ? c.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
            }
            function update() {
                var max = track.scrollWidth - track.clientWidth - 1;
                rail.classList.toggle('is-scrollable', max > 1);
                navs.forEach(function (n) {
                    var d = parseInt(n.getAttribute('data-dir'), 10);
                    n.disabled = (d < 0 && track.scrollLeft <= 1) || (d > 0 && track.scrollLeft >= max);
                });
            }
            navs.forEach(function (n) {
                n.addEventListener('click', function () {
                    track.scrollBy({ left: parseInt(n.getAttribute('data-dir'), 10) * step() * 2, behavior: 'smooth' });
                });
            });
            track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
            window.addEventListener('resize', update);
            window.addEventListener('load', update);
            update();
        });
    });
    </script>
    <?php
}
?>
<section class="gh-block gh-category-slider" id="<?php echo esc_attr($block_id); ?>" style="<?php echo esc_attr($section_style); ?>">
    <div class="gh-category-slider__container">
        <?php if (!empty($title)) : ?>
            <header class="gh-category-slider__header">
                <h2 class="gh-category-slider__title"><?php echo esc_html($title); ?></h2>
            </header>
        <?php endif; ?>

        <div class="gh-cs">
            <?php if ($show_nav) : ?>
                <button type="button" class="gh-cs__nav gh-cs__nav--prev" data-dir="-1" aria-label="Precedente">&lsaquo;</button>
            <?php endif; ?>

            <ul class="gh-cs__track">
                <?php foreach ($categories as $index => $category) : ?>
                    <li class="gh-cs__slide">
                        <a href="<?php echo esc_url($category['url'] ?? '#'); ?>" class="gh-category-slider__item">
                            <div class="gh-category-slider__image-wrapper" style="aspect-ratio: <?php echo esc_attr($image_ratio); ?>">
                                <?php if (!empty($category['image'])) : ?>
                                    <img src="<?php echo esc_url($category['image']); ?>"
                                         alt="<?php echo esc_attr($category['name'] ?? ''); ?>"
                                         class="gh-category-slider__image"
                                         loading="lazy"
                                         decoding="async">
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($category['name'])) : ?>
                                <p class="gh-category-slider__name"><?php echo esc_html($category['name']); ?></p>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($show_nav) : ?>
                <button type="button" class="gh-cs__nav gh-cs__nav--next" data-dir="1" aria-label="Successivo">&rsaquo;</button>
            <?php endif; ?>
        </div>
    </div>
</section>
