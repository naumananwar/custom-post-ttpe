<?php
/**
 * Custom User Roles for Custom Login & Subscription Plugin.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds custom user roles to the site.
 * This function should be called on plugin activation.
 */
function cls_add_custom_user_roles() {
    // Student Role
    add_role(
        'student',
        __( 'Student', 'custom-login-subscription' ),
        array(
            'read' => true, // Basic capability to read public content
        )
    );

    // Instructor Role
    add_role(
        'instructor',
        __( 'Instructor', 'custom-login-subscription' ),
        array(
            'read' => true,
            'edit_posts' => true,       // Can edit their own posts
            'upload_files' => true,     // Can upload media
            'delete_posts' => true,     // Can delete their own posts
            // 'publish_posts' => true, // Might be granted if they can publish directly
            // Consider adding specific CPT capabilities later if needed
        )
    );

    // Institution Role
    // For 'edit_others_posts', 'publish_posts', etc., ensure the CPTs they manage support these.
    // These are powerful capabilities.
    add_role(
        'institution',
        __( 'Institution', 'custom-login-subscription' ),
        array(
            'read' => true,
            'edit_posts' => true,           // Can edit their own posts
            'edit_others_posts' => true,    // Can edit posts by others (e.g., instructors under them)
            'publish_posts' => true,        // Can publish posts directly
            'upload_files' => true,
            'delete_posts' => true,
            'delete_others_posts' => true,  // Can delete posts by others
            // 'manage_categories' => true, // If they manage taxonomies
            // Consider adding specific CPT capabilities and user management capabilities later
        )
    );
}

/**
 * Removes custom user roles from the site.
 * This function should be called on plugin deactivation.
 */
function cls_remove_custom_user_roles() {
    // Check if users exist with these roles before removing.
    // WordPress might not remove roles if users are assigned to them.
    // However, remove_role() itself is safe to call.

    if ( get_role( 'student' ) ) {
        remove_role( 'student' );
    }
    if ( get_role( 'instructor' ) ) {
        remove_role( 'instructor' );
    }
    if ( get_role( 'institution' ) ) {
        remove_role( 'institution' );
    }
}

?>
