<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TutorPress Subscription Utilities
 *
 * Provides shared utility methods for subscription operations across different
 * subscription providers (Tutor LMS native, PMPro, future providers).
 *
 * This utility class enables code reuse via composition rather than inheritance,
 * allowing subscription controllers to share common validation and formatting
 * logic while maintaining independence.
 */
class TutorPress_Subscription_Utils {

    /**
     * Validate course ID
     *
     * @param int $course_id Course ID to validate
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_course_id($course_id) {
        if (!function_exists('tutor')) {
            return new WP_Error(
                'tutor_unavailable',
                __('Tutor LMS is not available.', 'tutorpress'),
                ['status' => 500]
            );
        }

        $course = get_post($course_id);
        if (!$course || $course->post_type !== tutor()->course_post_type) {
            return new WP_Error(
                'invalid_course',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        return true;
    }

    /**
     * Validate bundle ID
     *
     * @param int $bundle_id Bundle ID to validate
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_bundle_id($bundle_id) {
        if (!function_exists('tutor')) {
            return new WP_Error(
                'tutor_unavailable',
                __('Tutor LMS is not available.', 'tutorpress'),
                ['status' => 500]
            );
        }

        $bundle = get_post($bundle_id);
        if (!$bundle || $bundle->post_type !== tutor()->bundle_post_type) {
            return new WP_Error(
                'invalid_bundle',
                __('Invalid bundle ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        return true;
    }

    /**
     * Format success response data with consistent structure
     *
     * @param mixed  $data    The data to format
     * @param string $message Optional message to include
     * @return array Formatted response data
     */
    public static function format_success_response($data, $message = '') {
        return [
            'success' => true,
            'message' => $message ?: __('Request successful.', 'tutorpress'),
            'data'    => $data,
        ];
    }

    /**
     * Create error response with consistent structure
     *
     * @param string $message Error message
     * @param string $code    Error code (default: 'error')
     * @param int    $status  HTTP status code (default: 400)
     * @return WP_Error Formatted error response
     */
    public static function format_error_response($message, $code = 'error', $status = 400) {
        return new WP_Error(
            $code,
            $message,
            ['status' => $status]
        );
    }
}
