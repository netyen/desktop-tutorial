<?php
/**
* Plugin Name: Odeme URL
* Plugin URI: https://example.com/
* Description: Woocommerce sipariş ödeme tutarını Odeme URL'ye post eder
* Version: 1.0
* Author: [Author Name]
* Author URI: https://example.com/
* Text Domain: odeme-url
* Domain Path: /languages
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'plugins_loaded', 'odeme_url_init' );

function odeme_url_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Odeme_URL_Gateway extends WC_Payment_Gateway {

        public function __construct() {

            $this->id                 = 'odeme_url';
            $this->icon               = apply_filters( 'woocommerce_odeme_url_icon', '' );
            $this->has_fields         = false;
            $this->method_title       = __( 'Odeme URL', 'odeme-url' );
            $this->method_description = __( 'Woocommerce sipariş ödeme tutarını Odeme URL\'ye post eder', 'odeme-url' );

            $this->init_settings();
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou', array( $this, 'odeme_url_post' ) );
        }

        public function odeme_url_post( $order_id ) {

            $order = wc_get_order( $order_id );
            $order_total = $order->get_total();
            $odeme_url = 'https://odeme.tatlicivciv.com/odeme/index.php';
			
			$order_data = $order->get_data();
$post_data = array(
    'siparis_id' => $order_id,
    'toplam_tutar' => $order_total,
    'ad_soyad' => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
    'email' => $order_data['billing']['email'],
    'telefon' => $order_data['billing']['phone'],
    // diğer sipariş verilerini buraya ekleyin
);


            $post_data = array(
                'siparis_id' => $order_id,
                'toplam_tutar' => $order_total
            );

            $response = wp_remote_post(
                $odeme_url,
                array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'body' => $post_data,
                    'cookies' => array()
                )
            );
        }
    }

    function add_odeme_url_gateway_class($methods)
    {
        $methods[] = 'WC_Odeme_URL_Gateway';
        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'add_odeme_url_gateway_class' );
}
?>
