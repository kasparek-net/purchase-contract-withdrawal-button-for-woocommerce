<?php
/**
 * Guest withdrawal lookup form.
 *
 * Can be overridden by copying to
 * yourtheme/purchase-contract-withdrawal-button-for-woocommerce/guest-lookup.php
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 * @version 1.2.0
 *
 * @var string $error_message Sanitized error string (empty when no error).
 * @var string $order_number  Submitted order number on retry.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="pcwb-guest-lookup">
    <h2 class="pcwb-form-heading"><?php esc_html_e( 'Withdraw from purchase contract', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></h2>

    <p>
        <?php esc_html_e( 'Enter your order number and the email address used when placing the order to continue.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
    </p>

    <?php if ( $error_message !== '' ) : ?>
        <div class="woocommerce-error" role="alert">
            <?php echo esc_html( $error_message ); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="pcwb-form pcwb-guest-form">
        <?php wp_nonce_field( 'pcwb_guest_lookup', 'pcwb_guest_nonce' ); ?>
        <input type="hidden" name="pcwb_guest" value="lookup">

        <p class="pcwb-field">
            <label for="pcwb_guest_order">
                <strong><?php esc_html_e( 'Order number', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong>
            </label>
            <input type="text" id="pcwb_guest_order" name="order_number" value="<?php echo esc_attr( $order_number ); ?>" class="input-text" required>
        </p>

        <p class="pcwb-field">
            <label for="pcwb_guest_email">
                <strong><?php esc_html_e( 'Billing email', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong>
            </label>
            <input type="email" id="pcwb_guest_email" name="billing_email" class="input-text" required>
        </p>

        <p class="pcwb-actions">
            <button type="submit" class="button alt pcwb-submit-button">
                <?php esc_html_e( 'Continue', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
            </button>
        </p>
    </form>
</div>
