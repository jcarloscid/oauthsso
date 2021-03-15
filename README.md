# PrestaShop OAuth 2.0 Single SignOn Client
Provides a mechanism to identify users from an OAuth 2.0 Identity Server, allowing the Single SignOn of users in your entire organization.

**Requires PrestaShop 1.7+**

[More info](https://fishandbits.es/modulo-prestashop-cliente-oauth-single-signon/ "Carlos' personal blog - Spanish")

## Features

* Implements OAuth 2.0 client protocol.
* Supports connections using _CURL_ or _FSOCKOPEN_.
* Supports _HTTP_ as well as _HTTPS_.
* Can be configured on the following PrestaShop hooks:
	* Authentication page
	* Registration Page
	* Navigation Menu
	* Left column
	* Right column
	* Right column product
* Can be added to any _Smarty_ template files by means of a custom hook (`{$HOOK_OASSO_CUSTOM nofilter}`).
* Supports shop front customization by adding custom CSS and custom JavaScript.
* Supports **role mapping** (user's roles provided by the Identity Server can be mapped to PrestaShop customer groups, which allows to implement these roles on your shop).
* Allows to send notifications to administrators and/or customers when a new customer is registered via SSO.
* Supports **account linking** (users coming from the Identity Server can be linked to existing customers on your shop by means of their email address).
* Supports registration data validation (confirm new customers data).

## Install and configure

Install as any other module on PrestaShop using the file `oauthsso.zip`.

Click on **Configure** after installing the module. Read the explanations on the admin panel to understand how to configure the module.

At the minimum you need to configure:
* `OAuth Server Name`: Fully qualified domain name (FQDN) of the Identity Server (eg. sso.mydomain.com)
* `Client ID`: This is provided by the Identity Server as part of this client configuration.
* `Client Secret`: This is also provided by the Identity Server as part of this client configuration.

When configuring the client access on the Identity Server, do not forget to set the **Redirect URI** to the one provided by the module on the admin panel.

## Recommended styling

The styling depends highly on your theme, but if things are not displayed correctly, please add this **Custom CSS**:

```
.block.oauth_sso_block_column {
    box-shadow: 2px 2px 11px 0 rgba(0,0,0,.1);
    background: #fff;
    padding: 1.5625rem 1.25rem;
    margin-bottom: 1.5625rem;
}

.oauthsso-wrapper {
    max-width: 600px;
    margin: 1em auto;
    padding: 1em;
    box-shadow: 2px 2px 11px 0 rgba(0,0,0,.1);
}
```
Hints to customize the aspect of the OAuth 2.0 Single SignOn widget:

* All OAuth 2.0 single SignOn widgets has the class `oauth_sso_block`
* Login form widget (class and ID) `oauth_sso_customer_login_form`
* Customer account form widget (class and ID) `oauth_sso_customer_account_form`
* Custom widget (class and ID) `oauth_sso_custom`
* Left column widget: class `oauth_sso_block_column` ID: `oauth_sso_block_left_column`
* Right column widget: class `oauth_sso_block_column` ID: `oauth_sso_block_right_column`
* Right column product widget: class `oauth_sso_block_column` ID: `oauth_sso_block_right_column_product`
* Single SignOn button wrapper (class and ID) `oauth_sso_provider`
* Single SignOn button (class and ID) `oauth_sso_button`

## Translations

**OAuth 2.0 Single SignOn Client** is provided with translations for the following languages:

* Spanish from Spain (`es_ES`)

## Emails' subjects translations

As of _PrestaShop 1.7.7.1_ custom emails' subjects cannot be translated by means of the admin tools. However, translation can be performed by directly modifying some source files inside your theme's folder structure.

If you are familiar with code customizations, please see instructions and sample code on `/translations/es-lang.php`.

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,USA.

The "GNU General Public License" (GPL) is available at http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
