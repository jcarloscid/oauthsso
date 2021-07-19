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

class OAuthSSOHelper {

  // User-agent string used by this module when calling the identity server
  private const USER_AGENT = 'OAuthSSO/1.0.1 PrestaShop/1.7.x.x (+http://fishandbits.es/)';

  /**
   * Generate a user token out from user data.
   */
  public static function get_user_token($data) {
    // Properties that can represent a user token (ordered)
    $properties = array( 'user_token',
                         'ID',
                         'Id',
                         'user_id',
                         'user_login',
                         'user_email');

    // Find the first property that is present
    foreach($properties as $prop) {
      if ( array_key_exists($prop, $data) ) {
        // Return user token from data
        return $data[$prop];
      }
    }

    return false;
  }

  /**
   * Generate an identity token out from user data.
   */
  public static function get_identity_token($data) {
    // User email is preferred to build this token
    if ( array_key_exists('user_email', $data) ) {
      return base64_encode($data['user_email']);
    }

    // If user email is not present use user_token
    return base64_encode(self::get_user_token($data));
  }

  /**
   * Generates a random email address
   */
  public static function generate_random_email_address() {
    do {
      $email_address = md5(uniqid(mt_rand(10000, 99000))) . "@example.com";
    } while (self::get_id_customer_for_email_address($email_address) !== false);

    return $email_address;
  }

  /**
   * Loads an existing customer and instantiates a Customer object.
   */
  public static function load_customer($id_customer) {
    // Make sure that that the customers exists.
    $sql = "SELECT *
              FROM `" . _DB_PREFIX_ . "customer`
             WHERE `id_customer` = " . pSQL($id_customer);
    $result = Db::getInstance()->GetRow($sql);

    // The user account has been found!
    if (!empty($result['id_customer'])) {
      // See => CustomerCore::getByEmail
      $customer = new Customer();
      $customer->id = $result['id_customer'];
      foreach ($result as $key => $value) {
        if (key_exists($key, $customer)) {
          $customer->{$key} = $value;
        }
      }

      // Return customer object
      return $customer;
    }

    // Invalid customer specified.
    return false;
  }

  public static function update_user_data($id_customer, $data) {
    // Allow some variants
    $first_name = trim(empty($data['user_first_name']) ? $data['first_name'] : $data['user_first_name']);
    $last_name  = trim(empty($data['user_last_name'])  ? $data['last_name']  : $data['user_last_name']);
    $email      = trim($data['user_email']);

    $result  = false;
    $sql_set = '';

    // Update non empty fields
    if ( !empty($first_name) ) {
      $sql_set .= " `firstname` = '" . pSQL($first_name) . "',";
    }
    if ( !empty($last_name) ) {
      $sql_set .= " `lastname` = '" . pSQL($last_name) . "',";
    }
    if ( !empty($email) ) {
      $sql_set .= " `email` = '" . pSQL($email) . "',";
    }

    // Something to update?
    if ( !empty($sql_set) ) {
      // Update customer record
      $sql = "UPDATE `" . _DB_PREFIX_ . "customer`
                 SET {$sql_set}
                     `id_customer` = `id_customer`
               WHERE `id_customer` = " . pSQL($id_customer);
      $result = Db::getInstance()->execute($sql);
    }

    // Done
    return $result;
  }

  /**
   * Logs a given customer in.
   */
  public static function login_customer($customer) {

    // If the user has been deactivated on the shop, the login cannot proceed.
    if ($customer->active == 0) {
      $error_msg = "OAuth SSO: Customer #{$customer->id} ({$customer->email}) is marked as inactive. Login is not allowed.";
      error_log($error_msg);

      // Error
      return false;
    }

    // See => AuthControllerCore::processSubmitLogin
    Hook::exec('actionBeforeAuthentication');

    $context = Context::getContext();
    $context->cookie->id_customer = (int) ($customer->id);
    $context->cookie->customer_lastname = $customer->lastname;
    $context->cookie->customer_firstname = $customer->firstname;
    $context->cookie->logged = 1;
    $context->cookie->is_guest = $customer->isGuest();
    $context->cookie->passwd = $customer->passwd;
    $context->cookie->email = $customer->email;

    // Customer is logged in
    $customer->logged = 1;

    // Add customer to the context
    $context->customer = $customer;

    // Used to init session
    $context->updateCustomer($customer);

    if (Configuration::get('PS_CART_FOLLOWING') && (empty($context->cookie->id_cart) || Cart::getNbProducts($context->cookie->id_cart) == 0) && $id_cart = (int) Cart::lastNoneOrderedCart($context->customer->id)) {
      $context->cart = new Cart($id_cart);
    } else {
      $context->cart->id_carrier = 0;
      $context->cart->setDeliveryOption(null);
      $context->cart->id_address_delivery = Address::getFirstCustomerAddressId((int) ($customer->id));
      $context->cart->id_address_invoice = Address::getFirstCustomerAddressId((int) ($customer->id));
    }
    $context->cart->id_customer = (int) $customer->id;
    $context->cart->secure_key = $customer->secure_key;
    $context->cart->save();

    $context->cookie->id_cart = (int) $context->cart->id;
    $context->cookie->update();
    $context->cart->autosetProductAddress();

    Hook::exec('actionAuthentication');

    // Login information have changed, so we check if the cart rules still apply
    CartRule::autoRemoveFromCart($context);
    CartRule::autoAddToCart($context);

    // Customer is now logged in.
    return true;
  }

  /**
   * Creates a new customer based on the given data.
   */
  public static function create_customer_from_data(array $data, $send_email_to_admin = false, $send_email_to_customer = false) {
    if (is_array($data) && !empty($data['user_token']) && !empty($data['identity_token'])) {
      $password = Tools::passwdGen();

      // Prestashop first and last names are restricted to some characters.
      $data['user_first_name'] = preg_replace("/[^A-Za-z0-9 ]/", "", $data['user_first_name']);
      $data['user_last_name'] = preg_replace("/[^A-Za-z0-9 ]/", "", $data['user_last_name']);

      // Build customer fields.
      $customer = new CustomerCore();
      $customer->firstname = empty($data['user_first_name']) ? '' : $data['user_first_name'];
      $customer->lastname = empty($data['user_last_name']) ? '' : $data['user_last_name'];
      $customer->id_gender = empty($data['user_gender']) ? '' : $data['user_gender'];
      $customer->birthday = empty($data['user_birthdate']) ? '' : $data['user_birthdate'];
      $customer->active = true;
      $customer->deleted = false;
      $customer->is_guest = false;
      $customer->passwd = Tools::encrypt($password);

      //Opted for the newsletter?
      if (!empty($data['user_newsletter'])) {
        $customer->ip_registration_newsletter = pSQL(Tools::getRemoteAddr());
        $customer->newsletter_date_add = pSQL(date('Y-m-d H:i:s'));
        $customer->newsletter = true;
      } else {
        $customer->newsletter = false;
      }

      // We could get the email.
      if (!empty($data['user_email'])) {
        // It already exists.
        if (self::get_id_customer_for_email_address($data['user_email']) !== false) {
          // Create a new one.
          $customer->email = self::generate_random_email_address();
          $customer->newsletter = false;
        } else {
          $customer->email = $data['user_email'];
        }
      } else {
        // We could not get the email.
        // Create a new one.
        $customer->email = self::generate_random_email_address();
        $customer->newsletter = false;
      }

      // Create a new user account.
      try {
        $add_result = $customer->add();
      } catch(Exception $ex) {
        $add_result = false;
        error_log("create_customer_from_data() Exception: " . $ex->getMessage());
      }

      if ($add_result) {
        // Tie the tokens to the newly created member.
        if (self::link_tokens_to_id_customer($customer->id, $data['user_token'], $data['identity_token'], $data['identity_provider'])) {
          // Send an email to the customer.
          if ($send_email_to_customer === true) {
            self::send_confirmation_to_customer($customer, $password, $data['identity_provider']);
          }

          //Send an email to the administrators
          if ($send_email_to_admin === true) {
            self::send_confirmation_to_administrators($customer, $data['identity_provider']);
          }

          //Process the newletter settings
          if ($customer->newsletter === true) {
            if ($module_newsletter = Module::getInstanceByName('blocknewsletter')) {
              if ($module_newsletter->active) {
                $module_newsletter->confirmSubscription($customer->email);
              }
            }
          }

          //Done
          return $customer->id;
        }
      }
    }

    //Error
    return false;
  }

  /**
   * Links the user/identity tokens to a customer
   */
  public static function link_tokens_to_id_customer($id_customer, $user_token, $identity_token, $identity_provider) {
    // Make sure that that the customers exists.
    $sql = "SELECT `id_customer`
              FROM `" . _DB_PREFIX_ . "customer`
             WHERE `id_customer` = " . pSQL($id_customer);
    $row_customer = Db::getInstance()->GetRow($sql);

    // The user account has been found!
    if (!empty($row_customer['id_customer'])) {
      // Read the entry for the given user_token.
      $sql = "SELECT `id_oasso_user`,
                     `id_customer`
                FROM `" . _DB_PREFIX_ . "oasso_user`
               WHERE `user_token` = '" . pSQL($user_token) . "'";
      $row_oasso_user = Db::getInstance()->GetRow($sql);

      // The user_token exists but is linked to another user.
      if (!empty($row_oasso_user['id_oasso_user']) and $row_oasso_user['id_customer'] != $id_customer) {
        // Delete the wrongly linked user_token.
        $sql = "DELETE
                  FROM `" . _DB_PREFIX_ . "oasso_user`
                 WHERE `user_token` = '" . pSQL($user_token) . "'
                 LIMIT 1";
        $result = Db::getInstance()->execute($sql);

        // Delete the wrongly linked identity_token.
        $sql = "DELETE
                  FROM `" . _DB_PREFIX_ . "oasso_identity`
                 WHERE `id_oasso_user` = '" . pSQL($row_oasso_user['id_oasso_user']) . "'";
        $result = Db::getInstance()->execute($sql);

        // Reset the identifier to create a new one.
        $row_oasso_user['id_oasso_user'] = null;
      }

      // The user_token either does not exist or has been reset.
      if (empty($row_oasso_user['id_oasso_user'])) {
        // Add new link.
        $sql = "INSERT
                  INTO `" . _DB_PREFIX_ . "oasso_user`
                   SET `id_customer` = '" . pSQL($id_customer) . "',
                       `user_token`  = '" . pSQL($user_token) . "',
                       `date_add`    = '" . date('Y-m-d H:i:s') . "'";
        $result = Db::getInstance()->execute($sql);

        // Identifier of the newly created user_token entry.
        $row_oasso_user['id_oasso_user'] = Db::getInstance()->Insert_ID();
      }

      // Read the entry for the given identity_token.
      $sql = "SELECT `id_oasso_identity`,
                     `id_oasso_user`,
                     `identity_token`
                FROM `" . _DB_PREFIX_ . "oasso_identity`
               WHERE `identity_token` = '" . pSQL($identity_token) . "'";
      $row_oasso_identity = Db::getInstance()->GetRow($sql);

      // The identity_token exists but is linked to another user_token.
      if (!empty($row_oasso_identity['id_oasso_identity']) and $row_oasso_identity['id_oasso_user'] != $row_oasso_user['id_oasso_user']) {
        // Delete the wrongly linked user_token.
        $sql = "DELETE
                  FROM `" . _DB_PREFIX_ . "oasso_identity`
                 WHERE `id_oasso_identity` = '" . pSQL($row_oasso_identity['id_oasso_identity']) . "'
                 LIMIT 1";
        $result = Db::getInstance()->execute($sql);

        // Reset the identifier to create a new one.
        $row_oasso_identity['id_oasso_identity'] = null;
      }

      // The identity_token either does not exist or has been reset.
      if (empty($row_oasso_identity['id_oasso_identity'])) {
        // Add new link.
        $sql = "INSERT
                  INTO `" . _DB_PREFIX_ . "oasso_identity`
                   SET `id_oasso_user`     = '" . pSQL($row_oasso_user['id_oasso_user']) . "',
                       `identity_token`    = '" . pSQL($identity_token) . "',
                       `identity_provider` = '" . pSQL($identity_provider) . "',
                       `num_logins`        = 1,
                       `date_add`          = '" . date('Y-m-d H:i:s') . "',
                       `date_upd`          = '" . date('Y-m-d H:i:s') . "'";
        $result = Db::getInstance()->execute($sql);

        // Identifier of the newly created identity_token entry.
        $row_oasso_identity['id_oasso_identity'] = Db::getInstance()->Insert_ID();
      }

      // Done.
      return true;
    }

    // An error occured.
    return false;
  }

  /**
   * Updates the number of logins for an identity_token.
   */
  public static function update_identity_logins($identity_token) {
    // Make sure it is not empty.
    $identity_token = trim($identity_token);
    if (strlen($identity_token) == 0) {
      return false;
    }

    //Update
    $sql = "UPDATE `" . _DB_PREFIX_ . "oasso_identity`
               SET `num_logins`     = `num_logins` + 1,
                   `date_upd`       = '" . date('Y-m-d H:i:s') . "'
             WHERE `identity_token` = '" . pSQL($identity_token) . "' LIMIT 1";
    $result = Db::getInstance()->execute($sql);

    //Done
    return $result;
  }

  /**
   * Sends a confirmation to the administrators.
   */
  public static function send_confirmation_to_administrators($customer, $identity_provider) {
    // Get the language identifier.
    $context = Context::getContext();
    $language_id = $context->language->id;

    // Current module
    $module = Module::getInstanceByName('oauthsso');

    // Setup the mail vars.
    $mail_vars = array();
    $mail_vars['{identifier}'] = $customer->id;
    $mail_vars['{user_first_name}'] = $customer->firstname;
    $mail_vars['{user_last_name}'] = $customer->lastname;
    $mail_vars['{user_email}'] = $customer->email;
    $mail_vars['{provider}'] = html_entity_decode($identity_provider);

    // Read the first employe - should be the board owner
    $employees = Employee::getEmployeesByProfile(_PS_ADMIN_PROFILE_, true);
    foreach ($employees as $employee) {
      // Employee Details
      $mail_vars['{firstname}'] = $employee['firstname'];
      $mail_vars['{lastname}'] = $employee['lastname'];

      // Send mail to administrators
      @Mail::Send((int) $language_id,
                  'oauthsso_admin',
                  Mail::l('A new customer has registered with OAuth 2.0 Single SignOn Client'),
                  $mail_vars,
                  $employee['email'],
                  $employee['firstname'] . ' ' . $employee['lastname'],
                  null,
                  null,
                  null,
                  null,
                  dirname(__FILE__) . '/../mails/');
    }

    // Done
    return true;
  }

  /**
   * Sends a confirmation to the given customer.
   */
  public static function send_confirmation_to_customer($customer, $password, $identity_provider) {
    // Get the language identifier.
    $context = Context::getContext();
    $language_id = $context->language->id;

    // Setup the mail vars.
    $mail_vars = array();
    $mail_vars['{firstname}'] = $customer->firstname;
    $mail_vars['{lastname}'] = $customer->lastname;
    $mail_vars['{email}'] = $customer->email;
    $mail_vars['{passwd}'] = $password;
    $mail_vars['{identity_provider}'] = html_entity_decode($identity_provider);

    // Send mail to customer.
    return @Mail::Send((int) $language_id,
                       'oauthsso_customer',
                       Mail::l('Welcome!'),
                       $mail_vars,
                       $customer->email,
                       $customer->firstname . ' ' . $customer->lastname,
                       null,
                       null,
                       null,
                       null,
                       dirname(__FILE__) . '/../mails/');
  }

  /**
   * Returns the customer identifier for a given email address.
   */
  public static function get_id_customer_for_email_address($email_address) {
    // Make sure it is not empty.
    $email_address = trim($email_address);
    if (strlen($email_address) == 0) {
      return false;
    }

    // Check if the user account exists.
    $sql = "SELECT *
              FROM `" . _DB_PREFIX_ . "customer`
             WHERE `email` = '" . pSQL($email_address) . "'
               AND `deleted` = 0
               AND `is_guest` = 0";
    $result = Db::getInstance()->getRow($sql);

    // Either return the id_customer or false if none has been found.
    return (!empty($result['id_customer']) ? $result['id_customer'] : false);
  }

  /**
   * Returns the customer identifier for a given token.
   */
  public static function get_id_customer_for_user_token($user_token) {
    // Make sure it is not empty.
    $user_token = trim($user_token);
    if (strlen($user_token) == 0) {
      return false;
    }

    // Read the id_customer for this user_token.
    $sql = "SELECT `id_oasso_user`,
                   `id_customer`
              FROM `" . _DB_PREFIX_ . "oasso_user`
             WHERE `user_token` = '" . pSQL($user_token) . "'";
    $row_oasso_user = Db::getInstance()->GetRow($sql);

    // We have found an entry for this customers.
    if (!empty($row_oasso_user['id_customer'])) {
      $id_customer = intval($row_oasso_user['id_customer']);
      $id_oasso_user = intval($row_oasso_user['id_oasso_user']);

      // Check if the user account exists.
      $sql = "SELECT `id_customer`
                FROM `" . _DB_PREFIX_ . "customer`
               WHERE `id_customer` = " . pSQL($id_customer);
      $row_customer = Db::getInstance()->GetRow($sql);

      // The user account exists, return it's identifier.
      if (!empty($row_customer['id_customer'])) {
        return $row_customer['id_customer'];
      }

      // Delete the wrongly linked user_token.
      $sql = "DELETE
                FROM `" . _DB_PREFIX_ . "oasso_user`
               WHERE `user_token` = '" . pSQL($user_token) . "'
               LIMIT 1";
      $result = Db::getInstance()->execute($sql);

      // Delete the wrongly linked identity_token.
      $sql = "DELETE
                FROM `" . _DB_PREFIX_ . "oasso_identity`
               WHERE `id_oasso_user` = '" . pSQL($id_oasso_user) . "'";
      $result = Db::getInstance()->execute($sql);
    }

    // No entry found.
    return false;
  }

  /**
   * Send an OAuth request by using the given handler
   */
  public static function do_oauth_request($handler, $url, $cmd, $options = array(), $timeout = 15) {
    if ($handler == 'fsockopen') {
      //FSOCKOPEN
      return self::do_fsockopen_request($url, $cmd, $options, $timeout);
    } else {
      // CURL
      return self::do_curl_request($url, $cmd, $options, $timeout);
    }
  }

  /**
   * Check if fsockopen can be used
   */
  public static function check_fsockopen($secure = true, $host = '') {
    if (empty($host)) $host = Configuration::get('OASSO_OAUTH_SERVER_NAME');
    $result = self::do_fsockopen_request(($secure ? 'https' : 'http') . '://' . $host . '/');
    if (is_object($result) and property_exists($result, 'http_code') and $result->http_code == 200) {
      if (property_exists($result, 'http_data')) {
        if (strlen($result->http_data) > 0) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Check if CURL can be used
   */
  public static function check_curl($secure = true, $host = '') {
    if (empty($host)) $host = Configuration::get('OASSO_OAUTH_SERVER_NAME');
    if (in_array('curl', get_loaded_extensions()) and function_exists('curl_exec')) {
      $result = self::do_curl_request(($secure ? 'https' : 'http') . '://' . $host . '/');
      if (is_object($result) and property_exists($result, 'http_code') and $result->http_code == 200) {
        if (property_exists($result, 'http_data')) {
          if (strlen($result->http_data) > 0) {
            return true;
          }
        }
      }
    }

    return false;
  }

  /**
   * Sends a CURL request
   */
  public static function do_curl_request($url, $cmd = 'get',  $options = array(), $timeout = 15) {
    // Store the result
    $result = new stdClass();

    // Send request
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, FALSE);
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_VERBOSE, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
    curl_setopt($curl, CURLOPT_FRESH_CONNECT, TRUE);

    // BASIC AUTH?
    $auth = (isset($options['client_id']) and isset($options['client_secret']));
    if ( $auth ) {
      curl_setopt($curl, CURLOPT_USERPWD, $options['client_id'] . ":" . $options['client_secret']);
      // BEARER?
    } elseif ( isset($options['bearer']) ) {
      $auth = true;
      $authorization = "Authorization: Bearer " . $options['bearer'];
      curl_setopt($curl, CURLOPT_HTTPHEADER, array($authorization));

      // When this header is used, redirections must be followed
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    }

    // POST request?
    if ($cmd == 'post') {
      curl_setopt($curl, CURLOPT_POST, 1);
      $post_fields = '';
      foreach ($options as $option => $value) {
        if (!$auth or ($option !== 'client_id' and $option !== 'client_secret' and $option !== 'bearer') ) {
          $post_fields .= empty($post_fields) ? "" : "&";
          $post_fields .= "{$option}={$value}";
        }
      }
      if ( !empty($post_fields) ) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
      }
      // GET request?
    } elseif ($cmd == 'get') {
      // Add query params?
      $get_fields = '';
      foreach ($options as $option => $value) {
        if (!$auth or ($option !== 'client_id' and $option !== 'client_secret' and $option !== 'bearer') ) {
          $get_fields .= empty($get_fields) ? "" : "&";
          $get_fields .= "{$option}={$value}";
        }
      }
      if ( !empty($get_fields) ) {
        curl_setopt($curl, CURLOPT_URL, $url . '?' . $get_fields);
      }
    }

    // Make request
    if (($http_data = curl_exec($curl)) !== false) {
      $result->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      $result->http_data = $http_data;
      $result->http_error = null;
      $result->redirect_url = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
    } else {
      $result->http_code = -1;
      $result->http_data = null;
      $result->http_error = curl_error($curl);
      $result->redirect_url = null;
    }

    // Done
    return $result;
  }

  /**
   * Sends an fsockopen request
   */
  public static function do_fsockopen_request($url, $cmd = 'get', $options = array(), $timeout = 15, $depth = 0) {
    // Store the result
    $result = new stdClass();

    // Make that this is a valid URL
    if (($uri = parse_url($url)) == false) {
      $result->http_code = -1;
      $result->http_data = null;
      $result->http_error = 'invalid_uri';

      return $result;
    }

    // Make sure we can handle the schema
    switch ($uri['scheme']) {
      case 'http':
        $port = (isset($uri['port']) ? $uri['port'] : 80);
        $host = ($uri['host'] . ($port != 80 ? ':' . $port : ''));
        $fp = @fsockopen($uri['host'], $port, $errno, $errstr, $timeout);
        break;

      case 'https':
        $port = (isset($uri['port']) ? $uri['port'] : 443);
        $host = ($uri['host'] . ($port != 443 ? ':' . $port : ''));
        $fp = @fsockopen('ssl://' . $uri['host'], $port, $errno, $errstr, $timeout);
        break;

      default:
        $result->http_code = -1;
        $result->http_data = null;
        $result->http_error = 'invalid_schema';

        return $result;
        break;
    }

    // Make sure the socket opened properly
    if ( !$fp ) {
      $result->http_code = -$errno;
      $result->http_data = null;
      $result->http_error = trim($errstr);

      return $result;
    }

    // Construct the path to act on
    $path = (isset($uri['path']) ? $uri['path'] : '/');
    if (isset($uri['query'])) {
      $path .= '?' . $uri['query'];
    }

    // Create HTTP request
    $content = '';
    $follow_location = false;
    $headers = array(
      'Host'          => 'Host: ' . $host,
      'User-Agent'    => 'User-Agent: ' . self::USER_AGENT,
      'Cache-Control' => 'Cache-Control: no-cache'
    );

    // BASIC AUTH?
    $auth = (isset($options['client_id']) and isset($options['client_secret']));
    if ( $auth ) {
      $headers['Authorization'] = 'Authorization: Basic ' . base64_encode($options['client_id'] . ":" . $options['client_secret']);
      // BEARER?
    } elseif ( isset($options['bearer']) ) {
      $auth = true;
      $headers['Authorization'] = 'Authorization: Bearer ' . $options['bearer'];

      // When this header is used, redirections must be followed
      $follow_location = true;
    }

    // POST request?
    if ($cmd == 'post') {
      $post_fields = '';
      foreach ($options as $option => $value) {
        if (!$auth or ($option !== 'client_id' and $option !== 'client_secret' and $option !== 'bearer') ) {
          $post_fields .= empty($post_fields) ? "" : "&";
          $post_fields .= "{$option}={$value}";
        }
      }
      if ( !empty($post_fields) ) {
        $content = $post_fields . "\r\n";
        $headers['Content-Type'] = 'Content-Type: application/x-www-form-urlencoded';
        $headers['Content-Length'] = 'Content-Length: ' . strlen($post_fields);
      }
      // GET request?
    } elseif ($cmd == 'get') {
      // Add query params?
      $get_fields = '';
      foreach ($options as $option => $value) {
        if (!$auth or ($option !== 'client_id' and $option !== 'client_secret' and $option !== 'bearer') ) {
          $get_fields .= empty($get_fields) ? "" : "&";
          $get_fields .= "{$option}={$value}";
        }
      }
      if ( !empty($get_fields) ) {
        $path .= (isset($uri['query']) ? "&" : "?") . $get_fields;
      }
    }

    // Build and send request
    $request = strtoupper($cmd) . ' ' . $path . " HTTP/1.0\r\n";
    $request .= implode("\r\n", $headers);
    $request .= "\r\n\r\n";
    $request .= empty($content) ? '' : $content;
    fwrite($fp, $request);

    // Fetch response
    $response = '';
    while (!feof($fp)) {
      $response .= fread($fp, 1024);
    }

    // Close connection
    fclose($fp);

    // Parse response
    list($response_headers, $response_body) = explode("\r\n\r\n", $response, 2);

    // Parse header
    $response_headers = preg_split("/\r\n|\n|\r/", $response_headers);
    list($header_protocol, $header_code, $header_status_message) = explode(' ', trim(array_shift($response_headers)), 3);
    $redirect_url = null;

    // Some headers are processed
    foreach($response_headers as $header) {
      list($header_name, $header_value) = explode(': ', trim($header), 2);

      // Location header (HTTP STATUS 301, 302)
      if ( $header_name === 'Location' ) {
        $redirect_url = $header_value;
      }

      // Set-Cookie header
      if ( $header_name === 'Set-Cookie' ) {
        self::set_cookie($host, $header_value);
      }
    }

    // Follow redirections, if required
    if ($follow_location and ($header_code == 301 or $header_code == 302) and !empty($redirect_url)) {
      if ($depth < 50) {
        return self::do_fsockopen_request($redirect_url, $cmd, $options, $timeout, $depth + 1);
      } else {
        $header_code = 500;
        $header_status_message = 'ERR_TOO_MANY_REDIRECTS';
      }
    }

    // Build result
    $result->http_code = $header_code;
    $result->http_data = $response_body;
    $result->redirect_url = $redirect_url;
    $result->http_error = $header_status_message;

    // Done
    return $result;
  }

  /**
   * Creates a cookie using the info of a Set-Cookie header.
   */
  public static function set_cookie($domain, $header) {
    $name = '';
    $value = '';
    $expires = 0;
    $path = '';
    $domain = $domain;
    $secure = false;
    $httponly = false;

    // Parse parts of the Set-Cookie header
    $cookie_parts = explode('; ', trim($header), 6);
    foreach($cookie_parts as $item) {
      if ( substr($item, 0 , 8) === 'expires=' ) {
        $expires = strtotime(substr($item, 8));
      } elseif ( substr($item, 0, 8) === 'Max-Age=' ) {
        $expires = time() + intval(substr($item, 8));
      } elseif ( substr($item, 0, 5) === 'path=' ) {
        $path = substr($item, 5);
      } elseif ( substr($item, 0, 6) === 'secure' ) {
        $secure = true;
      } elseif ( substr($item, 0, 8) === 'HttpOnly' ) {
        $httponly = true;
      } else {
        list($name, $value) = explode("=", $item, 2);
      }
    }

    // At least name is required
    if ( empty($name) ) {
      return false;
    }

    // Set the cookie
    return setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
  }

  /**
   * Returns the signon URI
   */
  public static function get_signon_uri($include_return_to_param = false, $remove_oauth_params = true) {
    $context = Context::getContext();

    // Current URL
    $current_url = self::get_current_url();

    // Break up url
    list($url_part, $query_part) = array_pad(explode('?', $current_url), 2, '');
    parse_str($query_part, $query_vars);

    // Remove oauth parameters?
    if ($remove_oauth_params) {
      // Parameters to remove
      $params = array('auth', 'code', 'state');

      // Remove params if present on current query string
      foreach($params as $param) {
        if (is_array($query_vars) && isset($query_vars[$param])) {
          unset($query_vars[$param]);
        }
      }
    }

    // Resolve back parameter to prevent ERR_TO_MANY_REDIRECTS error
    if (is_array($query_vars) && isset($query_vars['back'])) {
      $url_part = $context->link->getPageLink($query_vars['back'], true);
      unset($query_vars['back']);

      // Prevent chromium browsers using cached content
      $query_vars['nocache'] = rand();
    }

    // Build new url
    $junction = (strpos($url_part, "?") === false) ? "?" : "&";
    $current_url = $url_part . ((is_array($query_vars) && count($query_vars) > 0) ? ($junction . http_build_query($query_vars)) : '');

    // Prevent the browser caching OAuth calls
    $params = array('nocache' => rand());

    // Add return_to parameter?
    if ($include_return_to_param) {
      $params['return_to'] = $current_url;
    }

    // Build sigon uri
    return $context->link->getModuleLink('oauthsso', 'signon', $params);
  }

  /**
   * Returns the current url
   */
  public static function get_current_url() {
    // Get request URI - Should work on Apache + IIS
    $request_uri = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']);
    $request_protocol = (self::is_https_on() ? 'https' : 'http');
    $request_host = (isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));

    // Make sure we strip $request_host so we got no double ports un $current_url
    $request_host = preg_replace('/:[0-9]*$/', '', $request_host);

    // We are using a proxy
    if (isset($_SERVER['HTTP_X_FORWARDED_PORT'])) {
      // SERVER_PORT is usually wrong on proxies, don't use it!
      $request_port = intval($_SERVER['HTTP_X_FORWARDED_PORT']);
    } elseif (isset($_SERVER['SERVER_PORT'])) { //Does not seem like a proxy
      $request_port = intval($_SERVER['SERVER_PORT']);
    }

    // Remove standard ports
    $request_port = (!in_array($request_port, array(80, 443)) ? $request_port : '');

    // Build url
    $current_url = $request_protocol . '://' . $request_host . (!empty($request_port) ? (':' . $request_port) : '') . $request_uri;

    // Done
    return $current_url;
  }

  /**
   * Check if the current connection is being made over https
   */
  public static function is_https_on() {
    if (!empty($_SERVER['SERVER_PORT'])) {
      if (trim($_SERVER['SERVER_PORT']) == '443') {
        return true;
      }
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
      if (strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO'])) == 'https') {
        return true;
      }
    }

    if (!empty($_SERVER['HTTPS'])) {
      if (strtolower(trim($_SERVER['HTTPS'])) == 'on' or trim($_SERVER['HTTPS']) == '1') {
        return true;
      }
    }

    // HTTPS is off.
    return false;
  }

  /**
   * Build the parameters to call the API.
   */
  public static function get_api_parameters() {
    $api = new stdClass();

    // Return path URI. If not set on the call, jump to shop home page
    $api->return_to = Tools::getIsset('return_to') ? trim(Tools::getValue('return_to')) : Tools::getHttpHost(true) . __PS_BASE_URI__;

    // OAuth redirect URI
    $api->callback_uri  = Tools::getHttpHost(true) . __PS_BASE_URI__;
    $api->callback_uri .= (parse_url($api->callback_uri, PHP_URL_QUERY) ? '&' : '?');
    $api->callback_uri .= 'auth=sso';

    // OAuth 2.0 Credentials
    $api->oauth_server_name = Configuration::get('OASSO_OAUTH_SERVER_NAME');
    $api->client_id = Configuration::get('OASSO_CLIENT_ID');
    $api->client_secret = Configuration::get('OASSO_CLIENT_SECRET');

    // API Settings
    $api->connection_port = Configuration::get('OASSO_API_PORT');
    $api->connection_port = ($api->connection_port == 80 ? 80 : 443);
    $api->connection_use_https = ($api->connection_port == 443);

    $api->connection_handler = Configuration::get('OASSO_API_HANDLER');
    $api->connection_handler = ($api->connection_handler == 'fsockopen' ? 'fsockopen' : 'curl');

    $api->base_url = ($api->connection_use_https ? 'https' : 'http') . '://' . $api->oauth_server_name;

    return $api;
  }

  /**
   * OAuth 2.0
   * First call: Ask for authorization
   */
  public static function ask_for_authorization($api) {
    // Get current context
    $context = Context::getContext();

    // Only if the user is not logged in.
    if ($context->customer->isLogged()) {
      return null;
    }

    // CSRF Token
    $CSRF_token = bin2hex(random_bytes(32));

    // Add to cookie data
    $data['return_to'] = $api->return_to;
    $data['csrf_token'] = $CSRF_token;

    // Save the data in the session.
    $context->cookie->oasso_data = json_encode($data);
    $context->cookie->write();

    // Call OAuth 2.0 identity server
    $result = OAuthSSOHelper::do_oauth_request( $api->connection_handler,
                                                $api->base_url . '/oauth/authorize',
                                                'get',
                                                array('client_id'     => $api->client_id,
                                                      'redirect_uri'  => $api->callback_uri,
                                                      'response_type' => 'code',
                                                      'scope'         => 'profile',
                                                      'state'         => $CSRF_token));

    $error_code = 0;
    $error_msg = '';

    // Parse result
    if (is_object($result) and property_exists($result, 'http_code') and property_exists($result, 'http_data')) {
      switch ($result->http_code) {
        // Success
        case 200 :
          // Not really expected, but if happens just display reponse and die.
          echo $result->http_data;
          die();
        break;

        // Redirect
        case 301 :
          // Redirect (this can be a login screen or a call to the callback)
          Tools::redirect($result->redirect_url);
        break;

        // Error
        default :
          $error_code = $result->http_code;
          $error_msg = "HTTP ERROR CODE: {$result->http_code}";
          $response = json_decode($result->http_data);
          if (is_object ($response) and property_exists($response, 'error') and property_exists($response, 'error_description')) {
            $error_msg .= " Error: {$response->error} Error description: {$response->error_description}";
          }
        break;
      }
    } else {
      $error_code = 500;
      $error_msg = "Call to OAuth 2.0 API does not return something I can understand.";
    }

    // Log error
    $error_msg = 'OAuth SSO: Attemp to Ask for authorization: ' . $error_msg;
    error_log($error_msg);
    PrestaShopLogger::addLog($error_msg, 1, $error_code, null, null, true);

    return $api->return_to;
  }

  /**
   * OAuth 2.0
   * Second call: Get access token
   */
  public static function get_access_token($context, $api, $oauth_code, $outh_state) {
    // Only if the user is not logged in.
    if ($context->customer->isLogged()) {
      return null;
    }

    // Call OAuth 2.0 identity server
    $result = OAuthSSOHelper::do_oauth_request( $api->connection_handler,
                                                $api->base_url . '/oauth/token',
                                                'post',
                                                array('client_id'     => $api->client_id,
                                                      'client_secret' => $api->client_secret,
                                                      'grant_type'    => 'authorization_code',
                                                      'code'          => $oauth_code,
                                                      'redirect_uri'  => $api->callback_uri,
                                                      'state'         => $outh_state));

    $response = null;
    $token = new stdClass();
    $error_code = 0;
    $error_msg = '';

    // Parse result
    if (is_object($result) and property_exists($result, 'http_code') and property_exists($result, 'http_data')) {
      switch ($result->http_code) {
        // Success
        case 200 :
          $response = json_decode($result->http_data);
          if (is_object($response)) {
            $token->access_token  = property_exists($response, 'access_token')  ? $response->access_token  : null;
            $token->expires_in    = property_exists($response, 'expires_in')    ? $response->expires_in    : 0;
            $token->scope         = property_exists($response, 'scope')         ? $response->scope         : "basic";
            $token->token_type    = property_exists($response, 'token_type')    ? $response->token_type    : 'unknown';
            $token->refresh_token = property_exists($response, 'refresh_token') ? $response->refresh_token : null;
          }
          if ( !is_null($token->access_token) ) {
            // Return the access token
            return $token;
          }
        break;

        // Redirect
        case 301 :
          Tools::redirect($result->redirect_url);
        break;

        // Error
        default :
          $error_code = $result->http_code;
          $error_msg = "HTTP ERROR CODE: {$result->http_code}";
          $response = json_decode($result->http_data);
          if (is_object ($response) and property_exists($response, 'error') and property_exists($response, 'error_description')) {
            $error_msg .= " Error: {$response->error} Error description: {$response->error_description}";
          }
        break;
      }
    } else {
      $error_code = 500;
      $error_msg = "Call to OAuth 2.0 API does not return something I can understand.";
    }

    if (is_object($response) and property_exists($response, 'error')) {
      if ( $error_code === 0 ) {
        $error_code = 500;
      }
      $error_msg = "Error: {$response->error} Message: {$response->error_description}";
    }

    // Log error
    $error_msg = 'OAuth SSO: Attemp to Get Access Token: ' . $error_msg;
    error_log($error_msg);
    PrestaShopLogger::addLog($error_msg, 1, $error_code, null, null, true);

    // Error
    return null;
  }

  /**
   * OAuth 2.0
   * Third call: Retrieve user details
   */
  public static function get_user_details($context, $api, $token) {
    // Only if the user is not logged in.
    if ($context->customer->isLogged()) {
      return null;
    }

    // Call OAuth 2.0 identity server
    $result = OAuthSSOHelper::do_oauth_request( $api->connection_handler,
                                                $api->base_url . '/oauth/me',
                                                'get',
                                                array('bearer' => $token->access_token));

    $error_code = 0;
    $error_msg = '';

    // Parse result
    if (is_object($result) and property_exists($result, 'http_code') and property_exists($result, 'http_data')) {
      switch ($result->http_code) {
        // Success
        case 200 :
          // Return user details as an associative array
          return json_decode($result->http_data, true);
        break;

        // Error
        default :
          $error_code = $result->http_code;
          $error_msg = "HTTP ERROR CODE: {$result->http_code}";
          $response = json_decode($result->http_data);
          if (is_object ($response) and property_exists($response, 'error') and property_exists($response, 'error_description')) {
            $error_msg .= " Error: {$response->error} Error description: {$response->error_description}";
          }
        break;
      }
    } else {
      $error_code = 500;
      $error_msg = "Call to OAuth 2.0 API does not return something I can understand.";
    }

    if (is_object($response) and property_exists($response, 'error')) {
      if ( $error_code === 0 ) {
        $error_code = 500;
      }
      $error_msg = "Error: {$response->error} Message: {$response->error_description}";
    }

    // Log error
    $error_msg = 'OAuth SSO: Attemp to Get User Details: ' . $error_msg;
    error_log($error_msg);
    PrestaShopLogger::addLog($error_msg, 1, $error_code, null, null, true);

    // Error
    return null;
  }

  /**
   * OAuth 2.0
   * This manages the callback from the Identity Server to the end-point:
   *     https://my-server.com/?oauth=sso
   *
   * It should contain the authorization code (from the identity server)
   * and the CSRF token (state) sent on the previous `ask for authorization` request.
   */
  public static function oauth_callback() {
    // Load the context.
    $context = Context::getContext();

    // Only if the user is not logged in.
    if ($context->customer->isLogged()) {
      return null;
    }

    // Check for callback arguments.
    if (Tools::getIsset('auth') !== true) {
      // This is not really a callback (just a regular page load request)
      return null;
    }

    // Extract the callback arguments.
    $callback_type = trim(Tools::getValue('auth'));
    $oauth_code = trim(Tools::getValue('code'));
    $oauth_state = trim(Tools::getValue('state'));

    // Verify arguments
    if ($callback_type != 'sso' or empty($oauth_code)) {
      // Don't really know what to do with this callback
      $error_msg = "OAuth SSO: Unmanaged callback type: {$callback_type} Code: {$oauth_code}";
      error_log($error_msg);
      PrestaShopLogger::addLog($error_msg, 1, 801, null, null, true);

      // Error
      return null;
    }

    // Extract extra data from cookie
    if (!isset($context->cookie->oasso_data)) {
      // Was not originated by this module (class OauthssoSignonModuleFrontController)
      $error_msg = "OAuth SSO: Callback not recognized as legitimate [no-cookie]";
      error_log($error_msg);
      PrestaShopLogger::addLog($error_msg, 1, 802, null, null, true);

      // Error
      return null;
    }
    $data = json_decode($context->cookie->oasso_data, true);
    $return_to = isset($data['return_to']) ? $data['return_to'] : '';
    $csrf_token = isset($data['csrf_token']) ? $data['csrf_token'] : '';

    // Verify this is a legal callback originated by this session
    if ($csrf_token != $oauth_state) {
      // Was not originated by this module (class OauthssoSignonModuleFrontController)
      $error_msg = "OAuth SSO: Callback not recognized as legitimate [wrong-state]";
      error_log($error_msg);
      PrestaShopLogger::addLog($error_msg, 1, 803, null, null, true);

      // Error
      return null;
    }

    // Build parameters to call Oauth 2.0 API
    $api = self::get_api_parameters();

    // Obtain an access token using the authorization code
    $token = self::get_access_token($context, $api, $oauth_code, $oauth_state);

    // Obtain user details using the access token
    if ( !is_null($token) ) {
      $user_data = self::get_user_details($context, $api, $token);

      // Login user using user data
      if ( !is_null($user_data) ) {
        self::perform_user_login($user_data, $return_to);
      }
    }

    // Redirect to the origination page (or to home page)
    if ( empty($return_to) ) {
      $return_to = $api->return_to;
    }
    Tools::redirect($return_to);
  }

  /**
   * Perform Prestashop Login using the data retrieved from the identity server.
   */
  public static function perform_user_login($data, $return_to) {
    // Load the context.
    $context = Context::getContext();

    // Create a token to identify this particular user from the identity server
    $user_token = self::get_user_token($data);
    if ( !$user_token ) {
      // Error: Nothing to use as user token
      $error_msg = "OAuth SSO: User data is imcomplete (user_token): " . json_encode($data);
      error_log($error_msg);
      PrestaShopLogger::addLog($error_msg, 1, 900, null, null, true);
      return null;
    }

    $data['user_token'] = $user_token;
    $data['identity_provider'] = Configuration::get('OASSO_PROVIDER_NAME');
    $data['identity_token'] = self::get_identity_token($data);

    // Get the customer identifier for a given token.
    $id_customer_tmp = self::get_id_customer_for_user_token($data['user_token']);

    // This customer already exists.
    if (is_numeric($id_customer_tmp)) {
      // Update the identity.
      self::update_identity_logins($data['identity_token']);

      // Update user data (first name, last name, email)
      self::update_user_data($id_customer_tmp, $data);

      // Login this customer.
      $id_customer = $id_customer_tmp;
    } else { // This is a new customer.
      // Account linking is enabled.
      if (Configuration::get('OASSO_LINK_ACCOUNT_DISABLE') != 1) {
        // Account linking is done based on user's email address
        if (!empty($data['user_email'])) {
          // Try to read the existing customer account.
          if (($id_customer_tmp = self::get_id_customer_for_email_address($data['user_email'])) !== false) {
            // Tie the user_token to the customer.
            if (self::link_tokens_to_id_customer($id_customer_tmp,
                $data['user_token'],
                $data['identity_token'],
                $data['identity_provider']) === true) {
              // Update the identity.
              self::update_identity_logins($data['identity_token']);

              // Login this customer.
              $id_customer = $id_customer_tmp;
            }
          }
        }
      }
    }

    // Create a new user account.
    if (empty($id_customer)) {
      // Notify the customer ?
      $customer_email_notify = true;

      // Allow some variants
      $data['user_first_name'] = empty($data['user_first_name']) ? $data['first_name'] : $data['user_first_name'];
      $data['user_last_name']  = empty($data['user_last_name'])  ? $data['last_name']  : $data['user_last_name'];

      // How do we have to proceed?
      switch (Configuration::get('OASSO_DATA_HANDLING')) {
        // Automatic Completion.
        case 'auto':
          // Generate a random email if none is provided or if it's already taken.
          if (empty($data['user_email']) or self::get_id_customer_for_email_address($data['user_email']) !== false) {
            // Generate a random email.
            $data['user_email'] = self::generate_random_email_address();

            // But do not send notifications to this email
            $customer_email_notify = false;
          }

          // Generate a lastname if none is provided.
          if (empty($data['user_last_name'])) {
            $data['user_last_name'] = Module::getInstanceByName('oauthsso')->translate('Username');
          }

          // Generate a firstname if none is provided.
          if (empty($data['user_first_name'])) {
            $data['user_first_name'] = Module::getInstanceByName('oauthsso')->translate('Sample');
          }
          break;

        // Ask for manual completion if any of the fields is empty or if the email is already taken.
        case 'ask':
          if (empty($data['user_email']) || empty($data['user_first_name']) || empty($data['user_last_name']) || self::get_id_customer_for_email_address($data['user_email']) !== false) {
            // Add to cookie data
            $data['return_to'] = $return_to;

            // Save the data in the session.
            $context->cookie->oasso_data = json_encode($data);
            $context->cookie->write();

            // Redirect to the Single SignOn registration form
            header('Location: ' . $context->link->getModuleLink('oauthsso', 'register'));
            exit();
          }
          break;

        // Always verify the fields
        default:
          // Add to cookie data
          $data['return_to'] = $return_to;

          // Save the data in the session.
          $context->cookie->oasso_data = json_encode($data);
          $context->cookie->write();

          // Redirect to the Single SignOn registration form
          header('Location: ' . $context->link->getModuleLink('oauthsso', 'register'));
          exit();
          break;
      }

      // Email flags.
      $send_email_to_admin = ((Configuration::get('OASSO_EMAIL_ADMIN_DISABLE') != 1) ? true : false);
      $send_email_to_customer = ($customer_email_notify == true and Configuration::get('OASSO_EMAIL_CUSTOMER_DISABLE') != 1);

      // Create a new account.
      $id_customer = self::create_customer_from_data($data, $send_email_to_admin, $send_email_to_customer);
    }

    // If we have a customer
    if (!empty($id_customer)) {
      // Load customer data
      $customer = self::load_customer($id_customer);

      // Perform role to group mappings
      if (!empty($customer) && Configuration::get('OASSO_ROLE_MAPPING_ENABLE') ) {
        $roles = self::get_user_roles($data);
        self::perform_roles_mappings($customer, $roles);
      }

      // Login.
      if (!empty($customer) && self::login_customer($customer)) {
        // Remove OAuth SSO Cookie
        if (isset($context->cookie->oasso_data)) {
            unset($context->cookie->oasso_data);
        }

        // Redirect
        Tools::redirect($return_to);
      }
    }

    // Login failed
    $error_msg = "OAuth SSO: Login failed for " . json_encode($data);
    error_log($error_msg);
    PrestaShopLogger::addLog($error_msg, 1, 901, null, null, true);

    // Redirect to the Single SignOn fatal error page
    header('Location: ' . $context->link->getModuleLink('oauthsso', 'fatal'));
    exit();
  }

  /**
   * Returns an array with the names of the roles for this user.
   */
  public static function get_user_roles($data) {
    $result = array();

    // Is role mappings enabled?
    if ( !Configuration::get('OASSO_ROLE_MAPPING_ENABLE') ) {
      throw new Exception("OAuthSSOHelper::get_user_roles() called, but roles mapping is disabled.");
    }

    // Get configuration settings for role mapping
    $prop   = Configuration::get('OASSO_ROLES_CONTAINER_PROPERTY');
    $format = Configuration::get('OASSO_ROLES_CONTAINER_FORMAT');

    // Was a container property set?
    if ( empty($prop) ) {
      return $result;
    }

    // Does user data include that property?
    if ( !array_key_exists($prop, $data) ) {
      return $result;
    }

    // Actual raw value from identity server
    $raw_roles = $data[$prop];

    // Is the property value empty?
    if ( empty($raw_roles) ) {
      return $result;
    }

    // Parse prpperty value
    switch ($format) {
      // List of comma separated values or a single value
      case 'list':
        if ( is_string($raw_roles)) {
          $list = explode(',', $raw_roles);
          if ( is_array($list) ) {
            foreach($list as $role) {
              $result[] = trim($role);
            }
          }
        }
        break;

      // Array of values. Role names are array's keys.
      case 'array_k':
        if ( is_array($raw_roles) ) {
          foreach($raw_roles as $role => $value) {
            $result[] = $role;
          }
        }
        break;

      // Array of values. Role names are array's values.
      case 'array_v':
        if ( is_array($raw_roles) ) {
          foreach($raw_roles as $key => $role) {
            $result[] = $role;
          }
        }
        break;

      // Unknown format
      default:
        throw new Exception("OAuthSSOHelper::get_user_roles() unknown format for role names property: {$format}");
        break;
    }

    // Return the array of roles (might be empty)
    return $result;
  }

  /**
   * Add the customer to PrestaShop groups according to roles mappings.
   *
   * @param  object $customer Current customer object
   * @param  array  $roles    List of roles the customer belongs to
   * @return bool             If any mapping has been performed.
   */
  public static function perform_roles_mappings($customer, $roles) {
    $set_default_group = null;

    // Is role mappings enabled?
    if ( !Configuration::get('OASSO_ROLE_MAPPING_ENABLE') ) {
      throw new Exception("OAuthSSOHelper::map_user_roles() called, but roles mapping is disabled.");
    }

    // Get roles mapping configuration
    $mappings = Module::getInstanceByName('oauthsso')->get_roles_mappings();

    // Clean-up all groups before mapping?
    if ( Configuration::get('OASSO_ROLES_CLEANUP') ) {
      // Clean-up current customer groups
      $customer->cleanGroups();

      // A PS user must be either a Guest or a Customer
      if ($customer->is_guest) {
        $set_default_group = (int) Configuration::get('PS_GUEST_GROUP');
      } else {
        $set_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
      }
      $customer_groups = array();
      $additional_groups = array($set_default_group);
    } else {
      // Get current customer groups
      $customer_groups = $customer->getGroups();
      $additional_groups = array();
    }

    // Process all role mappings
    foreach($mappings as $role_map) {

      // Process all user roles (the same role can be mapped to several groups)
      foreach($roles as $role) {

        // Does the role name match current mapping?
        if ( !empty($role) and ($role === $role_map['role']) ) {
          $id_group = $role_map['id_group'];

          // Check that the user doesn't belong to that group
          if ( !in_array($id_group, $customer_groups, true) ) {
            $additional_groups[] = $id_group;

            // Set default group if requested by the mapping (the last will persist)
            if ( $role_map['default'] ) {
              $set_default_group = $id_group;
            }
          }
        }
      }
    }

    // Any new group to add the customer to?
    if ( !empty($additional_groups) and is_array($additional_groups) and (sizeof($additional_groups) > 0)) {
      // Add the customer to the mapped groups
      $customer->addGroups($additional_groups);

      // Update default group if requested by any mapping
      if ( null !== $set_default_group) {
        $customer->id_default_group = $set_default_group;
        $customer->update();
      }

      // User's group subscriptions updated
      return true;
    }

    // User's group subscriptions unchanged
    return false;
  }
}
