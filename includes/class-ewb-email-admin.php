<?php
/**
 * Admin notification of a new withdrawal.
 *
 * @package EUcomplyWithdrawalButton
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
    return;
}

class EWB_Email_Admin extends WC_Email {

    public function __construct() {
        $this->id          = 'ewb_admin_withdrawal';
        $this->title       = __( 'Withdrawal notification (admin)', 'eucomply-withdrawal-button' );
        $this->description = __( 'Sent to the store administrator when a customer submits a withdrawal.', 'eucomply-withdrawal-button' );

        $this->template_html  = 'emails/admin-withdrawal-notification.php';
        $this->template_plain = 'emails/plain/admin-withdrawal-notification.php';
        $this->template_base  = EWB_TEMPLATE_PATH;

        $this->placeholders = [
            '{site_title}'   => $this->get_blogname(),
            '{order_number}' => '',
        ];

        add_action( 'ewb_admin_withdrawal_email', [ $this, 'trigger' ], 10, 3 );

        parent::__construct();

        $this->recipient = $this->get_option( 'recipient', ewb_get_admin_recipient() );
    }

    public function get_default_subject() {
        return __( '[{site_title}] New withdrawal request — order #{order_number}', 'eucomply-withdrawal-button' );
    }

    public function get_default_heading() {
        return __( 'New withdrawal request', 'eucomply-withdrawal-button' );
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

        $this->object  = $order;
        $this->reason  = $reason;
        $this->account = $account;

        $this->placeholders['{order_number}'] = $order->get_order_number();

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'order'              => $this->object,
                'reason'             => $this->reason ?? '',
                'account'            => $this->account ?? '',
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => true,
                'plain_text'         => false,
                'email'              => $this,
            ],
            '',
            $this->template_base
        );
    }

    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            [
                'order'              => $this->object,
                'reason'             => $this->reason ?? '',
                'account'            => $this->account ?? '',
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => true,
                'plain_text'         => true,
                'email'              => $this,
            ],
            '',
            $this->template_base
        );
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled'    => [
                'title'   => __( 'Enable/Disable', 'eucomply-withdrawal-button' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'eucomply-withdrawal-button' ),
                'default' => 'yes',
            ],
            'recipient'  => [
                'title'       => __( 'Recipient(s)', 'eucomply-withdrawal-button' ),
                'type'        => 'text',
                'description' => sprintf(
                    /* translators: %s: admin email */
                    __( 'Enter recipients (comma separated). Defaults to %s.', 'eucomply-withdrawal-button' ),
                    '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
                ),
                'placeholder' => '',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'subject'    => [
                'title'       => __( 'Subject', 'eucomply-withdrawal-button' ),
                'type'        => 'text',
                /* translators: %s: list of available placeholders */
                'description' => sprintf( __( 'Placeholders: %s', 'eucomply-withdrawal-button' ), '<code>{site_title}, {order_number}</code>' ),
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'heading'    => [
                'title'       => __( 'Email heading', 'eucomply-withdrawal-button' ),
                'type'        => 'text',
                /* translators: %s: list of available placeholders */
                'description' => sprintf( __( 'Placeholders: %s', 'eucomply-withdrawal-button' ), '<code>{site_title}, {order_number}</code>' ),
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'email_type' => [
                'title'       => __( 'Email type', 'eucomply-withdrawal-button' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'eucomply-withdrawal-button' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ],
        ];
    }
}
