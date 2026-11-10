<?php
/**
 * Authorized item projection for dormant live lessons.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Publish-record projection. Sync state stays visible. */
class TutorPress_Live_Lesson_Response_Projector {
	/** Null unless the item context is authorized and publish. */
	public static function project_item( array $item, array $topic, array $course, $can, $envelope, $metadata ) {
		$stored = null === $envelope ? null : TutorPress_Live_Lesson_Sync_Envelope::envelope( $envelope );
		if ( null === TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can ) || 'publish' !== ( isset( $item['post_status'] ) ? $item['post_status'] : '' ) || ( null !== $envelope && ! is_array( $stored ) ) ) {
			return null;
		}
		$operation        = is_array( $stored ) && is_array( $stored['operation'] ) ? $stored['operation'] : null;
		$target           = is_array( $operation ) ? array( 'title' => $operation['title'], 'content' => $operation['content'], 'start_local' => $operation['start_local'], 'end_local' => $operation['end_local'], 'timezone' => $operation['timezone'], 'duration' => $operation['duration'], 'provider_target' => $operation['provider_target'] ) : null;
		$from_target      = is_array( $stored ) && in_array( $stored['state'], array( 'pending', 'error' ), true ) && ! is_array( $metadata );
		$record           = $from_target ? $target : ( is_array( $metadata ) ? $metadata : null );
		$edit             = $from_target ? self::target_fields( is_array( $target ) ? $target : array() ) : self::reader_fields( $item, is_array( $metadata ) ? $metadata : array() );
		$edit['password'] = self::blob_password( $metadata );
		return array_merge( array( 'id' => (int) $item['ID'], 'title' => isset( $item['post_title'] ) ? $item['post_title'] : '', 'description' => isset( $item['post_content'] ) ? $item['post_content'] : '', 'type' => 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : 'google_meet', 'topicId' => (int) $topic['ID'], 'courseId' => (int) $course['ID'], 'status' => 'scheduled', 'sync_state' => is_array( $stored ) ? $stored['state'] : 'error', 'sync_version' => TutorPress_Live_Lesson_Sync_Envelope::expected_version( null === $envelope ? null : $stored ), 'persisted_envelope' => is_array( $stored ), 'operation' => is_array( $operation ) ? array( 'operation_id' => $operation['operation_id'], 'kind' => $operation['kind'], 'dispatch_state' => $operation['dispatch_state'] ) : null, 'warning' => is_array( $stored ) ? $stored['warning'] : null, 'error' => is_array( $stored ) ? $stored['error'] : null, 'resolution' => null, 'record' => self::safe( $record ) ), $edit );
	}

	/** String password from an array _tutor_zm_data blob. */
	private static function blob_password( $metadata ) {
		$blob = is_array( $metadata ) && isset( $metadata['_tutor_zm_data'] ) && is_array( $metadata['_tutor_zm_data'] ) ? $metadata['_tutor_zm_data'] : array();
		return isset( $blob['password'] ) && is_string( $blob['password'] ) ? $blob['password'] : '';
	}

	/** Hydrated schedule, settings, and host. */
	private static function reader_fields( array $item, array $metadata ) {
		$zoom    = 'tutor_zoom_meeting' === $item['post_type'];
		$adapter = $zoom ? new TutorPress_Zoom_Provider_Adapter( null, null, null, null, null, null, null ) : new TutorPress_Google_Meet_Provider_Adapter( null, null, null, null, null );
		$row     = $adapter->hydrate_item( $metadata );
		return self::edit_fields( $row, isset( $row['duration'] ) && is_array( $row['duration'] ) ? $row['duration'] : null );
	}

	/** In-progress schedule when no provider meta is stored. */
	private static function target_fields( array $target ) {
		$provider = isset( $target['provider_target'] ) && is_array( $target['provider_target'] ) ? $target['provider_target'] : array();
		$row      = array_merge( $provider, array( 'start_local' => self::text( $target, 'start_local' ), 'end_local' => self::text( $target, 'end_local' ), 'timezone' => self::text( $target, 'timezone' ) ) );
		$duration = isset( $target['duration'] ) && is_array( $target['duration'] ) ? $target['duration'] : null;
		return self::edit_fields( $row, $duration );
	}

	/** Start, end, duration, settings, and host. */
	private static function edit_fields( array $row, $duration ) {
		$zone = self::text( $row, 'timezone' );
		return array(
			'startDateTime'  => self::text( $row, 'start_local' ),
			'endDateTime'    => self::text( $row, 'end_local' ),
			'duration'       => $duration,
			'settings'       => array(
				'timezone'            => '' !== $zone ? $zone : 'UTC',
				'autoRecording'       => self::text( $row, 'auto_recording' ),
				'waitingRoom'         => isset( $row['waiting_room'] ) && true === $row['waiting_room'],
				'addEnrolledStudents' => self::text( $row, 'add_enrolled_students' ),
				'status'              => self::text( $row, 'status' ),
				'transparency'        => self::text( $row, 'transparency' ),
				'visibility'          => self::text( $row, 'visibility' ),
				'reminder_minutes'    => isset( $row['reminder_minutes'] ) && is_int( $row['reminder_minutes'] ) ? $row['reminder_minutes'] : null,
			),
			'providerConfig' => array( 'host' => self::text( $row, 'host_id' ) ),
		);
	}

	/** Stored string, or an empty string. */
	private static function text( array $row, $key ) {
		return isset( $row[ $key ] ) && is_string( $row[ $key ] ) ? $row[ $key ] : '';
	}

	/** Drop credential and token keys from projected records. */
	private static function safe( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && 1 === preg_match( '/token|secret|credential|password|api_key/i', $key ) ) {
				unset( $value[ $key ] );
				continue;
			}
			$value[ $key ] = self::safe( $item );
		}
		return $value;
	}

	/** Topic-row summary. Actions are narrow descriptors with no provider id. */
	public static function project_summary( array $item, array $topic, array $course, $can, $envelope ) {
		$full = self::project_item( $item, $topic, $course, $can, $envelope, null );
		if ( ! is_array( $full ) ) {
			return null;
		}
		return array(
			'id'           => $full['id'],
			'sync_state'   => $full['sync_state'],
			'sync_version' => $full['sync_version'],
			'menuOrder'    => isset( $item['menu_order'] ) ? (int) $item['menu_order'] : 0,
			'actions'      => self::actions( $envelope ),
		);
	}

	/** Inspect, retry, resolve-owner, abandon, or one distinct attach shape. */
	private static function actions( $envelope ) {
		$stored = null === $envelope ? null : TutorPress_Live_Lesson_Sync_Envelope::envelope( $envelope );
		if ( ! is_array( $stored ) ) {
			return array(
				array( 'action' => 'resolve_owner', 'version' => 0 ),
				array( 'action' => 'attach', 'kind' => 'legacy_identity', 'version' => 0 ),
			);
		}
		$operation = is_array( $stored['operation'] ) ? $stored['operation'] : null;
		$idless    = is_array( $operation ) && 'create' === $operation['kind'] && null === $stored['provider_id'] && null === $operation['provider_id'];
		if ( $idless ) {
			return array(
				array( 'action' => 'inspect', 'version' => $stored['version'], 'operation_id' => $operation['operation_id'] ),
				array( 'action' => 'attach', 'kind' => 'uncertain_create', 'version' => $stored['version'], 'operation_id' => $operation['operation_id'] ),
				array( 'action' => 'abandon', 'version' => $stored['version'], 'operation_id' => $operation['operation_id'] ),
			);
		}
		$actions = array();
		if ( is_array( $operation ) && 'not_dispatched' === $operation['dispatch_state'] ) {
			$actions[] = array( 'action' => 'retry', 'version' => $stored['version'], 'operation_id' => $operation['operation_id'] );
		}
		if ( is_string( $stored['provider_id'] ) && '' !== $stored['provider_id'] ) {
			$actions[] = array( 'action' => 'inspect', 'version' => $stored['version'] );
		}
		return $actions;
	}

	/** Persisted success stays success. Refresh failure is only a sanitized warning. */
	public static function persisted_result( array $item, array $topic, array $course, $can, $envelope, $metadata, array $outcome, $refresh_warning = null ) {
		$projected = self::project_item( $item, $topic, $course, $can, $envelope, $metadata );
		if ( ! is_array( $projected ) ) {
			return null;
		}
		$warning = is_string( $refresh_warning ) && '' !== $refresh_warning ? ( 1 === preg_match( '/token|secret|credential|password|api_key/i', $refresh_warning ) ? 'refresh_failed' : $refresh_warning ) : null;
		if ( ! empty( $outcome['persisted'] ) ) {
			if ( null !== $warning ) {
				$projected['warning']          = $warning;
				$projected['refetch_required'] = true;
			}
			$projected['persisted'] = true;
			return $projected;
		}
		return new WP_Error(
			isset( $outcome['code'] ) && is_string( $outcome['code'] ) ? $outcome['code'] : 'live_lesson_persisted_error',
			'Live lesson persistence failed.',
			array(
				'status'            => isset( $outcome['status'] ) ? (int) $outcome['status'] : 500,
				'record_id'         => $projected['id'],
				'course_id'         => $projected['courseId'],
				'topic_id'          => $projected['topicId'],
				'operation_id'      => is_array( $projected['operation'] ) ? $projected['operation']['operation_id'] : null,
				'sync_state'        => $projected['sync_state'],
				'sync_version'      => $projected['sync_version'],
				'persisted'         => false,
				'refetch_required'  => true,
				'warning'           => $warning,
				'projected_record'  => $projected,
			)
		);
	}
}
