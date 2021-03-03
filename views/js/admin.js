jQuery(document).ready(function($) {

	var path = OAUTHSSO_AJAX_PATH;
	var token = OAUTHSSO_AJAX_TOKEN;

	/* Autodetect API Connection Handler */
	jQuery('#OASSO_VERIFY_CONNECTION_HANDLER').click(function() {

		var message_string;
		var message_container;
		var is_success;

		var oauth_server_name = jQuery('#OASSO_OAUTH_SERVER_NAME').val();
		var data = {
			'oasso_action' : 'autodetect_api_connection_handler',
			'oasso_oauth_server_name' : oauth_server_name,
			'oasso_token' : token
		};

		message_container = jQuery('#OASSO_VERIFY_CONNECTION_HANDLER_RESULT');
		message_container.removeClass('oasso_success_message oasso_error_message').addClass('oasso_working_message');
		message_container.html(MSG_oauthsso['contacting_api']);

		jQuery.post(path, data, function(response) {

			/* Radio Boxes */
			var radio_curl = jQuery("#OASSO_API_HANDLER_CURL");
			var radio_fsockopen = jQuery("#OASSO_API_HANDLER_FSOCKOPEN");
			var radio_443 = jQuery("#OASSO_API_PORT_443");
			var radio_80 = jQuery("#OASSO_API_PORT_80");

			radio_curl.prop("checked", false);
			radio_fsockopen.prop("checked", false);
			radio_443.prop("checked", false);
			radio_80.prop("checked", false);

			/* CURL detected */
			if (response == 'success_autodetect_api_curl_https') {
				is_success = true;
				radio_curl.prop("checked", true);
				radio_443.prop("checked", true);
				message_string = MSG_oauthsso['curl_on_443'] + ' - ' + MSG_oauthsso['save_changes'];
			}	else if (response == 'success_autodetect_api_fsockopen_https') {
				is_success = true;
				radio_fsockopen.prop("checked", true);
				radio_443.prop("checked", true);
				message_string = MSG_oauthsso['fsocopen_on_443'] + ' - ' + MSG_oauthsso['save_changes'];
			}	else if (response == 'success_autodetect_api_curl_http') {
				is_success = true;
				radio_curl.prop("checked", true);
				radio_80.prop("checked", true);
				message_string = MSG_oauthsso['curl_on_80'] + ' - ' + MSG_oauthsso['save_changes'];
			} else if (response == 'success_autodetect_api_fsockopen_http') {
				is_success = true;
				radio_fsockopen.prop("checked", true);
				radio_80.prop("checked", true);
				message_string = MSG_oauthsso['fsockopen_on_80'] + ' - ' + MSG_oauthsso['save_changes'];
			} else {
				/* No handler detected */
				is_success = false;
				radio_curl.prop("checked", true);
				radio_443.prop("checked", true);
				message_string = MSG_oauthsso['autodetect_error'];
			}

			message_container.removeClass('oasso_working_message');
			message_container.html(message_string);

			if (is_success) {
				message_container.addClass('oasso_success_message');
			} else {
				message_container.addClass('oasso_error_message');
			}
		});
		return false;
	});

	/* Test OAuth 2.0 Settings */
	jQuery('#OASSO_VERIFY_CONNECTION_SETTINGS').click(function() {

		var message_string;
		var message_container;
		var is_success;

		var radio_curl_val = jQuery("#OASSO_API_HANDLER_CURL:checked").val();
		var radio_fsockopen_val = jQuery("#OASSO_API_HANDLER_FSOCKOPEN:checked").val();
		var radio_use_port_443 = jQuery("#OASSO_API_PORT_443:checked").val();
		var radio_use_port_80 = jQuery("#OASSO_API_PORT_80:checked").val();

		var oauth_server_name = jQuery('#OASSO_OAUTH_SERVER_NAME').val();
		var client_id = jQuery('#OASSO_CLIENT_ID').val();
		var client_secret = jQuery('#OASSO_CLIENT_SECRET').val();
		var handler = (radio_fsockopen_val == 'fsockopen' ? 'fsockopen' : 'curl');
		var port = (radio_use_port_80 == 1 ? 80 : 443);

		var data = {
		  'oasso_action' : 'check_api_settings',
			'oasso_token' : token,
		  'oasso_oauth_server_name' : oauth_server_name,
		  'oasso_client_id' : client_id,
		  'oasso_client_secret' : client_secret,
		  'oasso_api_connection_port': port,
		  'oasso_api_connection_handler' : handler
		};

		message_container = jQuery('#OASSO_VERIFY_CONNECTION_SETTINGS_RESULT');
		message_container.removeClass('oasso_success_message oasso_error_message').addClass('oasso_working_message');
		message_container.html(MSG_oauthsso['contacting_oauth']);

		jQuery.post(path, data, function(response) {
			is_success = false;

			if (response == 'error_selected_handler_faulty'   ||
			    response == 'error_not_all_fields_filled_out' ||
					response == 'error_server_name_wrong'         ||
					response == 'error_server_name_wrong_syntax'  ||
					response == 'error_communication'             ||
					response == 'error_authentication_credentials_wrong') {
				message_string = MSG_oauthsso[response];
			} else if (response == 'success') {
				is_success = true;
				message_string = MSG_oauthsso[response] + ' - ' + MSG_oauthsso['save_changes'];
			} else {
				message_string = MSG_oauthsso['unknow'];
			}

			message_container.removeClass('oasso_working_message');
			message_container.html(message_string);

			if (is_success) {
				message_container.addClass('oasso_success_message');
			} else {
				message_container.addClass('oasso_error_message');
			}
		});
		return false;
	});

	$('input[type=radio][name=OASSO_ROLE_MAPPING_ENABLE]').change(function() {
    if (this.value == '1') {
			jQuery("#OASSO_ROLE_MAPPING_PANE").show();
    } else {
			jQuery("#OASSO_ROLE_MAPPING_PANE").hide();
    }
  });
});
