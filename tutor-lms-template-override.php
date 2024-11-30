<?php
/*
Plugin Name: Tutor LMS Template Override
Description: Overrides the template loader for Tutor LMS to enable custom templates and support for Blocksy Content Blocks.
Version: 1.0.0
Author: Brandon Meyer
*/

add_action( 'plugins_loaded', 'override_tutor_lms_template_loader', 20 );

function override_tutor_lms_template_loader() {
    if ( class_exists( 'TUTOR\Template' ) ) {
        $template_instance = TUTOR()->template;

        // Remove the original template loader for course archive
        remove_filter( 'template_include', array( $template_instance, 'load_course_archive_template' ), 99 );

        // Include and initialize our custom template loader
        require_once plugin_dir_path( __FILE__ ) . 'class-tutor-lms-template-loader.php';
        Custom_Tutor_LMS_Template_Loader::init();
    }
}
