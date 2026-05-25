<?php
/**
 * Guest withdrawal flow: shortcode + lookup + submission.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCWB_Guest {

    const TOKEN_TTL          = 30 * MINUTE_IN_SECONDS;
    const RATE_WINDOW        = 15 * MINUTE_IN_SECONDS;
    const RATE_MAX_ATTEMPTS  = 5;
    const SHORTCODE          = 'pcwb_withdrawal_form';

    public static function init() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'shortcode' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_handle_submit' ], 5 );
    }

    public static function is_enabled() {
        return 'yes' === get_option( 'pcwb_guest_enabled', 'no' );
    }

    /**
     * Shortcode handler.
     *
     * @param array $atts Shortcode attributes.
     */
    public static function shortcode( $atts = [] ) {
        if ( ! self::is_enabled() ) {
            return '<p>' . esc_html__( 'The withdrawal form is not currently available. Please contact the store.', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</p>';
        }

        ob_start();

        // Success after submit (set via query arg by maybe_handle_submit).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing flag.
        if ( isset( $_GET['pcwb_guest'] ) && 'success' === sanitize_key( wp_unslash( $_GET['pcwb_guest'] ) ) ) {
            echo '<div class="woocommerce-message pcwb-success-notice">';
            echo esc_html__( 'Your withdrawal has been submitted. A confirmation has been sent to your email.', 'purchase-contract-withdrawal-button-for-woocommerce' );
            echo '</div>';
            return ob_get_clean();
        }

        // Form step: user passed lookup, render the full withdrawal form.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Lookup nonce is verified inside resolve_lookup_request().
        if ( isset( $_POST['pcwb_guest'] ) && 'lookup' === sanitize_key( wp_unslash( $_POST['pcwb_guest'] ) ) ) {
            $lookup = self::resolve_lookup_request();
            if ( $lookup['order'] instanceof WC_Order ) {
                self::render_form( $lookup['order'], $lookup['token'] );
                return ob_get_clean();
            }
            self::render_lookup( $lookup['error'], $lookup['order_number'] );
            return ob_get_clean();
        }

        self::render_lookup( '', '' );
        return ob_get_clean();
    }

    /**
     * Handle the submit step. Runs on template_redirect so we can redirect on success.
     */
    public static function maybe_handle_submit() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing gate; nonce verified below.
        if ( empty( $_POST['pcwb_submit'] ) || empty( $_POST['pcwb_guest'] ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing gate; nonce verified below.
        if ( 'submit' !== sanitize_key( wp_unslash( $_POST['pcwb_guest'] ) ) ) {
            return;
        }
        if ( ! self::is_enabled() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified after extracting order ID below.
        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        if ( ! $order_id ) {
            wp_die( esc_html__( 'Invalid order.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 400 ] );
        }

        $nonce = isset( $_POST['pcwb_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pcwb_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'pcwb_submit_' . $order_id ) ) {
            wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }

        if ( empty( $_POST['confirm'] ) ) {
            wp_die( esc_html__( 'You must check the confirmation box to submit a withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 400 ] );
        }

        $token = isset( $_POST['pcwb_guest_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pcwb_guest_token'] ) ) : '';
        if ( ! self::consume_token( $order_id, $token ) ) {
            wp_die( esc_html__( 'Your session has expired. Please start the lookup again.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'Order not found.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 404 ] );
        }

        $reason  = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $account = isset( $_POST['refund_account'] ) ? sanitize_text_field( wp_unslash( $_POST['refund_account'] ) ) : '';

        $result = PCWB_Frontend::do_submit( $order, $reason, $account, 'guest' );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 400 ] );
        }

        $redirect = add_query_arg( 'pcwb_guest', 'success', wp_get_referer() ?: home_url( '/' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Render the lookup form.
     */
    private static function render_lookup( $error_message, $order_number ) {
        wc_get_template(
            'guest-lookup.php',
            [
                'error_message' => (string) $error_message,
                'order_number'  => (string) $order_number,
            ],
            '',
            PCWB_TEMPLATE_PATH
        );
    }

    /**
     * Render the full form for a verified guest.
     */
    private static function render_form( WC_Order $order, $token ) {
        $reference = pcwb_get_period_reference_date( $order );
        $deadline  = $reference ? $reference->getTimestamp() + ( pcwb_get_period_days() * DAY_IN_SECONDS ) : 0;

        wc_get_template(
            'withdrawal-form.php',
            [
                'order'       => $order,
                'back_url'    => wp_get_referer() ?: home_url( '/' ),
                'deadline'    => $deadline,
                'is_guest'    => true,
                'guest_token' => $token,
            ],
            '',
            PCWB_TEMPLATE_PATH
        );
    }

    /**
     * Validate the lookup request and either return an order + fresh token,
     * or an error message + previously submitted order number for prefilling.
     *
     * @return array{order:WC_Order|null, token:string, error:string, order_number:string}
     */
    private static function resolve_lookup_request() {
        $order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
        $email        = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
        $nonce        = isset( $_POST['pcwb_guest_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pcwb_guest_nonce'] ) ) : '';

        $fail = static function ( $message ) use ( $order_number ) {
            return [
                'order'        => null,
                'token'        => '',
                'error'        => $message,
                'order_number' => $order_number,
            ];
        };

        if ( ! wp_verify_nonce( $nonce, 'pcwb_guest_lookup' ) ) {
            return $fail( __( 'Security check failed. Please try again.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        if ( self::rate_limited() ) {
            return $fail( __( 'Too many attempts. Please wait a few minutes and try again.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        if ( $order_number === '' || $email === '' || ! is_email( $email ) ) {
            self::register_attempt();
            return $fail( __( 'Order number or email is missing or invalid.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        $order = self::find_order( $order_number );
        $generic = __( 'We could not find an order with the provided details.', 'purchase-contract-withdrawal-button-for-woocommerce' );

        if ( ! $order || strcasecmp( $order->get_billing_email(), $email ) !== 0 ) {
            self::register_attempt();
            return $fail( $generic );
        }

        if ( $order->get_meta( '_pcwb_requested_at' ) ) {
            return $fail( __( 'A withdrawal has already been submitted for this order.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        if ( ! pcwb_is_eligible( $order ) ) {
            return $fail( __( 'This order is no longer eligible for withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        $token = self::issue_token( $order->get_id() );

        return [
            'order'        => $order,
            'token'        => $token,
            'error'        => '',
            'order_number' => $order_number,
        ];
    }

    /**
     * Resolve an order by user-supplied "order number". Most stores use the ID, but
     * sequential-order-number plugins override it via the woocommerce_order_number filter.
     */
    private static function find_order( $order_number ) {
        $by_id = wc_get_order( absint( $order_number ) );
        if ( $by_id && $by_id->get_order_number() === $order_number ) {
            return $by_id;
        }

        $orders = wc_get_orders( [
            'limit'       => 1,
            'order_number' => $order_number,
        ] );
        if ( ! empty( $orders ) ) {
            return $orders[0];
        }

        return $by_id ?: null;
    }

    private static function issue_token( $order_id ) {
        $token = wp_generate_password( 32, false, false );
        set_transient( 'pcwb_guest_' . $order_id, $token, self::TOKEN_TTL );
        return $token;
    }

    private static function consume_token( $order_id, $token ) {
        $stored = get_transient( 'pcwb_guest_' . $order_id );
        if ( ! $stored || ! hash_equals( $stored, (string) $token ) ) {
            return false;
        }
        delete_transient( 'pcwb_guest_' . $order_id );
        return true;
    }

    private static function rate_limit_key() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        return 'pcwb_rate_' . md5( $ip );
    }

    private static function rate_limited() {
        $count = (int) get_transient( self::rate_limit_key() );
        return $count >= self::RATE_MAX_ATTEMPTS;
    }

    private static function register_attempt() {
        $key   = self::rate_limit_key();
        $count = (int) get_transient( $key );
        set_transient( $key, $count + 1, self::RATE_WINDOW );
    }
}
