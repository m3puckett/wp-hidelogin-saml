# Hide WP Login SAML-Aware

A WordPress plugin that hides the default WordPress login page with a custom URL while preserving SAML authentication functionality.

## Description

I had an issue where an open source version of the hide login plugin and the WP SAML Auth plugin did not get along.  So I wrote another hide login plugin that actually works.  The hide login code works differently than the original, and it still preserves the same idea of obscuring the login page.  This keeps unwanted bots from attempting brute force logins since they can't find the login page.

It works pretty simple.  If you check the Auto-Redirect to SAML option, it will redirect users to the SAML SSO page. On that page hosted by the WP SAML Auth plugin, you can configure it to have a button to press to login - or you can configure it to redirect to the SSO login automatically.  

If you don't check the Auto-Redirect to SAML option, it will default to the basic Wordpress authentication page.  It works
fine if you don't have SAML authentication at all.

If you deactivate the plugin, it reverts to the default settings with no lasting impact.

### Key Features

- **Custom Login URL**: Replace the default WordPress login URL with your own custom slug
- **SAML Compatibility**: Automatically allows SAML authentication requests to pass through
- **Auto-Redirect to SAML**: Optional automatic redirection to SAML SSO when accessing the login page
- **Security Enhancement**: Blocks direct access to `wp-login.php` for non-SAML requests
- **Debug Mode**: Built-in debugging capabilities for troubleshooting
- **Easy Configuration**: Simple settings page in WordPress admin

### How It Works

The plugin intercepts requests to the WordPress login page and:

1. **For SAML Requests**: Automatically detects and allows SAML authentication parameters (`saml_acs`, `saml_sso`, `saml_sls`, `SAMLResponse`, `SAMLRequest`) to access `wp-login.php`
2. **For Custom Login Slug**: Redirects users to your custom login URL for standard WordPress authentication
3. **For Direct Access**: Blocks unauthorized direct access to `wp-login.php` with a 404 error
4. **For Auto-Redirect**: Optionally redirects users from the custom login page directly to SAML SSO

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- SAML authentication plugin (optional, but recommended for full functionality)

## Installation

### Method 1: WordPress Admin Panel (Recommended)

1. Download the latest release as a ZIP file
2. Log in to your WordPress admin panel
3. Navigate to **Plugins > Add New**
4. Click **Upload Plugin** at the top of the page
5. Click **Choose File** and select the downloaded ZIP file
6. Click **Install Now**
7. Once installed, click **Activate Plugin**

### Method 2: Manual Installation via FTP

1. Download the latest release as a ZIP file
2. Extract the ZIP file on your computer
3. Upload the `wp-hidelogin-saml` folder to `/wp-content/plugins/` directory via FTP
4. Log in to your WordPress admin panel
5. Navigate to **Plugins > Installed Plugins**
6. Find "Hide WP Login SAML-Aware" and click **Activate**

### Method 3: Manual Installation via SSH

```bash
cd /path/to/wordpress/wp-content/plugins/
wget https://github.com/m3puckett/wp-hidelogin-saml/archive/refs/heads/main.zip
unzip main.zip
mv wp-saml-hidelogin-fix-main wp-hidelogin-saml
```

Then activate the plugin from the WordPress admin panel.

## Configuration

### Basic Setup

1. After activation, go to **Settings > SAML Hide Login** in your WordPress admin panel
2. In the **Custom Login Slug** field, enter your desired login URL slug (e.g., `mylogin`, `secure-access`, etc.)
3. Click **Save Settings**

Your new login URL will be: `https://yoursite.com/your-custom-slug`

### Reserved Slugs

The following slugs cannot be used as they are reserved by WordPress:
- `wp-admin`
- `wp-includes`
- `wp-content`
- `admin`
- `login`
- `wp-login`
- `dashboard`

### Auto-Redirect to SAML (Optional)

If you want users to automatically be redirected to SAML SSO when they visit the login page:

1. Go to **Settings > SAML Hide Login**
2. Check the **Enable automatic redirect to SAML authentication** option
3. Click **Save Settings**

When enabled, visitors accessing the custom login page will be automatically redirected to `wp-login.php?saml_sso`

### Debug Mode

The plugin includes debug logging that can be enabled by editing `hidelogin.php`:

```php
define('SHL_DEBUG', true); // Set to false in production
```

When enabled, debug messages will be written to your PHP error log (requires `WP_DEBUG_LOG` to be enabled in `wp-config.php`).

## SAML URLs

The following SAML authentication URLs will always work, regardless of your custom login slug:

- **SAML ACS (Assertion Consumer Service)**: `https://yoursite.com/wp-login.php?saml_acs`
- **SAML SSO (Single Sign-On)**: `https://yoursite.com/wp-login.php?saml_sso`
- **SAML SLS (Single Logout Service)**: `https://yoursite.com/wp-login.php?saml_sls`

Configure these URLs in your SAML Identity Provider settings.

## Automatic Updates

This plugin supports automatic updates from GitHub! Once installed, WordPress will automatically check for new versions and notify you when updates are available.

### How It Works

The plugin uses the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library to check for updates from the GitHub repository. When you create a new release on GitHub:

1. Tag the release with a version number (e.g., `v2.2.0`)
2. WordPress will detect the new version within 12 hours
3. Users will see an update notification in their WordPress admin
4. Users can update with one click from the Plugins page

### For Plugin Developers

To release a new version:

1. Update the version number in `hidelogin.php` (line 6)
2. Update version in `readme.txt` (Stable tag)
3. Update the CHANGELOG in both README files
4. Commit and push changes
5. Create a new release on GitHub:
   ```bash
   git tag v2.2.0
   git push origin v2.2.0
   ```
6. Create a GitHub release with the tag and upload the ZIP file

WordPress will automatically detect the new release and offer updates to users.

## Usage Examples

### Example 1: Basic Custom Login

1. Set custom login slug to: `secure-login`
2. Your new login URL: `https://yoursite.com/secure-login`
3. SAML URLs remain: `https://yoursite.com/wp-login.php?saml_acs` (and other SAML endpoints)

### Example 2: Auto-Redirect to SAML

1. Set custom login slug to: `staff-login`
2. Enable auto-redirect to SAML
3. Users visit: `https://yoursite.com/staff-login`
4. Plugin automatically redirects to: `https://yoursite.com/wp-login.php?saml_sso`
5. Users authenticate via SAML SSO

## Frequently Asked Questions

### Will this break my SAML authentication?

No. The plugin is specifically designed to preserve SAML functionality. All SAML-related requests are automatically detected and allowed to access `wp-login.php`.

### What happens to my existing login URL?

Direct access to `wp-login.php` (without SAML parameters) will be blocked and return a 404 error. Only your custom login slug and SAML authentication URLs will work.

### Can I still use WordPress authentication?

Yes. Access your custom login slug (e.g., `https://yoursite.com/your-custom-slug`) to use standard WordPress authentication, unless you've enabled auto-redirect to SAML.

### What if I forget my custom login slug?

You can disable the plugin via FTP by renaming the plugin folder in `/wp-content/plugins/`, or you can check your database in the `wp_options` table for the `shl_login_slug` option.

### Does this plugin work with other security plugins?

Yes, it should be compatible with most WordPress security plugins. However, test compatibility in a staging environment first.

## Troubleshooting

### I can't access my login page

1. Check that you're using the correct custom login slug
2. Verify the slug in **Settings > SAML Hide Login**
3. If locked out, disable the plugin via FTP by renaming the plugin folder

### SAML authentication isn't working

1. Verify your SAML plugin is active
2. Check that you're using the correct SAML URLs in your IdP configuration
3. Enable debug mode and check error logs
4. Verify `wp-login.php?saml_acs` (and other SAML endpoints) are accessible

### How do I enable debug logging?

1. Edit `hidelogin.php` and set `define('SHL_DEBUG', true);`
2. In `wp-config.php`, ensure these are set:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Check `/wp-content/debug.log` for log entries

## Packaging for Distribution

To create a distributable ZIP file:

```bash
# From the plugin directory
cd /path/to/wp-saml-hidelogin-fix

# Create a clean directory with the correct plugin name
mkdir -p ../wp-hidelogin-saml
cp hidelogin.php ../wp-hidelogin-saml/
cp LICENSE ../wp-hidelogin-saml/
cp README.md ../wp-hidelogin-saml/
cp readme.txt ../wp-hidelogin-saml/

# Create the ZIP file
cd ..
zip -r wp-hidelogin-saml.zip wp-hidelogin-saml/

# Clean up
rm -rf wp-hidelogin-saml/
```

The resulting `wp-hidelogin-saml.zip` file can be uploaded via the WordPress admin panel.

## Changelog

### Version 2.1.0
- Fixed SAML authentication compatibility
- Added auto-redirect to SAML option
- Improved debug logging
- Enhanced security for login page hiding
- Updated to GPL v3 license

### Version 2.0.0
- Complete rewrite for better SAML compatibility
- Added settings page
- Improved request detection

### Version 1.0.0
- Initial release

## Security Considerations

- This plugin enhances security through obscurity by hiding the default login URL
- Always use strong passwords and two-factor authentication
- Regularly update WordPress core, themes, and plugins
- Monitor access logs for suspicious activity
- Use HTTPS for all login pages

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin feature/my-new-feature`
5. Submit a pull request

## Support

For bugs, feature requests, or questions:

- GitHub Issues: https://github.com/m3puckett/wp-hidelogin-saml/issues
- GitHub Repository: https://github.com/m3puckett/wp-hidelogin-saml

## License

This plugin is licensed under the GNU General Public License v3.0 or later.

Copyright (C) 2024 Mark Puckett

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/.

## Credits

Developed by Mark Puckett (https://github.com/m3puckett)
