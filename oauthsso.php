<?php
/**
 * @package       OAuth Single SignOn
 * @author        Carlos Cid <carlos@fishandbits.es>
 * @copyright     Copyleft 2021 http://fishandbits.es
 * @license       GNU/GPL 2 or later
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

if (!defined('_PS_VERSION_')) {
    exit();
}

class OAuthSSO extends Module {

  // Maximum number of roles->group mappings that can be configured
  private const MAX_MAPPINGS = 10;

  // Hooks to install for this module (enabled/disabled by admin panel)
  private const HOOKS = array(
    // Widget
    'displayLeftColumn'             => array('pos' => 1),
    // Widget
    'displayRightColumn'            => array('pos' => 1),
    // Widget
    'displayRightColumnProduct'     => array('pos' => 1),
    // Create account
    'displayCustomerAccountFormTop' => array('pos' => 1),
    // Login
    'displayCustomerLoginFormAfter' => array('pos' => 1),
    // Right menu
    'displayNav2'                   => array('pos' => 1),
    // Callback
    'displayTop'                    => array(),
    // Head
    'displayHeader'                 => array(),
    // Scripts
    'displayFooterAfter'            => array()
  );

  /**
   * Constructor
   */
  public function __construct() {
    $this->name = 'oauthsso';
    $this->tab = 'front_office_features';
    $this->version = '1.0.0';
    $this->author = 'Carlos Cid <carlos@fishandbits.es>';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array(
        'min' => '1.7'
    );

    parent::__construct();

    $this->displayName = $this->l('OAuth 2.0 Single SignOn Client');
    $this->description = $this->l('Provides a mechanism to identify users from an OAuth 2.0 Identity Server, allowing the Single SignOn of users in your entire organization.');
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall OAuth 2.0 Single SignOn Client?');

    // This is the first time that the class is used
    if (!Configuration::get('OASSO_FIRST_INSTALL')) {
      // Setup default values
      Configuration::updateValue('OASSO_FIRST_INSTALL', '1');
      Configuration::updateValue('OASSO_PROVIDER_NAME', 'Single SignOn Provider');
      Configuration::updateValue('OASSO_API_HANDLER', 'curl');
      Configuration::updateValue('OASSO_API_PORT', '443');
      Configuration::updateValue('OASSO_LINK_ACCOUNT_DISABLE', 0);
      Configuration::updateValue('OASSO_JS_HOOK_AUTH_DISABLE', 0);
      Configuration::updateValue('OASSO_JS_HOOK_LOGIN_DISABLE', 0);
      Configuration::updateValue('OASSO_HOOK_LEFT_DISABLE', 0);
      Configuration::updateValue('OASSO_HOOK_RIGHT_DISABLE', 0);
      Configuration::updateValue('OASSO_HOOK_RIGHT_PRODUCT_DISABLE', 0);
      Configuration::updateValue('OASSO_HOOK_NAV_MENU_DISABLE', 0);
      Configuration::updateValue('OASSO_DATA_HANDLING', 'verify');
      Configuration::updateValue('OASSO_EMAIL_CUSTOMER_DISABLE', '0');
      Configuration::updateValue('OASSO_EMAIL_ADMIN_DISABLE', '0');
      Configuration::updateValue('OASSO_CUSTOM_CSS', '');
      Configuration::updateValue('OASSO_CUSTOM_JS', '');

      Configuration::updateValue('OASSO_ROLE_MAPPING_ENABLE', 0);
      Configuration::updateValue('OASSO_ROLES_CONTAINER_PROPERTY', '');
      Configuration::updateValue('OASSO_ROLES_CONTAINER_FORMAT', 'list');
      Configuration::updateValue('OASSO_ROLES_CLEANUP', 0);
      for ($i = 0; $i < self::MAX_MAPPINGS; $i++) {
        Configuration::updateValue('OASSO_ROLES_MAPPING_ROLE_' . $i, '');
        Configuration::updateValue('OASSO_ROLES_MAPPING_GROUP_' . $i, 0);
        Configuration::updateValue('OASSO_ROLES_MAPPING_DEFAULT_' . $i, 0);
      }
    }

    // Requires includes
    require_once dirname(__FILE__) . "/includes/functions.php";
  }

  /**
   * **************************************************************************
   * Administration Area
   * **************************************************************************
   */

  /**
   * Display Admin panel
   */
  public function getContent() {
    // Compute the form url.
    $form_url = 'index.php?';
    foreach ($_GET as $key => $value) {
      if (strtolower($key) != 'submit') {
        $form_url .= $key . '=' . $value . '&';
      }
    }
    $form_url = rtrim($form_url, '&');

    // Add external files.
    $this->context = Context::getContext();
    $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
    $this->context->controller->addJS($this->_path . 'views/js/admin.js');

    // This is what is being displayed.
    $html = '';

    // Submit Button Clicked
    if (Tools::isSubmit('submit')) {
      // Read OAuth Credentials
      $oauth_server_name = strtolower(trim(Tools::getValue('OASSO_OAUTH_SERVER_NAME')));
      $client_id = trim(Tools::getValue('OASSO_CLIENT_ID'));
      $client_secret = trim(Tools::getValue('OASSO_CLIENT_SECRET'));
      $provider_name = htmlspecialchars(stripslashes(trim(Tools::getValue('OASSO_PROVIDER_NAME'))));
      if ( empty($provider_name) ) $provider_name = 'Single SignOn Provider';

      // Read API Connection Settings
      $api_handler = Tools::getValue('OASSO_API_HANDLER');
      $api_handler = ($api_handler == 'fsockopen' ? 'fsockopen' : 'curl');
      $api_port = Tools::getValue('OASSO_API_PORT');
      $api_port = ($api_port == 80 ? 80 : 443);

      // Hook Left
      $hook_left_disable = (Tools::getValue('OASSO_HOOK_LEFT_DISABLE') == 1 ? 1 : 0);

      // Hook Right
      $hook_right_disable = (Tools::getValue('OASSO_HOOK_RIGHT_DISABLE') == 1 ? 1 : 0);

      // Hook Right Product
      $hook_right_product_disable = (Tools::getValue('OASSO_HOOK_RIGHT_PRODUCT_DISABLE') == 1 ? 1 : 0);

      // Navigation menu
      $hook_nav_menu_disable = (Tools::getValue('OASSO_HOOK_NAV_MENU_DISABLE') == 1 ? 1 : 0);

      // JavaScript Hook for Authentication
      $js_hook_auth_disable = (Tools::getValue('OASSO_JS_HOOK_AUTH_DISABLE') == 1 ? 1 : 0);

      // JavaScript Hook for Login Page
      $js_hook_login_disable = (Tools::getValue('OASSO_JS_HOOK_LOGIN_DISABLE') == 1 ? 1 : 0);

      // Email Settins
      $email_customer_disable = (Tools::getValue('OASSO_EMAIL_CUSTOMER_DISABLE') == 1 ? 1 : 0);
      $email_admin_disable = (Tools::getValue('OASSO_EMAIL_ADMIN_DISABLE') == 1 ? 1 : 0);

      // Shop front customization
      $oauth_custom_css = htmlspecialchars(stripslashes(Tools::getValue('OASSO_CUSTOM_CSS')));
      $oauth_custom_js = htmlspecialchars(stripslashes(Tools::getValue('OASSO_CUSTOM_JS')));

      // Roles mapping
      $role_mapping_enable = (Tools::getValue('OASSO_ROLE_MAPPING_ENABLE') == 1 ? 1 : 0);
      $roles_container_property = trim(Tools::getValue('OASSO_ROLES_CONTAINER_PROPERTY'));
      $roles_container_format = Tools::getValue('OASSO_ROLES_CONTAINER_FORMAT');
      $roles_container_format = (in_array($roles_container_format, array(
          'list',
          'array_k',
          'array_v'
      )) ? $roles_container_format : 'list');
      $roles_cleanup = (Tools::getValue('OASSO_ROLES_CLEANUP') == 1 ? 1 : 0);
      $mappings = self::get_roles_mappings('UI');

      // Settings
      $link_account_disable = (Tools::getValue('OASSO_LINK_ACCOUNT_DISABLE') == 1 ? 1 : 0);
      $data_handling = Tools::getValue('OASSO_DATA_HANDLING');
      $data_handling = (in_array($data_handling, array(
          'ask',
          'verify',
          'auto'
      )) ? $data_handling : 'verify');

      // Save Values
      Configuration::updateValue('OASSO_OAUTH_SERVER_NAME', $oauth_server_name);
      Configuration::updateValue('OASSO_CLIENT_ID', $client_id);
      Configuration::updateValue('OASSO_CLIENT_SECRET', $client_secret);
      Configuration::updateValue('OASSO_PROVIDER_NAME', $provider_name);
      Configuration::updateValue('OASSO_API_HANDLER', $api_handler);
      Configuration::updateValue('OASSO_API_PORT', $api_port);
      Configuration::updateValue('OASSO_JS_HOOK_AUTH_DISABLE', $js_hook_auth_disable);
      Configuration::updateValue('OASSO_JS_HOOK_LOGIN_DISABLE', $js_hook_login_disable);
      Configuration::updateValue('OASSO_HOOK_LEFT_DISABLE', $hook_left_disable);
      Configuration::updateValue('OASSO_HOOK_RIGHT_DISABLE', $hook_right_disable);
      Configuration::updateValue('OASSO_HOOK_RIGHT_PRODUCT_DISABLE', $hook_right_product_disable);
      Configuration::updateValue('OASSO_HOOK_NAV_MENU_DISABLE', $hook_nav_menu_disable);
      Configuration::updateValue('OASSO_LINK_ACCOUNT_DISABLE', $link_account_disable);
      Configuration::updateValue('OASSO_DATA_HANDLING', $data_handling);
      Configuration::updateValue('OASSO_EMAIL_ADMIN_DISABLE', $email_admin_disable);
      Configuration::updateValue('OASSO_EMAIL_CUSTOMER_DISABLE', $email_customer_disable);
      Configuration::updateValue('OASSO_CUSTOM_CSS', $oauth_custom_css);
      Configuration::updateValue('OASSO_CUSTOM_JS', $oauth_custom_js);
      Configuration::updateValue('OASSO_ROLE_MAPPING_ENABLE', $role_mapping_enable);
      Configuration::updateValue('OASSO_ROLES_CONTAINER_PROPERTY', $roles_container_property);
      Configuration::updateValue('OASSO_ROLES_CONTAINER_FORMAT', $roles_container_format);
      Configuration::updateValue('OASSO_ROLES_CLEANUP', $roles_cleanup);
      for ($i = 0; $i < self::MAX_MAPPINGS; $i++) {
        Configuration::updateValue('OASSO_ROLES_MAPPING_ROLE_'  . $i, $mappings[$i]['role']);
        Configuration::updateValue('OASSO_ROLES_MAPPING_GROUP_' . $i, $mappings[$i]['id_group']);
        Configuration::updateValue('OASSO_ROLES_MAPPING_GROUP_' . $i, $mappings[$i]['id_group']);
        Configuration::updateValue('OASSO_ROLES_MAPPING_DEFAULT_' . $i, $mappings[$i]['default']);
      }
    }

    // Read OAuth Credentials
    $oauth_server_name = Configuration::get('OASSO_OAUTH_SERVER_NAME');
    $client_id = Configuration::get('OASSO_CLIENT_ID');
    $client_secret = Configuration::get('OASSO_CLIENT_SECRET');
    $provider_name = Configuration::get('OASSO_PROVIDER_NAME');

    // Read API Connection Settings
    $api_handler = Configuration::get('OASSO_API_HANDLER');
    $api_handler = ($api_handler == 'fsockopen' ? 'fsockopen' : 'curl');
    $api_port = Configuration::get('OASSO_API_PORT');
    $api_port = ($api_port == 80 ? 80 : 443);

    // Hook Left
    $hook_left_disable = Configuration::get('OASSO_HOOK_LEFT_DISABLE') == 1 ? 1 : 0;

    // Hook Right
    $hook_right_disable = Configuration::get('OASSO_HOOK_RIGHT_DISABLE') == 1 ? 1 : 0;

    // Hook Right Product
    $hook_right_product_disable = Configuration::get('OASSO_HOOK_RIGHT_PRODUCT_DISABLE') == 1 ? 1 : 0;

    // Hook Nav Menu
    $hook_nav_menu_disable = Configuration::get('OASSO_HOOK_NAV_MENU_DISABLE') == 1 ? 1 : 0;

    // Hook Authentication
    $js_hook_auth_disable = Configuration::get('OASSO_JS_HOOK_AUTH_DISABLE') == 1 ? 1 : 0;

    // Hook Login Page
    $js_hook_login_disable = Configuration::get('OASSO_JS_HOOK_LOGIN_DISABLE') == 1 ? 1 : 0;

    // Shop front customization
    $oauth_custom_css = Configuration::get('OASSO_CUSTOM_CSS');
    $oauth_custom_js = Configuration::get('OASSO_CUSTOM_JS');

    // Roles mapping
    $role_mapping_enable = Configuration::get('OASSO_ROLE_MAPPING_ENABLE');
    $roles_container_property = Configuration::get('OASSO_ROLES_CONTAINER_PROPERTY');
    $roles_container_format = Configuration::get('OASSO_ROLES_CONTAINER_FORMAT');
    $roles_container_format = (in_array($roles_container_format, array(
        'list',
        'array_k',
        'array_v'
    )) ? $roles_container_format : 'list');
    $roles_cleanup = Configuration::get('OASSO_ROLES_CLEANUP');

    // Settings
    $link_account_disable = Configuration::get('OASSO_LINK_ACCOUNT_DISABLE') == 1 ? 1 : 0;
    $data_handling = Configuration::get('OASSO_DATA_HANDLING');
    $data_handling = (in_array($data_handling, array(
        'ask',
        'verify',
        'auto'
    )) ? $data_handling : 'verify');
    $email_customer_disable = Configuration::get('OASSO_EMAIL_CUSTOMER_DISABLE') == 1 ? 1 : 0;
    $email_admin_disable = Configuration::get('OASSO_EMAIL_ADMIN_DISABLE') == 1 ? 1 : 0;

    // Build admin panel HTML
    $html .= '
    <h2>' . $this->l('OAuth 2.0 Single SignOn Client') . ' ' . $this->version . '</h2>
    <p>
    ' . $this->l('Provides a mechanism to identify users from an OAuth 2.0 Identity Server, allowing the Single SignOn of users in your entire organization.') . '
    ' . $this->l('Simplify the management of user information and user credentials with a central identity server.') . '
    </p>
    <form id ="oauthsso_form" action="' . Tools::safeOutput($form_url) . '" method="post">

      <fieldset style="margin-top:20px">
        <legend>' . $this->l('OAuth 2.0 Identity Server') . '</legend>
        <div class="oasso_notice">' . $this->l('On Identity Server client\'s configuration set the Redirect URI to') . ' <span class="oauthsso_uri">' . OAuthSSOHelper::get_api_parameters()->callback_uri . '</span></div>
        <label>' . $this->l('OAuth Server Name') . ':</label>
        <div class="margin-form">
          <input type="text" name="OASSO_OAUTH_SERVER_NAME" id="OASSO_OAUTH_SERVER_NAME" size="60" value="' . $oauth_server_name . '" />
          <p>' . $this->l('This is the fully qualified domain name (FQDN) of the Identity Server, like sso.mydomain.com') . '</p>
        </div>
        <label>' . $this->l('Client ID') . ':</label>
        <div class="margin-form">
          <input type="text" name="OASSO_CLIENT_ID" id="OASSO_CLIENT_ID" size="60" value="' . $client_id . '" />
        </div>
        <label>' . $this->l('Client Secret') . ':</label>
        <div class="margin-form">
          <input type="password" name="OASSO_CLIENT_SECRET" id="OASSO_CLIENT_SECRET" size="60" value="' . $client_secret . '" />
        </div>
        <label>' . $this->l('SSO Provider Name') . ':</label>
        <div class="margin-form">
          <input type="text" name="OASSO_PROVIDER_NAME" id="OASSO_PROVIDER_NAME" size="60" value="' . $provider_name . '" />
          <p>' . $this->l('This is the text that appears on the Single SignOn button.') . '</p>
        </div>
        <div class="margin-form">
          <input type="button" id="OASSO_VERIFY_CONNECTION_SETTINGS" value="' . $this->l('Verify OAuth Settings') . '" class="button" />
        </div>
        <div class="margin-form">
          <span class="oasso_message" id="OASSO_VERIFY_CONNECTION_SETTINGS_RESULT"></span>
        </div>
      </fieldset>

      <fieldset>
      	<legend>' . $this->l('API Connection') . '</legend>
      	<label>' . $this->l('API Connection Handler:') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_API_HANDLER" id="OASSO_API_HANDLER_CURL" value="curl" ' . ($api_handler != 'fsockopen' ? 'checked="checked"' : '') . ' /> ' . $this->l('Use PHP CURL to communicate with the API') . ' <strong>(' . $this->l('Default') . ')</strong>
      		<p>' . $this->l('Using CURL is recommended but it might be disabled on some servers.') . '</p><br />
      		<input type="radio" name="OASSO_API_HANDLER" id="OASSO_API_HANDLER_FSOCKOPEN" value="fsockopen" ' . ($api_handler == 'fsockopen' ? 'checked="checked"' : '') . ' /> ' . $this->l('Use PHP FSOCKOPEN to communicate with the API') . '
      		<p>' . $this->l('Try using FSOCKOPEN if you encounter any problems with CURL.') . '</p>
      	</div>
      	<label>' . $this->l('API Connection Port:') . '</label>
      	<div class="margin-form">
      		<input type="radio" name="OASSO_API_PORT" value="443" id="OASSO_API_PORT_443" ' . ($api_port != 80 ? 'checked="checked"' : '') . ' /> ' . $this->l('Communication via HTTPS on port 443') . ' <strong>(' . $this->l('Default') . ')</strong>
      		<p>' . $this->l('Using port 443 is secure but you might need OpenSSL.') . '</p><br />
      		<input type="radio" name="OASSO_API_PORT" value="80" id="OASSO_API_PORT_80" ' . ($api_port == 80 ? 'checked="checked"' : '') . ' /> ' . $this->l('Communication via HTTP on port 80') . '
      		<p>' . $this->l('Using port 80 is a bit faster, does not need OpenSSL but is also less secure.') . '</p>
      	</div>
      	<div class="margin-form">
      		<input type="button" id="OASSO_VERIFY_CONNECTION_HANDLER" value="' . $this->l('AutoDetect the best API Connection Settings') . '" class="button" />
      	</div>
      	<div class="margin-form">
      		<span class="oasso_message" id="OASSO_VERIFY_CONNECTION_HANDLER_RESULT"></span>
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Custom Embedding') . '</legend>
      	<div class="oasso_notice">' . $this->l('You can manually embed Single SignOn by adding this code to a .tpl file of your PrestaShop:') . '</div>
      	<label style="width:300px;text-align:left"><code>{$HOOK_OASSO_CUSTOM nofilter}</code></label>
      	<div style="margin-bottom: 20px;">
      			' . $this->l('Simply copy the code and add it to any .tpl file in your /themes directory.') . '
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Authentication Page') . '</legend>
      	<div class="oasso_notice">' . $this->l('Displays Single SignOn button on the sign in page of your shop') . '</div>
      	<label>' . $this->l('Enable Authentication Page Hook?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_JS_HOOK_LOGIN_DISABLE" id="OASSO_JS_HOOK_LOGIN_DISABLE_0" value="0" ' . ($js_hook_login_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '&nbsp;
      		<input type="radio" name="OASSO_JS_HOOK_LOGIN_DISABLE" id="OASSO_JS_HOOK_LOGIN_DISABLE_1" value="1" ' . ($js_hook_login_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '<br />
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Registration Page') . '</legend>
      	<div class="oasso_notice">' . $this->l('Displays Single SignOn button on the create account page of your shop') . '</div>
      	<label>' . $this->l('Enable Registration Page Hook?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_JS_HOOK_AUTH_DISABLE" id="OASSO_JS_HOOK_AUTH_DISABLE_0" value="0" ' . ($js_hook_auth_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '&nbsp;
      		<input type="radio" name="OASSO_JS_HOOK_AUTH_DISABLE" id="OASSO_JS_HOOK_AUTH_DISABLE_1" value="1" ' . ($js_hook_auth_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '<br />
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Hook: Navegation Menu') . '</legend>
      	<div class="oasso_notice">' . $this->l('Displays Single SignOn button on the navegation menu on your shop') . '</div>
      	<label>' . $this->l('Enable Navegation Menu Hook?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_HOOK_NAV_MENU_DISABLE" id="OASSO_HOOK_NAV_MENU_DISABLE_0" value="0" ' . ($hook_nav_menu_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '&nbsp;
      		<input type="radio" name="OASSO_HOOK_NAV_MENU_DISABLE" id="OASSO_HOOK_NAV_MENU_DISABLE_1" value="1" ' . ($hook_nav_menu_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '<br />
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Hook: Left Side') . '</legend>
      	<div class="oasso_notice">' . $this->l('Displays Single SignOn button on the left side of your shop') . '</div>
      	<label>' . $this->l('Enable Left Side Hook?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_HOOK_LEFT_DISABLE" id="OASSO_HOOK_LEFT_DISABLE_0" value="0" ' . ($hook_left_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '&nbsp;
      		<input type="radio" name="OASSO_HOOK_LEFT_DISABLE" id="OASSO_HOOK_LEFT_DISABLE_1" value="1" ' . ($hook_left_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '<br />
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Hook: Right Side') . '</legend>
      	<div class="oasso_notice">' . $this->l('Displays Single SignOn button on the right side of your shop') . '</div>
      	<label>' . $this->l('Enable Right Side Hook?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_HOOK_RIGHT_DISABLE" id="OASSO_HOOK_RIGHT_DISABLE_0" value="0" ' . ($hook_right_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '&nbsp;
      		<input type="radio" name="OASSO_HOOK_RIGHT_DISABLE" id="OASSO_HOOK_RIGHT_DISABLE_1" value="1" ' . ($hook_right_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '<br />
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Hook: Right Side Product') . '</legend>
      	<div class="oasso_notice">' . $this->l('Displays Single SignOn button on the right side of the product page on your shop') . '</div>
      	<label>' . $this->l('Enable Right Side Product Hook?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_HOOK_RIGHT_PRODUCT_DISABLE" id="OASSO_HOOK_RIGHT_PRODUCT_DISABLE_0" value="0" ' . ($hook_right_product_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '&nbsp;
      		<input type="radio" name="OASSO_HOOK_RIGHT_PRODUCT_DISABLE" id="OASSO_HOOK_RIGHT_PRODUCT_DISABLE_1" value="1" ' . ($hook_right_product_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '<br />
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Shop Front Customization') . '</legend>
        <div class="oasso_notice">
          <p>' . $this->l('You can use custom CSS/JavaScript to personalize aspects of the front of your shop.') . '</p>
          <p>' . $this->l('For instance, you can hide the elements on the login page making Single Sign On the only option available.') . '</p>
        </div>

        <label>' . $this->l('Custom CSS') . ':</label>
        <div class="margin-form">
          <textarea type="text" name="OASSO_CUSTOM_CSS" id="OASSO_CUSTOM_CSS" cols="60" rows="10">' . $oauth_custom_css . '</textarea>
          <p>' . $this->l('CSS is injected on the header. Do not include the tags') . ' &lt;style&gt;&hellip;&lt;/style&gt;</p>
        </div>

        <label>' . $this->l('Custom JavaScript') . ':</label>
        <div class="margin-form">
          <textarea type="text" name="OASSO_CUSTOM_JS" id="OASSO_CUSTOM_JS" cols="60" rows="10">' . $oauth_custom_js . '</textarea>
          <p>' . $this->l('JavScript is injected on the footer. Do not include the tags') . ' &lt;script&gt;&hellip;&lt;/script&gt;</p>
        </div>

        <div>
          <p>' . $this->l('Hints to customize the aspect of the OAuth 2.0 Single SignOn widget:') . '</p>
        </div>
        <div class="margin-form">
          <p>' . $this->l('All OAuth 2.0 single SignOn widgets has the class \'oauth_sso_block\'') . '</p>
          <p>' . $this->l('Login form widget (class and ID) \'oauth_sso_customer_login_form\'') . '</p>
          <p>' . $this->l('Customer account form widget (class and ID) \'oauth_sso_customer_account_form\'') . '</p>
          <p>' . $this->l('Custom widget (class and ID) \'oauth_sso_custom\'') . '</p>
          <p>' . $this->l('Left column widget: class \'oauth_sso_block_column\' ID: \'oauth_sso_block_left_column\'') . '</p>
          <p>' . $this->l('Right column widget: class \'oauth_sso_block_column\' ID: \'oauth_sso_block_right_column\'') . '</p>
          <p>' . $this->l('Right column product widget: class \'oauth_sso_block_column\' ID: \'oauth_sso_block_right_column_product\'') . '</p>
          <p>' . $this->l('Single SignOn button wrapper (class and ID) \'oauth_sso_provider\'') . '</p>
          <p>' . $this->l('Single SignOn button (class and ID) \'oauth_sso_button\'') . '</p>
        </div>
      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Role Mapping') . '</legend>
        <div class="oasso_notice">' . $this->l('You can map users\' roles to PrestaShop groups in order to implement these roles on your shop.') . '</div>

        <label>' . $this->l('Enable Role Mapping?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_ROLE_MAPPING_ENABLE" id="OASSO_ROLE_MAPPING_ENABLE_1" value="1" ' . ($role_mapping_enable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '
      		<input type="radio" name="OASSO_ROLE_MAPPING_ENABLE" id="OASSO_ROLE_MAPPING_ENABLE_0" value="0" ' . ($role_mapping_enable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '
      	</div>

        <div id="OASSO_ROLE_MAPPING_PANE" ' . ($role_mapping_enable != 1 ? 'style="display:none"' : '') . '>
          <label>' . $this->l('Property describing roles') . ':</label>
          <div class="margin-form">
            <input type="text" name="OASSO_ROLES_CONTAINER_PROPERTY" id="OASSO_ROLES_CONTAINER_PROPERTY" size="60" value="' . $roles_container_property . '" />
            <p>' . $this->l('This is the name of the property sent by the Identity Server which will contain the list of user roles.') . '</p>
          </div>

          <label>' . $this->l('What does this property contain?') . '</label>
        	<div class="margin-form">
        		<p>' . $this->l('The following formats are supported:') . '</p><br />
        		<input type="radio" name="OASSO_ROLES_CONTAINER_FORMAT" id="OASSO_ROLES_CONTAINER_FORMAT_LIST" value="list" '    . ($roles_container_format == 'list'    ? 'checked="checked"' : '') . ' /> ' . $this->l('A comma separated list of role names (or a single role name).') . ' <strong>(' . $this->l('Default') . ')</strong> <br /><br />
        		<input type="radio" name="OASSO_ROLES_CONTAINER_FORMAT" id="OASSO_ROLES_CONTAINER_FORMAT_ARRK" value="array_k" ' . ($roles_container_format == 'array_k' ? 'checked="checked"' : '') . ' /> ' . $this->l('An array of roles, where the role name is the array key.') . '<br /><br />
        		<input type="radio" name="OASSO_ROLES_CONTAINER_FORMAT" id="OASSO_ROLES_CONTAINER_FORMAT_ARRV" value="array_v" ' . ($roles_container_format == 'array_v' ? 'checked="checked"' : '') . ' /> ' . $this->l('An array of roles, where the role name is the array value.') . '<br /><br />
        	</div>

          <label>' . $this->l('Mappings') . ':</label>
          <div class="margin-form">
        		<p>' . $this->l('Map the following roles from the Identity Server to their corresponding shop\'s groups:') . '</p>
            ' . self::build_mappings_table_html(self::MAX_MAPPINGS) . '
            <br />
        	</div>

          <label>' . $this->l('Clean-up existing roles?') . '</label>
        	<div class="margin-form">
        		<input type="radio" name="OASSO_ROLES_CLEANUP" id="OASSO_ROLES_CLEANUP_1" value="1" ' . ($roles_cleanup == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . '
            <p>' . $this->l('Tick to unsuscribe the user formm all groups (clean-up) and then subscribe to those mapped from the Identity Server.') . '</p><br />
        		<input type="radio" name="OASSO_ROLES_CLEANUP" id="OASSO_ROLES_CLEANUP_0" value="0" ' . ($roles_cleanup != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . ' <strong>(' . $this->l('Default') . ')</strong>
            <p>' . $this->l('Tick to preserve all present group subscriptions, and to only add new roles.') . '</p><br />
        	</div>
        </div>

      </fieldset>

      <fieldset style="margin-top:20px">
      	<legend>' . $this->l('Settings') . '</legend>

      	<label>' . $this->l('Enable Administrator Emails?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_EMAIL_ADMIN_DISABLE" id="OASSO_EMAIL_ADMIN_DISABLE_0" value="0" ' . ($email_admin_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . ' <strong>(' . $this->l('Default') . ')</strong>
      		<p>' . $this->l('Tick to have the module send an email to the administrators for each customer that registers with Single SignOn') . '</p><br />
      		<input type="radio" name="OASSO_EMAIL_ADMIN_DISABLE" id="OASSO_EMAIL_ADMIN_DISABLE_1" value="1" ' . ($email_admin_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '
      		<p>' . $this->l('Tick to disable the emails send to administrators.') . '</p>
      	</div>

      	<label>' . $this->l('Enable Customer Emails?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_EMAIL_CUSTOMER_DISABLE" id="OASSO_EMAIL_CUSTOMER_DISABLE_0" value="0" ' . ($email_customer_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable') . ' <strong>(' . $this->l('Default') . ')</strong>
      		<p>' . $this->l('Tick to have the module send an email to each new customer that registers with Single SignOn') . '</p><br />
      		<input type="radio" name="OASSO_EMAIL_CUSTOMER_DISABLE" id="OASSO_EMAIL_CUSTOMER_DISABLE_1" value="1" ' . ($email_customer_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable') . '
      		<p>' . $this->l('Tick to disable the emails send to customer.') . '</p>
      	</div>

      	<label>' . $this->l('Enable Account Linking?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<input type="radio" name="OASSO_LINK_ACCOUNT_DISABLE" id="OASSO_LINK_ACCOUNT_DISABLE_0" value="0" ' . ($link_account_disable != 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Enable Account Linking') . ' <strong>(' . $this->l('Default') . ')</strong>
      		<p>' . $this->l('If the user\'s profile provides an email address, the plugin will try to link the profile to an existing account.') . '</p><br />
      		<input type="radio" name="OASSO_LINK_ACCOUNT_DISABLE" id="OASSO_LINK_ACCOUNT_DISABLE_1" value="1" ' . ($link_account_disable == 1 ? 'checked="checked"' : '') . ' /> ' . $this->l('Disable Account Linking') . '
      		<p>' . $this->l('Identitiy Server user\'s profiles will never be linked automatically to existing users.') . '</p>
      	</div>

      	<label>' . $this->l('User Data Completion?') . '</label>
      	<div class="margin-form" style="margin-bottom: 20px;">
      		<p>' . $this->l('To create an account PrestaShop requires a firstname, a lastname and an email address. The OAuth 2.0 Identity Server might or mignt not provide this data.') . '</p><br />
      		<input type="radio" name="OASSO_DATA_HANDLING" id="OASSO_DATA_HANDLING_VERIFY" value="verify" ' . (!in_array($data_handling,
            array(
                'auto, ask'
            )) ? 'checked="checked"' : '') . ' /> ' . $this->l('Always ask users to verify their data when they sign up with Single SignOn account') . ' <strong>(' . $this->l('Default') . ')</strong>
      		<p>' . $this->l('Tick this option to have the users always verify the data retrieved from the Identity Server.') . '</p><br />
      		<input type="radio" name="OASSO_DATA_HANDLING" id="OASSO_DATA_HANDLING_ASK" value="ask" ' . ($data_handling == 'ask' ? 'checked="checked"' : '') . ' /> ' . $this->l('Only ask for missing values') . ' (' . $this->l('Faster Registration') . ')
      		<p>' . $this->l('Tick this option to have the users verify their data manually only in case there are required fields that are not provided by the Identity Server.') . '</p><br />
      		<input type="radio" name="OASSO_DATA_HANDLING" id="OASSO_DATA_HANDLING_AUTO" value="auto" ' . ($data_handling == 'auto' ? 'checked="checked"' : '') . ' /> ' . $this->l('Never ask, create placeholders for missing fields') . ' (' . $this->l('Fastests Registration, Not recommended') . ')
      		<p>' . $this->l('Tick this option to have the plugin automatically create placeholder values for fields that are not provided by the Identity Server.') . '</p>
      	</div>
      </fieldset>

      <fieldset style="margin-top:20px">
  			<legend>' . $this->l('Save OAuth 2.0 Single SignOn Client Settings') . '</legend>
  			<div class="margin-form">
        <input type="submit" class="button" name="submit" value="' . $this->l('Save Settings') . '">
  			</div>
      </fieldset>

  		<script type="text/javascript">
        const OAUTHSSO_AJAX_TOKEN = \'' . sha1(_COOKIE_KEY_ . 'OAUTH20SSO') . '\';
        const OAUTHSSO_AJAX_PATH  = \'' . Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/oauthsso/assets/ajax/admin.php\';
        var MSG_oauthsso = [];
        MSG_oauthsso[\'contacting_api\'] = "' . $this->l('Contacting API - please wait ...') . '";
        MSG_oauthsso[\'contacting_oauth\'] = "' . $this->l('Contacting OAuth 2.0 Identity Server - please wait ...') . '";
        MSG_oauthsso[\'curl_on_443\'] = "' . $this->l('Autodetected CURL on port 443') . '";
        MSG_oauthsso[\'fsocopen_on_443\'] = "' . $this->l('Autodetected FSOCKOPEN on port 443') . '";
        MSG_oauthsso[\'curl_on_80\'] = "' . $this->l('Autodetected CURL on port 80') . '";
        MSG_oauthsso[\'fsockopen_on_80\'] = "' . $this->l('Autodetected FSOCKOPEN on port 80') . '";
        MSG_oauthsso[\'autodetect_error\'] = "' . $this->l('Autodetection Error.') . '";
        MSG_oauthsso[\'save_changes\'] = "' . $this->l('do not forget to save your changes!') . '";
        MSG_oauthsso[\'error_selected_handler_faulty\'] = "' . $this->l('The API Connection cannot be made, try using the API Connection autodetection') . '";
        MSG_oauthsso[\'error_not_all_fields_filled_out\'] = "' . $this->l('Please fill out each of the fields above') . '";
        MSG_oauthsso[\'error_server_name_wrong\'] = "' . $this->l('The OAuth Server does not exist. Have you filled it out correctly?') . '";
        MSG_oauthsso[\'error_server_name_wrong_syntax\'] = "' . $this->l('The OAuth Server Name has a wrong syntax!') . '";
        MSG_oauthsso[\'error_communication\'] = "' . $this->l('Could not contact API. Try using another connection handler') . '";
        MSG_oauthsso[\'error_authentication_credentials_wrong\'] = "' . $this->l('The API credentials are wrong') . '";
        MSG_oauthsso[\'success\'] = "' . $this->l('The settings are correct') . '";
        MSG_oauthsso[\'unknow\'] = "' . $this->l('An unknow error occured! The settings could not be verified.') . '";
  		</script>
    </form>';

    return $html;
  }

  /**
   * Build HTML for the list of mappings (user role --> PS group)
   */
  private function build_mappings_table_html($rows) {
    $ps_groups = GroupCore::getGroups($this->context->language->id);
    $none_grp = $this->l('-- None --');

    $html = '
    <table class="role_mappings">
      <thead>
        <tr>
          <th>' . $this->l('Role name') . '</th>
          <th></th>
          <th>' . $this->l('Shop Group') . '</th>
          <th></th>
          <th>' . $this->l('Set as default') . '</th>
        </tr>
      </thead>
      <tbody>';

    for ($i = 0; $i < $rows; $i++) {
      $role_p  = "OASSO_ROLES_MAPPING_ROLE_". $i;
      $role_v  = Configuration::get($role_p);
      $group_p = "OASSO_ROLES_MAPPING_GROUP_". $i;
      $group_v = Configuration::get($group_p);
      $def_g_p = "OASSO_ROLES_MAPPING_DEFAULT_". $i;
      $def_g_v = Configuration::get($def_g_p);

      $html .= '
        <tr>
          <td>
            <input type="text" name="' . $role_p. '" id="' . $role_p . '" size="30" value="' . $role_v . '" />
          </td>
          <td>
            <span style="margin: 0 1em;">&#8594;</span>
          </td>
          <td>
            <select name="' . $group_p . '" id="' . $group_p . '">';

      $selected = ($group_v == 0) ? 'selected' : '';
      $groups_html = '
              <option value="0" ' . $selected . '>' . $none_grp . '</option>';
      foreach($ps_groups as $group) {
        $selected = ($group_v == $group['id_group']) ? 'selected' : '';
        $groups_html .= '
              <option value="' . $group['id_group'] . '" ' . $selected . '>' . $group['name'] . '</option>';
      }

      $html .= $groups_html . '
            </select>
          </td>
          <td>
            <span style="margin: 0 1em;">&nbsp;</span>
          </td>
          <td align="center">
            <input type="checkbox" name="' . $def_g_p . '" id="' . $def_g_p . '" value="1" ' . ($def_g_v == 1 ? "checked" : ""). ' />
          </td>
        </tr>';
    }

    $html .= '
      </tbody>
    </table>';

    return $html;
  }

  /**
   * Get roles mapping from 'UI' or 'config'.
   */
  public function get_roles_mappings($source = 'config') {
    $mappings = array();
    for ($i = 0; $i < self::MAX_MAPPINGS; $i++) {
      if ( $source === 'UI' ) {
        $mappings[] = array( 'role'     => trim(Tools::getValue('OASSO_ROLES_MAPPING_ROLE_' . $i)),
                             'id_group' => trim(Tools::getValue('OASSO_ROLES_MAPPING_GROUP_' . $i)),
                             'default'  => trim(Tools::getValue('OASSO_ROLES_MAPPING_DEFAULT_' . $i)));
      } elseif ( $source === 'config' ) {
        $role = Configuration::get('OASSO_ROLES_MAPPING_ROLE_' . $i);
        $group = Configuration::get('OASSO_ROLES_MAPPING_GROUP_' . $i);
        $group_i = intval($group);
        $default = Configuration::get('OASSO_ROLES_MAPPING_DEFAULT_' . $i);
        if ( !empty($role) and !empty($group) and ($group_i == $group) and $group_i > 0 ) {
          $mappings[] = array( 'role'     => $role,
                               'id_group' => $group_i,
                               'default'  => $default == 1);
        }
      } else {
        return null;
      }
    }
    return $mappings;
  }

  /**
   * **************************************************************************
   * INSTALLATION
   * **************************************************************************
   */

  /**
   * Moves a hook to the given position
   */
  protected function move_hook_position($hook_name, $position) {
    // Get the hook identifier.
    if (($id_hook = Hook::getIdByName($hook_name)) !== false) {
        // Load the module.
        if (($module = Module::getInstanceByName($this->name)) !== false) {
            // Get the max position of this hook.
            $sql = "SELECT MAX(position) AS position FROM `" . _DB_PREFIX_ . "hook_module` WHERE `id_hook` = '" . intval($id_hook) . "'";
            $result = Db::getInstance()->GetRow($sql);
            if (is_array($result) and isset($result['position'])) {
                $way = (($result['position'] >= $position) ? 0 : 1);

                return $module->updatePosition($id_hook, $way, $position);
            }
        }
    }

    // An error occurred.
    return false;
  }

  /**
   * Returns a list of files to install
   */
  protected function get_files_to_install() {
    // Read current language.
    $language = strtolower(trim(strval(Language::getIsoById($this->context->language->id))));

    // All languages to be installed for.
    $languages = array_unique(array('en', 'es', $language));

    // List of templates
    $templates = array('oauthsso_customer.html',
                       'oauthsso_customer.txt',
                       'oauthsso_admin.html',
                       'oauthsso_admin.txt');

    // Install email templates
    foreach ($languages as $language) {
      // For unknown languages install the English version
      $src_language = ($language == 'es') ? 'es' : 'en';
      $source = _PS_MODULE_DIR_ . $this->name . '/upload/mails/' . $src_language . '/';
      $target = _PS_MODULE_DIR_ . $this->name . '/mails/' . $language . '/';

      // Make sure the directory exists
      if (!is_dir($target)) {
        mkdir($target, 0755, true);
      }

      // Install all templates for this language
      foreach ($templates as $template) {
        $files[] = array(
            'name'   => $template,
            'source' => $source,
            'target' => $target
        );
      }
    }

    // Done
    return $files;
  }

  /**
   * Install
   */
  public function install() {
    // Load context
    $this->context = Context::getContext();

    // Start Installation
    if (!parent::install()) {
      return false;
    }

    // Store the added files
    $files_added = array();

    // Get files to install.
    $files = $this->get_files_to_install();

    // Install files.
    foreach ($files as $file_data) {
      if (is_array($file_data) && !empty($file_data['name']) && !empty($file_data['source']) && !empty($file_data['target'])) {
        if (!file_exists($file_data['target'] . $file_data['name'])) {
          if (!copy($file_data['source'] . $file_data['name'], $file_data['target'] . $file_data['name'])) {
            // Add Error
            $this->context->controller->errors[] = 'Could not copy the file ' . $file_data['source'] . $file_data['name'] . ' to ' . $file_data['target'] . $file_data['name'];

            // Rollback the copied files in case of an error
            foreach ($files_added as $file_added) {
              if (file_exists($file_added)) {
                @unlink($file_added);
              }
            }

            // Abort Installation.
            return false;
          } else {
            $files_added[] = $file_data['target'] . $file_data['name'];
          }
        }
      }
    }

    // Install our hooks.
    foreach (self::HOOKS as $hook_name => $hook_data) {
      if (!$this->registerHook($hook_name)) {
        $this->context->controller->errors[] = 'Could not register the hook ' . $hook_name;

        return false;
      } else {
        if (is_array($hook_data) and isset($hook_data['pos'])) {
          $this->move_hook_position($hook_name, $hook_data['pos']);
        }
      }
    }

    // Create user_token table.
    $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'oasso_user` (
      `id_oasso_user` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `id_customer` int(10) unsigned NOT NULL,
      `user_token` varchar(48) NOT NULL,
      `date_add` datetime NOT NULL,
      PRIMARY KEY (`id_oasso_user`))';
    if (!Db::getInstance()->execute($query)) {
      $this->context->controller->errors[] = "Could not create the table " . _DB_PREFIX_ . "oasso_user";

      return false;
    }

    // Create identity_token table.
    $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'oasso_identity` (
      `id_oasso_identity` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `id_oasso_user` int(10) unsigned NOT NULL,
      `identity_token` varchar(48) NOT NULL,
      `identity_provider` varchar(64) NOT NULL,
      `num_logins` int(10) unsigned NOT NULL,
      `date_add` datetime NOT NULL,
      `date_upd` datetime NOT NULL,
      PRIMARY KEY (`id_oasso_identity`))';
    if (!Db::getInstance()->execute($query)) {
      $this->context->controller->errors[] = "Could not create the table " . _DB_PREFIX_ . "oasso_identity";

      return false;
    }

    // Clean class cache.
    $class_cache = _PS_CACHE_DIR_ . 'class_index.php';
    if (file_exists($class_cache)) {
      @unlink($class_cache);
    }

    // Done
    return true;
  }

  /**
   * Uninstall
   */
  public function uninstall() {
    // UnInstall
    if (!parent::uninstall()) {
      return false;
    }

    // Drop user_token table
    $query = 'DROP table IF EXISTS `' . _DB_PREFIX_ . 'oasso_user`';
    Db::getInstance()->execute($query);

    // Drop identity_token table
    $query = 'DROP table IF EXISTS `' . _DB_PREFIX_ . 'oasso_identity`';
    Db::getInstance()->execute($query);

    // Get files to remove.
    $files = $this->get_files_to_install();

    // Remove files
    foreach ($files as $file_data) {
      if (is_array($file_data) && !empty($file_data['name']) && !empty($file_data['source']) && !empty($file_data['target'])) {
        if (file_exists($file_data['target'] . $file_data['name'])) {
          @unlink($file_data['target'] . $file_data['name']);
        }
      }
    }

    return true;
  }

  /**
   * **************************************************************************
   * HOOKS
   * **************************************************************************
   */

  /**
   * Generic Hook
   */
  protected function hookGeneric($params, $target) {
    global $smarty;

    // Load context
    $this->context = Context::getContext();

    // Do not display for users that are logged in, or for users that are using our controller
    if (!$this->context->customer->isLogged() and $this->context->controller->php_self != 'oauthsso') {
      // Default
      $widget_enable = false;
      $widget_location = 'unspecified';

      // Check what has to be done
      switch ($target) {
        // Login form
        case 'login_form':
          if (Configuration::get('OASSO_JS_HOOK_LOGIN_DISABLE') != 1) {
              $widget_enable = true;
              $widget_location = $target;
          }
          break;

        // Customer Account Form
        case 'customer_account_form':
          if (Configuration::get('OASSO_JS_HOOK_AUTH_DISABLE') != 1) {
              $widget_enable = true;
              $widget_location = $target;
          }
          break;

        // Left Column
        case 'left_column':
          if (Configuration::get('OASSO_HOOK_LEFT_DISABLE') != 1) {
              $widget_enable = true;
              $widget_location = $target;
          }
          break;

        // Right Column
        case 'right_column':
          if (Configuration::get('OASSO_HOOK_RIGHT_DISABLE') != 1) {
              $widget_enable = true;
              $widget_location = $target;
          }
          break;

        // Right Column Product
        case 'right_column_product':
          if (Configuration::get('OASSO_HOOK_RIGHT_PRODUCT_DISABLE') != 1) {
              $widget_enable = true;
              $widget_location = $target;
          }
          break;

        // Menu
        case 'menu':
          if (Configuration::get('OASSO_HOOK_NAV_MENU_DISABLE') != 1) {
              $widget_enable = true;
              $widget_location = $target;
          }
          break;

        // Custom
        case 'custom':
          $widget_enable = true;
          $widget_location = $target;
          break;
      }

      // Enable this widget?
      if ($widget_enable) {
        // Setup placeholders
        $smarty->assign('oasso_widget_location',  $widget_location);
        $smarty->assign('oasso_widget_sso_uri',   OAuthSSOHelper::get_signon_uri(true));
        $smarty->assign('oasso_sso_provider',     html_entity_decode(Configuration::get('OASSO_PROVIDER_NAME')));

        // Display template
        return $this->display(__FILE__, 'oauth_sso_widget.tpl');
      }
    }
  }

  /**
   * Hook: Login form
   */
  public function hookDisplayCustomerLoginFormAfter($params) {
    return $this->hookGeneric($params, 'login_form');
  }

  /**
   * Hook: Customer Account Form Top
   */
  public function hookDisplayCustomerAccountFormTop($params) {
    return $this->hookGeneric($params, 'customer_account_form');
  }

  /**
   * Hook: Left Column
   */
  public function hookDisplayLeftColumn($params) {
    return $this->hookGeneric($params, 'left_column');
  }

  /**
   * Hook: Right Column
   */
  public function hookDisplayRightColumn($params) {
    return $this->hookGeneric($params, 'right_column');
  }

  /**
   * Hook: Right Column Product
   */
  public function hookDisplayRightColumnProduct($params) {
    return $this->hookGeneric($params, 'right_column_product');
  }

  /**
   * Hook: displayNav2 (right menu)
   */
  public function hookDisplayNav2($params) {
    return $this->hookGeneric($params, 'menu');
  }

  /**
   * Hook: Header (head)
   */
  public function hookDisplayHeader($params) {
    global $smarty;

    // Output
    $output = '';

    // Add a shortcut.
    $smarty->assign('HOOK_OASSO_CUSTOM', $this->hookGeneric($params, 'custom'));

    // Add the OauthSSO head.
    $smarty->assign('oasso_widget_location', 'head');
    $smarty->assign('oasso_oauth_server_name', Configuration::get('OASSO_OAUTH_SERVER_NAME'));
    $smarty->assign('oasso_sso_provider', html_entity_decode(Configuration::get('OASSO_PROVIDER_NAME')));
    $smarty->assign('oasso_custom_css', html_entity_decode(Configuration::get('OASSO_CUSTOM_CSS')));

    // Read template
    $output .= $this->display(__FILE__, 'oauth_sso_widget.tpl');

    return $output;
  }

  /**
   * Hook: Footer (scripts)
   */
  public function hookDisplayFooterAfter($params) {
    global $smarty;

    // Output
    $output = '';

    // Add the OauthSSO scripts.
    $smarty->assign('oasso_widget_location',            'scripts');
    $smarty->assign('oasso_custom_js',                  html_entity_decode(Configuration::get('OASSO_CUSTOM_JS')));
    $smarty->assign('oasso_sso_provider',               html_entity_decode(Configuration::get('OASSO_PROVIDER_NAME')));
    $smarty->assign('oasso_oauth_server_name',           Configuration::get('OASSO_OAUTH_SERVER_NAME'));
    $smarty->assign('oasso_data_handling',               Configuration::get('OASSO_DATA_HANDLING'));
    $smarty->assign('oasso_auth_disable',               (Configuration::get('OASSO_JS_HOOK_AUTH_DISABLE')       == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_login_disable',              (Configuration::get('OASSO_JS_HOOK_LOGIN_DISABLE')      == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_hook_left_disable',          (Configuration::get('OASSO_HOOK_LEFT_DISABLE')          == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_hook_right_disable',         (Configuration::get('OASSO_HOOK_RIGHT_DISABLE')         == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_hook_right_product_disable', (Configuration::get('OASSO_HOOK_RIGHT_PRODUCT_DISABLE') == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_hook_nav_menu_disable',      (Configuration::get('OASSO_HOOK_NAV_MENU_DISABLE')      == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_link_account_disable',       (Configuration::get('OASSO_LINK_ACCOUNT_DISABLE')       == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_email_admin_disable',        (Configuration::get('OASSO_EMAIL_ADMIN_DISABLE')        == 1 ? 'true' : 'false'));
    $smarty->assign('oasso_email_customer_disable',     (Configuration::get('OASSO_EMAIL_CUSTOMER_DISABLE')     == 1 ? 'true' : 'false'));

    // Read template
    $output .= $this->display(__FILE__, 'oauth_sso_widget.tpl');

    return $output;
  }

  /**
   * Hook: Page Top (Callback)
   */
  public function hookDisplayTop() {
    return OAuthSSOHelper::oauth_callback();
  }

  /**
   * **************************************************************************
   * Internazionalization
   * **************************************************************************
   */

  /**
   * Returns the traslation of the string passed as an argument.
   *
   * As of PrestaShop 1.7.7.1 the method $this->module->l('Sample') [used outside the module file itself]
   * generates a string that can be tranalated, but the traslation is not retrieved on the front-end.
   *
   * As l() only works for the module file itself and for the Smarty templates, the rest of the files on
   * this module call this method to retrieve the translations for the strings they use. So the transformation
   * is $this->module->l('Sample') ==> $this->module->translate('Sample')
   *
   * In order to be able to translate a string, it needs to be listed explicetely here.
   *
   * @param  string $string String to translate
   * @return string         Traslated string
   */
  public function translate(string $string) {
    switch ($string) {
      case 'Sample':                                return $this->l('Sample');
      case 'Username':                              return $this->l('Username');
      case 'Please enter your first name':          return $this->l('Please enter your first name');
      case 'Please enter a valid first name':       return $this->l('Please enter a valid first name');
      case 'Please enter your lastname':            return $this->l('Please enter your lastname');
      case 'Please enter a valid last name':        return $this->l('Please enter a valid last name');
      case 'Please enter your email address':       return $this->l('Please enter your email address');
      case 'Please enter a valid email address':    return $this->l('Please enter a valid email address');
      case 'This email address is already taken':   return $this->l('This email address is already taken');
    }
    return $string;
  }
}
