<?php
/**
 * PayPal Payment Gateway Handler for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get PayPal API base URL based on mode.
 * @return string PayPal API base URL.
 */
function cls_get_paypal_api_base_url() {
    if ( defined('CLS_PAYPAL_MODE') && CLS_PAYPAL_MODE === 'live' ) {
        return 'https://api.paypal.com';
    }
    return 'https://api.sandbox.paypal.com';
}

/**
 * Get PayPal Access Token.
 * @return string|false Access token or false on failure.
 */
function cls_get_paypal_access_token() {
    $client_id = defined('CLS_PAYPAL_CLIENT_ID') ? CLS_PAYPAL_CLIENT_ID : '';
    $client_secret = defined('CLS_PAYPAL_CLIENT_SECRET') ? CLS_PAYPAL_CLIENT_SECRET : '';

    if ( empty($client_id) || $client_id === 'YOUR_PAYPAL_CLIENT_ID' || empty($client_secret) ) {
        error_log('PayPal Error: Client ID or Secret not configured.');
        return false;
    }

    $url = cls_get_paypal_api_base_url() . '/v1/oauth2/token';
    $headers = array(
        'Accept'        => 'application/json',
        'Accept-Language' => 'en_US',
        'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
    );
    $body = array(
        'grant_type' => 'client_credentials',
    );

    $response = wp_remote_post( $url, array(
        'method'    => 'POST',
        'headers'   => $headers,
        'body'      => http_build_query( $body ),
        'timeout'   => 60,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'PayPal Token API Error: ' . $response->get_error_message() );
        return false;
    }

    $response_body = wp_remote_retrieve_body( $response );
    $data = json_decode( $response_body, true );

    if ( isset( $data['access_token'] ) ) {
        return $data['access_token'];
    } else {
        error_log( 'PayPal Token API Error: No access token received. Response: ' . $response_body );
        return false;
    }
}


/**
 * Handles the AJAX request to create a PayPal subscription.
 */
function cls_create_paypal_subscription_ajax_handler() {
    check_ajax_referer( 'cls_paypal_checkout_nonce', 'nonce' );

    if ( !is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to subscribe.', 'custom-login-subscription' ) ) );
        return;
    }

    $paypal_plan_id = isset( $_POST['paypal_plan_id'] ) ? sanitize_text_field( $_POST['paypal_plan_id'] ) : null;
    $package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : null;

    if ( ! $paypal_plan_id || ! $package_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid PayPal plan or package information.', 'custom-login-subscription' ) ) );
        return;
    }

    $access_token = cls_get_paypal_access_token();
    if ( ! $access_token ) {
        wp_send_json_error( array( 'message' => __( 'Could not authenticate with PayPal. Please try again later.', 'custom-login-subscription' ) ) );
        return;
    }

    $current_user = wp_get_current_user();
    $api_url = cls_get_paypal_api_base_url() . '/v1/billing/subscriptions';

    // For PayPal, start_time should be in the future, e.g. 5-10 minutes from now.
    // YYYY-MM-DDTHH:MM:SSZ format.
    $start_time = gmdate( "Y-m-d\TH:i:s\Z", time() + (5 * 60) ); // 5 minutes in future

    $payload = array(
        'plan_id'    => $paypal_plan_id,
        'start_time' => $start_time,
        'subscriber' => array(
            'name'          => array(
                'given_name' => $current_user->first_name ? $current_user->first_name : $current_user->display_name,
                'surname'    => $current_user->last_name ? $current_user->last_name : 'User',
            ),
            'email_address' => $current_user->user_email,
        ),
        'application_context' => array(
            'brand_name'          => get_bloginfo( 'name' ),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'SUBSCRIBE_NOW',
            'return_url'          => add_query_arg( array(
                                        'action' => 'cls_paypal_return',
                                        'package_id' => $package_id,
                                        // 'paypal_sub_id' will be appended by PayPal: BA-XXXXXXXXXXXXX (Billing Agreement ID)
                                        // or I-XXXXXXXXXXXXX (Subscription ID in newer API versions)
                                        // We will use 'subscription_id' from query param that PayPal adds.
                                    ), home_url('/paypal-subscription-success/') ), // Page slug
            'cancel_url'          => home_url('/paypal-subscription-canceled/'), // Page slug
        ),
        'custom_id' => $current_user->ID . '|' . $package_id, // Store WP User ID and Package ID
    );

    $response = wp_remote_post( $api_url, array(
        'method'  => 'POST',
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
            // 'PayPal-Request-Id' => 'SOME_UNIQUE_ID_PER_REQUEST' // Optional for idempotency
        ),
        'body'    => json_encode( $payload ),
        'timeout' => 60,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log('PayPal Subscription API Error: ' . $response->get_error_message());
        wp_send_json_error( array( 'message' => 'PayPal API Error: ' . $response->get_error_message() ) );
        return;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $subscription_data = json_decode( $response_body, true );

    if ( $response_code === 200 || $response_code === 201 ) { // 201 Created for subscriptions
        if ( isset( $subscription_data['links'] ) ) {
            foreach ( $subscription_data['links'] as $link ) {
                if ( $link['rel'] === 'approve' || $link['rel'] === 'payer-action' ) {
                    // Store PayPal subscription ID temporarily to associate with user upon return if needed,
                    // or rely on webhook / custom_id
                    // update_user_meta($current_user->ID, '_cls_pending_paypal_subscription_id', $subscription_data['id']);
                    wp_send_json_success( array( 'approve_url' => $link['href'], 'paypal_sub_id' => $subscription_data['id'] ) );
                    return;
                }
            }
        }
        // If no approve link found but successful response
        error_log('PayPal Subscription: Approve link not found in response. Data: ' . $response_body);
        wp_send_json_error( array( 'message' => __( 'Could not retrieve PayPal approval link. Response: ', 'custom-login-subscription' ) . $response_body ) );

    } else {
        error_log('PayPal Subscription API Error: Failed to create subscription. Code: ' . $response_code . ' Body: ' . $response_body);
        $error_message = __( 'Failed to create PayPal subscription.', 'custom-login-subscription' );
        if(isset($subscription_data['details'][0]['description'])){
             $error_message .= ' Details: ' . $subscription_data['details'][0]['description'];
        } else if (isset($subscription_data['message'])) {
             $error_message .= ' Details: ' . $subscription_data['message'];
        }
        wp_send_json_error( array( 'message' => $error_message, 'raw_response' => $response_body ) );
    }
}
add_action( 'wp_ajax_cls_create_paypal_subscription', 'cls_create_paypal_subscription_ajax_handler' );


/**
 * Helper function to get a WordPress user by PayPal Subscription ID.
 *
 * @param string $paypal_subscription_id The PayPal Subscription ID.
 * @return WP_User|false The user object if found, false otherwise.
 */
function cls_get_user_by_paypal_subscription_id( $paypal_subscription_id ) {
    if ( empty( $paypal_subscription_id ) ) {
        return false;
    }
    $users = get_users( array(
        'meta_key'   => '_cls_paypal_subscription_id',
        'meta_value' => $paypal_subscription_id,
        'number'     => 1,
        'count_total' => false,
    ) );
    return ! empty( $users ) ? $users[0] : false;
}


/**
 * Registers the PayPal webhook REST API endpoint.
 */
function cls_register_paypal_webhook_endpoint() {
    register_rest_route( 'custom-login-subscription/v1', '/paypal-webhook', array(
        'methods'  => 'POST',
        'callback' => 'cls_handle_paypal_webhook',
        'permission_callback' => '__return_true', // Open endpoint, security by PayPal signature
    ) );
}
add_action( 'rest_api_init', 'cls_register_paypal_webhook_endpoint' );


/**
 * Verifies a PayPal webhook signature.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @param string $raw_body The raw request body.
 * @return bool True if verified, false otherwise.
 */
function cls_verify_paypal_webhook_signature( WP_REST_Request $request, $raw_body ) {
    $client_id = defined('CLS_PAYPAL_CLIENT_ID') ? CLS_PAYPAL_CLIENT_ID : '';
    $client_secret = defined('CLS_PAYPAL_CLIENT_SECRET') ? CLS_PAYPAL_CLIENT_SECRET : '';
    $webhook_id = defined('CLS_PAYPAL_WEBHOOK_ID') ? CLS_PAYPAL_WEBHOOK_ID : '';

    if ( empty($client_id) || empty($client_secret) || empty($webhook_id) || $webhook_id === 'YOUR_PAYPAL_WEBHOOK_ID' ) {
        error_log('PayPal Webhook Error: Missing Client ID, Secret or Webhook ID for verification.');
        return false;
    }

    $access_token = cls_get_paypal_access_token(); // We need a fresh token
    if ( ! $access_token ) {
        error_log('PayPal Webhook Error: Could not get access token for signature verification.');
        return false;
    }

    $verification_payload = array(
        'auth_algo'         => $request->get_header('paypal-auth-algo'),
        'cert_url'          => $request->get_header('paypal-cert-url'),
        'transmission_id'   => $request->get_header('paypal-transmission-id'),
        'transmission_sig'  => $request->get_header('paypal-transmission-sig'),
        'transmission_time' => $request->get_header('paypal-transmission-time'),
        'webhook_id'        => $webhook_id,
        'webhook_event'     => json_decode($raw_body) // Must be JSON object, not string
    );

    $verify_url = cls_get_paypal_api_base_url() . '/v1/notifications/verify-webhook-signature';
    $verify_response = wp_remote_post( $verify_url, array(
        'method'  => 'POST',
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
        'body'    => json_encode($verification_payload),
        'timeout' => 60,
    ));

    if ( is_wp_error( $verify_response ) ) {
        error_log('PayPal Webhook Signature Verification API Error: ' . $verify_response->get_error_message());
        return false;
    }

    $verify_body = wp_remote_retrieve_body( $verify_response );
    $verify_data = json_decode( $verify_body, true );

    if ( isset( $verify_data['verification_status'] ) && $verify_data['verification_status'] === 'SUCCESS' ) {
        return true;
    } else {
        error_log('PayPal Webhook Signature Verification Failed. Status: ' . ($verify_data['verification_status'] ?? 'Unknown') . ' Response: ' . $verify_body);
        return false;
    }
}


/**
 * Handles incoming PayPal webhooks.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response
 */
function cls_handle_paypal_webhook( WP_REST_Request $request ) {
    $raw_body = $request->get_body();
    $event_body = json_decode( $raw_body ); // For accessing event details

    if ( ! cls_verify_paypal_webhook_signature( $request, $raw_body ) ) {
        error_log('PayPal Webhook Error: Signature verification failed.');
        return new WP_REST_Response( array( 'error' => 'Signature verification failed' ), 403 );
    }

    $event_type = isset( $event_body->event_type ) ? $event_body->event_type : null;
    $resource = isset( $event_body->resource ) ? $event_body->resource : null;

    if ( ! $event_type || ! $resource ) {
        error_log('PayPal Webhook Error: Invalid event structure. Event Type or Resource missing.');
        return new WP_REST_Response( array( 'error' => 'Invalid event structure' ), 400 );
    }

    error_log("PayPal Webhook Received: Event Type: {$event_type}");

    switch ( $event_type ) {
        case 'BILLING.SUBSCRIPTION.ACTIVATED':
            $paypal_subscription_id = $resource->id;
            $custom_id_parts = isset($resource->custom_id) ? explode('|', $resource->custom_id) : null;
            $wp_user_id = isset($custom_id_parts[0]) ? intval($custom_id_parts[0]) : null;
            $wp_package_id = isset($custom_id_parts[1]) ? intval($custom_id_parts[1]) : null;

            if ( $wp_user_id && $wp_package_id && $paypal_subscription_id ) {
                $user = get_user_by('id', $wp_user_id);
                $package = get_post($wp_package_id);

                if ( !$user || ($package && $package->post_type !== 'subscription_package') ) {
                    error_log("PayPal Webhook (BILLING.SUBSCRIPTION.ACTIVATED): User or Package not found. User ID: {$wp_user_id}, Package ID: {$wp_package_id}");
                    break;
                }

                update_user_meta( $wp_user_id, '_cls_paypal_subscription_id', $paypal_subscription_id );
                update_user_meta( $wp_user_id, '_cls_subscription_package_id', $wp_package_id );
                update_user_meta( $wp_user_id, '_cls_subscription_status', 'active' );
                update_user_meta( $wp_user_id, '_cls_subscription_start_date', isset($resource->start_time) ? strtotime($resource->start_time) : time() );
                if (isset($resource->billing_info->next_billing_time)) {
                    update_user_meta( $wp_user_id, '_cls_subscription_end_date', strtotime($resource->billing_info->next_billing_time) );
                }
                update_user_meta( $wp_user_id, '_cls_payment_gateway', 'paypal' );

                do_action('cls_subscription_activated', $wp_user_id, $wp_package_id, $paypal_subscription_id);
                error_log("PayPal Webhook: Processed BILLING.SUBSCRIPTION.ACTIVATED for User ID {$wp_user_id}, PayPal Sub ID {$paypal_subscription_id}");
            } else {
                error_log('PayPal Webhook (BILLING.SUBSCRIPTION.ACTIVATED): Missing custom_id, user_id, package_id or subscription_id. Resource: ' . print_r($resource, true));
            }
            break;

        case 'PAYMENT.SALE.COMPLETED':
            // This event is for a payment related to a subscription (billing_agreement_id)
            $paypal_subscription_id = isset($resource->billing_agreement_id) ? $resource->billing_agreement_id : null;
            if ( $paypal_subscription_id ) {
                $user = cls_get_user_by_paypal_subscription_id( $paypal_subscription_id );
                if ( $user ) {
                    // Fetch subscription details to get the next billing date
                    $access_token = cls_get_paypal_access_token();
                    if ($access_token) {
                        $sub_url = cls_get_paypal_api_base_url() . '/v1/billing/subscriptions/' . $paypal_subscription_id;
                        $sub_response = wp_remote_get( $sub_url, array(
                            'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' )
                        ));
                        if (!is_wp_error($sub_response) && wp_remote_retrieve_response_code($sub_response) === 200) {
                            $sub_data = json_decode(wp_remote_retrieve_body($sub_response));
                            if (isset($sub_data->billing_info->next_billing_time)) {
                                update_user_meta( $user->ID, '_cls_subscription_end_date', strtotime($sub_data->billing_info->next_billing_time) );
                            }
                        } else {
                             error_log('PayPal Webhook (PAYMENT.SALE.COMPLETED): Could not retrieve subscription details to update end date for Sub ID: ' . $paypal_subscription_id);
                        }
                    }
                    update_user_meta( $user->ID, '_cls_subscription_status', 'active' );
                    do_action('cls_subscription_renewed', $user->ID, $paypal_subscription_id);
                    error_log("PayPal Webhook: Processed PAYMENT.SALE.COMPLETED for User ID {$user->ID}, PayPal Sub ID {$paypal_subscription_id}");
                } else {
                    error_log("PayPal Webhook (PAYMENT.SALE.COMPLETED): User not found for PayPal Sub ID {$paypal_subscription_id}");
                }
            } else {
                 error_log("PayPal Webhook (PAYMENT.SALE.COMPLETED): No billing_agreement_id found in resource. ". print_r($resource, true));
            }
            break;

        case 'BILLING.SUBSCRIPTION.CANCELLED':
            $paypal_subscription_id = $resource->id;
             if ( $paypal_subscription_id ) {
                $user = cls_get_user_by_paypal_subscription_id( $paypal_subscription_id );
                if ( $user ) {
                    update_user_meta( $user->ID, '_cls_subscription_status', 'canceled' );
                    if (isset($resource->status_update_time)) { // When the cancellation was processed
                         update_user_meta( $user->ID, '_cls_subscription_end_date', strtotime($resource->status_update_time) );
                    }
                    do_action('cls_subscription_canceled', $user->ID, $paypal_subscription_id);
                    error_log("PayPal Webhook: Processed BILLING.SUBSCRIPTION.CANCELLED for User ID {$user->ID}, PayPal Sub ID {$paypal_subscription_id}");
                } else {
                    error_log("PayPal Webhook (BILLING.SUBSCRIPTION.CANCELLED): User not found for PayPal Sub ID {$paypal_subscription_id}");
                }
            }
            break;

        // TODO: Handle BILLING.SUBSCRIPTION.EXPIRED, BILLING.SUBSCRIPTION.SUSPENDED etc.
        // case 'BILLING.SUBSCRIPTION.SUSPENDED':
        // case 'BILLING.SUBSCRIPTION.EXPIRED':

        default:
            error_log( 'PayPal Webhook: Received unhandled event type: ' . $event_type );
    }

    return new WP_REST_Response( array( 'received' => true ), 200 );
}

?>
