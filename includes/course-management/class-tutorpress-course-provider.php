<?php
/**
 * Course Data Provider Class
 *
 * Provides centralized, role-aware data access for courses following Sensei's provider pattern.
 * Consolidates scattered course query logic and implements consistent permission filtering.
 *
 * @package TutorPress
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TutorPress_Course_Provider {

    /**
     * Get courses for current user using WordPress-native queries
     * Follows Sensei's simple provider pattern
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return array Array of course post IDs
     */
    public function get_user_courses(?int $user_id = null): array {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $args = [
            'post_type' => 'courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];

        // Simple role-based logic (like Sensei's approach)
        if (current_user_can('manage_options')) {
            // Admin sees all courses
        } elseif (current_user_can('tutor_instructor')) {
            // Instructors see their courses
            $args['author'] = $user_id;
        } else {
            // Students see enrolled courses
            $args = $this->add_enrollment_filter($args, $user_id);
        }

        // Apply filter for extensibility (Sensei pattern)
        $args = apply_filters('tutorpress_course_provider_args', $args, $user_id);

        return get_posts($args);
    }

    /**
     * Get course enrollment status for user
     * Simple, focused method (Sensei style)
     *
     * @param int $course_id Course ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user is enrolled
     */
    public function is_user_enrolled(int $course_id, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Use Tutor LMS's existing enrollment check
        if (function_exists('tutor_utils')) {
            return tutor_utils()->is_enrolled($course_id, $user_id);
        }

        return false;
    }

    /**
     * Check if user can access course
     * Centralizes course access logic scattered across templates/controllers
     *
     * @param int $course_id Course ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can access course
     */
    public function can_user_access_course(int $course_id, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admin can access everything
        if (current_user_can('manage_options')) {
            return true;
        }

        // Course author can access
        if (current_user_can('edit_post', $course_id)) {
            return true;
        }

        // Enrolled students can access
        return $this->is_user_enrolled($course_id, $user_id);
    }

    /**
     * Get course settings
     * Centralizes course settings retrieval with consistent structure
     *
     * @param int $course_id Course ID
     * @return array Course settings
     */
    public function get_course_settings(int $course_id): array {
        // Get Tutor LMS course settings
        $tutor_settings = get_post_meta($course_id, '_tutor_course_settings', true);
        
        if (!is_array($tutor_settings)) {
            $tutor_settings = [];
        }

        // Apply filter for extensibility
        return apply_filters('tutorpress_course_settings', $tutor_settings, $course_id);
    }

    /**
     * Save course settings
     * Centralizes course settings saving with validation
     *
     * @param int $course_id Course ID
     * @param array $settings Settings to save
     * @return bool Success status
     */
    public function save_course_settings(int $course_id, array $settings): bool {
        // Validate user can edit course
        if (!current_user_can('edit_post', $course_id)) {
            return false;
        }

        // Apply filter for validation/modification
        $settings = apply_filters('tutorpress_course_settings_before_save', $settings, $course_id);

        // Save settings
        $result = update_post_meta($course_id, '_tutor_course_settings', $settings);

        // Fire action for integrations
        do_action('tutorpress_course_settings_saved', $course_id, $settings);

        return $result !== false;
    }

    /**
     * Add enrollment filter to query args
     * Keeps complex logic contained and testable
     *
     * @param array $args Query arguments
     * @param int $user_id User ID
     * @return array Modified query arguments
     */
    private function add_enrollment_filter(array $args, int $user_id): array {
        // Use WordPress meta_query (not raw SQL)
        $enrolled_courses = $this->get_enrolled_course_ids($user_id);

        if (!empty($enrolled_courses)) {
            $args['post__in'] = $enrolled_courses;
        } else {
            $args['post__in'] = [0]; // No courses if not enrolled in any
        }

        return $args;
    }

    /**
     * Helper to get enrolled course IDs
     * Simple WordPress query (Sensei approach)
     *
     * @param int $user_id User ID
     * @return array Array of course IDs
     */
    private function get_enrolled_course_ids(int $user_id): array {
        // Use Tutor LMS's enrollment system if available
        if (function_exists('tutor_utils')) {
            return tutor_utils()->get_enrolled_courses_ids_by_user($user_id) ?: [];
        }

        // Fallback: check for enrollment meta
        return get_posts([
            'post_type' => 'courses',
            'meta_query' => [
                [
                    'key' => '_tutor_course_enrolled_' . $user_id,
                    'compare' => 'EXISTS'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ]);
    }
}
