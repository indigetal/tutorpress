<?php
/**
 * Plugin Name: TutorPress
 * Description: Restores backend Gutenberg editing for Tutor LMS courses and lessons, modernizing the backend UI and streamlining the course creation workflow. Enables dynamic template overrides, custom metadata storage, and other enhancements for a seamless integration with Gutenberg, WordPress core, and third-party plugins.
 * Version: 2.0.3
 * Author: Indigetal WebCraft
 * Author URI: https://indigetal.com/tutorpress
 */

if ( ! function_exists( 'tutorpress_fs' ) ) {
    // Create a helper function for easy SDK access.
    function tutorpress_fs() {
        global $tutorpress_fs;

        if ( ! isset( $tutorpress_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
            $tutorpress_fs = fs_dynamic_init( array(
                'id'                  => '18606',
                'slug'                => 'tutorpress',
                'premium_slug'        => 'tutorpress',
                'type'                => 'plugin',
                'public_key'          => 'pk_703b19a55bb9391b8f8dabb350543',
                'is_premium'          => true,
                'is_premium_only'     => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => false,
                'trial'               => array(
                    'days'               => 14,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'slug'           => 'tutorpress-settings',
                    'support'        => false,
                    'parent'         => array(
                        'slug' => 'tutor',
                    ),
                ),
            ) );
        }

        return $tutorpress_fs;
    }

    // Init Freemius.
    tutorpress_fs();
    // Signal that SDK was initiated.
    do_action( 'tutorpress_fs_loaded' );
}

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('TUTORPRESS_PATH', plugin_dir_path(__FILE__));
define('TUTORPRESS_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
require_once TUTORPRESS_PATH . 'vendor/autoload.php';

// Load main orchestrator
require_once TUTORPRESS_PATH . 'includes/class-tutorpress.php';

// Initialize TutorPress
TutorPress_Main::instance( array( 'main_file' => __FILE__ ) );

// Global access function
if ( ! function_exists( 'TutorPress' ) ) {
    /**
     * Returns the global TutorPress Instance.
     *
     * @return TutorPress_Main
     */
    function TutorPress() {
	return TutorPress_Main::instance();
}
}

// Modify assignment post type to enable WordPress admin UI
add_action('init', function() {
    // Check if Tutor LMS has registered the assignment post type
    if (post_type_exists('tutor_assignments')) {
        // Get the current post type object
        $assignment_post_type = get_post_type_object('tutor_assignments');
        
        if ($assignment_post_type) {
            // Enable admin UI for assignments
            $assignment_post_type->show_ui = true;
            $assignment_post_type->show_in_menu = false; // Keep it out of the main menu
            $assignment_post_type->public = true;
            $assignment_post_type->publicly_queryable = true;
            
            // Enable Gutenberg editor support for assignments
            $enable_gutenberg = (bool) tutor_utils()->get_option('enable_gutenberg_course_edit');
            if ($enable_gutenberg) {
                $assignment_post_type->show_in_rest = true;
            }
            
            // Enable REST API support for individual meta fields to work
            if (!$assignment_post_type->show_in_rest) {
                $assignment_post_type->show_in_rest = true;
            }
            
            // Enable Gutenberg for assignments
            if (!post_type_supports('tutor_assignments', 'editor')) {
                add_post_type_support('tutor_assignments', 'editor');
            }
            
            // Enable custom-fields support for assignments
            if (!post_type_supports('tutor_assignments', 'custom-fields')) {
                add_post_type_support('tutor_assignments', 'custom-fields');
            }
        }
    }
}, 20); // Priority 20 to run after Tutor LMS registration

// Enable custom-fields support for course-bundle post type (required for meta fields via REST API)
add_action('init', function() {
    // Check if Tutor LMS Pro has registered the course-bundle post type
    if (post_type_exists('course-bundle')) {
        // Enable custom-fields support (required for meta fields to be accessible via REST API)
        if (!post_type_supports('course-bundle', 'custom-fields')) {
            add_post_type_support('course-bundle', 'custom-fields');
        }
    }
}, 20); // Priority 20 to run after Tutor LMS registration
