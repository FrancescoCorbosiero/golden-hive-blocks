<?php
/**
 * Store Hours Block - Render lato server
 */

$title = $attributes['title'] ?? 'Orari di Apertura';
$hours = $attributes['hours'] ?? [];
$note  = $attributes['note'] ?? '';

if (empty($hours)) {
    return;
}
?>
<section class="gh-block gh-store-hours" style="
    background: var(--gh-gray-50, #f9fafb);
    padding: var(--gh-space-16, 4rem) var(--gh-space-6, 1.5rem);
">
    <div style="
        max-width: 640px;
        margin: 0 auto;
    ">
        <?php if (!empty($title)) : ?>
            <h2 data-gh-reveal="up" style="
                font-size: clamp(1.5rem, 3vw, 2rem);
                font-weight: 700;
                letter-spacing: -0.02em;
                color: var(--gh-gray-900, #111827);
                margin: 0 0 var(--gh-space-8, 2rem) 0;
                text-align: center;
            "><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <div style="
            background: var(--gh-white, #ffffff);
            border-radius: var(--gh-radius-lg, 12px);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        ">
            <?php foreach ($hours as $index => $entry) :
                $day  = $entry['day'] ?? '';
                $time = $entry['time'] ?? '';
                $is_closed = (strtolower(trim($time)) === 'chiuso');
                $is_last = ($index === count($hours) - 1);
            ?>
                <div data-gh-reveal="up" data-gh-reveal-delay="<?php echo $index * 50; ?>" style="
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: var(--gh-space-4, 1rem) var(--gh-space-6, 1.5rem);
                    <?php if (!$is_last) : ?>border-bottom: 1px solid var(--gh-gray-200, #e5e7eb);<?php endif; ?>
                ">
                    <span style="
                        font-weight: 600;
                        color: var(--gh-gray-900, #111827);
                        font-size: 0.95rem;
                    "><?php echo esc_html($day); ?></span>

                    <span style="
                        font-size: 0.9rem;
                        <?php if ($is_closed) : ?>
                            color: var(--gh-accent, #ef4444);
                            font-weight: 600;
                            text-transform: uppercase;
                            letter-spacing: 0.05em;
                            font-size: 0.8rem;
                        <?php else : ?>
                            color: var(--gh-gray-600, #4b5563);
                            font-weight: 400;
                        <?php endif; ?>
                    "><?php echo esc_html($time); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($note)) : ?>
            <p data-gh-reveal="up" data-gh-reveal-delay="<?php echo count($hours) * 50; ?>" style="
                margin: var(--gh-space-4, 1rem) 0 0 0;
                text-align: center;
                font-size: 0.8rem;
                color: var(--gh-gray-500, #6b7280);
                line-height: 1.5;
            "><?php echo esc_html($note); ?></p>
        <?php endif; ?>
    </div>
</section>
