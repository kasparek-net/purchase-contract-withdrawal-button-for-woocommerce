<?php
/**
 * Plugin Name: Purchase Contract Withdrawal Button for WooCommerce
 * Plugin URI: https://github.com/kasparek-net/purchase-contract-withdrawal-button-for-woocommerce
 * Description: Adds a "Withdraw from purchase contract" button for logged-in customers and an optional shortcode-based form for guests. Centralised admin overview of withdrawal requests, CSV export, configurable cooling-off period and reference date, automated emails. Designed to comply with EU Directive 2023/2673 — required in the Czech Republic from 19 June 2026 (§ 1830a Civil Code).
 * Version: 1.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Jakub Kašpárek
 * Author URI: https://github.com/kasparek-net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: purchase-contract-withdrawal-button-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 10.7
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PCWB_VERSION', '1.3.0' );
define( 'PCWB_PLUGIN_FILE', __FILE__ );
define( 'PCWB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PCWB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCWB_TEMPLATE_PATH', PCWB_PLUGIN_PATH . 'templates/' );

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
add_action( 'plugins_loaded', 'pcwb_bootstrap' );
function pcwb_bootstrap() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'pcwb_woocommerce_missing_notice' );
        return;
    }

    require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-frontend.php';
    require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-settings.php';
    require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-guest.php';

    PCWB_Frontend::init();
    PCWB_Settings::init();
    PCWB_Guest::init();

    if ( is_admin() ) {
        require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-admin-order.php';
        require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-admin-list.php';
        PCWB_Admin_Order::init();
        PCWB_Admin_List::init();
    }

    add_filter( 'woocommerce_email_classes', 'pcwb_register_email_classes' );

    add_action( 'wp_enqueue_scripts', 'pcwb_enqueue_styles' );
}

/**
 * Admin notice when WooCommerce is missing.
 */
function pcwb_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__( 'Purchase Contract Withdrawal Button for WooCommerce requires WooCommerce to be installed and active.', 'purchase-contract-withdrawal-button-for-woocommerce' );
    echo '</p></div>';
}

/**
 * Register custom WC_Email classes.
 *
 * @param array $email_classes Existing email classes keyed by ID.
 * @return array
 */
function pcwb_register_email_classes( $email_classes ) {
    require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-email-customer.php';
    require_once PCWB_PLUGIN_PATH . 'includes/class-pcwb-email-admin.php';

    $email_classes['PCWB_Email_Customer'] = new PCWB_Email_Customer();
    $email_classes['PCWB_Email_Admin']    = new PCWB_Email_Admin();

    return $email_classes;
}

/**
 * Enqueue plugin styles on the My Account page or any page containing the guest shortcode.
 */
function pcwb_enqueue_styles() {
    $should_enqueue = function_exists( 'is_account_page' ) && is_account_page();

    if ( ! $should_enqueue ) {
        global $post;
        if ( $post instanceof WP_Post && has_shortcode( $post->post_content, PCWB_Guest::SHORTCODE ) ) {
            $should_enqueue = true;
        }
    }

    if ( ! $should_enqueue ) {
        return;
    }

    wp_enqueue_style(
        'purchase-contract-withdrawal-button-for-woocommerce',
        PCWB_PLUGIN_URL . 'assets/css/purchase-contract-withdrawal-button-for-woocommerce.css',
        [],
        PCWB_VERSION
    );
}

/**
 * Get the configured withdrawal period in days.
 *
 * @return int
 */
function pcwb_get_period_days() {
    $days = absint( get_option( 'pcwb_period_days', 14 ) );
    return $days > 0 ? $days : 14;
}

/**
 * Get the order statuses for which the withdrawal button is eligible.
 *
 * @return array
 */
function pcwb_get_eligible_statuses() {
    $statuses = get_option( 'pcwb_eligible_statuses', [ 'completed' ] );
    if ( ! is_array( $statuses ) || empty( $statuses ) ) {
        $statuses = [ 'completed' ];
    }
    return apply_filters( 'pcwb_eligible_statuses', $statuses );
}

/**
 * Get the email address that receives admin notifications.
 *
 * @return string
 */
function pcwb_get_admin_recipient() {
    $email = get_option( 'pcwb_admin_recipient', '' );
    if ( ! is_email( $email ) ) {
        $email = get_option( 'admin_email' );
    }
    return apply_filters( 'pcwb_admin_recipient', $email );
}

/**
 * Check whether an order is eligible for withdrawal submission.
 *
 * @param WC_Order $order Order to check.
 * @return bool
 */
function pcwb_is_eligible( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return false;
    }

    if ( ! in_array( $order->get_status(), pcwb_get_eligible_statuses(), true ) ) {
        return false;
    }

    if ( $order->get_meta( '_pcwb_requested_at' ) ) {
        return false;
    }

    $reference_date = pcwb_get_period_reference_date( $order );
    if ( ! $reference_date ) {
        return false;
    }

    $deadline = $reference_date->getTimestamp() + ( pcwb_get_period_days() * DAY_IN_SECONDS );
    return time() < $deadline;
}

/**
 * Get the reference date from which the withdrawal period is counted.
 *
 * Resolution order:
 *   1. `_pcwb_delivered_at` order meta (admin-entered date of goods receipt).
 *      This is the legally correct reference under EU consumer law — the
 *      cooling-off period runs from the moment the consumer takes physical
 *      possession of the goods, not from order completion.
 *   2. Value returned by the `pcwb_period_reference_date` filter.
 *   3. Order completion date.
 *   4. Order creation date (final fallback).
 *
 * @param WC_Order $order Order.
 * @return WC_DateTime|DateTimeImmutable|null
 */
function pcwb_get_period_reference_date( $order ) {
    $delivered = (string) $order->get_meta( '_pcwb_delivered_at' );
    if ( $delivered !== '' ) {
        try {
            $tz   = wp_timezone();
            $date = new WC_DateTime( $delivered, $tz );
            $date->setTimezone( $tz );
            $filtered = apply_filters( 'pcwb_period_reference_date', $date, $order );
            return $filtered instanceof DateTimeInterface ? $filtered : $date;
        } catch ( Exception $e ) {
            // Fall through to the next source if the stored value is unparseable.
        }
    }

    $reference = $order->get_date_completed();
    if ( ! $reference ) {
        $reference = $order->get_date_created();
    }

    $filtered = apply_filters( 'pcwb_period_reference_date', $reference, $order );
    return $filtered instanceof DateTimeInterface ? $filtered : $reference;
}

/**
 * Build the URL that opens the withdrawal form for the given order.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function pcwb_get_form_url( $order_id ) {
    return add_query_arg(
        [ 'pcwb' => 'form' ],
        wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) )
    );
}

/**
 * Plugin action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $settings_url = admin_url( 'admin.php?page=pcwb-settings' );
    array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</a>' );
    return $links;
} );

// Translations are loaded automatically by WordPress.org for plugins hosted in the
// official repository (since WordPress 4.6). Local .mo files in /languages/ still
// work as a fallback during development.
