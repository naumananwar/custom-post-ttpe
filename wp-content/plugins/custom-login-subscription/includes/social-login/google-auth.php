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
        if ( ! defined('CLS_GOOGLE_CLIENT_ID') || CLS_GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID' ) {
            return '<p>Google Client ID is not configured.</p>';
        }
        // Construct the Google OAuth URL
        $google_oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
            'client_id'     => CLS_GOOGLE_CLIENT_ID,
            'redirect_uri'  => CLS_GOOGLE_REDIRECT_URI,
            'scope'         => 'email profile openid',
            'response_type' => 'code',
            'access_type'   => 'offline', // Request refresh token
            'prompt'        => 'select_account' // Ensures account selection even if logged in
        ) );

        // Basic button HTML. Styling can be added later.
        return '<a href="' . esc_url( home_url( '/?google_auth_init=1' ) ) . '" class="cls-google-login-button">Login with Google</a>';
    }

    /**
     * Initiates the Google OAuth flow by redirecting the user.
     */
    public function handle_google_redirect() {
        if ( isset( $_GET['google_auth_init'] ) && $_GET['google_auth_init'] == '1' ) {
            if ( ! defined('CLS_GOOGLE_CLIENT_ID') || CLS_GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID' ) {
                wp_die( 'Google Client ID is not configured. Please configure it in the plugin settings.' );
            }

            // Generate state for CSRF protection (optional but recommended)
            // if ( ! session_id() ) {
            //     session_start();
            // }
            // $_SESSION['google_oauth_state'] = bin2hex( random_bytes(16) );

            $params = array(
                'client_id'     => CLS_GOOGLE_CLIENT_ID,
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
        $token_response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
            'body'    => array(
                'code'          => $code,
                'client_id'     => CLS_GOOGLE_CLIENT_ID,
                'client_secret' => CLS_GOOGLE_CLIENT_SECRET,
                'redirect_uri'  => CLS_GOOGLE_REDIRECT_URI,
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 60, // seconds
        ) );

        if ( is_wp_error( $token_response ) ) {
            error_log( 'Google Token API Error: ' . $token_response->get_error_message() );
            wp_die( 'Error exchanging Google auth code for token: ' . $token_response->get_error_message() );
            return;
        }

        $token_body = wp_remote_retrieve_body( $token_response );
        $token_data = json_decode( $token_body, true );

        if ( ! isset( $token_data['access_token'] ) ) {
            error_log( 'Google Token API Error: No access token received. Response: ' . $token_body );
            wp_die( 'Could not retrieve access token from Google. Response: ' . esc_html( $token_body ) );
            return;
        }

        $access_token = $token_data['access_token'];

        // Fetch user profile information
        $userinfo_response = wp_remote_get( 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=' . $access_token, array(
            'timeout' => 60,
        ));

        if ( is_wp_error( $userinfo_response ) ) {
            error_log( 'Google UserInfo API Error: ' . $userinfo_response->get_error_message() );
            wp_die( 'Error fetching user information from Google: ' . $userinfo_response->get_error_message() );
            return;
        }

        $userinfo_body = wp_remote_retrieve_body( $userinfo_response );
        $user_info = json_decode( $userinfo_body, true );

        if ( ! isset( $user_info['email'] ) ) {
            error_log( 'Google UserInfo API Error: Email not found in user info. Response: ' . $userinfo_body );
            wp_die( 'Could not retrieve user email from Google. Response: ' . esc_html( $userinfo_body ) );
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
                wp_die( 'Could not create user: ' . $user_id->get_error_message() );
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
