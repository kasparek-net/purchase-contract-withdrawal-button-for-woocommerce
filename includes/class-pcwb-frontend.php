<?php
/**
 * Frontend: button rendering, form display, submission handling.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCWB_Frontend {

    public static function init() {
        $position = (string) get_option( 'pcwb_button_position', 'after_order_table' );
        $positions = PCWB_Settings::get_positions();
        $hook = $positions[ $position ]['hook'] ?? 'woocommerce_order_details_after_order_table';

        // Orders list action uses a different signature.
        if ( 'woocommerce_my_account_my_orders_actions' === $hook ) {
            add_filter( 'woocommerce_my_account_my_orders_actions', [ __CLASS__, 'add_orders_list_action' ], 20, 2 );
        } else {
            add_action( $hook, [ __CLASS__, 'render_button' ], 20 );
        }

        add_action( 'woocommerce_view_order', [ __CLASS__, 'maybe_render_form' ], 5, 1 );
        add_action( 'woocommerce_view_order', [ __CLASS__, 'maybe_render_success' ], 1, 1 );
        add_action( 'template_redirect', [ __CLASS__, 'handle_submit' ] );
    }

    /**
     * Add an action button in the My Account orders list.
     *
     * @param array    $actions Existing actions.
     * @param WC_Order $order   The order.
     * @return array
     */
    public static function add_orders_list_action( $actions, $order ) {
        if ( $order->get_meta( '_pcwb_requested_at' ) ) {
            return $actions;
        }
        if ( ! pcwb_is_eligible( $order ) ) {
            return $actions;
        }
        $actions['pcwb_withdrawal'] = [
            'url'  => pcwb_get_form_url( $order->get_id() ),
            'name' => __( 'Withdraw', 'purchase-contract-withdrawal-button-for-woocommerce' ),
        ];
        return $actions;
    }

    /**
     * Render the button (or the "already submitted" notice) on the order detail page.
     *
     * @param WC_Order|int $order Order or order ID — different WC hooks pass different types.
     */
    public static function render_button( $order ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) {
            return;
        }

        // Don't render on the form / success sub-pages — they're handled by other hooks.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check on a public page, no side effects.
        if ( isset( $_GET['pcwb'] ) ) {
            return;
        }

        $requested_at = $order->get_meta( '_pcwb_requested_at' );
        if ( $requested_at ) {
            self::render_already_submitted_notice( $requested_at );
            return;
        }

        if ( ! pcwb_is_eligible( $order ) ) {
            return;
        }

        $url = pcwb_get_form_url( $order->get_id() );
        ?>
        <p class="pcwb-button-wrapper">
            <a href="<?php echo esc_url( $url ); ?>" class="button pcwb-button">
                <?php esc_html_e( 'Withdraw from purchase contract', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Render the form when ?pcwb=form is in the URL.
     *
     * @param int $order_id Order ID.
     */
    public static function maybe_render_form( $order_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; the underlying submit is nonce-protected.
        $sub_page = isset( $_GET['pcwb'] ) ? sanitize_key( wp_unslash( $_GET['pcwb'] ) ) : '';
        if ( 'form' !== $sub_page ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
            return;
        }

        $back_url = wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) );

        if ( ! pcwb_is_eligible( $order ) ) {
            // Replace default view-order content with a friendly notice.
            remove_all_actions( 'woocommerce_view_order', 10 );
            ?>
            <p><?php esc_html_e( 'This order is no longer eligible for withdrawal (the cooling-off period has expired, or a withdrawal has already been submitted).', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></p>
            <p><a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( 'Back to order', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></a></p>
            <?php
            return;
        }

        remove_all_actions( 'woocommerce_view_order', 10 );

        $reference = pcwb_get_period_reference_date( $order );
        $deadline  = $reference ? $reference->getTimestamp() + ( pcwb_get_period_days() * DAY_IN_SECONDS ) : 0;

        wc_get_template(
            'withdrawal-form.php',
            [
                'order'    => $order,
                'back_url' => $back_url,
                'deadline' => $deadline,
            ],
            '',
            PCWB_TEMPLATE_PATH
        );
    }

    /**
     * Show a success notice when ?pcwb=success is in the URL.
     *
     * @param int $order_id Order ID.
     */
    public static function maybe_render_success( $order_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
        $sub_page = isset( $_GET['pcwb'] ) ? sanitize_key( wp_unslash( $_GET['pcwb'] ) ) : '';
        if ( 'success' !== $sub_page ) {
            return;
        }
        echo '<div class="woocommerce-message pcwb-success-notice">';
        echo esc_html__( 'Your withdrawal has been submitted. A confirmation has been sent to your email. We will be in touch with return instructions.', 'purchase-contract-withdrawal-button-for-woocommerce' );
        echo '</div>';
    }

    /**
     * Render the "already submitted" notice.
     *
     * @param string $requested_at MySQL datetime of submission.
     */
    private static function render_already_submitted_notice( $requested_at ) {
        $date = date_i18n( get_option( 'date_format' ), strtotime( $requested_at ) );
        ?>
        <p class="pcwb-already-submitted-notice">
            <strong><?php esc_html_e( 'Withdrawal has been submitted', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></strong>
            <?php
            /* translators: %s: submission date */
            printf( ' ' . esc_html__( 'on %s.', 'purchase-contract-withdrawal-button-for-woocommerce' ), esc_html( $date ) );
            ?>
            <?php
            $admin_email = pcwb_get_admin_recipient();
            if ( $admin_email ) {
                /* translators: %s: admin email address */
                printf( ' ' . esc_html__( 'If you have any questions, contact us at %s.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '<a href="mailto:' . esc_attr( $admin_email ) . '">' . esc_html( $admin_email ) . '</a>' );
            }
            ?>
        </p>
        <?php
    }

    /**
     * Handle POST submission from a logged-in customer (My Account flow).
     */
    public static function handle_submit() {
        // Gate: only process our submission; nonce is verified below.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Gate; full nonce verification follows after extracting the order ID.
        if ( empty( $_POST['pcwb_submit'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing gate for the guest flow; handled by PCWB_Guest.
        if ( ! empty( $_POST['pcwb_guest'] ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'You must be logged in to submit a withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }

        // The order ID is required to construct the nonce action name, so it must be read before the nonce is verified.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified on the next statement.
        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        if ( ! $order_id ) {
            wp_die( esc_html__( 'Invalid order.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 400 ] );
        }

        $nonce = isset( $_POST['pcwb_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pcwb_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'pcwb_submit_' . $order_id ) ) {
            wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
            wp_die( esc_html__( 'You are not authorized to access this order.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }

        if ( empty( $_POST['confirm'] ) ) {
            wp_die( esc_html__( 'You must check the confirmation box to submit a withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 400 ] );
        }

        $reason  = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $account = isset( $_POST['refund_account'] ) ? sanitize_text_field( wp_unslash( $_POST['refund_account'] ) ) : '';

        $result = self::do_submit( $order, $reason, $account, 'customer' );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 400 ] );
        }

        $back_url = add_query_arg(
            [ 'pcwb' => 'success' ],
            wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) )
        );
        wp_safe_redirect( $back_url );
        exit;
    }

    /**
     * Shared submission pipeline used by customer, guest, and admin flows.
     *
     * @param WC_Order $order    Order being withdrawn.
     * @param string   $reason   Optional reason.
     * @param string   $account  Optional refund account.
     * @param string   $source   One of: customer, guest, admin.
     * @param int      $admin_id Admin user ID when $source = admin.
     * @return true|WP_Error
     */
    public static function do_submit( WC_Order $order, $reason = '', $account = '', $source = 'customer', $admin_id = 0 ) {
        if ( $order->get_meta( '_pcwb_requested_at' ) ) {
            return new WP_Error( 'already_submitted', __( 'A withdrawal has already been submitted for this order.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        if ( 'admin' !== $source && ! pcwb_is_eligible( $order ) ) {
            return new WP_Error( 'not_eligible', __( 'This order is no longer eligible for withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        $now    = current_time( 'mysql' );
        $reason = (string) $reason;
        $account = (string) $account;

        $order->update_meta_data( '_pcwb_requested_at', $now );
        $order->update_meta_data( '_pcwb_reason', $reason );
        $order->update_meta_data( '_pcwb_refund_account', $account );
        $order->update_meta_data( '_pcwb_source', $source );
        if ( 'admin' === $source && $admin_id > 0 ) {
            $order->update_meta_data( '_pcwb_submitted_by', $admin_id );
        }

        $intro = self::submission_intro( $source, $admin_id );
        $note  = $intro . "\n";
        if ( $reason ) {
            /* translators: %s: customer-provided reason */
            $note .= sprintf( __( 'Reason: %s', 'purchase-contract-withdrawal-button-for-woocommerce' ), $reason ) . "\n";
        }
        if ( $account ) {
            /* translators: %s: bank account for refund */
            $note .= sprintf( __( 'Refund account: %s', 'purchase-contract-withdrawal-button-for-woocommerce' ), $account ) . "\n";
        }
        $order->add_order_note( $note );

        $new_status = apply_filters( 'pcwb_new_status', get_option( 'pcwb_new_status', 'on-hold' ), $order );
        $order->update_status( $new_status, $intro );
        $order->save();

        do_action( 'pcwb_after_submit', $order, $reason, $account, $source );

        WC()->mailer();
        do_action( 'pcwb_customer_withdrawal_email', $order, $reason, $account );
        do_action( 'pcwb_admin_withdrawal_email', $order, $reason, $account );

        return true;
    }

    /**
     * Mark a previously submitted withdrawal as resolved (refund processed, goods returned, etc.).
     *
     * @param WC_Order $order        Order.
     * @param int      $resolved_by  Admin user ID resolving the request.
     * @return true|WP_Error
     */
    public static function resolve( WC_Order $order, $resolved_by = 0 ) {
        if ( ! $order->get_meta( '_pcwb_requested_at' ) ) {
            return new WP_Error( 'not_submitted', __( 'This order does not have a pending withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }
        if ( $order->get_meta( '_pcwb_resolved_at' ) ) {
            return new WP_Error( 'already_resolved', __( 'This withdrawal has already been resolved.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        $resolved_by = absint( $resolved_by );
        $order->update_meta_data( '_pcwb_resolved_at', current_time( 'mysql' ) );
        if ( $resolved_by > 0 ) {
            $order->update_meta_data( '_pcwb_resolved_by', $resolved_by );
        }

        $user = $resolved_by ? get_userdata( $resolved_by ) : null;
        $name = $user ? $user->display_name : __( 'an administrator', 'purchase-contract-withdrawal-button-for-woocommerce' );
        /* translators: %s: admin display name */
        $order->add_order_note( sprintf( __( 'Withdrawal marked as resolved by %s.', 'purchase-contract-withdrawal-button-for-woocommerce' ), $name ) );
        $order->save();

        do_action( 'pcwb_after_resolve', $order, $resolved_by );

        return true;
    }

    /**
     * Remove a withdrawal record from an order.
     *
     * Deletes all withdrawal-related meta. The order itself is NOT deleted and
     * its status is left untouched — an administrator can adjust it manually.
     *
     * @param WC_Order $order Order to clear.
     * @return true|WP_Error
     */
    public static function delete_withdrawal( WC_Order $order ) {
        if ( ! $order->get_meta( '_pcwb_requested_at' ) ) {
            return new WP_Error( 'not_submitted', __( 'This order does not have a withdrawal record.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }

        $keys = [
            '_pcwb_requested_at',
            '_pcwb_reason',
            '_pcwb_refund_account',
            '_pcwb_source',
            '_pcwb_submitted_by',
            '_pcwb_resolved_at',
            '_pcwb_resolved_by',
        ];
        foreach ( $keys as $key ) {
            $order->delete_meta_data( $key );
        }

        $order->add_order_note( __( 'Withdrawal record removed by an administrator. The order itself was not deleted.', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        $order->save();

        do_action( 'pcwb_after_delete', $order );

        return true;
    }

    /**
     * Build a human-readable intro for the order note / status-change comment.
     *
     * @param string $source   One of: customer, guest, admin.
     * @param int    $admin_id Admin user ID when $source = admin.
     */
    private static function submission_intro( $source, $admin_id = 0 ) {
        if ( 'admin' === $source ) {
            $user = $admin_id ? get_userdata( $admin_id ) : null;
            $name = $user ? $user->display_name : __( 'an administrator', 'purchase-contract-withdrawal-button-for-woocommerce' );
            /* translators: %s: admin display name */
            return sprintf( __( 'Withdrawal submitted on behalf of the customer by %s.', 'purchase-contract-withdrawal-button-for-woocommerce' ), $name );
        }
        if ( 'guest' === $source ) {
            return __( 'Customer (guest) submitted a withdrawal from the purchase contract.', 'purchase-contract-withdrawal-button-for-woocommerce' );
        }
        return __( 'Customer submitted a withdrawal from the purchase contract.', 'purchase-contract-withdrawal-button-for-woocommerce' );
    }
}
