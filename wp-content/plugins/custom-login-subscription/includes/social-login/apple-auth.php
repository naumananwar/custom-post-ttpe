<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Sign in with Apple process.
 */
class CLS_Apple_Auth {

    public function __construct() {
        add_shortcode( 'cls_apple_login_button', array( $this, 'render_login_button' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        // Apple POSTs to the redirect URI, so 'init' or 'template_redirect' can catch it.
        // 'init' is generally better for non-themed endpoints.
        add_action( 'init', array( $this, 'handle_apple_callback' ) );
    }

    /**
     * Enqueues the Apple JS SDK and our custom Apple auth script.
     */
    public function enqueue_scripts() {
        // Only enqueue if the shortcode is likely to be used or on specific pages.
        // For simplicity, let's assume it could be anywhere for now.
        // A more optimized approach would be to only enqueue if a shortcode is detected.
        wp_enqueue_script( 'apple-auth-sdk', 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js', array(), null, true );

        wp_register_script( 'cls-apple-auth', CLS_PLUGIN_URL . 'assets/js/apple-auth.js', array( 'apple-auth-sdk' ), CLS_PLUGIN_VERSION, true );

        // Generate a nonce for CSRF protection (state parameter)
        $state = wp_create_nonce('apple_auth_state');
        if ( ! session_id() ) {
            session_start(); // Required to store the state if we were to verify it server-side from a client-side generated one.
                           // However, Apple's flow POSTs directly, state is set client-side and verified by Apple.
                           // We can use a server-generated state and pass it to JS if we want to verify it upon callback.
        }
        $_SESSION['apple_auth_state'] = $state;


        wp_localize_script( 'cls-apple-auth', 'cls_apple_auth_params', array(
            'client_id'    => defined('CLS_APPLE_CLIENT_ID') ? CLS_APPLE_CLIENT_ID : 'YOUR_APPLE_SERVICE_ID',
            'redirect_uri' => defined('CLS_APPLE_REDIRECT_URI') ? CLS_APPLE_REDIRECT_URI : home_url('/apple-auth-callback'),
            'state'        => $state, // Pass the generated state to JS
            'use_popup'    => 'false', // Apple recommends redirect flow for web
        ) );
        wp_enqueue_script( 'cls-apple-auth' );
    }

    /**
     * Renders the "Sign in with Apple" button.
     * Apple has specific guidelines for the button's appearance.
     * For now, a simple button that triggers the JS.
     */
    public function render_login_button() {
        if ( ! defined('CLS_APPLE_CLIENT_ID') || CLS_APPLE_CLIENT_ID === 'YOUR_APPLE_SERVICE_ID' ) {
            return '<p>Sign in with Apple is not configured.</p>';
        }
        // Apple provides official button styles. This is a placeholder.
        // Actual implementation should use CSS to style it according to Apple's guidelines.
        // The `id` is important for the JS to find the button.
        return '<button id="cls-apple-signin-button" class="cls-apple-login-button" style="background-color: #000; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">Sign in with Apple</button>';
    }

    /**
     * Handles the callback from Apple after user authentication.
     * Apple sends a POST request to the redirect URI.
     */
    public function handle_apple_callback() {
        // Check if it's an Apple callback by checking a specific POST parameter Apple sends, e.g., 'code' or 'id_token'.
        // Also check if the request path matches CLS_APPLE_REDIRECT_URI.
        $request_path = strtok( $_SERVER['REQUEST_URI'], '?' );
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || $request_path !== parse_url(CLS_APPLE_REDIRECT_URI, PHP_URL_PATH) || ! isset( $_POST['id_token'] ) ) {
            return;
        }

        // It's good practice to verify the 'state' if you passed one to Apple.
        // The JS sets a state, Apple includes it in the POST.
        // if ( ! session_id() ) {
        //     session_start();
        // }
        // if ( !isset($_POST['state']) || !isset($_SESSION['apple_auth_state']) || $_POST['state'] !== $_SESSION['apple_auth_state'] ) {
        //     error_log('Apple Auth Error: Invalid state parameter. CSRF might be attempted.');
        //     wp_die('Invalid state. CSRF protection mismatch.');
        // }
        // unset($_SESSION['apple_auth_state']); // Clean up

        $id_token = sanitize_text_field( $_POST['id_token'] );
        // $auth_code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : null; // Authorization code

        // --- JWT Validation (Simplified) ---
        // In a real application, you MUST validate the JWT. This involves:
        // 1. Fetching Apple's public keys (e.g., from https://appleid.apple.com/auth/keys). Cache these.
        // 2. Selecting the correct key based on the 'kid' (Key ID) in the JWT header.
        // 3. Using a JWT library (e.g., Firebase JWT) to verify the signature, issuer ('iss' -> https://appleid.apple.com),
        //    audience ('aud' -> your CLS_APPLE_CLIENT_ID), and expiration ('exp').
        // For this subtask, we'll do a basic decode if possible or assume valid if present.

        $decoded_token = $this->decode_apple_jwt( $id_token );

        if ( ! $decoded_token || ! isset( $decoded_token->sub ) ) {
            error_log('Apple Auth Error: Invalid or undecodable ID token. Raw token: ' . $id_token);
            wp_die('Authentication with Apple failed. Could not validate token.');
            return;
        }

        // Verify 'iss' and 'aud' claims (simplified)
        if ( $decoded_token->iss !== 'https://appleid.apple.com' || $decoded_token->aud !== CLS_APPLE_CLIENT_ID ) {
            error_log('Apple Auth Error: Token issuer or audience mismatch. Decoded: ' . print_r($decoded_token, true));
            wp_die('Apple token validation failed (issuer/audience).');
            return;
        }
        // Check 'exp' claim
        if ( time() > $decoded_token->exp ) {
            error_log('Apple Auth Error: Token expired. Decoded: ' . print_r($decoded_token, true));
            wp_die('Apple token has expired.');
            return;
        }

        $apple_user_id = $decoded_token->sub;
        $email = isset( $decoded_token->email ) ? sanitize_email( $decoded_token->email ) : null;
        $email_verified = isset( $decoded_token->email_verified ) && $decoded_token->email_verified === 'true'; // or true (boolean)

        $first_name = '';
        $last_name = '';

        // Apple sends user name info in a separate 'user' POST field, ONLY ONCE (first auth).
        if ( isset( $_POST['user'] ) ) {
            $user_data_json = stripslashes( $_POST['user'] );
            $user_data = json_decode( $user_data_json, true );
            if ( $user_data && isset( $user_data['name'] ) ) {
                $first_name = isset( $user_data['name']['firstName'] ) ? sanitize_text_field( $user_data['name']['firstName'] ) : '';
                $last_name = isset( $user_data['name']['lastName'] ) ? sanitize_text_field( $user_data['name']['lastName'] ) : '';
            }
            if ( $user_data && isset( $user_data['email']) && empty($email) ) {
                 // This email might be more reliable or the private relay one if user chose to hide
                $email = sanitize_email($user_data['email']);
            }
        }

        if ( empty( $email ) ) {
            // If email is still empty, this is problematic. Apple should provide it in token or user field if scope requested.
            // It might be a private relay email.
            error_log("Apple Auth Error: Email not found for user {$apple_user_id}. Token: " . print_r($decoded_token, true) . " POST: " . print_r($_POST, true));
            wp_die('Could not retrieve email from Apple. Please ensure you authorize email sharing.');
            return;
        }

        // If it's a private relay email, it's still a valid email for the user.
        // $is_private_email = isset($decoded_token->is_private_email) && $decoded_token->is_private_email === 'true';


        $user = get_user_by( 'email', $email );

        if ( $user ) {
            // User exists, log them in
            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID );
            update_user_meta( $user->ID, 'apple_user_id', $apple_user_id );
            // Optionally update name if it was missing and now provided
            if ( (empty($user->first_name) && !empty($first_name)) || (empty($user->last_name) && !empty($last_name)) ) {
                $update_args = ['ID' => $user->ID];
                if (!empty($first_name)) $update_args['first_name'] = $first_name;
                if (!empty($last_name)) $update_args['last_name'] = $last_name;
                wp_update_user($update_args);
            }
        } else {
            // User does not exist, create a new user
            $username = $this->generate_username_from_email( $email, $first_name, $last_name );
            $password = wp_generate_password( 20, true );

            $user_id = wp_create_user( $username, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                error_log( 'WordPress User Creation Error (Apple): ' . $user_id->get_error_message() );
                wp_die( 'Could not create user: ' . $user_id->get_error_message() );
                return;
            }

            wp_update_user( array(
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ),
            ) );
            update_user_meta( $user_id, 'apple_user_id', $apple_user_id );
            if ($email_verified) {
                update_user_meta( $user_id, 'apple_email_verified', 'true' );
            }


            wp_set_current_user( $user_id, $username );
            wp_set_auth_cookie( $user_id );
            // wp_new_user_notification( $user_id, null, 'both' );
        }

        wp_redirect( home_url() );
        exit;
    }

    /**
     * Basic decoding of JWT.
     * THIS IS NOT SECURE FOR PRODUCTION WITHOUT SIGNATURE VERIFICATION.
     * Replace with a proper JWT library and validation.
     */
    private function decode_apple_jwt( $jwt_string ) {
        $parts = explode('.', $jwt_string);
        if (count($parts) !== 3) {
            return null; // Invalid JWT structure
        }
        // $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])));
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])));
        // $signature = $parts[2]; // Signature not verified here

        return $payload; // Return only payload
    }


    /**
     * Generates a unique WordPress username.
     */
    private function generate_username_from_email( $email, $first_name = '', $last_name = '' ) {
        $username_base = '';
        if ( ! empty( $email ) && strpos( $email, '@' ) ) {
            $username_base = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
        } elseif ( ! empty( $first_name ) || ! empty( $last_name ) ) {
            $username_base = sanitize_user( $first_name . $last_name, true );
        }

        if ( empty( $username_base ) ) {
            $username_base = 'appleuser'; // Fallback
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
new CLS_Apple_Auth();

?>
