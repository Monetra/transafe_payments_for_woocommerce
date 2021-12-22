<?php
/**
 * Plugin Name:       TranSafe Payments for WooCommerce
 * Plugin URI:        https://www.transafe.com
 * Description:       Accept credit card payments using TranSafe Gateway
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            Monetra Technologies, LLC
 * Author URI:        https://www.monetra.com
 * License:           MIT
 */

defined('ABSPATH') or exit;

function wc_transafe_missing_wc_notice() {
	echo 
		'<div class="error"><p><strong>TranSafe requires the WooCommerce plugin to be installed and active.' .  
		'<br />You can install or activate WooCommerce from the Plugins section here in your Wordpress admin interface.</strong></p></div>';
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	add_action('admin_notices', 'wc_transafe_missing_wc_notice');
	return;
}

function wc_transafe_add_to_gateways($gateways) {
	$gateways[] = 'WC_Transafe';
	return $gateways;
}

add_action('plugins_loaded', 'wc_transafe_init');

add_filter('woocommerce_payment_gateways', 'wc_transafe_add_to_gateways');

function wc_transafe_plugin_action_links($links) {
	$plugin_links = [
		'<a href="admin.php?page=wc-settings&tab=checkout&section=transafe">Settings</a>'
	];
	return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_transafe_plugin_action_links');

function wc_transafe_init() {

	class WC_Transafe extends WC_Payment_Gateway {

		const TEST_SERVER_URL = 'https://test.transafe.com';
		const TEST_SERVER_PORT = '443';

		const LIVE_SERVER_URL = 'https://post.live.transafe.com';
		const LIVE_SERVER_PORT = '443';

		public function __construct() {

			$this->id                 = 'transafe';
			$this->has_fields         = true;
			$this->method_title       = 'TranSafe Payments';
			$this->method_description = 'Accept credit card payments using TranSafe Gateway.';

			$this->init_form_fields();
			$this->init_settings();

			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );

			$this->supports = ['refunds'];

			require_once dirname(__FILE__) . '/includes/class.transafe-payment-frame.php';

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
			add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
			add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

		}

		public function init_form_fields() {

			$fields = require(dirname(__FILE__) . '/includes/admin/settings.php');

			$this->form_fields = apply_filters('wc_transafe_form_fields', $fields);

		}

		public function payment_fields() {

			$config = [
				'user' => $this->get_option('user'),
				'password' => $this->get_option('password'),
				'css-url' => $this->get_option('css_url'),
				'include-cardholdername' => 'no',
				'include-street' => 'no',
				'include-zip' => 'no',
				'expdate-format' => $this->get_option('expdate_format'),
				'auto-reload' => $this->get_option('auto_reload'),
				'autocomplete' => $this->get_option('autocomplete'),
				'include-submit-button' => 'no',
				'payment-server-origin' => $this->paymentServerOrigin()
			];

			$paymentframe = new TransafePaymentFrame($config);

			echo $paymentframe->getHtml();
		}

		public function admin_scripts() {
			wp_register_style('admin', plugins_url('assets/css/admin.css', __FILE__ ), [], false);
			wp_enqueue_style('admin');

			wp_register_script('admin', plugins_url('assets/js/admin.js', __FILE__ ), [], false, true);
			wp_enqueue_script('admin');
		}

		public function payment_scripts() {

			wp_register_style('checkout', plugins_url('assets/css/checkout.css', __FILE__ ), [], false);
			wp_enqueue_style('checkout');

			$server = $this->get_option('server');

			$paymentframe_script_domain = $this->paymentServerOrigin();

			wp_register_script('paymentframe', $paymentframe_script_domain . '/PaymentFrame/PaymentFrame.js', [], false, true);
			wp_register_script('checkout', plugins_url('assets/js/checkout.js', __FILE__ ), [], false, true);
			
			wp_enqueue_script('paymentframe');
			wp_enqueue_script('checkout');
		}

		public function process_payment($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);

			if (empty($_POST['transafe_payment_ticket'])) {

				if (!empty($_POST['transafe_payment_error'])) {
					$error_message = sanitize_text_field($_POST['transafe_payment_error']);
				} else {
					$error_message = $this->get_option('payment_error_notice');
				}
				wc_add_notice($error_message, 'error');
				return;

			}

			$ticket = sanitize_text_field($_POST['transafe_payment_ticket']);

			$payment_response = $this->sendPaymentToPaymentServer($ticket, $order);

			if ($payment_response['code'] === 'AUTH') {
			
				$order->payment_complete($payment_response['ttid']);
				
				wc_reduce_stock_levels($order);
				
				$woocommerce->cart->empty_cart();
				
				return [
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url($order)
				];

			} else {

				wc_add_notice($this->get_option('declined_payment_notice'), 'error');

				return;

			}
		}

		public function process_refund($order_id, $amount = null, $reason = '') {

			$order = new WC_Order($order_id);

			$refund_response = $this->sendRefundToPaymentServer($amount, $order);

			if (!empty($refund_response) && $refund_response['code'] === 'AUTH') {

				return true;

			} else {

				return false;

			}

		}

		private function sendPaymentToPaymentServer($payment_ticket, $order)
		{
			$path = 'transaction/purchase';

			$cardholdername = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$street = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
			$zip = $order->get_billing_postcode();
			$amount = $order->get_total();
			$tax = $order->get_total_tax();
			$ordernum = $order->get_order_number();
			$capture = $this->get_option('capture');

			$transaction_data = [
				'account_data' => [
					'cardshieldticket' => $payment_ticket,
					'cardholdername' => $cardholdername
				],
				'verification' => [
					'street' => $street,
					'zip' => $zip
				],
				'money' => [
					'amount' => $amount,
					'tax' => $tax
				],
				'order' => [
					'ordernum' => $ordernum
				],
				'processing_options' => [
					'capture' => $capture
				]
			];

			$transaction_response = $this->sendTransafeApiRequest($path, 'POST', $transaction_data);

			return $transaction_response;
		}

		private function sendRefundToPaymentServer($refund_amount, $order) {

			$ttid = $order->get_transaction_id();
			$ordernum = $order->get_order_number();
			$order_total = $order->get_total();

			$transaction_details = $this->sendTransafeApiRequest("transaction/$ttid", 'GET');

			if ($transaction_details['code'] !== 'AUTH') {

				error_log(
					"Unable to retrieve prior transaction details from payment server. Response verbiage: " .
					$transaction_details['verbiage']
				);
				return null;

			}
			
			$status_flags = explode('|', $transaction_details['txnstatus']);

			if (in_array('COMPLETE', $status_flags)) {
				$method = 'POST';
				$path = "transaction/$ttid/refund";
				$data = [
					'money' => [
						'amount' => $refund_amount
					],
					'order' => [
						'ordernum' => $ordernum
					]
				];
			} else {

				/* Do not allow partial void/reversal */
				if ($refund_amount < $order_total) {
					error_log('Partial void of an unsettled transaction is not allowed.');
					return null;
				}

				$method = 'DELETE';
				$path = "transaction/$ttid";
				$data = null;
			}

			$refund_response = $this->sendTransafeApiRequest($path, $method, $data);

			if ($refund_response['code'] !== 'AUTH') {
				error_log(
					'Unable to process refund through payment server. Response verbiage: ' . 
					$refund_response['verbiage']
				);
			}

			return $refund_response;
		}

		private function paymentServerOrigin()
		{
			$server = $this->get_option('server');

			if ($server === 'test') {

				return self::TEST_SERVER_URL . ':' . self::TEST_SERVER_PORT;
			
			} elseif ($server === 'live') {
				
				return self::LIVE_SERVER_URL . ':' . self::LIVE_SERVER_PORT;
			
			} else {

				$custom_host = $this->get_option('host');

				if (strpos($custom_host, 'https://') !== 0) {
					if (strpos($custom_host, 'http://') === 0) {
						$custom_host = str_replace('http://', 'https://', $custom_host);
					} else {
						$custom_host = 'https://' . $custom_host;
					}
				}
				
				return $custom_host . ':' . $this->get_option('port');
				
			}
		}

		private function sendTransafeApiRequest($path, $method, $data = null)
		{
			$url = $this->paymentServerOrigin() . '/api/v1/' . $path;
			$username = str_replace(':', '|', $this->get_option('user'));
			$password = $this->get_option('password');

			$headers = [
				"Authorization" => "Basic " . base64_encode($username . ':' . $password)
			];

			if ($method === 'GET') {

				if (!empty($data)) {
					$url .= '?' . http_build_query($data);
				}
				$response = wp_remote_get($url, [
					'headers' => $headers
				]);

			} elseif ($method === 'POST') {

				$request_body = json_encode($data);
				$headers["Content-Type"] = "application/json";
				$headers["Content-Length"] = strlen($request_body);

				$response = wp_remote_post($url, [
					'headers' => $headers,
					'body' => $request_body
				]);

			} else {
				$params = [
					'method' => $method,
					'headers' => $headers
				];
				if (!empty($data)) {
					$params['body'] = json_encode($data);
				}

				$response = wp_remote_request($url, $params);
			}

			$response_body = wp_remote_retrieve_body($response);

			return json_decode($response_body, true);
		}

	}

}
