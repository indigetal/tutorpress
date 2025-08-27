<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TutorPress Permissions Helper
 *
 * Provides cross-cutting permission policies that combine resource checks,
 * feature flags, and role rules across multiple domains.
 *
 * This complements data providers (which handle resource-specific access)
 * by centralizing higher-level policies used across controllers/features.
 */
class TutorPress_Permissions {

    /**
     * Check if user can access a course
     * Cross-cutting policy that combines feature flags and resource access
     *
     * @param int $course_id Course ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can access course
     */
    public function can_user_access_course(int $course_id, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Use Tutor LMS's native permission system for course access
        if (function_exists('tutor_utils')) {
            return tutor_utils()->can_user_edit_course($user_id, $course_id);
        }

        // Fallback to WordPress core permissions
        return current_user_can('edit_post', $course_id);
    }

    /**
     * Check if user can edit course settings
     * Policy that combines course access with editing capabilities
     *
     * @param int $course_id Course ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can edit course settings
     */
    public function can_user_edit_course_settings(int $course_id, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Use Tutor LMS's native permission system
        if (function_exists('tutor_utils')) {
            $can_edit = tutor_utils()->can_user_edit_course($user_id, $course_id);
            
            // Apply filters for extensibility
            return apply_filters('tutorpress_can_edit_course_settings', $can_edit, $course_id, $user_id);
        }

        // Fallback to WordPress core permissions
        $can_edit = current_user_can('manage_options') || current_user_can('edit_post', $course_id);
        return apply_filters('tutorpress_can_edit_course_settings', $can_edit, $course_id, $user_id);
    }

    /**
     * Check if user can access a lesson
     * Cross-cutting policy for lesson access across features
     *
     * @param int $lesson_id Lesson ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can access lesson
     */
    public function can_user_access_lesson(int $lesson_id, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admin can access everything
        if (current_user_can('manage_options')) {
            return true;
        }

        // Lesson author can access
        if (current_user_can('edit_post', $lesson_id)) {
            return true;
        }

        // Get parent course and check enrollment
        $course_id = get_post_meta($lesson_id, '_tutor_course_id_for_lesson', true);
        if ($course_id) {
            return $this->can_user_access_course((int)$course_id, $user_id);
        }

        return false;
    }

    /**
     * Check if user can manage enrollments
     * Policy for enrollment management across features
     *
     * @param int|null $course_id Course ID (optional - for course-specific enrollment management)
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can manage enrollments
     */
    public function can_manage_enrollments(?int $course_id = null, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admin can manage all enrollments
        if (current_user_can('manage_options')) {
            return true;
        }

        // Instructor can manage enrollments for their courses
        if ($course_id && current_user_can('tutor_instructor')) {
            return current_user_can('edit_post', $course_id);
        }

        // General instructor capability for enrollment management
        if (current_user_can('tutor_instructor')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can access feature with combined availability and capability check
     * Delegates to feature flags service for unified policy
     *
     * @param string $feature Feature name
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can access feature
     */
    public function can_user_access_feature(string $feature, ?int $user_id = null): bool {
        return tutorpress_feature_flags()->can_user_access_feature($feature, $user_id);
    }
}
