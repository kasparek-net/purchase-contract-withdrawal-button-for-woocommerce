<?php
/**
 * Customer withdrawal confirmation — HTML.
 *
 * Override by copying to yourtheme/eucomply-withdrawal-button/emails/customer-withdrawal-confirmation.php
 *
 * @package EUcomplyWithdrawalButton
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Hello,', 'eucomply-withdrawal-button' ); ?></p>

<p>
    <?php
    printf(
        /* translators: 1: order number, 2: order date */
        esc_html__( 'We have received your withdrawal from the purchase contract for order no. %1$s placed on %2$s.', 'eucomply-withdrawal-button' ),
        '<strong>' . esc_html( $order->get_order_number() ) . '</strong>',
        esc_html( wc_format_datetime( $order->get_date_created() ) )
    );
    ?>
</p>

<h2><?php esc_html_e( 'Next steps', 'eucomply-withdrawal-button' ); ?></h2>

<ol>
    <li><?php esc_html_e( 'Please return the goods to our address within 14 days of submitting this withdrawal.', 'eucomply-withdrawal-button' ); ?></li>
    <li><?php esc_html_e( 'We recommend shipping with tracking and insurance.', 'eucomply-withdrawal-button' ); ?></li>
    <li>
        <?php
        printf(
            /* translators: %s: total amount */
            esc_html__( 'Once we receive the goods we will refund the paid amount (%s) within 14 days.', 'eucomply-withdrawal-button' ),
            wp_kses_post( wc_price( $order->get_total() ) )
        );
        ?>
    </li>
</ol>

<?php
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
