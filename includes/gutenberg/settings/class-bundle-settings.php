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
     * Bundle pricing meta keys (following Tutor LMS Course constants).
     *
     * @var string
     */
    const BUNDLE_PRICE_TYPE_META_KEY = '_tutor_course_price_type';
    const BUNDLE_PRICE_META_KEY = 'tutor_course_price';
    const BUNDLE_SALE_PRICE_META_KEY = 'tutor_course_sale_price';
    const BUNDLE_SELLING_OPTION_META_KEY = 'tutor_course_selling_option';
    const BUNDLE_PRODUCT_ID_META_KEY = '_tutor_course_product_id';
    
    /**
     * Bundle ribbon meta key (following Tutor LMS Pro Course Bundle constants).
     *
     * @var string
     */
    const BUNDLE_RIBBON_META_KEY = 'tutor_bundle_ribbon_type';
    
    /**
     * Bundle course IDs meta key (following Tutor LMS Pro Course Bundle constants).
     *
     * @var string
     */
    const BUNDLE_COURSE_IDS_META_KEY = 'bundle-course-ids';
    
    /**
     * Bundle benefits meta key (following Tutor LMS Course constants).
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
        // Register meta fields when WordPress is ready (following Bundle Benefits pattern)
        add_action('init', [__CLASS__, 'maybe_register_meta_fields']);
        add_action('rest_api_init', [__CLASS__, 'maybe_register_meta_fields']);
    }
    
    /**
     * Maybe register meta fields if conditions are met.
     *
     * @since 0.1.0
     * @return void
     */
    public static function maybe_register_meta_fields() {
        // Only proceed if Tutor LMS is active and course-bundle post type exists
        if (!function_exists('tutor') || !post_type_exists('course-bundle')) {
            return;
        }

        self::register_meta_fields();
    }

    /**
     * Register bundle settings meta fields.
     *
     * @since 0.1.0
     * @return void
     */
    public static function register_meta_fields() {
        // Register the exact same meta fields as Tutor LMS Pro with REST API exposure
        
        // Price type meta field
        register_post_meta('course-bundle', self::BUNDLE_PRICE_TYPE_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle price type (free/paid)', 'tutorpress'),
            'single'            => true,
            'default'           => 'free',
            'sanitize_callback' => [__CLASS__, 'sanitize_price_type'],
            'show_in_rest'      => true,
        ]);
        
        // Regular price meta field
        register_post_meta('course-bundle', self::BUNDLE_PRICE_META_KEY, [
            'type'              => 'number',
            'description'       => __('Bundle regular price', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => [__CLASS__, 'sanitize_price'],
            'show_in_rest'      => true,
        ]);
        
        // Sale price meta field
        register_post_meta('course-bundle', self::BUNDLE_SALE_PRICE_META_KEY, [
            'type'              => 'number',
            'description'       => __('Bundle sale price', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => [__CLASS__, 'sanitize_price'],
            'show_in_rest'      => true,
        ]);
        
        // Selling option meta field
        register_post_meta('course-bundle', self::BUNDLE_SELLING_OPTION_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle selling option', 'tutorpress'),
            'single'            => true,
            'default'           => 'one_time',
            'sanitize_callback' => [__CLASS__, 'sanitize_selling_option'],
            'show_in_rest'      => true,
        ]);
        
        // Product ID meta field
        register_post_meta('course-bundle', self::BUNDLE_PRODUCT_ID_META_KEY, [
            'type'              => 'integer',
            'description'       => __('Bundle product ID', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);
        
        // Ribbon type meta field
        register_post_meta('course-bundle', self::BUNDLE_RIBBON_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle ribbon type', 'tutorpress'),
            'single'            => true,
            'default'           => 'none',
            'sanitize_callback' => [__CLASS__, 'sanitize_ribbon_type'],
            'show_in_rest'      => true,
        ]);
        
        // Course IDs meta field
        register_post_meta('course-bundle', self::BUNDLE_COURSE_IDS_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle course IDs', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [__CLASS__, 'sanitize_course_ids'],
            'show_in_rest'      => true,
        ]);
        
        // Benefits meta field
        register_post_meta('course-bundle', self::BUNDLE_BENEFITS_META_KEY, [
            'type'              => 'string',
            'description'       => __('Bundle benefits', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]);
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
        $allowed_types = ['in_percentage', 'in_amount', 'none'];
        $value = sanitize_text_field($value);
        return in_array($value, $allowed_types) ? $value : 'none';
    }

    /**
     * Sanitize price type.
     *
     * @since 0.1.0
     * @param mixed $value The value to sanitize.
     * @return string Sanitized price type.
     */
    public static function sanitize_price_type($value) {
        $allowed_types = ['free', 'paid'];
        $value = sanitize_text_field($value);
        return in_array($value, $allowed_types) ? $value : 'free';
    }

    /**
     * Sanitize price.
     *
     * @since 0.1.0
     * @param mixed $value The value to sanitize.
     * @return float Sanitized price.
     */
    public static function sanitize_price($value) {
        $price = floatval($value);
        return $price >= 0 ? $price : 0;
    }

    /**
     * Sanitize selling option.
     *
     * @since 0.1.0
     * @param mixed $value The value to sanitize.
     * @return string Sanitized selling option.
     */
    public static function sanitize_selling_option($value) {
        $allowed_options = ['one_time', 'subscription', 'both', 'membership', 'all'];
        $value = sanitize_text_field($value);
        return in_array($value, $allowed_options) ? $value : 'one_time';
    }

}