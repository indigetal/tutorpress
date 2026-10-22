<?php
/**
 * Direct Google duplicate for dormant live lessons.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Publish Google copy with schedule fields only. No provider call. */
final class TutorPress_Live_Lesson_Duplicate_Service {
	/** Null unless the source item and target topic are authorized Google records. */
	public static function duplicate( array $source, array $topic, array $course, array $target, array $target_course, array $children, array $meta, $can ) {
		$source_id = TutorPress_Live_Lesson_Authorization::authorize( $source, $topic, $course, $can );
		$target_id = TutorPress_Live_Lesson_Authorization::authorize_create( $target, $target_course, $can );
		if ( null === $source_id || null === $target_id || ! isset( $source['post_type'] ) || 'tutor-google-meet' !== $source['post_type'] ) {
			return null;
		}
		$copy    = array(
			'post_title'   => ( isset( $source['post_title'] ) ? (string) $source['post_title'] : '' ) . ' (Copy)',
			'post_content' => isset( $source['post_content'] ) ? (string) $source['post_content'] : '',
			'post_status'  => 'publish',
			'post_type'    => 'tutor-google-meet',
			'post_parent'  => (int) $target['ID'],
			'menu_order'   => TutorPress_Live_Lesson_Ordering::next_menu_order( $children ),
		);
		$copy_id = wp_insert_post( $copy );
		if ( $copy_id instanceof WP_Error || ! is_int( $copy_id ) || $copy_id < 1 ) {
			return null;
		}
		foreach ( array( 'tutor-google-meet-start-datetime', 'tutor-google-meet-end-datetime' ) as $key ) {
			if ( isset( $meta[ $key ] ) && is_string( $meta[ $key ] ) && '' !== $meta[ $key ] ) {
				add_post_meta( $copy_id, $key, $meta[ $key ] );
			}
		}
		$copy['ID'] = $copy_id;
		$bytes      = self::initialize_copy( $copy, $target_course, $target, $can );
		if ( ! is_string( $bytes ) ) {
			return $bytes;
		}
		try {
			do_action( 'tutorpress_live_lesson_duplicated', $copy_id, (int) $source['ID'], self::response_data( $copy, $target, $target_course, $meta ) );
		} catch ( Throwable $thrown ) {
			return self::transition_error( $copy, $target, $target_course, $can, $bytes );
		}
		return $copy_id;
	}

	/** Current Google duplicate payload. Identity fields stay empty. */
	private static function response_data( array $copy, array $topic, array $course, array $meta ) {
		$start = isset( $meta['tutor-google-meet-start-datetime'] ) && is_string( $meta['tutor-google-meet-start-datetime'] ) ? $meta['tutor-google-meet-start-datetime'] : '';
		$end   = isset( $meta['tutor-google-meet-end-datetime'] ) && is_string( $meta['tutor-google-meet-end-datetime'] ) ? $meta['tutor-google-meet-end-datetime'] : '';
		return array( 'id' => (int) $copy['ID'], 'title' => $copy['post_title'], 'description' => $copy['post_content'], 'type' => 'google_meet', 'topicId' => (int) $topic['ID'], 'courseId' => (int) $course['ID'], 'status' => 'scheduled', 'createdAt' => '', 'updatedAt' => '', 'startDateTime' => $start, 'endDateTime' => $end, 'settings' => array( 'timezone' => 'UTC', 'add_enrolled_students' => 'No' ), 'providerConfig' => array( 'meetingUrl' => '', 'eventId' => '' ) );
	}

	/** Hook failure keeps the unlinked record. persisted_result() stays on cleanup failure. */
	private static function transition_error( array $copy, array $topic, array $course, $can, $bytes ) {
		$decoded   = is_string( $bytes ) ? json_decode( $bytes, true ) : null;
		$projected = is_array( $decoded ) ? TutorPress_Live_Lesson_Response_Projector::project_item( $copy, $topic, $course, $can, $decoded, null ) : null;
		if ( ! is_array( $projected ) ) {
			return null;
		}
		return new WP_Error(
			'live_lesson_persisted_transition',
			'Live lesson transition failed.',
			array(
				'status'           => 500,
				'record_id'        => $projected['id'],
				'course_id'        => $projected['courseId'],
				'topic_id'         => $projected['topicId'],
				'operation_id'     => is_array( $projected['operation'] ) ? $projected['operation']['operation_id'] : null,
				'sync_state'       => $projected['sync_state'],
				'sync_version'     => $projected['sync_version'],
				'persisted'        => true,
				'refetch_required' => true,
				'projected_record' => $projected,
			)
		);
	}

	/** Initialize one unlinked envelope. Delete the copy when initialization fails. */
	private static function initialize_copy( array $copy, array $course, array $topic, $can ) {
		$id     = isset( $copy['ID'] ) ? (int) $copy['ID'] : 0;
		$owner  = isset( $course['post_author'] ) ? (int) $course['post_author'] : 0;
		$token  = bin2hex( random_bytes( 16 ) );
		$expiry = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
		$next   = array( 'schema_version' => 1, 'version' => 1, 'state' => 'unlinked', 'provider' => 'google_meet', 'owner_id' => $owner, 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null );
		$lock   = $id > 0 && $owner > 0 ? TutorPress_Live_Lesson_Sync_Repository::acquire( $id, 'initialize', $token, $expiry, static function ( $name, $bytes ) { return add_option( $name, $bytes, '', false ); } ) : null;
		$stored = null;
		if ( is_string( $lock ) ) {
			try {
				$stored = TutorPress_Live_Lesson_Sync_Repository::during( $id, $token, $lock, static function () use ( $id, $lock, $token, $next ) {
					return TutorPress_Live_Lesson_Sync_Repository::initialize( $id, $lock, $token, 0, $next, static function ( $post_id, $key ) { return self::meta_rows( $post_id, $key ); }, static function ( $post_id, $key, $value ) { return add_post_meta( $post_id, $key, $value ); }, static function () { return true; } );
				}, static function ( $name, $prior ) { return self::delete_lock( $name, $prior ); } );
			} catch ( Throwable $thrown ) {
				$stored = null;
			}
		}
		if ( is_string( $stored ) ) {
			return $stored;
		}
		if ( true === wp_delete_post( $id, true ) ) {
			return null;
		}
		$copy['post_status'] = 'publish';
		return TutorPress_Live_Lesson_Response_Projector::persisted_result( $copy, $topic, $course, $can, null, null, array( 'persisted' => false, 'status' => 500 ) );
	}

	/** Rows shaped for envelope initialization. */
	private static function meta_rows( $post_id, $key ) {
		$values = get_post_meta( (int) $post_id, $key, false );
		$rows   = array();
		foreach ( is_array( $values ) ? $values : array() as $value ) {
			$rows[] = array( 'post_id' => (int) $post_id, 'meta_value' => $value );
		}
		return $rows;
	}

	/** Delete the lock in one statement when the stored bytes still match. */
	private static function delete_lock( $name, $prior ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior ) );
		return 1 === (int) $wpdb->rows_affected;
	}
}
