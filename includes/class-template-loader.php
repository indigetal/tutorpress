<?php
/**
 * Custom Template Loader for Tutor LMS
 */

defined( 'ABSPATH' ) || exit;

class Custom_Tutor_LMS_Template_Loader {

    public static function init() {
        $options = get_option('tutorpress_settings', []);
        
        if (!isset($options['enable_template_loader']) || !$options['enable_template_loader']) {
            return;
        }
        
        add_filter( 'template_include', array( __CLASS__, 'template_loader' ), 99 );
    }

    public static function template_loader( $template ) {
        if ( is_post_type_archive( 'courses' ) ) {
            // Let WordPress handle the template via default template hierarchy
            $new_template = locate_template( array( 'archive-course.php' ) );

            if ( $new_template ) {
                return $new_template;
            }

            // Default to WordPress's archive.php if no specific template exists
            return locate_template( array( 'archive.php' ) );
        }

        return $template;
    }
}

// Initialize the class only if the feature is enabled
$options = get_option('tutorpress_settings', []);
if (!empty($options['enable_template_loader'])) {
    Custom_Tutor_LMS_Template_Loader::init();
}
