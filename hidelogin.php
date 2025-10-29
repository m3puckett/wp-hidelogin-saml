<?php
/**
 * Plugin Name: Hide WP Login SAML
 * Plugin URI: https://github.com/m3puckett/wp-hidelogin-saml
 * Description: Hides the WordPress login page with a custom URL while preserving SAML authentication functionality
 * Version: 2.1.4
 * Author: Mark Puckett
 * Author URI: https://github.com/m3puckett
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-hidelogin-saml
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * @noinspection PhpUndefinedFunctionInspection
 * @noinspection PhpUndefinedConstantInspection
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SHL_VERSION', '2.1.4');
define('SHL_PLUGIN_FILE', __FILE__);
define('SHL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHL_DEBUG', true); // Set to true for debugging

// Enable automatic updates from GitHub (if vendor directory exists)
$autoload_file = SHL_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload_file)) {
    require_once $autoload_file;

    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/m3puckett/wp-hidelogin-saml/',
            __FILE__,
            'wp-hidelogin-saml'
        );

        // Set the branch to check for updates
        $updateChecker->setBranch('main');

        // Optional: Enable release assets (if you want to use pre-built ZIPs)
        // $updateChecker->getVcsApi()->enableReleaseAssets();
    }
}

/**
 * Debug logging function
 */
function shl_log($message) {
    if (SHL_DEBUG) {
        error_log('[SAML Hide Login] ' . $message);
    }
}

/**
 * Main Plugin Class
 */
class SAML_Hide_Login {

    private $custom_login_slug = 'login'; // Default slug

    public function __construct() {
        // Get custom login slug from options
        $this->custom_login_slug = get_option('shl_login_slug', 'login');

        shl_log('Plugin initialized with slug: ' . $this->custom_login_slug);

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Core functionality
        add_action('plugins_loaded', array($this, 'plugins_loaded'), 1);
        add_action('init', array($this, 'init'), 1);
        add_action('wp_loaded', array($this, 'wp_loaded'));
        add_action('template_redirect', array($this, 'template_redirect'));

        // Admin hooks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

        // Login URL filters - only rewrite when WordPress explicitly asks for login URLs
        add_filter('login_url', array($this, 'login_url'), 10, 3);
        add_filter('logout_url', array($this, 'logout_url'), 10, 2);
        add_filter('lostpassword_url', array($this, 'lostpassword_url'), 10, 2);
    }

    /**
     * Check if current request is a SAML authentication request
     */
    private function is_saml_request() {
        // Check for SAML parameters in the request
        // Note: $_REQUEST includes both $_GET and $_POST
        $is_saml = (
            isset($_REQUEST['saml_acs']) ||
            isset($_REQUEST['saml_sso']) ||
            isset($_REQUEST['saml_sls']) ||
            isset($_REQUEST['SAMLResponse']) ||
            isset($_REQUEST['SAMLRequest'])
        );

        // Also check REQUEST_URI
        if (!$is_saml && isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            $is_saml = (
                strpos($uri, 'saml_acs') !== false ||
                strpos($uri, 'saml_sso') !== false ||
                strpos($uri, 'saml_sls') !== false ||
                strpos($uri, 'SAMLResponse') !== false ||
                strpos($uri, 'SAMLRequest') !== false
            );
        }

        if ($is_saml) {
            shl_log('SAML request detected');
        }

        return $is_saml;
    }

    /**
     * Check if request is trying to access wp-login.php
     */
    private function is_wp_login_request() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $request_uri = $_SERVER['REQUEST_URI'];

        // Remove query string
        $path = parse_url($request_uri, PHP_URL_PATH);
        if ($path === false || $path === null) {
            return false;
        }

        // Check if it's wp-login.php
        return (basename($path) === 'wp-login.php');
    }

    /**
     * Check if request is for custom login page
     */
    private function is_custom_login_request() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $request_uri = $_SERVER['REQUEST_URI'];
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path === false || $home_path === null) {
            $home_path = '';
        }
        $home_path = $home_path ? trim($home_path, '/') : '';

        $request_path = parse_url($request_uri, PHP_URL_PATH);
        if ($request_path === false || $request_path === null) {
            return false;
        }
        $request_path = $request_path ? trim($request_path, '/') : '';

        // Remove home path from request path
        if ($home_path && strpos($request_path, $home_path) === 0) {
            $request_path = substr($request_path, strlen($home_path));
            $request_path = ltrim($request_path, '/');
        }

        return ($request_path === $this->custom_login_slug);
    }

    /**
     * Check if request is for WordPress default /login redirect
     */
    private function is_default_login_request() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $request_uri = $_SERVER['REQUEST_URI'];
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path === false || $home_path === null) {
            $home_path = '';
        }
        $home_path = $home_path ? trim($home_path, '/') : '';

        $request_path = parse_url($request_uri, PHP_URL_PATH);
        if ($request_path === false || $request_path === null) {
            return false;
        }
        $request_path = $request_path ? trim($request_path, '/') : '';

        // Remove home path from request path
        if ($home_path && strpos($request_path, $home_path) === 0) {
            $request_path = substr($request_path, strlen($home_path));
            $request_path = ltrim($request_path, '/');
        }

        // Check if it's exactly 'login' (not our custom slug)
        return ($request_path === 'login' && $this->custom_login_slug !== 'login');
    }


    /**
     * Plugins loaded hook
     */
    public function plugins_loaded() {
        // Prevent caching
        if ($this->is_wp_login_request() || $this->is_custom_login_request()) {
            nocache_headers();
            shl_log('No-cache headers sent for login request');
        }
    }

    /**
     * Init hook - main request handling
     */
    public function init() {
        global $pagenow;

        // Skip for AJAX, cron, and CLI
        if (defined('DOING_AJAX') || defined('DOING_CRON') || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        shl_log('Init hook - Request URI: ' . $request_uri);

        // Block access to WordPress default /login URL to prevent exposing custom slug
        if ($this->is_default_login_request()) {
            shl_log('Default /login access blocked - redirecting to 404');
            $this->wp_template_redirect();
        }

        // CRITICAL: Allow wp-login.php if it's a SAML request
        if ($this->is_wp_login_request() && $this->is_saml_request()) {
            shl_log('SAML request to wp-login.php - ALLOWING access');

            // Set the pagenow to wp-login.php so WordPress processes it correctly
            $pagenow = 'wp-login.php';
            $_SERVER['PHP_SELF'] = '/wp-login.php';
            $GLOBALS['pagenow'] = 'wp-login.php';

            // Do NOT block this request - let WordPress handle it normally
            return;
        }

        // Handle custom login slug request
        if ($this->is_custom_login_request()) {
            shl_log('Custom login slug detected');

            // Check if auto-redirect to SAML is enabled
            $auto_redirect = get_option('shl_auto_redirect_saml', 0);
            shl_log('Auto-redirect option value: ' . var_export($auto_redirect, true) . ' (type: ' . gettype($auto_redirect) . ')');

            // Check if enabled (should be integer 1 for enabled, 0 for disabled)
            $auto_redirect_enabled = ($auto_redirect === 1 || $auto_redirect === '1');
            $is_logged_in = is_user_logged_in();
            $is_saml = $this->is_saml_request();

            shl_log('Redirect conditions - Enabled: ' . ($auto_redirect_enabled ? 'YES' : 'NO') . ', Logged in: ' . ($is_logged_in ? 'YES' : 'NO') . ', SAML request: ' . ($is_saml ? 'YES' : 'NO'));

            // If auto-redirect is enabled and this is not a SAML callback
            if ($auto_redirect_enabled && !$is_saml) {
                // If user is already logged in, redirect them away from login page
                if ($is_logged_in) {
                    shl_log('User already logged in - redirecting to destination');
                    // Get redirect_to parameter or default to admin
                    $redirect_to = isset($_GET['redirect_to']) ? wp_validate_redirect($_GET['redirect_to'], admin_url()) : admin_url();
                    wp_redirect($redirect_to);
                    exit;
                }

                // User is not logged in, redirect to SAML SSO
                shl_log('Auto-redirect to SAML enabled - redirecting to SAML SSO');
                $saml_sso_url = site_url('wp-login.php?saml_sso');

                // Preserve redirect_to parameter if present (with validation to prevent open redirects)
                if (isset($_GET['redirect_to'])) {
                    $redirect_to = wp_validate_redirect($_GET['redirect_to'], admin_url());
                    $saml_sso_url = add_query_arg('redirect_to', urlencode($redirect_to), $saml_sso_url);
                }

                wp_redirect($saml_sso_url);
                exit;
            }

            shl_log('Loading wp-login.php');

            $pagenow = 'wp-login.php';
            $_SERVER['PHP_SELF'] = '/wp-login.php';
            $GLOBALS['pagenow'] = 'wp-login.php';

            // Load wp-login.php
            require_once ABSPATH . 'wp-login.php';
            die();
        }

        // Block direct access to wp-login.php (non-SAML requests)
        if ($this->is_wp_login_request() && !$this->is_saml_request()) {
            shl_log('Direct wp-login.php access blocked - redirecting to 404');
            $this->wp_template_redirect();
        }

        // Block wp-admin access for non-logged-in users
        if (is_admin() && !is_user_logged_in() && !defined('DOING_AJAX') &&
            $pagenow !== 'admin-post.php') {
            shl_log('wp-admin access blocked for non-logged-in user');
            $this->wp_template_redirect();
        }
    }

    /**
     * WP loaded hook
     */
    public function wp_loaded() {
        // Additional checks after WordPress is fully loaded
        if ($this->is_saml_request()) {
            shl_log('SAML request confirmed at wp_loaded');
        }
    }

    /**
     * Template redirect hook
     */
    public function template_redirect() {
        // Final check for blocked requests
        if ($this->is_wp_login_request() && !$this->is_saml_request() && !is_user_logged_in()) {
            shl_log('Template redirect - blocking non-SAML wp-login.php request');
            $this->wp_template_redirect();
        }
    }

    /**
     * Redirect to 404
     */
    private function wp_template_redirect() {
        global $wp_query;

        $wp_query->set_404();
        status_header(404);

        if (file_exists(get_template_directory() . '/404.php')) {
            include get_template_directory() . '/404.php';
        } else {
            echo '<h1>404 Not Found</h1>';
        }

        die();
    }

    /**
     * Filter login_url - rewrite wp-login.php to custom slug
     */
    public function login_url($login_url, $redirect, $force_reauth) {
        // Don't filter SAML requests
        if ($this->is_saml_request()) {
            return $login_url;
        }

        // Only rewrite if it contains wp-login.php
        if (strpos($login_url, 'wp-login.php') !== false) {
            // Check if this is a SAML URL (has saml parameters)
            $query_string = parse_url($login_url, PHP_URL_QUERY);
            if ($query_string && (
                strpos($query_string, 'saml_acs') !== false ||
                strpos($query_string, 'saml_sso') !== false ||
                strpos($query_string, 'saml_sls') !== false
            )) {
                shl_log('Not filtering SAML login URL: ' . $login_url);
                return $login_url;
            }

            // Replace with custom slug
            $login_url = home_url($this->custom_login_slug);
            shl_log('Rewriting login URL to: ' . $login_url);
        }

        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }

        if ($force_reauth) {
            $login_url = add_query_arg('reauth', '1', $login_url);
        }

        return $login_url;
    }

    /**
     * Filter logout_url - rewrite wp-login.php to custom slug
     */
    public function logout_url($logout_url, $redirect) {
        // Don't filter SAML requests
        if ($this->is_saml_request()) {
            return $logout_url;
        }

        // Only rewrite if it contains wp-login.php and is not SAML
        if (strpos($logout_url, 'wp-login.php') !== false) {
            $parsed = parse_url($logout_url);
            $logout_url = home_url($this->custom_login_slug);

            // Preserve query parameters
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $query_params);
                $logout_url = add_query_arg($query_params, $logout_url);
            }
        }

        return $logout_url;
    }

    /**
     * Filter lostpassword_url - rewrite wp-login.php to custom slug
     */
    public function lostpassword_url($lostpassword_url, $redirect) {
        // Don't filter SAML requests
        if ($this->is_saml_request()) {
            return $lostpassword_url;
        }

        // Only rewrite if it contains wp-login.php
        if (strpos($lostpassword_url, 'wp-login.php') !== false) {
            $parsed = parse_url($lostpassword_url);
            $lostpassword_url = home_url($this->custom_login_slug);

            // Preserve query parameters
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $query_params);
                $lostpassword_url = add_query_arg($query_params, $lostpassword_url);
            }
        }

        return $lostpassword_url;
    }

    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_options_page(
            'Hide Login',
            'Hide Login',
            'manage_options',
            'saml-hide-login',
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('shl_settings', 'shl_login_slug', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_slug'),
            'default' => 'login'
        ));

        register_setting('shl_settings', 'shl_auto_redirect_saml', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => 0
        ));

        add_settings_section(
            'shl_main_section',
            'Login Page Settings',
            array($this, 'settings_section_callback'),
            'saml-hide-login'
        );

        add_settings_field(
            'shl_login_slug',
            'Custom Login Slug',
            array($this, 'login_slug_field_callback'),
            'saml-hide-login',
            'shl_main_section'
        );

        add_settings_field(
            'shl_auto_redirect_saml',
            'Auto-Redirect to SAML',
            array($this, 'auto_redirect_field_callback'),
            'saml-hide-login',
            'shl_main_section'
        );
    }

    /**
     * Sanitize slug
     */
    public function sanitize_slug($slug) {
        $slug = sanitize_title($slug);

        // Prevent using reserved slugs
        $reserved = array('wp-admin', 'wp-includes', 'wp-content', 'admin', 'login', 'wp-login', 'dashboard');

        if (empty($slug) || in_array($slug, $reserved)) {
            add_settings_error('shl_login_slug', 'invalid_slug', 'Invalid slug. Please choose a different one.');
            return get_option('shl_login_slug', 'login');
        }

        return $slug;
    }

    /**
     * Sanitize checkbox value
     */
    public function sanitize_checkbox($value) {
        // Checkboxes send '1' when checked, nothing when unchecked
        // Convert to proper boolean: 1 (integer) for true, 0 (integer) for false
        $result = ($value === '1' || $value === 1 || $value === true) ? 1 : 0;
        shl_log('Sanitizing checkbox value: ' . var_export($value, true) . ' -> ' . var_export($result, true));
        return $result;
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Configure your custom login URL and SAML auto-redirect settings. The default wp-login.php will be hidden, but SAML authentication URLs will continue to work.</p>';
        echo '<p><strong>Note:</strong> wp-login.php?saml_acs, wp-login.php?saml_sso, and wp-login.php?saml_sls will always work for SAML authentication.</p>';
    }

    /**
     * Login slug field callback
     */
    public function login_slug_field_callback() {
        $slug = get_option('shl_login_slug', 'login');
        $login_url = home_url($slug);

        echo '<input type="text" id="shl_login_slug" name="shl_login_slug" value="' . esc_attr($slug) . '" class="regular-text" />';
        echo '<p class="description">Your login URL will be: <strong>' . esc_html($login_url) . '</strong></p>';
        echo '<p class="description">SAML authentication will continue to work at: <strong>' . esc_html(site_url('wp-login.php?saml_acs')) . '</strong></p>';
    }

    /**
     * Auto-redirect field callback
     */
    public function auto_redirect_field_callback() {
        $auto_redirect = get_option('shl_auto_redirect_saml', 0);
        $saml_sso_url = site_url('wp-login.php?saml_sso');

        echo '<label for="shl_auto_redirect_saml">';
        echo '<input type="checkbox" id="shl_auto_redirect_saml" name="shl_auto_redirect_saml" value="1" ' . checked(1, $auto_redirect, false) . ' />';
        echo ' Enable automatic redirect to SAML authentication';
        echo '</label>';
        echo '<p class="description">When enabled, visitors accessing the login page will be automatically redirected to SAML SSO authentication.</p>';
        echo '<p class="description">Redirect URL: <strong>' . esc_html($saml_sso_url) . '</strong></p>';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('shl_settings');
                do_settings_sections('saml-hide-login');
                submit_button('Save Settings');
                ?>
            </form>

            <hr>

            <h2>Debug Information</h2>
            <table class="widefat">
                <tr>
                    <th>Current Login URL:</th>
                    <td><strong><?php echo esc_html(home_url(get_option('shl_login_slug', 'login'))); ?></strong></td>
                </tr>
                <tr>
                    <th>Auto-Redirect to SAML:</th>
                    <td><?php echo get_option('shl_auto_redirect_saml', 0) ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></td>
                </tr>
                <tr>
                    <th>SAML ACS URL:</th>
                    <td><strong><?php echo esc_html(site_url('wp-login.php?saml_acs')); ?></strong></td>
                </tr>
                <tr>
                    <th>SAML SSO URL:</th>
                    <td><strong><?php echo esc_html(site_url('wp-login.php?saml_sso')); ?></strong></td>
                </tr>
                <tr>
                    <th>SAML SLS URL:</th>
                    <td><strong><?php echo esc_html(site_url('wp-login.php?saml_sls')); ?></strong></td>
                </tr>
                <tr>
                    <th>Debug Mode:</th>
                    <td><?php echo SHL_DEBUG ? '<span style="color: green;">Enabled</span>' : '<span style="color: red;">Disabled</span>'; ?></td>
                </tr>
            </table>

            <?php if (SHL_DEBUG): ?>
            <p class="description">Debug mode is enabled. Check your error logs for detailed information about requests,
                but be sure your WP_DEBUG config settings are enabled in wp-config.php as well.</p>
            <?php endif; ?>

            <hr>

            <h2>About</h2>
            <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-top: 15px;">
                <p style="margin: 0 0 10px 0;">
                    <strong>Plugin Version:</strong> <?php echo esc_html(SHL_VERSION); ?>
                </p>
                <p style="margin: 0 0 10px 0;">
                    <strong>Author:</strong> Mark Puckett - <a href="https://raxis.com/">Raxis</a>
                </p>
                <p style="margin: 0;">
                    <strong>GitHub:</strong>
                    <a href="https://github.com/m3puckett/wp-hidelogin-saml" target="_blank" rel="noopener noreferrer">
                        https://github.com/m3puckett/wp-hidelogin-saml
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=saml-hide-login')) . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
function saml_hide_login_init() {
    global $saml_hide_login;
    $saml_hide_login = new SAML_Hide_Login();
    shl_log('Plugin instance created');
}
add_action('plugins_loaded', 'saml_hide_login_init', 1);

// Activation hook
register_activation_hook(__FILE__, function() {
    shl_log('Plugin activated');

    // Set default options
    if (!get_option('shl_login_slug')) {
        update_option('shl_login_slug', 'login');
    }

    // Set default auto-redirect option if not set
    if (get_option('shl_auto_redirect_saml') === false) {
        update_option('shl_auto_redirect_saml', 0);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    shl_log('Plugin deactivated');

    // Flush rewrite rules
    flush_rewrite_rules();
});