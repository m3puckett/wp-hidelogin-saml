<?php
/**
 * Plugin Name: WP SAML Auth - Hide Login Fix
 * Description: Allows SAML authentication to work with WPS Hide Login
 * Version: 1.0.0
 * Author: Mark Puckett
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('WP_SAML_FIX_VERSION', '1.0.0');
define('WP_SAML_FIX_NAME', 'WP SAML Auth - Hide Login Fix');
define('WP_SAML_FIX_DESCRIPTION', 'Allows SAML authentication to work with WPS Hide Login');
define('WP_SAML_FIX_AUTHOR', 'Mark Puckett');
define('WP_SAML_FIX_AUTHOR_URI', 'https://github.com/m3puckett');
define('WP_SAML_FIX_PLUGIN_URI', 'https://raxis.com/wp-saml-hidelogin-fix');
define('WP_SAML_FIX_FILE', __FILE__);
define('WP_SAML_FIX_PATH', plugin_dir_path(__FILE__));

// Allow SAML ACS to bypass WPS Hide Login
add_action('init', function() {
    if (isset($_REQUEST['saml_acs']) || isset($_REQUEST['saml_sso']) || isset($_REQUEST['saml_sls'])) {
        // Remove WP Hide Login's redirect when SAML is active
        remove_action('template_redirect', 'whl_template_redirect');
        remove_action('init', 'whl_load_textdomain');

        // Allow direct wp-login.php access for SAML
        add_filter('whl_block_access', '__return_false');
    }
}, 0);

// Whitelist SAML URLs in WP Hide Login
add_filter('whl_whitelist_uri', function($whitelist) {
    $whitelist[] = 'wp-login.php?saml_acs';
    $whitelist[] = 'wp-login.php?saml_sso';
    $whitelist[] = 'wp-login.php?saml_sls';
    return $whitelist;
});