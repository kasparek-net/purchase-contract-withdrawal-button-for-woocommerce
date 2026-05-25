<?php
/**
 * Admin withdrawal notification — plain text.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $reason
 * @var string   $account
 * @var string   $email_heading
 * @var string   $additional_content
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

echo esc_html__( 'A customer has submitted a withdrawal from a purchase contract.', 'purchase-contract-withdrawal-button-for-woocommerce' ) . "\n\n";

echo esc_html__( 'Order', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': #' . esc_html( $order->get_order_number() ) . "\n";
echo esc_html__( 'Customer', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( $order->get_formatted_billing_full_name() ) . "\n";
echo esc_html__( 'Email', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( $order->get_billing_email() ) . "\n";
echo esc_html__( 'Phone', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( $order->get_billing_phone() ) . "\n";
echo esc_html__( 'Address', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( wp_strip_all_tags( $order->get_formatted_billing_address() ) ) . "\n";
echo esc_html__( 'Total', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( wp_strip_all_tags( wc_price( $order->get_total() ) ) ) . "\n";

$completed = $order->get_date_completed();
echo esc_html__( 'Completion date', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( $completed ? wc_format_datetime( $completed ) : '—' ) . "\n";

echo esc_html__( 'Refund account', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( $account ?: __( 'Original payment method', 'purchase-contract-withdrawal-button-for-woocommerce' ) ) . "\n";
echo esc_html__( 'Reason', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ': ' . esc_html( $reason ?: __( '(not provided)', 'purchase-contract-withdrawal-button-for-woocommerce' ) ) . "\n\n";

echo esc_html__( 'View order:', 'purchase-contract-withdrawal-button-for-woocommerce' ) . ' ' . esc_url( $order->get_edit_order_url() ) . "\n";

if ( $additional_content ) {
    echo "\n" . esc_html( wp_strip_all_tags( $additional_content ) ) . "\n";
}
