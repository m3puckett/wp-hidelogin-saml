<?php
/**
 * Plugin Name: Hide WP Login SAML
 * Plugin URI: https://github.com/m3puckett/wp-hidelogin-saml
 * Description: Hides the WordPress login page with a custom URL while preserving SAML authentication functionality
 * Version: 3.0.3
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
define('SHL_VERSION', '3.0.3');
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
 * Get cached login slug (avoids repeated DB queries)
 */
function shl_get_login_slug() {
    static $slug = null;

    if ($slug === null) {
        // Try object cache first (if available)
        $slug = wp_cache_get('shl_login_slug', 'saml_hide_login');

        if ($slug === false) {
            // Cache miss - query database
            $slug = get_option('shl_login_slug', 'login');

            // Store in object cache for 1 hour
            wp_cache_set('shl_login_slug', $slug, 'saml_hide_login', 3600);
        }
    }

    return $slug;
}

/**
 * Early detection of login-related requests (runs before class instantiation)
 */
function shl_is_login_related_request() {
    // Skip for AJAX, cron, and CLI
    if (defined('DOING_AJAX') || defined('DOING_CRON') || (defined('WP_CLI') && WP_CLI)) {
        return false;
    }

    if (!isset($_SERVER['REQUEST_URI'])) {
        return false;
    }

    $request_uri = $_SERVER['REQUEST_URI'];
    $request_path = parse_url($request_uri, PHP_URL_PATH);

    // Check for wp-login.php
    if ($request_path && basename($request_path) === 'wp-login.php') {
        return true;
    }

    // Check for custom login slug or default /login
    $custom_slug = shl_get_login_slug();
    $home_path = parse_url(home_url(), PHP_URL_PATH);
    $home_path = $home_path ? trim($home_path, '/') : '';
    $request_path = $request_path ? trim($request_path, '/') : '';

    // Remove home path from request path
    if ($home_path && strpos($request_path, $home_path) === 0) {
        $request_path = substr($request_path, strlen($home_path));
        $request_path = ltrim($request_path, '/');
    }

    // Check if it matches custom slug or default 'login'
    if ($request_path === $custom_slug || $request_path === 'login') {
        return true;
    }

    // Check for SAML parameters
    if (isset($_REQUEST['saml_acs']) || isset($_REQUEST['saml_sso']) ||
        isset($_REQUEST['saml_sls']) || isset($_REQUEST['SAMLResponse']) ||
        isset($_REQUEST['SAMLRequest'])) {
        return true;
    }

    // DO NOT check for admin area access - let WordPress handle it normally
    // We only want to intercept direct login page requests, not general wp-admin access

    return false;
}

/**
 * Debug logging function (only logs on login-related requests)
 */
function shl_log($message) {
    if (SHL_DEBUG && shl_is_login_related_request()) {
        error_log('[SAML Hide Login] ' . $message);
    }
}

/**
 * Standalone URL filter functions (lightweight, no class instantiation needed)
 * These are used on non-login pages to minimize overhead
 */

/**
 * Filter login_url - rewrite ONLY basic login to custom slug
 * (v3.0: Simplified to only handle basic login, not logout/password reset)
 */
function shl_filter_login_url($login_url, $redirect, $force_reauth) {
    // DON'T rewrite if we're currently processing a SAML request
    // This prevents redirect loops during SAML authentication flow
    if (isset($_REQUEST['saml_acs']) || isset($_REQUEST['saml_sso']) ||
        isset($_REQUEST['saml_sls']) || isset($_REQUEST['SAMLResponse']) ||
        isset($_REQUEST['SAMLRequest'])) {
        return $login_url;
    }

    // Only rewrite if it's wp-login.php
    if (strpos($login_url, 'wp-login.php') === false) {
        return $login_url;
    }

    // Parse query string to check for action parameter
    $query_string = parse_url($login_url, PHP_URL_QUERY);
    parse_str($query_string ? $query_string : '', $params);

    // Don't rewrite SAML URLs
    if (isset($params['saml_acs']) || isset($params['saml_sso']) || isset($params['saml_sls'])) {
        return $login_url;
    }

    // Don't rewrite if action parameter exists (logout, lostpassword, etc.)
    // Only rewrite basic login (no action or action=login)
    if (isset($params['action']) && $params['action'] !== 'login') {
        return $login_url;
    }

    // Rewrite to custom slug for basic login only
    $login_url = home_url(shl_get_login_slug());

    if (!empty($redirect)) {
        $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
    }

    if ($force_reauth) {
        $login_url = add_query_arg('reauth', '1', $login_url);
    }

    return $login_url;
}

/**
 * Main Plugin Class
 */
class SAML_Hide_Login {

    private $custom_login_slug = 'login'; // Default slug

    public function __construct() {
        // Get custom login slug from cached function (avoids DB query)
        $this->custom_login_slug = shl_get_login_slug();

        shl_log('Full plugin initialized with slug: ' . $this->custom_login_slug);

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

        // Login URL filter - v3.0: Only rewrite basic login, not logout/password reset
        add_filter('login_url', array($this, 'login_url'), 10, 3);
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
        // PERFORMANCE: Early return if not login-related
        if (!$this->is_login_related_request()) {
            return;
        }

        // Prevent caching
        if ($this->is_wp_login_request() || $this->is_custom_login_request()) {
            nocache_headers();
            shl_log('No-cache headers sent for login request');
        }
    }

    /**
     * Check if the current request is login-related
     */
    private function is_login_related_request() {
        // Check if it's wp-login.php, custom login slug, or default /login
        // DO NOT intercept general wp-admin access - let WordPress handle it normally
        return (
            $this->is_wp_login_request() ||
            $this->is_custom_login_request() ||
            $this->is_default_login_request()
        );
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

        // PERFORMANCE: Early return if this is not a login-related request
        // This prevents the plugin from doing unnecessary work on every page load
        if (!$this->is_login_related_request()) {
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
            // v3.0: Simplified - custom slug only handles basic login now (no action parameters)
            // Logout/password reset go directly to wp-login.php with action params
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

        // Block direct access to wp-login.php ONLY for basic login (v3.0)
        // Allow wp-login.php with action parameters (logout, lostpassword, etc.)
        if ($this->is_wp_login_request() && !$this->is_saml_request()) {
            $action = isset($_GET['action']) ? $_GET['action'] : '';

            // Block ONLY basic login (no action or action=login)
            // Allow through: logout, lostpassword, retrievepassword, rp, resetpass, postpass
            if (empty($action) || $action === 'login') {
                shl_log('Direct wp-login.php access blocked (basic login) - redirecting to 404');
                $this->wp_template_redirect();
            } else {
                shl_log('Allowing wp-login.php with action=' . $action);
            }
        }

        // DO NOT block wp-admin access - let WordPress handle authentication redirects normally
        // This allows other plugins' admin pages to work correctly
    }

    /**
     * WP loaded hook
     */
    public function wp_loaded() {
        // PERFORMANCE: Early return if not login-related
        if (!$this->is_login_related_request()) {
            return;
        }

        // Additional checks after WordPress is fully loaded
        if ($this->is_saml_request()) {
            shl_log('SAML request confirmed at wp_loaded');
        }
    }

    /**
     * Template redirect hook
     */
    public function template_redirect() {
        // PERFORMANCE: Early return if not login-related
        if (!$this->is_login_related_request()) {
            return;
        }

        // Final check for blocked requests (v3.0: only block basic login)
        if ($this->is_wp_login_request() && !$this->is_saml_request() && !is_user_logged_in()) {
            $action = isset($_GET['action']) ? $_GET['action'] : '';

            // Block ONLY basic login (no action or action=login)
            if (empty($action) || $action === 'login') {
                shl_log('Template redirect - blocking basic login wp-login.php request');
                $this->wp_template_redirect();
            }
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
     * Filter login_url - rewrite ONLY basic login to custom slug
     * (v3.0: Simplified to only handle basic login, not logout/password reset)
     */
    public function login_url($login_url, $redirect, $force_reauth) {
        // DON'T rewrite if we're currently processing a SAML request
        // This prevents redirect loops during SAML authentication flow
        if (isset($_REQUEST['saml_acs']) || isset($_REQUEST['saml_sso']) ||
            isset($_REQUEST['saml_sls']) || isset($_REQUEST['SAMLResponse']) ||
            isset($_REQUEST['SAMLRequest'])) {
            shl_log('Not filtering login URL - currently in SAML request: ' . $login_url);
            return $login_url;
        }

        // Only rewrite if it's wp-login.php
        if (strpos($login_url, 'wp-login.php') === false) {
            return $login_url;
        }

        // Parse query string to check for action parameter
        $query_string = parse_url($login_url, PHP_URL_QUERY);
        parse_str($query_string ? $query_string : '', $params);

        // Don't rewrite SAML URLs
        if (isset($params['saml_acs']) || isset($params['saml_sso']) || isset($params['saml_sls'])) {
            shl_log('Not filtering SAML login URL: ' . $login_url);
            return $login_url;
        }

        // Don't rewrite if action parameter exists (logout, lostpassword, etc.)
        // Only rewrite basic login (no action or action=login)
        if (isset($params['action']) && $params['action'] !== 'login') {
            return $login_url;
        }

        // Rewrite to custom slug for basic login only
        $login_url = home_url($this->custom_login_slug);
        shl_log('Rewriting login URL to: ' . $login_url);

        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }

        if ($force_reauth) {
            $login_url = add_query_arg('reauth', '1', $login_url);
        }

        return $login_url;
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

// Initialize the plugin (smart loading based on request type)
function saml_hide_login_init() {
    global $saml_hide_login;

    // Check if this is a login-related request
    if (shl_is_login_related_request()) {
        // Load full plugin for login pages
        $saml_hide_login = new SAML_Hide_Login();
        shl_log('Full plugin instance created for login request');
    } else {
        // For non-login pages, only register lightweight URL filter for basic login
        // v3.0: Only rewrite login URL, let WordPress handle logout/password reset natively
        add_filter('login_url', 'shl_filter_login_url', 10, 3);

        // No logging on regular pages to reduce noise
    }
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