<?php
/**
 * Handles metadata for Tutor LMS courses.
 */

defined( 'ABSPATH' ) || exit;

class Tutor_LMS_Metadata_Handler {

    /**
     * Flag to prevent multiple initializations.
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Initialize hooks for metadata updates.
     */
    public static function init() {
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        // Update metadata when a course is saved.
        add_action( 'save_post', array( __CLASS__, 'update_course_metadata' ), 10, 1 );

        // Update metadata when a comment is approved or status is changed.
        add_action( 'wp_set_comment_status', array( __CLASS__, 'update_metadata_on_comment_change' ), 10, 2 );
    }

    /**
     * Update course metadata (average rating and rating count) when the course is saved.
     *
     * @param int $post_id The ID of the course post.
     */
    public static function update_course_metadata( $post_id ) {
        // Ensure this is not an autosave or a non-course post type.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $post_type = get_post_type( $post_id );
        if ( $post_type !== 'courses' ) {
            return;
        }

        // Use Tutor LMS's built-in function to fetch course ratings.
        $course_rating = tutor_utils()->get_course_rating( $post_id );
        if ( $course_rating && isset( $course_rating->rating_count ) && isset( $course_rating->rating_avg ) ) {
            // Delete existing metadata before updating.
            delete_post_meta( $post_id, 'tutor_course_rating_count' );
            delete_post_meta( $post_id, 'tutor_course_average_rating' );

            // Update metadata fields.
            update_post_meta( $post_id, 'tutor_course_rating_count', $course_rating->rating_count );
            update_post_meta( $post_id, 'tutor_course_average_rating', $course_rating->rating_avg );
        } else {
            // Reset metadata if no ratings exist.
            update_post_meta( $post_id, 'tutor_course_rating_count', 0 );
            update_post_meta( $post_id, 'tutor_course_average_rating', 0 );
        }
    }

    /**
     * Update course metadata when a review's status changes.
     *
     * @param int    $comment_id The ID of the comment.
     * @param string $comment_status The new status of the comment.
     */
    public static function update_metadata_on_comment_change( $comment_id, $comment_status ) {
        // Fetch the comment and ensure it is for a course rating.
        $comment = get_comment( $comment_id );
        if ( $comment && $comment->comment_type === 'tutor_course_rating' ) {
            $course_id = $comment->comment_post_ID;

            // Update metadata for the associated course.
            self::update_course_metadata( $course_id );
        }
    }
}
