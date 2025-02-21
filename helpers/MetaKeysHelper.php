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
    // Course Difficulty Level
    const COURSE_DIFFICULTY = '_tutor_course_level';

    // Course Duration (Stored as an array with hours and minutes)
    const COURSE_DURATION = '_course_duration';

    // Public Course Toggle
    const IS_PUBLIC_COURSE = '_tutor_is_public_course';

    // Q&A Enable Toggle
    const ENABLE_QA = '_tutor_enable_qa';

    /**
     * Retrieve the list of course meta fields.
     *
     * @return array List of meta keys with their attributes.
     */
    public static function get_meta_fields() {
        return [
            self::COURSE_DIFFICULTY => ['type' => 'string', 'label' => __('Course Difficulty', 'tutor')],
            self::COURSE_DURATION => ['type' => 'array', 'label' => __('Course Duration', 'tutor')],
            self::IS_PUBLIC_COURSE => ['type' => 'boolean', 'label' => __('Public Course', 'tutor')],
            self::ENABLE_QA => ['type' => 'boolean', 'label' => __('Enable Q&A', 'tutor')],
        ];
    }
}
