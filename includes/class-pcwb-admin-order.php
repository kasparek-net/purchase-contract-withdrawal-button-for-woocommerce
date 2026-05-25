<?php
/**
 * Admin order edit screen: meta box and order actions.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCWB_Admin_Order {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
        add_action( 'woocommerce_process_shop_order_meta', [ __CLASS__, 'save_meta_box' ], 10, 2 );
        add_action( 'woocommerce_update_order', [ __CLASS__, 'save_meta_box_hpos' ], 10, 1 );

        add_filter( 'woocommerce_order_actions', [ __CLASS__, 'register_order_actions' ] );
        add_action( 'woocommerce_order_action_pcwb_resolve', [ __CLASS__, 'order_action_resolve' ] );
        add_action( 'woocommerce_order_action_pcwb_submit_on_behalf', [ __CLASS__, 'order_action_submit' ] );
    }

    /**
     * Register the cooling-off meta box. Works for both classic CPT and HPOS screens.
     */
    public static function register_meta_box() {
        $screens = [
            'shop_order',
            'woocommerce_page_wc-orders',
        ];
        if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Orders\PageController' ) ) {
            $screens[] = wc_get_page_screen_id( 'shop-order' );
        }

        foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
            add_meta_box(
                'pcwb_cooling_off',
                __( 'Withdrawal cooling-off', 'purchase-contract-withdrawal-button-for-woocommerce' ),
                [ __CLASS__, 'render_meta_box' ],
                $screen,
                'side',
                'default'
            );
        }
    }

    /**
     * Render the meta box.
     *
     * @param WP_Post|WC_Order $post_or_order Post (classic) or order (HPOS).
     */
    public static function render_meta_box( $post_or_order ) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
        if ( ! $order ) {
            return;
        }

        wp_nonce_field( 'pcwb_meta_box', 'pcwb_meta_box_nonce' );

        $delivered_raw = (string) $order->get_meta( '_pcwb_delivered_at' );
        $delivered_val = '';
        if ( $delivered_raw !== '' ) {
            try {
                $dt = new WC_DateTime( $delivered_raw, wp_timezone() );
                $delivered_val = $dt->format( 'Y-m-d' );
            } catch ( Exception $e ) {
                $delivered_val = '';
            }
        }

        $reference = pcwb_get_period_reference_date( $order );
        $deadline  = $reference ? $reference->getTimestamp() + ( pcwb_get_period_days() * DAY_IN_SECONDS ) : 0;
        $requested = (string) $order->get_meta( '_pcwb_requested_at' );
        $resolved  = (string) $order->get_meta( '_pcwb_resolved_at' );
        $source    = (string) $order->get_meta( '_pcwb_source' );
        ?>
        <p>
            <label for="pcwb_delivered_at"><strong><?php esc_html_e( 'Date of delivery', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong></label>
            <input type="date" id="pcwb_delivered_at" name="pcwb_delivered_at" value="<?php echo esc_attr( $delivered_val ); ?>" class="widefat" />
            <span class="description"><?php esc_html_e( 'Date the customer received the goods. Used as the reference date for the cooling-off period (leave empty to use the order completion date).', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></span>
        </p>

        <?php if ( $deadline > 0 ) : ?>
            <p>
                <strong><?php esc_html_e( 'Cooling-off ends:', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong><br>
                <?php
                echo esc_html( date_i18n( get_option( 'date_format' ), $deadline ) );
                if ( time() < $deadline ) {
                    $remaining = (int) ceil( ( $deadline - time() ) / DAY_IN_SECONDS );
                    /* translators: %d: number of days remaining */
                    echo ' &middot; <em>' . esc_html( sprintf( _n( '%d day remaining', '%d days remaining', $remaining, 'purchase-contract-withdrawal-button-for-woocommerce' ), $remaining ) ) . '</em>';
                } else {
                    echo ' &middot; <em>' . esc_html__( 'expired', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</em>';
                }
                ?>
            </p>
        <?php endif; ?>

        <?php if ( $requested !== '' ) : ?>
            <p>
                <strong><?php esc_html_e( 'Withdrawal submitted:', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong><br>
                <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $requested ) ) ); ?>
                <?php if ( $source !== '' ) : ?>
                    (<?php echo esc_html( self::label_source( $source ) ); ?>)
                <?php endif; ?>
            </p>
            <?php if ( $resolved !== '' ) : ?>
                <p><strong><?php esc_html_e( 'Resolved:', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $resolved ) ) ); ?></p>
            <?php else : ?>
                <p><em><?php esc_html_e( 'Use the Order actions box to mark this withdrawal as resolved.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></em></p>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Save meta box (classic CPT screen).
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function save_meta_box( $post_id, $post = null ) {
        $order = wc_get_order( $post_id );
        if ( $order ) {
            self::persist_meta_box( $order );
        }
    }

    /**
     * Save meta box (HPOS screen). Only persists when our nonce is present.
     *
     * @param int $order_id Order ID.
     */
    public static function save_meta_box_hpos( $order_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Gate; nonce validated in persist_meta_box.
        if ( empty( $_POST['pcwb_meta_box_nonce'] ) ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( $order ) {
            self::persist_meta_box( $order );
        }
    }

    /**
     * Read POST data and write it to the order.
     */
    private static function persist_meta_box( WC_Order $order ) {
        $nonce = isset( $_POST['pcwb_meta_box_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pcwb_meta_box_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'pcwb_meta_box' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $raw = isset( $_POST['pcwb_delivered_at'] ) ? sanitize_text_field( wp_unslash( $_POST['pcwb_delivered_at'] ) ) : '';
        if ( $raw === '' ) {
            $order->delete_meta_data( '_pcwb_delivered_at' );
            $order->save();
            return;
        }

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            $order->update_meta_data( '_pcwb_delivered_at', $raw . ' 00:00:00' );
            $order->save();
        }
    }

    /**
     * Add our admin-side order actions to the dropdown.
     *
     * @param array $actions Existing actions.
     * @return array
     */
    public static function register_order_actions( $actions ) {
        global $theorder;
        if ( $theorder instanceof WC_Order ) {
            $has_request = (bool) $theorder->get_meta( '_pcwb_requested_at' );
            $has_resolve = (bool) $theorder->get_meta( '_pcwb_resolved_at' );

            if ( $has_request && ! $has_resolve ) {
                $actions['pcwb_resolve'] = __( 'Mark withdrawal as resolved', 'purchase-contract-withdrawal-button-for-woocommerce' );
            }
            if ( ! $has_request ) {
                $actions['pcwb_submit_on_behalf'] = __( 'Submit withdrawal on behalf of customer', 'purchase-contract-withdrawal-button-for-woocommerce' );
            }
        }
        return $actions;
    }

    /**
     * Order action: resolve.
     *
     * @param WC_Order $order Order.
     */
    public static function order_action_resolve( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }
        PCWB_Frontend::resolve( $order, get_current_user_id() );
    }

    /**
     * Order action: submit on behalf.
     *
     * @param WC_Order $order Order.
     */
    public static function order_action_submit( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }
        PCWB_Frontend::do_submit( $order, '', '', 'admin', get_current_user_id() );
    }

    private static function label_source( $source ) {
        switch ( $source ) {
            case 'guest':
                return __( 'guest form', 'purchase-contract-withdrawal-button-for-woocommerce' );
            case 'admin':
                return __( 'admin', 'purchase-contract-withdrawal-button-for-woocommerce' );
            default:
                return __( 'customer account', 'purchase-contract-withdrawal-button-for-woocommerce' );
        }
    }
}
