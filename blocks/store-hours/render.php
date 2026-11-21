<?php
/**
 * Store Hours block — server render.
 *
 * Styles live in style.css (.gh-store-hours). Today's row is marked on the
 * server (current_time('N')) and corrected in the browser by
 * js/live-blocks.js (StoreHours), which also adds "Aperto ora · chiude alle
 * 19:30": behind a page cache the page never stays on the day it was built.
 * Italian holidays and closed dates included.
 *
 * Hours, note and closures left out of the block come from the store
 * (Customizer → Golden Hive → Negozio, includes/store.php); written, they are
 * this page's (customer-service hours, say).
 */

$ghb_block = 'golden-hive/store-hours';
$title     = $attributes['title'] ?? 'Orari di Apertura';
$hours     = (array) ghb_store_attr($attributes, $ghb_block, 'hours');
$note      = (string) ghb_store_attr($attributes, $ghb_block, 'note');

if (empty($hours)) {
    return;
}

$wrapper_attributes = get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-store-hours',
));
$hours_data = gh_hours_data($hours, (bool) ghb_store_attr($attributes, $ghb_block, 'closedOnHolidays'), (string) ghb_store_attr($attributes, $ghb_block, 'closedDates'));

// 1 (Monday) … 7 (Sunday), in WordPress's time zone.
$weekday_names = array(
    1 => 'lunedì',
    2 => 'martedì',
    3 => 'mercoledì',
    4 => 'giovedì',
    5 => 'venerdì',
    6 => 'sabato',
    7 => 'domenica',
);
$today_name = $weekday_names[(int) current_time('N')] ?? '';
?>
<section <?php echo $wrapper_attributes; ?> data-ghb-hours="<?php echo esc_attr($hours_data); ?>">
    <div class="gh-store-hours__container">
        <?php if (!empty($title)) : ?>
            <h2 class="gh-store-hours__title" data-gh-split="lines"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <p class="gh-hours-status gh-store-hours__status" data-ghb-hours-status hidden>
            <span class="gh-hours-status__dot" aria-hidden="true"></span>
            <span data-ghb-hours-text></span>
        </p>

        <div class="gh-store-hours__card">
            <?php foreach ($hours as $entry) :
                $day  = $entry['day'] ?? '';
                $time = $entry['time'] ?? '';

                $is_closed = (strtolower(trim($time)) === 'chiuso');

                $day_norm = function_exists('mb_strtolower')
                    ? mb_strtolower(trim($day), 'UTF-8')
                    : strtolower(trim($day));
                $is_today = ($today_name !== '' && $day_norm === $today_name);

                $row_class = 'gh-store-hours__row' . ($is_today ? ' gh-store-hours__row--today' : '');

                $time_class = 'gh-store-hours__time' . ($is_closed ? ' gh-store-hours__time--closed' : '');
            ?>
                <div class="<?php echo esc_attr($row_class); ?>"
                     data-ghb-day="<?php echo esc_attr($day); ?>"
                     data-gh-reveal="up">
                    <span class="gh-store-hours__day">
                        <?php echo esc_html($day); ?>
                        <span class="gh-store-hours__badge" data-ghb-today-badge<?php echo $is_today ? '' : ' hidden'; ?>>Oggi</span>
                    </span>

                    <span class="<?php echo esc_attr($time_class); ?>"><?php echo esc_html($time); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($note)) : ?>
            <p class="gh-store-hours__note"
               data-gh-reveal="up"><?php echo esc_html($note); ?></p>
        <?php endif; ?>
    </div>
</section>
