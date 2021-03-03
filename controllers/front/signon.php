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
class OauthssoSignonModuleFrontController extends ModuleFrontController {
  public $auth = false;
  public $guestAllowed = true;
  public $ssl = true;

  public function initContent() {
    parent::initContent();
    global $smarty;

    $api = OAuthSSOHelper::get_api_parameters();
    $return_to = OAuthSSOHelper::ask_for_authorization($api);

    // Display fatal error screen
    if ( !is_null($return_to) ) {
      $smarty->assign('oasso_continue_url', $return_to);
      $this->setTemplate('module:oauthsso/views/templates/front/oauth_sso_fatal.tpl');
    }
  }
}
