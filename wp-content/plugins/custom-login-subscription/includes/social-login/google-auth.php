<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Google OAuth 2.0 process.
 */
class CLS_Google_Auth {

    public function __construct() {
        add_shortcode( 'cls_google_login_button', array( $this, 'render_login_button' ) );
        add_action( 'init', array( $this, 'handle_google_redirect' ) ); // For initiating login
        add_action( 'template_redirect', array( $this, 'handle_google_callback' ) );
    }

    /**
     * Renders the "Login with Google" button.
     */
    public function render_login_button() {
        $google_client_id = cls_get_setting('google_client_id');
        if ( empty($google_client_id) ) {
            // Admin should see this on the page where shortcode is, users shouldn't if not configured.
            // However, for simplicity, a generic message or nothing if not configured.
            // For a better UX, this check should ideally be done before rendering anything.
            return current_user_can('manage_options') ? '<p>'.esc_html__( '[Admin] Google Login is not configured.', 'custom-login-subscription' ).'</p>' : '';
        }

        if (!defined('CLS_GOOGLE_REDIRECT_URI')) {
            error_log('CLS_GOOGLE_REDIRECT_URI is not defined. Google login may fail.');
            return current_user_can('manage_options') ? '<p>'.esc_html__( '[Admin] Google Login redirect URI is not configured.', 'custom-login-subscription' ).'</p>' : '';
        }

        // The URL itself is constructed for redirect, not direct display, so internal parts don't need esc_html.
        // esc_url() will be used on the final href.
        $auth_init_url = add_query_arg( 'google_auth_init', '1', home_url( '/' ) );

        return '<a href="' . esc_url( $auth_init_url ) . '" class="cls-google-login-button button">' . esc_html__( 'Login with Google', 'custom-login-subscription' ) . '</a>';
    }

    /**
     * Initiates the Google OAuth flow by redirecting the user.
     */
    public function handle_google_redirect() {
        if ( isset( $_GET['google_auth_init'] ) && $_GET['google_auth_init'] == '1' ) {
            $google_client_id = cls_get_setting('google_client_id');
            if ( empty($google_client_id) ) {
                wp_die( esc_html__( 'Google Login is not configured by the site administrator. Cannot initiate OAuth.', 'custom-login-subscription' ) );
            }
            if (!defined('CLS_GOOGLE_REDIRECT_URI')) {
                 wp_die( esc_html__( 'Google Login redirect URI is not configured. Cannot initiate OAuth.', 'custom-login-subscription' ) );
            }

            // Generate state for CSRF protection (optional but recommended)
            // if ( ! session_id() ) {
            //     session_start();
            // }
            // $_SESSION['google_oauth_state'] = bin2hex( random_bytes(16) );

            $params = array(
                'client_id'     => $google_client_id,
                'redirect_uri'  => CLS_GOOGLE_REDIRECT_URI,
                'scope'         => 'email profile openid',
                'response_type' => 'code',
                'access_type'   => 'offline',
                'prompt'        => 'select_account',
                // 'state'         => $_SESSION['google_oauth_state'] // Add state to params
            );
            $google_oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );

            wp_redirect( $google_oauth_url );
            exit;
        }
    }


    /**
     * Handles the callback from Google after user authentication.
     */
    public function handle_google_callback() {
        // Check if this is the Google callback URL
        // A more robust way to check the URL would be to parse it
        if ( strpos( $_SERVER['REQUEST_URI'], 'google-auth-callback' ) === false || ! isset( $_GET['code'] ) ) {
            return;
        }

        // Verify state for CSRF (if implemented)
        // if ( ! isset( $_GET['state'] ) || ! isset( $_SESSION['google_oauth_state'] ) || $_GET['state'] !== $_SESSION['google_oauth_state'] ) {
        //     // Log this attempt or show an error
        //     wp_die( 'Invalid state parameter. CSRF attempt?' );
        // }
        // unset( $_SESSION['google_oauth_state'] ); // Clean up state

        $code = sanitize_text_field( $_GET['code'] );

        // Exchange authorization code for an access token
        $google_client_id = cls_get_setting('google_client_id');
        $google_client_secret = cls_get_setting('google_client_secret');

        if ( empty($google_client_id) || empty($google_client_secret) ) {
            error_log( 'Google OAuth Error: Client ID or Secret is not configured in settings.' );
            wp_die( esc_html__( 'Google authentication is not properly configured. Missing API credentials.', 'custom-login-subscription' ) );
            return;
        }
         if (!defined('CLS_GOOGLE_REDIRECT_URI')) {
            error_log( 'Google OAuth Error: CLS_GOOGLE_REDIRECT_URI is not defined.' );
            wp_die( esc_html__( 'Google authentication redirect URI is not configured.', 'custom-login-subscription' ) );
            return;
        }

        $token_response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
            'body'    => array(
                'code'          => $code,
                'client_id'     => $google_client_id,
                'client_secret' => $google_client_secret,
                'redirect_uri'  => CLS_GOOGLE_REDIRECT_URI,
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 60, // seconds
        ) );

        if ( is_wp_error( $token_response ) ) {
            error_log( 'Google Token API Error: ' . $token_response->get_error_message() );
            wp_die( sprintf(esc_html__( 'Error exchanging Google auth code for token: %s', 'custom-login-subscription' ), esc_html($token_response->get_error_message()) ) );
            return;
        }

        $token_body = wp_remote_retrieve_body( $token_response );
        $token_data = json_decode( $token_body, true );

        if ( ! isset( $token_data['access_token'] ) ) {
            error_log( 'Google Token API Error: No access token received. Response: ' . $token_body );
            wp_die( sprintf(esc_html__( 'Could not retrieve access token from Google. Response: %s', 'custom-login-subscription' ), esc_html( $token_body ) ) );
            return;
        }

        $access_token = $token_data['access_token'];

        // Fetch user profile information
        $userinfo_response = wp_remote_get( 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=' . $access_token, array(
            'timeout' => 60,
        ));

        if ( is_wp_error( $userinfo_response ) ) {
            error_log( 'Google UserInfo API Error: ' . $userinfo_response->get_error_message() );
            wp_die( sprintf(esc_html__( 'Error fetching user information from Google: %s', 'custom-login-subscription' ), esc_html($userinfo_response->get_error_message()) ) );
            return;
        }

        $userinfo_body = wp_remote_retrieve_body( $userinfo_response );
        $user_info = json_decode( $userinfo_body, true );

        if ( ! isset( $user_info['email'] ) ) {
            error_log( 'Google UserInfo API Error: Email not found in user info. Response: ' . $userinfo_body );
            wp_die( sprintf(esc_html__( 'Could not retrieve user email from Google. Response: %s', 'custom-login-subscription' ), esc_html( $userinfo_body ) ) );
            return;
        }

        $email = sanitize_email( $user_info['email'] );
        $google_user_id = sanitize_text_field( $user_info['id'] );
        $first_name = isset( $user_info['given_name'] ) ? sanitize_text_field( $user_info['given_name'] ) : '';
        $last_name = isset( $user_info['family_name'] ) ? sanitize_text_field( $user_info['family_name'] ) : '';

        // Check if user exists by email
        $user = get_user_by( 'email', $email );

        if ( $user ) {
            // User exists, log them in
            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID );
            update_user_meta( $user->ID, 'google_user_id', $google_user_id ); // Update Google ID if needed
            // Potentially update other details like name if they've changed in Google
        } else {
            // User does not exist, create a new user
            $username = $this->generate_username_from_email( $email );
            $password = wp_generate_password( 20, true ); // Generate a strong password

            $user_id = wp_create_user( $username, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                error_log( 'WordPress User Creation Error: ' . $user_id->get_error_message() );
                wp_die( sprintf(esc_html__( 'Could not create user: %s', 'custom-login-subscription' ), esc_html($user_id->get_error_message()) ) );
                return;
            }

            // Update user details
            wp_update_user( array(
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ),
            ) );
            update_user_meta( $user_id, 'google_user_id', $google_user_id );

            // Log the new user in
            wp_set_current_user( $user_id, $username );
            wp_set_auth_cookie( $user_id );

            // Optionally, send the new user an email about their account
            // wp_new_user_notification( $user_id, null, 'both' );
        }

        // Redirect user to the homepage or a specific dashboard page
        wp_redirect( home_url() );
        exit;
    }

    /**
     * Generates a unique WordPress username from an email address.
     * If the base part of the email is taken, it appends numbers.
     */
    private function generate_username_from_email( $email ) {
        $username = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
        $original_username = $username;
        $i = 1;
        while ( username_exists( $username ) ) {
            $username = $original_username . $i;
            $i++;
        }
        return $username;
    }
}

// Instantiate the class
new CLS_Google_Auth();

?>
