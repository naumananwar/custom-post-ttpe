<?php
/**
 * Stripe Payment Gateway Handler for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the AJAX request to create a Stripe Checkout session.
 */
function cls_create_stripe_checkout_session_ajax_handler() {
    // Verify nonce
    check_ajax_referer( 'cls_stripe_checkout_nonce', 'nonce' );

    // Check if Stripe keys are set
    if ( !defined('CLS_STRIPE_SECRET_KEY') || CLS_STRIPE_SECRET_KEY === 'YOUR_STRIPE_SECRET_KEY' ||
         !defined('CLS_STRIPE_PUBLISHABLE_KEY') || CLS_STRIPE_PUBLISHABLE_KEY === 'YOUR_STRIPE_PUBLISHABLE_KEY') {
        wp_send_json_error( array( 'message' => __( 'Stripe is not configured correctly. Please check API keys.', 'custom-login-subscription' ) ) );
        return;
    }

    // Check if Stripe PHP SDK is loaded
    if ( !class_exists('\Stripe\Stripe') ) {
        wp_send_json_error( array( 'message' => __( 'Stripe PHP library not found.', 'custom-login-subscription' ) ) );
        return;
    }

    $stripe_price_id = isset( $_POST['stripe_price_id'] ) ? sanitize_text_field( $_POST['stripe_price_id'] ) : null;
    $package_id = isset( $_POST['package_id'] ) ? intval( $_POST['package_id'] ) : null;

    if ( ! $stripe_price_id || ! $package_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid package information provided.', 'custom-login-subscription' ) ) );
        return;
    }

    // Ensure user is logged in (typically subscriptions require a user account)
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to subscribe.', 'custom-login-subscription' ) ) );
        return;
    }

    \Stripe\Stripe::setApiKey( CLS_STRIPE_SECRET_KEY );

    $current_user = wp_get_current_user();
    $success_url = home_url('/subscription-success?session_id={CHECKOUT_SESSION_ID}&package_id=' . $package_id); // Add package_id for reference
    $cancel_url = home_url('/subscription-canceled'); // Consider adding package_id or other context if needed

    // Check if user already has a Stripe Customer ID
    $stripe_customer_id = get_user_meta( $current_user->ID, '_stripe_customer_id', true );

    $checkout_session_params = [
        'payment_method_types' => ['card'],
        'line_items'           => [[
            'price'    => $stripe_price_id,
            'quantity' => 1,
        ]],
        'mode'                 => 'subscription',
        'success_url'          => $success_url,
        'cancel_url'           => $cancel_url,
        // 'client_reference_id'  => $current_user->ID . '_' . $package_id, // Useful for webhooks
        'metadata'             => [ // Add metadata to the Stripe Checkout Session
            'wp_user_id' => $current_user->ID,
            'wp_package_id' => $package_id,
        ]
    ];

    if ($stripe_customer_id) {
        $checkout_session_params['customer'] = $stripe_customer_id;
    } else {
        // If no Stripe customer ID, prefill email and create customer later via webhook or success handler
        $checkout_session_params['customer_email'] = $current_user->user_email;
        // Or create customer now:
        // try {
        //     $customer = \Stripe\Customer::create([
        //         'email' => $current_user->user_email,
        //         'name' => $current_user->display_name,
        //         'metadata' => [
        //             'wp_user_id' => $current_user->ID,
        //         ]
        //     ]);
        //     $stripe_customer_id = $customer->id;
        //     update_user_meta($current_user->ID, '_stripe_customer_id', $stripe_customer_id);
        //     $checkout_session_params['customer'] = $stripe_customer_id;
        // } catch (\Stripe\Exception\ApiErrorException $e) {
        //     wp_send_json_error( array( 'message' => 'Error creating Stripe customer: ' . $e->getMessage() ) );
        //     return;
        // }
    }


    try {
        $checkout_session = \Stripe\Checkout\Session::create( $checkout_session_params );
        wp_send_json_success( array( 'sessionId' => $checkout_session->id ) );
    } catch ( \Stripe\Exception\ApiErrorException $e ) {
        error_log("Stripe API Error: " . $e->getMessage());
        wp_send_json_error( array( 'message' => 'Stripe API Error: ' . $e->getMessage() ) );
    } catch ( Exception $e ) {
        error_log("General Error creating Stripe session: " . $e->getMessage());
        wp_send_json_error( array( 'message' => 'Error creating Stripe session: ' . $e->getMessage() ) );
    }
}
// Hook for logged-in users. If you want to allow non-logged-in users, also add 'wp_ajax_nopriv_cls_create_stripe_checkout_session'.
add_action( 'wp_ajax_cls_create_stripe_checkout_session', 'cls_create_stripe_checkout_session_ajax_handler' );


/**
 * Registers the Stripe webhook REST API endpoint.
 */
function cls_register_stripe_webhook_endpoint() {
    register_rest_route( 'custom-login-subscription/v1', '/stripe-webhook', array(
        'methods'  => 'POST',
        'callback' => 'cls_handle_stripe_webhook',
        'permission_callback' => '__return_true', // Open endpoint, security is handled by Stripe signature
    ) );
}
add_action( 'rest_api_init', 'cls_register_stripe_webhook_endpoint' );

/**
 * Helper function to get a WordPress user by Stripe Subscription ID.
 *
 * @param string $stripe_subscription_id The Stripe Subscription ID.
 * @return WP_User|false The user object if found, false otherwise.
 */
function cls_get_user_by_stripe_subscription_id( $stripe_subscription_id ) {
    if ( empty( $stripe_subscription_id ) ) {
        return false;
    }
    $users = get_users( array(
        'meta_key'   => '_cls_stripe_subscription_id',
        'meta_value' => $stripe_subscription_id,
        'number'     => 1,
        'count_total' => false,
    ) );
    return ! empty( $users ) ? $users[0] : false;
}

/**
 * Helper function to get a WordPress user by Stripe Customer ID.
 * (Useful for events that are customer-centric rather than subscription-centric)
 *
 * @param string $stripe_customer_id The Stripe Customer ID.
 * @return WP_User|false The user object if found, false otherwise.
 */
function cls_get_user_by_stripe_customer_id( $stripe_customer_id ) {
    if ( empty( $stripe_customer_id ) ) {
        return false;
    }
    $users = get_users( array(
        'meta_key'   => '_cls_stripe_customer_id', // Primary meta key for customer ID
        'meta_value' => $stripe_customer_id,
        'number'     => 1,
        'count_total' => false,
    ) );
    if ( ! empty( $users ) ) {
        return $users[0];
    }
    // Fallback to the general _stripe_customer_id if used by other processes
    $users = get_users( array(
        'meta_key'   => '_stripe_customer_id',
        'meta_value' => $stripe_customer_id,
        'number'     => 1,
        'count_total' => false,
    ) );
    return ! empty( $users ) ? $users[0] : false;
}


/**
 * Handles incoming Stripe webhooks.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response
 */
function cls_handle_stripe_webhook( WP_REST_Request $request ) {
    $payload = $request->get_body();
    $sig_header = $request->get_header( 'stripe_signature' ); // Stripe sends 'Stripe-Signature'

    // Check if Stripe keys are set
    if ( !defined('CLS_STRIPE_SECRET_KEY') || CLS_STRIPE_SECRET_KEY === 'YOUR_STRIPE_SECRET_KEY' ||
         !defined('CLS_STRIPE_WEBHOOK_SECRET') || CLS_STRIPE_WEBHOOK_SECRET === 'YOUR_STRIPE_WEBHOOK_SECRET') {
        error_log('Stripe Webhook Error: Stripe API keys or webhook secret not configured.');
        return new WP_REST_Response( array( 'error' => 'Stripe not configured on server.' ), 500 );
    }
     // Check if Stripe PHP SDK is loaded
    if ( !class_exists('\Stripe\Stripe') ) {
        error_log('Stripe Webhook Error: Stripe PHP library not found.');
        return new WP_REST_Response( array( 'error' => 'Stripe PHP library not found on server.' ), 500 );
    }

    \Stripe\Stripe::setApiKey( CLS_STRIPE_SECRET_KEY );

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, CLS_STRIPE_WEBHOOK_SECRET
        );
    } catch ( \UnexpectedValueException $e ) {
        error_log( 'Stripe Webhook Error: Invalid payload. ' . $e->getMessage() );
        return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
    } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
        error_log( 'Stripe Webhook Error: Invalid signature. ' . $e->getMessage() );
        return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
    } catch ( Exception $e ) {
        error_log( 'Stripe Webhook Error: Generic error during event construction. ' . $e->getMessage() );
        return new WP_REST_Response( array( 'error' => 'Webhook error: ' . $e->getMessage() ), 400 );
    }

    // Handle the event
    switch ( $event->type ) {
        case 'checkout.session.completed':
            $session = $event->data->object; // contains a \Stripe\Checkout\Session

            $wp_user_id = isset($session->metadata->wp_user_id) ? intval($session->metadata->wp_user_id) : null;
            $wp_package_id = isset($session->metadata->wp_package_id) ? intval($session->metadata->wp_package_id) : null;
            $stripe_customer_id = $session->customer;
            $stripe_subscription_id = $session->subscription;

            if ( $wp_user_id && $wp_package_id && $stripe_customer_id && $stripe_subscription_id ) {
                $user = get_user_by( 'id', $wp_user_id );
                $package = get_post( $wp_package_id );

                if ( !$user || ($package && $package->post_type !== 'subscription_package') ) {
                    error_log("Stripe Webhook (checkout.session.completed): User or Package not found. User ID: {$wp_user_id}, Package ID: {$wp_package_id}");
                    break; // Break switch, will return 200
                }

                try {
                    $stripe_subscription = \Stripe\Subscription::retrieve($stripe_subscription_id);
                    $current_period_end = $stripe_subscription->current_period_end;
                    $start_date = $stripe_subscription->start_date;

                    update_user_meta( $wp_user_id, '_cls_subscription_package_id', $wp_package_id );
                    update_user_meta( $wp_user_id, '_cls_subscription_status', 'active' ); // Stripe status is $stripe_subscription->status
                    update_user_meta( $wp_user_id, '_cls_stripe_customer_id', $stripe_customer_id );
                    update_user_meta( $wp_user_id, '_cls_stripe_subscription_id', $stripe_subscription_id );
                    update_user_meta( $wp_user_id, '_cls_subscription_start_date', $start_date );
                    update_user_meta( $wp_user_id, '_cls_subscription_end_date', $current_period_end );
                    update_user_meta( $wp_user_id, '_cls_payment_gateway', 'stripe' );

                    if ( ! get_user_meta( $wp_user_id, '_stripe_customer_id', true ) ) {
                         update_user_meta( $wp_user_id, '_stripe_customer_id', $stripe_customer_id );
                    }
                    do_action('cls_subscription_activated', $wp_user_id, $wp_package_id, $stripe_subscription_id);
                    error_log("Stripe Webhook: Processed checkout.session.completed for User ID {$wp_user_id}, Sub ID {$stripe_subscription_id}");
                } catch ( \Stripe\Exception\ApiErrorException $e ) {
                    error_log("Stripe API Error (checkout.session.completed): " . $e->getMessage() . " Sub ID: {$stripe_subscription_id}");
                }
            } else {
                error_log('Stripe Webhook (checkout.session.completed): Missing metadata or IDs. Data: ' . print_r($session, true));
            }
            break;

        case 'invoice.payment_succeeded':
            $invoice = $event->data->object;
            $stripe_subscription_id = $invoice->subscription;

            if ($stripe_subscription_id) {
                $user = cls_get_user_by_stripe_subscription_id( $stripe_subscription_id );
                if ( $user ) {
                    try {
                        $stripe_subscription = \Stripe\Subscription::retrieve($stripe_subscription_id);
                        update_user_meta( $user->ID, '_cls_subscription_status', 'active' ); // Stripe status: $stripe_subscription->status
                        update_user_meta( $user->ID, '_cls_subscription_end_date', $stripe_subscription->current_period_end );
                        // If it's the first invoice of a trial, start_date might need to be set if not done by checkout.session.completed
                        if ( $invoice->billing_reason === 'subscription_cycle' || $invoice->billing_reason === 'subscription_create') {
                             // Potentially update package_id if plan changed, though customer.subscription.updated is better
                            do_action('cls_subscription_renewed', $user->ID, $stripe_subscription_id);
                            error_log("Stripe Webhook: Processed invoice.payment_succeeded for User ID {$user->ID}, Sub ID {$stripe_subscription_id}");
                        }
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        error_log("Stripe API Error (invoice.payment_succeeded): " . $e->getMessage() . " Sub ID: {$stripe_subscription_id}");
                    }
                } else {
                     error_log("Stripe Webhook (invoice.payment_succeeded): User not found for Sub ID {$stripe_subscription_id}");
                }
            }
            break;

        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $stripe_subscription_id = $invoice->subscription;
            if ($stripe_subscription_id) {
                $user = cls_get_user_by_stripe_subscription_id( $stripe_subscription_id );
                if ( $user ) {
                    update_user_meta( $user->ID, '_cls_subscription_status', 'past_due' ); // Or 'payment_failed'
                    // Consider sending a notification to the user.
                    do_action('cls_subscription_payment_failed', $user->ID, $stripe_subscription_id);
                    error_log("Stripe Webhook: Processed invoice.payment_failed for User ID {$user->ID}, Sub ID {$stripe_subscription_id}");
                } else {
                    error_log("Stripe Webhook (invoice.payment_failed): User not found for Sub ID {$stripe_subscription_id}");
                }
            }
            break;

        case 'customer.subscription.updated':
            $stripe_subscription = $event->data->object; // This is a Stripe Subscription object
            $user = cls_get_user_by_stripe_subscription_id( $stripe_subscription->id );
            if ( $user ) {
                update_user_meta( $user->ID, '_cls_subscription_status', $stripe_subscription->status ); // e.g. active, past_due, trialing
                update_user_meta( $user->ID, '_cls_subscription_end_date', $stripe_subscription->current_period_end );
                // If plan changed: $stripe_subscription->items->data[0]->price->id can give new price_id
                // This would require mapping Stripe Price ID back to your WP Package ID, which can be complex.
                // For now, we assume package_id does not change or is handled manually.
                // update_user_meta( $user->ID, '_cls_stripe_price_id', $stripe_subscription->items->data[0]->price->id );
                do_action('cls_subscription_updated', $user->ID, $stripe_subscription->id);
                error_log("Stripe Webhook: Processed customer.subscription.updated for User ID {$user->ID}, Sub ID {$stripe_subscription->id}. New status: {$stripe_subscription->status}");
            } else {
                 error_log("Stripe Webhook (customer.subscription.updated): User not found for Sub ID {$stripe_subscription->id}");
            }
            break;

        case 'customer.subscription.deleted': // Occurs when a subscription is canceled
            $stripe_subscription = $event->data->object;
            $user = cls_get_user_by_stripe_subscription_id( $stripe_subscription->id );
            if ( $user ) {
                update_user_meta( $user->ID, '_cls_subscription_status', 'canceled' );
                if ( isset($stripe_subscription->ended_at) && !empty($stripe_subscription->ended_at) ) {
                     update_user_meta( $user->ID, '_cls_subscription_end_date', $stripe_subscription->ended_at );
                } else {
                    // If ended_at is not set, it might be canceled but active until period end.
                    // current_period_end is likely more relevant until true deletion/expiration.
                    // Or use canceled_at if immediate. For simplicity, we mark as 'canceled'.
                    // The status 'canceled' should gate access.
                }
                do_action('cls_subscription_canceled', $user->ID, $stripe_subscription->id);
                error_log("Stripe Webhook: Processed customer.subscription.deleted for User ID {$user->ID}, Sub ID {$stripe_subscription->id}");
            } else {
                error_log("Stripe Webhook (customer.subscription.deleted): User not found for Sub ID {$stripe_subscription->id}");
            }
            break;

        default:
            error_log( 'Stripe Webhook: Received unhandled event type: ' . $event->type );
    }

    return new WP_REST_Response( array( 'received' => true ), 200 );
}

?>
