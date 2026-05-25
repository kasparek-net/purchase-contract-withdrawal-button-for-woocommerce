<?php
/**
 * Settings page under WooCommerce.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCWB_Settings {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Purchase Contract Withdrawal Button for WooCommerce', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            __( 'Withdrawal Button', 'purchase-contract-withdrawal-button-for-woocommerce' ),
            'manage_woocommerce',
            'pcwb-settings',
            [ __CLASS__, 'render' ]
        );
    }

    public static function register() {
        register_setting(
            'pcwb_settings',
            'pcwb_period_days',
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 14,
            ]
        );
        register_setting(
            'pcwb_settings',
            'pcwb_eligible_statuses',
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_statuses' ],
                'default'           => [ 'completed' ],
            ]
        );
        register_setting(
            'pcwb_settings',
            'pcwb_admin_recipient',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default'           => '',
            ]
        );
        register_setting(
            'pcwb_settings',
            'pcwb_new_status',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
                'default'           => 'on-hold',
            ]
        );
    }

    public static function sanitize_statuses( $value ) {
        if ( ! is_array( $value ) ) {
            return [ 'completed' ];
        }
        $valid = array_keys( wc_get_order_statuses() );
        $valid = array_map( static fn ( $s ) => str_replace( 'wc-', '', $s ), $valid );
        return array_values( array_intersect( $value, $valid ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $period      = (int) get_option( 'pcwb_period_days', 14 );
        $statuses    = (array) get_option( 'pcwb_eligible_statuses', [ 'completed' ] );
        $recipient   = (string) get_option( 'pcwb_admin_recipient', '' );
        $new_status  = (string) get_option( 'pcwb_new_status', 'on-hold' );
        $all_statuses = wc_get_order_statuses();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Purchase Contract Withdrawal Button for WooCommerce', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></h1>

            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce email settings URL */
                    esc_html__( 'Customer and admin email templates can be customized in %s.', 'purchase-contract-withdrawal-button-for-woocommerce' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Emails', 'purchase-contract-withdrawal-button-for-woocommerce' ) . '</a>'
                );
                ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( 'pcwb_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="pcwb_period_days"><?php esc_html_e( 'Cooling-off period (days)', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></label>
                        </th>
                        <td>
                            <input type="number" min="1" max="365" id="pcwb_period_days" name="pcwb_period_days" value="<?php echo esc_attr( $period ); ?>" class="small-text">
                            <p class="description"><?php esc_html_e( 'How long after the reference date (order completion) the withdrawal button stays visible. EU standard is 14 days.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Eligible order statuses', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ( $all_statuses as $status_key => $status_label ) :
                                    $clean = str_replace( 'wc-', '', $status_key );
                                    ?>
                                    <label style="display:block;">
                                        <input type="checkbox" name="pcwb_eligible_statuses[]" value="<?php echo esc_attr( $clean ); ?>" <?php checked( in_array( $clean, $statuses, true ) ); ?>>
                                        <?php echo esc_html( $status_label ); ?>
                                        <code><?php echo esc_html( $clean ); ?></code>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e( 'The button is shown only for orders in one of these statuses.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pcwb_admin_recipient"><?php esc_html_e( 'Admin email recipient', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></label>
                        </th>
                        <td>
                            <input type="email" id="pcwb_admin_recipient" name="pcwb_admin_recipient" value="<?php echo esc_attr( $recipient ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: admin email */
                                    esc_html__( 'Leave empty to use the site admin email (%s).', 'purchase-contract-withdrawal-button-for-woocommerce' ),
                                    '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pcwb_new_status"><?php esc_html_e( 'Status after submission', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></label>
                        </th>
                        <td>
                            <select id="pcwb_new_status" name="pcwb_new_status">
                                <?php foreach ( $all_statuses as $status_key => $status_label ) :
                                    $clean = str_replace( 'wc-', '', $status_key );
                                    ?>
                                    <option value="<?php echo esc_attr( $clean ); ?>" <?php selected( $new_status, $clean ); ?>>
                                        <?php echo esc_html( $status_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Order status applied automatically after the customer submits a withdrawal.', 'purchase-contract-withdrawal-button-for-woocommerce' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
