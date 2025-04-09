<?php
/*
Plugin Name: TutorPress
Description: Restores backend Gutenberg editing for Tutor LMS courses and lessons, modernizing the backend UI and streamlining the course creation workflow. Enables dynamic template overrides, custom metadata storage, and other enhancements for a seamless integration with Gutenberg, WordPress core, and third-party plugins.
Version: 1.2.7
Author: Brandon Meyer
*/

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

// Initialize classes when Tutor LMS is fully loaded.
add_action('tutor_loaded', function () {
    Tutor_LMS_Metadata_Handler::init(); // Metadata handler
});

/* Freemius Integration Start */
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
                    'slug'    => 'tutorpress-settings',
                    'contact' => false,
                    'support' => false,
                    'parent'  => array(
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

if ( function_exists( 'tutorpress_fs' ) ) {
    tutorpress_fs()->set_basename( true, __FILE__ );
}
/* Freemius Integration End */
