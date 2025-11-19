<?php
/**
 * TutorPress Capability Fixes
 *
 * Fixes missing capabilities in Tutor LMS core for assignments and instructors.
 * Tutor LMS grants capabilities for courses, lessons, and quizzes but forgets assignments.
 *
 * @package TutorPress
 * @since 2.0.11
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TutorPress_Capability_Fixes
 *
 * Handles capability grants that Tutor LMS core is missing.
 */
class TutorPress_Capability_Fixes {

    /**
     * Initialize capability fixes
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'grant_missing_capabilities'), 20);
    }

    /**
     * Grant capabilities that Tutor LMS forgot to add
     *
     * @since 2.0.11
     */
    public static function grant_missing_capabilities() {
        if (!function_exists('tutor')) {
            return;
        }

        self::grant_assignment_capabilities();
        self::grant_instructor_rest_api_capabilities();
    }

    /**
     * Grant assignment capabilities to admin and instructor roles
     *
     * Tutor LMS grants capabilities for courses, lessons, quizzes in Tutor.php
     * but assignments are completely missing from their capability setup.
     *
     * @since 2.0.11
     */
    private static function grant_assignment_capabilities() {
        $assignment_caps = array(
            'edit_tutor_assignment',
            'read_tutor_assignment',
            'delete_tutor_assignment',
            'delete_tutor_assignments',
            'edit_tutor_assignments',
            'edit_others_tutor_assignments',
            'publish_tutor_assignments',
            'read_private_tutor_assignments',
        );

        // Grant to administrator
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($assignment_caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }

        // Grant to instructor
        $instructor = get_role(tutor()->instructor_role);
        if ($instructor) {
            foreach ($assignment_caps as $cap) {
                if (!$instructor->has_cap($cap)) {
                    $instructor->add_cap($cap);
                }
            }
        }
    }

    /**
     * Grant REST API capabilities to instructor role
     *
     * Instructors need edit_posts and read capabilities for:
     * - REST API access (required by TutorPress REST controllers)
     * - Course Curriculum metabox (uses REST API)
     * - Creating new lessons/assignments
     *
     * @since 2.0.11
     */
    private static function grant_instructor_rest_api_capabilities() {
        $instructor = get_role(tutor()->instructor_role);
        if (!$instructor) {
            return;
        }

        // Grant edit_posts for REST API access
        if (!$instructor->has_cap('edit_posts')) {
            $instructor->add_cap('edit_posts');
        }

        // Grant read capability (often checked alongside edit_posts)
        if (!$instructor->has_cap('read')) {
            $instructor->add_cap('read');
        }
    }
}

// Initialize
TutorPress_Capability_Fixes::init();

