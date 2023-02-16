# TranSafe Payments for WooCommerce

The TranSafe Payments plugin extends the functionality of WooCommerce, allowing your online store to accept credit card payments from all major credit card brands. Payment fields on your store's checkout page are generated in a secure iframe hosted by the TranSafe Gateway, so sensitive data such as credit card numbers are never entered into your site's front end or sent through your site's servers. The appearance of the secure payment fields is fully customizable with CSS, so they can blend in seamlessly with the look and feel of your checkout process.

## Installation

We recommend using the WordPress 'Plugins' screen to install the TranSafe Payments plugin. This will download all of the necessary files directly to your server. Alternatively, you can obtain the plugin files from [WordPress.org](https://wordpress.org/plugins/transafe-payments-for-woocommerce/) or [GitHub](https://github.com/Monetra/transafe_payments_for_woocommerce) and manually upload them to your server. 

## Configuration

Once the files are in place on your server, follow these steps to configure the plugin:

1. Go to the 'Plugins' screen in your site's admin section, locate "TranSafe Payments for WooCommerce", and activate the plugin by clicking the "Activate" link.
2. On the same page, click the "Settings" link underneath the plugin name. This will take you to the plugin's 'Settings' screen, where you can configure the plugin.

## Authentication

Starting with version 2.0.0, the TranSafe Payments plugin uses API key authentication instead of username/password authentication. If you already have a username and password configured, the plugin will continue to work as it did previously. However, going forward you will be unable to set a new username or password. Upon upgrading from 1.x to 2.0.0 or later, we highly recommend updating your configuration to use API keys for improved security.

For testing purposes, you can configure the plugin to point to our test server and 
use our public test credentials to generate an API key. 
This will allow you to begin testing your checkout process right away. 

1. On the Settings screen in the Payment Processing section, set the "Payment Server" configuration value to "TranSafe Test Server".
2. Click the "Generate API Key" button. A dialog box will pop up.
3. Enter these credentials in the dialog box:
	- Username: `test_ecomm:public`
	- Password: `publ1ct3st`
4. Click "Submit" or press Enter. The dialog box will close, and you'll see that the API Key ID and API Key Secret configuration fields are now populated.
5. Click "Save changes" at the bottom of the page.

With an API key generated and saved in your configuration, you will have a functional test checkout page. You can see a list of available test cards [here](https://www.monetra.com/test-server). 

To use TranSafe in a production setting, you will need a TranSafe Gateway 
merchant account. If you don't have an account yet, visit 
[transafe.com](https://www.transafe.com) or contact us at info@transafe.com.
