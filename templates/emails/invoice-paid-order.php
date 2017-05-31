<?php
/**
 * Invoice paid email
 */

 if ( ! defined( 'ABSPATH' ) ) {
     exit;
 }

 /**
  * @hooked WC_Emails::email_header() Output the email header
  */
 do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

 <p><?php printf( __( 'O pagamento para a ordem #%d para o endere&ccedil;o %s foi recebido na quantidade de %s %s.<br>Por favor, tome todas as medidas necessárias para preenchimento e marque como completas. Se a ordem é composta inteiramente de mercadorias virtuais geridas pela WooCommerce, nenhuma outra a&ccedil;ão é necessária.<br>A ordem é a seguinte:', 'dashpay-woocommerce' ), $order->id, $invoice->address, $invoice->orderTotal, $invoice->paymentCurrency ); ?></p>

 <?php


 /**
  * @hooked WC_Emails::order_details() Shows the order details table.
  * @since 2.5.0
  */
 do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::order_meta() Shows order meta data.
  */
 do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::customer_details() Shows customer details
  * @hooked WC_Emails::email_address() Shows email address
  */
 do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::email_footer() Output the email footer
  */
 do_action( 'woocommerce_email_footer', $email );
