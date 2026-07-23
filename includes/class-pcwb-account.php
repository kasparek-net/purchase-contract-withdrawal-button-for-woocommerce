<?php
/**
 * Optional "Withdrawal" tab in the My Account navigation.
 *
 * Registers a WooCommerce account endpoint that lists the current customer's
 * orders still within the cooling-off period, each linking to the withdrawal form.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCWB_Account {

    const ENDPOINT = 'withdrawal';

    public static function init() {
        if ( 'yes' !== get_option( 'pcwb_menu_tab_enabled', 'no' ) ) {
            return;
        }

        add_action( 'init', [ __CLASS__, 'add_endpoint' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_var' ], 0 );
        add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'add_menu_item' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ __CLASS__, 'render_content' ] );
    }

    public static function add_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    /**
     * @param array $vars Registered query vars.
     * @return array
     */
    public static function add_query_var( $vars ) {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    /**
     * Insert the tab just before "Logout".
     *
     * @param array $items Existing menu items.
     * @return array
     */
    public static function add_menu_item( $items ) {
        $label = __( 'Withdrawal', 'purchase-contract-withdrawal-button-for-woocommerce' );

        if ( ! isset( $items['customer-logout'] ) ) {
            $items[ self::ENDPOINT ] = $label;
            return $items;
        }

        $reordered = [];
        foreach ( $items as $key => $value ) {
            if ( 'customer-logout' === $key ) {
                $reordered[ self::ENDPOINT ] = $label;
            }
            $reordered[ $key ] = $value;
        }
        return $reordered;
    }

    /**
     * Render the endpoint content: eligible orders with a withdrawal button each.
     */
    public static function render_content() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $orders = wc_get_orders(
            [
                'customer_id' => $user_id,
                'status'      => pcwb_get_eligible_statuses(),
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]
        );

        $eligible = array_filter(
            $orders,
            static function ( $order ) {
                return $order instanceof WC_Order && pcwb_is_eligible( $order );
            }
        );

        echo '<p>' . esc_html__( 'Below are your orders that are still within the withdrawal (cooling-off) period. You may withdraw from the purchase contract for any of them.', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</p>';

        if ( empty( $eligible ) ) {
            echo '<p>' . esc_html__( 'You have no orders that are currently eligible for withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive pcwb-account-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Order', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</th>';
        echo '<th>' . esc_html__( 'Date', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</th>';
        echo '<th>' . esc_html__( 'Withdraw by', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</th>';
        echo '<th>&nbsp;</th>';
        echo '</tr></thead><tbody>';

        $days = pcwb_get_period_days();
        foreach ( $eligible as $order ) {
            $reference = pcwb_get_period_reference_date( $order );
            $deadline  = $reference ? $reference->getTimestamp() + ( $days * DAY_IN_SECONDS ) : 0;
            $created   = $order->get_date_created();

            echo '<tr>';
            echo '<td data-title="' . esc_attr__( 'Order', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '">';
            echo '<a href="' . esc_url( $order->get_view_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
            echo '</td>';
            echo '<td data-title="' . esc_attr__( 'Date', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '">';
            echo $created ? esc_html( wc_format_datetime( $created ) ) : '&ndash;';
            echo '</td>';
            echo '<td data-title="' . esc_attr__( 'Withdraw by', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '">';
            echo $deadline ? esc_html( date_i18n( get_option( 'date_format' ), $deadline ) ) : '&ndash;';
            echo '</td>';
            echo '<td>';
            echo '<a href="' . esc_url( pcwb_get_form_url( $order->get_id() ) ) . '" class="button pcwb-button">' . esc_html( _x( 'Withdraw', 'withdrawal action button', 'purchase-contract-withdrawal-button-for-woocommerce' ) ) . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
