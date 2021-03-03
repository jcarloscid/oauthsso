{*
* @package   	OAuth Single SignOn
* @author     Carlos Cid <carlos@fishandbits.es>
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
*}

{capture name=path}{l s='Fatal error' mod='oauthsso'}{/capture}

{extends file='page.tpl'}

{block name="page_content"}

<div class="oauthsso-wrapper oauthsso-fatal-error">
  <h1 class="page-heading bottom-indent">{l s='OAuth 2.0 Single SignOn Client' mod='oauthsso'}</h1>
  <h2>{l s='Fatal error' mod='oauthsso'}</h2>
  <p>{l s='Unable to perform Single Sign On.' mod='oauthsso'}</p>
  <p>{l s='Error details has been logged. Please, try again later and if the problem persists report the incident to Webmaster.' mod='oauthsso'}</p>
  <div class="submit">
    <a href="{$oasso_continue_url}" class="btn btn-default button button-medium"><span>{l s='Continue' mod='oauthsso'}<i class="icon-chevron-right right"></i></span></a>
  </div>
</div>

{/block}
