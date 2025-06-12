<?php
/**
 * Custom Post Type and Meta Box functionality for Subscription Packages.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the 'subscription_package' custom post type.
 */
function cls_register_subscription_package_cpt() {
    $labels = array(
        'name'                  => _x( 'Subscription Packages', 'Post type general name', 'custom-login-subscription' ),
        'singular_name'         => _x( 'Subscription Package', 'Post type singular name', 'custom-login-subscription' ),
        'menu_name'             => _x( 'Subscription Packages', 'Admin Menu text', 'custom-login-subscription' ),
        'name_admin_bar'        => _x( 'Subscription Package', 'Add New on Toolbar', 'custom-login-subscription' ),
        'add_new'               => __( 'Add New', 'custom-login-subscription' ),
        'add_new_item'          => __( 'Add New Subscription Package', 'custom-login-subscription' ),
        'new_item'              => __( 'New Subscription Package', 'custom-login-subscription' ),
        'edit_item'             => __( 'Edit Subscription Package', 'custom-login-subscription' ),
        'view_item'             => __( 'View Subscription Package', 'custom-login-subscription' ),
        'all_items'             => __( 'All Subscription Packages', 'custom-login-subscription' ),
        'search_items'          => __( 'Search Subscription Packages', 'custom-login-subscription' ),
        'parent_item_colon'     => __( 'Parent Subscription Packages:', 'custom-login-subscription' ),
        'not_found'             => __( 'No subscription packages found.', 'custom-login-subscription' ),
        'not_found_in_trash'    => __( 'No subscription packages found in Trash.', 'custom-login-subscription' ),
        'featured_image'        => _x( 'Package Image', 'Overrides the “Featured Image” phrase for this post type.', 'custom-login-subscription' ),
        'set_featured_image'    => _x( 'Set package image', 'Overrides the “Set featured image” phrase for this post type.', 'custom-login-subscription' ),
        'remove_featured_image' => _x( 'Remove package image', 'Overrides the “Remove featured image” phrase for this post type.', 'custom-login-subscription' ),
        'use_featured_image'    => _x( 'Use as package image', 'Overrides the “Use as featured image” phrase for this post type.', 'custom-login-subscription' ),
        'archives'              => _x( 'Subscription Package archives', 'The post type archive label used in nav menus.', 'custom-login-subscription' ),
        'insert_into_item'      => _x( 'Insert into package', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post).', 'custom-login-subscription' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this package', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'custom-login-subscription' ),
        'filter_items_list'     => _x( 'Filter packages list', 'Screen reader text for the filter links heading on the post type listing screen.', 'custom-login-subscription' ),
        'items_list_navigation' => _x( 'Packages list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'custom-login-subscription' ),
        'items_list'            => _x( 'Packages list', 'Screen reader text for the items list heading on the post type listing screen.', 'custom-login-subscription' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'subscription-package' ),
        'capability_type'    => 'post',
        'has_archive'        => false, // No dedicated archive page for now
        'hierarchical'       => false,
        'menu_position'      => 20, // Below Pages
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
        'menu_icon'          => 'dashicons-cart',
        'show_in_rest'       => true, // Enable Gutenberg editor and REST API support
    );

    register_post_type( 'subscription_package', $args );
}
add_action( 'init', 'cls_register_subscription_package_cpt' );

/**
 * Adds meta boxes for subscription package details.
 */
function cls_add_package_meta_boxes() {
    add_meta_box(
        'cls_package_details_meta_box',
        __( 'Package Details', 'custom-login-subscription' ),
        'cls_render_package_details_meta_box',
        'subscription_package',
        'normal', // context (normal, side, advanced)
        'high'    // priority (high, core, default, low)
    );
}
add_action( 'add_meta_boxes', 'cls_add_package_meta_boxes' );

/**
 * Renders the HTML for the package details meta box.
 *
 * @param WP_Post $post The current post object.
 */
function cls_render_package_details_meta_box( $post ) {
    // Add a nonce field for security
    wp_nonce_field( 'cls_save_package_details', 'cls_package_details_nonce' );

    // Retrieve existing values from the database
    $price          = get_post_meta( $post->ID, '_cls_package_price', true );
    $interval       = get_post_meta( $post->ID, '_cls_package_interval', true );
    $interval_count = get_post_meta( $post->ID, '_cls_package_interval_count', true );
    $stripe_price_id = get_post_meta( $post->ID, '_cls_stripe_price_id', true );
    $paypal_plan_id  = get_post_meta( $post->ID, '_cls_paypal_plan_id', true );
    $features       = get_post_meta( $post->ID, '_cls_package_features', true );

    ?>
    <p>
        <label for="cls_package_price"><strong><?php _e( 'Price:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_package_price" name="cls_package_price" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g., 10.00">
    </p>
    <p>
        <label for="cls_package_interval_count"><strong><?php _e( 'Billing Cycle:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="number" id="cls_package_interval_count" name="cls_package_interval_count" value="<?php echo esc_attr( $interval_count ? $interval_count : 1 ); ?>" min="1" style="width: 70px;">
        <select id="cls_package_interval" name="cls_package_interval">
            <option value="day" <?php selected( $interval, 'day' ); ?>><?php _e( 'Day(s)', 'custom-login-subscription' ); ?></option>
            <option value="week" <?php selected( $interval, 'week' ); ?>><?php _e( 'Week(s)', 'custom-login-subscription' ); ?></option>
            <option value="month" <?php selected( $interval, 'month' ); ?>><?php _e( 'Month(s)', 'custom-login-subscription' ); ?></option>
            <option value="year" <?php selected( $interval, 'year' ); ?>><?php _e( 'Year(s)', 'custom-login-subscription' ); ?></option>
        </select>
    </p>
    <p>
        <label for="cls_package_features"><strong><?php _e( 'Features (one per line):', 'custom-login-subscription' ); ?></strong></label><br>
        <textarea id="cls_package_features" name="cls_package_features" rows="5" style="width:98%;"><?php echo esc_textarea( $features ); ?></textarea>
    </p>
    <hr>
    <p><em><?php _e( 'Optional: Payment Gateway Plan/Price IDs. These will be used for Stripe/PayPal integration.', 'custom-login-subscription' ); ?></em></p>
    <p>
        <label for="cls_stripe_price_id"><strong><?php _e( 'Stripe Price ID:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_stripe_price_id" name="cls_stripe_price_id" value="<?php echo esc_attr( $stripe_price_id ); ?>" placeholder="e.g., price_1LXXXX..." style="width:50%;">
    </p>
    <p>
        <label for="cls_paypal_plan_id"><strong><?php _e( 'PayPal Plan ID:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_paypal_plan_id" name="cls_paypal_plan_id" value="<?php echo esc_attr( $paypal_plan_id ); ?>" placeholder="e.g., P-XXXXX..." style="width:50%;">
    </p>
    <?php
}

/**
 * Saves the custom meta box data for subscription packages.
 *
 * @param int $post_id The ID of the post being saved.
 */
function cls_save_package_details_meta( $post_id ) {
    // Check if our nonce is set.
    if ( ! isset( $_POST['cls_package_details_nonce'] ) ) {
        return;
    }
    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['cls_package_details_nonce'], 'cls_save_package_details' ) ) {
        return;
    }
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // Check the user's permissions.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    // Make sure we are saving our 'subscription_package' CPT.
    if ( 'subscription_package' !== get_post_type( $post_id ) ) {
        return;
    }

    // Sanitize and save the data
    if ( isset( $_POST['cls_package_price'] ) ) {
        update_post_meta( $post_id, '_cls_package_price', sanitize_text_field( $_POST['cls_package_price'] ) );
    }
    if ( isset( $_POST['cls_package_interval'] ) ) {
        $allowed_intervals = array( 'day', 'week', 'month', 'year' );
        if ( in_array( $_POST['cls_package_interval'], $allowed_intervals, true ) ) {
            update_post_meta( $post_id, '_cls_package_interval', sanitize_text_field( $_POST['cls_package_interval'] ) );
        }
    }
    if ( isset( $_POST['cls_package_interval_count'] ) ) {
        update_post_meta( $post_id, '_cls_package_interval_count', intval( $_POST['cls_package_interval_count'] ) );
    }
    if ( isset( $_POST['cls_stripe_price_id'] ) ) {
        update_post_meta( $post_id, '_cls_stripe_price_id', sanitize_text_field( $_POST['cls_stripe_price_id'] ) );
    }
     if ( isset( $_POST['cls_paypal_plan_id'] ) ) {
        update_post_meta( $post_id, '_cls_paypal_plan_id', sanitize_text_field( $_POST['cls_paypal_plan_id'] ) );
    }
    if ( isset( $_POST['cls_package_features'] ) ) {
        // Sanitize each line of the features textarea
        $features = explode( "\n", $_POST['cls_package_features'] );
        $sanitized_features = array_map( 'sanitize_text_field', $features );
        update_post_meta( $post_id, '_cls_package_features', implode( "\n", $sanitized_features ) );
    }
}
add_action( 'save_post', 'cls_save_package_details_meta' );

?>
