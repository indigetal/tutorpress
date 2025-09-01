<?php
/**
 * Custom Template Loader for Tutor LMS
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_Template_Overrides {

    public static function init() {
        add_filter( 'template_include', array( __CLASS__, 'template_loader' ), 99 );
    }

    public static function template_loader( $template ) {
        // Use Freemius-aware wrapper to read template override settings safely
        if ( function_exists('tutorpress_get_setting') ) {
            $template_overrides = tutorpress_get_setting('template_overrides', []);
        } else {
            $options = get_option('tutorpress_settings', []);
            $template_overrides = isset($options['template_overrides']) ? $options['template_overrides'] : [];
        }

        // Check if course archive override is enabled
        if (isset($template_overrides['course_archive']) && '1' === $template_overrides['course_archive']) {
            if ( is_post_type_archive( 'courses' ) ) {
                // Let WordPress handle the template via default template hierarchy
                $new_template = locate_template( array( 'archive-course.php' ) );

                if ( $new_template ) {
                    return $new_template;
                }

                // Default to WordPress's archive.php if no specific template exists
                return locate_template( array( 'archive.php' ) );
            }
        }
        
        // Add more template override logic here in the future

        return $template;
    }
}

// Initialize the class
TutorPress_Template_Overrides::init();
