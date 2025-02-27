<?php
namespace Tutor\Helpers;

/**
 * MetaKeysHelper class contains static helper methods to manage
 * meta keys for course settings.
 *
 * @package Tutor\Helpers
 * @since v2.7.9
 */
class MetaKeysHelper {
    // Course Metadata Keys
    const COURSE_DIFFICULTY = '_tutor_course_level';
    const COURSE_DURATION = '_course_duration'; // Stored as an array (hours & minutes)
    const IS_PUBLIC_COURSE = '_tutor_is_public_course';
    const ENABLE_QA = '_tutor_enable_qa';
    const INSTRUCTOR_COURSE_ID = '_tutor_instructor_course_id'; // Assign instructors

    /**
     * Retrieve the list of valid course meta fields.
     *
     * @return array List of meta keys with their attributes.
     */
    public static function get_meta_fields() {
        return [
            self::COURSE_DIFFICULTY => ['type' => 'string', 'label' => __('Course Difficulty', 'tutor')],
            self::COURSE_DURATION => ['type' => 'array', 'label' => __('Course Duration', 'tutor')],
            self::IS_PUBLIC_COURSE => ['type' => 'boolean', 'label' => __('Public Course', 'tutor')],
            self::ENABLE_QA => ['type' => 'boolean', 'label' => __('Enable Q&A', 'tutor')],
            self::INSTRUCTOR_COURSE_ID => ['type' => 'integer', 'label' => __('Instructor Course ID', 'tutor')],
        ];
    }

    /**
     * Validate if the provided meta key is allowed.
     *
     * @param string $meta_key Meta key to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_meta_key($meta_key) {
        return array_key_exists($meta_key, self::get_meta_fields());
    }

    /**
     * Retrieve a course meta value.
     *
     * @param int $course_id Course post ID.
     * @param string $meta_key The meta key to retrieve.
     * @return mixed Meta value or null if not found.
     */
    public static function get_course_meta($course_id, $meta_key) {
        if (!$course_id || !$meta_key || !self::is_valid_meta_key($meta_key)) {
            return null;
        }

        return get_post_meta($course_id, $meta_key, true);
    }

    /**
     * Update a course meta value with validation & sanitization.
     *
     * @param int $course_id Course post ID.
     * @param string $meta_key The meta key to update.
     * @param mixed $value The value to store.
     * @return mixed The sanitized value that was stored.
     */
    public static function update_course_meta($course_id, $meta_key, $value) {
        if (!$course_id || !$meta_key || !self::is_valid_meta_key($meta_key)) {
            return;
        }

        // ✅ Ensure sanitization before updating metadata
        $sanitized_value = self::sanitize_meta_value($meta_key, $value);
        
        update_post_meta($course_id, $meta_key, $sanitized_value);

        return $sanitized_value; // ✅ Return updated value for potential use in API responses
    }

    /**
     * Sanitize meta values before saving to the database.
     *
     * @param string $meta_key The meta key.
     * @param mixed $value The value to sanitize.
     * @return mixed Sanitized value.
     */
    private static function sanitize_meta_value($meta_key, $value) {
        $meta_fields = self::get_meta_fields();

        if (!isset($meta_fields[$meta_key])) {
            return $value; // If key is not recognized, return as is.
        }

        $type = $meta_fields[$meta_key]['type'];

        switch ($type) {
            case 'string':
                return sanitize_text_field($value);
            case 'array':
                return array_map('sanitize_text_field', (array) $value);
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
            case 'integer':
                return intval($value);
            default:
                return $value;
        }
    }

    /**
     * Assign an instructor to a course.
     *
     * @param int $course_id Course post ID.
     * @param int $author_id Instructor ID.
     */
    public static function assign_instructor($course_id, $author_id) {
        if (!$course_id || !$author_id) {
            return;
        }

        $existing_instructor = get_post_meta($course_id, '_tutor_instructor_course_id', true);

        // If instructor is already assigned, do nothing.
        if ($existing_instructor && $existing_instructor == $author_id) {
            return;
        }

        update_post_meta($course_id, '_tutor_instructor_course_id', $author_id);
    }
}
