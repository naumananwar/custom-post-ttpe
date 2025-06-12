<?php
/**
 * Dashboard Shortcodes for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper function to check user role access.
 * Always allows 'administrator'.
 *
 * @param array $allowed_roles Array of role slugs that are allowed.
 * @return bool|string True if access granted, 'not_logged_in' if not logged in, false if role not allowed.
 */
function cls_check_user_role_access( $allowed_roles = array() ) {
    if ( ! is_user_logged_in() ) {
        return 'not_logged_in';
    }
    $user = wp_get_current_user();
    $user_roles = (array) $user->roles;

    // Administrator can access everything
    if ( in_array( 'administrator', $user_roles, true ) ) {
        return true;
    }

    // Check if the user has any of the allowed roles
    foreach ( $allowed_roles as $role ) {
        if ( in_array( $role, $user_roles, true ) ) {
            return true;
        }
    }
    return false; // Role not allowed
}

/**
 * Student Dashboard Shortcode.
 * Usage: [cls_student_dashboard]
 */
function cls_student_dashboard_shortcode() {
    $access = cls_check_user_role_access( array( 'student' ) );

    if ( $access === 'not_logged_in' ) {
        // translators: %s: login URL
        return sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to view your dashboard.', 'custom-login-subscription' ) ), esc_url( wp_login_url( get_permalink() ) ) );
    } elseif ( $access === true ) {
        $output = '<h2>' . esc_html__( 'Student Dashboard', 'custom-login-subscription' ) . '</h2>';
        $output .= '<p>' . esc_html__( 'Welcome to your Student Dashboard! Content coming soon.', 'custom-login-subscription' ) . '</p>';
        // Future: List enrolled courses, progress, etc.
        return $output;
    } else {
        return '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'custom-login-subscription' ) . '</p>';
    }
}
add_shortcode( 'cls_student_dashboard', 'cls_student_dashboard_shortcode' );

/**
 * Instructor Dashboard Shortcode.
 * Usage: [cls_instructor_dashboard]
 */
function cls_instructor_dashboard_shortcode() {
    $access = cls_check_user_role_access( array( 'instructor' ) );
    $output = '';

    if ( $access === 'not_logged_in' ) {
         // translators: %s: login URL
        return sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to view your dashboard.', 'custom-login-subscription' ) ), esc_url( wp_login_url( get_permalink() ) ) );
    } elseif ( $access === true ) {
        $output .= '<h2>' . esc_html__( 'Instructor Dashboard', 'custom-login-subscription' ) . '</h2>';

        // Sanitize GET parameters
        $view_action = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
        $course_id_param = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        $lesson_action_param = isset($_GET['lesson_action']) ? sanitize_key($_GET['lesson_action']) : '';
        $lesson_id_param = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
        $assessment_action_param = isset($_GET['assessment_action']) ? sanitize_key($_GET['assessment_action']) : '';
        $assessment_id_param = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
        $current_user_id = get_current_user_id();
        $dashboard_page_url = get_permalink(get_the_ID()); // Page where this dashboard shortcode is placed

        if ( $view_action === 'manage_course_assessments' && $course_id_param > 0) {
            $parent_course_for_assessment = get_post($course_id_param);
            if ( !$parent_course_for_assessment || $parent_course_for_assessment->post_type !== 'course' || ($parent_course_for_assessment->post_author != $current_user_id && !current_user_can('administrator')) ) {
                 return '<p>' . esc_html__( 'Invalid course or you do not have permission to manage its assessments.', 'custom-login-subscription' ) . '</p>';
            }
            $output .= '<h3>' . sprintf(esc_html__('Managing Assessments for: %s', 'custom-login-subscription'), esc_html($parent_course_for_assessment->post_title)) . '</h3>';

            if ( $assessment_action_param === 'manage_assessment_form') {
                $assessment_id_attr = $assessment_id_param > 0 ? sprintf(' assessment_id="%d"', $assessment_id_param) : '';
                $output .= do_shortcode('[cls_manage_assessments_form' . $assessment_id_attr . ' course_id="' . $course_id_param . '"]'); // course_id attribute for context
                $output .= '<p><a href="' . esc_url(remove_query_arg(array('assessment_action', 'assessment_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Assessment List for this Course', 'custom-login-subscription') . '</a></p>';
            } else {
                $add_assessment_link = add_query_arg(array('assessment_action' => 'manage_assessment_form', 'course_id' => $course_id_param, 'view' => 'manage_course_assessments'), $dashboard_page_url);
                $output .= '<p><a href="' . esc_url($add_assessment_link) . '" class="button button-primary">' . esc_html__('Add New Assessment for this Course', 'custom-login-subscription') . '</a></p>';

                $assessments_args = array(
                    'post_type' => 'assessment', 'posts_per_page' => -1,
                    'meta_query' => array( array( 'key' => '_cls_assessment_associated_course_id', 'value' => $course_id_param, 'compare' => '=' ) ),
                    'orderby' => 'title', 'order' => 'ASC',
                );
                $assessments_query = new WP_Query($assessments_args);
                if ($assessments_query->have_posts()) {
                    $output .= '<ul class="cls-dashboard-assessment-list">';
                    while ($assessments_query->have_posts()) {
                        $assessments_query->the_post();
                        $assessment_id = get_the_ID();
                        $edit_assessment_link = add_query_arg(array('assessment_action' => 'manage_assessment_form', 'assessment_id' => $assessment_id, 'course_id' => $course_id_param, 'view' => 'manage_course_assessments'), $dashboard_page_url);
                        $output .= '<li><strong>' . esc_html(get_the_title()) . '</strong> (' . esc_html(get_post_meta($assessment_id, '_cls_assessment_type', true)) . ')';
                        $output .= ' | <a href="' . esc_url(get_permalink($assessment_id)) . '">' . esc_html__('View', 'custom-login-subscription') . '</a>';
                        $output .= ' | <a href="' . esc_url($edit_assessment_link) . '">' . esc_html__('Edit', 'custom-login-subscription') . '</a>';
                        $output .= '</li>';
                    }
                    $output .= '</ul>';
                    wp_reset_postdata();
                } else {
                    $output .= '<p>' . esc_html__('No assessments found for this course.', 'custom-login-subscription') . '</p>';
                }
            }
            $output .= '<p><a href="' . esc_url(remove_query_arg(array('view', 'course_id', 'assessment_action', 'assessment_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Courses List', 'custom-login-subscription') . '</a></p>';

        } elseif ( $view_action === 'manage_lessons' && $course_id_param > 0 ) {
            $parent_course = get_post($course_id_param);
            if ( !$parent_course || $parent_course->post_type !== 'course' || ($parent_course->post_author != $current_user_id && !current_user_can('administrator')) ) {
                 return '<p>' . esc_html__( 'Invalid course or you do not have permission to manage its lessons.', 'custom-login-subscription' ) . '</p>';
            }
            $output .= '<h3>' . sprintf(esc_html__('Managing Lessons for: %s', 'custom-login-subscription'), esc_html($parent_course->post_title)) . '</h3>';

            if ( $lesson_action_param === 'manage_lesson_form' ) {
                $lesson_id_attr = $lesson_id_param > 0 ? sprintf(' lesson_id="%d"', $lesson_id_param) : '';
                $output .= do_shortcode('[cls_manage_lessons_form course_id="' . $course_id_param . '"' . $lesson_id_attr . ']');
                $output .= '<p><a href="' . esc_url(remove_query_arg(array('lesson_action', 'lesson_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Lesson List for this Course', 'custom-login-subscription') . '</a></p>';
            } else {
                $add_lesson_link = add_query_arg(array('lesson_action' => 'manage_lesson_form', 'course_id' => $course_id_param, 'view' => 'manage_lessons'), $dashboard_page_url);
                $output .= '<p><a href="' . esc_url($add_lesson_link) . '" class="button button-primary">' . esc_html__('Add New Lesson', 'custom-login-subscription') . '</a></p>';

                $lessons_args = array(
                    'post_type' => 'lesson', 'post_parent' => $course_id_param, 'posts_per_page' => -1,
                    'orderby' => 'menu_order title', 'order' => 'ASC',
                );
                $lessons_query = new WP_Query($lessons_args);
                if ($lessons_query->have_posts()) {
                    $output .= '<ul class="cls-dashboard-lesson-list">';
                    while ($lessons_query->have_posts()) {
                        $lessons_query->the_post();
                        $lesson_id = get_the_ID();
                        $edit_lesson_link = add_query_arg(array('lesson_action' => 'manage_lesson_form', 'lesson_id' => $lesson_id, 'course_id' => $course_id_param, 'view' => 'manage_lessons'), $dashboard_page_url);
                        $output .= '<li><strong>' . esc_html(get_the_title()) . '</strong> (Order: ' . esc_html(get_post()->menu_order) . ')';
                        $output .= ' | <a href="' . esc_url($edit_lesson_link) . '">' . esc_html__('Edit', 'custom-login-subscription') . '</a></li>';
                    }
                    $output .= '</ul>';
                    wp_reset_postdata();
                } else {
                    $output .= '<p>' . esc_html__('No lessons found for this course.', 'custom-login-subscription') . '</p>';
                }
            }
             $output .= '<p><a href="' . esc_url(remove_query_arg(array('view', 'course_id', 'lesson_action', 'lesson_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Courses List', 'custom-login-subscription') . '</a></p>';

        } elseif ( $view_action === 'manage_form' ) {
            $course_id_attr = $course_id_param > 0 ? sprintf(' course_id="%d"', $course_id_param) : '';
            $output .= do_shortcode('[cls_manage_courses_form' . $course_id_attr . ']');
             $output .= '<p><a href="' . esc_url(remove_query_arg(array('view', 'course_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Courses List', 'custom-login-subscription') . '</a></p>';
        } else {
            $output .= '<p>' . esc_html__( 'Welcome to your Instructor Dashboard!', 'custom-login-subscription' ) . '</p>';
            $output .= '<h3>' . esc_html__( 'Your Courses', 'custom-login-subscription' ) . '</h3>';

            $args = array(
                'post_type'      => 'course', 'author'         => $current_user_id,
                'post_status'    => array('publish', 'pending', 'draft'), 'posts_per_page' => -1,
                'orderby'        => 'title', 'order'          => 'ASC',
            );
            $courses_query = new WP_Query( $args );

            if ( $courses_query->have_posts() ) {
                $output .= '<ul class="cls-dashboard-course-list">';
                while ( $courses_query->have_posts() ) {
                    $courses_query->the_post();
                    $course_id = get_the_ID();
                    $edit_link = add_query_arg( array('view' => 'manage_form', 'course_id' => $course_id), $dashboard_page_url );
                    $manage_lessons_link = add_query_arg( array('view' => 'manage_lessons', 'course_id' => $course_id), $dashboard_page_url );
                    $manage_assessments_link = add_query_arg( array('view' => 'manage_course_assessments', 'course_id' => $course_id), $dashboard_page_url);

                    $output .= '<li>';
                    $output .= '<strong>' . esc_html( get_the_title() ) . '</strong> (' . esc_html( get_post_status() ) . ')';
                    $output .= '<br>&nbsp;&nbsp;&nbsp;<a href="' . esc_url( get_permalink() ) . '">' . esc_html__( 'View Course', 'custom-login-subscription' ) . '</a>';
                    $output .= ' | <a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit Course', 'custom-login-subscription' ) . '</a>';
                    $output .= ' | <a href="' . esc_url( $manage_lessons_link ) . '">' . esc_html__( 'Manage Lessons', 'custom-login-subscription' ) . '</a>';
                    $output .= ' | <a href="' . esc_url( $manage_assessments_link ) . '">' . esc_html__( 'Manage Assessments', 'custom-login-subscription' ) . '</a>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
                wp_reset_postdata();
            } else {
                $output .= '<p>' . esc_html__( 'You have not created any courses yet.', 'custom-login-subscription' ) . '</p>';
            }
            $add_new_link = add_query_arg( array('view' => 'manage_form'), $dashboard_page_url );
            $output .= '<p><a href="' . esc_url( $add_new_link ) . '" class="button button-primary">' . esc_html__( 'Add New Course', 'custom-login-subscription' ) . '</a></p>';
        }
        return $output;
    } else {
        return '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'custom-login-subscription' ) . '</p>';
    }
}
add_shortcode( 'cls_instructor_dashboard', 'cls_instructor_dashboard_shortcode' );

/**
 * Institution Dashboard Shortcode.
 * Usage: [cls_institution_dashboard]
 */
function cls_institution_dashboard_shortcode() {
    $access = cls_check_user_role_access( array( 'institution' ) );
    $output = '';

    if ( $access === 'not_logged_in' ) {
        return sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to view your dashboard.', 'custom-login-subscription' ) ), esc_url( wp_login_url( get_permalink() ) ) );
    } elseif ( $access === true ) {
        $output .= '<h2>' . esc_html__( 'Institution Dashboard', 'custom-login-subscription' ) . '</h2>';

        $view_action = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
        $course_id_param = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        $lesson_action_param = isset($_GET['lesson_action']) ? sanitize_key($_GET['lesson_action']) : '';
        $lesson_id_param = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
        $assessment_action_param = isset($_GET['assessment_action']) ? sanitize_key($_GET['assessment_action']) : '';
        $assessment_id_param = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
        $current_user_id = get_current_user_id();
        $dashboard_page_url = get_permalink(get_the_ID());

        if ( $view_action === 'manage_course_assessments' && $course_id_param > 0) {
            $parent_course_for_assessment = get_post($course_id_param);
            $institution_id_on_course = get_post_meta($course_id_param, '_cls_course_institution_id', true);
            if ( !$parent_course_for_assessment || $parent_course_for_assessment->post_type !== 'course' || ($institution_id_on_course != $current_user_id && !current_user_can('administrator')) ) {
                 return '<p>' . esc_html__( 'Invalid course or you do not have permission to manage its assessments.', 'custom-login-subscription' ) . '</p>';
            }
            $output .= '<h3>' . sprintf(esc_html__('Managing Assessments for: %s', 'custom-login-subscription'), esc_html($parent_course_for_assessment->post_title)) . '</h3>';

            if ( $assessment_action_param === 'manage_assessment_form') {
                $assessment_id_attr = $assessment_id_param > 0 ? sprintf(' assessment_id="%d"', $assessment_id_param) : '';
                $output .= do_shortcode('[cls_manage_assessments_form' . $assessment_id_attr . ' course_id="' . $course_id_param . '"]');
                $output .= '<p><a href="' . esc_url(remove_query_arg(array('assessment_action', 'assessment_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Assessment List for this Course', 'custom-login-subscription') . '</a></p>';
            } else {
                $add_assessment_link = add_query_arg(array('assessment_action' => 'manage_assessment_form', 'course_id' => $course_id_param, 'view' => 'manage_course_assessments'), $dashboard_page_url);
                $output .= '<p><a href="' . esc_url($add_assessment_link) . '" class="button button-primary">' . esc_html__('Add New Assessment for this Course', 'custom-login-subscription') . '</a></p>';
                $assessments_args = array(
                    'post_type' => 'assessment', 'posts_per_page' => -1,
                    'meta_query' => array( array( 'key' => '_cls_assessment_associated_course_id', 'value' => $course_id_param, 'compare' => '=' ) ),
                    'orderby' => 'title', 'order' => 'ASC',
                );
                $assessments_query = new WP_Query($assessments_args);
                if ($assessments_query->have_posts()) {
                    $output .= '<ul class="cls-dashboard-assessment-list">';
                    while ($assessments_query->have_posts()) {
                        $assessments_query->the_post();
                        $assessment_id = get_the_ID();
                        $edit_assessment_link = add_query_arg(array('assessment_action' => 'manage_assessment_form', 'assessment_id' => $assessment_id, 'course_id' => $course_id_param, 'view' => 'manage_course_assessments'), $dashboard_page_url);
                        $output .= '<li><strong>' . esc_html(get_the_title()) . '</strong> (' . esc_html(get_post_meta($assessment_id, '_cls_assessment_type', true)) . ')';
                        $output .= ' | <a href="' . esc_url(get_permalink($assessment_id)) . '">' . esc_html__('View', 'custom-login-subscription') . '</a>';
                        $output .= ' | <a href="' . esc_url($edit_assessment_link) . '">' . esc_html__('Edit', 'custom-login-subscription') . '</a></li>';
                    }
                    $output .= '</ul>';
                    wp_reset_postdata();
                } else {
                    $output .= '<p>' . esc_html__('No assessments found for this course.', 'custom-login-subscription') . '</p>';
                }
            }
            $output .= '<p><a href="' . esc_url(remove_query_arg(array('view', 'course_id', 'assessment_action', 'assessment_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Courses List', 'custom-login-subscription') . '</a></p>';

        } elseif ( $view_action === 'manage_lessons' && $course_id_param > 0 ) {
            $parent_course = get_post($course_id_param);
            $institution_id_on_course = get_post_meta($course_id_param, '_cls_course_institution_id', true);
            if ( !$parent_course || $parent_course->post_type !== 'course' || ($institution_id_on_course != $current_user_id && !current_user_can('administrator')) ) {
                 return '<p>' . esc_html__( 'Invalid course or you do not have permission to manage its lessons.', 'custom-login-subscription' ) . '</p>';
            }
            $output .= '<h3>' . sprintf(esc_html__('Managing Lessons for: %s', 'custom-login-subscription'), esc_html($parent_course->post_title)) . '</h3>';

            if ( $lesson_action_param === 'manage_lesson_form' ) {
                $lesson_id_attr = $lesson_id_param > 0 ? sprintf(' lesson_id="%d"', $lesson_id_param) : '';
                $output .= do_shortcode('[cls_manage_lessons_form course_id="' . $course_id_param . '"' . $lesson_id_attr . ']');
                $output .= '<p><a href="' . esc_url(remove_query_arg(array('lesson_action', 'lesson_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Lesson List for this Course', 'custom-login-subscription') . '</a></p>';
            } else {
                $add_lesson_link = add_query_arg(array('lesson_action' => 'manage_lesson_form', 'course_id' => $course_id_param, 'view' => 'manage_lessons'), $dashboard_page_url);
                $output .= '<p><a href="' . esc_url($add_lesson_link) . '" class="button button-primary">' . esc_html__('Add New Lesson', 'custom-login-subscription') . '</a></p>';
                $lessons_args = array(
                    'post_type' => 'lesson', 'post_parent' => $course_id_param, 'posts_per_page' => -1,
                    'orderby' => 'menu_order title', 'order' => 'ASC',
                );
                $lessons_query = new WP_Query($lessons_args);
                if ($lessons_query->have_posts()) {
                    $output .= '<ul class="cls-dashboard-lesson-list">';
                    while ($lessons_query->have_posts()) {
                        $lessons_query->the_post();
                        $lesson_id = get_the_ID();
                        $edit_lesson_link = add_query_arg(array('lesson_action' => 'manage_lesson_form', 'lesson_id' => $lesson_id, 'course_id' => $course_id_param, 'view' => 'manage_lessons'), $dashboard_page_url);
                        $output .= '<li><strong>' . esc_html(get_the_title()) . '</strong> (Order: ' . esc_html(get_post()->menu_order) . ')';
                        $output .= ' | <a href="' . esc_url($edit_lesson_link) . '">' . esc_html__('Edit', 'custom-login-subscription') . '</a></li>';
                    }
                    $output .= '</ul>';
                    wp_reset_postdata();
                } else {
                    $output .= '<p>' . esc_html__('No lessons found for this course.', 'custom-login-subscription') . '</p>';
                }
            }
            $output .= '<p><a href="' . esc_url(remove_query_arg(array('view', 'course_id', 'lesson_action', 'lesson_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Courses List', 'custom-login-subscription') . '</a></p>';

        } elseif ( $view_action === 'manage_form' ) {
            $course_id_attr = $course_id_param > 0 ? sprintf(' course_id="%d"', $course_id_param) : '';
            $output .= do_shortcode('[cls_manage_courses_form' . $course_id_attr . ']');
            $output .= '<p><a href="' . esc_url(remove_query_arg(array('view', 'course_id'), $dashboard_page_url)) . '">' . esc_html__('&laquo; Back to Courses List', 'custom-login-subscription') . '</a></p>';
        } else {
            $output .= '<p>' . esc_html__( 'Welcome to your Institution Dashboard!', 'custom-login-subscription' ) . '</p>';
            $output .= '<h3>' . esc_html__( 'Courses Associated with this Institution', 'custom-login-subscription' ) . '</h3>';

            $args = array(
                'post_type'      => 'course',
                'meta_query'     => array( array( 'key' => '_cls_course_institution_id', 'value' => $current_user_id, 'compare' => '=',)),
                'post_status'    => array('publish', 'pending', 'draft'), 'posts_per_page' => -1,
                'orderby'        => 'title', 'order'          => 'ASC',
            );
            $courses_query = new WP_Query( $args );

            if ( $courses_query->have_posts() ) {
                $output .= '<ul class="cls-dashboard-course-list">';
                while ( $courses_query->have_posts() ) {
                    $courses_query->the_post();
                    $course_id = get_the_ID();
                    $edit_link = add_query_arg( array('view' => 'manage_form', 'course_id' => $course_id), $dashboard_page_url );
                    $manage_lessons_link = add_query_arg( array('view' => 'manage_lessons', 'course_id' => $course_id), $dashboard_page_url );
                    $manage_assessments_link = add_query_arg( array('view' => 'manage_course_assessments', 'course_id' => $course_id), $dashboard_page_url);

                    $output .= '<li>';
                    $output .= '<strong>' . esc_html( get_the_title() ) . '</strong> (' . esc_html( get_post_status() ) . ')';
                    $output .= '<br>&nbsp;&nbsp;&nbsp;<a href="' . esc_url( get_permalink() ) . '">' . esc_html__( 'View Course', 'custom-login-subscription' ) . '</a>';
                    $output .= ' | <a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit Course', 'custom-login-subscription' ) . '</a>';
                    $output .= ' | <a href="' . esc_url( $manage_lessons_link ) . '">' . esc_html__( 'Manage Lessons', 'custom-login-subscription' ) . '</a>';
                    $output .= ' | <a href="' . esc_url( $manage_assessments_link ) . '">' . esc_html__( 'Manage Assessments', 'custom-login-subscription' ) . '</a>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
                wp_reset_postdata();
            } else {
                $output .= '<p>' . esc_html__( 'No courses are currently associated with this institution.', 'custom-login-subscription' ) . '</p>';
            }
            $add_new_link = add_query_arg( array('view' => 'manage_form'), $dashboard_page_url );
            $output .= '<p><a href="' . esc_url( $add_new_link ) . '" class="button button-primary">' . esc_html__( 'Add New Course', 'custom-login-subscription' ) . '</a></p>';
        }
        return $output;
    } else {
        return '<p>' . esc_html__( 'You do not have permission to view this dashboard.', 'custom-login-subscription' ) . '</p>';
    }
}
add_shortcode( 'cls_institution_dashboard', 'cls_institution_dashboard_shortcode' );

?>
