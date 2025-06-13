<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CLS_PLUGIN_DIR . 'includes/lib/php-jwt/JWT.php';
require_once CLS_PLUGIN_DIR . 'includes/lib/php-jwt/Key.php';
require_once CLS_PLUGIN_DIR . 'includes/lib/php-jwt/JWK.php'; // Added JWK
require_once CLS_PLUGIN_DIR . 'includes/lib/php-jwt/ExpiredException.php';
require_once CLS_PLUGIN_DIR . 'includes/lib/php-jwt/SignatureInvalidException.php';
require_once CLS_PLUGIN_DIR . 'includes/lib/php-jwt/BeforeValidException.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\JWK; // Added JWK
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

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


        $client_id = cls_get_setting('apple_service_id', 'YOUR_APPLE_SERVICE_ID');
        // CLS_APPLE_REDIRECT_URI is assumed to be a constant for now.
        $redirect_uri = defined('CLS_APPLE_REDIRECT_URI') ? CLS_APPLE_REDIRECT_URI : home_url('/apple-auth-callback');
        if (empty($client_id) || $client_id === 'YOUR_APPLE_SERVICE_ID') {
             error_log('Apple Sign In: Client ID (Service ID) not configured in settings.');
        }
         if (!defined('CLS_APPLE_REDIRECT_URI')) {
            error_log('Apple Sign In: CLS_APPLE_REDIRECT_URI constant is not defined.');
        }

        wp_localize_script( 'cls-apple-auth', 'cls_apple_auth_params', array(
            'client_id'    => $client_id,
            'redirect_uri' => $redirect_uri,
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
        $client_id = cls_get_setting('apple_service_id');
        $team_id = cls_get_setting('apple_team_id');
        $key_id = cls_get_setting('apple_key_id');
        // $private_key = cls_get_setting('apple_private_key'); // Not directly used in button rendering decision usually

        if ( empty($client_id) || empty($team_id) || empty($key_id) ) {
             return current_user_can('manage_options') ? '<p>'.esc_html__( '[Admin] Sign in with Apple is not fully configured (missing Service ID, Team ID, or Key ID).', 'custom-login-subscription' ).'</p>' : '';
        }

        // The `id` is important for the JS to find the button.
        // Apple has specific styling guidelines. This is a basic button.
        return '<button id="cls-apple-signin-button" class="cls-apple-login-button button" style="background-color: #000; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">' . esc_html__( 'Sign in with Apple', 'custom-login-subscription' ) . '</button>';
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
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if ( !isset($_POST['state']) || !isset($_SESSION['apple_auth_state']) || $_POST['state'] !== $_SESSION['apple_auth_state'] ) {
            error_log('Apple Auth Error: Invalid state parameter. CSRF might be attempted.');
            wp_die( esc_html__('Invalid state. CSRF protection mismatch.', 'custom-login-subscription') );
        }
        unset($_SESSION['apple_auth_state']); // Clean up

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
            error_log('Apple Auth Error: Full JWT validation failed or token did not contain subject (sub). Review previous logs for details. Raw token hint: ' . substr($id_token, 0, 20) . '...');
            wp_die( esc_html__('Authentication with Apple failed. Could not validate your identity token.', 'custom-login-subscription') );
            return;
        }

        // $apple_client_id setting is now checked within decode_apple_jwt
        // Redundant claim checks are also removed as they are handled by decode_apple_jwt

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
            wp_die( esc_html__('Could not retrieve email from Apple. Please ensure you authorize email sharing.', 'custom-login-subscription') );
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
            $redirect_url = cls_handle_social_login_redirect( $user->ID ); // Redirect for existing user
            wp_redirect( $redirect_url );
            exit;
        } else {
            // User does not exist, create a new user
            $username = $this->generate_username_from_email( $email, $first_name, $last_name );
            $password = wp_generate_password( 20, true );

            $user_id = wp_create_user( $username, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                error_log( 'WordPress User Creation Error (Apple): ' . $user_id->get_error_message() );
                wp_die( sprintf( esc_html__( 'Could not create user: %s', 'custom-login-subscription' ), esc_html($user_id->get_error_message()) ) );
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
            wp_new_user_notification( $user_id, null, 'both' );
            $redirect_url = cls_handle_social_login_redirect( $user_id ); // Redirect for new user
            wp_redirect( $redirect_url );
            exit;
        }

        // This part should ideally not be reached if the logic above is correct.
        // wp_redirect( home_url() );
        // exit;
    }

    private function get_apple_public_keys() {
        $cache_key = 'cls_apple_public_keys_v2'; // New cache key
        $cached_data = get_transient( $cache_key );
        if ( $cached_data ) {
            return $cached_data; // This will be an array like ['keys' => [...]]
        }

        $response = wp_remote_get( 'https://appleid.apple.com/auth/keys' );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            error_log( 'Apple Auth: Failed to fetch public keys from Apple. ' . (is_wp_error($response) ? $response->get_error_message() : 'HTTP Status: ' . wp_remote_retrieve_response_code($response)) );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true ); // $data should be like ['keys' => [ 0 => ['kty' => ..., 'kid' => ...], ... ]]

        if ( ! isset( $data['keys'] ) || ! is_array( $data['keys'] ) ) {
            error_log( 'Apple Auth: Invalid format for public keys from Apple. Response: ' . $body );
            return null;
        }

        set_transient( $cache_key, $data, DAY_IN_SECONDS ); // Cache the entire object containing the 'keys' array for 1 day
        return $data;
    }

    private function decode_apple_jwt( $id_token ) {
        $apple_client_id = cls_get_setting('apple_service_id');
        if (empty($apple_client_id)) {
            error_log('Apple Auth Error: Apple Service ID (Client ID) is not configured for JWT validation.');
            return null;
        }

        $jwks_data = $this->get_apple_public_keys(); // Fetches e.g. ['keys' => [0 => ['kty'...], 1 => ['kty'...]]]
        if ( ! $jwks_data || ! isset( $jwks_data['keys'] ) || empty( $jwks_data['keys'] ) ) {
            error_log('Apple Auth: Failed to get Apple public keys or keys array is empty for JWT validation.');
            return null;
        }

        try {
            // firebase/php-jwt v6+ uses JWK::parseKeySet to prepare the keys.
            // This function expects an array of JWK data (like the 'keys' array from Apple's response).
            $parsed_jwks = JWK::parseKeySet( $jwks_data['keys'], 'RS256' ); // Specify allowed algorithm for safety

            JWT::$leeway = 60; // Allow up to 60 seconds of clock skew for exp, nbf, iat claims.

            // JWT::decode will use the 'kid' from the token header to select the correct key from $parsed_jwks.
            $decoded_token = JWT::decode( $id_token, $parsed_jwks );

            // Validate standard claims
            if ( !isset($decoded_token->iss) || $decoded_token->iss !== 'https://appleid.apple.com' ) {
                error_log( 'Apple Auth Error: Token issuer (iss) mismatch. Expected: https://appleid.apple.com, Got: ' . ($decoded_token->iss ?? 'null') );
                return null;
            }

            // Apple can return 'aud' as a string or an array of strings.
            $audience_is_valid = false;
            if (isset($decoded_token->aud)) {
                if (is_array($decoded_token->aud)) {
                    if (in_array($apple_client_id, $decoded_token->aud)) {
                        $audience_is_valid = true;
                    }
                } elseif (is_string($decoded_token->aud)) {
                    if ($decoded_token->aud === $apple_client_id) {
                        $audience_is_valid = true;
                    }
                }
            }

            if (!$audience_is_valid) {
                $received_aud = 'null';
                if(isset($decoded_token->aud)) {
                    $received_aud = is_array($decoded_token->aud) ? implode(', ', $decoded_token->aud) : $decoded_token->aud;
                }
                error_log( 'Apple Auth Error: Token audience (aud) mismatch or missing. Expected: ' . $apple_client_id . ', Got: ' . $received_aud );
                return null;
            }

            // 'exp' (expiration) is automatically checked by JWT::decode().
            // 'sub' (subject/user ID) must be present.
            if ( !isset($decoded_token->sub) || empty($decoded_token->sub) ) {
                error_log( 'Apple Auth Error: Token subject (sub) missing or empty.' );
                return null;
            }

            // Optional: Nonce validation if you implement it.
            // if ( !isset($decoded_token->nonce) || $decoded_token->nonce !== $_SESSION['apple_auth_nonce_expected'] ) { // Assuming you stored a nonce
            //     error_log( 'Apple Auth Error: Token nonce mismatch.' );
            //     return null;
            // }

            return $decoded_token; // Success

        } catch (ExpiredException $e) {
            error_log( 'Apple Auth Error: ID token expired. Message: ' . $e->getMessage() );
        } catch (SignatureInvalidException $e) {
            error_log( 'Apple Auth Error: ID token signature invalid. Message: ' . $e->getMessage() );
        } catch (BeforeValidException $e) {
            error_log( 'Apple Auth Error: ID token not yet valid (e.g., nbf claim). Message: ' . $e->getMessage() );
        } catch (Exception $e) { // Catching \Firebase\JWT\InvalidArgumentException and other generic \Exception
            error_log( 'Apple Auth Error: ID token decoding failed. Message: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString() );
        }

        return null; // Return null on any failure
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
