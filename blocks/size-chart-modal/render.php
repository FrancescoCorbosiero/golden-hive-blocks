<?php
/**
 * Size Chart Modal Block - Render lato server
 */

$modal_id = $attributes['modalId'] ?? 'size-chart';
$title = $attributes['title'] ?? 'Guida Taglie';
$description = $attributes['description'] ?? 'Trova la tua taglia perfetta';
$trigger_text = $attributes['triggerText'] ?? 'Guida Taglie';
$show_trigger = $attributes['showTrigger'] ?? true;

$sizes = [
    ['us' => '6', 'uk' => '5.5', 'eu' => '38.5', 'cm' => '24'],
    ['us' => '6.5', 'uk' => '6', 'eu' => '39', 'cm' => '24.5'],
    ['us' => '7', 'uk' => '6', 'eu' => '40', 'cm' => '25'],
    ['us' => '7.5', 'uk' => '6.5', 'eu' => '40.5', 'cm' => '25.5'],
    ['us' => '8', 'uk' => '7', 'eu' => '41', 'cm' => '26'],
    ['us' => '8.5', 'uk' => '7.5', 'eu' => '42', 'cm' => '26.5'],
    ['us' => '9', 'uk' => '8', 'eu' => '42.5', 'cm' => '27'],
    ['us' => '9.5', 'uk' => '8.5', 'eu' => '43', 'cm' => '27.5'],
    ['us' => '10', 'uk' => '9', 'eu' => '44', 'cm' => '28'],
    ['us' => '10.5', 'uk' => '9.5', 'eu' => '44.5', 'cm' => '28.5'],
    ['us' => '11', 'uk' => '10', 'eu' => '45', 'cm' => '29'],
    ['us' => '12', 'uk' => '11', 'eu' => '46', 'cm' => '30'],
    ['us' => '13', 'uk' => '12', 'eu' => '47.5', 'cm' => '31'],
];
?>

<?php if ($show_trigger) : ?>
    <button type="button"
            class="gh-btn gh-btn--outline"
            data-gh-modal-trigger="<?php echo esc_attr($modal_id); ?>"
            style="border-color: var(--gh-gray-300); color: var(--gh-black);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
        <?php echo esc_html($trigger_text); ?>
    </button>
<?php endif; ?>

<div class="gh-modal" data-gh-modal="<?php echo esc_attr($modal_id); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">
    <div class="gh-modal__backdrop"></div>
    <div class="gh-modal__container">
        <div class="gh-modal__header">
            <h3 class="gh-modal__title" id="<?php echo esc_attr($modal_id); ?>-title"><?php echo esc_html($title); ?></h3>
            <button class="gh-modal__close" data-gh-modal-close aria-label="Chiudi">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="gh-modal__body">
            <?php if (!empty($description)) : ?>
                <p style="margin-bottom: var(--gh-space-xl); color: var(--gh-gray-600);"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <table class="gh-size-chart">
                <thead>
                    <tr>
                        <th>US</th>
                        <th>UK</th>
                        <th>EU</th>
                        <th>CM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sizes as $size) : ?>
                        <tr>
                            <td><?php echo esc_html($size['us']); ?></td>
                            <td><?php echo esc_html($size['uk']); ?></td>
                            <td><?php echo esc_html($size['eu']); ?></td>
                            <td><?php echo esc_html($size['cm']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: var(--gh-space-xl); font-size: var(--gh-text-sm); color: var(--gh-gray-500);">
                <strong>Consiglio:</strong> Se sei tra due taglie, ti consigliamo di scegliere la taglia più grande.
            </p>
        </div>
    </div>
</div>
