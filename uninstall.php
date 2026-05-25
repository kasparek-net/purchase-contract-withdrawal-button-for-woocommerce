<?php
/**
 * Uninstall — cleanup options. Order metadata is preserved as it is part of
 * the legal record of the customer's withdrawal request.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'pcwb_period_days' );
delete_option( 'pcwb_eligible_statuses' );
delete_option( 'pcwb_admin_recipient' );
delete_option( 'pcwb_new_status' );

delete_option( 'woocommerce_pcwb_customer_withdrawal_settings' );
delete_option( 'woocommerce_pcwb_admin_withdrawal_settings' );
