<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DP_Email_Invoice_Paid' ) ) :

/**
 * Invoice Paid Email
 *
 * An email sent to the admin when payment is completed.
 *
 * @class       DP_Email_Invoice_Paid
 * @version     0.0.1
 * @package     DashPayments/Classes/Emails
 * @author      BlackCarrotVentures
 * @extends     WC_Email
 */
class DP_Email_Invoice_Paid extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id               = 'dp_invoice_paid';
        $this->title            = __( 'Fatura paga', 'dashpay-woocommerce' );
        $this->description      = __( 'Email de fatura paga ser&aacute; enviado pela loja quando o pagamento estiver confirmado.', 'dashpay-woocommerce' );
        $this->heading          = __( 'Fatura paga', 'dashpay-woocommerce' );
        $this->subject          = __( '[{site_title}] Fatura paga ({order_number})', 'dashpay-woocommerce' );
        $this->template_html    = 'emails/invoice-paid-order.php';
        $this->template_plain   = 'emails/plain/invoice-paid-order.php';
        $this->template_base    = DP()->template_path();

        // Triggers for this email
        add_action( 'dashpayments_invoice_paid_notification', array( $this, 'trigger' ) );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
    }

    /**
     * Trigger.
     *
     * @param int $order_id
     */
    public function trigger( $order_id ) {
        if ( $order_id ) {
            $this->object                  = wc_get_order( $order_id );
            $this->find['order-date']      = '{order_date}';
            $this->find['order-number']    = '{order_number}';
            $this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $this->object->order_date ) );
            $this->replace['order-number'] = $this->object->get_order_number();
        }

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    /**
     * Get content html.
     *
     * @access public
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html( $this->template_html, array(
            'order'         => $this->object,
            'invoice'       => new DP_Invoice( $this->object->id ),
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => true,
            'plain_text'    => false,
            'email'         => $this
        ), DP()->template_path() , DP()->template_path() );
    }

    /**
     * Get content plain.
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html( $this->template_plain, array(
            'order'         => $this->object,
            'invoice'       => new DP_Invoice( $this->object->id ),
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => true,
            'plain_text'    => true,
            'email'         => $this
        ), DP()->template_path() , DP()->template_path() );
    }

    /**
     * Initialise settings form fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Ativar/Desativar', 'dashpay-woocommerce' ),
                'type'          => 'checkbox',
                'label'         => __( 'Ativar este email de notifica&ccedil;&atilde;o', 'dashpay-woocommerce' ),
                'default'       => 'yes'
            ),
            'recipient' => array(
                'title'         => __( 'Destinat&aacute;rio(s)', 'dashpay-woocommerce' ),
                'type'          => 'text',
                'description'   => sprintf( __( 'Informe os destinat&aacute;rios (separado por vírgula) para este email. Padr&atilde;o <code>%s</code>.', 'dashpay-woocommerce' ), esc_attr( get_option('admin_email') ) ),
                'placeholder'   => '',
                'default'       => '',
                'desc_tip'      => true
            ),
            'subject' => array(
                'title'         => __( 'Assunto', 'dashpay-woocommerce' ),
                'type'          => 'text',
                'description'   => sprintf( __( 'Isso controla a linha de assunto do e-mail. Deixe em branco para usar o assunto padr&atilde;o: <code>%s</code>.', 'dashpay-woocommerce' ), $this->subject ),
                'placeholder'   => '',
                'default'       => '',
                'desc_tip'      => true
            ),
            'heading' => array(
                'title'         => __( 'Cabe&ccedil;alho Email', 'dashpay-woocommerce' ),
                'type'          => 'text',
                'description'   => sprintf( __( 'Isso controla o cabe&ccedil;alho principal contido na notifica&ccedil;&atilde;o por e-mail. Deixe em branco para usar o cabe&ccedil;alho padr&atilde;o: <code>%s</code>.', 'dashpay-woocommerce' ), $this->heading ),
                'placeholder'   => '',
                'default'       => '',
                'desc_tip'      => true
            ),
            'email_type' => array(
                'title'         => __( 'Tipo de Email', 'dashpay-woocommerce' ),
                'type'          => 'select',
                'description'   => __( 'Escolha qual formato de e-mail enviar.', 'dashpay-woocommerce' ),
                'default'       => 'html',
                'class'         => 'email_type wc-enhanced-select',
                'options'       => $this->get_email_type_options(),
                'desc_tip'      => true
            )
        );
    }
}

endif;

return new DP_Email_Invoice_Paid();

