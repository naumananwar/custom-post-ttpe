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
            $interval_display = $interval ? esc_html(ucfirst($interval)) : 'Month';
            if ($interval_count_display > 1) {
                // basic pluralization
                $interval_display .= 's';
            }


            ?>
            <div class="cls-package" id="cls-package-<?php echo $post_id; ?>">
                <h3><?php the_title(); ?></h3>

                <div class="cls-package-description">
                    <?php the_content(); // Package description from the editor ?>
                </div>

                <div class="cls-package-price">
                    <?php if ( ! empty( $price ) ) : ?>
                        <strong>Price:</strong> <?php echo esc_html( $price ); ?> / <?php echo $interval_count_display . ' ' . $interval_display; ?>
                    <?php else : ?>
                        <strong>Price:</strong> <?php _e( 'Contact us', 'custom-login-subscription' ); ?>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $features_raw ) ) : ?>
                    <div class="cls-package-features">
                        <h4><?php _e( 'Features:', 'custom-login-subscription' ); ?></h4>
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
                <?php
                $stripe_price_id = get_post_meta( $post_id, '_cls_stripe_price_id', true );
                $button_text = __( 'Subscribe', 'custom-login-subscription' );
                $button_disabled = false;
                $button_attrs = 'data-package-id="' . esc_attr( $post_id ) . '"';

                if ( defined('CLS_STRIPE_PUBLISHABLE_KEY') && CLS_STRIPE_PUBLISHABLE_KEY !== 'YOUR_STRIPE_PUBLISHABLE_KEY' ) {
                    if ( ! empty( $stripe_price_id ) ) {
                        $button_attrs .= ' data-stripe-price-id="' . esc_attr( $stripe_price_id ) . '"';
                    } else {
                        $button_text = __( 'Stripe Not Configured for Package', 'custom-login-subscription' );
                        $button_disabled = true;
                    }
                } else {
                    // Stripe keys not configured in plugin settings
                    // Or handle other payment gateways here in future
                    $button_text = __( 'Payment Not Configured', 'custom-login-subscription' );
                    $button_disabled = true;
                }
                ?>
                <button class="cls-subscribe-button" <?php echo $button_attrs; ?> <?php if ($button_disabled) echo 'disabled'; ?>>
                    <?php echo esc_html( $button_text ); ?>
                </button>

                <?php
                // PayPal Button
                $paypal_plan_id = get_post_meta( $post_id, '_cls_paypal_plan_id', true );
                $paypal_button_text = __( 'Subscribe with PayPal', 'custom-login-subscription' );
                $paypal_button_disabled = false;
                $paypal_button_attrs = 'data-package-id="' . esc_attr( $post_id ) . '"';

                if ( defined('CLS_PAYPAL_CLIENT_ID') && CLS_PAYPAL_CLIENT_ID !== 'YOUR_PAYPAL_CLIENT_ID' ) {
                    if ( ! empty( $paypal_plan_id ) ) {
                        $paypal_button_attrs .= ' data-paypal-plan-id="' . esc_attr( $paypal_plan_id ) . '"';
                    } else {
                        // $paypal_button_text = __( 'PayPal Not Configured for Package', 'custom-login-subscription' );
                        // $paypal_button_disabled = true;
                        // Hide button if not configured for this package
                        $paypal_plan_id = null;
                    }
                } else {
                    // PayPal keys not configured in plugin settings
                    // $paypal_button_text = __( 'PayPal Not Configured', 'custom-login-subscription' );
                    // $paypal_button_disabled = true;
                     // Hide button if PayPal not configured globally
                    $paypal_plan_id = null;
                }

                if ($paypal_plan_id): // Only show button if PayPal is an option
                ?>
                <button class="cls-paypal-subscribe-button" <?php echo $paypal_button_attrs; ?> <?php if ($paypal_button_disabled) echo 'disabled'; ?> style="margin-top: 10px; background-color: #0070ba;">
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
