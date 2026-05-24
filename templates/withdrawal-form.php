<?php
/**
 * Withdrawal request form.
 *
 * This template can be overridden by copying it to
 * yourtheme/eucomply-withdrawal-button/withdrawal-form.php
 *
 * @package EUcomplyWithdrawalButton
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $back_url
 * @var int      $deadline Unix timestamp of the cooling-off deadline.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2 class="ewb-form-heading"><?php esc_html_e( 'Withdrawal from purchase contract', 'eucomply-withdrawal-button' ); ?></h2>

<p>
    <?php
    printf(
        /* translators: 1: order number, 2: deadline date */
        esc_html__( 'Complete the form below to withdraw from purchase contract no. %1$s. The cooling-off period ends on %2$s.', 'eucomply-withdrawal-button' ),
        '<strong>' . esc_html( $order->get_order_number() ) . '</strong>',
        '<strong>' . esc_html( date_i18n( get_option( 'date_format' ), $deadline ) ) . '</strong>'
    );
    ?>
</p>

<form method="post" action="" class="ewb-form">
    <?php wp_nonce_field( 'ewb_submit_' . $order->get_id(), 'ewb_nonce' ); ?>
    <input type="hidden" name="ewb_submit" value="1">
    <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

    <table class="shop_table ewb-order-summary">
        <tr>
            <th><?php esc_html_e( 'Order number', 'eucomply-withdrawal-button' ); ?></th>
            <td><?php echo esc_html( $order->get_order_number() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Order date', 'eucomply-withdrawal-button' ); ?></th>
            <td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Customer', 'eucomply-withdrawal-button' ); ?></th>
            <td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Address', 'eucomply-withdrawal-button' ); ?></th>
            <td><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Email', 'eucomply-withdrawal-button' ); ?></th>
            <td><?php echo esc_html( $order->get_billing_email() ); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Total', 'eucomply-withdrawal-button' ); ?></th>
            <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
        </tr>
    </table>

    <p class="ewb-field">
        <label for="ewb_account">
            <strong><?php esc_html_e( 'Bank account for refund', 'eucomply-withdrawal-button' ); ?></strong>
            <?php esc_html_e( '(optional — leave empty to refund to the original payment method)', 'eucomply-withdrawal-button' ); ?>
        </label>
        <input type="text" id="ewb_account" name="refund_account" class="input-text">
    </p>

    <p class="ewb-field">
        <label for="ewb_reason">
            <strong><?php esc_html_e( 'Reason for withdrawal', 'eucomply-withdrawal-button' ); ?></strong>
            <?php esc_html_e( '(optional)', 'eucomply-withdrawal-button' ); ?>
        </label>
        <textarea id="ewb_reason" name="reason" rows="4"></textarea>
    </p>

    <p class="ewb-confirm-box">
        <label>
            <input type="checkbox" name="confirm" value="1" required>
            <strong><?php esc_html_e( 'I confirm that I am withdrawing from the purchase contract', 'eucomply-withdrawal-button' ); ?></strong>
            <?php esc_html_e( 'and I understand that I must return the purchased goods within 14 days of submitting this withdrawal. The refund will be issued after the returned goods are received.', 'eucomply-withdrawal-button' ); ?>
        </label>
    </p>

    <p class="ewb-actions">
        <button type="submit" class="button alt ewb-submit-button">
            <?php esc_html_e( 'Confirm withdrawal', 'eucomply-withdrawal-button' ); ?>
        </button>
        <a href="<?php echo esc_url( $back_url ); ?>" class="button ewb-cancel-button">
            <?php esc_html_e( 'Cancel', 'eucomply-withdrawal-button' ); ?>
        </a>
    </p>
</form>
