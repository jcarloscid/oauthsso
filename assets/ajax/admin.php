<?php
/**
 * @package   	OAuth Single SignOn
 * @author        Carlos Cid <carlos@fishandbits.es>
 * @copyright 	Copyleft 2021 http://fishandbits.es
 * @license   	GNU/GPL 2 or later
  *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,USA.
 *
 * The "GNU General Public License" (GPL) is available at
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 */
include_once('../../../../config/config.inc.php');
include_once('../../../../init.php');
include_once('../../../../modules/oauthsso/includes/functions.php');

// Otherwise it will not work in various browsers.
header('Access-Control-Allow-Origin: *');

// Security Check.
if (Tools::getValue('oasso_action') != '' and (Tools::getValue('oasso_token') == sha1(_COOKIE_KEY_ . 'OAUTH20SSO'))) {
  switch (Tools::getValue ('oasso_action')) {
		// ****** AUTODETECT CONNECTION HANDLER
		case 'autodetect_api_connection_handler' :
			$oauth_server_name = trim(Tools::getValue('oasso_oauth_server_name'));

			if (OAuthSSOHelper::check_curl(true, $oauth_server_name) === true) {
				// Check CURL HTTPS - Port 443
				die('success_autodetect_api_curl_https');
			} elseif (OAuthSSOHelper::check_curl(false, $oauth_server_name) === true) {
				// Check CURL HTTP - Port 80
				die('success_autodetect_api_curl_http');
			} elseif (OAuthSSOHelper::check_fsockopen(true, $oauth_server_name) == true) {
				// Check FSOCKOPEN HTTPS - Port 443
				die('success_autodetect_api_fsockopen_https');
			} elseif (OAuthSSOHelper::check_fsockopen(false, $oauth_server_name) == true) {
				// Check FSOCKOPEN HTTP - Port 80
				die('success_autodetect_api_fsockopen_http');
			}

			// No working handler found
			die('error_autodetect_api_no_handler');
		break;

		// ****** CHECK CONNECTION SETTINGS
		case 'check_api_settings' :
			// OAuth 2.0 Credentials
			$oauth_server_name = trim (Tools::getValue('oasso_oauth_server_name'));
			$client_id = trim (Tools::getValue('oasso_client_id'));
			$client_secret = trim (Tools::getValue('oasso_client_secret'));

			// API Settings
			$api_connection_port = trim(Tools::getValue('oasso_api_connection_port'));
			$api_connection_port = ($api_connection_port == 80 ? 80 : 443);
			$api_connection_use_https = ($api_connection_port == 443);

			$api_connection_handler = trim(Tools::getValue('oasso_api_connection_handler'));
			$api_connection_handler = ($api_connection_handler == 'fsockopen' ? 'fsockopen' : 'curl');

			// Check if all fields have been filled out
			if (empty($oauth_server_name) or empty($client_id) or empty($client_secret)) {
				die('error_not_all_fields_filled_out');
			}

			// Check FSOCKOPEN
			if ($api_connection_handler == 'fsockopen') {
				if (!OAuthSSOHelper::check_fsockopen($api_connection_use_https, $oauth_server_name)) {
					die('error_selected_handler_faulty');
				}
			} else {
				// Check CURL
				if (!OAuthSSOHelper::check_curl($api_connection_use_https, $oauth_server_name)) {
					die('error_selected_handler_faulty');
				}
			}

			// Check server name format
			$valid_ip_address_regex = "/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/i";
			$valid_host_name_regex = "/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/i";
			if ( !preg_match($valid_ip_address_regex, $oauth_server_name) and
			     !preg_match($valid_host_name_regex, $oauth_server_name) ) {
				die('error_server_name_wrong_syntax');
			}

			// Connection to
			$api_resource_url = ($api_connection_use_https ? 'https' : 'http') . '://' . $oauth_server_name . '/oauth/token';
			$api_resource_cmd = 'post';

			// Simulated call to API
			$result = OAuthSSOHelper::do_oauth_request($api_connection_handler, $api_resource_url, $api_resource_cmd, array('grant_type' => 'client_credentials', 'client_id' => $client_id, 'client_secret' => $client_secret), 15);

			// Parse result
			if (is_object($result) and property_exists($result, 'http_code') and property_exists($result, 'http_data')) {
				switch ($result->http_code) {
					// Success
					case 200 :
						die('success');
						break;

					// Bad request
					case 400 :
						$response = json_decode($result->http_data);
						if (is_object ($response) and property_exists($response, 'error')) {
							// Identity server does not support client_credentials grant type
							// but that means that identity server is up and running and that
							// credentials are valid.
							if ( $response->error == 'unauthorized_client' ) {
								die('success');
							} elseif ( $response->error == 'invalid_client' ) {
								// This means an error on the credentials
								die('error_authentication_credentials_wrong');
							}
						}
						die('error_communication');
					break;

					// Authentication Error
					case 401 :
						die('error_authentication_credentials_wrong');
					break;

					// Wrong server name / is not an OAuth Identity Server
					case 404 :
						die('error_server_name_wrong');
					break;

					// Other error
					default:
						die('error_communication');
					break;
				}
			}	else {
				die('error_communication');
			}

			die('error_unknown_workflow');
			break;
	}
}
