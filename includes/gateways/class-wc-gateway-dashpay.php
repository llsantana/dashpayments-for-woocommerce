<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Gateway_DashPay' ) ) :

/**
 * Dash Payment Gateway
 *
 * Provides a Dash Payment Gateway
 *
 * @class    WC_Gateway_DashPay
 * @extends  WC_Payment_Gateway
 * @version  0.0.1
 * @package  DashPayments
 * @author   "Black Carrot Ventures" <nmarley@blackcarrot.be>
 */
class WC_Gateway_DashPay extends WC_Payment_Gateway {
    private static $currency = 'Dash';
    private static $currency_ticker_symbol = 'DASH';
    private static $currency_symbol = 'Đ';

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
    public function __construct() {
        $this->id                 = DP_Gateways::gateway_id( self::$currency );
        $this->icon               = plugins_url('/assets/images/dash-32x.png', DP_PLUGIN_FILE);
        $this->has_fields         = false;
        $this->method_title       = __('Dash', 'dashpay-woocommerce');
        $this->method_description = __('Pague com Dash - Digital Cash', 'dashpay-woocommerce');

        // Init settings
        $this->init_form_fields();
        $this->init_settings();

        // Use settings
        $this->enabled     = $this->settings['enabled'];
        $this->title       = $this->settings['title'];
        $this->description = $this->settings['description'];

        $this->xpub_key = $this->settings['xpub_key'];
        $this->confirmations = $this->settings['confirmations'];
        $this->exchange_multiplier = $this->settings['exchange_multiplier'];

        // a trailing slash will break insight api
        $this->settings['insight_api_url'] = rtrim($this->settings['insight_api_url'], '/');

        // hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page') );
        add_action( 'woocommerce_thankyou', array( $this, 'add_ajax_order_check') );
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

        // Effectively broken, so not going to implement this 'til WordPress fixes it
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }


    /**
     * Check if this gateway is enabled and all dependencies are fine.
     * Disable the plugin if dependencies fail.
     *
     * @access      public
     * @return      bool
     */
    public function is_available() {
        if ( $this->enabled === 'no' ) {
            return false;
        }

        // ensure required extensions
        if ( 0 !== count(DP()->missing_required_extensions()) ) {
            return false;
        }

        // Validate settings
        if ( ! $this->xpub_key ) {
            return false;
        }

        // validate xpub key
        if ( ! CoinUtil::is_valid_public_xkey( $this->xpub_key ) ) {
            return false;
        }

        // Must be a valid insight instance that we can connect to
        $insight = new DP_Insight_API( $this->settings['insight_api_url'] );
        if ( ! $insight->is_valid() ) {
            return false;
        }

        // ensure we can fetch exchange rate
        $exchange_rate;
        try {
            DP_Exchange_Rate::get_exchange_rate(self::$currency_ticker_symbol, get_woocommerce_currency());
        }
        catch (\Exception $e) {
            return false;
        }

        // ensure matching xpub and insight networks
        $xpub_network = CoinUtil::guess_network_from_xkey( $this->correct_xpub_key() );

        $insight_network;
        try {
            $insight_network = $insight->get_network();
        }
        catch (\Exception $ex) {
            return false;
        }

        if ( $xpub_network != $insight_network ) {
            return false;
        }

        return true;
    }


    /**
     * Check if this gateway is enabled and available for the store's default currency
     *
     * @access public
     * @return bool
     */
    protected function check_valid() {
        if ( $this->enabled == 'no') {
            return __('O gateway n&atilde;o est&aacute; ativado.', 'dashpay-woocommerce');
        }

        // required PHP extensions
        $missing = DP()->missing_required_extensions();
        if ( 0 !== count($missing) ) {
            $msg = sprintf(
                esc_html__("Extens&otilde;es necess&aacute;rias n&atilde;o carregadas / habilitadas. Ative a extens&atilde;o '%s' em seu servidor WordPress.", 'dashpay-woocommerce'),
                join(', ', $missing)
            );
            return $msg;
        }

        // Ensure xpub entered
        if ( ! $this->xpub_key ) {
            $msg = __("Por favor adicione uma chave p&uacute;blica Dash BIP32 (Abra sua carteira Electrum-Dash , Selecione  Carteira-> Master Public Keys)", 'dashpay-woocommerce');
            return $msg;
        }

        // Ensure valid xpub
        if ( ! CoinUtil::is_valid_public_xkey( $this->xpub_key ) ) {
            $msg = __("Chave xpub inv&aacute;lida.", 'dashpay-woocommerce');
            return $msg;
        }

        // Must be a valid insight instance that we can connect to
        $insight = new DP_Insight_API( $this->settings['insight_api_url'] );
        if ( ! $insight->is_valid() ) {
            $msg = __('Insight-API erro na conex&atilde;o ou status da rede', 'dashpay-woocommerce');
            return $msg;
        }

        // ensure can connect to exchange rate webservice
        $exchange_rate;
        try {
            $exchange_rate = DP_Exchange_Rate::get_exchange_rate(self::$currency_ticker_symbol, get_woocommerce_currency());
        }
        catch (\Exception $e) {
            $msg = __('Erro ao buscar taxa de c&acirc;mbio.');
            return $msg;
        }


        // ensure matching xpub and insight networks
        $xpub_network = CoinUtil::guess_network_from_xkey( $this->correct_xpub_key() );
        try {
            $insight_network = $insight->get_network();
        }
        catch (\Exception $ex) {
            $msg = __('Error Insight-API.', 'dashpay-woocommerce');
            return $msg;
        }

        if ( $xpub_network != $insight_network ) {
            $msg = sprintf(
                esc_html__('Discrep&acirc;ncia da rede. Rede para chave xpub determinada como %s, mas Insight-API inst&acirc;ncia est&aacute; em execu&ccedil;&atilde;o %s.', 'dashpay-woocommerce'),
                $xpub_network,
                $insight_network
            );
            return $msg;
        }

        return null;
    }


    /**
     * Send notices to users if requirements fail, or for any other reason
     *
     * @access      public
     * @return      bool
     */
    public function admin_notices() {
        if ( $this->enabled == 'no') {
            return false;
        }

        $missing = DP()->missing_required_extensions();
        if ( 0 !== count($missing) ) {
            $msg = sprintf(
                __("<strong>DashPayments para WooCommerce:</strong> Extens&otilde;es necess&aacute;rias n&atilde;o carregadas / habilitadas. Ative a extens&atilde;o '% s' do PHP no seu servidor WordPress.", 'dashpay-woocommerce'),
                join(', ', $missing)
            );
            self::_admin_error( $msg );
            return false;
        }

        // Check for xpub key
        if ( ! $this->xpub_key ) {
            self::_admin_error( __("Por favor adicione uma chave p&uacute;blica Dash BIP32 (Abra sua carteira Electrum-Dash , Selecione  Carteira-> Master Public Keys)", 'dashpay-woocommerce') );
            return false;
        }

        // valid xpub key
        if ( ! CoinUtil::is_valid_public_xkey( $this->xpub_key ) ) {
            $msg = __("Chave xpub inv&aacute;lida.", 'dashpay-woocommerce');
            self::_admin_error( $msg );
            return false;
        }

        // Must be a valid insight instance that we can connect to
        $insight = new DP_Insight_API( $this->settings['insight_api_url'] );
        $insight_valid = false;
        try {
            $insight_valid = $insight->is_valid();
        }
        catch (\Exception $e) {
            $insight_valid = false;
        }

        if ( ! $insight_valid ) {
            $msg = __('Insight-API erro na conex&atilde;o ou status da rede', 'dashpay-woocommerce');
            self::_admin_error( $msg );
            return false;
        }

        // ensure can connect to exchange rate webservice
        $exchange_rate;
        try {
            $exchange_rate = DP_Exchange_Rate::get_exchange_rate(self::$currency_ticker_symbol, get_woocommerce_currency());
        }
        catch (\Exception $e) {
            $msg = __('Erro ao buscar taxa de c&acirc;mbio.');
            self::_admin_error( $msg );
            return false;
        }


        // ensure matching xpub and insight networks
        $xpub_network = CoinUtil::guess_network_from_xkey( $this->correct_xpub_key() );
        try {
            $insight_network = $insight->get_network();
        }
        catch (\Exception $ex) {
            $msg = __('Erro Insight-API.', 'dashpay-woocommerce');
            self::_admin_error( $msg );
            return false;
        }

        if ( $xpub_network != $insight_network ) {
            $msg = sprintf(
                esc_html__('Discrep&acirc;ncia da rede. Rede para chave xpub determinada como %s, mas Insight-API inst&acirc;ncia est&aacute; em execu&ccedil;&atilde;o %s.', 'dashpay-woocommerce'),
                $xpub_network,
                $insight_network
            );
            self::_admin_error( $msg );
            return false;
        }

        return true;
    }



    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
          'enabled' => array(
            'title' => __( 'Ativar/Desativar', 'dashpay-woocommerce' ),
            'type' => 'checkbox',
            'label' => __( 'Ativar Dash Payments', 'dashpay-woocommerce' ),
            'default' => 'yes'
          ),
          'title' => array(
            'title' => __( 'T&iacute;tulo', 'dashpay-woocommerce' ),
            'type' => 'text',
            'description' => __( 'Isso controla o t&iacute;tulo que o usu&aacute;rio vê durante o checkout.', 'dashpay-woocommerce' ),
            'default' => __( 'Pague com Dash', 'dashpay-woocommerce' ),
            // 'desc_tip'    => true,
          ),
          'xpub_key' => array(
            'title' => __( "Dash BIP32 Chave p&uacute;blica estendida", 'dashpay-woocommerce' ),
            'type' => 'textarea',
            'default' => '',
            'description' => __('V&aacute; em  <a href="https://www.dash.org/downloads" target="_blank">Carteira Electrum Dash</a> e pegue a Chave P&uacute;blica Master de:<br>Carteira -> Master Public Key<br>Copie e Cole o texto come&ccedil;&atilde;ndo com "xpub" desntro deste campo.', 'dashpay-woocommerce'),
            // 'desc_tip'    => true,
          ),
          'confirmations' => array(
            'title' => __( 'Confirma&ccedil;&otilde;es necess&aacute;rias', 'dashpay-woocommerce' ),
            'type' => 'text',
            'description' => __( 'N&uacute;mero de confirmations necess&aacute;rias antes do pagamento ser considerado realizado.', 'dashpay-woocommerce' ),
            'default' => '6',
            // 'desc_tip'    => true,
          ),
          'exchange_multiplier' => array(
            'title' => __('Multiplicador de taxa de c&acirc;mbio', 'dashpay-woocommerce' ),
            'type' => 'text',
            'description' => 'Multiplicador extra para aplicar para converter a moeda padr&atilde;o da loja para o pre&ccedil;o.',
            'default' => '1.00',
            // 'desc_tip'    => true,
          ),
          'insight_api_url' => array(
            'title' => __('Url Insight API ', 'dashpay-woocommerce' ),
            'description' => 'Este plugin requer uma inst&acirc;ncia em execu&ccedil;&atilde;o do Insight-API. Você pode usar o padr&atilde;o, ou fornecer o seu próprio para maior seguran&ccedil;a.',
            'type' => 'text',
            'default' => 'https://insight.dash.org',
            // 'desc_tip'    => true,
          ),
          'description' => array(
            'title'       => __( 'Descri&ccedil;&atilde;o', 'woocommerce' ),
            'type' => 'textarea',
            'description' => __( 'Descri&ccedil;&atilde;o do método de pagamento que o cliente ver&aacute; na sua conta.', 'dashpay-woocommerce' ),
            'default' => __( 'V&aacute; para a próxima tela para ver os detalhes de pagamento necess&aacute;rios.', 'dashpay-woocommerce' ),
            // 'desc_tip'    => true,
          ),
        );
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @access public
     * @return void
     */
    public function admin_options() {
        echo '<h3>' . __('Pague com Dash', 'dashpay-woocommerce') . "</h3>\n";
        echo '<p>' . __('Permite pagamentos diretos do Dash com WooCommerce. <a href="https://www.dash.org/" target="_blank">Dash</a> é moeda digital peer-to-peer, descentralizada que permite pagamentos instant&acirc;neos de qualquer pessoa para qualquer pessoa, em qualquer lugar do mundo.', 'dashpay-woocommerce') . "</p>\n";

        $error_message = $this->check_valid();

        if ( $error_message ) {
            $msg = sprintf(
                esc_html__('Gateway de pagamento Dash N&atilde;O est&aacute; operacional. %s', 'dashpay-woocommerce'),
                $error_message
            );
            self::_OLD_admin_error( $msg );
        }
        else {
            self::_OLD_admin_success(__('Gateway de pagamento Dash est&aacute; operacional.','dashpay-woocommerce'));
        }

        // Generate the HTML for the settings form
        echo "<table class=\"form-table\">\n";
        $this->generate_settings_html();
        echo "</table>\n";
    }

    // Hook into admin options saving
    public function process_admin_options() {
        parent::process_admin_options();
    }

    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order($order_id);

        add_action( 'wp_enqueue_scripts', array( $this, 'add_ajax_order_check') );

        try {
            // Pull exchange rate
            $exchange_rate = DP_Exchange_Rate::get_exchange_rate(self::$currency_ticker_symbol, get_woocommerce_currency());
        }
        catch (\Exception $e) {
            $msg = "Erro ao buscar taxa de c&acirc;mbio.";
            exit ('<h2 style="color:red;">' . $msg . '</h2>');
        }

        $order_total_in_coins = bcdiv(
            (string) $order->get_total(),
            (string) $exchange_rate,
            8
        );

        if ( 1.0 != $exchange_rate ) {
            $order_total_in_coins = bcmul(
              (string) $order_total_in_coins,
              (string) $this->exchange_multiplier,
              8
            );
        }

        $expires_in_hours = 1;
        $expires_at = (time() + ($expires_in_hours * 60 * 60));

        $order_info = array(
            'order_id' => $order_id,
            'payment_currency' => self::$currency,
            'xpub' => $this->correct_xpub_key(),
            'expires_at' => $expires_at,
            'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
            'order_total_coins' => $order_total_in_coins,
        );

        $invoice = DP_Invoice::create( $order_info );

        $woocommerce->cart->empty_cart();

        return array(
          'result'   => 'success',
          'redirect'    => $this->get_return_url( $order )
        );
    }

    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
    public function thankyou_page( $order_id ) {
        $invoice = new DP_Invoice($order_id);

        // wrap instructions here in this div...
        $instructions = '<div id="payment_instructions">' .
          $invoice->paymentInstructionsHTML()
          . '</div>';
        echo wpautop(wptexturize($instructions));
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @return void
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        $invoice = new DP_Invoice( $order->id );
        if ( !$sent_to_admin && 'dashpay-woocommerce' === $order->payment_method && $order->has_status('pending') ) {
            echo wpautop(wptexturize( $invoice->paymentInstructionsHTML() )) . PHP_EOL;
        }
    }

    // == WooCommerce Hooks
    public static function add_gateway_to_woocommerce( $methods ) {
        $methods[] = __CLASS__;
        return $methods;
    }

    public static function add_this_currency($currencies) {
        $currencies[self::$currency_ticker_symbol] = __( self::$currency, 'dashpay-woocommerce' );
        return $currencies;
    }

    public static function add_this_currency_symbol($currency_symbol, $currency) {
        switch( $currency ) {
            case self::$currency_ticker_symbol:
                $currency_symbol = self::$currency_symbol;
                break;
        }
        return $currency_symbol;
    }

    /**
     * Javascript for checking order status from an Ajax call
     *
     * @access public
     * @return void
     */
    public function add_ajax_order_check() {
        wp_enqueue_script( 'jquery-base64', plugins_url( '/assets/js/jquery.base64.js?' . time(), DP_PLUGIN_FILE), array( 'jquery' ));
        wp_enqueue_script( 'check_order_status', plugins_url( '/assets/js/check_order_status.js?' . time(), DP_PLUGIN_FILE), array( 'jquery' ));
        wp_localize_script(
            'check_order_status',
            'obj',
            array( 'ajaxurl' => WC()->ajax_url() )
        );
    }

    /**
     * Return properly serialized extended public key
     *
     * @access protected
     * @return void
     */
    protected function correct_xpub_key() {
        // must be valid xpub
        if ( ! CoinUtil::is_valid_public_xkey( $this->xpub_key ) ) {
            return '';
        }
        $correct_xpub_key = CoinUtil::reserialize_key( $this->xpub_key, self::$currency );
        return $correct_xpub_key;
    }

    protected static function _admin_error($msg) {
        echo '<div class="notice notice-error"><p>' . $msg . '</p></div>';
    }

    protected static function _OLD_admin_error($msg) {
        echo '<p style="border:1px solid #ebccd1;padding:5px 10px;font-weight:bold;color:#a94442;background-color:#f2dede;">' . $msg . '</p>';
    }

    protected static function _OLD_admin_success($msg) {
        echo '<p style="border:1px solid #d6e9c6;padding:5px 10px;font-weight:bold;color:#3c763d;background-color:#dff0d8;">' . $msg . '</p>';
    }

}

endif;

return 'WC_Gateway_DashPay';

