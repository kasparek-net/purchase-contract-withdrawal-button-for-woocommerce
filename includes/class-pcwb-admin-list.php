<?php
/**
 * Admin: list of withdrawal requests.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PCWB_Admin_List extends WP_List_Table {

    /**
     * State counts for the views row, computed in prepare_items().
     *
     * @var array
     */
    private $state_counts = [ '' => 0, 'pending' => 0, 'resolved' => 0 ];

    public function __construct() {
        parent::__construct( [
            'singular' => 'withdrawal',
            'plural'   => 'withdrawals',
            'ajax'     => false,
        ] );
    }

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
        add_action( 'admin_post_pcwb_export_csv', [ __CLASS__, 'export_csv' ] );
        add_action( 'admin_post_pcwb_resolve', [ __CLASS__, 'handle_resolve' ] );
        add_action( 'admin_post_pcwb_delete', [ __CLASS__, 'handle_delete' ] );
    }

    public static function menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Withdrawals', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            __( 'Withdrawals', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'manage_woocommerce',
            'pcwb-withdrawals',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $table = new self();
        $table->process_bulk_action();
        $table->prepare_items();

        $export_url = wp_nonce_url(
            add_query_arg(
                array_filter( [
                    'action' => 'pcwb_export_csv',
                    'state'  => self::current_state(),
                    's'      => self::current_search(),
                    'from'   => self::current_from(),
                    'to'     => self::current_to(),
                ] ),
                admin_url( 'admin-post.php' )
            ),
            'pcwb_export_csv'
        );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Withdrawals', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></h1>
            <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></a>
            <hr class="wp-header-end">

            <form method="get">
                <input type="hidden" name="page" value="pcwb-withdrawals">
                <?php $table->views(); ?>
                <?php $table->search_box( __( 'Search', 'purchase-contract-withdrawal-button-for-woocommerce' ), 'pcwb' ); ?>
                <p>
                    <label><?php esc_html_e( 'From', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
                        <input type="date" name="from" value="<?php echo esc_attr( self::current_from() ); ?>">
                    </label>
                    <label><?php esc_html_e( 'To', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
                        <input type="date" name="to" value="<?php echo esc_attr( self::current_to() ); ?>">
                    </label>
                    <?php submit_button( __( 'Filter', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', 'filter', false ); ?>
                </p>
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'order'          => __( 'Order', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'requested_at'   => __( 'Submitted', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'customer'       => __( 'Customer', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'total'          => __( 'Total', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'refund_account' => __( 'Refund account', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'source'         => __( 'Source', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'status'         => __( 'Status', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'resolved'       => __( 'Resolved', 'purchase-contract-withdrawal-button-for-woocommerce' ),
        ];
    }

    public function get_bulk_actions() {
        return [
            'resolve' => __( 'Mark as resolved', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'delete'  => __( 'Delete withdrawal record', 'purchase-contract-withdrawal-button-for-woocommerce' ),
        ];
    }

    public function get_views() {
        $base    = remove_query_arg( [ 'state', 'paged' ] );
        $current = self::current_state();
        $counts  = $this->state_counts;
        $views   = [];
        $labels  = [
            ''         => __( 'All', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'pending'  => __( 'Pending', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'resolved' => __( 'Resolved', 'purchase-contract-withdrawal-button-for-woocommerce' ),
        ];
        foreach ( $labels as $key => $label ) {
            $url   = $key === '' ? $base : add_query_arg( 'state', $key, $base );
            $class = $current === $key ? 'current' : '';
            $count = isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
            $views[ $key ] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                esc_attr( $class ),
                esc_html( $label ),
                (int) $count
            );
        }
        return $views;
    }

    public function prepare_items() {
        $per_page = 20;
        $current  = $this->get_pagenum();

        // Candidate set: all withdrawals matching search + date range, sorted newest first.
        // State (All/Pending/Resolved) is filtered in PHP rather than via wc_get_orders
        // meta_query, which does not reliably narrow by EXISTS/NOT EXISTS across the
        // CPT and HPOS order stores.
        $candidates = $this->get_candidate_orders();

        $pending  = 0;
        $resolved = 0;
        foreach ( $candidates as $order ) {
            if ( $order->get_meta( '_pcwb_resolved_at' ) ) {
                $resolved++;
            } else {
                $pending++;
            }
        }
        $this->state_counts = [
            ''         => count( $candidates ),
            'pending'  => $pending,
            'resolved' => $resolved,
        ];

        $filtered = $this->filter_by_state( $candidates, self::current_state() );
        $total    = count( $filtered );

        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items = array_slice( $filtered, ( $current - 1 ) * $per_page, $per_page );
        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ] );
    }

    /**
     * Load all withdrawal orders matching the current search + date range,
     * sorted by submission date descending.
     *
     * @return WC_Order[]
     */
    private function get_candidate_orders() {
        $ids = wc_get_orders( $this->build_query_args() );
        $ids = is_array( $ids ) ? $ids : [];

        $orders = [];
        foreach ( $ids as $id ) {
            $order = wc_get_order( $id );
            if ( ! $order || ! $this->in_date_range( $order ) ) {
                continue;
            }
            $orders[] = $order;
        }

        usort(
            $orders,
            static function ( $a, $b ) {
                return strcmp(
                    (string) $b->get_meta( '_pcwb_requested_at' ),
                    (string) $a->get_meta( '_pcwb_requested_at' )
                );
            }
        );

        return $orders;
    }

    /**
     * Filter a list of withdrawal orders by state.
     *
     * @param WC_Order[] $orders Orders to filter.
     * @param string     $state  '', 'pending', or 'resolved'.
     * @return WC_Order[]
     */
    private function filter_by_state( $orders, $state ) {
        if ( 'resolved' !== $state && 'pending' !== $state ) {
            return $orders;
        }
        $out = [];
        foreach ( $orders as $order ) {
            $is_resolved = (bool) $order->get_meta( '_pcwb_resolved_at' );
            if ( ( 'resolved' === $state && $is_resolved ) || ( 'pending' === $state && ! $is_resolved ) ) {
                $out[] = $order;
            }
        }
        return $out;
    }

    /**
     * Check whether an order's submission date is within the current date filter.
     *
     * @param WC_Order $order Order to test.
     * @return bool
     */
    private function in_date_range( $order ) {
        $from = self::current_from();
        $to   = self::current_to();
        if ( ! $from && ! $to ) {
            return true;
        }
        $requested = (string) $order->get_meta( '_pcwb_requested_at' );
        if ( '' === $requested ) {
            return false;
        }
        $ts = strtotime( $requested );
        if ( $from && $ts < strtotime( $from . ' 00:00:00' ) ) {
            return false;
        }
        if ( $to && $ts > strtotime( $to . ' 23:59:59' ) ) {
            return false;
        }
        return true;
    }

    /**
     * Build wc_get_orders args for the base withdrawal set (search applied here;
     * date range and state are filtered in PHP).
     *
     * @return array
     */
    private function build_query_args() {
        $args = [
            'limit'        => 9999,
            'return'       => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to list orders flagged as withdrawn; volume is bounded by withdrawal requests.
            'meta_key'     => '_pcwb_requested_at',
            'meta_compare' => 'EXISTS',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to filter orders by withdrawal status; bounded volume.
            'meta_query'   => [
                [
                    'key'     => '_pcwb_requested_at',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $search = self::current_search();
        if ( $search !== '' ) {
            $args['search'] = '*' . $search . '*';
        }

        return $args;
    }

    public function column_cb( $order ) {
        return sprintf( '<input type="checkbox" name="orders[]" value="%d" />', $order->get_id() );
    }

    public function column_default( $order, $column_name ) {
        switch ( $column_name ) {
            case 'order':
                $url        = $order->get_edit_order_url();
                $delete_url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action'   => 'pcwb_delete',
                            'order_id' => $order->get_id(),
                        ],
                        admin_url( 'admin-post.php' )
                    ),
                    'pcwb_delete_' . $order->get_id()
                );
                /* translators: %s: order number */
                $confirm = esc_attr( sprintf( __( 'Remove the withdrawal record from order #%s? The order itself will NOT be deleted.', 'purchase-contract-withdrawal-button-for-woocommerce' ), $order->get_order_number() ) );
                $actions = [
                    'delete' => sprintf(
                        '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                        esc_url( $delete_url ),
                        $confirm,
                        esc_html__( 'Delete', 'purchase-contract-withdrawal-button-for-woocommerce' )
                    ),
                ];
                return sprintf( '<a href="%s">#%s</a>', esc_url( $url ), esc_html( $order->get_order_number() ) ) . $this->row_actions( $actions );
            case 'requested_at':
                $requested = (string) $order->get_meta( '_pcwb_requested_at' );
                return $requested ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $requested ) ) ) : '—';
            case 'customer':
                $name  = $order->get_formatted_billing_full_name();
                $email = $order->get_billing_email();
                return esc_html( $name ) . '<br><small>' . esc_html( $email ) . '</small>';
            case 'total':
                return wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) );
            case 'refund_account':
                $account = (string) $order->get_meta( '_pcwb_refund_account' );
                return $account !== '' ? esc_html( $account ) : '<em>—</em>';
            case 'source':
                $source = (string) $order->get_meta( '_pcwb_source' );
                switch ( $source ) {
                    case 'guest':
                        return esc_html__( 'guest', 'purchase-contract-withdrawal-button-for-woocommerce' );
                    case 'admin':
                        return esc_html__( 'admin', 'purchase-contract-withdrawal-button-for-woocommerce' );
                    default:
                        return esc_html__( 'customer', 'purchase-contract-withdrawal-button-for-woocommerce' );
                }
            case 'status':
                return esc_html( wc_get_order_status_name( $order->get_status() ) );
            case 'resolved':
                $resolved = (string) $order->get_meta( '_pcwb_resolved_at' );
                if ( $resolved !== '' ) {
                    return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $resolved ) ) );
                }
                $url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action'   => 'pcwb_resolve',
                            'order_id' => $order->get_id(),
                        ],
                        admin_url( 'admin-post.php' )
                    ),
                    'pcwb_resolve_' . $order->get_id()
                );
                return sprintf( '<a class="button button-small" href="%s">%s</a>', esc_url( $url ), esc_html__( 'Resolve', 'purchase-contract-withdrawal-button-for-woocommerce' ) );
        }
        return '';
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        if ( 'resolve' !== $action && 'delete' !== $action ) {
            return;
        }
        check_admin_referer( 'bulk-withdrawals' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        $ids   = isset( $_REQUEST['orders'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['orders'] ) ) : [];
        $count = 0;
        foreach ( $ids as $id ) {
            $order = wc_get_order( $id );
            if ( ! $order ) {
                continue;
            }
            $result = 'delete' === $action
                ? PCWB_Frontend::delete_withdrawal( $order )
                : PCWB_Frontend::resolve( $order, get_current_user_id() );
            if ( true === $result ) {
                $count++;
            }
        }
        if ( $count > 0 ) {
            add_action( 'admin_notices', function () use ( $count, $action ) {
                if ( 'delete' === $action ) {
                    $message = sprintf(
                        /* translators: %d: number of withdrawal records removed */
                        _n( '%d withdrawal record removed.', '%d withdrawal records removed.', $count, 'purchase-contract-withdrawal-button-for-woocommerce' ),
                        $count
                    );
                } else {
                    $message = sprintf(
                        /* translators: %d: number of withdrawals resolved */
                        _n( '%d withdrawal marked as resolved.', '%d withdrawals marked as resolved.', $count, 'purchase-contract-withdrawal-button-for-woocommerce' ),
                        $count
                    );
                }
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html( $message )
                );
            } );
        }
    }

    public static function handle_resolve() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        check_admin_referer( 'pcwb_resolve_' . $order_id );
        $order = wc_get_order( $order_id );
        if ( $order ) {
            PCWB_Frontend::resolve( $order, get_current_user_id() );
        }
        wp_safe_redirect( add_query_arg( 'page', 'pcwb-withdrawals', admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_delete() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        check_admin_referer( 'pcwb_delete_' . $order_id );
        $order = wc_get_order( $order_id );
        if ( $order ) {
            PCWB_Frontend::delete_withdrawal( $order );
        }
        wp_safe_redirect( add_query_arg( 'page', 'pcwb-withdrawals', admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function export_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'pcwb_export_csv' );

        $table  = new self();
        $orders = $table->filter_by_state( $table->get_candidate_orders(), self::current_state() );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=pcwb-withdrawals-' . gmdate( 'Y-m-d' ) . '.csv' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV to php://output; WP_Filesystem cannot stream to the HTTP response.
        $out = fopen( 'php://output', 'w' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Writing CSV row to php://output stream.
        fputcsv( $out, [
            'Order',
            'Submitted',
            'Customer',
            'Email',
            'Total',
            'Currency',
            'Refund account',
            'Reason',
            'Source',
            'Status',
            'Resolved at',
        ] );

        if ( is_array( $orders ) ) {
            foreach ( $orders as $order ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Writing CSV row to php://output stream.
                fputcsv( $out, [
                    $order->get_order_number(),
                    (string) $order->get_meta( '_pcwb_requested_at' ),
                    $order->get_formatted_billing_full_name(),
                    $order->get_billing_email(),
                    $order->get_total(),
                    $order->get_currency(),
                    (string) $order->get_meta( '_pcwb_refund_account' ),
                    (string) $order->get_meta( '_pcwb_reason' ),
                    (string) $order->get_meta( '_pcwb_source' ),
                    $order->get_status(),
                    (string) $order->get_meta( '_pcwb_resolved_at' ),
                ] );
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream.
        fclose( $out );
        exit;
    }

    private static function current_state() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list filter parameter; access already gated by 'manage_woocommerce' capability.
        return isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
    }

    private static function current_search() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list search parameter; access already gated by 'manage_woocommerce' capability.
        return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    }

    private static function current_from() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list filter parameter; access already gated by 'manage_woocommerce' capability.
        $from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : '';
    }

    private static function current_to() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list filter parameter; access already gated by 'manage_woocommerce' capability.
        $to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to : '';
    }
}
