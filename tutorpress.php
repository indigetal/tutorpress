<?php
/**
 * Plugin Name: TutorPress
 * Description: Restores backend Gutenberg editing for Tutor LMS courses and lessons, modernizing the backend UI and streamlining the course creation workflow. Enables dynamic template overrides, custom metadata storage, and other enhancements for a seamless integration with Gutenberg, WordPress core, and third-party plugins.
 * Version: 1.11.31
 * Author: Indigetal WebCraft
 * Author URI: https://tutorpress.indigetal.com
 *
 * @fs_premium_only /includes/gutenberg/
 */

// Freemius Integration Start
if ( ! function_exists( 'tutorpress_fs' ) ) {
    // Create a helper function for easy SDK access.
    function tutorpress_fs() {
        global $tutorpress_fs;

        if ( ! isset( $tutorpress_fs ) ) {
            // Activate multisite network integration.
            if ( ! defined( 'WP_FS__PRODUCT_18606_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_18606_MULTISITE', true );
            }

            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
            $tutorpress_fs = fs_dynamic_init( array(
                'id'                  => '18606',
                'slug'                => 'tutorpress',
                'premium_slug'        => 'is-premium',
                'type'                => 'plugin',
                'public_key'          => 'pk_703b19a55bb9391b8f8dabb350543',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'trial'               => array(
                    'days'               => 7,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'slug'           => 'tutorpress-settings',
                    'contact'        => false,
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
// Freemius Integration End

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('TUTORPRESS_PATH', plugin_dir_path(__FILE__));
define('TUTORPRESS_URL', plugin_dir_url(__FILE__));

// Load dependencies.
require_once TUTORPRESS_PATH . 'includes/class-settings.php';
require_once TUTORPRESS_PATH . 'includes/class-template-loader.php';
require_once TUTORPRESS_PATH . 'includes/class-metadata-handler.php';
require_once TUTORPRESS_PATH . 'includes/class-admin-customizations.php';
require_once TUTORPRESS_PATH . 'includes/class-dashboard-customizations.php';
require_once TUTORPRESS_PATH . 'includes/class-sidebar-tabs.php';
require_once TUTORPRESS_PATH . 'includes/class-scripts.php';
require_once TUTORPRESS_PATH . 'includes/class-rest.php';

require_once TUTORPRESS_PATH . 'includes/gutenberg/utilities/class-addon-checker.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/metaboxes/class-curriculum-metabox.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/metaboxes/class-certificate-metabox.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/metaboxes/class-additional-content-metabox.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/settings/class-assignment-settings.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/settings/class-lesson-settings.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/settings/class-course-settings.php';
require_once TUTORPRESS_PATH . 'includes/gutenberg/settings/class-content-drip-helpers.php';

// Load REST controllers early
require_once TUTORPRESS_PATH . 'includes/rest/class-rest-controller.php';
require_once TUTORPRESS_PATH . 'includes/rest/class-lessons-controller.php';
require_once TUTORPRESS_PATH . 'includes/rest/class-topics-controller.php';
require_once TUTORPRESS_PATH . 'includes/rest/class-assignments-controller.php';
require_once TUTORPRESS_PATH . 'includes/rest/class-quizzes-controller.php';

// Initialize lesson handling
TutorPress_REST_Lessons_Controller::init();

// Initialize assignment handling
TutorPress_REST_Assignments_Controller::init();

// Initialize quiz handling
TutorPress_REST_Quizzes_Controller::init();

// Initialize assignment settings
TutorPress_Assignment_Settings::init();

// Initialize lesson settings
TutorPress_Lesson_Settings::init();

// Initialize course settings
TutorPress_Course_Settings::init();

// Initialize Content Drip helpers
TutorPress_Content_Drip_Helpers::init();

// Initialize REST API early
add_action('init', function() {
    new TutorPress_REST();
});

// Initialize classes when Tutor LMS is fully loaded.
add_action('tutor_loaded', function () {
    Tutor_LMS_Metadata_Handler::init(); // Metadata handler
});

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
            
            // Enable Gutenberg editor support (same as lessons)
            $enable_gutenberg = (bool) tutor_utils()->get_option('enable_gutenberg_course_edit');
            if ($enable_gutenberg) {
                $assignment_post_type->show_in_rest = true;
            }
            
            // Enable Gutenberg for assignments if not already enabled
            if (!post_type_supports('tutor_assignments', 'editor')) {
                add_post_type_support('tutor_assignments', 'editor');
            }
        }
    }
}, 20); // Priority 20 to run after Tutor LMS registration
