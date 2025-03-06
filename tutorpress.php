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
