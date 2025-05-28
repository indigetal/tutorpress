<?php
/**
 * Content Drip Helper Functions
 *
 * Provides compatibility functions for Tutor LMS Pro's Content Drip addon.
 * Used across multiple post types (assignments, lessons, quizzes, etc.).
 *
 * @package TutorPress
 * @since 1.3.0
 */

defined('ABSPATH') || exit;

class TutorPress_Content_Drip_Helpers {

    /**
     * Initialize the Content Drip helpers.
     *
     * @since 1.3.0
     * @return void
     */
    public static function init() {
        // Register global helper functions
        self::register_global_functions();
    }

    /**
     * Register global helper functions for backward compatibility.
     *
     * @since 1.3.0
     * @return void
     */
    private static function register_global_functions() {
        // Only register if functions don't already exist
        if (!function_exists('get_item_content_drip_settings')) {
            /**
             * Get specific content drip setting for a lesson, quiz, or assignment.
             * 
             * This function provides compatibility with Tutor LMS Pro's Content Drip addon
             * by retrieving specific values from the _content_drip_settings meta field.
             *
             * @since 1.3.0
             * @param int $item_id The ID of the lesson, quiz, or assignment.
             * @param string $setting_key The specific setting key to retrieve.
             * @param mixed $default Default value if setting doesn't exist.
             * @return mixed The setting value or default.
             */
            function get_item_content_drip_settings($item_id, $setting_key, $default = null) {
                return TutorPress_Content_Drip_Helpers::get_item_setting($item_id, $setting_key, $default);
            }
        }

        if (!function_exists('set_item_content_drip_settings')) {
            /**
             * Set specific content drip setting for a lesson, quiz, or assignment.
             * 
             * This function provides compatibility with Tutor LMS Pro's Content Drip addon
             * by updating specific values in the _content_drip_settings meta field.
             *
             * @since 1.3.0
             * @param int $item_id The ID of the lesson, quiz, or assignment.
             * @param string $setting_key The specific setting key to update.
             * @param mixed $value The value to set.
             * @return bool True on success, false on failure.
             */
            function set_item_content_drip_settings($item_id, $setting_key, $value) {
                return TutorPress_Content_Drip_Helpers::set_item_setting($item_id, $setting_key, $value);
            }
        }

        if (!function_exists('get_tutor_course_settings')) {
            /**
             * Get course settings with fallback for when Tutor LMS function doesn't exist.
             * 
             * @since 1.3.0
             * @param int $course_id Course ID.
             * @param string $key Setting key.
             * @param mixed $default Default value.
             * @return mixed Setting value or default.
             */
            function get_tutor_course_settings($course_id, $key, $default = null) {
                return TutorPress_Content_Drip_Helpers::get_course_setting($course_id, $key, $default);
            }
        }
    }

    /**
     * Get specific content drip setting for a content item.
     *
     * @since 1.3.0
     * @param int $item_id The ID of the content item.
     * @param string $setting_key The specific setting key to retrieve.
     * @param mixed $default Default value if setting doesn't exist.
     * @return mixed The setting value or default.
     */
    public static function get_item_setting($item_id, $setting_key, $default = null) {
        // Get the content drip settings array
        $content_drip_settings = get_post_meta($item_id, '_content_drip_settings', true);
        
        // Ensure it's an array
        if (!is_array($content_drip_settings)) {
            $content_drip_settings = array();
        }
        
        // Return the specific setting or default
        return isset($content_drip_settings[$setting_key]) ? $content_drip_settings[$setting_key] : $default;
    }

    /**
     * Set specific content drip setting for a content item.
     *
     * @since 1.3.0
     * @param int $item_id The ID of the content item.
     * @param string $setting_key The specific setting key to update.
     * @param mixed $value The value to set.
     * @return bool True on success, false on failure.
     */
    public static function set_item_setting($item_id, $setting_key, $value) {
        // Get existing content drip settings
        $content_drip_settings = get_post_meta($item_id, '_content_drip_settings', true);
        
        // Ensure it's an array
        if (!is_array($content_drip_settings)) {
            $content_drip_settings = array();
        }
        
        // Update the specific setting
        $content_drip_settings[$setting_key] = $value;
        
        // Save back to meta
        return update_post_meta($item_id, '_content_drip_settings', $content_drip_settings);
    }

    /**
     * Get course settings with fallback for when Tutor LMS function doesn't exist.
     *
     * @since 1.3.0
     * @param int $course_id Course ID.
     * @param string $key Setting key.
     * @param mixed $default Default value.
     * @return mixed Setting value or default.
     */
    public static function get_course_setting($course_id, $key, $default = null) {
        // Use Tutor LMS function if available
        if (function_exists('tutor_utils') && tutor_utils()) {
            return tutor_utils()->get_course_settings($course_id, $key, $default);
        }
        
        // Fallback: get from course meta
        $course_settings = get_post_meta($course_id, '_tutor_course_settings', true);
        if (is_array($course_settings) && isset($course_settings[$key])) {
            return $course_settings[$key];
        }
        
        return $default;
    }

    /**
     * Get content drip information for a content item.
     *
     * @since 1.3.0
     * @param int $item_id The ID of the content item.
     * @return array Content drip information.
     */
    public static function get_content_drip_info($item_id) {
        // Get course ID for the content item
        $course_id = null;
        if (function_exists('tutor_utils') && tutor_utils()) {
            $course_id = tutor_utils()->get_course_id_by_content($item_id);
        }

        $content_drip_enabled = false;
        $content_drip_type = 'unlock_by_date';
        
        if ($course_id) {
            $content_drip_enabled = (bool) self::get_course_setting($course_id, 'enable_content_drip');
            $content_drip_type = self::get_course_setting($course_id, 'content_drip_type', 'unlock_by_date');
        }

        return [
            'enabled' => $content_drip_enabled,
            'type' => $content_drip_type,
            'course_id' => $course_id,
            'show_days_field' => $content_drip_enabled && $content_drip_type === 'specific_days',
            'show_date_field' => $content_drip_enabled && $content_drip_type === 'unlock_by_date',
            'show_prerequisites_field' => $content_drip_enabled && $content_drip_type === 'after_finishing_prerequisites',
            'is_sequential' => $content_drip_enabled && $content_drip_type === 'unlock_sequentially',
        ];
    }

    /**
     * Check if Content Drip addon is available and enabled.
     *
     * @since 1.3.0
     * @return bool True if Content Drip addon is available.
     */
    public static function is_content_drip_available() {
        // Check if the Content Drip addon class exists
        return class_exists('TUTOR_CONTENT_DRIP\ContentDrip');
    }

    /**
     * Get available content drip types.
     *
     * @since 1.3.0
     * @return array Array of content drip types with labels.
     */
    public static function get_content_drip_types() {
        return [
            'unlock_by_date' => __('Schedule course contents by date', 'tutorpress'),
            'specific_days' => __('Content available after X days from enrollment', 'tutorpress'),
            'unlock_sequentially' => __('Course content available sequentially', 'tutorpress'),
            'after_finishing_prerequisites' => __('Course content unlocked after finishing prerequisites', 'tutorpress'),
        ];
    }
} 