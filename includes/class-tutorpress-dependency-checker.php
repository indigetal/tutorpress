<?php
/**
 * TutorPress Dependency Checker
 *
 * Checks core system requirements before plugin initialization.
 * Replaces the tutor_loaded action hook with systematic dependency checking.
 *
 * @package TutorPress
 * @since 1.13.17
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TutorPress Dependencies Check
 *
 * @since 1.13.17
 */
class TutorPress_Dependency_Checker {
    
    /**
     * Minimum PHP version required
     */
    const MINIMUM_PHP_VERSION = '7.4';
    
    /**
     * Minimum WordPress version required
     */
    const MINIMUM_WP_VERSION = '5.0';
    
    /**
     * Check all core requirements for TutorPress
     *
     * Replaces the tutor_loaded action hook with systematic dependency checking.
     * Prevents plugin loading if core requirements are not met.
     *
     * @return array Array of error messages, empty if all requirements met
     */
    public static function check_all_requirements() {
        $errors = [];
        
        // Check PHP version
        if ( ! self::check_php_requirement() ) {
            $errors[] = sprintf(
                __( 'TutorPress requires PHP %s or higher, but you are running %s.', 'tutorpress' ),
                self::MINIMUM_PHP_VERSION,
                phpversion()
            );
        }
        
        // Check WordPress version
        if ( ! self::check_wordpress_requirement() ) {
            $errors[] = sprintf(
                __( 'TutorPress requires WordPress %s or higher, but you are running %s.', 'tutorpress' ),
                self::MINIMUM_WP_VERSION,
                get_bloginfo( 'version' )
            );
        }
        
        // Check Tutor LMS requirement (replaces tutor_loaded action)
        if ( ! self::check_tutor_lms_requirement() ) {
            $errors[] = __( 'Tutor LMS plugin is required for TutorPress to function.', 'tutorpress' );
        }
        
        return $errors;
    }
    
    /**
     * Check PHP version requirement
     *
     * @return bool
     */
    public static function check_php_requirement() {
        return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
    }
    
    /**
     * Check WordPress version requirement
     *
     * @return bool
     */
    public static function check_wordpress_requirement() {
        return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
    }
    
    /**
     * Check if Tutor LMS is available
     *
     * @return bool
     */
    public static function check_tutor_lms_requirement() {
        return function_exists( 'tutor' );
    }
    
    /**
     * Display error messages as admin notices
     *
     * @param array $errors Array of error messages
     */
    public static function display_errors( $errors ) {
        if ( empty( $errors ) ) {
            return;
        }
        
        foreach ( $errors as $error ) {
            add_action( 'admin_notices', function() use ( $error ) {
                self::show_admin_notice( $error );
            });
        }
    }
    
    /**
     * Show a single admin notice
     *
     * @param string $message The message to display
     */
    private static function show_admin_notice( $message ) {
        $screen = get_current_screen();
        $valid_screens = array( 'dashboard', 'plugins', 'toplevel_page_tutorpress-settings' );
        
        // Only show to users who can activate plugins
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        
        // Only show on relevant screens
        if ( $screen && ! in_array( $screen->id, $valid_screens, true ) ) {
            return;
        }
        
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html( $message )
        );
    }
} 