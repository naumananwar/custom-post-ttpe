<?php
/**
 * Assessment Management Shortcodes for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode to display a form for creating or editing assessments.
 * Usage: [cls_manage_assessments_form assessment_id="123"] (for editing)
 *        [cls_manage_assessments_form] (for adding new - course_id might be passed via GET)
 */
function cls_manage_assessments_form_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'assessment_id' => 0,
        // course_id might be passed via GET if adding new for a specific course,
        // or derived from assessment if editing.
    ), $atts, 'cls_manage_assessments_form' );

    $assessment_id = intval( $atts['assessment_id'] );
    $is_editing = $assessment_id > 0;
    $current_user_id = get_current_user_id();

    // --- Capability Check ---
    $access_check = cls_check_user_role_access( array( 'instructor', 'institution', 'administrator' ) );
    if ( $access_check === 'not_logged_in' ) {
        return sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to manage assessments.', 'custom-login-subscription' ) ), esc_url( wp_login_url( get_permalink() ) ) );
    } elseif ( $access_check !== true ) {
        return '<p>' . esc_html__( 'You do not have sufficient permissions to manage assessments.', 'custom-login-subscription' ) . '</p>';
    }

    $assessment = null;
    $meta = array();
    $course_context_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0; // For new assessments linked to a course

    if ( $is_editing ) {
        $assessment = get_post( $assessment_id );
        if ( ! $assessment || $assessment->post_type !== 'assessment' ) {
            return '<p>' . esc_html__( 'Invalid assessment ID provided.', 'custom-login-subscription' ) . '</p>';
        }
        // Permission check: User must be author or admin, or linked institution
        $can_edit = false;
        if (current_user_can('administrator') || $assessment->post_author == $current_user_id) {
            $can_edit = true;
        } else if (in_array('institution', (array)wp_get_current_user()->roles)) {
            // Check if this institution is linked to the course, which is linked to assessment
            $assoc_course_id = get_post_meta($assessment_id, '_cls_assessment_associated_course_id', true);
            if ($assoc_course_id && get_post_meta($assoc_course_id, '_cls_course_institution_id', true) == $current_user_id) {
                $can_edit = true;
            }
        }
        if (!$can_edit) {
            return '<p>' . esc_html__( 'You do not have permission to edit this assessment.', 'custom-login-subscription' ) . '</p>';
        }

        $meta['_cls_assessment_associated_course_id'] = get_post_meta( $assessment_id, '_cls_assessment_associated_course_id', true );
        $meta['_cls_assessment_associated_lesson_id'] = get_post_meta( $assessment_id, '_cls_assessment_associated_lesson_id', true );
        $meta['_cls_assessment_total_time_limit'] = get_post_meta( $assessment_id, '_cls_assessment_total_time_limit', true );
        $meta['_cls_assessment_type'] = get_post_meta( $assessment_id, '_cls_assessment_type', true );
        $questions_data = get_post_meta( $assessment_id, '_cls_assessment_questions', true );
        $meta['_cls_assessment_questions_json'] = !empty($questions_data) && is_array($questions_data) ? json_encode($questions_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '[]';
        $course_context_id = $meta['_cls_assessment_associated_course_id'] ?: $course_context_id; // Prefer saved course ID
    } else {
        // Defaults for new assessment
        $meta['_cls_assessment_type'] = 'quiz';
        $meta['_cls_assessment_questions_json'] = json_encode(array(
             array(
                'question_text' => 'Sample Question: What is the capital of this course?',
                'question_type' => 'multiple_choice',
                'options' => array(array('option_text' => 'Option A'), array('option_text' => 'Option B')),
                'correct_answer' => 'Option B', 'points' => 10
            )
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }


    // --- Form Submission Handling ---
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['action'] ) && $_POST['action'] === 'cls_save_assessment' ) {
        if ( ! isset( $_POST['_wpnonce_cls_save_assessment'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_wpnonce_cls_save_assessment'])), 'cls_save_assessment_action' ) ) {
            wp_safe_redirect( add_query_arg( 'assessment_error', urlencode(__( 'Security check failed.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }

        $submitted_assessment_id = isset( $_POST['assessment_id'] ) ? intval( $_POST['assessment_id'] ) : 0;
        $assessment_title        = isset( $_POST['assessment_title'] ) ? sanitize_text_field( $_POST['assessment_title'] ) : '';
        $assessment_description  = isset( $_POST['assessment_description'] ) ? wp_kses_post( $_POST['assessment_description'] ) : '';

        $assoc_course_id = isset( $_POST['_cls_assessment_associated_course_id'] ) ? intval( $_POST['_cls_assessment_associated_course_id'] ) : 0;
        $assoc_lesson_id = isset( $_POST['_cls_assessment_associated_lesson_id'] ) ? intval( $_POST['_cls_assessment_associated_lesson_id'] ) : 0;
        $time_limit      = isset( $_POST['_cls_assessment_total_time_limit'] ) ? intval( $_POST['_cls_assessment_total_time_limit'] ) : 0;
        $assessment_type = isset( $_POST['_cls_assessment_type'] ) ? sanitize_text_field( $_POST['_cls_assessment_type'] ) : 'quiz';
        $questions_json  = isset( $_POST['_cls_assessment_questions_json'] ) ? stripslashes( $_POST['_cls_assessment_questions_json'] ) : '[]';

        if ( empty( $assessment_title ) ) {
            wp_safe_redirect( add_query_arg( 'assessment_error', urlencode(__( 'Assessment title is required.', 'custom-login-subscription' )), get_permalink() ) );
            exit;
        }

        $post_status = 'publish'; // Default for now

        $post_data = array(
            'post_title'   => $assessment_title,
            'post_content' => $assessment_description,
            'post_type'    => 'assessment',
            'post_status'  => $post_status,
            'post_author'  => $current_user_id,
        );

        if ( $submitted_assessment_id > 0 ) {
            // Editing existing assessment
            $post_data['ID'] = $submitted_assessment_id;
            // Re-check permission to edit this specific assessment
            $existing_assessment = get_post($submitted_assessment_id);
            $can_edit_existing = false;
            if (current_user_can('administrator') || $existing_assessment->post_author == $current_user_id) {
                $can_edit_existing = true;
            } else if (in_array('institution', (array)wp_get_current_user()->roles)) {
                $existing_assoc_course_id = get_post_meta($submitted_assessment_id, '_cls_assessment_associated_course_id', true);
                if ($existing_assoc_course_id && get_post_meta($existing_assoc_course_id, '_cls_course_institution_id', true) == $current_user_id) {
                    $can_edit_existing = true;
                }
            }
            if (!$can_edit_existing) {
                 wp_safe_redirect( add_query_arg( 'assessment_error', urlencode(__( 'Permission denied to edit this assessment.', 'custom-login-subscription' )), get_permalink() ) );
                 exit;
            }
            $result_id = wp_update_post( $post_data, true );
        } else {
            // Creating new assessment
            $result_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result_id ) ) {
            wp_safe_redirect( add_query_arg( 'assessment_error', urlencode( $result_id->get_error_message() ), get_permalink() ) );
            exit;
        } else {
            // Save meta fields
            update_post_meta( $result_id, '_cls_assessment_associated_course_id', $assoc_course_id );
            update_post_meta( $result_id, '_cls_assessment_associated_lesson_id', $assoc_lesson_id );
            update_post_meta( $result_id, '_cls_assessment_total_time_limit', $time_limit );

            $allowed_assessment_types = array('quiz', 'exam', 'assignment');
            if (in_array($assessment_type, $allowed_assessment_types, true)) {
                update_post_meta( $result_id, '_cls_assessment_type', $assessment_type );
            }

            // Save questions from JSON
            if ( empty( trim( $questions_json ) ) ) {
                update_post_meta( $result_id, '_cls_assessment_questions', array() );
            } else {
                $questions_data = json_decode( $questions_json, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $questions_data ) ) {
                    $is_valid_structure = true; // Assume true initially
                    foreach ($questions_data as $question_item) {
                        if (!is_array($question_item) || !isset($question_item['question_text']) || !isset($question_item['question_type'])) {
                            $is_valid_structure = false;
                            break;
                        }
                    }
                    if ($is_valid_structure) {
                        update_post_meta( $result_id, '_cls_assessment_questions', $questions_data );
                    } else {
                        // Don't kill redirect, but pass error related to JSON structure
                         wp_safe_redirect( add_query_arg( 'assessment_error', urlencode(__( 'Invalid question structure within JSON.', 'custom-login-subscription' )), get_permalink() ) );
                         exit;
                    }
                } else {
                     wp_safe_redirect( add_query_arg( 'assessment_error', urlencode(__( 'Invalid JSON format for questions.', 'custom-login-subscription' )), get_permalink() ) );
                     exit;
                }
            }

            // Redirect
            $redirect_url_base = get_permalink( get_the_ID() ); // Page where shortcode is
            $redirect_args = array(
                'view' => isset($_GET['view']) ? sanitize_key($_GET['view']) : '', // Preserve original view context if any
                'course_id' => $assoc_course_id ?: (isset($_POST['course_context_id']) ? intval($_POST['course_context_id']) : ''), // Keep course context
            );

            if ($submitted_assessment_id === 0) { // New assessment
                $redirect_args['assessment_id'] = $result_id;
                $redirect_args['new_assessment_saved'] = 'true';
                // Keep assessment_action=manage_assessment_form to stay on edit page for new assessment
                $redirect_args['assessment_action'] = 'manage_assessment_form';
            } else { // Updated assessment
                $redirect_args['assessment_id'] = $result_id;
                $redirect_args['assessment_saved'] = 'true';
                $redirect_args['assessment_action'] = 'manage_assessment_form'; // Stay on edit form
            }

            $redirect_url = add_query_arg( array_filter($redirect_args), $redirect_url_base );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }


    ob_start();
    ?>
    <div class="cls-manage-assessment-form">
        <h3><?php echo $is_editing ? esc_html__( 'Edit Assessment', 'custom-login-subscription' ) : esc_html__( 'Add New Assessment', 'custom-login-subscription' ); ?></h3>

        <?php
        if ( isset( $_GET['assessment_saved'] ) && $_GET['assessment_saved'] === 'true' ) {
            echo '<div class="cls-form-message success"><p>' . esc_html__( 'Assessment saved successfully!', 'custom-login-subscription' ) . '</p></div>';
        }
        if ( isset( $_GET['new_assessment_saved'] ) && $_GET['new_assessment_saved'] === 'true' ) {
            echo '<div class="cls-form-message success"><p>' . esc_html__( 'Assessment created successfully! You are now editing it.', 'custom-login-subscription' ) . '</p></div>';
        }
        if ( isset( $_GET['assessment_error'] ) ) {
            echo '<div class="cls-form-message error"><p>' . esc_html( urldecode( sanitize_text_field(wp_unslash($_GET['assessment_error'])) ) ) . '</p></div>';
        }
        ?>

        <form method="post" action="<?php echo esc_url( get_permalink( get_the_ID() ) ); // Submit to current page ?>">
            <input type="hidden" name="action" value="cls_save_assessment">
            <input type="hidden" name="assessment_id" value="<?php echo esc_attr( $assessment_id ); ?>">
            <input type="hidden" name="course_context_id" value="<?php echo esc_attr( $course_context_id ); // Used if creating new for a specific course ?>">
            <?php wp_nonce_field( 'cls_save_assessment_action', '_wpnonce_cls_save_assessment' ); ?>

            <p>
                <label for="assessment_title"><?php esc_html_e( 'Assessment Title:', 'custom-login-subscription' ); ?></label><br>
                <input type="text" id="assessment_title" name="assessment_title" value="<?php echo esc_attr( $is_editing && $assessment ? $assessment->post_title : '' ); ?>" required class="widefat">
            </p>

            <p>
                <label for="assessment_description"><?php esc_html_e( 'Description/Instructions:', 'custom-login-subscription' ); ?></label><br>
                <?php
                wp_editor(
                    $is_editing && $assessment ? $assessment->post_content : '',
                    'assessment_description',
                    array( 'textarea_name' => 'assessment_description', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 5 )
                );
                ?>
            </p>

            <p>
                <label for="_cls_assessment_associated_course_id"><strong><?php esc_html_e( 'Associated Course (Optional):', 'custom-login-subscription' ); ?></strong></label><br>
                <?php
                $courses = get_posts(array('post_type' => 'course', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'author' => ($is_editing && $assessment && !current_user_can('administrator')) ? $assessment->post_author : null)); // Show only own courses for non-admins, or all for admin
                 if (current_user_can('administrator')) $courses = get_posts(array('post_type' => 'course', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));

                if ( ! empty( $courses ) ) {
                    echo '<select id="_cls_assessment_associated_course_id" name="_cls_assessment_associated_course_id" style="width:100%;">';
                    echo '<option value="">' . esc_html__('-- Select Course --', 'custom-login-subscription') . '</option>';
                    foreach ( $courses as $course_item ) {
                        printf('<option value="%s" %s>%s</option>', esc_attr($course_item->ID), selected($meta['_cls_assessment_associated_course_id'] ?? $course_context_id, $course_item->ID, false), esc_html($course_item->post_title));
                    }
                    echo '</select>';
                } else {
                    esc_html_e( 'No courses available, or you may need to create one first.', 'custom-login-subscription' );
                }
                ?>
            </p>
             <p>
                <label for="_cls_assessment_associated_lesson_id"><strong><?php esc_html_e( 'Associated Lesson (Optional):', 'custom-login-subscription' ); ?></strong></label><br>
                <select id="_cls_assessment_associated_lesson_id" name="_cls_assessment_associated_lesson_id" style="width:100%;">
                    <option value=""><?php esc_html_e('-- Select Lesson --', 'custom-login-subscription'); ?></option>
                    <?php
                    // This dropdown could be populated via AJAX based on selected course.
                    // For now, showing all lessons or lessons of current course_context_id if available.
                    $lesson_query_args = array('post_type' => 'lesson', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC');
                    if ($course_context_id) $lesson_query_args['post_parent'] = $course_context_id;

                    $lessons = get_posts($lesson_query_args);
                    if ( ! empty( $lessons ) ) {
                        foreach ( $lessons as $lesson_item ) {
                             $parent_title = $course_context_id ? '' : ' (' . get_the_title(wp_get_post_parent_id($lesson_item->ID)) . ')';
                            printf('<option value="%s" %s>%s%s</option>', esc_attr($lesson_item->ID), selected($meta['_cls_assessment_associated_lesson_id'] ?? 0, $lesson_item->ID, false), esc_html($lesson_item->post_title), esc_html($parent_title));
                        }
                    }
                    ?>
                </select>
                <?php if (!$course_context_id) echo '<small>' . esc_html__('Select a course above to filter lessons, or lessons from all your courses are shown.', 'custom-login-subscription') . '</small>'; ?>
            </p>

            <p>
                <label for="_cls_assessment_total_time_limit"><strong><?php esc_html_e( 'Time Limit (minutes):', 'custom-login-subscription' ); ?></strong></label><br>
                <input type="number" id="_cls_assessment_total_time_limit" name="_cls_assessment_total_time_limit" value="<?php echo esc_attr( $meta['_cls_assessment_total_time_limit'] ?? 0 ); ?>" min="0">
                <small><?php esc_html_e('Enter time in minutes. 0 for no limit.', 'custom-login-subscription'); ?></small>
            </p>

            <p>
                <label for="_cls_assessment_type"><strong><?php esc_html_e( 'Assessment Type:', 'custom-login-subscription' ); ?></strong></label><br>
                <select id="_cls_assessment_type" name="_cls_assessment_type">
                    <option value="quiz" <?php selected( $meta['_cls_assessment_type'], 'quiz' ); ?>><?php esc_html_e( 'Quiz', 'custom-login-subscription' ); ?></option>
                    <option value="exam" <?php selected( $meta['_cls_assessment_type'], 'exam' ); ?>><?php esc_html_e( 'Exam', 'custom-login-subscription' ); ?></option>
                    <option value="assignment" <?php selected( $meta['_cls_assessment_type'], 'assignment' ); ?>><?php esc_html_e( 'Assignment', 'custom-login-subscription' ); ?></option>
                </select>
            </p>

            <div class="cls-assessment-questions-json-wrapper">
                <h4><?php esc_html_e('Assessment Questions (JSON Format)', 'custom-login-subscription'); ?></h4>
                <p><?php esc_html_e('Enter questions in JSON format below. Ensure the JSON is valid.', 'custom-login-subscription'); ?></p>
                <p><strong><?php esc_html_e('Required fields per question:', 'custom-login-subscription'); ?></strong> <code>question_text</code>, <code>question_type</code>.</p>
                <p><strong><?php esc_html_e('Optional fields per question:', 'custom-login-subscription'); ?></strong> <code>options</code> (array of objects like <code>[{"option_text": "Answer 1"}]</code>, required for multiple_choice), <code>correct_answer</code> (string), <code>individual_time_limit</code> (number in seconds), <code>points</code> (number).</p>
                <p><strong><?php esc_html_e('Question types:', 'custom-login-subscription'); ?></strong> <code>multiple_choice</code>, <code>true_false</code>, <code>short_answer</code>.</p>
                <textarea id="cls_assessment_questions_json" name="_cls_assessment_questions_json" rows="20" style="width:100%; font-family: monospace;"><?php echo esc_textarea( $meta['_cls_assessment_questions_json'] ); ?></textarea>
            </div>

            <p>
                <input type="submit" name="submit_assessment" class="button button-primary" value="<?php echo $is_editing ? esc_attr__( 'Update Assessment', 'custom-login-subscription' ) : esc_attr__( 'Create Assessment', 'custom-login-subscription' ); ?>">
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'cls_manage_assessments_form', 'cls_manage_assessments_form_shortcode' );

?>
