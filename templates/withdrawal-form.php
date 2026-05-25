<?php
/**
 * Withdrawal request form.
 *
 * This template can be overridden by copying it to
 * yourtheme/purchase-contract-withdrawal-button-for-woocommerce/withdrawal-form.php
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 * @version 1.2.0
 *
 * @var WC_Order $order
 * @var string   $back_url
 * @var int      $deadline Unix timestamp of the cooling-off deadline.
 * @var bool     $is_guest Optional. True when the form is rendered for a guest. Default false.
 * @var string   $guest_token Optional. Short-lived token issued during guest lookup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_guest    = ! empty( $is_guest );
$guest_token = isset( $guest_token ) ? (string) $guest_token : '';
?>
<h2 class="pcwb-form-heading"><?php esc_html_e( 'Withdrawal from purchase contract', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></h2>

<p>
    <?php
    printf(
        /* translators: 1: order number, 2: deadline date */
        esc_html__( 'Complete the form below to withdraw from purchase contract no. %1$s. The cooling-off period ends on %2$s.', 'purchase-contract-withdrawal-button-for-woocommerce' ),
        '<strong>' . esc_html( $order->get_order_number() ) . '</strong>',
        '<strong>' . esc_html( date_i18n( get_option( 'date_format' ), $deadline ) ) . '</strong>'
    );
    ?>
</p>

<form method="post" action="" class="pcwb-form<?php echo $is_guest ? ' pcwb-guest-form' : ''; ?>">
    <?php wp_nonce_field( 'pcwb_submit_' . $order->get_id(), 'pcwb_nonce' ); ?>
    <input type="hidden" name="pcwb_submit" value="1">
    <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
    <?php if ( $is_guest ) : ?>
        <input type="hidden" name="pcwb_guest" value="submit">
        <input type="hidden" name="pcwb_guest_token" value="<?php echo esc_attr( $guest_token ); ?>">
    <?php endif; ?>

    <table class="shop_table pcwb-order-summary">
        <tr>
            <th><?php esc_html_e( 'Order number', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( $order->get_order_number() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Order date', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Customer', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Address', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
            <td><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Email', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
            <td><?php echo esc_html( $order->get_billing_email() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Total', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
            <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
        </tr>
    </table>

    <p class="pcwb-field">
        <label for="pcwb_account">
            <strong><?php esc_html_e( 'Bank account for refund', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong>
            <?php esc_html_e( '(optional — leave empty to refund to the original payment method)', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
        </label>
        <input type="text" id="pcwb_account" name="refund_account" class="input-text">
    </p>

    <p class="pcwb-field">
        <label for="pcwb_reason">
            <strong><?php esc_html_e( 'Reason for withdrawal', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong>
            <?php esc_html_e( '(optional)', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
        </label>
        <textarea id="pcwb_reason" name="reason" rows="4"></textarea>
    </p>

    <p class="pcwb-confirm-box">
        <label>
            <input type="checkbox" name="confirm" value="1" required>
            <strong><?php esc_html_e( 'I confirm that I am withdrawing from the purchase contract', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong>
            <?php esc_html_e( 'and I understand that I must return the purchased goods within 14 days of submitting this withdrawal. The refund will be issued after the returned goods are received.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
        </label>
    </p>

    <p class="pcwb-actions">
        <button type="submit" class="button alt pcwb-submit-button">
            <?php esc_html_e( 'Confirm withdrawal', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
        </button>
        <a href="<?php echo esc_url( $back_url ); ?>" class="button pcwb-cancel-button">
            <?php esc_html_e( 'Cancel', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
        </a>
    </p>
</form>
