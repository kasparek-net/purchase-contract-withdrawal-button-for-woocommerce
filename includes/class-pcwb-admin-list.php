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
        ];
    }

    public function get_views() {
        $base    = remove_query_arg( [ 'state', 'paged' ] );
        $current = self::current_state();
        $counts  = $this->get_state_counts();
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
        $args     = $this->build_query_args( $per_page, $current );

        $query = wc_get_orders( $args );
        $count_args = $args;
        unset( $count_args['paged'], $count_args['orderby'], $count_args['order'] );
        $count_args['return'] = 'ids';
        $count_args['limit']  = 9999;
        $all_ids = wc_get_orders( $count_args );
        $total   = is_array( $all_ids ) ? count( $all_ids ) : 0;

        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items = is_array( $query ) ? $query : [];
        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ] );
    }

    private function get_state_counts() {
        $ids = wc_get_orders( [
            'limit'        => 9999,
            'return'       => 'ids',
            'meta_key'     => '_pcwb_requested_at',
            'meta_compare' => 'EXISTS',
        ] );
        $ids = is_array( $ids ) ? $ids : [];

        $pending  = 0;
        $resolved = 0;
        foreach ( $ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }
            if ( $order->get_meta( '_pcwb_resolved_at' ) ) {
                $resolved++;
            } else {
                $pending++;
            }
        }
        return [
            ''         => count( $ids ),
            'pending'  => $pending,
            'resolved' => $resolved,
        ];
    }

    /**
     * Build wc_get_orders args from current request.
     *
     * @param int  $per_page Per-page limit.
     * @param int  $paged    Current page (1-based).
     * @param bool $ignore_state When true, skip the state filter (for view counts).
     */
    private function build_query_args( $per_page, $paged, $ignore_state = false ) {
        $args = [
            'limit'        => $per_page,
            'paged'        => $paged,
            'orderby'      => 'meta_value',
            'order'        => 'DESC',
            'meta_key'     => '_pcwb_requested_at',
            'meta_compare' => 'EXISTS',
            'meta_query'   => [
                [
                    'key'     => '_pcwb_requested_at',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        if ( ! $ignore_state ) {
            $state = self::current_state();
            if ( 'resolved' === $state ) {
                $args['meta_query'][] = [
                    'key'     => '_pcwb_resolved_at',
                    'compare' => 'EXISTS',
                ];
            } elseif ( 'pending' === $state ) {
                $args['meta_query'][] = [
                    'key'     => '_pcwb_resolved_at',
                    'compare' => 'NOT EXISTS',
                ];
            }
        }

        $search = self::current_search();
        if ( $search !== '' ) {
            $args['search'] = '*' . $search . '*';
        }

        $from = self::current_from();
        $to   = self::current_to();
        if ( $from || $to ) {
            $args['meta_query'][] = [
                'key'     => '_pcwb_requested_at',
                'value'   => [ $from ? $from . ' 00:00:00' : '0000-00-00 00:00:00', $to ? $to . ' 23:59:59' : '9999-12-31 23:59:59' ],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ];
        }

        return $args;
    }

    public function column_cb( $order ) {
        return sprintf( '<input type="checkbox" name="orders[]" value="%d" />', $order->get_id() );
    }

    public function column_default( $order, $column_name ) {
        switch ( $column_name ) {
            case 'order':
                $url = $order->get_edit_order_url();
                return sprintf( '<a href="%s">#%s</a>', esc_url( $url ), esc_html( $order->get_order_number() ) );
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
        if ( 'resolve' !== $this->current_action() ) {
            return;
        }
        check_admin_referer( 'bulk-withdrawals' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        $ids = isset( $_REQUEST['orders'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['orders'] ) ) : [];
        $resolved = 0;
        foreach ( $ids as $id ) {
            $order = wc_get_order( $id );
            if ( $order ) {
                $result = PCWB_Frontend::resolve( $order, get_current_user_id() );
                if ( true === $result ) {
                    $resolved++;
                }
            }
        }
        if ( $resolved > 0 ) {
            add_action( 'admin_notices', function () use ( $resolved ) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html( sprintf(
                        /* translators: %d: number of withdrawals resolved */
                        _n( '%d withdrawal marked as resolved.', '%d withdrawals marked as resolved.', $resolved, 'purchase-contract-withdrawal-button-for-woocommerce' ),
                        $resolved
                    ) )
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

    public static function export_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'purchase-contract-withdrawal-button-for-woocommerce' ), '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'pcwb_export_csv' );

        $table = new self();
        $args  = $table->build_query_args( -1, 1 );
        $args['paginate'] = false;
        unset( $args['paged'] );
        $orders = wc_get_orders( $args );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=pcwb-withdrawals-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
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
        fclose( $out );
        exit;
    }

    private static function current_state() {
        return isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
    }

    private static function current_search() {
        return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    }

    private static function current_from() {
        $from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : '';
    }

    private static function current_to() {
        $to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to : '';
    }
}
