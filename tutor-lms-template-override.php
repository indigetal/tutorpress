<?php
/*
Plugin Name: Tutor LMS Advanced Integration Toolkit
Description: A powerful integration tool for Tutor LMS, making it compatible with Blocksy's Content Blocks and advanced query systems like GreenShift. Features include dynamic template overrides, course metadata storage, and seamless Gutenberg-based customization.
Version: 1.1.0
Author: Brandon Meyer
*/

// Load dependencies.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-template-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-metadata-handler.php';

// Initialize Template Loader.
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'TUTOR\Template' ) ) {
        $template_instance = TUTOR()->template;

        // Remove the original template loader for course archive.
        remove_filter( 'template_include', array( $template_instance, 'load_course_archive_template' ), 99 );

        // Initialize custom template loader.
        Custom_Tutor_LMS_Template_Loader::init();
    }

    // Initialize Metadata Handler.
    Tutor_LMS_Metadata_Handler::init();
}, 20 );
