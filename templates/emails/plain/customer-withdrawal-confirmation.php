<?php
/**
 * Customer withdrawal confirmation — plain text.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

echo esc_html__( 'Hello,', 'purchase-contract-withdrawal-button-for-woocommerce' ) . "\n\n";

printf(
    /* translators: 1: order number, 2: order date */
    esc_html__( 'We have received your withdrawal from the purchase contract for order no. %1$s placed on %2$s.', 'purchase-contract-withdrawal-button-for-woocommerce' ),
    esc_html( $order->get_order_number() ),
    esc_html( wc_format_datetime( $order->get_date_created() ) )
);
echo "\n\n";

echo esc_html__( 'Next steps:', 'purchase-contract-withdrawal-button-for-woocommerce' ) . "\n";
echo '1. ' . esc_html__( 'Please return the goods to our address within 14 days of submitting this withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ) . "\n";
echo '2. ' . esc_html__( 'We recommend shipping with tracking and insurance.', 'purchase-contract-withdrawal-button-for-woocommerce' ) . "\n";
printf(
    /* translators: %s: total amount */
    '3. ' . esc_html__( 'Once we receive the goods we will refund the paid amount (%s) within 14 days.', 'purchase-contract-withdrawal-button-for-woocommerce' ),
    esc_html( wp_strip_all_tags( wc_price( $order->get_total() ) ) )
);
echo "\n\n";

if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( $additional_content ) ) . "\n\n";
}

echo "\n----------\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
