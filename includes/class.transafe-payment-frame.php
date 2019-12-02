<?php

defined('ABSPATH') or exit;

class TransafePaymentFrame {

	private $config;

	public function __construct($config)
	{
		$this->config = $config;
	}

	public function getHtml()
	{
		$iframe_attributes = $this->getIframeAttributes();

		$iframe_attribute_string_parts = [];
		foreach ($iframe_attributes as $key => $value) {
			$iframe_attribute_string_parts[] = $key . '="' . $value . '"';
		}
		$iframe_attribute_string = implode(' ', $iframe_attribute_string_parts);

		$html = [];

		$html[] = '<div id="wc-transafe-iframe-container">';
		$html[] = '<iframe ' . $iframe_attribute_string . '></iframe>';
		$html[] = '</div>';

		return implode('', $html);
	}

	private function getIframeAttributes()
	{
		$hmac_fields = $this->getHmacFields();
		$hmac = $this->generateHmac($hmac_fields);

		$iframe_name = 'wc-transafe-iframe-' . uniqid();

		$iframe_attributes = [
			'id' => 'wc-transafe-iframe',
			'name' => $iframe_name,
			'data-payment-form-host' => $this->config['payment-server-origin'],
			'data-hmac-hmacsha256' => $hmac
		];

		foreach ($hmac_fields as $key => $value) {
			$iframe_attributes['data-hmac-' . $key] = $value;
		}

		return $iframe_attributes;
	}

	private function getHmacFields()
	{
		$host_domain = 'https://' . $_SERVER['HTTP_HOST'];
		$monetra_username = $this->config['user'];

		$hmac_fields = [];

		$hmac_fields["timestamp"] = time();

		$hmac_fields["domain"] = $host_domain;

		$hmac_fields["sequence"] = bin2hex(random_bytes(16));

		$hmac_fields["username"] = $monetra_username;

		if (!empty($this->config['css-url'])) {
			$hmac_fields["css-url"] = $host_domain . "/" . $this->config['css-url'];
		}

		$hmac_fields["include-cardholdername"] = $this->config['include-cardholdername'];
		$hmac_fields["include-street"] = $this->config['include-street'];
		$hmac_fields["include-zip"] = $this->config['include-zip'];
		$hmac_fields["expdate-format"] = $this->config['expdate-format'];

		$hmac_fields["auto-reload"] = $this->config['auto-reload'];
		$hmac_fields["autocomplete"] = $this->config['autocomplete'];

		$hmac_fields["include-submit-button"] = $this->config['include-submit-button'];

		return $hmac_fields;
	}

	private function generateHmac($hmac_fields)
	{
		$monetra_password = $this->config['password'];

		$data_to_hash = implode("", $hmac_fields);

		$hmac = hash_hmac('sha256', $data_to_hash, $monetra_password);

		return $hmac;
	}

}
