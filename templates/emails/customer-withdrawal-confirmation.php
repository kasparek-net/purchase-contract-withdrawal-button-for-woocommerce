<?php
/**
 * Customer withdrawal confirmation — HTML.
 *
 * Override by copying to yourtheme/purchase-contract-withdrawal-button-for-woocommerce/emails/customer-withdrawal-confirmation.php
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Hello,', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></p>

<p>
    <?php
    printf(
        /* translators: 1: order number, 2: order date */
        esc_html__( 'We have received your withdrawal from the purchase contract for order no. %1$s placed on %2$s.', 'purchase-contract-withdrawal-button-for-woocommerce' ),
        '<strong>' . esc_html( $order->get_order_number() ) . '</strong>',
        esc_html( wc_format_datetime( $order->get_date_created() ) )
    );
    ?>
</p>

<h2><?php esc_html_e( 'Next steps', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></h2>

<ol>
    <li><?php esc_html_e( 'Please return the goods to our address within 14 days of submitting this withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></li>
    <li><?php esc_html_e( 'We recommend shipping with tracking and insurance.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></li>
    <li>
        <?php
        printf(
            /* translators: %s: total amount */
            esc_html__( 'Once we receive the goods we will refund the paid amount (%s) within 14 days.', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            wp_kses_post( wc_price( $order->get_total() ) )
        );
        ?>
    </li>
</ol>

<?php
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
