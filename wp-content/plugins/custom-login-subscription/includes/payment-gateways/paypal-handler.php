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
    $mode = cls_get_setting('paypal_mode', 'sandbox');
    if ( $mode === 'live' ) {
        return 'https://api.paypal.com';
    }
    return 'https://api.sandbox.paypal.com';
}

/**
 * Get PayPal Access Token.
 * @return string|false Access token or false on failure.
 */
function cls_get_paypal_access_token() {
    $client_id = cls_get_setting('paypal_client_id');
    $client_secret = cls_get_setting('paypal_client_secret');

    if ( empty($client_id) || empty($client_secret) ) {
        error_log('PayPal Error: Client ID or Secret not configured in settings.');
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
        wp_send_json_error( array( 'message' => esc_html__( 'You must be logged in to subscribe.', 'custom-login-subscription' ) ) );
        return;
    }

    $paypal_plan_id = isset( $_POST['paypal_plan_id'] ) ? sanitize_text_field( $_POST['paypal_plan_id'] ) : null;
    $package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : null;

    if ( ! $paypal_plan_id || ! $package_id ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Invalid PayPal plan or package information.', 'custom-login-subscription' ) ) );
        return;
    }

    $access_token = cls_get_paypal_access_token();
    if ( ! $access_token ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Could not authenticate with PayPal. Please try again later.', 'custom-login-subscription' ) ) );
        return;
    }

    $current_user = wp_get_current_user();
    $api_url = cls_get_paypal_api_base_url() . '/v1/billing/subscriptions';

    $start_time = gmdate( "Y-m-d\TH:i:s\Z", time() + (5 * 60) );

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
                                    ), home_url('/paypal-subscription-success/') ),
            'cancel_url'          => home_url('/paypal-subscription-canceled/'),
        ),
        'custom_id' => $current_user->ID . '|' . $package_id,
    );

    $response = wp_remote_post( $api_url, array(
        'method'  => 'POST',
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ),
        'body'    => json_encode( $payload ),
        'timeout' => 60,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log('PayPal Subscription API Error: ' . $response->get_error_message());
        wp_send_json_error( array( 'message' => sprintf(esc_html__('PayPal API Error: %s', 'custom-login-subscription'), esc_html($response->get_error_message())) ) );
        return;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $subscription_data = json_decode( $response_body, true );

    if ( $response_code === 200 || $response_code === 201 ) {
        if ( isset( $subscription_data['links'] ) ) {
            foreach ( $subscription_data['links'] as $link ) {
                if ( $link['rel'] === 'approve' || $link['rel'] === 'payer-action' ) {
                    wp_send_json_success( array( 'approve_url' => $link['href'], 'paypal_sub_id' => $subscription_data['id'] ) );
                    return;
                }
            }
        }
        error_log('PayPal Subscription: Approve link not found in response. Data: ' . $response_body);
        wp_send_json_error( array( 'message' => sprintf(esc_html__( 'Could not retrieve PayPal approval link. Response: %s', 'custom-login-subscription' ), esc_html($response_body) ) ));

    } else {
        error_log('PayPal Subscription API Error: Failed to create subscription. Code: ' . $response_code . ' Body: ' . $response_body);
        $user_facing_error_message = esc_html__( 'Failed to create PayPal subscription.', 'custom-login-subscription' );
        if(isset($subscription_data['details'][0]['description'])){
             $user_facing_error_message .= ' ' . sprintf(esc_html__('Details: %s', 'custom-login-subscription'), esc_html($subscription_data['details'][0]['description']));
        } else if (isset($subscription_data['message'])) {
             $user_facing_error_message .= ' ' . sprintf(esc_html__('Details: %s', 'custom-login-subscription'), esc_html($subscription_data['message']));
        }
        wp_send_json_error( array( 'message' => $user_facing_error_message, 'raw_response' => $response_body ) ); // raw_response for debugging, not for user display
    }
}
add_action( 'wp_ajax_cls_create_paypal_subscription', 'cls_create_paypal_subscription_ajax_handler' );


/**
 * Helper function to get a WordPress user by PayPal Subscription ID.
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
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'cls_register_paypal_webhook_endpoint' );


/**
 * Verifies a PayPal webhook signature.
 */
function cls_verify_paypal_webhook_signature( WP_REST_Request $request, $raw_body ) {
    $webhook_id = cls_get_setting('paypal_webhook_id');

    if ( empty($webhook_id) ) {
        error_log('PayPal Webhook Error: Missing Webhook ID for verification in settings.');
        return false;
    }

    $access_token = cls_get_paypal_access_token();
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
        'webhook_event'     => json_decode($raw_body)
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
 */
function cls_handle_paypal_webhook( WP_REST_Request $request ) {
    $raw_body = $request->get_body();
    $event_body = json_decode( $raw_body );

    if ( ! cls_verify_paypal_webhook_signature( $request, $raw_body ) ) {
        error_log('PayPal Webhook Error: Signature verification failed.');
        return new WP_REST_Response( array( 'error' => esc_html__('Signature verification failed', 'custom-login-subscription') ), 403 );
    }

    $event_type = isset( $event_body->event_type ) ? $event_body->event_type : null;
    $resource = isset( $event_body->resource ) ? $event_body->resource : null;

    if ( ! $event_type || ! $resource ) {
        error_log('PayPal Webhook Error: Invalid event structure. Event Type or Resource missing.');
        return new WP_REST_Response( array( 'error' => esc_html__('Invalid event structure', 'custom-login-subscription') ), 400 );
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

                $user_info = get_userdata($wp_user_id);
                if ($user_info) {
                    $context_data = [
                        'user_id'         => $wp_user_id,
                        'package_id'      => $wp_package_id,
                        'subscription_id' => $paypal_subscription_id,
                        'start_date'      => isset($resource->start_time) ? strtotime($resource->start_time) : time(),
                        'end_date'        => isset($resource->billing_info->next_billing_time) ? strtotime($resource->billing_info->next_billing_time) : null,
                    ];
                    cls_send_notification_email($user_info->user_email, 'successful_subscription', $context_data);
                }

                do_action('cls_subscription_activated', $wp_user_id, $wp_package_id, $paypal_subscription_id);
                error_log("PayPal Webhook: Processed BILLING.SUBSCRIPTION.ACTIVATED for User ID {$wp_user_id}, PayPal Sub ID {$paypal_subscription_id}");
            } else {
                error_log('PayPal Webhook (BILLING.SUBSCRIPTION.ACTIVATED): Missing custom_id, user_id, package_id or subscription_id. Resource: ' . print_r($resource, true));
            }
            break;

        case 'PAYMENT.SALE.COMPLETED':
            $paypal_subscription_id = isset($resource->billing_agreement_id) ? $resource->billing_agreement_id : null;
            if ( $paypal_subscription_id ) {
                $user = cls_get_user_by_paypal_subscription_id( $paypal_subscription_id );
                if ( $user ) {
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
                    if (isset($resource->status_update_time)) {
                         update_user_meta( $user->ID, '_cls_subscription_end_date', strtotime($resource->status_update_time) );
                    }

                    $context_data = [
                        'user_id'         => $user->ID,
                        'package_id'      => get_user_meta($user->ID, '_cls_subscription_package_id', true),
                        'subscription_id' => $paypal_subscription_id,
                        'end_date'        => get_user_meta($user->ID, '_cls_subscription_end_date', true),
                    ];
                    cls_send_notification_email($user->user_email, 'subscription_canceled', $context_data);

                    do_action('cls_subscription_canceled', $user->ID, $paypal_subscription_id);
                    error_log("PayPal Webhook: Processed BILLING.SUBSCRIPTION.CANCELLED for User ID {$user->ID}, PayPal Sub ID {$paypal_subscription_id}");
                } else {
                    error_log("PayPal Webhook (BILLING.SUBSCRIPTION.CANCELLED): User not found for PayPal Sub ID {$paypal_subscription_id}");
                }
            }
            break;

        default:
            error_log( 'PayPal Webhook: Received unhandled event type: ' . $event_type );
    }

    return new WP_REST_Response( array( 'received' => true ), 200 );
}

?>
