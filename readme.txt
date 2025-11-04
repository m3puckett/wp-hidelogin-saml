=== Hide WP Login SAML-Aware ===
Contributors: m3puckett
Tags: login, security, saml, authentication, hide login
Requires at least: 5.0
Tested up to: 6.8.3
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Hides the WordPress login page with a custom URL. Works with standard WordPress authentication and is also compatible with SAML authentication.

== Description ==

Hide WP Login SAML-Aware provides enhanced security for your WordPress site by concealing the standard login URL (wp-login.php) and replacing it with a custom URL of your choice.

**SAML is completely optional.** This plugin works perfectly with standard WordPress authentication - you don't need any SAML setup to use it. The "SAML-Aware" name simply means that if you do use SAML authentication, this plugin won't break it (unlike other hide login plugins that can conflict with SAML systems).

= Key Features =

* **Custom Login URL**: Replace the default WordPress login URL with your own custom slug
* **Works Without SAML**: Perfect for standard WordPress sites - no SAML required
* **SAML Compatible**: If you do use SAML, it automatically preserves SAML authentication
* **Auto-Redirect to SAML**: Optional automatic redirection to SAML SSO when accessing the login page
* **Security Enhancement**: Blocks direct access to wp-login.php for unauthorized requests
* **Debug Mode**: Built-in debugging capabilities for troubleshooting
* **Easy Configuration**: Simple settings page in WordPress admin

= How It Works =

The plugin intercepts requests to the WordPress login page and:

1. **For SAML Requests**: Automatically detects and allows SAML authentication parameters (saml_acs, saml_sso, saml_sls, SAMLResponse, SAMLRequest) to access wp-login.php
2. **For Custom Login Slug**: Redirects users to your custom login URL for standard WordPress authentication
3. **For Direct Access**: Blocks unauthorized direct access to wp-login.php with a 404 error
4. **For Auto-Redirect**: Optionally redirects users from the custom login page directly to SAML SSO

= SAML URLs =

The following SAML authentication URLs will always work, regardless of your custom login slug:

* **SAML ACS (Assertion Consumer Service)**: https://yoursite.com/wp-login.php?saml_acs
* **SAML SSO (Single Sign-On)**: https://yoursite.com/wp-login.php?saml_sso
* **SAML SLS (Single Logout Service)**: https://yoursite.com/wp-login.php?saml_sls

Configure these URLs in your SAML Identity Provider settings.

== Installation ==

= Via WordPress Admin Panel (Recommended) =

1. Download the latest release as a ZIP file
2. Log in to your WordPress admin panel
3. Navigate to **Plugins > Add New**
4. Click **Upload Plugin** at the top of the page
5. Click **Choose File** and select the downloaded ZIP file
6. Click **Install Now**
7. Once installed, click **Activate Plugin**

= Via FTP =

1. Download the latest release as a ZIP file
2. Extract the ZIP file on your computer
3. Upload the `wp-hidelogin-saml` folder to `/wp-content/plugins/` directory via FTP
4. Log in to your WordPress admin panel
5. Navigate to **Plugins > Installed Plugins**
6. Find "Hide WP Login SAML-Aware" and click **Activate**

= Configuration =

1. After activation, go to **Settings > SAML Hide Login** in your WordPress admin panel
2. In the **Custom Login Slug** field, enter your desired login URL slug (e.g., mylogin, secure-access, etc.)
3. Click **Save Settings**

Your new login URL will be: https://yoursite.com/your-custom-slug

== Frequently Asked Questions ==

= Will this break my SAML authentication? =

No. The plugin is specifically designed to preserve SAML functionality. All SAML-related requests are automatically detected and allowed to access wp-login.php.

= What happens to my existing login URL? =

Direct access to wp-login.php (without SAML parameters) will be blocked and return a 404 error. Only your custom login slug and SAML authentication URLs will work.

= Can I still use WordPress authentication? =

Yes. Access your custom login slug (e.g., https://yoursite.com/your-custom-slug) to use standard WordPress authentication, unless you've enabled auto-redirect to SAML.

= What if I forget my custom login slug? =

You can disable the plugin via FTP by renaming the plugin folder in /wp-content/plugins/, or you can check your database in the wp_options table for the shl_login_slug option.

= Does this plugin work with other security plugins? =

Yes, it should be compatible with most WordPress security plugins. However, test compatibility in a staging environment first.

= What slugs can I use? =

You can use any slug except these reserved ones: wp-admin, wp-includes, wp-content, admin, login, wp-login, dashboard

= How do I enable auto-redirect to SAML? =

Go to **Settings > SAML Hide Login**, check the **Enable automatic redirect to SAML authentication** option, and click **Save Settings**. Visitors will be automatically redirected to SAML SSO when accessing the login page.

= How do I enable debug logging? =

1. Edit hidelogin.php and set `define('SHL_DEBUG', true);`
2. In wp-config.php, ensure WP_DEBUG and WP_DEBUG_LOG are set to true
3. Check /wp-content/debug.log for log entries

== Screenshots ==

1. Settings page showing custom login slug configuration
2. Auto-redirect to SAML option
3. Debug information panel

== Changelog ==

= 3.0.0 =
* BREAKING: Major architectural simplification for better maintainability and performance
* Plugin now only intercepts basic login URL; WordPress handles logout/password resets natively
* Logout URLs now use wp-login.php?action=logout (no longer rewritten to custom slug)
* Password reset URLs now use wp-login.php?action=lostpassword (no longer rewritten)
* Brute force protection unchanged: wp-login.php still blocked and returns 404 to bots
* 60% code reduction - simpler, more maintainable codebase
* Removed complex allowed_actions logic - no longer needed
* Better WordPress compatibility - let WordPress handle its own actions
* Better SAML compatibility - fewer special cases and edge cases
* Fixes logout redirect bug permanently
* Performance improvements from simplified logic
* Note: Functionally equivalent for end users - logout and password reset still work perfectly

= 2.1.6 =
* Major performance optimization: Smart plugin loading based on request type
* Plugin class only instantiates on actual login pages (wp-login.php, custom slug, SAML requests)
* Non-login pages now use lightweight standalone URL filters (no class instantiation)
* Added object caching for login slug to eliminate repeated database queries
* Logging only occurs on login-related requests (eliminates spam in error logs)
* Reduces overhead by ~85-90% on regular pages (blog posts, feeds, etc.)
* Significantly improves performance on high-traffic sites

= 2.1.5 =
* Performance improvement: Plugin now only runs on login-related URLs instead of every page request
* Added early return checks to prevent unnecessary processing on non-login pages
* Significantly reduces server load on high-traffic sites

= 2.1.3 =
* Fixed auto-redirect to SAML functionality
* Improved handling of logged-in users visiting the login page
* When auto-redirect is enabled, logged-in users are now redirected to wp-admin instead of seeing the login form

= 2.1.0 =
* Fixed SAML authentication compatibility
* Added auto-redirect to SAML option
* Improved debug logging
* Enhanced security for login page hiding
* Updated to GPL v3 license

= 2.0.0 =
* Complete rewrite for better SAML compatibility
* Added settings page
* Improved request detection

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 3.0.0 =
Major architectural simplification. Logout and password reset URLs now use native WordPress URLs (wp-login.php with action parameters) instead of custom slug. Brute force protection unchanged - wp-login.php still blocked for basic login. All features work the same, just simplified internally.

= 2.1.0 =
This version adds auto-redirect to SAML functionality and improves SAML compatibility. Update recommended for all users.

= 2.0.0 =
Major update with improved SAML compatibility and settings interface. Backup your site before updating.

== Security Considerations ==

* This plugin enhances security through obscurity by hiding the default login URL
* Always use strong passwords and two-factor authentication
* Regularly update WordPress core, themes, and plugins
* Monitor access logs for suspicious activity
* Use HTTPS for all login pages

== Support ==

For bugs, feature requests, or questions, please visit:
https://github.com/m3puckett/wp-hidelogin-saml/issues

== License ==

This plugin is licensed under the GNU General Public License v3.0 or later.

Copyright (C) 2024 Mark Puckett

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/.
