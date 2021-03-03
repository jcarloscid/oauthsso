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
class OauthssoRegisterModuleFrontController extends ModuleFrontController {
  public $auth = false;
  public $ssl = true;

  /**
   * Assign template vars related to page content
   */
  public function initContent() {
    parent::initContent();
    global $smarty;

    // Did an error occur?
    $have_error = true;

    // The cookie is required to proceed.
    if ( isset($this->context->cookie->oasso_data) ) {
      // Extract the data.
      $data = json_decode($this->context->cookie->oasso_data, true);

      // Check data format.
      if (is_array($data)) {
        $have_error = false;

        // Submit Button Clicked
        if (Tools::isSubmit('submit')) {
          // Current module
          $module = Module::getInstanceByName('oauthsso');

          // Reset Errors.
          $this->errors = array();

          // Read fields.
          $email = trim(Tools::getValue('oasso_email'));
          $firstname = trim(Tools::getValue('oasso_firstname'));
          $lastname = trim(Tools::getValue('oasso_lastname'));
          $newsletter = intval(Tools::getValue('oasso_newsletter'));

          // Make sure the firstname is not empty.
          if (strlen($firstname) == 0) {
            $this->errors[] = $module->translate('Please enter your first name');
            // Make sure the format of the firstname is correct.
          } elseif (!Validate::isName($firstname)) {
            $this->errors[] = $module->translate('Please enter a valid first name');
          }

          // Make sure the lastname is not empty.
          if (strlen($lastname) == 0) {
            $this->errors[] = $module->translate('Please enter your lastname');
            // Make sure the format of the lastname is correct.
          } elseif (!Validate::isName($lastname)) {
            $this->errors[] = $module->translate('Please enter a valid last name');
          }

          // Make sure the email address it is not empty.
          if (strlen($email) == 0) {
            $this->errors[] = $module->translate('Please enter your email address');
            // Make sure the format of the email address is correct.
          } elseif (!Validate::isEmail($email)) {
            $this->errors[] = $module->translate('Please enter a valid email address');
            // Make sure the email address is not already taken.
          } elseif (OAuthSSOHelper::get_id_customer_for_email_address($email) !== false) {
            $this->errors[] = $module->translate('This email address is already taken');
          }

          // We are good to go.
          if (count($this->errors) == 0) {
            // Store the manually entered email fields.
            $data['user_email'] = Tools::strtolower($email);
            $data['user_first_name'] = Tools::ucwords($firstname);
            $data['user_last_name'] = Tools::ucwords($lastname);
            $data['user_newsletter'] = ($newsletter == 1 ? 1 : 0);

            // Email flags.
            $send_email_to_admin = ((Configuration::get('OASSO_EMAIL_ADMIN_DISABLE') != 1) ? true : false);
            $send_email_to_customer = ((Configuration::get('OASSO_EMAIL_CUSTOMER_DISABLE') != 1) ? true : false);

            // Create a new account.
            $id_customer = OAuthSSOHelper::create_customer_from_data($data, $send_email_to_admin, $send_email_to_customer);

            // Load customer data
            $customer = OAuthSSOHelper::load_customer($id_customer);

            // Perform role to group mappings
            if (!empty($customer) && Configuration::get('OASSO_ROLE_MAPPING_ENABLE') ) {
              $roles = OAuthSSOHelper::get_user_roles($data);
              OAuthSSOHelper::perform_roles_mappings($customer, $roles);
            }

            // Login the customer.
            if (!empty($customer) && OAuthSSOHelper::login_customer($customer)) {
              // Redirection URL is set
              $redirect_url = (!empty($data['return_to']) ? $data['return_to'] : OAuthSSOHelper::get_current_url());

              // Remove the data
              unset($this->context->cookie->oasso_data);

              // Redirect
              Tools::redirect($redirect_url);
            }
          }
        } else {
          // First call of the page.
          $smarty->assign('oasso_populate', 1);
          $smarty->assign('oasso_email', (isset($data['user_email']) ? $data['user_email'] : ''));
          $smarty->assign('oasso_first_name', (isset($data['user_first_name']) ? $data['user_first_name'] : ''));
          $smarty->assign('oasso_last_name', (isset($data['user_last_name']) ? $data['user_last_name'] : ''));
          $smarty->assign('oasso_newsletter', 1);
        }

        // Assign template vars.
        $smarty->assign('identity_provider', html_entity_decode($data['identity_provider']));
        $smarty->assign('oasso_register', $this->context->link->getModuleLink('oauthsso', 'register'));

        // Show our template.
        $this->setTemplate('module:oauthsso/views/templates/front/oauth_sso_register.tpl');
      }
    }

    // We could not extract the data.
    if ($have_error) {
      Tools::redirect(OAuthSSOHelper::get_current_url());
    }
  }
}
