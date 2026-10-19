<?php
/**
 * Store Hours Block - Render lato server
 *
 * Gli stili vivono in style.css (.gh-store-hours). Il giorno corrente viene
 * evidenziato lato server (current_time('N')) e poi corretto nel browser da
 * js/live-blocks.js (StoreHours), che aggiunge anche "Aperto ora · chiude
 * alle 19:30": con una page cache la pagina non resta ferma al giorno in cui
 * è stata generata. Festività italiane e date di chiusura incluse.
 */

$title = $attributes['title'] ?? 'Orari di Apertura';
$hours = $attributes['hours'] ?? [];
$note  = $attributes['note'] ?? '';

if (empty($hours)) {
    return;
}

$wrapper_attributes = get_block_wrapper_attributes(array(
    'class' => 'gh-block gh-store-hours',
));
$hours_data = gh_hours_data($hours, $attributes['closedOnHolidays'] ?? true, $attributes['closedDates'] ?? '');

// 1 (lunedì) ... 7 (domenica), nel fuso orario di WordPress.
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
            <h2 class="gh-store-hours__title" data-gh-reveal="up"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <p class="gh-hours-status gh-store-hours__status" data-ghb-hours-status hidden>
            <span class="gh-hours-status__dot" aria-hidden="true"></span>
            <span data-ghb-hours-text></span>
        </p>

        <div class="gh-store-hours__card">
            <?php foreach ($hours as $index => $entry) :
                $day  = $entry['day'] ?? '';
                $time = $entry['time'] ?? '';

                $is_closed = (strtolower(trim($time)) === 'chiuso');

                $day_norm = function_exists('mb_strtolower')
                    ? mb_strtolower(trim($day), 'UTF-8')
                    : strtolower(trim($day));
                $is_today = ($today_name !== '' && $day_norm === $today_name);

                $row_class = 'gh-store-hours__row' . ($is_today ? ' gh-store-hours__row--today' : '');

                $time_class = 'gh-store-hours__time' . ($is_closed ? ' gh-store-hours__time--closed' : '');

                $delay = $index * 50;
            ?>
                <div class="<?php echo esc_attr($row_class); ?>"
                     data-ghb-day="<?php echo esc_attr($day); ?>"
                     data-gh-reveal="up"
                     data-gh-reveal-delay="<?php echo (int) $delay; ?>"
                     style="--gh-reveal-delay: <?php echo (int) $delay; ?>ms">
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
               data-gh-reveal="up"
               data-gh-reveal-delay="<?php echo (int) (count($hours) * 50); ?>"
               style="--gh-reveal-delay: <?php echo (int) (count($hours) * 50); ?>ms"><?php echo esc_html($note); ?></p>
        <?php endif; ?>
    </div>
</section>
