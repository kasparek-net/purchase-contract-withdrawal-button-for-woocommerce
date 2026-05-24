<?php
/**
 * Settings page under WooCommerce.
 *
 * @package EUcomplyWithdrawalButton
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EWB_Settings {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function menu() {
        add_submenu_page(
            'woocommerce',
            __( 'EUcomply Withdrawal Button', 'eucomply-withdrawal-button' ),
            __( 'Withdrawal Button', 'eucomply-withdrawal-button' ),
            'manage_woocommerce',
            'ewb-settings',
            [ __CLASS__, 'render' ]
        );
    }

    public static function register() {
        register_setting(
            'ewb_settings',
            'ewb_period_days',
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 14,
            ]
        );
        register_setting(
            'ewb_settings',
            'ewb_eligible_statuses',
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_statuses' ],
                'default'           => [ 'completed' ],
            ]
        );
        register_setting(
            'ewb_settings',
            'ewb_admin_recipient',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default'           => '',
            ]
        );
        register_setting(
            'ewb_settings',
            'ewb_new_status',
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

        $period      = (int) get_option( 'ewb_period_days', 14 );
        $statuses    = (array) get_option( 'ewb_eligible_statuses', [ 'completed' ] );
        $recipient   = (string) get_option( 'ewb_admin_recipient', '' );
        $new_status  = (string) get_option( 'ewb_new_status', 'on-hold' );
        $all_statuses = wc_get_order_statuses();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'EUcomply Withdrawal Button', 'eucomply-withdrawal-button' ); ?></h1>

            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce email settings URL */
                    esc_html__( 'Customer and admin email templates can be customized in %s.', 'eucomply-withdrawal-button' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Emails', 'eucomply-withdrawal-button' ) . '</a>'
                );
                ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( 'ewb_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ewb_period_days"><?php esc_html_e( 'Cooling-off period (days)', 'eucomply-withdrawal-button' ); ?></label>
                        </th>
                        <td>
                            <input type="number" min="1" max="365" id="ewb_period_days" name="ewb_period_days" value="<?php echo esc_attr( $period ); ?>" class="small-text">
                            <p class="description"><?php esc_html_e( 'How long after the reference date (order completion) the withdrawal button stays visible. EU standard is 14 days.', 'eucomply-withdrawal-button' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Eligible order statuses', 'eucomply-withdrawal-button' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ( $all_statuses as $status_key => $status_label ) :
                                    $clean = str_replace( 'wc-', '', $status_key );
                                    ?>
                                    <label style="display:block;">
                                        <input type="checkbox" name="ewb_eligible_statuses[]" value="<?php echo esc_attr( $clean ); ?>" <?php checked( in_array( $clean, $statuses, true ) ); ?>>
                                        <?php echo esc_html( $status_label ); ?>
                                        <code><?php echo esc_html( $clean ); ?></code>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e( 'The button is shown only for orders in one of these statuses.', 'eucomply-withdrawal-button' ); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ewb_admin_recipient"><?php esc_html_e( 'Admin email recipient', 'eucomply-withdrawal-button' ); ?></label>
                        </th>
                        <td>
                            <input type="email" id="ewb_admin_recipient" name="ewb_admin_recipient" value="<?php echo esc_attr( $recipient ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: admin email */
                                    esc_html__( 'Leave empty to use the site admin email (%s).', 'eucomply-withdrawal-button' ),
                                    '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ewb_new_status"><?php esc_html_e( 'Status after submission', 'eucomply-withdrawal-button' ); ?></label>
                        </th>
                        <td>
                            <select id="ewb_new_status" name="ewb_new_status">
                                <?php foreach ( $all_statuses as $status_key => $status_label ) :
                                    $clean = str_replace( 'wc-', '', $status_key );
                                    ?>
                                    <option value="<?php echo esc_attr( $clean ); ?>" <?php selected( $new_status, $clean ); ?>>
                                        <?php echo esc_html( $status_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Order status applied automatically after the customer submits a withdrawal.', 'eucomply-withdrawal-button' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
