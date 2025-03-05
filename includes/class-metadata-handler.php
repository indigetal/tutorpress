<?php
/**
 * Handles metadata for Tutor LMS courses.
 */

defined('ABSPATH') || exit;

class Tutor_LMS_Metadata_Handler {

    /**
     * Flag to prevent multiple metadata updates in a single request.
     *
     * @var bool
     */
    private static $metadata_updated = false;

    /**
     * Initialize hooks for metadata updates.
     */
    public static function init() {
        if (self::$metadata_updated) {
            return;
        }
        self::$metadata_updated = true;

        // Update metadata when a course is saved.
        add_action('save_post_courses', [__CLASS__, 'update_course_metadata'], 10, 1);

        // Update metadata when a comment (review) is approved or status is changed.
        add_action('wp_set_comment_status', [__CLASS__, 'update_metadata_on_comment_change'], 10, 2);
    }

    /**
     * Update course metadata (average rating and rating count) when the course is saved.
     *
     * @param int $post_id The ID of the course post.
     */
    public static function update_course_metadata($post_id) {
        // Prevent redundant updates in a single request.
        if (self::$metadata_updated) {
            return;
        }
        self::$metadata_updated = true;

        // Ensure this is not an autosave or revision.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Ensure this is a course post type.
        if (!is_singular('courses')) {
            return;
        }

        // Fetch course ratings using Tutor LMS's built-in function.
        $course_rating = tutor_utils()->get_course_rating($post_id);
        if ($course_rating && isset($course_rating->rating_count) && isset($course_rating->rating_avg)) {
            // Delete existing metadata before updating.
            delete_post_meta($post_id, 'tutor_course_rating_count');
            delete_post_meta($post_id, 'tutor_course_average_rating');

            // Store new metadata.
            update_post_meta($post_id, 'tutor_course_rating_count', apply_filters('tutorpress_course_rating_count', $course_rating->rating_count));
            update_post_meta($post_id, 'tutor_course_average_rating', apply_filters('tutorpress_course_average_rating', $course_rating->rating_avg));
        } else {
            // Reset metadata if no ratings exist.
            update_post_meta($post_id, 'tutor_course_rating_count', apply_filters('tutorpress_course_rating_count', 0));
            update_post_meta($post_id, 'tutor_course_average_rating', apply_filters('tutorpress_course_average_rating', 0));
        }
    }

    /**
     * Update course metadata when a review's status changes.
     *
     * @param int    $comment_id The ID of the comment.
     * @param string $comment_status The new status of the comment.
     */
    public static function update_metadata_on_comment_change($comment_id, $comment_status) {
        // Fetch the comment and ensure it is for a course rating.
        $comment = get_comment($comment_id);
        if ($comment && $comment->comment_type === 'tutor_course_rating') {
            $course_id = $comment->comment_post_ID;

            // Update metadata for the associated course.
            self::update_course_metadata($course_id);
        }
    }
}

// Initialize the metadata handler.
Tutor_LMS_Metadata_Handler::init();
