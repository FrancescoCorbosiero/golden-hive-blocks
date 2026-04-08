<?php
/**
 * About Hero Block - Render lato server
 * Sezione Chi Siamo con storia del brand, valori e immagine.
 */

$eyebrow   = $attributes['eyebrow'] ?? '';
$title     = $attributes['title'] ?? '';
$text      = $attributes['text'] ?? '';
$image_url = $attributes['imageUrl'] ?? '';
$values    = $attributes['values'] ?? [];
$reverse   = $attributes['reverse'] ?? false;

if (empty($title)) {
    return;
}

$reverse_class   = $reverse ? ' gh-about-hero--reverse' : '';
$content_reveal  = $reverse ? 'right' : 'left';
$image_reveal    = $reverse ? 'left' : 'right';
?>
<section class="gh-block gh-about-hero<?php echo esc_attr($reverse_class); ?>" style="
    color: var(--gh-black, #111);
    padding: var(--gh-space-20, 5rem) var(--gh-space-6, 1.5rem);
    overflow: hidden;
">
    <div style="
        max-width: var(--gh-max-width, 1280px);
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--gh-space-12, 3rem);
        align-items: center;
    ">

        <?php /* ---------- Text column ---------- */ ?>
        <div class="gh-about-hero__content" data-gh-reveal="<?php echo esc_attr($content_reveal); ?>" style="
            order: <?php echo $reverse ? '2' : '1'; ?>;
            display: flex;
            flex-direction: column;
            gap: var(--gh-space-6, 1.5rem);
        ">
            <?php if (!empty($eyebrow)) : ?>
                <span style="
                    font-size: 0.75rem;
                    font-weight: 700;
                    letter-spacing: 0.12em;
                    text-transform: uppercase;
                    color: var(--gh-accent, #d4a017);
                "><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>

            <h2 style="
                font-size: clamp(2rem, 4vw, 3.25rem);
                font-weight: 800;
                line-height: 1.1;
                margin: 0;
                color: var(--gh-black, #111);
            "><?php echo esc_html($title); ?></h2>

            <?php if (!empty($text)) : ?>
                <p style="
                    font-size: 1.125rem;
                    line-height: 1.7;
                    color: var(--gh-gray-600, #555);
                    margin: 0;
                    max-width: 540px;
                "><?php echo esc_html($text); ?></p>
            <?php endif; ?>

            <?php if (!empty($values)) : ?>
                <div style="
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: var(--gh-space-5, 1.25rem);
                    margin-top: var(--gh-space-4, 1rem);
                ">
                    <?php foreach ($values as $value) : ?>
                        <?php if (!empty($value['title'])) : ?>
                            <div style="
                                display: flex;
                                flex-direction: column;
                                gap: 0.25rem;
                            ">
                                <strong style="
                                    font-size: 1rem;
                                    font-weight: 700;
                                    color: var(--gh-black, #111);
                                "><?php echo esc_html($value['title']); ?></strong>
                                <?php if (!empty($value['text'])) : ?>
                                    <span style="
                                        font-size: 0.9375rem;
                                        line-height: 1.6;
                                        color: var(--gh-gray-600, #555);
                                    "><?php echo esc_html($value['text']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php /* ---------- Image column ---------- */ ?>
        <div class="gh-about-hero__visual" data-gh-reveal="<?php echo esc_attr($image_reveal); ?>" style="
            order: <?php echo $reverse ? '1' : '2'; ?>;
            position: relative;
            border-radius: var(--gh-radius-lg, 1rem);
            overflow: hidden;
            min-height: 480px;
        ">
            <?php if (!empty($image_url)) : ?>
                <img
                    src="<?php echo esc_url($image_url); ?>"
                    alt=""
                    loading="lazy"
                    style="
                        display: block;
                        width: 100%;
                        height: 100%;
                        min-height: 480px;
                        object-fit: cover;
                        border-radius: var(--gh-radius-lg, 1rem);
                    "
                >
            <?php endif; ?>
        </div>

    </div>

    <?php /* ---------- Responsive: stack on mobile ---------- */ ?>
    <style>
        @media (max-width: 768px) {
            .gh-about-hero > div {
                grid-template-columns: 1fr !important;
            }
            .gh-about-hero__content,
            .gh-about-hero__visual {
                order: unset !important;
            }
            .gh-about-hero__visual {
                min-height: 320px !important;
            }
            .gh-about-hero__visual img {
                min-height: 320px !important;
            }
        }
    </style>
</section>
