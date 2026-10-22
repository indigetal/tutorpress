<?php
/**
 * Canonical JSON bytes for the dormant live-lesson sync envelope.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Recursive key order, compact UTF-8 JSON, and the stable envelope field set. */
class TutorPress_Live_Lesson_Sync_Envelope {
	/** Binary JSON bytes, or null when the value cannot be encoded. */
	public static function canonical_bytes( $value ) {
		$encoded = json_encode( self::ordered( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $encoded ) ? $encoded : null;
	}

	/** Sort map keys recursively. List order stays as submitted. */
	private static function ordered( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$index = 0;
		foreach ( array_keys( $value ) as $key ) {
			if ( $key !== $index++ ) {
				ksort( $value, SORT_STRING );
				break;
			}
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::ordered( $item );
		}
		return $value;
	}

	/** Stable envelope, or null when a field, state, or secret key is rejected. */
	public static function envelope( $value ) {
		$names = array( 'error', 'operation', 'owner_id', 'provenance', 'provider', 'provider_id', 'reconciliation_required', 'schema_version', 'state', 'version', 'warning' );
		$keys  = is_array( $value ) ? array_keys( $value ) : array();
		sort( $keys, SORT_STRING );
		if ( $names !== $keys || self::forbidden( $value ) || ! is_int( $value['schema_version'] ) || $value['schema_version'] < 1 || ! is_int( $value['version'] ) || $value['version'] < 1 || ! in_array( $value['state'], array( 'pending', 'error', 'unlinked', 'synced' ), true ) || ! is_string( $value['provider'] ) || '' === $value['provider'] || ! is_int( $value['owner_id'] ) || $value['owner_id'] < 1 || ( null !== $value['provider_id'] && ( ! is_string( $value['provider_id'] ) || '' === $value['provider_id'] ) ) || ( null !== $value['provenance'] && ! is_string( $value['provenance'] ) ) || ( null !== $value['error'] && ! is_string( $value['error'] ) && ! is_array( $value['error'] ) ) || ( null !== $value['warning'] && ! is_string( $value['warning'] ) && ! is_array( $value['warning'] ) ) || ! is_bool( $value['reconciliation_required'] ) || ( null !== $value['operation'] && ( ! is_array( $value['operation'] ) || ! self::snapshot( $value['operation'] ) ) ) ) {
			return null;
		}
		return $value;
	}

	/** True when a credential, token, header, or raw-provider key appears anywhere. */
	private static function forbidden( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && 1 === preg_match( '/token|secret|credential|password|api_key|authorization|header|cookie|(?:^|_)raw(?:$|_)|exception/i', $key ) ) {
				return true;
			}
			if ( self::forbidden( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/** Common fields plus schedule, provider target, and the per-kind delete hook. */
	private static function snapshot( $value ) {
		$names = array( 'base_version', 'content', 'course_id', 'delete_hook', 'dispatch_state', 'duration', 'end_local', 'kind', 'menu_order', 'operation_id', 'owner_id', 'post_author', 'provider', 'provider_id', 'provider_target', 'start_local', 'started_at', 'timezone', 'title', 'topic_id' );
		$keys  = array_keys( $value );
		sort( $keys, SORT_STRING );
		return $names === $keys
			&& is_string( $value['operation_id'] ) && '' !== $value['operation_id']
			&& in_array( $value['kind'], array( 'create', 'update', 'delete' ), true )
			&& is_string( $value['started_at'] ) && '' !== $value['started_at']
			&& is_int( $value['base_version'] ) && $value['base_version'] >= 0
			&& is_string( $value['provider'] ) && '' !== $value['provider']
			&& is_int( $value['owner_id'] ) && $value['owner_id'] >= 1
			&& ( null === $value['provider_id'] || ( is_string( $value['provider_id'] ) && '' !== $value['provider_id'] ) )
			&& in_array( $value['dispatch_state'], array( 'not_dispatched', 'dispatch_possible', 'confirmed' ), true )
			&& is_int( $value['course_id'] ) && $value['course_id'] >= 1
			&& is_int( $value['topic_id'] ) && $value['topic_id'] >= 1
			&& is_int( $value['post_author'] ) && $value['post_author'] >= 1
			&& is_int( $value['menu_order'] ) && $value['menu_order'] >= 0
			&& is_string( $value['title'] )
			&& is_string( $value['content'] )
			&& self::schedule( $value )
			&& self::provider_target( $value )
			&& self::delete_hook( $value );
	}

	/** Existing wall-clock and timezone checks. Zoom duration uses the existing cap. */
	private static function schedule( $value ) {
		$duration = $value['duration'];
		$keys     = is_array( $duration ) ? array_keys( $duration ) : array();
		sort( $keys, SORT_STRING );
		return TutorPress_Live_Lesson_Datetime::is_wall_clock( $value['start_local'] ) && TutorPress_Live_Lesson_Datetime::is_wall_clock( $value['end_local'] ) && TutorPress_Live_Lesson_Datetime::is_approved_timezone( $value['timezone'] ) && array( 'unit', 'value' ) === $keys && is_int( $duration['value'] ) && in_array( $duration['unit'], array( 'hours', 'minutes' ), true ) && ( 'zoom' !== $value['provider'] || is_array( TutorPress_Live_Lesson_Datetime::zoom_duration( $duration['value'], $duration['unit'] ) ) );
	}

	/** Zoom host and settings, or Google Meet scalars. Emails and secrets stay out. */
	private static function provider_target( $value ) {
		$target = $value['provider_target'];
		$keys   = is_array( $target ) ? array_keys( $target ) : array();
		sort( $keys, SORT_STRING );
		if ( 'zoom' === $value['provider'] ) {
			return array( 'auto_recording', 'enforce_login', 'host_id', 'host_video', 'join_before_host', 'mute_upon_entry', 'participant_video', 'waiting_room' ) === $keys && is_string( $target['host_id'] ) && '' !== $target['host_id'] && is_bool( $target['join_before_host'] ) && false === $target['host_video'] && false === $target['participant_video'] && false === $target['mute_upon_entry'] && false === $target['enforce_login'] && in_array( $target['auto_recording'], array( 'cloud', 'local', 'none' ), true ) && is_bool( $target['waiting_room'] );
		}
		return 'google_meet' === $value['provider'] && array( 'add_enrolled_students', 'reminder_minutes', 'send_updates', 'status', 'transparency', 'visibility' ) === $keys && in_array( $target['add_enrolled_students'], array( 'No', 'Yes' ), true ) && is_scalar( $target['reminder_minutes'] ) && is_scalar( $target['send_updates'] ) && is_scalar( $target['status'] ) && is_scalar( $target['transparency'] ) && is_scalar( $target['visibility'] );
	}

	/** Null for create and update. Delete matches tutorpress_live_lesson_deleted. */
	private static function delete_hook( $value ) {
		$hook = $value['delete_hook'];
		$keys = is_array( $hook ) ? array_keys( $hook ) : array();
		sort( $keys, SORT_STRING );
		return 'delete' === $value['kind'] ? array( 'lesson_id', 'title', 'topic_id', 'type' ) === $keys && is_int( $hook['lesson_id'] ) && $hook['lesson_id'] >= 1 && is_string( $hook['title'] ) && is_int( $hook['topic_id'] ) && $hook['topic_id'] >= 1 && in_array( $hook['type'], array( 'google_meet', 'zoom' ), true ) : null === $hook;
	}

	/** Absence is expected version 0. A valid envelope returns its stored version. */
	public static function expected_version( $value ) {
		$stored = null === $value ? null : self::envelope( $value );
		return null === $value ? 0 : ( is_array( $stored ) ? $stored['version'] : null );
	}

	/** Only absence initializes one version-1 envelope. */
	public static function initialize_version_one( $prior, $next ) {
		$stored = self::envelope( $next );
		return null === $prior && is_array( $stored ) && 1 === $stored['version'] ? $stored : null;
	}

	/** Uncertain-create attach, legacy attach, or ordinary create/edit. Mixed shapes return null. */
	public static function identity_request( $value ) {
		$names = array( 'operation_id', 'provider_id', 'version' );
		$keys  = is_array( $value ) ? array_keys( $value ) : array();
		sort( $keys, SORT_STRING );
		if ( $names !== $keys || ! is_int( $value['version'] ) || $value['version'] < 0 ) {
			return null;
		}
		$operation = is_string( $value['operation_id'] ) && '' !== $value['operation_id'];
		$provider  = is_string( $value['provider_id'] ) && '' !== $value['provider_id'];
		return ( null === $value['provider_id'] && null === $value['operation_id'] && $value['version'] >= 1 ) || ( $provider && $operation && $value['version'] >= 1 ) || ( $provider && null === $value['operation_id'] && 0 === $value['version'] ) ? $value : null;
	}
}
