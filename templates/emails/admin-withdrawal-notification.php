<?php
/**
 * Admin withdrawal notification — HTML.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 * @version 1.2.2
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

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core action, intentionally invoked to integrate with WC email template system.
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'A customer has submitted a withdrawal from a purchase contract.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;">
    <tr>
        <th><?php esc_html_e( 'Order', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td>
            <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Customer', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Email', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo esc_html( $order->get_billing_email() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Phone', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo esc_html( $order->get_billing_phone() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Address', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Total', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Completion date', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td>
            <?php
            $pcwb_completed = $order->get_date_completed();
            echo esc_html( $pcwb_completed ? wc_format_datetime( $pcwb_completed ) : '—' );
            ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Refund account', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo esc_html( $account ?: __( 'Original payment method', 'purchase-contract-withdrawal-button-for-woocommerce' ) ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Reason', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></th>
        <td><?php echo esc_html( $reason ?: __( '(not provided)', 'purchase-contract-withdrawal-button-for-woocommerce' ) ); ?></td>
    </tr>
</table>

<?php
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core action, intentionally invoked to integrate with WC email template system.
do_action( 'woocommerce_email_footer', $email );
