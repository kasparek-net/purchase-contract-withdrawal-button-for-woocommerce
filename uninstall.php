<?php
/**
 * Uninstall — cleanup options. Order metadata is preserved as it is part of
 * the legal record of the customer's withdrawal request.
 *
 * @package EUcomplyWithdrawalButton
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'ewb_period_days' );
delete_option( 'ewb_eligible_statuses' );
delete_option( 'ewb_admin_recipient' );
delete_option( 'ewb_new_status' );

delete_option( 'woocommerce_ewb_customer_withdrawal_settings' );
delete_option( 'woocommerce_ewb_admin_withdrawal_settings' );
