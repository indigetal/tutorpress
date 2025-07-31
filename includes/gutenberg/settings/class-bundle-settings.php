<?php
/**
 * Bundle Settings Meta Fields
 *
 * Handles registration of bundle settings meta fields for Gutenberg editor.
 * Provides WordPress-first approach while maintaining bidirectional compatibility
 * with Tutor LMS's native bundle functionality.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Bundle_Settings {

    /**
     * Meta field keys for bundle settings
     */
    const BUNDLE_COURSE_IDS_META_KEY = 'bundle-course-ids';

    /**
     * Bundle ribbon type meta key.
     *
     * @var string
     */
    const BUNDLE_RIBBON_TYPE_META_KEY = 'tutor_bundle_ribbon_type';

    /**
     * Bundle benefits meta key.
     *
     * @var string
     */
    const BUNDLE_BENEFITS_META_KEY = '_tutor_course_benefits';

    /**
     * Initialize the bundle settings.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        // Only proceed if Tutor LMS is active and course-bundle post type exists
        if (!function_exists('tutor') || !post_type_exists('course-bundle')) {
            return;
        }

        add_action('init', [__CLASS__, 'register_meta_fields']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_fields']);
    }

    /**
     * Register bundle settings meta fields.
     *
     * @since 0.1.0
     * @return void
     */
    public static function register_meta_fields() {
        // Bundle course IDs
        register_post_meta('course-bundle', self::BUNDLE_COURSE_IDS_META_KEY, [
            'type'              => 'string',
            'description'       => __('Comma-separated list of course IDs in bundle', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [__CLASS__, 'sanitize_course_ids'],
            'show_in_rest'      => true,
        ]);

        // Bundle ribbon type
        register_post_meta('course-bundle', self::BUNDLE_RIBBON_TYPE_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle ribbon display type', 'tutorpress'),
            'single'            => true,
            'default'           => 'none',
            'sanitize_callback' => [__CLASS__, 'sanitize_ribbon_type'],
            'show_in_rest'      => true,
        ]);

        // Bundle benefits
        register_post_meta('course-bundle', self::BUNDLE_BENEFITS_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle benefits description', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Register REST API fields for bundle settings.
     *
     * @since 0.1.0
     * @return void
     */
    public static function register_rest_fields() {
        register_rest_field('course-bundle', 'bundle_settings', [
            'get_callback'    => [__CLASS__, 'get_bundle_settings'],
            'update_callback' => [__CLASS__, 'update_bundle_settings'],
        ]);
    }

    /**
     * Get bundle settings for REST API.
     *
     * @since 0.1.0
     * @param array $post The post array.
     * @return array Bundle settings.
     */
    public static function get_bundle_settings($post) {
        $post_id = $post['id'];
        
        return [
            'course_ids'  => self::get_bundle_course_ids($post_id),
            'ribbon_type' => self::get_bundle_ribbon_type($post_id),
            'benefits'    => self::get_bundle_benefits($post_id),
        ];
    }

    /**
     * Update bundle settings from REST API.
     *
     * @since 0.1.0
     * @param mixed $value The value to update.
     * @param WP_Post $post The post object.
     * @return bool True on success, false on failure.
     */
    public static function update_bundle_settings($value, $post) {
        // Placeholder - will be expanded as settings are implemented
        return true;
    }



    /**
     * Get bundle course IDs.
     *
     * @since 0.1.0
     * @param int $bundle_id The bundle ID.
     * @return array Array of course IDs.
     */
    public static function get_bundle_course_ids($bundle_id) {
        $course_ids_str = get_post_meta($bundle_id, self::BUNDLE_COURSE_IDS_META_KEY, true);
        
        if (empty($course_ids_str)) {
            return [];
        }

        return array_map('absint', explode(',', $course_ids_str));
    }

    /**
     * Get bundle ribbon type.
     *
     * @since 0.1.0
     * @param int $bundle_id The bundle ID.
     * @return string The ribbon type.
     */
    public static function get_bundle_ribbon_type($bundle_id) {
        return get_post_meta($bundle_id, self::BUNDLE_RIBBON_TYPE_META_KEY, true);
    }

    /**
     * Get bundle benefits.
     *
     * @since 0.1.0
     * @param int $bundle_id The bundle ID.
     * @return string The bundle benefits.
     */
    public static function get_bundle_benefits($bundle_id) {
        return get_post_meta($bundle_id, self::BUNDLE_BENEFITS_META_KEY, true);
    }

    /**
     * Update bundle course IDs.
     *
     * @since 0.1.0
     * @param int   $bundle_id  The bundle ID.
     * @param array $course_ids Array of course IDs.
     * @return bool True on success, false on failure.
     */
    public static function update_bundle_course_ids($bundle_id, $course_ids) {
        if (!is_array($course_ids)) {
            return false;
        }

        $course_ids = array_map('absint', $course_ids);
        $course_ids = array_filter($course_ids); // Remove empty values

        return update_post_meta($bundle_id, self::BUNDLE_COURSE_IDS_META_KEY, implode(',', $course_ids));
    }

    /**
     * Update bundle ribbon type.
     *
     * @since 0.1.0
     * @param int    $bundle_id   The bundle ID.
     * @param string $ribbon_type The ribbon type.
     * @return bool True on success, false on failure.
     */
    public static function update_bundle_ribbon_type($bundle_id, $ribbon_type) {
        $allowed_types = ['in_percentage', 'in_amount', 'none'];
        
        if (!in_array($ribbon_type, $allowed_types)) {
            return false;
        }

        return update_post_meta($bundle_id, self::BUNDLE_RIBBON_TYPE_META_KEY, $ribbon_type);
    }

    /**
     * Update bundle benefits.
     *
     * @since 0.1.0
     * @param int    $bundle_id The bundle ID.
     * @param string $benefits  The bundle benefits.
     * @return bool True on success, false on failure.
     */
    public static function update_bundle_benefits($bundle_id, $benefits) {
        $benefits = wp_kses_post($benefits);
        return update_post_meta($bundle_id, self::BUNDLE_BENEFITS_META_KEY, $benefits);
    }

    /**
     * Sanitize course IDs.
     *
     * @since 0.1.0
     * @param mixed $value The value to sanitize.
     * @return string Sanitized course IDs string.
     */
    public static function sanitize_course_ids($value) {
        // Simple sanitization - will be expanded as needed
        return sanitize_text_field($value);
    }

    /**
     * Sanitize ribbon type.
     *
     * @since 0.1.0
     * @param mixed $value The value to sanitize.
     * @return string Sanitized ribbon type.
     */
    public static function sanitize_ribbon_type($value) {
        // Simple sanitization - will be expanded as needed
        return sanitize_text_field($value);
    }
}