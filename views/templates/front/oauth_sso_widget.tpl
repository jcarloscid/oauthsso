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

{capture name='oauthsso_button_txt'}{l s='Login SSO' mod='oauthsso'}{/capture}
{assign var='oasso_translated_button_txt' value=$smarty.capture.oauthsso_button_txt}
{capture name='oauthsso_title'}{l s='Connect with:' mod='oauthsso'}{/capture}
{assign var='oasso_translated_title' value=$smarty.capture.oauthsso_title}
{capture name='oauthsso_title_login'}{l s='Log in with:' mod='oauthsso'}{/capture}
{assign var='oasso_translated_title_login' value=$smarty.capture.oauthsso_title_login}
{capture name='oauthsso_title_register'}{l s='Register with:' mod='oauthsso'}{/capture}
{assign var='oasso_translated_title_register' value=$smarty.capture.oauthsso_title_register}

{* Location: HEAD - Custom CSS *}
{if {$oasso_widget_location} eq 'head'}
	{if !empty($oasso_custom_css)}
  <style>
	{$oasso_custom_css nofilter}
  </style>
	{/if}
{/if}

{* Location: SCRIPTS (footer) - Custom javascript *}
{if {$oasso_widget_location} eq 'scripts'}
  {if !empty($oasso_custom_js)}
	<script type="text/javascript">
    var oauthsso_settings = [];
    oauthsso_settings['sso_provider'] 							= '{$oasso_sso_provider nofilter}';
    oauthsso_settings['oauth_server_name'] 					= '{$oasso_oauth_server_name}';
    oauthsso_settings['data_handling'] 							= '{$oasso_data_handling}';
    oauthsso_settings['auth_disable'] 							= {$oasso_auth_disable};
    oauthsso_settings['login_disable'] 							= {$oasso_login_disable};
    oauthsso_settings['hook_left_disable'] 					= {$oasso_hook_left_disable};
    oauthsso_settings['hook_right_disable'] 				= {$oasso_hook_right_disable};
    oauthsso_settings['hook_right_product_disable'] = {$oasso_hook_right_product_disable};
    oauthsso_settings['hook_nav_menu_disable'] 			= {$oasso_hook_nav_menu_disable};
    oauthsso_settings['link_account_disable'] 			= {$oasso_link_account_disable};
    oauthsso_settings['email_admin_disable'] 				= {$oasso_email_admin_disable};
    oauthsso_settings['email_customer_disable'] 		= {$oasso_email_customer_disable};

    {$oasso_custom_js nofilter}
	</script>
	{/if}
{/if}

{* Location: LOGIN FORM - Login SSO widget *}
{if {$oasso_widget_location} eq 'login_form'}
	<div class="block oauth_sso_block oauth_sso_customer_login_form" id="oauth_sso_block_customer_login_form">
	{if {$oasso_translated_title_login|strip} neq ' '}
		<p class="title_block">{$oasso_translated_title_login}</p>
	{/if}
		<p class="block_content">
			<div class="oauth_sso_provider" id="oauth_sso_provider">
				<a class="btn btn-primary btn-large btn-full-width oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
        	<i class="fto-user icon_btn"></i> {$oasso_sso_provider nofilter}
        </a>
			</div>
		</p>
	</div>
{/if}

{* Location: CUSTOMER ACCOUNT FORM - Login SSO widget *}
{if {$oasso_widget_location} eq 'customer_account_form'}
	<div class="block oauth_sso_block oauth_sso_customer_account_form" id="oauth_sso_block_customer_account_form">
	{if {$oasso_translated_title_register|strip} neq ' '}
		<p class="title_block">{$oasso_translated_title_register}</p>
	{/if}
		<p class="block_content">
			<div class="oauth_sso_provider" id="oauth_sso_provider">
				<a class="btn btn-primary btn-large btn-full-width oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
        	<i class="fto-user icon_btn"></i> {$oasso_sso_provider nofilter}
        </a>
			</div>
		</p>
	</div>
{/if}

{* Location: LEFT COLUMN HOOK - Login SSO widget *}
{if {$oasso_widget_location} eq 'left_column'}
	<div class="block oauth_sso_block oauth_sso_block_column" id="oauth_sso_block_left_column">
	{if {$oasso_translated_title|strip} neq ' '}
		<p class="title_block h6">{$oasso_translated_title}</p>
	{/if}
		<p class="block_content">
			<div class="oauth_sso_provider" id="oauth_sso_provider">
				<a class="btn btn-primary btn-large btn-full-width oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
        	<i class="fto-user icon_btn"></i> {$oasso_sso_provider nofilter}
        </a>
			</div>
		</p>
	</div>
{/if}

{* Location: RIGHT COLUMN HOOK - Login SSO widget *}
{if {$oasso_widget_location} eq 'right_column'}
	<div class="block oauth_sso_block oauth_sso_block_column" id="oauth_sso_block_right_column">
	{if {$oasso_translated_title|strip} neq ' '}
		<p class="title_block h6">{$oasso_translated_title}</p>
	{/if}
		<p class="block_content">
			<div class="oauth_sso_provider" id="oauth_sso_provider">
				<a class="btn btn-primary btn-large btn-full-width oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
        	<i class="fto-user icon_btn"></i> {$oasso_sso_provider nofilter}
        </a>
			</div>
		</p>
	</div>
{/if}

{* Location: RIGHT COLUMN PRODUCT HOOK - Login SSO widget *}
{if {$oasso_widget_location} eq 'right_column_product'}
	<div class="block oauth_sso_block oauth_sso_block_column" id="oauth_sso_block_right_column_product">
	{if {$oasso_translated_title|strip} neq ' '}
		<p class="title_block h6">{$oasso_translated_title}</p>
	{/if}
		<p class="block_content">
			<div class="oauth_sso_provider" id="oauth_sso_provider">
				<a class="btn btn-primary btn-large btn-full-width oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
        	<i class="fto-user icon_btn"></i> {$oasso_sso_provider nofilter}
        </a>
			</div>
		</p>
	</div>
{/if}

{* Location: CUSTOM HOOK - Login SSO widget *}
{if {$oasso_widget_location} eq 'custom'}
	<div class="block oauth_sso_block oauth_sso_custom" id="oauth_sso_block_custom">
		<div class="oauth_sso_provider" id="oauth_sso_provider">
			<a class="btn btn-primary btn-large btn-full-width oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
				<i class="fto-user icon_btn"></i> {$oasso_sso_provider nofilter}
			</a>
		</div>
	</div>
{/if}

{* Location: NAVIGATION MENU HOOK - Login SSO widget (button only) *}
{if {$oasso_widget_location} eq 'menu'}
	<div class="oauth_sso_block oauth_sso_menu" id="oauth_sso_block_menu">
		<div class="oauth_sso_provider" id="oauth_sso_provider">
			<a class="login top_bar_item oauth_sso_button" href="{$oasso_widget_sso_uri}" id="oauth_sso_button" rel="nofollow">
				<span class="header_item"><i class="fto-user icon_btn"></i> {$oasso_translated_button_txt}</span>
			</a>
		</div>
	</div>
{/if}
