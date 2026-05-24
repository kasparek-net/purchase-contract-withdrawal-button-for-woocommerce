<?php
/**
 * Admin withdrawal notification — HTML.
 *
 * @package EUcomplyWithdrawalButton
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $reason
 * @var string   $account
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'A customer has submitted a withdrawal from a purchase contract.', 'eucomply-withdrawal-button' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;">
    <tr>
        <th><?php esc_html_e( 'Order', 'eucomply-withdrawal-button' ); ?></th>
        <td>
            <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Customer', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Email', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo esc_html( $order->get_billing_email() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Phone', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo esc_html( $order->get_billing_phone() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Address', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Total', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Completion date', 'eucomply-withdrawal-button' ); ?></th>
        <td>
            <?php
            $completed = $order->get_date_completed();
            echo esc_html( $completed ? wc_format_datetime( $completed ) : '—' );
            ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Refund account', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo esc_html( $account ?: __( 'Original payment method', 'eucomply-withdrawal-button' ) ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Reason', 'eucomply-withdrawal-button' ); ?></th>
        <td><?php echo esc_html( $reason ?: __( '(not provided)', 'eucomply-withdrawal-button' ) ); ?></td>
    </tr>
</table>

<?php
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
