<?php
/**
 * TutorPress Addon Checker Utility
 *
 * @description Reusable utility class for checking Tutor LMS Pro addon availability.
 *              Provides consistent methods for detecting enabled addons across the plugin.
 *              Replaces one-off addon detection implementations with centralized logic.
 *
 * @package TutorPress
 * @subpackage Gutenberg\Utilities
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Checker utility class
 */
class TutorPress_Addon_Checker {
    
    /**
     * Cache for addon availability checks to avoid repeated file system operations
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Supported addons with their file paths and identifiers
     *
     * @var array
     */
    private static $addon_configs = [
        'course_preview' => [
            'file' => 'tutor-pro/addons/tutor-course-preview/tutor-course-preview.php',
            'basename' => 'tutor-pro/addons/tutor-course-preview/tutor-course-preview.php',
            'constant' => 'TUTOR_CP_VERSION',
            'class' => 'TUTOR_CP\CoursePreview',
        ],
        'google_meet' => [
            'file' => 'tutor-pro/addons/google-meet/google-meet.php',
            'basename' => 'tutor-pro/addons/google-meet/google-meet.php',
            'constant' => null, // Google Meet doesn't define a version constant
            'class' => 'TutorPro\GoogleMeet\GoogleMeet',
        ],
        'zoom' => [
            'file' => 'tutor-pro/addons/tutor-zoom/tutor-zoom.php',
            'basename' => 'tutor-pro/addons/tutor-zoom/tutor-zoom.php',
            'constant' => 'TUTOR_ZOOM_VERSION',
            'class' => 'TUTOR_ZOOM\Init',
        ],
        'h5p' => [
            'file' => 'tutor-pro/addons/h5p/h5p.php',
            'basename' => 'tutor-pro/addons/h5p/h5p.php',
            'constant' => 'TUTOR_H5P_VERSION',
            'class' => 'TutorPro\H5P\H5P',
        ],
    ];

    /**
     * Check if a specific addon is available and enabled
     *
     * @param string $addon_key The addon key (course_preview, google_meet, zoom)
     * @return bool True if addon is available and enabled
     */
    public static function is_addon_enabled($addon_key) {
        // Return cached result if available
        if (isset(self::$cache[$addon_key])) {
            return self::$cache[$addon_key];
        }

        // Check if addon config exists
        if (!isset(self::$addon_configs[$addon_key])) {
            self::$cache[$addon_key] = false;
            return false;
        }

        $config = self::$addon_configs[$addon_key];
        $result = self::check_addon_availability($config);
        
        // Cache the result
        self::$cache[$addon_key] = $result;
        
        return $result;
    }

    /**
     * Check if Course Preview addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_course_preview_enabled() {
        return self::is_addon_enabled('course_preview');
    }

    /**
     * Check if Google Meet addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_google_meet_enabled() {
        return self::is_addon_enabled('google_meet');
    }

    /**
     * Check if Zoom addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_zoom_enabled() {
        return self::is_addon_enabled('zoom');
    }

    /**
     * Check if H5P addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_h5p_enabled() {
        return self::is_addon_enabled('h5p');
    }

    /**
     * Get availability status for all supported addons
     *
     * @return array Associative array of addon availability
     */
    public static function get_all_addon_status() {
        $status = [];
        foreach (array_keys(self::$addon_configs) as $addon_key) {
            $status[$addon_key] = self::is_addon_enabled($addon_key);
        }
        return $status;
    }

    /**
     * Core logic for checking addon availability
     *
     * @param array $config Addon configuration
     * @return bool True if addon is available and enabled
     */
    private static function check_addon_availability($config) {
        // Primary check: Look for the specific addon file
        $addon_file = WP_PLUGIN_DIR . '/' . $config['file'];
        
        if (!file_exists($addon_file)) {
            return false;
        }
        
        // Check if Tutor Pro is active and addon is enabled using proper Tutor method
        if (function_exists('tutor_utils')) {
            $utils = tutor_utils();
            $addon_basename = $config['basename'];
            
            // Check if the addon is enabled in Tutor's addon system
            if (method_exists($utils, 'is_addon_enabled')) {
                return $utils->is_addon_enabled($addon_basename);
            }
            
            // Fallback: Check tutor options for addon status
            $tutor_options = get_option('tutor_option', array());
            if (isset($tutor_options['tutor_pro_addons'])) {
                $addons = $tutor_options['tutor_pro_addons'];
                
                if (isset($addons[$addon_basename])) {
                    return !empty($addons[$addon_basename]);
                }
            }
        }
        
        // Final fallback: Check for constant or class (but only if file exists)
        $constant_check = $config['constant'] ? defined($config['constant']) : false;
        $class_check = $config['class'] ? class_exists($config['class']) : false;
        
        return $constant_check || $class_check;
    }

    /**
     * Clear the addon availability cache
     * Useful for testing or when addon status might change
     *
     * @return void
     */
    public static function clear_cache() {
        self::$cache = [];
    }

    /**
     * Get supported addon keys
     *
     * @return array List of supported addon keys
     */
    public static function get_supported_addons() {
        return array_keys(self::$addon_configs);
    }
} 