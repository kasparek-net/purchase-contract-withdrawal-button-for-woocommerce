<?php
/**
 * Plugin Name: EUcomply Withdrawal Button
 * Plugin URI: https://github.com/kasparek-net/eucomply-withdrawal-button
 * Description: Adds a "Withdraw from purchase contract" button to the order detail page in My Account. Two-step process with nonce, configurable cooling-off period, automated email confirmation. Designed to comply with EU Directive 2023/2673 — required in the Czech Republic from 19 June 2026 (§ 1830a Civil Code), with the same legal basis applicable across the EU.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Jakub Kašpárek
 * Author URI: https://github.com/kasparek-net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eucomply-withdrawal-button
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 10.7
 *
 * @package EUcomplyWithdrawalButton
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EWB_VERSION', '1.0.0' );
define( 'EWB_PLUGIN_FILE', __FILE__ );
define( 'EWB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'EWB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EWB_TEMPLATE_PATH', EWB_PLUGIN_PATH . 'templates/' );

/**
 * HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Bootstrap — only when WooCommerce is active.
 */
add_action( 'plugins_loaded', 'ewb_bootstrap' );
function ewb_bootstrap() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'ewb_woocommerce_missing_notice' );
        return;
    }

    require_once EWB_PLUGIN_PATH . 'includes/class-ewb-frontend.php';
    require_once EWB_PLUGIN_PATH . 'includes/class-ewb-settings.php';

    EWB_Frontend::init();
    EWB_Settings::init();

    add_filter( 'woocommerce_email_classes', 'ewb_register_email_classes' );

    add_action( 'wp_enqueue_scripts', 'ewb_enqueue_styles' );
}

/**
 * Admin notice when WooCommerce is missing.
 */
function ewb_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__( 'EUcomply Withdrawal Button requires WooCommerce to be installed and active.', 'eucomply-withdrawal-button' );
    echo '</p></div>';
}

/**
 * Register custom WC_Email classes.
 *
 * @param array $email_classes Existing email classes keyed by ID.
 * @return array
 */
function ewb_register_email_classes( $email_classes ) {
    require_once EWB_PLUGIN_PATH . 'includes/class-ewb-email-customer.php';
    require_once EWB_PLUGIN_PATH . 'includes/class-ewb-email-admin.php';

    $email_classes['EWB_Email_Customer'] = new EWB_Email_Customer();
    $email_classes['EWB_Email_Admin']    = new EWB_Email_Admin();

    return $email_classes;
}

/**
 * Enqueue plugin styles on the My Account page only.
 */
function ewb_enqueue_styles() {
    if ( function_exists( 'is_account_page' ) && is_account_page() ) {
        wp_enqueue_style(
            'eucomply-withdrawal-button',
            EWB_PLUGIN_URL . 'assets/css/eucomply-withdrawal-button.css',
            [],
            EWB_VERSION
        );
    }
}

/**
 * Get the configured withdrawal period in days.
 *
 * @return int
 */
function ewb_get_period_days() {
    $days = absint( get_option( 'ewb_period_days', 14 ) );
    return $days > 0 ? $days : 14;
}

/**
 * Get the order statuses for which the withdrawal button is eligible.
 *
 * @return array
 */
function ewb_get_eligible_statuses() {
    $statuses = get_option( 'ewb_eligible_statuses', [ 'completed' ] );
    if ( ! is_array( $statuses ) || empty( $statuses ) ) {
        $statuses = [ 'completed' ];
    }
    return apply_filters( 'ewb_eligible_statuses', $statuses );
}

/**
 * Get the email address that receives admin notifications.
 *
 * @return string
 */
function ewb_get_admin_recipient() {
    $email = get_option( 'ewb_admin_recipient', '' );
    if ( ! is_email( $email ) ) {
        $email = get_option( 'admin_email' );
    }
    return apply_filters( 'ewb_admin_recipient', $email );
}

/**
 * Check whether an order is eligible for withdrawal submission.
 *
 * @param WC_Order $order Order to check.
 * @return bool
 */
function ewb_is_eligible( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    if ( ! in_array( $order->get_status(), ewb_get_eligible_statuses(), true ) ) {
        return false;
    }

    if ( $order->get_meta( '_ewb_requested_at' ) ) {
        return false;
    }

    $reference_date = ewb_get_period_reference_date( $order );
    if ( ! $reference_date ) {
        return false;
    }

    $deadline = $reference_date->getTimestamp() + ( ewb_get_period_days() * DAY_IN_SECONDS );
    return time() < $deadline;
}

/**
 * Get the reference date from which the withdrawal period is counted.
 * Defaults to the order completion date; falls back to creation date.
 *
 * @param WC_Order $order Order.
 * @return WC_DateTime|null
 */
function ewb_get_period_reference_date( $order ) {
    $completed = $order->get_date_completed();
    if ( $completed ) {
        return $completed;
    }
    return $order->get_date_created();
}

/**
 * Build the URL that opens the withdrawal form for the given order.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function ewb_get_form_url( $order_id ) {
    return add_query_arg(
        [ 'ewb' => 'form' ],
        wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) )
    );
}

/**
 * Plugin action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $settings_url = admin_url( 'admin.php?page=ewb-settings' );
    array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'eucomply-withdrawal-button' ) . '</a>' );
    return $links;
} );

// Translations are loaded automatically by WordPress.org for plugins hosted in the
// official repository (since WordPress 4.6). Local .mo files in /languages/ still
// work as a fallback during development.
