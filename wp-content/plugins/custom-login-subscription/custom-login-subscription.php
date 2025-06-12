<?php
/**
 * Plugin Name:       Custom Login and Subscription
 * Plugin URI:        https://example.com/custom-login-subscription
 * Description:       A comprehensive WordPress plugin for login and subscription functionality.
 * Version:           1.0.0
 * Author:            Your Name or Company
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-login-subscription
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'CLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLS_PLUGIN_VERSION', '1.0.0' );

// Social Login Redirect URIs - These are less likely to be changed by users from an admin panel
// and are more tied to the application registration with the provider.
define('CLS_GOOGLE_REDIRECT_URI', home_url('/google-auth-callback'));
define('CLS_FACEBOOK_REDIRECT_URI', home_url('/facebook-auth-callback'));
define('CLS_APPLE_REDIRECT_URI', home_url('/apple-auth-callback'));


// Include Stripe PHP library autoloader
if ( file_exists( CLS_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php' ) ) {
    require_once CLS_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
} else {
    // Maybe add an admin notice if the library is missing
    // For now, this will prevent fatal errors if the library isn't there,
    // but Stripe functionality will not work.
    error_log('Stripe PHP library not found. Please install it in vendor/stripe/stripe-php/');
}

// Note: API Keys for Google, Facebook, Apple, Stripe, PayPal are now managed via the Settings page
// and retrieved using cls_get_setting().

/**
 * Helper function to get plugin settings.
 *
 * @param string $key The key of the setting to retrieve.
 * @param mixed  $default Optional. Default value to return if the key is not found.
 * @return mixed The value of the setting, or $default if not found.
 */
function cls_get_setting( $key, $default = null ) {
    $options = get_option( 'cls_plugin_settings' );
    return isset( $options[$key] ) ? $options[$key] : $default;
}

// Include User Roles functionality
require_once CLS_PLUGIN_DIR . 'includes/user-roles.php';

/**
 * The code that runs during plugin activation.
 */
function activate_custom_login_subscription() {
    // Activation code (e.g., creating custom tables, setting default options)
    cls_add_custom_user_roles(); // Add custom roles
    flush_rewrite_rules(); // Important after CPT registration and role changes
    cls_schedule_expiration_warnings_cron_job(); // Schedule daily cron
}
register_activation_hook( __FILE__, 'activate_custom_login_subscription' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_custom_login_subscription() {
    // Deactivation code (e.g., cleaning up options, transients)
    cls_remove_custom_user_roles(); // Remove custom roles
    wp_clear_scheduled_hook('cls_daily_expiration_warnings_event'); // Unschedule daily cron
    flush_rewrite_rules(); // Clean up rewrite rules
}
register_deactivation_hook( __FILE__, 'deactivate_custom_login_subscription' );

/**
 * Schedules the daily cron job for sending expiration warnings.
 */
function cls_schedule_expiration_warnings_cron_job() { // Renamed to avoid conflict if a hook has same name
    if ( ! wp_next_scheduled( 'cls_daily_expiration_warnings_event' ) ) {
        wp_schedule_event( time(), 'daily', 'cls_daily_expiration_warnings_event' );
    }
}
// Can also be hooked to 'init' if preferred, but activation is fine for initial setup.
// add_action('init', 'cls_schedule_expiration_warnings_cron_job');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once CLS_PLUGIN_DIR . 'includes/social-login/google-auth.php';
require_once CLS_PLUGIN_DIR . 'includes/social-login/facebook-auth.php';
require_once CLS_PLUGIN_DIR . 'includes/social-login/apple-auth.php';
require_once CLS_PLUGIN_DIR . 'includes/post-types.php';
require_once CLS_PLUGIN_DIR . 'includes/shortcodes.php';
// require CLS_PLUGIN_DIR . 'includes/class-custom-login-subscription.php';

/**
 * Enqueue frontend styles and scripts.
 */
function cls_enqueue_frontend_styles_scripts() { // Renamed function for clarity
    wp_enqueue_style(
        'cls-frontend-styles',
        CLS_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        CLS_PLUGIN_VERSION
    );

    // Enqueue Stripe.js and our custom Stripe checkout script
    $stripe_publishable_key = cls_get_setting('stripe_publishable_key');
    if ( !empty($stripe_publishable_key) ) {
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );

        wp_enqueue_script(
            'cls-stripe-checkout',
            CLS_PLUGIN_URL . 'assets/js/stripe-checkout.js',
            array( 'stripe-js', 'jquery' ),
            CLS_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'cls-stripe-checkout',
            'cls_stripe_params',
            array(
                'publishable_key' => $stripe_publishable_key,
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'cls_stripe_checkout_nonce' ),
            )
        );
    }

    // Enqueue PayPal checkout script if PayPal is configured
    $paypal_client_id = cls_get_setting('paypal_client_id');
    $paypal_mode = cls_get_setting('paypal_mode');
    if ( !empty($paypal_client_id) && !empty($paypal_mode) ) {
        wp_enqueue_script(
            'cls-paypal-checkout',
            CLS_PLUGIN_URL . 'assets/js/paypal-checkout.js',
            array( 'jquery' ),
            CLS_PLUGIN_VERSION,
            true
        );
        wp_localize_script(
            'cls-paypal-checkout',
            'cls_paypal_params',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'cls_paypal_checkout_nonce' ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'cls_enqueue_frontend_styles_scripts' ); // Adjusted hook to new function name

// Include payment gateway handlers
require_once CLS_PLUGIN_DIR . 'includes/payment-gateways/stripe-handler.php';
require_once CLS_PLUGIN_DIR . 'includes/payment-gateways/paypal-handler.php';
require_once CLS_PLUGIN_DIR . 'includes/user-profile.php';
if ( is_admin() ) { // Admin-specific includes
    require_once CLS_PLUGIN_DIR . 'includes/admin/settings-page.php';
}
require_once CLS_PLUGIN_DIR . 'includes/email-handler.php';

/**
 * Begins execution of the plugin.
 */
// function run_custom_login_subscription() {
//     $plugin = new Custom_Login_Subscription();
//     $plugin->run();
// }
// run_custom_login_subscription();

?>
