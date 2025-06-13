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
require_once CLS_PLUGIN_DIR . 'includes/shortcodes.php'; // For package display shortcode
require_once CLS_PLUGIN_DIR . 'includes/shortcodes/dashboard-shortcodes.php'; // For dashboard shortcodes
require_once CLS_PLUGIN_DIR . 'includes/shortcodes/course-management-shortcodes.php'; // For course management
require_once CLS_PLUGIN_DIR . 'includes/shortcodes/lesson-management-shortcodes.php'; // For lesson management
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

// Could be in custom-login-subscription.php or includes/setup.php
if ( ! function_exists( 'cls_create_placeholder_pages' ) ) {
    function cls_create_placeholder_pages() {
        $pages_to_create = array(
            'package-subscription' => array(
                'title' => 'Package Subscription',
                'content' => '<!-- wp:shortcode -->[cls_subscription_packages_list /]<!-- /wp:shortcode --> <p>Please subscribe to a package to continue.</p>',
                'option_name' => 'cls_package_subscription_page_id' // Option to store the page ID
            ),
            'student-dashboard' => array(
                'title' => 'Student Dashboard',
                'content' => '<!-- wp:shortcode -->[cls_student_dashboard /]<!-- /wp:shortcode --> <p>Welcome to your dashboard!</p>',
                'option_name' => 'cls_student_dashboard_page_id'
            ),
            'instructor-dashboard' => array(
                'title' => 'Instructor Dashboard',
                'content' => '<!-- wp:shortcode -->[cls_instructor_dashboard /]<!-- /wp:shortcode --> <p>Welcome to your dashboard!</p>',
                'option_name' => 'cls_instructor_dashboard_page_id'
            ),
            'institution-dashboard' => array(
                'title' => 'Institution Dashboard',
                'content' => '<!-- wp:shortcode -->[cls_institution_dashboard /]<!-- /wp:shortcode --> <p>Welcome to your dashboard!</p>',
                'option_name' => 'cls_institution_dashboard_page_id'
            )
        );

        foreach ( $pages_to_create as $slug => $page_data ) {
            $page_id = get_option( $page_data['option_name'] );

            // Check if page exists and is valid
            if ( $page_id && get_post_status( $page_id ) === 'publish' && get_post_type( $page_id ) === 'page' ) {
                // Optional: Check if slug matches, though ID is primary reference
                // $existing_page = get_post($page_id);
                // if ($existing_page && $existing_page->post_name === $slug) {
                //    continue; // Page exists and slug matches
                // }
                continue; // Page exists
            }

            // Check if a page with this slug already exists (e.g. user created it manually)
            $existing_page_by_slug = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $existing_page_by_slug ) {
                update_option( $page_data['option_name'], $existing_page_by_slug->ID );
                // Optionally, update its content if it's empty or different
                // if (empty($existing_page_by_slug->post_content)) {
                //    wp_update_post(['ID' => $existing_page_by_slug->ID, 'post_content' => $page_data['content']]);
                // }
                continue;
            }

            // Create the page
            $page_args = array(
                'post_title'    => $page_data['title'],
                'post_content'  => $page_data['content'],
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_name'     => $slug, // Set the slug
                'comment_status' => 'closed',
                'ping_status'   => 'closed',
            );
            $new_page_id = wp_insert_post( $page_args );

            if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
                update_option( $page_data['option_name'], $new_page_id );
            }
        }
    }
}

// Add this function in custom-login-subscription.php, outside any class
if ( ! function_exists( 'cls_handle_social_login_redirect' ) ) {
    function cls_handle_social_login_redirect( $user_id ) {
        // Placeholder for subscription check - this will be refined in later phases
        // For now, assume no subscription by default.
        // A real check might involve looking up a custom table or user meta set by payment gateways.
        $has_active_subscription = get_user_meta( $user_id, '_cls_has_active_subscription', true ); // Example meta key

        if ( ! $has_active_subscription ) {
            $subscription_page_id = get_option('cls_package_subscription_page_id');
            if ($subscription_page_id) {
                return get_permalink($subscription_page_id);
            } else {
                // Fallback if page ID not found in options (should not happen if created on activation)
                return home_url( '/package-subscription/' );
            }
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return home_url(); // Fallback if user not found
        }

        $redirect_url = home_url(); // Default redirect

        if ( in_array( 'student', (array) $user->roles ) ) {
            $dashboard_page_id = get_option('cls_student_dashboard_page_id');
            if ($dashboard_page_id) {
                $redirect_url = get_permalink($dashboard_page_id);
            } else {
                $redirect_url = home_url( '/student-dashboard/' ); // Fallback
            }
        } elseif ( in_array( 'instructor', (array) $user->roles ) ) {
            $dashboard_page_id = get_option('cls_instructor_dashboard_page_id');
            if ($dashboard_page_id) {
                $redirect_url = get_permalink($dashboard_page_id);
            } else {
                $redirect_url = home_url( '/instructor-dashboard/' ); // Fallback
            }
        } elseif ( in_array( 'institution', (array) $user->roles ) ) {
            $dashboard_page_id = get_option('cls_institution_dashboard_page_id');
            if ($dashboard_page_id) {
                $redirect_url = get_permalink($dashboard_page_id);
            } else {
                $redirect_url = home_url( '/institution-dashboard/' ); // Fallback
            }
        }
        // Else, if none of these roles, or multiple roles without a specific primary one,
        // it will redirect to home_url() or you can define other logic.

        return $redirect_url;
    }
}

// Hook into standard WordPress login
add_filter( 'login_redirect', 'cls_apply_custom_login_redirect', 10, 3 );

if ( ! function_exists( 'cls_apply_custom_login_redirect' ) ) {
    function cls_apply_custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        // Ensure $user is a WP_User object and not an error
        if ( is_wp_error( $user ) || ! is_object( $user ) || ! isset( $user->ID ) ) {
            return $redirect_to; // Return default redirect for errors or non-user objects
        }

        // Simplified admin logic from subtask description
        if ( user_can( $user, 'manage_options' ) ) {
            // If admin is trying to go to a specific page (e.g. via ?redirect_to= query)
            if ( !empty($requested_redirect_to) && $requested_redirect_to !== home_url('/') && $requested_redirect_to !== admin_url() ) {
                 // Potentially check if this requested_redirect_to is one of our dashboards, if admin also has that role.
                 // For now, let it pass through or decide if admins should *always* go to admin_url unless it's a frontend dash.
                 // This part is complex. Simplest for now: if admin, and redirect_to is not obviously frontend, let WP handle it or go to admin.
            }
            // If $redirect_to is already pointing to wp-admin, or if $requested_redirect_to is empty,
            // let them proceed to wp-admin. The default $redirect_to for admins is usually wp-admin/profile.php or wp-admin/.
            return $redirect_to;
        }

        // For non-admins, proceed with custom redirection logic
        // Get the redirect URL from the refactored function
        $custom_redirect_url = cls_handle_social_login_redirect( $user->ID );
        return $custom_redirect_url; // Return the URL for login_redirect filter
    }
}
?>
