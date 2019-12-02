jQuery(function($) {

	var server_select = $('#woocommerce_transafe_server');
	var host_input_container = $('#woocommerce_transafe_host').closest('tr');
	var port_input_container = $('#woocommerce_transafe_port').closest('tr');

	server_select.change(function() {

		if (server_select.val() == 'custom') {
			host_input_container.show();
			port_input_container.show();
		} else {
			host_input_container.hide();
			port_input_container.hide();
		}

	});

	server_select.change();

});
