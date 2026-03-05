<?php
/**
 * PECE Child Theme Functions
 *
 * This file loads the parent theme styles and adds any custom functionality
 * specific to the PECE platform that belongs in the theme layer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue parent and child theme styles.
 */
add_action( 'wp_enqueue_scripts', 'pece_child_enqueue_styles' );
function pece_child_enqueue_styles() {
    // Parent theme stylesheet
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );

    // Child theme stylesheet (loads after parent)
    wp_enqueue_style(
        'pece-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'parent-style' ),
        wp_get_theme()->get( 'Version' )
    );
}

/**
 * Set default role for new self-registered users.
 * New User Approve will hold them in pending status regardless,
 * but this ensures they get the right default role once approved.
 *
 * NOTE: If using ProfilePress registration with a role dropdown,
 * ProfilePress handles role assignment and this filter may not be needed.
 * Keep it as a safety net.
 */
add_filter( 'pre_option_default_role', 'pece_default_registration_role' );
function pece_default_registration_role( $default_role ) {
    return 'cbo_member'; // Safest default; admin can change after approval
}

/**
 * Customize the "pending approval" message shown to users after registration.
 */
add_filter( 'new_user_approve_pending_message', 'pece_custom_pending_message' );
function pece_custom_pending_message( $message ) {
    return 'Thank you for registering! Your account is pending approval by our team. '
         . 'You will receive an email once your account has been activated. '
         . 'If you have questions, please contact us at the email on our Contact page.';
}

/**
 * Add admin notification when a new user registers and needs approval.
 * This supplements New User Approve's built-in notification.
 */
add_action( 'new_user_approve_user_approved', 'pece_log_user_approval' );
function pece_log_user_approval( $user_id ) {
    $user = get_userdata( $user_id );
    if ( $user ) {
        error_log( sprintf(
            'PECE: User approved - %s (%s), Role: %s',
            $user->display_name,
            $user->user_email,
            implode( ', ', $user->roles )
        ) );
    }
}
