<?php
/**
 * Newsletter signup endpoint.
 *
 * The newsletter block's JS used to *simulate* a successful subscription
 * while sending nothing anywhere. This registers a real AJAX endpoint that
 * stores signups as a private CPT (visible under Iscritti newsletter in the
 * admin) and fires `ghb_newsletter_subscribed` so an ESP integration
 * (Mailchimp, Brevo, …) can hook in without touching the plugin.
 *
 * Front-end contract (emitted by blocks/newsletter/render.php, consumed by
 * js/animations.js):
 *   data-gh-newsletter-url   = admin-ajax.php URL
 *   data-gh-newsletter-nonce = wp_create_nonce('ghb_newsletter')
 *   POST { action: 'ghb_newsletter_subscribe', nonce, email }
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Private CPT that stores signups (title = email).
 */
function ghb_newsletter_register_cpt()
{
    register_post_type('ghb_subscriber', array(
        'labels' => array(
            'name'          => __('Iscritti newsletter', 'golden-hive-blocks'),
            'singular_name' => __('Iscritto newsletter', 'golden-hive-blocks'),
        ),
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'tools.php',
        'supports'        => array('title'),
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ));
}
add_action('init', 'ghb_newsletter_register_cpt');

/**
 * AJAX handler: validate, rate-limit, dedupe, store, hand off.
 */
function ghb_newsletter_subscribe_handler()
{
    /*
     * Niente hard-fail sul nonce: il nonce è stampato nell'HTML e con una
     * page cache più vecchia della sua vita (12-24h) OGNI iscrizione di un
     * visitatore anonimo fallirebbe in silenzio. Il CSRF su una iscrizione
     * newsletter è a impatto trascurabile (stesso ragionamento del wc-ajax
     * add_to_cart nonce-less di WooCommerce); le vere protezioni qui sono
     * il rate limit per IP e la validazione dell'email. Il nonce resta nel
     * markup e nel POST per forward-compat.
     */

    // Cheap per-IP rate limit: 5 attempts per hour.
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $key = 'ghb_nl_' . md5($ip);
    $attempts = (int) get_transient($key);
    if ($attempts >= 5) {
        wp_send_json_error(
            array('message' => __('Troppi tentativi — riprova più tardi.', 'golden-hive-blocks')),
            429
        );
    }
    set_transient($key, $attempts + 1, HOUR_IN_SECONDS);

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    if (!is_email($email)) {
        wp_send_json_error(
            array('message' => __('Inserisci un indirizzo email valido.', 'golden-hive-blocks')),
            400
        );
    }

    $existing = get_posts(array(
        'post_type'      => 'ghb_subscriber',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_key'       => '_ghb_email',
        'meta_value'     => $email,
    ));

    if (!empty($existing)) {
        wp_send_json_success(
            array('message' => __('Sei già iscritto alla newsletter!', 'golden-hive-blocks'))
        );
    }

    $post_id = wp_insert_post(array(
        'post_type'   => 'ghb_subscriber',
        'post_status' => 'private',
        'post_title'  => $email,
        'meta_input'  => array('_ghb_email' => $email),
    ), true);

    if (is_wp_error($post_id) || !$post_id) {
        wp_send_json_error(
            array('message' => __('Errore imprevisto — riprova più tardi.', 'golden-hive-blocks')),
            500
        );
    }

    /**
     * Fired after a signup is stored. Hook an ESP here.
     *
     * @param string $email   The subscriber email.
     * @param int    $post_id The ghb_subscriber post ID.
     */
    do_action('ghb_newsletter_subscribed', $email, $post_id);

    wp_send_json_success(
        array('message' => __('Iscrizione completata! Grazie, a presto.', 'golden-hive-blocks'))
    );
}
add_action('wp_ajax_ghb_newsletter_subscribe', 'ghb_newsletter_subscribe_handler');
add_action('wp_ajax_nopriv_ghb_newsletter_subscribe', 'ghb_newsletter_subscribe_handler');
