<?php
/*
Plugin Name: Tutor LMS Advanced Customization Toolkit
Description: A powerful integration tool for Tutor LMS, making it compatible with Blocksy's Content Blocks and advanced query systems like GreenShift. Features include dynamic template overrides, course metadata storage, and seamless Gutenberg-based customization.
Version: 1.1.1
Author: Brandon Meyer
*/

// Load dependencies.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-template-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-metadata-handler.php';

// Override the course archive template loader.
add_action( 'tutor_loaded', 'override_tutor_lms_template_loader', 20 );
function override_tutor_lms_template_loader() {
    global $wp_filter;

    // Debug active template_include filters (commented out for cleanup).
    // if ( isset( $wp_filter['template_include'] ) ) {
    //     error_log( 'Active template_include filters: ' . print_r( $wp_filter['template_include'], true ) );
    // }

    if ( class_exists( 'TUTOR\Template' ) ) {
        $template_class = new TUTOR\Template();

        // Remove the course archive template loader.
        remove_filter( 'template_include', array( $template_class, 'load_course_archive_template' ), 99 );

        // Log success for debugging (commented out for cleanup).
        // error_log( "Successfully removed Tutor LMS course archive template loader." );

        // Initialize custom template loader.
        Custom_Tutor_LMS_Template_Loader::init();
    } else {
        // Log fallback if TUTOR\Template is not accessible (commented out for cleanup).
        // error_log( "TUTOR\Template class not available. Manual removal skipped." );
    }
}

// Initialize metadata handler after Tutor LMS is fully loaded.
add_action( 'tutor_loaded', function() {
    Tutor_LMS_Metadata_Handler::init();
});

// Force WordPress default behavior for the course archive template.
add_filter( 'template_include', function( $template ) {
    if ( is_post_type_archive( 'courses' ) ) {
        // Debugging: Log the override for course archive (commented out for cleanup).
        // error_log( "Applying custom override for course archive template." );

        // Use default WordPress template hierarchy.
        return locate_template( 'archive-course.php' ) ?: locate_template( 'archive.php' );
    }
    return $template;
}, 100 );
