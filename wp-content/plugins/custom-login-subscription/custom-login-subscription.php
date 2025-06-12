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

// Google API Credentials - Replace with your actual credentials
define('CLS_GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('CLS_GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('CLS_GOOGLE_REDIRECT_URI', home_url('/google-auth-callback')); // Example callback URL

// Facebook API Credentials - Replace with your actual credentials
define('CLS_FACEBOOK_APP_ID', 'YOUR_FACEBOOK_APP_ID');
define('CLS_FACEBOOK_APP_SECRET', 'YOUR_FACEBOOK_APP_SECRET');
define('CLS_FACEBOOK_REDIRECT_URI', home_url('/facebook-auth-callback')); // Example callback URL

// Apple Sign In Credentials - Replace with your actual credentials
define('CLS_APPLE_CLIENT_ID', 'YOUR_APPLE_SERVICE_ID'); // e.g., com.example.webapp
define('CLS_APPLE_TEAM_ID', 'YOUR_APPLE_TEAM_ID');
define('CLS_APPLE_KEY_ID', 'YOUR_APPLE_KEY_ID');
define('CLS_APPLE_PRIVATE_KEY_PATH', 'path/to/your/AuthKey_XXXXXX.p8'); // Or the key content itself
define('CLS_APPLE_REDIRECT_URI', home_url('/apple-auth-callback'));

// Stripe API Credentials - Replace with your actual credentials
define('CLS_STRIPE_PUBLISHABLE_KEY', 'YOUR_STRIPE_PUBLISHABLE_KEY');
define('CLS_STRIPE_SECRET_KEY', 'YOUR_STRIPE_SECRET_KEY');
define('CLS_STRIPE_WEBHOOK_SECRET', 'YOUR_STRIPE_WEBHOOK_SECRET'); // For webhook handling later

// Include Stripe PHP library autoloader
if ( file_exists( CLS_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php' ) ) {
    require_once CLS_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
} else {
    // Maybe add an admin notice if the library is missing
    // For now, this will prevent fatal errors if the library isn't there,
    // but Stripe functionality will not work.
    error_log('Stripe PHP library not found. Please install it in vendor/stripe/stripe-php/');
}

// PayPal API Credentials - Replace with your actual credentials
define('CLS_PAYPAL_CLIENT_ID', 'YOUR_PAYPAL_CLIENT_ID');
define('CLS_PAYPAL_CLIENT_SECRET', 'YOUR_PAYPAL_CLIENT_SECRET');
define('CLS_PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'
define('CLS_PAYPAL_WEBHOOK_ID', 'YOUR_PAYPAL_WEBHOOK_ID'); // For webhook verification later

/**
 * The code that runs during plugin activation.
 */
function activate_custom_login_subscription() {
    // Activation code (e.g., creating custom tables, setting default options)
}
register_activation_hook( __FILE__, 'activate_custom_login_subscription' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_custom_login_subscription() {
    // Deactivation code (e.g., cleaning up options, transients)
}
register_deactivation_hook( __FILE__, 'deactivate_custom_login_subscription' );

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
    if ( defined('CLS_STRIPE_PUBLISHABLE_KEY') && CLS_STRIPE_PUBLISHABLE_KEY !== 'YOUR_STRIPE_PUBLISHABLE_KEY' ) {
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
                'publishable_key' => CLS_STRIPE_PUBLISHABLE_KEY,
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'cls_stripe_checkout_nonce' ),
            )
        );
    }

    // Enqueue PayPal checkout script if PayPal is configured
    if ( defined('CLS_PAYPAL_CLIENT_ID') && CLS_PAYPAL_CLIENT_ID !== 'YOUR_PAYPAL_CLIENT_ID' && defined('CLS_PAYPAL_MODE') && CLS_PAYPAL_MODE !== '' ) {
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

/**
 * Begins execution of the plugin.
 */
// function run_custom_login_subscription() {
//     $plugin = new Custom_Login_Subscription();
//     $plugin->run();
// }
// run_custom_login_subscription();

?>
