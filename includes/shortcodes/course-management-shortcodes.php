<?php
/**
 * Course Management Shortcodes for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders a list of course categories as checkboxes.
 *
 * @param int $post_id Optional. The ID of the post to get currently selected terms for.
 * @return string HTML for checkboxes.
 */
function cls_get_course_categories_checkboxes( $post_id = 0 ) {
    $output = '';
    $categories = get_terms( array(
        'taxonomy'   => 'course_category',
        'hide_empty' => false,
    ) );

    $selected_cats = array();
    if ( $post_id ) {
        $selected_cats_terms = get_the_terms( $post_id, 'course_category' );
        if ( ! is_wp_error( $selected_cats_terms ) && ! empty( $selected_cats_terms ) ) {
            $selected_cats = wp_list_pluck( $selected_cats_terms, 'term_id' );
        }
    }

    if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
        $output .= '<div class="cls-course-categories-checklist" style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">';
        foreach ( $categories as $category ) {
            $checked = in_array( $category->term_id, $selected_cats ) ? 'checked' : '';
            $output .= sprintf(
                '<label><input type="checkbox" name="course_categories[]" value="%s" %s> %s</label><br>',
                esc_attr( $category->term_id ),
                $checked,
                esc_html( $category->name )
            );
        }
        $output .= '</div>';
    } else {
        $output = '<p>' . esc_html__( 'No course categories found.', 'custom-login-subscription' ) . '</p>';
    }
    return $output;
}


/**
 * Shortcode to display a form for creating or editing courses.
 * Usage: [cls_manage_courses_form course_id="123"] (for editing)
 *        [cls_manage_courses_form] (for adding new)
 */
function cls_manage_courses_form_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'course_id' => 0,
    ), $atts, 'cls_manage_courses_form' );

    $course_id = intval( $atts['course_id'] );
    $is_editing = $course_id > 0;

    // --- Capability Check ---
    $access_check = cls_check_user_role_access( array( 'instructor', 'institution' ) ); // Assuming cls_check_user_role_access exists
    if ( $access_check === 'not_logged_in' ) {
        return sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to manage courses.', 'custom-login-subscription' ) ), esc_url( wp_login_url( get_permalink() ) ) );
    } elseif ( $access_check !== true ) {
        return '<p>' . esc_html__( 'You do not have sufficient permissions to manage courses.', 'custom-login-subscription' ) . '</p>';
    }

    $current_user = wp_get_current_user();
    $course = null;
    $course_meta = array();

    if ( $is_editing ) {
        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== 'course' ) {
            return '<p>' . esc_html__( 'Invalid course ID provided.', 'custom-login-subscription' ) . '</p>';
        }
        // Permission check: Can current user edit this specific course?
        // Admins can edit any. Instructors/Institutions can edit their own.
        // Institutions might be able to edit courses by instructors under them (more complex, not handled here yet).
        if ( !current_user_can('administrator') && $course->post_author != $current_user->ID ) {
             // A more granular check like current_user_can('edit_course', $course_id) would be better if custom caps are set.
            if (!(in_array('institution', (array)$current_user->roles) && get_post_meta($course_id, '_cls_course_institution_id', true) == $current_user->ID) &&
                !in_array('instructor', (array)$current_user->roles) && $course->post_author == $current_user->ID ) {
                 return '<p>' . esc_html__( 'You do not have permission to edit this course.', 'custom-login-subscription' ) . '</p>';
            }
        }

        $course_meta['_cls_course_price'] = get_post_meta( $course_id, '_cls_course_price', true );
        $course_meta['_cls_course_objectives'] = get_post_meta( $course_id, '_cls_course_objectives', true );
        $course_meta['_cls_course_requirements'] = get_post_meta( $course_id, '_cls_course_requirements', true );
        $course_meta['_cls_course_outcomes'] = get_post_meta( $course_id, '_cls_course_outcomes', true );
        $course_meta['_cls_course_featured_image_url'] = get_post_meta( $course_id, '_cls_course_featured_image_url', true );
        // Institution ID is set in backend meta box, not directly editable by instructor in this form for now.
    }

    // --- Form Submission Handling ---
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['action'] ) && $_POST['action'] === 'cls_save_course' ) {
        if ( ! isset( $_POST['_wpnonce_cls_save_course'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_wpnonce_cls_save_course'])), 'cls_save_course_action' ) ) {
            // Nonce verification failed
            // Redirect with error or display error:
            wp_safe_redirect( add_query_arg( 'course_error', urlencode(__( 'Security check failed.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }

        $submitted_course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        $course_title        = isset( $_POST['course_title'] ) ? sanitize_text_field( $_POST['course_title'] ) : '';
        $course_description  = isset( $_POST['course_description'] ) ? wp_kses_post( $_POST['course_description'] ) : '';

        $course_price        = isset( $_POST['_cls_course_price'] ) ? sanitize_text_field( $_POST['_cls_course_price'] ) : '';
        $course_objectives   = isset( $_POST['_cls_course_objectives'] ) ? sanitize_textarea_field( $_POST['_cls_course_objectives'] ) : '';
        $course_requirements = isset( $_POST['_cls_course_requirements'] ) ? sanitize_textarea_field( $_POST['_cls_course_requirements'] ) : '';
        $course_outcomes     = isset( $_POST['_cls_course_outcomes'] ) ? sanitize_textarea_field( $_POST['_cls_course_outcomes'] ) : '';
        $featured_image_url  = isset( $_POST['_cls_course_featured_image_url'] ) ? esc_url_raw( $_POST['_cls_course_featured_image_url'] ) : '';
        $course_categories   = isset( $_POST['course_categories'] ) && is_array( $_POST['course_categories'] ) ? array_map( 'intval', $_POST['course_categories'] ) : array();

        if ( empty( $course_title ) ) {
            wp_safe_redirect( add_query_arg( 'course_error', urlencode(__( 'Course title is required.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }

        $post_status = 'publish'; // Default for now
        if ( in_array('instructor', (array)$current_user->roles) && !current_user_can('administrator') && !in_array('institution', (array)$current_user->roles) ) {
            // $post_status = 'pending'; // Example: Instructors submit for review, unless admin/institution
        }


        $post_data = array(
            'post_title'   => $course_title,
            'post_content' => $course_description,
            'post_type'    => 'course',
            'post_status'  => $post_status,
            'post_author'  => $current_user->ID,
        );

        if ( $submitted_course_id > 0 ) {
            // Editing existing course
            $post_data['ID'] = $submitted_course_id;
            // Ensure the user has permission to edit this specific post
            $existing_course = get_post($submitted_course_id);
            if (!$existing_course || $existing_course->post_author != $current_user->ID && !current_user_can('administrator') && !(in_array('institution', (array)$current_user->roles) && get_post_meta($submitted_course_id, '_cls_course_institution_id', true) == $current_user->ID) ){
                wp_safe_redirect( add_query_arg( 'course_error', urlencode(__( 'Permission denied to edit this course.', 'custom-login-subscription' )), get_permalink() ) );
                exit;
            }
            $result_id = wp_update_post( $post_data, true );
        } else {
            // Creating new course
            $result_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result_id ) ) {
            // Handle error
            wp_safe_redirect( add_query_arg( 'course_error', urlencode( $result_id->get_error_message() ), get_permalink() ) );
            exit;
        } else {
            // Save meta fields
            update_post_meta( $result_id, '_cls_course_price', $course_price );
            update_post_meta( $result_id, '_cls_course_objectives', $course_objectives );
            update_post_meta( $result_id, '_cls_course_requirements', $course_requirements );
            update_post_meta( $result_id, '_cls_course_outcomes', $course_outcomes );
            update_post_meta( $result_id, '_cls_course_featured_image_url', $featured_image_url );

            if ( in_array('institution', (array)$current_user->roles) ) {
                update_post_meta( $result_id, '_cls_course_institution_id', $current_user->ID );
            } elseif ( $submitted_course_id === 0 ) { // If new course by non-institution, clear it
                 delete_post_meta( $result_id, '_cls_course_institution_id');
            } // If editing, and not institution, institution ID remains as set by admin/institution.

            // Save course categories
            if ( ! empty( $course_categories ) ) {
                wp_set_post_terms( $result_id, $course_categories, 'course_category', false );
            } else {
                wp_set_post_terms( $result_id, array(), 'course_category', false ); // Clear terms if none selected
            }

            // Redirect after successful save
            // Could redirect to an instructor dashboard page or the course view page
            // For now, redirect back to the same page with a success message
            $redirect_url = add_query_arg( 'course_saved', 'true', get_permalink() );
            if ($is_editing) {
                 $redirect_url = add_query_arg( 'course_id', $result_id, $redirect_url ); // Keep course_id for edit form
            } else {
                // For new course, redirect to edit form of the new course
                 $redirect_url = add_query_arg( 'course_id', $result_id, remove_query_arg('course_saved', $redirect_url) ); // Remove course_saved to avoid double message
                 $redirect_url = add_query_arg( 'new_course_saved', 'true', $redirect_url );
            }
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    ob_start();
    ?>
    <div class="cls-manage-course-form">
        <h2><?php echo $is_editing ? esc_html__( 'Edit Course', 'custom-login-subscription' ) : esc_html__( 'Add New Course', 'custom-login-subscription' ); ?></h2>

        <?php
        // Display any success/error messages from form submission
        if ( isset( $_GET['course_saved'] ) && $_GET['course_saved'] === 'true' ) {
            echo '<div class="cls-form-message success"><p>' . esc_html__( 'Course saved successfully!', 'custom-login-subscription' ) . '</p></div>';
        }
         if ( isset( $_GET['new_course_saved'] ) && $_GET['new_course_saved'] === 'true' ) {
            echo '<div class="cls-form-message success"><p>' . esc_html__( 'Course created successfully! You are now editing it.', 'custom-login-subscription' ) . '</p></div>';
        }
        if ( isset( $_GET['course_error'] ) ) {
            echo '<div class="cls-form-message error"><p>' . esc_html( urldecode( sanitize_text_field(wp_unslash($_GET['course_error'])) ) ) . '</p></div>';
        }
        ?>

        <form method="post" action="<?php echo esc_url( get_permalink(get_the_ID()) ); // Submit to current page shortcode is on ?>">
            <input type="hidden" name="action" value="cls_save_course">
            <input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
            <?php wp_nonce_field( 'cls_save_course_action', '_wpnonce_cls_save_course' ); ?>

            <p>
                <label for="course_title"><?php esc_html_e( 'Course Title:', 'custom-login-subscription' ); ?></label><br>
                <input type="text" id="course_title" name="course_title" value="<?php echo esc_attr( $is_editing && $course ? $course->post_title : '' ); ?>" required class="widefat">
            </p>

            <p>
                <label for="course_description"><?php esc_html_e( 'Course Description:', 'custom-login-subscription' ); ?></label><br>
                <?php
                wp_editor(
                    $is_editing && $course ? $course->post_content : '',
                    'course_description',
                    array( 'textarea_name' => 'course_description', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 10 )
                );
                ?>
            </p>

            <p>
                <label for="cls_course_price"><?php esc_html_e( 'Price:', 'custom-login-subscription' ); ?></label><br>
                <input type="text" id="cls_course_price" name="_cls_course_price" value="<?php echo esc_attr( $course_meta['_cls_course_price'] ?? '' ); ?>" placeholder="<?php esc_attr_e('e.g., 29.99 or Free', 'custom-login-subscription'); ?>" class="regular-text">
            </p>

            <p>
                <label for="cls_course_objectives"><?php esc_html_e( 'Course Objectives (one per line):', 'custom-login-subscription' ); ?></label><br>
                <textarea id="cls_course_objectives" name="_cls_course_objectives" rows="5" class="widefat"><?php echo esc_textarea( $course_meta['_cls_course_objectives'] ?? '' ); ?></textarea>
            </p>

            <p>
                <label for="cls_course_requirements"><?php esc_html_e( 'Course Requirements (one per line):', 'custom-login-subscription' ); ?></label><br>
                <textarea id="cls_course_requirements" name="_cls_course_requirements" rows="5" class="widefat"><?php echo esc_textarea( $course_meta['_cls_course_requirements'] ?? '' ); ?></textarea>
            </p>

            <p>
                <label for="cls_course_outcomes"><?php esc_html_e( 'Course Outcomes (one per line):', 'custom-login-subscription' ); ?></label><br>
                <textarea id="cls_course_outcomes" name="_cls_course_outcomes" rows="5" class="widefat"><?php echo esc_textarea( $course_meta['_cls_course_outcomes'] ?? '' ); ?></textarea>
            </p>

            <p>
                <label for="cls_course_featured_image_url"><?php esc_html_e( 'Featured Image URL (Optional):', 'custom-login-subscription' ); ?></label><br>
                <input type="url" id="cls_course_featured_image_url" name="_cls_course_featured_image_url" value="<?php echo esc_url( $course_meta['_cls_course_featured_image_url'] ?? '' ); ?>" class="widefat">
                 <small><?php esc_html_e('Enter the full URL for the course image. For more advanced image handling, use the admin dashboard.', 'custom-login-subscription');?></small>
            </p>

            <p>
                <label><?php esc_html_e( 'Course Categories:', 'custom-login-subscription' ); ?></label><br>
                <?php echo cls_get_course_categories_checkboxes( $course_id ); ?>
            </p>

            <p>
                <input type="submit" name="submit_course" class="button button-primary" value="<?php echo $is_editing ? esc_attr__( 'Update Course', 'custom-login-subscription' ) : esc_attr__( 'Create Course', 'custom-login-subscription' ); ?>">
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'cls_manage_courses_form', 'cls_manage_courses_form_shortcode' );

?>
