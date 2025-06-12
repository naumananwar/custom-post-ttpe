<?php
/**
 * User Profile Subscription Display for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Displays subscription details on the user's WordPress admin profile page.
 *
 * @param WP_User $user The current user object.
 */
function cls_display_subscription_details_on_profile( $user ) {
    // Get the user ID from the user object
    $user_id = $user->ID;

    // Retrieve subscription meta data for the user
    $package_id     = get_user_meta( $user_id, '_cls_subscription_package_id', true );
    $status         = get_user_meta( $user_id, '_cls_subscription_status', true );
    $gateway        = get_user_meta( $user_id, '_cls_payment_gateway', true );
    $start_date_ts  = get_user_meta( $user_id, '_cls_subscription_start_date', true );
    $end_date_ts    = get_user_meta( $user_id, '_cls_subscription_end_date', true );

    // Check if there's any subscription status. If not, assume no active subscription.
    if ( empty( $status ) ) {
        ?>
        <h3><?php _e( 'My Subscription', 'custom-login-subscription' ); ?></h3>
        <table class="form-table">
            <tr>
                <td colspan="2"><?php _e( 'You do not have an active subscription.', 'custom-login-subscription' ); ?></td>
            </tr>
        </table>
        <?php
        return;
    }

    // Get package name
    $package_name = ! empty( $package_id ) ? get_the_title( $package_id ) : esc_html__( 'N/A', 'custom-login-subscription' );
    if ( empty($package_name) && !empty($package_id) ) { // If get_the_title returns empty (e.g. post deleted)
        $package_name = sprintf(esc_html__( 'Unknown Package (ID: %s)', 'custom-login-subscription' ), esc_html($package_id));
    }


    // Format dates
    $date_format = get_option( 'date_format', 'F j, Y' ); // Provide a fallback date_format
    $start_date_formatted = ! empty( $start_date_ts ) ? date_i18n( $date_format, (int) $start_date_ts ) : esc_html__( 'N/A', 'custom-login-subscription' );
    $end_date_formatted = ! empty( $end_date_ts ) ? date_i18n( $date_format, (int) $end_date_ts ) : esc_html__( 'N/A', 'custom-login-subscription' );

    $gateway_display = !empty($gateway) ? ucfirst($gateway) : esc_html__('N/A', 'custom-login-subscription');
    $status_display = !empty($status) ? ucfirst($status) : esc_html__('N/A', 'custom-login-subscription');


    ?>
    <h3><?php esc_html_e( 'My Subscription Details', 'custom-login-subscription' ); ?></h3>
    <table class="form-table" id="cls-subscription-details">
        <tbody>
            <tr>
                <th><label><?php esc_html_e( 'Package', 'custom-login-subscription' ); ?></label></th>
                <td><?php echo esc_html( $package_name ); ?></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Status', 'custom-login-subscription' ); ?></label></th>
                <td><?php echo esc_html( $status_display ); ?></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Payment Gateway', 'custom-login-subscription' ); ?></label></th>
                <td><?php echo esc_html( $gateway_display ); ?></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Start Date', 'custom-login-subscription' ); ?></label></th>
                <td><?php echo esc_html( $start_date_formatted ); ?></td>
            </tr>
            <tr>
                <th>
                    <label>
                        <?php
                        if ($status === 'active' || $status === 'past_due' || $status === 'trialing') { // Use strict comparison if status values are well-defined
                            esc_html_e( 'Renews On', 'custom-login-subscription' );
                        } else {
                            esc_html_e( 'Expires/Expired On', 'custom-login-subscription' );
                        }
                        ?>
                    </label>
                </th>
                <td><?php echo esc_html( $end_date_formatted ); ?></td>
            </tr>
        </tbody>
    </table>
    <?php
}
// Hook into user profile page actions
add_action( 'show_user_profile', 'cls_display_subscription_details_on_profile', 20 ); // 20 to display after default fields
add_action( 'edit_user_profile', 'cls_display_subscription_details_on_profile', 20 );

?>
