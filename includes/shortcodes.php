<?php
/**
 * Shortcode definitions for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Displays subscription packages.
 * Shortcode: [cls_subscription_packages]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the subscription packages.
 */
function cls_display_subscription_packages_shortcode( $atts ) {
    // For now, $atts are not used, but they could be for customization later (e.g., ids, category).
    $atts = shortcode_atts( array(
        // 'category' => '',
        // 'ids' => '',
        // 'orderby' => 'title',
        // 'order' => 'ASC',
    ), $atts, 'cls_subscription_packages' );

    $args = array(
        'post_type'      => 'subscription_package',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Display all packages
        'orderby'        => 'title', // Default order by title
        'order'          => 'ASC',
    );

    // Potentially modify query based on $atts later
    // if ( !empty($atts['ids']) ) {
    // $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
    // }
    // if ( !empty($atts['orderby']) ) {
    // $args['orderby'] = sanitize_key($atts['orderby']);
    // }
    // if ( !empty($atts['order']) ) {
    // $args['order'] = sanitize_key($atts['order']);
    // }


    $packages_query = new WP_Query( $args );

    ob_start();

    if ( $packages_query->have_posts() ) {
        echo '<div class="cls-subscription-packages-wrapper">';

        while ( $packages_query->have_posts() ) {
            $packages_query->the_post();
            $post_id = get_the_ID();

            // Retrieve meta data
            $price          = get_post_meta( $post_id, '_cls_package_price', true );
            $interval       = get_post_meta( $post_id, '_cls_package_interval', true );
            $interval_count = get_post_meta( $post_id, '_cls_package_interval_count', true );
            $features_raw   = get_post_meta( $post_id, '_cls_package_features', true );

            $interval_count_display = $interval_count ? intval($interval_count) : 1;
            // Ensure interval itself is sane, though it's from meta saved by select.
            $allowed_intervals = array('day' => 'Day(s)', 'week' => 'Week(s)', 'month' => 'Month(s)', 'year' => 'Year(s)');
            $interval_label = isset($allowed_intervals[$interval]) ? $allowed_intervals[$interval] : esc_html(ucfirst($interval));

            // If interval_count is 1, use singular form from $allowed_intervals (e.g. "Day" instead of "Day(s)")
            if ($interval_count_display === 1) {
                $singular_labels = array('day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year');
                $interval_display = isset($singular_labels[$interval]) ? $singular_labels[$interval] : esc_html(ucfirst($interval));
            } else {
                $interval_display = $interval_label;
            }

            ?>
            <div class="cls-package" id="cls-package-<?php echo esc_attr( $post_id ); ?>">
                <h3><?php echo esc_html( get_the_title() ); ?></h3>

                <div class="cls-package-description">
                    <?php echo wp_kses_post( get_the_content() ); // Using wp_kses_post for content from editor ?>
                </div>

                <div class="cls-package-price">
                    <?php if ( ! empty( $price ) ) : ?>
                        <strong><?php esc_html_e( 'Price:', 'custom-login-subscription' ); ?></strong> <?php echo esc_html( $price ); ?> / <?php echo esc_html( $interval_count_display . ' ' . $interval_display ); ?>
                    <?php else : ?>
                        <strong><?php esc_html_e( 'Price:', 'custom-login-subscription' ); ?></strong> <?php esc_html_e( 'Contact us', 'custom-login-subscription' ); ?>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $features_raw ) ) : ?>
                    <div class="cls-package-features">
                        <h4><?php esc_html_e( 'Features:', 'custom-login-subscription' ); ?></h4>
                        <ul>
                            <?php
                            $features_list = explode( "\n", $features_raw );
                            foreach ( $features_list as $feature_item ) {
                                if ( ! empty( trim( $feature_item ) ) ) {
                                    echo '<li>' . esc_html( trim( $feature_item ) ) . '</li>';
                                }
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php // Stripe Button
                $stripe_price_id = get_post_meta( $post_id, '_cls_stripe_price_id', true );
                $stripe_button_text = __( 'Subscribe with Card', 'custom-login-subscription' );
                $stripe_button_disabled = false;
                $stripe_button_attrs = 'data-package-id="' . esc_attr( $post_id ) . '"';
                $stripe_configured = cls_get_setting('stripe_publishable_key') && cls_get_setting('stripe_secret_key');

                if ( $stripe_configured ) {
                    if ( ! empty( $stripe_price_id ) ) {
                        $stripe_button_attrs .= ' data-stripe-price-id="' . esc_attr( $stripe_price_id ) . '"';
                    } else {
                        $stripe_button_text = __( 'Not Available (Stripe)', 'custom-login-subscription' );
                        $stripe_button_disabled = true;
                    }
                } else {
                    $stripe_button_text = __( 'Payments Offline', 'custom-login-subscription' );
                    $stripe_button_disabled = true;
                }
                ?>
                <button class="cls-subscribe-button" <?php echo $stripe_button_attrs; ?> <?php if ($stripe_button_disabled) echo 'disabled'; ?>>
                    <?php echo esc_html( $stripe_button_text ); ?>
                </button>

                <?php // PayPal Button
                $paypal_plan_id = get_post_meta( $post_id, '_cls_paypal_plan_id', true );
                $paypal_button_text = __( 'Subscribe with PayPal', 'custom-login-subscription' );
                $paypal_button_disabled = false;
                $paypal_button_attrs = 'data-package-id="' . esc_attr( $post_id ) . '"';
                $paypal_configured = cls_get_setting('paypal_client_id') && cls_get_setting('paypal_client_secret');

                if ( $paypal_configured ) {
                    if ( ! empty( $paypal_plan_id ) ) {
                        $paypal_button_attrs .= ' data-paypal-plan-id="' . esc_attr( $paypal_plan_id ) . '"';
                    } else {
                        // Hide button if PayPal plan ID not set for this package
                        $paypal_plan_id = null;
                    }
                } else {
                     // Hide button if PayPal not configured globally
                    $paypal_plan_id = null;
                }

                if ($paypal_plan_id && !$paypal_button_disabled): // Only show button if PayPal is an option and not explicitly disabled
                ?>
                <button class="cls-paypal-subscribe-button cls-subscribe-button" <?php echo $paypal_button_attrs; ?> style="margin-top: 10px; background-color: #0070ba;">
                    <?php echo esc_html( $paypal_button_text ); ?>
                </button>
                <?php endif; ?>
            </div>
            <?php
        }
        echo '</div>'; // .cls-subscription-packages-wrapper
    } else {
        echo '<p>' . __( 'No subscription packages available at the moment.', 'custom-login-subscription' ) . '</p>';
    }

    wp_reset_postdata(); // Restore original Post Data

    return ob_get_clean();
}
add_shortcode( 'cls_subscription_packages', 'cls_display_subscription_packages_shortcode' );

?>
