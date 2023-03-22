<?php

/*
Plugin Name: WooCommerce Pay with Credit Card
Plugin URI: https://www.goshopier.com
Description: Pay with Credit Card for woocommerce
Version: 1.0.4
Author: Shopier
Author URI: https://www.goshopier.com
*/

add_action('plugins_loaded', 'woocommerce_shopier_init', 0);
function woocommerce_shopier_init(){

  	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}
	
	add_action( 'woocommerce_order_status_changed', 'edit_order_status', 100);
 	
	class WC_Shopier extends WC_Payment_Gateway{
		public function __construct(){
			$this->id = 'shopier';
			$this->medthod_title = $this->getLangText('Pay wit Credit Card');
			$this->has_fields = false;
			
			$this->init_form_fields();
			$this->init_settings();
			
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->api_key = $this->settings['api_key'];
			$this->secret = $this->settings['secret'];
			$this->payment_endpoint_url = $this->settings['payment_endpoint_url'];
			$this->shipping_endpoint_url = $this->settings['shipping_endpoint_url'];
			$this->cancel_endpoint_url = $this->settings['cancel_endpoint_url'];
			$this->website_index = $this->settings['website_index'];
			$this->msg['message'] = "";
			$this->msg['class'] = "";
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			
			$this->callback = str_replace( 'https:', 'http:', home_url( '/wc-api/WC_Shopier' )  );
			
			add_action( 'woocommerce_api_wc_shopier', array( &$this, 'check_shopier_response' ) );          
			add_action('woocommerce_receipt_shopier', array(&$this, 'receipt_page'));
		}
		public function getLangText($text){
			if(!isset($this->shopierText)) {
				$lang = trim(get_bloginfo("language"));
				$lang_file =  ABSPATH . "wp-content/plugins/shopier-payment-gateway-woocommerce/lang/{$lang}.php";
				if (!file_exists($lang_file)){
					$lang_file =  ABSPATH . "wp-content/plugins/shopier-payment-gateway-woocommerce/lang/en-US.php";
				}
				require_once( $lang_file );
				$this->shopierText = $shopierText;
			}
			if (isset($this->shopierText[$text]) && !empty($this->shopierText[$text])) {
				return $this->shopierText[$text];
			} else {
				return $text;
			}
		}
		public function init_form_fields(){
		   $this->form_fields = array(
				'enabled' => array(
					'title' => $this->getLangText('Enable/Disable'),
					'type' => 'checkbox',
					'label' => $this->getLangText('Enable Shopier Module'),
					'default' => 'no'
				),
				'title' => array(
					'title' => $this->getLangText('Title:'),
					'type'=> 'text',
					'description' => $this->getLangText('This controls the title which the user sees during checkout.'),
					'default' => $this->getLangText('Pay with Credit Card')
				),
				'description' => array(
					'title' => $this->getLangText('Description:'),
					'type' => 'textarea',
					'description' => $this->getLangText('This controls the description which the user sees during checkout.'),
					'default' => $this->getLangText('Pay securely by Shopier Module.')
				),
				'api_key' => array(
					'title' => $this->getLangText('API Key'),
					'type' => 'text',
					'description' => $this->getLangText('This obtained by user from Shopier panel')
				),
				'secret' => array(
					'title' => $this->getLangText('Secret'),
					'type' => 'password',
					'description' =>  $this->getLangText('This obtained by user from Shopier panel'),
				),
				'payment_endpoint_url' => array(
					'title' => $this->getLangText('Payment Endpoint URL'),
					'type' => 'text',
					'default' => $this->getLangText('https://www.shopier.com/ShowProduct/api_pay4.php')
				),
				'shipping_endpoint_url' => array(
					'title' => $this->getLangText('Shipping Endpoint URL'),
					'type' => 'text',
					'default' => $this->getLangText('https://www.shopier.com/pg_sandbox/pg_shipping_info.php')
				),
				'cancel_endpoint_url' => array(
					'title' => $this->getLangText('Cancel Endpoint URL'),
					'type' => 'text',
					'default' => $this->getLangText('https://www.shopier.com/pg_sandbox/pg_cancel.php')
				),
				
				'website_index' => array(
				'title' => $this->getLangText('Website Index'),
				'type'=>'text',
				'default'=>$this->getLangText('1')
				),
				'callback' => array(
					'title' => $this->getLangText('Response URL'),
					'type' => 'hidden',
					'description' => '<span style="margin-top: -17px; position: absolute;">'.$this->getLangText('I certify that I have provided Shopier with the proper Response URL:').' <strong>'.str_replace( 'https:', 'http:', home_url( '/wc-api/WC_Shopier' )  ).'</strong></span>',
				),
			);
		}
		public function admin_options(){
			echo '<h3>'.$this->getLangText('Shopier Module').'</h3>';
			echo '<table class="form-table">';
			$this -> generate_settings_html();
			echo '</table>';
		}
		public function payment_fields(){
			if($this->description) echo wpautop(wptexturize($this->description));
		}
		public function receipt_page($order){
			echo '<p>'.$this->getLangText('Thank you for your order, please click the button below to pay with Credit Card.').'</p>';
			echo $this -> generate_shopier_form($order);
		}
		public function generate_shopier_form($order_id){
			global $woocommerce;
		
			$order = new WC_Order($order_id);
			
			$user_id = $order->user_id;
			$user = new WP_User($user_id);
			$user_registered = $user->user_registered;
			$time_elapsed = time() - strtotime($user_registered);
			$buyer_account_age = (int)($time_elapsed/86400);
			
			$currency = $order->get_order_currency();
			if ($currency == 'USD') {
				$currency = 1;
			} else if ($currency == 'EUR') {
				$currency = 2;
			} else {
				$currency = 0;
			}
			
			$productinfo = "";
			$producttype = '2';
			
			$items = $order->get_items();
			foreach ($items as $item) {
				$productinfo .= $item['name'].';';
				$product_id = $item['product_id'];
				
				if($producttype != 0 && get_post_meta($product_id,'_virtual',true) == 'yes'){
					$producttype = 1;
				} else if($producttype != 0 && get_post_meta($product_id,'_downloadable',true) == 'yes'){
					$producttype = 1;
				} else {
					$producttype = 0;
				}
			}
			$productinfo = str_replace('"','',$productinfo);
			$productinfo = str_replace('&quot;','',$productinfo);
			$current_language=get_bloginfo("language");
			$current_lan=0;
			if ($current_language == "tr-TR") { $current_lan=0; }
			$modul_version=('1.0.6');
			srand(time(NULL));
			$random_number=rand(100000,999999);
			$args = array(
			  'API_key' => $this->api_key,
			  'website_index' => $this->website_index,
			  'platform_order_id' => $order_id,
			  'product_name' => $productinfo,
			  'product_type' => $producttype,
			  'buyer_name' => $order->billing_first_name,
			  'buyer_surname' => $order->billing_last_name,
			  'buyer_email' => $order->billing_email,
			  'buyer_account_age' => $buyer_account_age,
			  'buyer_id_nr' => $user_id,
			  'buyer_phone' => $order->billing_phone,
			  'billing_address' => $order->billing_address_1.' '.$order->billing_address_2.' '.$order->billing_state,
			  'billing_city' => $order->billing_city,
			  'billing_country' => $order->billing_country,
			  'billing_postcode' => $order->billing_postcode,
			  'shipping_address' => $order->shipping_address_1.' '.$order->shipping_address_2.' '.$order->shipping_state,
			  'shipping_city' => $order->shipping_city,
			  'shipping_country' => $order->shipping_country,
			  'shipping_postcode' => $order->shipping_postcode,
			  'total_order_value' => $order->order_total,
			  'currency' => $currency,
			  'platform' => 0,
			  'is_in_frame' => 0,
			  'current_language'=>$current_lan,
			  'modul_version'=>$modul_version,
			  'random_nr' => $random_number
			);
			
			$data = $args["random_nr"].$args["platform_order_id"].$args["total_order_value"].$args["currency"];
			$signature = hash_hmac(SHA256,$data,$this->secret,true);
			$signature = base64_encode($signature);
			$args['signature'] = $signature;
		
			$args_array = array();
			foreach($args as $key => $value){
			  $args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}
			
			return '
			<form action="'.$this->payment_endpoint_url.'" method="post" id="shopier_payment_form">
				' . implode('', $args_array) . '
				<input type="submit" class="button-alt" id="submit_shopier_payment_form" value="'.$this->getLangText('Pay via Shopier').'" /> 
				<a class="button cancel" href="'.$order->get_cancel_order_url().'">
				'.$this->getLangText('Cancel order & restore cart').'
				</a>
				<script type="text/javascript">
					jQuery(function(){
					jQuery("body").block({
						message: "'.$this->getLangText('Thank you for your order. We are now redirecting you to Payment Gateway to make payment').'",
						overlayCSS:
						{
							background: "#fff",
								opacity: 0.6
						},
						css: {
							padding:        20,
								textAlign:      "center",
								color:          "#555",
								border:         "3px solid #aaa",
								backgroundColor:"#fff",
								cursor:         "wait",
								lineHeight:"32px"
						}
					});
					jQuery("#submit_shopier_payment_form").click();});
				</script>
			</form>';
		}
		public function process_payment($order_id){
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => add_query_arg('order',
				//$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			$order->id, add_query_arg('key', $order->order_key,$order->get_checkout_payment_url(true)))
		
			);
		}
		public function check_shopier_response(){
			global $woocommerce;
			if (isset($_REQUEST['platform_order_id'])) {
				$order_id = $_REQUEST['platform_order_id'];
				$status = $_REQUEST['status'];
				$payment_id = $_REQUEST['payment_id'];
				$installment = $_REQUEST['installment'];
				$random_nr = $_REQUEST['random_nr'];
				if ($order_id != '') {
					try {
						$order = new WC_Order($order_id);
						
						$signature = base64_decode($_POST["signature"]);
						$expected=hash_hmac(SHA256,$random_nr.$order_id,$this->secret,true);
		
						$transauthorised = false;
						if($order->status !=='completed'){
							if ($signature == $expected) {
								$status = strtolower($status);
								if ($status=="success") {
									$transauthorised = true;
									$this->msg['message'] = $this->getLangText('Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.');
									$this -> msg['class'] = 'woocommerce_message';
									if($order->status == 'processing') {
		
									} else {
										$order->payment_complete();
										update_post_meta($order_id, 'Shopier Payment ID', $payment_id);
										update_post_meta($order_id, 'Shopier Installment', $installment);
										$order->add_order_note($this->getLangText('Shopier payment successful'));
										$order->add_order_note($this->msg['message']);
										$woocommerce->cart->empty_cart();
										wp_redirect($this->get_return_url( $order ));
									}
								} else {
									$this->msg['class'] = 'woocommerce_error';
									$this->msg['message'] = $this->getLangText('An error occurred in payment.The transaction has been declined.');
									$order->add_order_note($this->getLangText('Transaction Declined: ').$_REQUEST['error_message']);
								}
							} else {
								$this->msg['class'] = 'error';
								$this->msg['message'] = $this->getLangText('Security Error. Illegal access detected');
							}
							
							add_action('the_content', array(&$this, 'showMessage'));
							if ($transauthorised == false) {
								$order->update_status('failed');
								$order->add_order_note('Failed');
								$order->add_order_note($this->msg['message']);
								wc_add_notice($this->msg['message'],'error');
								$redirect_url = wc_get_page_permalink( 'checkout' );
								wp_redirect($redirect_url);
							}
						}
					} catch(Exception $e){
						$msg = "Error";
					}
				}
			}
		}
		public function showMessage($content){
			return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
		}
	}
  
	/**
	 * Add the Gateway to WooCommerce
	 **/
	function woocommerce_add_shopier_gateway($methods) {
			$methods[] = 'WC_Shopier';
			return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_shopier_gateway' );
	
	function edit_order_status($order_id){
		$settings = get_option('woocommerce_shopier_settings');
		$order = new WC_Order($order_id);
		$status = $order->get_status();
		if($status == 'completed') {
			$args = array(
			  'API_key' => $settings['api_key'],
			  'platform_order_id' => $order_id,
			  'cargo_company' => $order->get_shipping_method(),
			  'tracking_number' => 'NA'
			);
			
			$data = implode('',$args);
			$signature = hash_hmac(SHA256,$data,$settings['secret'],true);
			$signature = base64_encode($signature);
			$args['signature'] = $signature;
			$curl = curl_init($settings['shipping_endpoint_url']); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
			curl_setopt($curl, CURLOPT_TIMEOUT, 30); 
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($args));
			$data = curl_exec($curl); 
			if (curl_error($curl)) { 
				curl_close($curl);
			} else {
				curl_close($curl);
			}
		} else if($status == 'cancelled' || $status == 'refunded') {
			$args = array(
			  'API_key' => $settings['api_key'],
			  'platform_order_id' => $order_id,
			);
			
			$data = implode('',$args);
			$signature = hash_hmac(SHA256,$data,$settings['secret'],true);
			$signature = base64_encode($signature);
			$args['signature'] = $signature;
			$curl = curl_init($settings['cancel_endpoint_url']); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
			curl_setopt($curl, CURLOPT_TIMEOUT, 30); 
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($args));
			$data = curl_exec($curl); 
			if (curl_error($curl)) { 
				curl_close($curl);
			} else {
				curl_close($curl);
			}
		}
	}
	function getLangTextOutside($text){
		$lang = trim(get_bloginfo("language"));
		$lang_file =  ABSPATH . "wp-content/plugins/shopier-payment-gateway-woocommerce/lang/{$lang}.php";
		if (!file_exists($lang_file)){
			$lang_file =  ABSPATH . "wp-content/plugins/shopier-payment-gateway-woocommerce/lang/en-US.php";
		}
		require_once( $lang_file );
		if (isset($shopierText[$text]) && !empty($shopierText[$text])) {
			return $shopierText[$text];
		} else {
			return $text;
		}
	}
}
