<?php
/**
 * Email Handling for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sends a notification email based on type and context.
 *
 * @param string $to_email The recipient's email address.
 * @param string $email_type The type of email to send (e.g., 'successful_subscription').
 * @param array  $context_data Associative array of data for placeholder replacement.
 * @return bool True if email sent successfully or was disabled, false on failure.
 */
function cls_send_notification_email( $to_email, $email_type, $context_data = array() ) {
    if ( ! function_exists('cls_get_setting') ) {
        error_log('CLS Email Error: cls_get_setting() function not found. Ensure main plugin file is loaded.');
        return false;
    }

    $settings = get_option( 'cls_plugin_settings', array() );

    $enable_key = "email_{$email_type}_enable";
    $subject_key = "email_{$email_type}_subject";
    $body_key = "email_{$email_type}_body";

    // Check if this email type is enabled
    if ( ! isset( $settings[$enable_key] ) || $settings[$enable_key] != '1' ) {
        // error_log("CLS Email Info: Email type '{$email_type}' is disabled.");
        return true; // Return true because it's not an error, just disabled.
    }

    // Get subject and body from settings, or use defaults if necessary
    // Defaults are primarily set in the settings page definition, but good to have fallbacks.
    $default_subjects = array(
        'successful_subscription' => __('Your subscription to {package_name} is active!', 'custom-login-subscription'),
        'expiration_warning'      => __('Your Subscription to {package_name} is Expiring Soon', 'custom-login-subscription'),
        'subscription_canceled'   => __('Your Subscription to {package_name} Has Been Canceled', 'custom-login-subscription'),
        'payment_failed'          => __('Action Required: Subscription Payment Failed for {package_name}', 'custom-login-subscription'),
    );
    $default_bodies = array( // Basic plain text defaults
        'successful_subscription' => "Hi {user_name},\n\nYour subscription to {package_name} is now active. Your subscription will renew on {end_date}.\n\nThanks,\n{site_name}",
        'expiration_warning'      => "Hi {user_name},\n\nThis is a reminder that your subscription to {package_name} is scheduled to renew/expire on {end_date}.\nPlease ensure your payment method is up to date if you wish to continue your subscription.\n\nThanks,\n{site_name}",
        'subscription_canceled'   => "Hi {user_name},\n\nThis email confirms that your subscription to {package_name} has been canceled.\nYour access will continue until {end_date}.\n\nThanks,\n{site_name}",
        'payment_failed'          => "Hi {user_name},\n\nWe were unable to process the payment for your subscription to {package_name}.\nPlease update your payment method to maintain access. You can update it here: {my_account_url}\n\nThanks,\n{site_name}",
    );

    $subject = isset( $settings[$subject_key] ) && !empty( $settings[$subject_key] ) ? $settings[$subject_key] : $default_subjects[$email_type];
    $body    = isset( $settings[$body_key] ) && !empty( $settings[$body_key] ) ? $settings[$body_key] : $default_bodies[$email_type];

    // --- Placeholder Replacement ---
    $user = null;
    $package_post = null;

    if ( isset( $context_data['user_id'] ) ) {
        $user = get_userdata( $context_data['user_id'] );
    }
    if ( isset( $context_data['package_id'] ) ) {
        $package_post = get_post( $context_data['package_id'] );
    }

    $placeholders = array(
        '{site_name}'        => get_bloginfo('name'),
        '{site_url}'         => home_url(),
        '{login_url}'        => wp_login_url(),
        '{my_account_url}'   => home_url('/my-account'), // Placeholder, replace with actual page if created
        '{user_name}'        => $user ? ($user->display_name ? $user->display_name : $user->user_login) : '',
        '{user_email}'       => $user ? $user->user_email : '',
        '{first_name}'       => $user ? $user->first_name : '',
        '{last_name}'        => $user ? $user->last_name : '',
        '{package_name}'     => $package_post ? $package_post->post_title : '',
        '{package_description}' => $package_post ? wp_trim_words($package_post->post_content, 50, '...') : '',
        '{start_date}'       => isset($context_data['start_date']) ? date_i18n(get_option('date_format'), $context_data['start_date']) : '',
        '{end_date}'         => isset($context_data['end_date']) ? date_i18n(get_option('date_format'), $context_data['end_date']) : '',
        '{renewal_date}'     => isset($context_data['end_date']) ? date_i18n(get_option('date_format'), $context_data['end_date']) : '', // Alias for end_date
        '{subscription_id}'  => isset($context_data['subscription_id']) ? $context_data['subscription_id'] : '',
        // Example for a payment update link (would need actual implementation)
        '{payment_update_link}' => home_url('/my-account/update-payment-method'), // Placeholder
    );

    $processed_subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
    // For the body, since it's HTML from wp_editor, we also need nl2br if it's plain text from default
    $processed_body    = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

    // If the body doesn't seem to contain HTML tags (e.g., from a default), add basic line breaks.
    if (strip_tags($processed_body) === $processed_body) {
        $processed_body = nl2br($processed_body);
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');

    // From address (can be made a setting later)
    $admin_email = get_option('admin_email');
    $from_name = get_bloginfo('name');
    $headers[] = "From: {$from_name} <{$admin_email}>";


    if ( wp_mail( $to_email, $processed_subject, $processed_body, $headers ) ) {
        error_log("CLS Email Sent: Type '{$email_type}' to {$to_email}. Subject: {$processed_subject}");
        return true;
    } else {
        error_log("CLS Email Error: Failed to send '{$email_type}' to {$to_email}. Subject: {$processed_subject}");
        return false;
    }
}

/**
 * Sends subscription expiration warning emails.
 * This function is hooked to a daily cron event.
 */
function cls_send_expiration_warnings() {
    if ( ! function_exists('cls_get_setting') ) {
        error_log('CLS Expiration Warning Error: cls_get_setting() function not found.');
        return;
    }

    // Check if the expiration warning email type is enabled globally
    $settings = get_option( 'cls_plugin_settings', array() );
    if ( ! isset( $settings['email_expiration_warning_enable'] ) || $settings['email_expiration_warning_enable'] != '1' ) {
        // error_log('CLS Expiration Warning Info: Email type "expiration_warning" is disabled globally.');
        return;
    }

    // For now, hardcode warning period to 7 days. This could be a setting later.
    $warning_period_days = apply_filters('cls_expiration_warning_period_days', 7);
    $warning_threshold_timestamp_start = strtotime( "+{$warning_period_days} days midnight" ); // Midnight at the start of the 7th day
    $warning_threshold_timestamp_end = strtotime( "+{$warning_period_days} days 23:59:59" ); // End of the 7th day

    // More precise: warning if end_date is ON the 7th day from now.
    // Example: Today is Jan 1. We want to warn for subscriptions ending on Jan 8.
    // End date > now AND End date < now + (warning_days + 1)

    $today = current_time('timestamp');
    $warning_date_future_limit = strtotime("+$warning_period_days days", $today);


    $args = array(
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'     => '_cls_subscription_status',
                'value'   => 'active', // Only for active subscriptions
                'compare' => '=',
            ),
            array(
                'key'     => '_cls_subscription_end_date',
                'value'   => array( $today, $warning_date_future_limit ), // end date is between today and X days from now
                'type'    => 'NUMERIC',
                'compare' => 'BETWEEN',
            ),
        ),
        'fields' => 'all_with_meta', // Get all user data and meta
    );

    $users_nearing_expiration = get_users( $args );

    if ( empty( $users_nearing_expiration ) ) {
        error_log("CLS Expiration Warning: No users found with subscriptions nearing expiration within {$warning_period_days} days.");
        return;
    }

    error_log("CLS Expiration Warning: Found " . count($users_nearing_expiration) . " users nearing subscription expiration.");

    foreach ( $users_nearing_expiration as $user ) {
        $user_id = $user->ID;
        $package_id = get_user_meta( $user_id, '_cls_subscription_package_id', true );
        $subscription_id_stripe = get_user_meta( $user_id, '_cls_stripe_subscription_id', true );
        $subscription_id_paypal = get_user_meta( $user_id, '_cls_paypal_subscription_id', true );
        $subscription_id = !empty($subscription_id_stripe) ? $subscription_id_stripe : $subscription_id_paypal;

        $context_data = array(
            'user_id'         => $user_id,
            'package_id'      => $package_id,
            'subscription_id' => $subscription_id,
            'start_date'      => get_user_meta( $user_id, '_cls_subscription_start_date', true ),
            'end_date'        => get_user_meta( $user_id, '_cls_subscription_end_date', true ),
        );

        // Check if an email for this user and this specific end_date (representing this billing cycle) has been sent
        // This is a simple way to prevent sending multiple warnings for the same period if cron runs more often or there are overlaps.
        $warning_sent_meta_key = '_cls_expiration_warning_sent_' . $context_data['end_date'];
        if (get_user_meta($user_id, $warning_sent_meta_key, true)) {
            error_log("CLS Expiration Warning: Warning email already sent for user {$user_id} for period ending " . date('Y-m-d', $context_data['end_date']));
            continue; // Skip if already sent for this specific end date
        }

        cls_send_notification_email( $user->user_email, 'expiration_warning', $context_data );

        // Mark that warning has been sent for this period to avoid duplicate emails
        update_user_meta($user_id, $warning_sent_meta_key, time());
    }
}
add_action( 'cls_daily_expiration_warnings_event', 'cls_send_expiration_warnings' );

?>
