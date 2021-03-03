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

{capture name=path}{l s='Create an account' mod='oauthsso'}{/capture}

{extends file='page.tpl'}

{block name="page_content"}

<div class="oauthsso-wrapper oauthsso-register">
	<h1 class="page-heading bottom-indent">{l s='You have connected with %s!' sprintf=[$identity_provider|strip_tags] mod='oauthsso'}</h1>
	<p>
		{l s='Please take a minute to review and complete your account information.' mod='oauthsso'}
		{l s='Once you have reviewed your details, your account is ready to use and you can sign in with %s.' sprintf=[$identity_provider|strip_tags] mod='oauthsso'}
	</p>
	<div id="oauthsso">
		<form id="account-creation_form" action="{$oasso_register}" method="post" class="box">
			<fieldset>
				<div class="form_content clearfix">
					<div class="form-group">
							<label for="oasso_firstname">{l s='First name' mod='oauthsso'} <sup>*</sup></label>
							<input type="text" class="is_required form-control" id="oasso_firstname" name="oasso_firstname" value="{if isset($smarty.post.oasso_firstname)}{$smarty.post.oasso_firstname|stripslashes}{elseif $oasso_populate == '1'}{$oasso_first_name}{/if}" />
					</div>
					<div class="form-group">
							<label for="oasso_lastname">{l s='Last name' mod='oauthsso'} <sup>*</sup></label>
							<input type="text" class="is_required form-control" id="oasso_lastname" name="oasso_lastname" value="{if isset($smarty.post.oasso_lastname)}{$smarty.post.oasso_lastname|stripslashes}{elseif $oasso_populate == '1'}{$oasso_last_name}{/if}" />
					</div>
					<div class="form-group">
							<label for="oasso_email">{l s='Email' mod='oauthsso'} <sup>*</sup></label>
							<input type="text" class="is_required form-control" id="oasso_email" name="oasso_email" value="{if isset($smarty.post.oasso_email)}{$smarty.post.oasso_email|stripslashes}{elseif $oasso_populate == '1'}{$oasso_email}{/if}" />
					</div>
					<div class="checkbox">
						<label for="oasso_newsletter">
							<input type="checkbox" id="oasso_newsletter" name="oasso_newsletter" value="1" {if isset($smarty.post.oasso_newsletter) && $smarty.post.oasso_newsletter == '1'}checked="checked"{elseif isset($oasso_newsletter) && $oasso_newsletter == '1'}checked="checked"{/if} />
							{l s='Sign up for our newsletter!' mod='oauthsso'}
						</label>
					</div>
					<hr />
					<div class="submit">
						<button name="submit" id="submit" type="submit" class="btn btn-default button button-medium"><span>{l s='Confirm' mod='oauthsso'}<i class="icon-chevron-right right"></i></span></button>
					</div>
				</div>
			</fieldset>
		</form>
	</div>
</div>

{/block}
