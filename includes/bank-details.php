<?php
/**
 * Bank details drawer for the checkout ("coordinate bancarie" / bonifico).
 *
 * A trigger link under the payment methods opens a slide-in drawer with the
 * store's bank details and one-tap copy for IBAN/BIC. Open/close, ESC,
 * backdrop click, focus trap, aria-hidden and scroll lock all come from the
 * plugin's shared modal engine (js/animations.js, data-gh-modal contract) —
 * this file only renders markup and enqueues the drawer's own CSS + copy JS,
 * on the checkout page alone.
 *
 * Data source: WooCommerce's own BACS gateway accounts (Impostazioni →
 * Pagamenti → Bonifico bancario) so the IBAN lives in ONE editable place.
 * Falls back to the `ghb_bank_details_account` filter when no BACS account
 * is configured; renders nothing when neither provides an IBAN.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve the account to display.
 *
 * @return array{account_name:string,bank_name:string,iban:string,bic:string,sort_code:string}
 */
function ghb_bank_details_get_account()
{
    $account = array(
        'account_name' => '',
        'bank_name'    => '',
        'iban'         => '',
        'bic'          => '',
        'sort_code'    => '',
    );

    // WooCommerce BACS gateway accounts (first one with an IBAN wins).
    $bacs = get_option('woocommerce_bacs_accounts');
    if (is_array($bacs)) {
        foreach ($bacs as $row) {
            if (!empty($row['iban'])) {
                $account['account_name'] = (string) ($row['account_name'] ?? '');
                $account['bank_name']    = (string) ($row['bank_name'] ?? '');
                $account['iban']         = (string) $row['iban'];
                $account['bic']          = (string) ($row['bic'] ?? '');
                $account['sort_code']    = (string) ($row['sort_code'] ?? '');
                break;
            }
        }
    }

    /**
     * Override/provide the displayed account. Return an array with the keys
     * account_name, bank_name, iban, bic, sort_code.
     */
    return apply_filters('ghb_bank_details_account', $account);
}

/**
 * Whether the drawer should exist on this request.
 */
function ghb_bank_details_should_load()
{
    $load = function_exists('is_checkout')
        && is_checkout()
        && ghb_bank_details_get_account()['iban'] !== '';

    return (bool) apply_filters('ghb_bank_details_enabled', $load);
}

/**
 * Checkout-only assets.
 */
function ghb_bank_details_assets()
{
    if (!ghb_bank_details_should_load()) {
        return;
    }

    wp_enqueue_style(
        'ghb-bank-details',
        gh_asset_url('bank-details.css'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION
    );

    wp_enqueue_script(
        'ghb-bank-details',
        gh_asset_url('js/bank-details.js'),
        array(),
        GOLDEN_HIVE_BLOCKS_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
}
add_action('wp_enqueue_scripts', 'ghb_bank_details_assets');

/**
 * Trigger link — rendered under the payment methods on the classic checkout.
 * Block-checkout themes can place it anywhere with [gh_bank_details].
 */
function ghb_bank_details_trigger()
{
    if (!ghb_bank_details_should_load()) {
        return '';
    }

    return '<button type="button" class="gh-bank-trigger" data-gh-modal-trigger="gh-bank-details">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>'
        . '</svg>'
        . esc_html__('Mostra coordinate bancarie per il bonifico', 'golden-hive-blocks')
        . '</button>';
}

add_action('woocommerce_review_order_after_payment', function () {
    echo ghb_bank_details_trigger(); // Escaped piecewise in the builder.
});

add_shortcode('gh_bank_details', 'ghb_bank_details_trigger');

/**
 * Drawer markup — printed once in the footer, hidden until opened.
 */
function ghb_bank_details_drawer()
{
    if (!ghb_bank_details_should_load()) {
        return;
    }

    $account = ghb_bank_details_get_account();

    // label / value / copyable / value id / text row (no monospace).
    $rows = array(
        array(__('Intestatario del conto', 'golden-hive-blocks'), $account['account_name'], false, '', true),
        array(__('Banca', 'golden-hive-blocks'), $account['bank_name'], false, '', true),
        array(__('IBAN', 'golden-hive-blocks'), $account['iban'], true, 'gh-bank-iban', false),
        array(__('Codice SWIFT / BIC', 'golden-hive-blocks'), $account['bic'], true, 'gh-bank-bic', false),
        array(__('Codice filiale', 'golden-hive-blocks'), $account['sort_code'], false, '', false),
    );
    ?>
<div id="gh-bank-details" class="gh-bank-drawer" data-gh-modal="gh-bank-details"
     role="dialog" aria-modal="true" aria-hidden="true"
     aria-label="<?php esc_attr_e('Coordinate bancarie', 'golden-hive-blocks'); ?>">
    <div class="gh-bank-drawer__panel">
        <div class="gh-bank-drawer__header">
            <h3 class="gh-bank-drawer__title"><?php esc_html_e('Coordinate bancarie', 'golden-hive-blocks'); ?></h3>
            <button type="button" class="gh-bank-drawer__close" data-gh-modal-close
                    aria-label="<?php esc_attr_e('Chiudi', 'golden-hive-blocks'); ?>">
                <?php echo gh_icon('close', 20); ?>
            </button>
        </div>

        <p class="gh-bank-drawer__intro">
            <?php esc_html_e('Effettua il pagamento tramite bonifico bancario inserendo come causale il numero del tuo ordine. L\'ordine verrà elaborato una volta ricevuto l\'accredito.', 'golden-hive-blocks'); ?>
        </p>

        <?php foreach ($rows as $row) :
            list($label, $value, $copyable, $value_id, $is_text) = $row;
            if ($value === '') {
                continue;
            }
            ?>
            <div class="gh-bank-row<?php echo $is_text ? ' gh-bank-row--text' : ''; ?>">
                <span class="gh-bank-row__label"><?php echo esc_html($label); ?></span>
                <div class="gh-bank-row__box">
                    <span class="gh-bank-row__value"<?php echo $value_id ? ' id="' . esc_attr($value_id) . '"' : ''; ?>><?php echo esc_html($value); ?></span>
                    <?php if ($copyable) : ?>
                        <button type="button" class="gh-bank-row__copy" data-gh-copy="<?php echo esc_attr($value_id); ?>">
                            <?php esc_html_e('Copia', 'golden-hive-blocks'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php /* Announces "IBAN copiato" & co. to screen readers. */ ?>
        <span class="gh-sr-only" role="status" aria-live="polite" data-gh-copy-status></span>

        <p class="gh-bank-drawer__footer">
            <?php esc_html_e('I tempi di accredito variano generalmente da 1 a 3 giorni lavorativi a seconda dell\'istituto bancario.', 'golden-hive-blocks'); ?>
        </p>
    </div>
</div>
    <?php
}
add_action('wp_footer', 'ghb_bank_details_drawer', 60);
