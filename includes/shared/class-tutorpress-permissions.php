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

    /**
     * Check if user can edit course content (lesson, assignment, quiz, etc)
     * Used for collaborative editing by co-instructors
     *
     * @param int $post_id Post ID (lesson, assignment, quiz, etc)
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Whether user can edit this content
     */
    public function can_user_edit_course_content(int $post_id, ?int $user_id = null): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Admin can always edit
        if (current_user_can('manage_options')) {
            return true;
        }

        // Resolve course for the given content
        $course_id = $this->get_course_id_for_content($post_id);
        if (!$course_id) {
            return false;
        }

        // Check if user is course author or a co-instructor
        return $this->is_user_course_instructor($user_id, $course_id);
    }

    /**
     * Get course ID for a piece of content (lesson, assignment, quiz)
     * Supports multiple resolution methods for reliability
     *
     * @param int $post_id Post ID
     * @return int|0 Course ID or 0 if not found
     */
    private function get_course_id_for_content(int $post_id): int {
        // Method 1: Use Tutor LMS utility if available
        if (function_exists('tutor_utils')) {
            $course_id = tutor_utils()->get_course_id_by('lesson', $post_id);
            if ($course_id) {
                return (int) $course_id;
            }
        }

        // Method 2: Check post meta (fallback)
        $meta_course_id = get_post_meta($post_id, '_tutor_course_id_for_lesson', true);
        if ($meta_course_id) {
            return (int) $meta_course_id;
        }

        return 0;
    }

    /**
     * Check if user is an instructor for a course (author or co-instructor)
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return bool
     */
    private function is_user_course_instructor(int $user_id, int $course_id): bool {
        // Course author check
        $course_author = get_post_field('post_author', $course_id);
        if ($course_author && (int) $course_author === $user_id) {
            return true;
        }

        // Co-instructor check (check Tutor LMS usermeta)
        $co_instructors = get_post_meta($course_id, '_tutor_course_instructors', true);
        if (is_array($co_instructors) && in_array($user_id, $co_instructors, true)) {
            return true;
        }

        // Fallback: check usermeta entries (legacy pattern)
        $user_meta_entries = get_user_meta($user_id, '_tutor_instructor_course_id', false);
        if (!empty($user_meta_entries) && in_array((string)$course_id, array_map('strval', $user_meta_entries), true)) {
            return true;
        }

        return false;
    }
}
