<?php
/**
 * Customer withdrawal confirmation email.
 *
 * @package PurchaseContractWithdrawalButtonForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
    return;
}

class PCWB_Email_Customer extends WC_Email {

    public function __construct() {
        $this->id             = 'pcwb_customer_withdrawal';
        $this->customer_email = true;
        $this->title          = __( 'Withdrawal confirmation (customer)', 'purchase-contract-withdrawal-button-for-woocommerce' );
        $this->description    = __( 'Sent to the customer after they submit a withdrawal from a purchase contract.', 'purchase-contract-withdrawal-button-for-woocommerce' );

        $this->template_html  = 'emails/customer-withdrawal-confirmation.php';
        $this->template_plain = 'emails/plain/customer-withdrawal-confirmation.php';
        $this->template_base  = PCWB_TEMPLATE_PATH;

        $this->placeholders = [
            '{site_title}'   => $this->get_blogname(),
            '{order_number}' => '',
            '{order_date}'   => '',
        ];

        add_action( 'pcwb_customer_withdrawal_email', [ $this, 'trigger' ], 10, 3 );

        parent::__construct();
    }

    public function get_default_subject() {
        return __( '[{site_title}] Withdrawal confirmation — order #{order_number}', 'purchase-contract-withdrawal-button-for-woocommerce' );
    }

    public function get_default_heading() {
        return __( 'Withdrawal confirmation', 'purchase-contract-withdrawal-button-for-woocommerce' );
    }

    /**
     * Trigger the email.
     *
     * @param WC_Order $order   Order.
     * @param string   $reason  Customer-provided reason.
     * @param string   $account Customer-provided refund account.
     */
    public function trigger( $order, $reason = '', $account = '' ) {
        if ( ! $this->is_enabled() || ! $order instanceof WC_Order ) {
            return;
        }

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
            ],
            '',
            $this->template_base
        );
    }

    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            [
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
            ],
            '',
            $this->template_base
        );
    }

    public function get_default_additional_content() {
        return __( 'If you have any questions about your withdrawal, please reply to this email.', 'purchase-contract-withdrawal-button-for-woocommerce' );
    }
}
