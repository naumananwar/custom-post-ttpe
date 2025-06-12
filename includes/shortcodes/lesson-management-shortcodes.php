<?php
/**
 * Lesson Management Shortcodes for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode to display a form for creating or editing lessons.
 * Usage: [cls_manage_lessons_form lesson_id="123" course_id="456"] (for editing)
 *        [cls_manage_lessons_form course_id="456"] (for adding new to a specific course)
 */
function cls_manage_lessons_form_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'lesson_id' => 0,
        'course_id' => 0, // Parent course ID
    ), $atts, 'cls_manage_lessons_form' );

    $lesson_id = intval( $atts['lesson_id'] );
    $course_id = intval( $atts['course_id'] ); // This is the parent course ID
    $is_editing = $lesson_id > 0;

    // --- Capability & Pre-checks ---
    $access_check = cls_check_user_role_access( array( 'instructor', 'institution', 'administrator' ) );
    if ( $access_check === 'not_logged_in' ) {
        return sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to manage lessons.', 'custom-login-subscription' ) ), esc_url( wp_login_url( get_permalink() ) ) );
    } elseif ( $access_check !== true ) {
        return '<p>' . esc_html__( 'You do not have sufficient permissions to manage lessons.', 'custom-login-subscription' ) . '</p>';
    }

    $current_user = wp_get_current_user();
    $lesson = null;
    $lesson_meta = array();
    $parent_course = null;

    if ( $is_editing ) {
        $lesson = get_post( $lesson_id );
        if ( ! $lesson || $lesson->post_type !== 'lesson' ) {
            return '<p>' . esc_html__( 'Invalid lesson ID provided.', 'custom-login-subscription' ) . '</p>';
        }
        // If editing, $course_id should ideally match $lesson->post_parent or be derived from it.
        $course_id = $lesson->post_parent;
        $parent_course = get_post( $course_id );

        // Permission check: Can current user edit this specific lesson? (via course ownership/authorship)
        if ( $parent_course && $parent_course->post_type === 'course' ) {
            if ( !current_user_can('administrator') && $parent_course->post_author != $current_user->ID ) {
                if (!(in_array('institution', (array)$current_user->roles) && get_post_meta($course_id, '_cls_course_institution_id', true) == $current_user->ID) ) {
                     return '<p>' . esc_html__( 'You do not have permission to edit lessons for this course.', 'custom-login-subscription' ) . '</p>';
                }
            }
        } else {
             return '<p>' . esc_html__( 'Lesson is not associated with a valid course.', 'custom-login-subscription' ) . '</p>';
        }

        $lesson_meta['_cls_lesson_type'] = get_post_meta( $lesson_id, '_cls_lesson_type', true );
        $lesson_meta['_cls_lesson_video_url'] = get_post_meta( $lesson_id, '_cls_lesson_video_url', true );
        $lesson_meta['_cls_lesson_pdf_url'] = get_post_meta( $lesson_id, '_cls_lesson_pdf_url', true );
        $lesson_meta['menu_order'] = $lesson->menu_order;

    } else { // Adding a new lesson
        if ( empty( $course_id ) ) {
            return '<p>' . esc_html__( 'A course ID is required to add a new lesson.', 'custom-login-subscription' ) . '</p>';
        }
        $parent_course = get_post( $course_id );
        if ( ! $parent_course || $parent_course->post_type !== 'course' ) {
            return '<p>' . esc_html__( 'Invalid parent course ID provided.', 'custom-login-subscription' ) . '</p>';
        }
        // Permission check: Can current user add lessons to this course?
         if ( !current_user_can('administrator') && $parent_course->post_author != $current_user->ID ) {
            if (!(in_array('institution', (array)$current_user->roles) && get_post_meta($course_id, '_cls_course_institution_id', true) == $current_user->ID) ) {
                 return '<p>' . esc_html__( 'You do not have permission to add lessons to this course.', 'custom-login-subscription' ) . '</p>';
            }
        }
        // Set default menu_order for new lessons (e.g., count existing lessons + 1)
        $existing_lessons = get_children(array('post_parent' => $course_id, 'post_type' => 'lesson'));
        $lesson_meta['menu_order'] = count($existing_lessons) + 1;
    }

    $lesson_meta['_cls_lesson_type'] = $lesson_meta['_cls_lesson_type'] ?? 'text';


    // --- Form Submission Handling ---
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['action'] ) && $_POST['action'] === 'cls_save_lesson' ) {
        if ( ! isset( $_POST['_wpnonce_cls_save_lesson'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_wpnonce_cls_save_lesson'])), 'cls_save_lesson_action' ) ) {
            wp_safe_redirect( add_query_arg( 'lesson_error', urlencode(__( 'Security check failed.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }

        $submitted_lesson_id = isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : 0;
        $parent_course_id    = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0; // Parent course ID from hidden field

        // Re-verify parent course ID, especially if it's critical
        if ( empty($parent_course_id) ) {
            wp_safe_redirect( add_query_arg( 'lesson_error', urlencode(__( 'Parent course ID is missing.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }
        $parent_course_for_save = get_post($parent_course_id);
        if (!$parent_course_for_save || $parent_course_for_save->post_type !== 'course') {
            wp_safe_redirect( add_query_arg( 'lesson_error', urlencode(__( 'Invalid parent course specified.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }
        // Re-verify permission to edit/add to this course
        if ( !current_user_can('administrator') && $parent_course_for_save->post_author != $current_user->ID ) {
            if (!(in_array('institution', (array)$current_user->roles) && get_post_meta($parent_course_id, '_cls_course_institution_id', true) == $current_user->ID) ) {
                 wp_safe_redirect( add_query_arg( 'lesson_error', urlencode(__( 'You do not have permission to manage lessons for this course.', 'custom-login-subscription' )), get_permalink() ) );
                 exit;
            }
        }


        $lesson_title        = isset( $_POST['lesson_title'] ) ? sanitize_text_field( $_POST['lesson_title'] ) : '';
        $lesson_content      = isset( $_POST['lesson_content'] ) ? wp_kses_post( $_POST['lesson_content'] ) : '';
        $lesson_type         = isset( $_POST['_cls_lesson_type'] ) ? sanitize_text_field( $_POST['_cls_lesson_type'] ) : 'text';
        $lesson_video_url    = isset( $_POST['_cls_lesson_video_url'] ) ? esc_url_raw( $_POST['_cls_lesson_video_url'] ) : '';
        $lesson_pdf_url      = isset( $_POST['_cls_lesson_pdf_url'] ) ? esc_url_raw( $_POST['_cls_lesson_pdf_url'] ) : '';
        $lesson_menu_order   = isset( $_POST['lesson_menu_order'] ) ? intval( $_POST['lesson_menu_order'] ) : 0;

        if ( empty( $lesson_title ) ) {
            wp_safe_redirect( add_query_arg( 'lesson_error', urlencode(__( 'Lesson title is required.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }

        $post_status = 'publish'; // Default for now

        $post_data = array(
            'post_title'   => $lesson_title,
            'post_content' => $lesson_content,
            'post_type'    => 'lesson',
            'post_status'  => $post_status,
            'post_author'  => $current_user->ID,
            'post_parent'  => $parent_course_id,
            'menu_order'   => $lesson_menu_order,
        );

        if ( $submitted_lesson_id > 0 ) {
            $post_data['ID'] = $submitted_lesson_id;
            $result_id = wp_update_post( $post_data, true );
        } else {
            $result_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result_id ) ) {
            wp_safe_redirect( add_query_arg( 'lesson_error', urlencode( $result_id->get_error_message() ), get_permalink() ) );
            exit;
        } else {
            // Save meta fields
            $allowed_lesson_types = array('text', 'video', 'pdf');
            if (in_array($lesson_type, $allowed_lesson_types, true)) {
                update_post_meta( $result_id, '_cls_lesson_type', $lesson_type );
            }
            update_post_meta( $result_id, '_cls_lesson_video_url', $lesson_video_url );
            update_post_meta( $result_id, '_cls_lesson_pdf_url', $lesson_pdf_url );

            // Redirect after successful save
            $redirect_url = get_permalink($parent_course_id); // Redirect to parent course view page or manage lessons page
            // For now, let's redirect to where the form was, indicating success.
            // The dashboard shortcode will eventually handle the display of lessons list or form.
            $current_form_page_url = get_permalink(get_the_ID()); // Page where [cls_manage_lessons_form] is.

            // If it was a new lesson, redirect to edit it. Otherwise, show success on current form.
            if ($submitted_lesson_id === 0) { // New lesson was created
                 $redirect_url = add_query_arg( array(
                    'lesson_id' => $result_id,
                    'course_id' => $parent_course_id, // Keep course_id context
                    'new_lesson_saved' => 'true'
                ), $current_form_page_url );
            } else { // Existing lesson was updated
                 $redirect_url = add_query_arg( array(
                    'lesson_id' => $result_id,
                    'course_id' => $parent_course_id,
                    'lesson_saved' => 'true'
                ), $current_form_page_url );
            }
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }


    ob_start();
    ?>
    <div class="cls-manage-lesson-form">
        <h3><?php echo $is_editing ? esc_html__( 'Edit Lesson', 'custom-login-subscription' ) : esc_html__( 'Add New Lesson', 'custom-login-subscription' ); ?>
            <?php if ($parent_course) : ?>
                <span style="font-size:0.8em; font-weight:normal;"> (<?php esc_html_e('for Course:', 'custom-login-subscription'); ?> <?php echo esc_html($parent_course->post_title); ?>)</span>
            <?php endif; ?>
        </h3>

        <?php
        // Display messages if any (will be set via redirects later)
        if ( isset( $_GET['lesson_saved'] ) && $_GET['lesson_saved'] === 'true' ) {
            echo '<div class="cls-form-message success"><p>' . esc_html__( 'Lesson saved successfully!', 'custom-login-subscription' ) . '</p></div>';
        }
         if ( isset( $_GET['new_lesson_saved'] ) && $_GET['new_lesson_saved'] === 'true' ) {
            echo '<div class="cls-form-message success"><p>' . esc_html__( 'Lesson created successfully! You are now editing it.', 'custom-login-subscription' ) . '</p></div>';
        }
        if ( isset( $_GET['lesson_error'] ) ) {
            echo '<div class="cls-form-message error"><p>' . esc_html( urldecode( sanitize_text_field(wp_unslash($_GET['lesson_error'])) ) ) . '</p></div>';
        }
        ?>

        <form method="post" action="<?php echo esc_url( get_permalink( get_the_ID() ) ); // Submit to current page where shortcode is placed ?>">
            <input type="hidden" name="action" value="cls_save_lesson">
            <input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson_id ); ?>">
            <input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); // Parent course ID ?>">
            <?php wp_nonce_field( 'cls_save_lesson_action', '_wpnonce_cls_save_lesson' ); ?>

            <p>
                <label for="lesson_title"><?php esc_html_e( 'Lesson Title:', 'custom-login-subscription' ); ?></label><br>
                <input type="text" id="lesson_title" name="lesson_title" value="<?php echo esc_attr( $is_editing && $lesson ? $lesson->post_title : '' ); ?>" required class="widefat">
            </p>

            <p>
                <label for="lesson_content"><?php esc_html_e( 'Lesson Content:', 'custom-login-subscription' ); ?></label><br>
                <?php
                wp_editor(
                    $is_editing && $lesson ? $lesson->post_content : '',
                    'lesson_content',
                    array( 'textarea_name' => 'lesson_content', 'media_buttons' => true, 'teeny' => false, 'textarea_rows' => 15 )
                );
                ?>
            </p>

            <p>
                <label for="cls_lesson_type"><strong><?php esc_html_e( 'Lesson Type:', 'custom-login-subscription' ); ?></strong></label><br>
                <select id="cls_lesson_type" name="_cls_lesson_type">
                    <option value="text" <?php selected( $lesson_meta['_cls_lesson_type'], 'text' ); ?>><?php esc_html_e( 'Text', 'custom-login-subscription' ); ?></option>
                    <option value="video" <?php selected( $lesson_meta['_cls_lesson_type'], 'video' ); ?>><?php esc_html_e( 'Video', 'custom-login-subscription' ); ?></option>
                    <option value="pdf" <?php selected( $lesson_meta['_cls_lesson_type'], 'pdf' ); ?>><?php esc_html_e( 'PDF', 'custom-login-subscription' ); ?></option>
                </select>
            </p>

            <div id="cls-lesson-video-url-wrapper" style="<?php echo $lesson_meta['_cls_lesson_type'] === 'video' ? '' : 'display:none;'; ?>">
                <p>
                    <label for="cls_lesson_video_url"><strong><?php esc_html_e( 'Video URL (e.g., YouTube, Vimeo):', 'custom-login-subscription' ); ?></strong></label><br>
                    <input type="url" id="cls_lesson_video_url" name="_cls_lesson_video_url" value="<?php echo esc_url( $lesson_meta['_cls_lesson_video_url'] ?? '' ); ?>" class="widefat">
                </p>
            </div>

            <div id="cls-lesson-pdf-url-wrapper" style="<?php echo $lesson_meta['_cls_lesson_type'] === 'pdf' ? '' : 'display:none;'; ?>">
                <p>
                    <label for="cls_lesson_pdf_url"><strong><?php esc_html_e( 'PDF URL:', 'custom-login-subscription' ); ?></strong></label><br>
                    <input type="url" id="cls_lesson_pdf_url" name="_cls_lesson_pdf_url" value="<?php echo esc_url( $lesson_meta['_cls_lesson_pdf_url'] ?? '' ); ?>" class="widefat">
                </p>
            </div>

            <p>
                <label for="lesson_menu_order"><?php esc_html_e( 'Order:', 'custom-login-subscription' ); ?></label><br>
                <input type="number" id="lesson_menu_order" name="lesson_menu_order" value="<?php echo esc_attr( $lesson_meta['menu_order'] ?? 0 ); ?>" class="small-text">
            </p>

            <p>
                <input type="submit" name="submit_lesson" class="button button-primary" value="<?php echo $is_editing ? esc_attr__( 'Update Lesson', 'custom-login-subscription' ) : esc_attr__( 'Create Lesson', 'custom-login-subscription' ); ?>">
            </p>
        </form>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleLessonFields() {
                var lessonType = $('#cls_lesson_type').val();
                $('#cls-lesson-video-url-wrapper').toggle(lessonType === 'video');
                $('#cls-lesson-pdf-url-wrapper').toggle(lessonType === 'pdf');
            }
            toggleLessonFields(); // Initial check
            $('#cls_lesson_type').on('change', toggleLessonFields);
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'cls_manage_lessons_form', 'cls_manage_lessons_form_shortcode' );

?>
