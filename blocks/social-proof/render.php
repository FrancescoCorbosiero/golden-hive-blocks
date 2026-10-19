<?php
/**
 * Social Proof Block - Notifica acquisto stile Shopify, da ordini reali.
 *
 * Il blocco stampa solo il contenitore: js/animations.js (SocialProof)
 * chiede gli ultimi ordini pagati all'endpoint ghb_social_proof
 * (includes/social-proof.php), così anche una pagina in cache mostra gli
 * acquisti di oggi, e calcola "2 ore fa" nel browser dall'orario del
 * pagamento. Le notifiche scritte a mano delle versioni precedenti
 * (attributo notifications) non vengono più mostrate.
 */

$days              = max(1, min(30, (int) ($attributes['days'] ?? 14)));
$limit             = max(1, min(20, (int) ($attributes['limit'] ?? 10)));
$show_city         = !empty($attributes['showCity']);
$max_per_visit     = max(1, min(10, (int) ($attributes['maxPerVisit'] ?? 3)));
$interval          = (int) ($attributes['interval'] ?? 25000);
$initial_delay     = (int) ($attributes['initialDelay'] ?? 8000);
$display_duration  = (int) ($attributes['displayDuration'] ?? 6000);
$show_verified     = $attributes['showVerified'] ?? true;
$title             = $attributes['title'] ?? 'Qualcuno ha appena acquistato';

$src = add_query_arg(
    array(
        'action' => 'ghb_social_proof',
        'days'   => $days,
        'limit'  => $limit,
        'city'   => $show_city ? 1 : 0,
    ),
    admin_url('admin-ajax.php')
);
?>
<div <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-social-proof')); ?>
     data-gh-social-proof
     data-gh-social-proof-src="<?php echo esc_url($src); ?>"
     data-gh-social-proof-days="<?php echo esc_attr($days); ?>"
     data-gh-social-proof-max="<?php echo esc_attr($max_per_visit); ?>"
     data-gh-social-proof-interval="<?php echo esc_attr($interval); ?>"
     data-gh-social-proof-delay="<?php echo esc_attr($initial_delay); ?>"
     data-gh-social-proof-duration="<?php echo esc_attr($display_duration); ?>"
     aria-live="polite"
     aria-atomic="true">

    <a class="gh-social-proof__link" href="#" data-gh-social-proof-link hidden></a>

    <div class="gh-social-proof__image">
        <img src="" alt="" data-gh-social-proof-image>
    </div>

    <div class="gh-social-proof__body">
        <p class="gh-social-proof__text" data-gh-social-proof-text>
            <?php echo esc_html($title); ?> <strong data-gh-social-proof-product></strong>
        </p>
        <p class="gh-social-proof__meta" data-gh-social-proof-meta></p>
        <?php if ($show_verified) : ?>
        <span class="gh-social-proof__verified">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
            </svg>
            Acquisto verificato
        </span>
        <?php endif; ?>
    </div>

    <button class="gh-social-proof__close" data-gh-social-proof-close aria-label="Chiudi notifica">
        <?php echo gh_icon('close'); ?>
    </button>
</div>
