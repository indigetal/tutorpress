<?php
/**
 * Handles metadata for Tutor LMS courses.
 */

defined( 'ABSPATH' ) || exit;

class Tutor_LMS_Metadata_Handler {

    /**
     * Initialize hooks for metadata updates.
     */
    public static function init() {
        // Update metadata when a course is saved.
        add_action( 'save_post_tutor_course', array( __CLASS__, 'update_course_metadata' ) );

        // Update metadata when a comment is approved or status is changed.
        add_action( 'wp_set_comment_status', array( __CLASS__, 'update_metadata_on_comment_change' ), 10, 2 );
    }

    /**
     * Update course metadata (average rating and rating count) when the course is saved.
     *
     * @param int $post_id The ID of the course post.
     */
    public static function update_course_metadata( $post_id ) {
        global $wpdb;

        // Fetch rating_count and rating_sum dynamically.
        $query = $wpdb->prepare(
            "SELECT COUNT(meta_value) AS rating_count, SUM(meta_value) AS rating_sum
             FROM {$wpdb->comments}
             INNER JOIN {$wpdb->commentmeta}
             ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_id
             WHERE {$wpdb->comments}.comment_post_ID = %d
             AND {$wpdb->comments}.comment_type = %s
             AND {$wpdb->comments}.comment_approved = %s
             AND meta_key = %s",
            $post_id,
            'tutor_course_rating',
            'approved',
            'tutor_rating'
        );

        $results = $wpdb->get_row( $query );

        if ( $results && $results->rating_count > 0 ) {
            // Calculate the average rating.
            $average_rating = number_format( $results->rating_sum / $results->rating_count, 2 );

            // Update metadata fields.
            update_post_meta( $post_id, 'tutor_course_rating_count', $results->rating_count );
            update_post_meta( $post_id, 'tutor_course_average_rating', $average_rating );
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
        global $wpdb;

        // Get the comment and related course ID.
        $comment = get_comment( $comment_id );
        if ( $comment->comment_type === 'tutor_course_rating' ) {
            $course_id = $comment->comment_post_ID;

            // Trigger metadata update for the course.
            self::update_course_metadata( $course_id );
        }
    }
}
