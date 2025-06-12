<?php
/**
 * Admin Settings Page for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds the admin menu item for the plugin settings page.
 */
function cls_add_admin_menu() {
    add_menu_page(
        __( 'Custom Login & Subscriptions Settings', 'custom-login-subscription' ), // Page title
        __( 'Login & Subs', 'custom-login-subscription' ),                       // Menu title
        'manage_options',                                                         // Capability required
        'cls-settings',                                                           // Menu slug
        'cls_render_settings_page_html',                                          // Callback function to render the page
        'dashicons-admin-generic',                                                // Icon
        85                                                                        // Position
    );
}
add_action( 'admin_menu', 'cls_add_admin_menu' );

/**
 * Renders the HTML for the settings page.
 */
function cls_render_settings_page_html() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-login-subscription' ) );
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'cls_options_group' );      // Output nonce, action, and option_page fields for 'cls_options_group'
            do_settings_sections( 'cls-settings' ); // Slug of the page whose sections are to be output
            submit_button( __( 'Save Settings', 'custom-login-subscription' ) );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Registers settings, sections, and fields for the settings page.
 */
function cls_register_settings() {
    // Register the main settings option group and option name
    register_setting(
        'cls_options_group',                // Option group
        'cls_plugin_settings',              // Option name (stores all settings as an array)
        'cls_sanitize_settings_callback'    // Sanitization callback
    );

    // --- Social Login APIs Section ---
    add_settings_section(
        'cls_section_social_logins',
        __( 'Social Login APIs', 'custom-login-subscription' ),
        'cls_section_social_logins_callback', // Callback for section description (optional)
        'cls-settings' // Page slug
    );

    // Field: Google Client ID
    add_settings_field(
        'google_client_id',
        __( 'Google Client ID', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_social_logins',
        array(
            'label_for' => 'google_client_id',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enter your Google Project Client ID.', 'custom-login-subscription'),
        )
    );
    // Field: Google Client Secret
    add_settings_field(
        'google_client_secret',
        __( 'Google Client Secret', 'custom-login-subscription' ),
        'cls_render_password_field', // Use password type for secrets
        'cls-settings',
        'cls_section_social_logins',
        array(
            'label_for' => 'google_client_secret',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enter your Google Project Client Secret.', 'custom-login-subscription'),
        )
    );
    // Field: Facebook App ID
    add_settings_field(
        'facebook_app_id',
        __( 'Facebook App ID', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_social_logins',
        array(
            'label_for' => 'facebook_app_id',
            'option_name' => 'cls_plugin_settings',
        )
    );
    // Field: Facebook App Secret
    add_settings_field(
        'facebook_app_secret',
        __( 'Facebook App Secret', 'custom-login-subscription' ),
        'cls_render_password_field',
        'cls-settings',
        'cls_section_social_logins',
        array(
            'label_for' => 'facebook_app_secret',
            'option_name' => 'cls_plugin_settings',
        )
    );
    // Field: Apple Service ID
    add_settings_field(
        'apple_service_id',
        __( 'Apple Service ID (Client ID)', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_social_logins',
        array('label_for' => 'apple_service_id', 'option_name' => 'cls_plugin_settings')
    );
    // Field: Apple Team ID
    add_settings_field(
        'apple_team_id',
        __( 'Apple Team ID', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_social_logins',
        array('label_for' => 'apple_team_id', 'option_name' => 'cls_plugin_settings')
    );
    // Field: Apple Key ID
    add_settings_field(
        'apple_key_id',
        __( 'Apple Key ID', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_social_logins',
        array('label_for' => 'apple_key_id', 'option_name' => 'cls_plugin_settings')
    );
    // Field: Apple Private Key (.p8 content)
    add_settings_field(
        'apple_private_key',
        __( 'Apple Private Key (.p8 content)', 'custom-login-subscription' ),
        'cls_render_textarea_field',
        'cls-settings',
        'cls_section_social_logins',
        array(
            'label_for' => 'apple_private_key',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Paste the content of your .p8 private key file here.', 'custom-login-subscription'),
            'rows' => 5,
        )
    );

    // --- Payment Gateway APIs Section ---
    add_settings_section(
        'cls_section_payment_gateways',
        __( 'Payment Gateway APIs', 'custom-login-subscription' ),
        'cls_section_payment_gateways_callback',
        'cls-settings'
    );
    // Field: Stripe Publishable Key
    add_settings_field(
        'stripe_publishable_key',
        __( 'Stripe Publishable Key', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_payment_gateways',
        array('label_for' => 'stripe_publishable_key', 'option_name' => 'cls_plugin_settings')
    );
    // Field: Stripe Secret Key
    add_settings_field(
        'stripe_secret_key',
        __( 'Stripe Secret Key', 'custom-login-subscription' ),
        'cls_render_password_field',
        'cls-settings',
        'cls_section_payment_gateways',
        array('label_for' => 'stripe_secret_key', 'option_name' => 'cls_plugin_settings')
    );
    // Field: PayPal Client ID
    add_settings_field(
        'paypal_client_id',
        __( 'PayPal Client ID', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_payment_gateways',
        array('label_for' => 'paypal_client_id', 'option_name' => 'cls_plugin_settings')
    );
    // Field: PayPal Client Secret
    add_settings_field(
        'paypal_client_secret',
        __( 'PayPal Client Secret', 'custom-login-subscription' ),
        'cls_render_password_field',
        'cls-settings',
        'cls_section_payment_gateways',
        array('label_for' => 'paypal_client_secret', 'option_name' => 'cls_plugin_settings')
    );
    // Field: PayPal Mode
    add_settings_field(
        'paypal_mode',
        __( 'PayPal Mode', 'custom-login-subscription' ),
        'cls_render_select_field',
        'cls-settings',
        'cls_section_payment_gateways',
        array(
            'label_for' => 'paypal_mode',
            'option_name' => 'cls_plugin_settings',
            'options' => array(
                'sandbox' => __('Sandbox', 'custom-login-subscription'),
                'live' => __('Live', 'custom-login-subscription'),
            ),
            'description' => __('Select PayPal API mode.', 'custom-login-subscription'),
        )
    );

    // --- Webhook Configuration Section ---
    add_settings_section(
        'cls_section_webhooks',
        __( 'Webhook Configuration', 'custom-login-subscription' ),
        'cls_section_webhooks_callback',
        'cls-settings'
    );
     // Field: Stripe Webhook URL (Display Only)
    add_settings_field(
        'stripe_webhook_url',
        __( 'Stripe Webhook URL', 'custom-login-subscription' ),
        'cls_render_display_text_field',
        'cls-settings',
        'cls_section_webhooks',
        array(
            'value' => get_rest_url(null, 'custom-login-subscription/v1/stripe-webhook'),
            'description' => __('Use this URL in your Stripe dashboard to configure webhooks.', 'custom-login-subscription'),
        )
    );
    // Field: Stripe Webhook Secret
    add_settings_field(
        'stripe_webhook_secret',
        __( 'Stripe Webhook Secret', 'custom-login-subscription' ),
        'cls_render_password_field',
        'cls-settings',
        'cls_section_webhooks',
        array(
            'label_for' => 'stripe_webhook_secret',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enter your Stripe webhook signing secret.', 'custom-login-subscription'),
        )
    );
    // Field: PayPal Webhook URL (Display Only)
    add_settings_field(
        'paypal_webhook_url',
        __( 'PayPal Webhook URL', 'custom-login-subscription' ),
        'cls_render_display_text_field',
        'cls-settings',
        'cls_section_webhooks',
        array(
            'value' => get_rest_url(null, 'custom-login-subscription/v1/paypal-webhook'),
            'description' => __('Use this URL in your PayPal dashboard to configure webhooks.', 'custom-login-subscription'),
        )
    );
    // Field: PayPal Webhook ID
     add_settings_field(
        'paypal_webhook_id',
        __( 'PayPal Webhook ID', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_webhooks',
        array(
            'label_for' => 'paypal_webhook_id',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enter your PayPal Webhook ID (from PayPal developer dashboard).', 'custom-login-subscription'),
        )
    );

    // --- Email Notifications Section ---
    add_settings_section(
        'cls_section_email_notifications',
        __( 'Email Notifications', 'custom-login-subscription' ),
        'cls_email_notifications_section_callback',
        'cls-settings'
    );

    // -- Successful Subscription Email --
    add_settings_field(
        'email_successful_subscription_enable',
        __( 'Successful Subscription Email', 'custom-login-subscription' ),
        'cls_render_checkbox_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_successful_subscription_enable',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enable this email to be sent to users upon successful subscription activation.', 'custom-login-subscription'),
        )
    );
    add_settings_field(
        'email_successful_subscription_subject',
        __( 'Subject', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_successful_subscription_subject',
            'option_name' => 'cls_plugin_settings',
            'default' => __('Your subscription to {package_name} is active!', 'custom-login-subscription'),
            'class' => 'large-text',
        )
    );
    add_settings_field(
        'email_successful_subscription_body',
        __( 'Email Body', 'custom-login-subscription' ),
        'cls_render_wp_editor_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_successful_subscription_body',
            'option_name' => 'cls_plugin_settings',
            'default' => sprintf(
                "%s\n\n%s\n{package_name}\n%s\n{end_date}\n\n%s\n%s",
                __( 'Hi {user_name},', 'custom-login-subscription' ),
                __( 'Your subscription to', 'custom-login-subscription' ),
                __( 'is now active. Your subscription will renew on', 'custom-login-subscription' ),
                __( 'Thanks,', 'custom-login-subscription' ),
                get_bloginfo('name')
            ),
            'description' => __('Available placeholders: {user_name}, {user_email}, {package_name}, {start_date}, {end_date}, {site_name}', 'custom-login-subscription'),
        )
    );

    // -- Subscription Expiration Warning Email --
    add_settings_field(
        'email_expiration_warning_enable',
        __( 'Subscription Expiration Warning Email', 'custom-login-subscription' ),
        'cls_render_checkbox_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_expiration_warning_enable',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enable this email to warn users before their subscription expires/renews.', 'custom-login-subscription'),
        )
    );
    add_settings_field( // Note: Add a field for "Days before expiration" if needed. For now, just the email template.
        'email_expiration_warning_subject',
        __( 'Subject', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_expiration_warning_subject',
            'option_name' => 'cls_plugin_settings',
            'default' => __('Your Subscription is Expiring Soon', 'custom-login-subscription'),
            'class' => 'large-text',
        )
    );
    add_settings_field(
        'email_expiration_warning_body',
        __( 'Email Body', 'custom-login-subscription' ),
        'cls_render_wp_editor_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_expiration_warning_body',
            'option_name' => 'cls_plugin_settings',
            'default' => sprintf(
                "%s\n\n%s {package_name} %s {end_date}.\n%s\n\n%s\n%s",
                __( 'Hi {user_name},', 'custom-login-subscription' ),
                __( 'This is a reminder that your subscription to', 'custom-login-subscription' ),
                __( 'is scheduled to renew/expire on', 'custom-login-subscription' ),
                __( 'Please ensure your payment method is up to date if you wish to continue your subscription.', 'custom-login-subscription' ),
                __( 'Thanks,', 'custom-login-subscription' ),
                get_bloginfo('name')
            ),
            'description' => __('Available placeholders: {user_name}, {user_email}, {package_name}, {start_date}, {end_date}, {site_name}', 'custom-login-subscription'),
        )
    );

    // -- Subscription Canceled Email --
    add_settings_field(
        'email_subscription_canceled_enable',
        __( 'Subscription Canceled Email', 'custom-login-subscription' ),
        'cls_render_checkbox_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_subscription_canceled_enable',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enable this email to be sent when a user\'s subscription is canceled.', 'custom-login-subscription'),
        )
    );
    add_settings_field(
        'email_subscription_canceled_subject',
        __( 'Subject', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_subscription_canceled_subject',
            'option_name' => 'cls_plugin_settings',
            'default' => __('Your Subscription Has Been Canceled', 'custom-login-subscription'),
            'class' => 'large-text',
        )
    );
    add_settings_field(
        'email_subscription_canceled_body',
        __( 'Email Body', 'custom-login-subscription' ),
        'cls_render_wp_editor_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_subscription_canceled_body',
            'option_name' => 'cls_plugin_settings',
            'default' => sprintf(
                "%s\n\n%s {package_name} %s.\n%s {end_date}.\n\n%s\n%s",
                __( 'Hi {user_name},', 'custom-login-subscription' ),
                __( 'This email confirms that your subscription to', 'custom-login-subscription' ),
                __( 'has been canceled', 'custom-login-subscription' ),
                __( 'Your access will continue until', 'custom-login-subscription' ),
                __( 'Thanks,', 'custom-login-subscription' ),
                get_bloginfo('name')
            ),
            'description' => __('Available placeholders: {user_name}, {user_email}, {package_name}, {end_date}, {site_name}', 'custom-login-subscription'),
        )
    );

    // -- Payment Failed Email --
    add_settings_field(
        'email_payment_failed_enable',
        __( 'Subscription Payment Failed Email', 'custom-login-subscription' ),
        'cls_render_checkbox_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_payment_failed_enable',
            'option_name' => 'cls_plugin_settings',
            'description' => __('Enable this email when a recurring payment fails.', 'custom-login-subscription'),
        )
    );
    add_settings_field(
        'email_payment_failed_subject',
        __( 'Subject', 'custom-login-subscription' ),
        'cls_render_text_input_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_payment_failed_subject',
            'option_name' => 'cls_plugin_settings',
            'default' => __('Action Required: Subscription Payment Failed', 'custom-login-subscription'),
            'class' => 'large-text',
        )
    );
    add_settings_field(
        'email_payment_failed_body',
        __( 'Email Body', 'custom-login-subscription' ),
        'cls_render_wp_editor_field',
        'cls-settings',
        'cls_section_email_notifications',
        array(
            'label_for' => 'email_payment_failed_body',
            'option_name' => 'cls_plugin_settings',
            'default' => sprintf(
                "%s\n\n%s {package_name}.\n%s\n\n%s\n%s",
                __( 'Hi {user_name},', 'custom-login-subscription' ),
                __( 'We were unable to process the payment for your subscription to', 'custom-login-subscription' ),
                __( 'Please update your payment method to maintain access. You can update it here: {payment_update_link}', 'custom-login-subscription' ), // {payment_update_link} would be a future addition
                __( 'Thanks,', 'custom-login-subscription' ),
                get_bloginfo('name')
            ),
            'description' => __('Available placeholders: {user_name}, {user_email}, {package_name}, {payment_update_link}, {site_name}', 'custom-login-subscription'),
        )
    );

}
add_action( 'admin_init', 'cls_register_settings' );


/**
 * Callback functions for rendering section descriptions (optional).
 */
function cls_section_social_logins_callback() {
    echo '<p>' . esc_html__( 'Configure API credentials for various social login providers.', 'custom-login-subscription' ) . '</p>';
}
function cls_section_payment_gateways_callback() {
    echo '<p>' . esc_html__( 'Configure API credentials for payment gateways like Stripe and PayPal.', 'custom-login-subscription' ) . '</p>';
}
function cls_section_webhooks_callback() {
    echo '<p>' . esc_html__( 'Webhook details for receiving real-time notifications from payment gateways.', 'custom-login-subscription' ) . '</p>';
    echo '<p><em>' . esc_html__('Ensure your site is publicly accessible for webhooks to function correctly.', 'custom-login-subscription') . '</em></p>';
}
function cls_email_notifications_section_callback() {
    echo '<p>' . esc_html__( 'Customize emails sent to users for various subscription events.', 'custom-login-subscription' ) . '</p>';
}


/**
 * Sanitization callback for the settings.
 * @param array $input The input array of settings.
 * @return array The sanitized array of settings.
 */
function cls_sanitize_settings_callback( $input ) {
    $output = get_option('cls_plugin_settings'); // Get existing options to preserve any not in $input

    if ( !is_array($output) ) $output = array();

    if ( is_array( $input ) ) {
        // Sanitize text fields
        $text_fields = array(
            'google_client_id', 'google_client_secret',
            'facebook_app_id', 'facebook_app_secret',
            'apple_service_id', 'apple_team_id', 'apple_key_id',
            'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret',
            'paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id'
        );
        foreach ( $text_fields as $field ) {
            if ( isset( $input[$field] ) ) {
                $output[$field] = sanitize_text_field( $input[$field] );
            }
        }

        // Sanitize textarea (Apple Private Key)
        if ( isset( $input['apple_private_key'] ) ) {
            // Basic sanitization for textarea, allowing multi-line.
            // For keys, often just ensuring it's a string is enough, as specific characters are needed.
            // `wp_kses_post` might be too aggressive. `sanitize_textarea_field` is good.
            $output['apple_private_key'] = sanitize_textarea_field( $input['apple_private_key'] );
        }

        // Sanitize select (PayPal Mode)
        if ( isset( $input['paypal_mode'] ) && array_key_exists( $input['paypal_mode'], array('sandbox'=>'', 'live'=>'') ) ) {
            $output['paypal_mode'] = $input['paypal_mode'];
        }

        // Sanitize Email Notification Settings
        $email_types = array(
            'successful_subscription',
            'expiration_warning',
            'subscription_canceled',
            'payment_failed'
        );

        foreach ($email_types as $type) {
            $enable_key = "email_{$type}_enable";
            $subject_key = "email_{$type}_subject";
            $body_key = "email_{$type}_body";

            // Checkbox
            $output[$enable_key] = isset( $input[$enable_key] ) && $input[$enable_key] == '1' ? '1' : '0';

            // Subject
            if ( isset( $input[$subject_key] ) ) {
                $output[$subject_key] = sanitize_text_field( $input[$subject_key] );
            }
            // Body (wp_editor content)
            if ( isset( $input[$body_key] ) ) {
                $output[$body_key] = wp_kses_post( $input[$body_key] );
            }
        }
    }
    return $output;
}

/**
 * Generic callback function to render a text input field.
 */
function cls_render_text_input_field( $args ) {
    $option_name = $args['option_name'];
    $field_id = $args['label_for'];
    $options = get_option( $option_name );
    $value = isset( $options[$field_id] ) ? $options[$field_id] : (isset($args['default']) ? $args['default'] : '');
    $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';
    $class = isset($args['class']) ? esc_attr($args['class']) : 'regular-text';

    printf(
        '<input type="text" id="%s" name="%s[%s]" value="%s" class="%s" />%s',
        esc_attr( $field_id ),
        esc_attr( $option_name ),
        esc_attr( $field_id ),
        esc_attr( $value ),
        esc_attr( $class ),
        $description // Already escaped if it exists
    );
}

/**
 * Generic callback function to render a password input field.
 */
function cls_render_password_field( $args ) {
    $option_name = $args['option_name'];
    $field_id = $args['label_for'];
    $options = get_option( $option_name );
    $value = isset( $options[$field_id] ) ? $options[$field_id] : ''; // Secrets should not have defaults displayed
    $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';
    $class = isset($args['class']) ? esc_attr($args['class']) : 'regular-text';

    printf(
        '<input type="password" id="%s" name="%s[%s]" value="%s" class="%s" />%s',
        esc_attr( $field_id ),
        esc_attr( $option_name ),
        esc_attr( $field_id ),
        esc_attr( $value ), // Value is a secret, still escape it for attribute context
        esc_attr( $class ),
        $description // Already escaped
    );
}

/**
 * Generic callback function to render a textarea field.
 */
function cls_render_textarea_field( $args ) {
    $option_name = $args['option_name'];
    $field_id = $args['label_for'];
    $options = get_option( $option_name );
    $value = isset( $options[$field_id] ) ? $options[$field_id] : (isset($args['default']) ? $args['default'] : '');
    $rows = isset($args['rows']) ? intval($args['rows']) : 5;
    $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';
    $class = isset($args['class']) ? esc_attr($args['class']) : 'large-text code';


    printf(
        '<textarea id="%s" name="%s[%s]" rows="%d" class="%s">%s</textarea>%s',
        esc_attr( $field_id ),
        esc_attr( $option_name ),
        esc_attr( $field_id ),
        $rows,
        esc_attr( $class ),
        esc_textarea( $value ), // Escapes text for textarea
        $description // Already escaped
    );
}

/**
 * Generic callback function to render a select field.
 */
function cls_render_select_field( $args ) {
    $option_name = $args['option_name'];
    $field_id = $args['label_for'];
    $options_array = $args['options'];
    $options = get_option( $option_name );
    $value = isset( $options[$field_id] ) ? $options[$field_id] : (isset($args['default']) ? $args['default'] : '');
    $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';

    echo "<select id='" . esc_attr( $field_id ) . "' name='" . esc_attr( $option_name . '[' . $field_id . ']' ) . "'>";
    foreach ( $options_array as $val => $label ) {
        echo "<option value='" . esc_attr( $val ) . "'" . selected( $value, $val, false ) . ">" . esc_html( $label ) . "</option>";
    }
    echo "</select>";
    echo $description; // Already escaped
}

/**
 * Generic callback function to render display-only text.
 */
function cls_render_display_text_field( $args ) {
    $value = isset($args['value']) ? $args['value'] : ''; // This value is pre-determined, not from options
    $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';
    echo '<code>' . esc_html( $value ) . '</code>'; // Value is a URL or similar, esc_html is fine.
    echo $description; // Already escaped
}

/**
 * Renders a checkbox field.
 */
function cls_render_checkbox_field( $args ) {
    $option_name = $args['option_name'];
    $field_id = $args['label_for']; // Use label_for as the unique part of the name/id
    $options = get_option( $option_name );
    $checked = isset( $options[$field_id] ) && $options[$field_id] === '1' ? 'checked="checked"' : ''; // Strict comparison
    $description = isset($args['description']) ? '<p class="description">' . esc_html($args['description']) . '</p>' : '';

    echo "<label for='" . esc_attr( $field_id ) . "'>";
    echo "<input type='checkbox' id='" . esc_attr( $field_id ) . "' name='" . esc_attr( $option_name . '[' . $field_id . ']' ) . "' value='1' " . $checked . " />";
    // If you want text directly next to checkbox (not as main field title)
    // if (isset($args['checkbox_label'])) {
    //     echo " " . esc_html($args['checkbox_label']);
    // }
    echo "</label>";
    echo $description; // Already escaped
}

/**
 * Renders a wp_editor field.
 */
function cls_render_wp_editor_field( $args ) {
    $option_name = $args['option_name'];
    $field_id = $args['label_for']; // Use label_for as the unique part of the name/id
    $options = get_option( $option_name );

    $default_value = isset($args['default']) ? $args['default'] : '';
    $value = isset( $options[$field_id] ) ? $options[$field_id] : $default_value; // Default applied here

    $editor_settings = array(
        'textarea_name' => esc_attr( $option_name . '[' . $field_id . ']' ),
        'textarea_rows' => isset($args['rows']) ? intval($args['rows']) : 10,
        'media_buttons' => false, // Disable media buttons for simple emails
        'teeny'         => true,  // Use a simpler editor interface
        'quicktags'     => true,
    );

    // Outputting wp_editor which handles its own internal escaping.
    wp_editor( $value, esc_attr($field_id), $editor_settings );

    if (isset($args['description'])) {
        // Using wp_kses_post for description as it might contain placeholders like <code>{tag}</code>
        echo '<p class="description">' . wp_kses( $args['description'], array( 'code' => array(), 'strong' => array(), 'em' => array(), 'a' => array('href'=>true) ) ) . '</p>';
    }
}

?>
