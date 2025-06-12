<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Facebook OAuth 2.0 process.
 */
class CLS_Facebook_Auth {

    public function __construct() {
        add_shortcode( 'cls_facebook_login_button', array( $this, 'render_login_button' ) );
        add_action( 'init', array( $this, 'handle_facebook_redirect' ) );
        add_action( 'template_redirect', array( $this, 'handle_facebook_callback' ) );
    }

    /**
     * Renders the "Login with Facebook" button.
     */
    public function render_login_button() {
        $facebook_app_id = cls_get_setting('facebook_app_id');
        if ( empty($facebook_app_id) ) {
            return current_user_can('manage_options') ? '<p>'.esc_html__( '[Admin] Facebook Login is not configured.', 'custom-login-subscription' ).'</p>' : '';
        }

        if (!defined('CLS_FACEBOOK_REDIRECT_URI')) {
            error_log('CLS_FACEBOOK_REDIRECT_URI is not defined. Facebook login may fail.');
            return current_user_can('manage_options') ? '<p>'.esc_html__( '[Admin] Facebook Login redirect URI is not configured.', 'custom-login-subscription' ).'</p>' : '';
        }

        $auth_init_url = add_query_arg( 'facebook_auth_init', '1', home_url( '/' ) );

        return '<a href="' . esc_url( $auth_init_url ) . '" class="cls-facebook-login-button button">' . esc_html__( 'Login with Facebook', 'custom-login-subscription' ) . '</a>';
    }

    /**
     * Initiates the Facebook OAuth flow by redirecting the user.
     */
    public function handle_facebook_redirect() {
        if ( isset( $_GET['facebook_auth_init'] ) && $_GET['facebook_auth_init'] == '1' ) {
            $facebook_app_id = cls_get_setting('facebook_app_id');
            if ( empty($facebook_app_id) ) {
                wp_die( esc_html__( 'Facebook Login is not configured by the site administrator. Cannot initiate OAuth.', 'custom-login-subscription' ) );
            }
            if (!defined('CLS_FACEBOOK_REDIRECT_URI')) {
                 wp_die( esc_html__( 'Facebook Login redirect URI is not configured. Cannot initiate OAuth.', 'custom-login-subscription' ) );
            }

            if ( ! session_id() ) {
                session_start();
            }
            // Generate state for CSRF protection
            $_SESSION['facebook_oauth_state'] = bin2hex( random_bytes(16) );

            $params = array(
                'client_id'     => $facebook_app_id,
                'redirect_uri'  => CLS_FACEBOOK_REDIRECT_URI, // Assuming this remains a constant
                'scope'         => 'email,public_profile',
                'response_type' => 'code',
                'state'         => $_SESSION['facebook_oauth_state'],
            );
            $facebook_oauth_url = 'https://www.facebook.com/v12.0/dialog/oauth?' . http_build_query( $params );

            wp_redirect( $facebook_oauth_url );
            exit;
        }
    }

    /**
     * Handles the callback from Facebook after user authentication.
     */
    public function handle_facebook_callback() {
        // Check if this is the Facebook callback URL and code is present
        if ( strpos( $_SERVER['REQUEST_URI'], 'facebook-auth-callback' ) === false || ! isset( $_GET['code'] ) ) {
            return;
        }

        if ( ! session_id() ) {
            session_start();
        }

        // Verify state for CSRF
        if ( ! isset( $_GET['state'] ) || ! isset( $_SESSION['facebook_oauth_state'] ) || $_GET['state'] !== $_SESSION['facebook_oauth_state'] ) {
            error_log('Facebook OAuth Error: Invalid state parameter. CSRF attempt?');
            wp_die( esc_html__( 'Invalid state parameter. CSRF attempt?', 'custom-login-subscription' ) );
        }
        unset( $_SESSION['facebook_oauth_state'] ); // Clean up state

        $code = sanitize_text_field( $_GET['code'] );

        $facebook_app_id = cls_get_setting('facebook_app_id');
        $facebook_app_secret = cls_get_setting('facebook_app_secret');

        if ( empty($facebook_app_id) || empty($facebook_app_secret) ) {
            error_log( 'Facebook OAuth Error: App ID or Secret is not configured in settings.' );
            wp_die( esc_html__( 'Facebook authentication is not properly configured. Missing API credentials.', 'custom-login-subscription' ) );
            return;
        }
        if (!defined('CLS_FACEBOOK_REDIRECT_URI')) {
            error_log( 'Facebook OAuth Error: CLS_FACEBOOK_REDIRECT_URI is not defined.' );
            wp_die( esc_html__( 'Facebook authentication redirect URI is not configured.', 'custom-login-subscription' ) );
            return;
        }

        // Exchange authorization code for an access token
        $token_url = 'https://graph.facebook.com/v12.0/oauth/access_token?' . http_build_query( array(
            'client_id'     => $facebook_app_id,
            'client_secret' => $facebook_app_secret,
            'redirect_uri'  => CLS_FACEBOOK_REDIRECT_URI, // Assuming constant
            'code'          => $code,
        ) );

        $token_response = wp_remote_get( $token_url, array( 'timeout' => 60 ) );

        if ( is_wp_error( $token_response ) ) {
            error_log( 'Facebook Token API Error: ' . $token_response->get_error_message() );
            wp_die( sprintf( esc_html__( 'Error exchanging Facebook auth code for token: %s', 'custom-login-subscription' ), esc_html($token_response->get_error_message()) ) );
            return;
        }

        $token_body = wp_remote_retrieve_body( $token_response );
        $token_data = json_decode( $token_body, true );

        if ( ! isset( $token_data['access_token'] ) ) {
            $error_message = isset($token_data['error']['message']) ? $token_data['error']['message'] : esc_html__('No access token received.', 'custom-login-subscription');
            error_log( 'Facebook Token API Error: ' . $error_message . ' Response: ' . $token_body );
            wp_die( sprintf( esc_html__( 'Could not retrieve access token from Facebook. %s', 'custom-login-subscription' ), esc_html( $error_message ) ) );
            return;
        }

        $access_token = $token_data['access_token'];

        // Fetch user profile information
        $userinfo_url = 'https://graph.facebook.com/me?' . http_build_query( array(
            'fields'       => 'id,name,email,first_name,last_name',
            'access_token' => $access_token,
        ) );
        $userinfo_response = wp_remote_get( $userinfo_url, array( 'timeout' => 60 ) );

        if ( is_wp_error( $userinfo_response ) ) {
            error_log( 'Facebook UserInfo API Error: ' . $userinfo_response->get_error_message() );
            wp_die( sprintf( esc_html__( 'Error fetching user information from Facebook: %s', 'custom-login-subscription' ), esc_html($userinfo_response->get_error_message()) ) );
            return;
        }

        $userinfo_body = wp_remote_retrieve_body( $userinfo_response );
        $user_info = json_decode( $userinfo_body, true );

        if ( ! isset( $user_info['id'] ) ) {
            $error_message = isset($user_info['error']['message']) ? $user_info['error']['message'] : esc_html__('User ID not found.', 'custom-login-subscription');
            error_log( 'Facebook UserInfo API Error: ' . $error_message . ' Response: ' . $userinfo_body );
            wp_die( sprintf( esc_html__( 'Could not retrieve user ID from Facebook. %s', 'custom-login-subscription' ), esc_html( $error_message ) ) );
            return;
        }

        $email = isset( $user_info['email'] ) ? sanitize_email( $user_info['email'] ) : '';
        $facebook_user_id = sanitize_text_field( $user_info['id'] );
        $first_name = isset( $user_info['first_name'] ) ? sanitize_text_field( $user_info['first_name'] ) : '';
        $last_name = isset( $user_info['last_name'] ) ? sanitize_text_field( $user_info['last_name'] ) : '';
        $full_name = isset( $user_info['name'] ) ? sanitize_text_field( $user_info['name'] ) : trim( $first_name . ' ' . $last_name);


        if ( empty( $email ) ) {
            // Handle missing email - for now, we'll block this.
            // In a real plugin, you might redirect to a form to ask for email,
            // or if your policy allows, create an account without an email (not standard for WP).
            error_log( 'Facebook Login Error: Email address not provided by Facebook for user ID ' . $facebook_user_id );
            wp_die( esc_html__( 'An email address is required to create an account. Facebook did not provide one for your profile. Please ensure your Facebook account has a verified email and that you have granted permission to share it.', 'custom-login-subscription' ) );
            return;
        }

        $user = get_user_by( 'email', $email );

        if ( $user ) {
            // User exists, log them in
            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID );
            update_user_meta( $user->ID, 'facebook_user_id', $facebook_user_id );
            // Potentially update other details
        } else {
            // User does not exist, create a new user
            $username = $this->generate_username_from_email( $email, $first_name, $last_name );
            $password = wp_generate_password( 20, true );

            $user_id = wp_create_user( $username, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                error_log( 'WordPress User Creation Error (Facebook): ' . $user_id->get_error_message() );
                wp_die( sprintf( esc_html__( 'Could not create user: %s', 'custom-login-subscription' ), esc_html($user_id->get_error_message()) ) );
                return;
            }

            wp_update_user( array(
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => $full_name,
            ) );
            update_user_meta( $user_id, 'facebook_user_id', $facebook_user_id );

            wp_set_current_user( $user_id, $username );
            wp_set_auth_cookie( $user_id );
            // wp_new_user_notification( $user_id, null, 'both' );
        }

        wp_redirect( home_url() );
        exit;
    }

    /**
     * Generates a unique WordPress username.
     * Prefers username from email, falls back to first/last name, then appends numbers if taken.
     */
    private function generate_username_from_email( $email, $first_name = '', $last_name = '' ) {
        $username_base = '';
        if ( ! empty( $email ) ) {
            $username_base = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
        } elseif ( ! empty( $first_name ) || ! empty( $last_name ) ) {
            $username_base = sanitize_user( $first_name . $last_name, true );
        }

        if ( empty( $username_base ) ) { // Fallback if all else fails
            $username_base = 'user';
        }

        $username = $username_base;
        $i = 1;
        while ( username_exists( $username ) ) {
            $username = $username_base . $i;
            $i++;
        }
        return $username;
    }
}

// Instantiate the class
new CLS_Facebook_Auth();

?>
