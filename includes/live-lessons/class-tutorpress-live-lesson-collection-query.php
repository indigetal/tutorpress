<?php
/**
 * Authorized live-lesson collection for dormant routes.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Course-scoped publish rows. Lifecycle status is not a filter. */
final class TutorPress_Live_Lesson_Collection_Query {
	/** Null unless the course context is authorized. */
	public static function query( array $course, $can, array $topics, array $lessons, array $args ) {
		$course_id = TutorPress_Live_Lesson_Authorization::authorize_course( $course, $can );
		if ( null === $course_id ) {
			return null;
		}
		$topic_ids = array();
		$only      = isset( $args['topic_id'] ) ? (int) $args['topic_id'] : 0;
		foreach ( $topics as $topic ) {
			$topic_id = is_array( $topic ) && isset( $topic['ID'], $topic['post_parent'], $topic['post_type'] ) && 'topics' === $topic['post_type'] ? (int) $topic['ID'] : 0;
			if ( $topic_id > 0 && (int) $topic['post_parent'] === (int) $course_id && ( 0 === $only || $topic_id === $only ) ) {
				$topic_ids[ $topic_id ] = true;
			}
		}
		$providers = array( 'tutor-google-meet' => 'google_meet', 'tutor_zoom_meeting' => 'zoom' );
		$type      = isset( $args['type'] ) ? $args['type'] : '';
		$matched   = array();
		foreach ( $lessons as $lesson ) {
			$post_type = is_array( $lesson ) && isset( $lesson['post_type'], $lesson['post_status'], $lesson['post_parent'], $lesson['ID'] ) ? $lesson['post_type'] : '';
			if ( 'publish' !== ( isset( $lesson['post_status'] ) ? $lesson['post_status'] : '' ) || ! isset( $providers[ $post_type ] ) || ! isset( $topic_ids[ (int) $lesson['post_parent'] ] ) ) {
				continue;
			}
			if ( '' !== $type && $providers[ $post_type ] !== $type ) {
				continue;
			}
			$matched[] = array( 'id' => (int) $lesson['ID'], 'courseId' => (int) $course_id, 'topicId' => (int) $lesson['post_parent'], 'type' => $providers[ $post_type ], 'status' => 'scheduled', 'sync_state' => isset( $lesson['sync_state'] ) ? $lesson['sync_state'] : 'error' );
		}
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 10;
		$page     = isset( $args['page'] ) ? (int) $args['page'] : 1;
		$per_page = $per_page < 1 ? 10 : min( 100, $per_page );
		$page     = $page < 1 ? 1 : $page;
		$total    = count( $matched );
		return array(
			'success' => true,
			'message' => 'Live lessons retrieved successfully.',
			'data'    => array(
				'data'       => array_values( array_slice( $matched, ( $page - 1 ) * $per_page, $per_page ) ),
				'pagination' => array( 'total' => $total, 'page' => $page, 'per_page' => $per_page, 'total_pages' => (int) ceil( $total / $per_page ) ),
			),
		);
	}
}
