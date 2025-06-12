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
        <label for="cls_package_price"><strong><?php esc_html_e( 'Price:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_package_price" name="cls_package_price" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g., 10.00">
    </p>
    <p>
        <label for="cls_package_interval_count"><strong><?php esc_html_e( 'Billing Cycle:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="number" id="cls_package_interval_count" name="cls_package_interval_count" value="<?php echo esc_attr( $interval_count ? $interval_count : 1 ); ?>" min="1" style="width: 70px;">
        <select id="cls_package_interval" name="cls_package_interval">
            <option value="day" <?php selected( $interval, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'custom-login-subscription' ); ?></option>
            <option value="week" <?php selected( $interval, 'week' ); ?>><?php esc_html_e( 'Week(s)', 'custom-login-subscription' ); ?></option>
            <option value="month" <?php selected( $interval, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'custom-login-subscription' ); ?></option>
            <option value="year" <?php selected( $interval, 'year' ); ?>><?php esc_html_e( 'Year(s)', 'custom-login-subscription' ); ?></option>
        </select>
    </p>
    <p>
        <label for="cls_package_features"><strong><?php esc_html_e( 'Features (one per line):', 'custom-login-subscription' ); ?></strong></label><br>
        <textarea id="cls_package_features" name="cls_package_features" rows="5" style="width:98%;"><?php echo esc_textarea( $features ); ?></textarea>
    </p>
    <hr>
    <p><em><?php esc_html_e( 'Optional: Payment Gateway Plan/Price IDs. These will be used for Stripe/PayPal integration.', 'custom-login-subscription' ); ?></em></p>
    <p>
        <label for="cls_stripe_price_id"><strong><?php esc_html_e( 'Stripe Price ID:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_stripe_price_id" name="cls_stripe_price_id" value="<?php echo esc_attr( $stripe_price_id ); ?>" placeholder="e.g., price_1LXXXX..." style="width:50%;">
    </p>
    <p>
        <label for="cls_paypal_plan_id"><strong><?php esc_html_e( 'PayPal Plan ID:', 'custom-login-subscription' ); ?></strong></label><br>
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
    // Note: using save_post_{post_type} hook is better for this check.
    // if ( 'subscription_package' !== get_post_type( $post_id ) ) { // This check is not needed if using save_post_{post_type}
    //     return;
    // }

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
// Using the more specific save_post_{post_type} hook
add_action( 'save_post_subscription_package', 'cls_save_package_details_meta' );


// --- Course CPT & Taxonomy ---

/**
 * Registers the 'course' custom post type.
 */
function cls_register_course_cpt() {
    $labels = array(
        'name'                  => _x( 'Courses', 'Post type general name', 'custom-login-subscription' ),
        'singular_name'         => _x( 'Course', 'Post type singular name', 'custom-login-subscription' ),
        'menu_name'             => _x( 'Courses', 'Admin Menu text', 'custom-login-subscription' ),
        'name_admin_bar'        => _x( 'Course', 'Add New on Toolbar', 'custom-login-subscription' ),
        'add_new'               => __( 'Add New', 'custom-login-subscription' ),
        'add_new_item'          => __( 'Add New Course', 'custom-login-subscription' ),
        'new_item'              => __( 'New Course', 'custom-login-subscription' ),
        'edit_item'             => __( 'Edit Course', 'custom-login-subscription' ),
        'view_item'             => __( 'View Course', 'custom-login-subscription' ),
        'all_items'             => __( 'All Courses', 'custom-login-subscription' ),
        'search_items'          => __( 'Search Courses', 'custom-login-subscription' ),
        'parent_item_colon'     => __( 'Parent Courses:', 'custom-login-subscription' ),
        'not_found'             => __( 'No courses found.', 'custom-login-subscription' ),
        'not_found_in_trash'    => __( 'No courses found in Trash.', 'custom-login-subscription' ),
        'featured_image'        => _x( 'Course Image', 'Overrides the “Featured Image” phrase for this CPT.', 'custom-login-subscription' ),
        'set_featured_image'    => _x( 'Set course image', 'Overrides the “Set featured image” phrase for this CPT.', 'custom-login-subscription' ),
        'remove_featured_image' => _x( 'Remove course image', 'Overrides the “Remove featured image” phrase for this CPT.', 'custom-login-subscription' ),
        'use_featured_image'    => _x( 'Use as course image', 'Overrides the “Use as featured image” phrase for this CPT.', 'custom-login-subscription' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => 'courses', // Slug for archive page
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'courses' ),
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'menu_position'      => 21,
        'supports'           => array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields', 'excerpt' ),
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'show_in_rest'       => true,
    );

    register_post_type( 'course', $args );
}
add_action( 'init', 'cls_register_course_cpt' );

/**
 * Registers the 'course_category' taxonomy for the 'course' CPT.
 */
function cls_register_course_category_taxonomy() {
    $labels = array(
        'name'              => _x( 'Course Categories', 'taxonomy general name', 'custom-login-subscription' ),
        'singular_name'     => _x( 'Course Category', 'taxonomy singular name', 'custom-login-subscription' ),
        'search_items'      => __( 'Search Course Categories', 'custom-login-subscription' ),
        'all_items'         => __( 'All Course Categories', 'custom-login-subscription' ),
        'parent_item'       => __( 'Parent Course Category', 'custom-login-subscription' ),
        'parent_item_colon' => __( 'Parent Course Category:', 'custom-login-subscription' ),
        'edit_item'         => __( 'Edit Course Category', 'custom-login-subscription' ),
        'update_item'       => __( 'Update Course Category', 'custom-login-subscription' ),
        'add_new_item'      => __( 'Add New Course Category', 'custom-login-subscription' ),
        'new_item_name'     => __( 'New Course Category Name', 'custom-login-subscription' ),
        'menu_name'         => __( 'Course Categories', 'custom-login-subscription' ),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'course-category' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'course_category', array( 'course' ), $args );
}
add_action( 'init', 'cls_register_course_category_taxonomy' );


/**
 * Adds meta boxes for course details.
 */
function cls_add_course_meta_boxes() {
    add_meta_box(
        'cls_course_details_meta_box',
        __( 'Course Details', 'custom-login-subscription' ),
        'cls_render_course_details_meta_box',
        'course',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'cls_add_course_meta_boxes' );

/**
 * Renders the HTML for the course details meta box.
 */
function cls_render_course_details_meta_box( $post ) {
    wp_nonce_field( 'cls_save_course_details', 'cls_course_details_nonce' );

    $price           = get_post_meta( $post->ID, '_cls_course_price', true );
    $institution_id  = get_post_meta( $post->ID, '_cls_course_institution_id', true );
    $objectives      = get_post_meta( $post->ID, '_cls_course_objectives', true );
    $requirements    = get_post_meta( $post->ID, '_cls_course_requirements', true );
    $outcomes        = get_post_meta( $post->ID, '_cls_course_outcomes', true );

    ?>
    <p>
        <label for="cls_course_price"><strong><?php esc_html_e( 'Price:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_course_price" name="cls_course_price" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php esc_attr_e( 'e.g., 29.99 or Free', 'custom-login-subscription' ); ?>">
    </p>
    <p>
        <label for="cls_course_institution_id"><strong><?php esc_html_e( 'Institution:', 'custom-login-subscription' ); ?></strong></label><br>
        <?php
        $institution_users = get_users(array('role__in' => array('institution'), 'orderby' => 'display_name'));
        if ( ! empty( $institution_users ) ) {
            echo '<select id="cls_course_institution_id" name="cls_course_institution_id">';
            echo '<option value="">' . esc_html__('-- Select Institution --', 'custom-login-subscription') . '</option>';
            foreach ( $institution_users as $inst_user ) {
                echo '<option value="' . esc_attr( $inst_user->ID ) . '"' . selected( $institution_id, $inst_user->ID, false ) . '>' . esc_html( $inst_user->display_name ) . ' (ID: ' . esc_html($inst_user->ID) . ')</option>';
            }
            echo '</select>';
        } else {
            esc_html_e( 'No users with the "Institution" role found.', 'custom-login-subscription' );
        }
        ?>
    </p>
    <p>
        <label for="cls_course_objectives"><strong><?php esc_html_e( 'Course Objectives (one per line):', 'custom-login-subscription' ); ?></strong></label><br>
        <textarea id="cls_course_objectives" name="cls_course_objectives" rows="5" style="width:98%;"><?php echo esc_textarea( $objectives ); ?></textarea>
    </p>
    <p>
        <label for="cls_course_requirements"><strong><?php esc_html_e( 'Course Requirements (one per line):', 'custom-login-subscription' ); ?></strong></label><br>
        <textarea id="cls_course_requirements" name="cls_course_requirements" rows="5" style="width:98%;"><?php echo esc_textarea( $requirements ); ?></textarea>
    </p>
    <p>
        <label for="cls_course_outcomes"><strong><?php esc_html_e( 'Course Outcomes (one per line):', 'custom-login-subscription' ); ?></strong></label><br>
        <textarea id="cls_course_outcomes" name="cls_course_outcomes" rows="5" style="width:98%;"><?php echo esc_textarea( $outcomes ); ?></textarea>
    </p>
    <?php
}

/**
 * Saves the custom meta box data for courses.
 */
function cls_save_course_details_meta( $post_id ) {
    if ( ! isset( $_POST['cls_course_details_nonce'] ) || ! wp_verify_nonce( $_POST['cls_course_details_nonce'], 'cls_save_course_details' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'course' ) {
        return;
    }

    // Price
    if ( isset( $_POST['cls_course_price'] ) ) {
        update_post_meta( $post_id, '_cls_course_price', sanitize_text_field( $_POST['cls_course_price'] ) );
    }
    // Institution ID
    if ( isset( $_POST['cls_course_institution_id'] ) ) {
        update_post_meta( $post_id, '_cls_course_institution_id', intval( $_POST['cls_course_institution_id'] ) );
    } else {
        delete_post_meta( $post_id, '_cls_course_institution_id' );
    }
    // Objectives
    if ( isset( $_POST['cls_course_objectives'] ) ) {
        update_post_meta( $post_id, '_cls_course_objectives', sanitize_textarea_field( $_POST['cls_course_objectives'] ) );
    }
    // Requirements
    if ( isset( $_POST['cls_course_requirements'] ) ) {
        update_post_meta( $post_id, '_cls_course_requirements', sanitize_textarea_field( $_POST['cls_course_requirements'] ) );
    }
    // Outcomes
    if ( isset( $_POST['cls_course_outcomes'] ) ) {
        update_post_meta( $post_id, '_cls_course_outcomes', sanitize_textarea_field( $_POST['cls_course_outcomes'] ) );
    }
}
add_action( 'save_post_course', 'cls_save_course_details_meta' );


// --- Lesson CPT & Meta Boxes ---

/**
 * Registers the 'lesson' custom post type.
 */
function cls_register_lesson_cpt() {
    $labels = array(
        'name'                  => _x( 'Lessons', 'Post type general name', 'custom-login-subscription' ),
        'singular_name'         => _x( 'Lesson', 'Post type singular name', 'custom-login-subscription' ),
        'menu_name'             => _x( 'Lessons', 'Admin Menu text', 'custom-login-subscription' ),
        'name_admin_bar'        => _x( 'Lesson', 'Add New on Toolbar', 'custom-login-subscription' ),
        'add_new'               => __( 'Add New', 'custom-login-subscription' ),
        'add_new_item'          => __( 'Add New Lesson', 'custom-login-subscription' ),
        'new_item'              => __( 'New Lesson', 'custom-login-subscription' ),
        'edit_item'             => __( 'Edit Lesson', 'custom-login-subscription' ),
        'view_item'             => __( 'View Lesson', 'custom-login-subscription' ),
        'all_items'             => __( 'All Lessons', 'custom-login-subscription' ),
        'search_items'          => __( 'Search Lessons', 'custom-login-subscription' ),
        'parent_item_colon'     => __( 'Parent Course:', 'custom-login-subscription' ),
        'not_found'             => __( 'No lessons found.', 'custom-login-subscription' ),
        'not_found_in_trash'    => __( 'No lessons found in Trash.', 'custom-login-subscription' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => 'edit.php?post_type=course',
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'lessons' ),
        'capability_type'    => 'post',
        'has_archive'        => 'lessons',
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields', 'page-attributes', 'thumbnail' ),
        'menu_icon'          => 'dashicons-media-document',
        'show_in_rest'       => true,
    );

    register_post_type( 'lesson', $args );
}
add_action( 'init', 'cls_register_lesson_cpt' );

/**
 * Filters the arguments for the parent page dropdown in the "Page Attributes" meta box.
 * Ensures that only 'course' post types are shown as possible parents for 'lesson' posts.
 */
function cls_filter_lesson_parent_dropdown( $dropdown_args, $post ) {
    if ( $post && 'lesson' === $post->post_type ) {
        $dropdown_args['post_type'] = 'course';
        $dropdown_args['show_option_none'] = __( '-- Select Parent Course --', 'custom-login-subscription' );
    }
    return $dropdown_args;
}
add_filter( 'page_attributes_dropdown_pages_args', 'cls_filter_lesson_parent_dropdown', 10, 2 );


/**
 * Adds meta boxes for lesson details.
 */
function cls_add_lesson_meta_boxes() {
    add_meta_box(
        'cls_lesson_details_meta_box',
        __( 'Lesson Details', 'custom-login-subscription' ),
        'cls_render_lesson_details_meta_box',
        'lesson',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'cls_add_lesson_meta_boxes' );

/**
 * Renders the HTML for the lesson details meta box.
 */
function cls_render_lesson_details_meta_box( $post ) {
    wp_nonce_field( 'cls_save_lesson_details', 'cls_lesson_details_nonce' );

    $lesson_type    = get_post_meta( $post->ID, '_cls_lesson_type', true );
    $video_url      = get_post_meta( $post->ID, '_cls_lesson_video_url', true );
    $pdf_url        = get_post_meta( $post->ID, '_cls_lesson_pdf_url', true );

    $lesson_type = $lesson_type ? $lesson_type : 'text';

    ?>
    <p>
        <label for="cls_lesson_type"><strong><?php esc_html_e( 'Lesson Type:', 'custom-login-subscription' ); ?></strong></label><br>
        <select id="cls_lesson_type" name="cls_lesson_type">
            <option value="text" <?php selected( $lesson_type, 'text' ); ?>><?php esc_html_e( 'Text', 'custom-login-subscription' ); ?></option>
            <option value="video" <?php selected( $lesson_type, 'video' ); ?>><?php esc_html_e( 'Video', 'custom-login-subscription' ); ?></option>
            <option value="pdf" <?php selected( $lesson_type, 'pdf' ); ?>><?php esc_html_e( 'PDF', 'custom-login-subscription' ); ?></option>
        </select>
    </p>
    <div id="cls-lesson-video-url-wrapper" style="<?php echo $lesson_type === 'video' ? '' : 'display:none;'; ?>">
        <p>
            <label for="cls_lesson_video_url"><strong><?php esc_html_e( 'Video URL (e.g., YouTube, Vimeo):', 'custom-login-subscription' ); ?></strong></label><br>
            <input type="url" id="cls_lesson_video_url" name="cls_lesson_video_url" value="<?php echo esc_url( $video_url ); ?>" class="widefat">
        </p>
    </div>
    <div id="cls-lesson-pdf-url-wrapper" style="<?php echo $lesson_type === 'pdf' ? '' : 'display:none;'; ?>">
        <p>
            <label for="cls_lesson_pdf_url"><strong><?php esc_html_e( 'PDF URL:', 'custom-login-subscription' ); ?></strong></label><br>
            <input type="url" id="cls_lesson_pdf_url" name="cls_lesson_pdf_url" value="<?php echo esc_url( $pdf_url ); ?>" class="widefat">
            <small><?php esc_html_e('Alternatively, you can upload a PDF to the media library and paste the URL here.', 'custom-login-subscription'); ?></small>
        </p>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleLessonFields() {
                var lessonType = $('#cls_lesson_type').val();
                $('#cls-lesson-video-url-wrapper').toggle(lessonType === 'video');
                $('#cls-lesson-pdf-url-wrapper').toggle(lessonType === 'pdf');
            }
            toggleLessonFields();
            $('#cls_lesson_type').on('change', toggleLessonFields);
        });
    </script>
    <?php
}

/**
 * Saves the custom meta box data for lessons.
 */
function cls_save_lesson_details_meta( $post_id ) {
    if ( ! isset( $_POST['cls_lesson_details_nonce'] ) || ! wp_verify_nonce( $_POST['cls_lesson_details_nonce'], 'cls_save_lesson_details' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'lesson' ) {
        return;
    }

    // Lesson Type
    if ( isset( $_POST['cls_lesson_type'] ) ) {
        $allowed_types = array( 'text', 'video', 'pdf' );
        $lesson_type = sanitize_text_field( $_POST['cls_lesson_type'] );
        if ( in_array( $lesson_type, $allowed_types, true ) ) {
            update_post_meta( $post_id, '_cls_lesson_type', $lesson_type );
        }
    }
    // Video URL
    if ( isset( $_POST['cls_lesson_video_url'] ) ) {
        update_post_meta( $post_id, '_cls_lesson_video_url', esc_url_raw( $_POST['cls_lesson_video_url'] ) );
    }
    // PDF URL
    if ( isset( $_POST['cls_lesson_pdf_url'] ) ) {
        update_post_meta( $post_id, '_cls_lesson_pdf_url', esc_url_raw( $_POST['cls_lesson_pdf_url'] ) );
    }
}
add_action( 'save_post_lesson', 'cls_save_lesson_details_meta' );


// --- Assessment CPT & Meta Boxes ---

/**
 * Registers the 'assessment' custom post type.
 */
function cls_register_assessment_cpt() {
    $labels = array(
        'name'                  => _x( 'Assessments', 'Post type general name', 'custom-login-subscription' ),
        'singular_name'         => _x( 'Assessment', 'Post type singular name', 'custom-login-subscription' ),
        'menu_name'             => _x( 'Assessments', 'Admin Menu text', 'custom-login-subscription' ),
        'name_admin_bar'        => _x( 'Assessment', 'Add New on Toolbar', 'custom-login-subscription' ),
        'add_new'               => __( 'Add New', 'custom-login-subscription' ),
        'add_new_item'          => __( 'Add New Assessment', 'custom-login-subscription' ),
        'new_item'              => __( 'New Assessment', 'custom-login-subscription' ),
        'edit_item'             => __( 'Edit Assessment', 'custom-login-subscription' ),
        'view_item'             => __( 'View Assessment', 'custom-login-subscription' ),
        'all_items'             => __( 'All Assessments', 'custom-login-subscription' ),
        'search_items'          => __( 'Search Assessments', 'custom-login-subscription' ),
        'not_found'             => __( 'No assessments found.', 'custom-login-subscription' ),
        'not_found_in_trash'    => __( 'No assessments found in Trash.', 'custom-login-subscription' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => 'edit.php?post_type=course',
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'assessments' ),
        'capability_type'    => 'post',
        'has_archive'        => 'assessments',
        'hierarchical'       => false,
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ),
        'menu_icon'          => 'dashicons-forms',
        'show_in_rest'       => true,
    );

    register_post_type( 'assessment', $args );
}
add_action( 'init', 'cls_register_assessment_cpt' );

/**
 * Adds meta boxes for assessment details.
 */
function cls_add_assessment_meta_boxes() {
    add_meta_box(
        'cls_assessment_details_meta_box',
        __( 'Assessment Details', 'custom-login-subscription' ),
        'cls_render_assessment_details_meta_box',
        'assessment',
        'normal',
        'high'
    );

    add_meta_box(
        'cls_assessment_questions_meta_box',
        __( 'Assessment Questions (JSON)', 'custom-login-subscription' ),
        'cls_render_assessment_questions_meta_box',
        'assessment',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'cls_add_assessment_meta_boxes' );

/**
 * Renders the HTML for the assessment details meta box.
 */
function cls_render_assessment_details_meta_box( $post ) {
    wp_nonce_field( 'cls_save_assessment_details', 'cls_assessment_details_nonce' );

    $associated_course_id = get_post_meta( $post->ID, '_cls_assessment_associated_course_id', true );
    $associated_lesson_id = get_post_meta( $post->ID, '_cls_assessment_associated_lesson_id', true );
    $time_limit           = get_post_meta( $post->ID, '_cls_assessment_total_time_limit', true );
    $assessment_type      = get_post_meta( $post->ID, '_cls_assessment_type', true );
    $assessment_type      = $assessment_type ? $assessment_type : 'quiz';

    ?>
    <h4><?php esc_html_e( 'General Details', 'custom-login-subscription'); ?></h4>
    <p>
        <label for="cls_assessment_associated_course_id"><strong><?php esc_html_e( 'Associated Course:', 'custom-login-subscription' ); ?></strong></label><br>
        <?php
        $courses = get_posts(array('post_type' => 'course', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        if ( ! empty( $courses ) ) {
            echo '<select id="cls_assessment_associated_course_id" name="cls_assessment_associated_course_id" style="width:100%;">';
            echo '<option value="">' . esc_html__('-- Select Course (Optional) --', 'custom-login-subscription') . '</option>';
            foreach ( $courses as $course_item ) {
                echo '<option value="' . esc_attr( $course_item->ID ) . '"' . selected( $associated_course_id, $course_item->ID, false ) . '>' . esc_html( $course_item->post_title ) . '</option>';
            }
            echo '</select>';
        } else {
            esc_html_e( 'No courses found to associate.', 'custom-login-subscription' );
        }
        ?>
    </p>
    <p>
        <label for="cls_assessment_associated_lesson_id"><strong><?php esc_html_e( 'Associated Lesson (Optional):', 'custom-login-subscription' ); ?></strong></label><br>
        <?php
        $lessons = get_posts(array('post_type' => 'lesson', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        if ( ! empty( $lessons ) ) {
            echo '<select id="cls_assessment_associated_lesson_id" name="cls_assessment_associated_lesson_id" style="width:100%;">';
            echo '<option value="">' . esc_html__('-- Select Lesson (Optional) --', 'custom-login-subscription') . '</option>';
            foreach ( $lessons as $lesson_item ) {
                $parent_course = get_post_parent($lesson_item->ID);
                $parent_title = $parent_course ? ' (' . get_the_title($parent_course) . ')' : '';
                echo '<option value="' . esc_attr( $lesson_item->ID ) . '"' . selected( $associated_lesson_id, $lesson_item->ID, false ) . '>' . esc_html( $lesson_item->post_title . $parent_title ) . '</option>';
            }
            echo '</select>';
        } else {
            esc_html_e( 'No lessons found to associate.', 'custom-login-subscription' );
        }
        ?>
    </p>
    <p>
        <label for="cls_assessment_total_time_limit"><strong><?php esc_html_e( 'Time Limit (minutes):', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="number" id="cls_assessment_total_time_limit" name="cls_assessment_total_time_limit" value="<?php echo esc_attr( $time_limit ); ?>" min="0">
        <small><?php esc_html_e('Enter time in minutes. 0 for no limit.', 'custom-login-subscription'); ?></small>
    </p>
    <p>
        <label for="cls_assessment_type"><strong><?php esc_html_e( 'Assessment Type:', 'custom-login-subscription' ); ?></strong></label><br>
        <select id="cls_assessment_type" name="cls_assessment_type">
            <option value="quiz" <?php selected( $assessment_type, 'quiz' ); ?>><?php esc_html_e( 'Quiz', 'custom-login-subscription' ); ?></option>
            <option value="exam" <?php selected( $assessment_type, 'exam' ); ?>><?php esc_html_e( 'Exam', 'custom-login-subscription' ); ?></option>
            <option value="assignment" <?php selected( $assessment_type, 'assignment' ); ?>><?php esc_html_e( 'Assignment', 'custom-login-subscription' ); ?></option>
        </select>
    </p>
    <?php
}


/**
 * Renders the meta box for assessment questions (JSON input).
 */
function cls_render_assessment_questions_meta_box( $post ) {
    // Nonce is included in cls_render_assessment_details_meta_box, and will be verified by cls_save_assessment_details_meta
    // If this were saved by a separate function, it would need its own nonce.

    $questions_data = get_post_meta( $post->ID, '_cls_assessment_questions', true );
    $json_output = '';
    if ( empty( $questions_data ) || !is_array($questions_data) ) {
        $sample_structure = array(
            array(
                'question_text' => 'What is 2+2?',
                'question_type' => 'multiple_choice',
                'options' => array(
                    array('option_text' => '3'),
                    array('option_text' => '4'),
                    array('option_text' => '5'),
                ),
                'correct_answer' => '4',
                'individual_time_limit' => 60,
                'points' => 10,
            )
        );
        $json_output = json_encode( $sample_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    } else {
        $json_output = json_encode( $questions_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }

    $json_error = get_transient('cls_assessment_json_error_' . $post->ID);
    if ($json_error) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($json_error) . '</p></div>';
        delete_transient('cls_assessment_json_error_' . $post->ID);
    }

    ?>
    <p><?php esc_html_e('Enter questions in JSON format below. Ensure the JSON is valid.', 'custom-login-subscription'); ?></p>
    <p><strong><?php esc_html_e('Required fields per question:', 'custom-login-subscription'); ?></strong> <code>question_text</code>, <code>question_type</code>.</p>
    <p><strong><?php esc_html_e('Optional fields per question:', 'custom-login-subscription'); ?></strong> <code>options</code> (array of objects like <code>[{"option_text": "Answer 1"}]</code>, required for multiple_choice), <code>correct_answer</code> (string), <code>individual_time_limit</code> (number in seconds), <code>points</code> (number).</p>
    <p><strong><?php esc_html_e('Question types:', 'custom-login-subscription'); ?></strong> <code>multiple_choice</code>, <code>true_false</code>, <code>short_answer</code>.</p>

    <textarea id="cls_assessment_questions_json" name="cls_assessment_questions_json" rows="20" style="width:100%; font-family: monospace;"><?php echo esc_textarea( $json_output ); ?></textarea>

    <p><em><?php esc_html_e('Example for one multiple choice question:', 'custom-login-subscription'); ?></em></p>
    <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 10px; border: 1px solid #ccc; max-width: 100%; overflow-x: auto;"><code>
[
    {
        "question_text": "What is the primary color of the sky on a clear day?",
        "question_type": "multiple_choice",
        "options": [
            {"option_text": "Green"},
            {"option_text": "Blue"},
            {"option_text": "Red"}
        ],
        "correct_answer": "Blue",
        "individual_time_limit": 60,
        "points": 5
    }
]
    </code></pre>
    <?php
}


/**
 * Saves the custom meta box data for assessments.
 * This function now also handles the JSON questions.
 */
function cls_save_assessment_details_meta( $post_id ) {
    if ( ! isset( $_POST['cls_assessment_details_nonce'] ) || ! wp_verify_nonce( $_POST['cls_assessment_details_nonce'], 'cls_save_assessment_details' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'assessment' ) {
        return;
    }

    // General Details
    if ( isset( $_POST['cls_assessment_associated_course_id'] ) ) {
        update_post_meta( $post_id, '_cls_assessment_associated_course_id', intval( $_POST['cls_assessment_associated_course_id'] ) );
    }
    if ( isset( $_POST['cls_assessment_associated_lesson_id'] ) ) {
        update_post_meta( $post_id, '_cls_assessment_associated_lesson_id', intval( $_POST['cls_assessment_associated_lesson_id'] ) );
    }
    if ( isset( $_POST['cls_assessment_total_time_limit'] ) ) {
        update_post_meta( $post_id, '_cls_assessment_total_time_limit', intval( $_POST['cls_assessment_total_time_limit'] ) );
    }
    if ( isset( $_POST['cls_assessment_type'] ) ) {
        $allowed_types = array( 'quiz', 'exam', 'assignment' );
        $assessment_type = sanitize_text_field( $_POST['cls_assessment_type'] );
        if ( in_array( $assessment_type, $allowed_types, true ) ) {
            update_post_meta( $post_id, '_cls_assessment_type', $assessment_type );
        }
    }

    // Assessment Questions JSON
    if ( isset( $_POST['cls_assessment_questions_json'] ) ) {
        $json_string = stripslashes( $_POST['cls_assessment_questions_json'] );
        if ( empty( trim( $json_string ) ) ) {
            update_post_meta( $post_id, '_cls_assessment_questions', array() );
        } else {
            $questions_data = json_decode( $json_string, true );
            // Check if JSON is valid and is an array (could be an object if user enters single question not in array)
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $questions_data ) ) {
                $is_valid_structure = true;
                foreach ($questions_data as $question_item) { // Renamed $question to $question_item
                    if (!is_array($question_item) || !isset($question_item['question_text']) || !isset($question_item['question_type'])) {
                        $is_valid_structure = false;
                        break;
                    }
                }
                if ($is_valid_structure) {
                    update_post_meta( $post_id, '_cls_assessment_questions', $questions_data );
                } else {
                    set_transient('cls_assessment_json_error_' . $post_id, __('Invalid question structure within JSON. Each question must be an object with at least "question_text" and "question_type".', 'custom-login-subscription'), 60);
                }
            } else {
                set_transient('cls_assessment_json_error_' . $post_id, __('Invalid JSON format for questions. Please ensure it is a valid JSON array of question objects.', 'custom-login-subscription'), 60);
            }
        }
    }
}
add_action( 'save_post_assessment', 'cls_save_assessment_details_meta' );


// --- Assessment Package CPT & Meta Boxes ---

/**
 * Registers the 'assessment_package' custom post type.
 */
function cls_register_assessment_package_cpt() {
    $labels = array(
        'name'                  => _x( 'Assessment Packages', 'Post type general name', 'custom-login-subscription' ),
        'singular_name'         => _x( 'Assessment Package', 'Post type singular name', 'custom-login-subscription' ),
        'menu_name'             => _x( 'Assessment Packages', 'Admin Menu text', 'custom-login-subscription' ),
        'name_admin_bar'        => _x( 'Assessment Package', 'Add New on Toolbar', 'custom-login-subscription' ),
        'add_new'               => __( 'Add New', 'custom-login-subscription' ),
        'add_new_item'          => __( 'Add New Assessment Package', 'custom-login-subscription' ),
        'new_item'              => __( 'New Assessment Package', 'custom-login-subscription' ),
        'edit_item'             => __( 'Edit Assessment Package', 'custom-login-subscription' ),
        'view_item'             => __( 'View Assessment Package', 'custom-login-subscription' ),
        'all_items'             => __( 'All Assessment Packages', 'custom-login-subscription' ),
        'search_items'          => __( 'Search Assessment Packages', 'custom-login-subscription' ),
        'not_found'             => __( 'No assessment packages found.', 'custom-login-subscription' ),
        'not_found_in_trash'    => __( 'No assessment packages found in Trash.', 'custom-login-subscription' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => 'edit.php?post_type=course', // Submenu of Courses
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'assessment-packages' ),
        'capability_type'    => 'post',
        'has_archive'        => 'assessment-packages',
        'hierarchical'       => false,
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ),
        'menu_icon'          => 'dashicons-archive',
        'show_in_rest'       => true,
    );

    register_post_type( 'assessment_package', $args );
}
add_action( 'init', 'cls_register_assessment_package_cpt' );

/**
 * Adds meta boxes for assessment package details.
 */
function cls_add_assessment_package_meta_boxes() {
    add_meta_box(
        'cls_assessment_package_details_meta_box',
        __( 'Assessment Package Details', 'custom-login-subscription' ),
        'cls_render_assessment_package_details_meta_box',
        'assessment_package',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'cls_add_assessment_package_meta_boxes' );

/**
 * Renders the HTML for the assessment package details meta box.
 */
function cls_render_assessment_package_details_meta_box( $post ) {
    wp_nonce_field( 'cls_save_assessment_package_details', 'cls_assessment_package_details_nonce' );

    $price                  = get_post_meta( $post->ID, '_cls_package_price', true );
    $included_assessments   = get_post_meta( $post->ID, '_cls_included_assessments', true );
    $availability_rules   = get_post_meta( $post->ID, '_cls_package_availability_rules', true );

    if ( ! is_array( $included_assessments ) ) {
        $included_assessments = array();
    }
    ?>
    <p>
        <label for="cls_package_price_field"><strong><?php esc_html_e( 'Price:', 'custom-login-subscription' ); ?></strong></label><br>
        <input type="text" id="cls_package_price_field" name="_cls_package_price" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php esc_attr_e( 'e.g., 49.99', 'custom-login-subscription' ); ?>">
    </p>

    <h4><?php esc_html_e( 'Included Assessments:', 'custom-login-subscription' ); ?></h4>
    <div id="cls_included_assessments_checklist" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background-color: #fff;">
        <?php
        $all_assessments = get_posts(array('post_type' => 'assessment', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        if ( ! empty( $all_assessments ) ) {
            foreach ( $all_assessments as $assessment ) {
                ?>
                <label style="display: block;">
                    <input type="checkbox" name="_cls_included_assessments[]" value="<?php echo esc_attr( $assessment->ID ); ?>" <?php checked( in_array( $assessment->ID, $included_assessments ) ); ?>>
                    <?php echo esc_html( $assessment->post_title ); ?>
                </label>
                <?php
            }
        } else {
            esc_html_e( 'No assessments found. Please create some first.', 'custom-login-subscription' );
        }
        ?>
    </div>
    <p><small><?php esc_html_e('Select the assessments to include in this package.', 'custom-login-subscription'); ?></small></p>

    <p>
        <label for="cls_package_availability_rules"><strong><?php esc_html_e( 'Availability Rules / Description:', 'custom-login-subscription' ); ?></strong></label><br>
        <textarea id="cls_package_availability_rules" name="_cls_package_availability_rules" rows="4" style="width:98%;"><?php echo esc_textarea( $availability_rules ); ?></textarea>
        <small><?php esc_html_e('e.g., "Available for 30 days after purchase", "One-time access per assessment".', 'custom-login-subscription'); ?></small>
    </p>
    <?php
}

/**
 * Saves the custom meta box data for assessment packages.
 */
function cls_save_assessment_package_details_meta( $post_id ) {
    if ( ! isset( $_POST['cls_assessment_package_details_nonce'] ) || ! wp_verify_nonce( $_POST['cls_assessment_package_details_nonce'], 'cls_save_assessment_package_details' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) || get_post_type( $post_id ) !== 'assessment_package' ) {
        return;
    }

    // Price
    if ( isset( $_POST['_cls_package_price'] ) ) {
        update_post_meta( $post_id, '_cls_package_price', sanitize_text_field( $_POST['_cls_package_price'] ) );
    }

    // Included Assessments
    if ( isset( $_POST['_cls_included_assessments'] ) && is_array( $_POST['_cls_included_assessments'] ) ) {
        $sanitized_assessment_ids = array_map( 'intval', $_POST['_cls_included_assessments'] );
        update_post_meta( $post_id, '_cls_included_assessments', $sanitized_assessment_ids );
    } else {
        update_post_meta( $post_id, '_cls_included_assessments', array() ); // Save empty array if nothing selected
    }

    // Availability Rules
    if ( isset( $_POST['_cls_package_availability_rules'] ) ) {
        update_post_meta( $post_id, '_cls_package_availability_rules', sanitize_textarea_field( $_POST['_cls_package_availability_rules'] ) );
    }
}
add_action( 'save_post_assessment_package', 'cls_save_assessment_package_details_meta' );

?>
