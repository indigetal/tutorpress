<?php
/**
 * Dormant create validation, pending-snapshot staging, and stored-id inspect.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Authorize a create, stage it, stage an unlinked explicit save, stage a synced update, dispatch the provider, stage a pending delete, clean up a pre-dispatch insert failure, inspect a stored provider id, and initialize a legacy identity. */
final class TutorPress_Live_Lesson_Sync_Orchestrator {
	/** Null unless the course, owner, schedule, and Zoom host checks pass. Provider identity is rejected. */
	public static function validate_create( array $topic, array $course, $can, array $input, array $children, $adapter, DateTimeImmutable $now ) {
		if ( array_key_exists( 'provider_id', $input ) || array_key_exists( 'id', $input ) ) {
			return null;
		}
		$course_id = TutorPress_Live_Lesson_Authorization::authorize_create( $topic, $course, $can );
		$provider  = isset( $input['provider'] ) && is_string( $input['provider'] ) ? $input['provider'] : '';
		if ( null === $course_id || ( 'google_meet' !== $provider && 'zoom' !== $provider ) || ! $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter ) {
			return null;
		}
		$owner_id = 'google_meet' === $provider ? TutorPress_Live_Lesson_Owner_Resolver::google( $course_id, isset( $input['acting_user_id'] ) ? $input['acting_user_id'] : 0 ) : self::zoom_input_owner( $course_id, $input );
		if ( null === $owner_id || true !== $adapter->preflight( $owner_id ) ) {
			return null;
		}
		$host_id = null;
		if ( 'zoom' === $provider ) {
			$host = TutorPress_Live_Lesson_Owner_Resolver::host_input( $course_id, $owner_id, isset( $input['host_id'] ) ? $input['host_id'] : '' );
			if ( null === $host || true !== $adapter->validate_host( $owner_id, $host['host_id'] ) ) {
				return null;
			}
			$host_id = $host['host_id'];
		}
		$start = isset( $input['start'] ) ? $input['start'] : '';
		$zone  = isset( $input['timezone'] ) ? $input['timezone'] : '';
		if ( ! TutorPress_Live_Lesson_Datetime::is_future_wall_clock( $start, $zone, $now ) ) { return null; }
		$end      = null;
		$duration = null;
		if ( 'google_meet' === $provider ) {
			$end = isset( $input['end'] ) ? $input['end'] : '';
			if ( ! TutorPress_Live_Lesson_Datetime::google_end_after_start( $start, $end, $zone ) ) { return null; }
		} else {
			$typed    = isset( $input['duration'] ) && is_array( $input['duration'] ) ? $input['duration'] : array();
			$duration = TutorPress_Live_Lesson_Datetime::zoom_duration( isset( $typed['value'] ) ? $typed['value'] : null, isset( $typed['unit'] ) ? $typed['unit'] : '' );
			if ( null === $duration ) { return null; }
		}
		return array( 'course_id' => $course_id, 'topic_id' => (int) $topic['ID'], 'provider' => $provider, 'owner_id' => $owner_id, 'menu_order' => TutorPress_Live_Lesson_Ordering::next_menu_order( $children ), 'start' => $start, 'timezone' => $zone, 'end' => $end, 'duration' => $duration, 'host_id' => $host_id );
	}

	/** Insert one publish post and acquire its create lock. Dispatch has not occurred. */
	public static function insert_locked( array $topic, array $course, $can, array $input, array $children, $adapter, DateTimeImmutable $now, $token ) {
		$valid  = self::validate_create( $topic, $course, $can, $input, $children, $adapter, $now );
		$author = isset( $course['post_author'] ) ? (int) $course['post_author'] : 0;
		if ( null === $valid || $author < 1 || ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$post_id = wp_insert_post( array( 'post_type' => 'google_meet' === $valid['provider'] ? 'tutor-google-meet' : 'tutor_zoom_meeting', 'post_status' => 'publish', 'post_parent' => (int) $topic['ID'], 'post_author' => $author, 'post_title' => isset( $input['title'] ) && is_string( $input['title'] ) ? $input['title'] : '', 'post_content' => isset( $input['content'] ) && is_string( $input['content'] ) ? $input['content'] : '', 'menu_order' => $valid['menu_order'] ), true );
		if ( ! is_int( $post_id ) || $post_id < 1 ) {
			return null;
		}
		$expiry = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
		$lock   = TutorPress_Live_Lesson_Sync_Repository::acquire( $post_id, 'create', $token, $expiry, static function ( $name, $bytes ) { return add_option( $name, $bytes, '', false ); } );
		if ( ! is_string( $lock ) ) {
			return self::cleanup_before_dispatch( $post_id, self::publish_item( $post_id, $valid, $topic, $input ), $topic, $course, $can, null, null );
		}
		$valid['post_id']        = $post_id;
		$valid['lock']           = $lock;
		$valid['dispatch_state'] = 'not_dispatched';
		return $valid;
	}

	/** Initialize one version-1 pending create envelope before provider dispatch. */
	public static function stage_pending( array $topic, array $course, $can, array $input, array $children, $adapter, DateTimeImmutable $now, $token ) {
		$valid    = self::insert_locked( $topic, $course, $can, $input, $children, $adapter, $now, $token );
		if ( $valid instanceof WP_Error ) {
			return $valid;
		}
		$schedule = is_array( $valid ) ? self::create_schedule( $valid ) : null;
		$target   = is_array( $schedule ) ? self::create_target( $valid, $input ) : null;
		if ( null === $target ) {
			return null;
		}
		$operation = array( 'operation_id' => bin2hex( random_bytes( 16 ) ), 'kind' => 'create', 'started_at' => $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), 'base_version' => 0, 'provider' => $valid['provider'], 'owner_id' => $valid['owner_id'], 'provider_id' => null, 'dispatch_state' => 'not_dispatched', 'course_id' => $valid['course_id'], 'topic_id' => $valid['topic_id'], 'post_author' => (int) $course['post_author'], 'menu_order' => $valid['menu_order'], 'title' => isset( $input['title'] ) && is_string( $input['title'] ) ? $input['title'] : '', 'content' => isset( $input['content'] ) && is_string( $input['content'] ) ? $input['content'] : '', 'start_local' => $schedule['start_local'], 'end_local' => $schedule['end_local'], 'timezone' => $schedule['timezone'], 'duration' => $schedule['duration'], 'provider_target' => $target, 'delete_hook' => null );
		$next      = array( 'schema_version' => 1, 'version' => 1, 'state' => 'pending', 'provider' => $valid['provider'], 'owner_id' => $valid['owner_id'], 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation );
		$bytes = null;
		try {
			$bytes = TutorPress_Live_Lesson_Sync_Repository::initialize( $valid['post_id'], $valid['lock'], $token, 0, $next, static function ( $post_id, $key ) {
				$rows = array();
				foreach ( get_post_meta( (int) $post_id, $key, false ) as $value ) {
					$rows[] = array( 'post_id' => (int) $post_id, 'meta_value' => $value );
				}
				return $rows;
			}, static function ( $post_id, $key, $value ) { return add_post_meta( $post_id, $key, wp_slash( $value ) ); }, static function ( $name ) { return true; } );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
			$bytes = null;
		}
		if ( ! is_string( $bytes ) ) {
			return self::cleanup_before_dispatch( $valid['post_id'], self::publish_item( $valid['post_id'], $valid, $topic, $input ), $topic, $course, $can, $token, $valid['lock'] );
		}
		$valid['envelope'] = $bytes;
		return $valid;
	}

	/** Dispatch a version-1 or version-2 pending create. Synced completion releases the lock, then fires one save hook. */
	public static function dispatch_create( array $staged, $token, $adapter, $read, $update, $affected, $write, array $topic = array(), array $course = array(), $can = null ) {
		$prior     = isset( $staged['envelope'] ) && is_string( $staged['envelope'] ) ? json_decode( $staged['envelope'], true ) : null;
		$operation = is_array( $prior ) && isset( $prior['operation'] ) && is_array( $prior['operation'] ) ? $prior['operation'] : null;
		$version   = is_array( $prior ) && isset( $prior['version'] ) ? $prior['version'] : null;
		if ( ! is_array( $prior ) || ( ! is_int( $version ) || $version < 1 ) || 'pending' !== $prior['state'] || null !== $prior['provider_id'] || ! is_array( $operation ) || 'create' !== $operation['kind'] || 'not_dispatched' !== $operation['dispatch_state'] || ! isset( $staged['post_id'], $staged['lock'], $staged['owner_id'] ) || ! is_string( $token ) || ! TutorPress_Live_Lesson_Sync_Repository::owns( $staged['lock'], $token ) || ! $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter || ! is_callable( $read ) || ! is_callable( $update ) || ! is_callable( $affected ) || ! is_callable( $write ) ) { return null; }
		$marked                                = $prior;
		$marked['version']                     = $version + 1;
		$marked['operation']['dispatch_state'] = 'dispatch_possible';
		$possible                              = TutorPress_Live_Lesson_Sync_Repository::exchange( (int) $staged['post_id'], $staged['lock'], $token, 'create', 'pending', $version, $staged['envelope'], $marked, $read, $update, $affected );
		if ( ! is_string( $possible ) ) { return null; }
		$target = self::dispatch_target( $operation );
		$result = null;
		try {
			$result = TutorPress_Live_Lesson_Provider_Result::from( $adapter->create( (int) $staged['owner_id'], $target ) );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
		}
		$id      = is_array( $result ) ? TutorPress_Live_Lesson_Provider_Result::identity( $result ) : null;
		$outcome = is_array( $result ) ? $result['outcome'] : 'uncertain';
		$map     = 'confirmed' === $outcome && is_string( $id ) ? $adapter->metadata_map( $result, $target ) : null;
		$synced  = is_array( $map ) && self::map_stored( (int) $staged['post_id'], $map, $write, $read );
		$next    = json_decode( $possible, true );
		$next['version']                     = $version + 2;
		$next['state']                       = $synced ? 'synced' : 'error';
		$next['provider_id']                 = is_string( $id ) ? $id : null;
		$next['provenance']                  = is_string( $id ) ? 'create' : null;
		$next['reconciliation_required']     = ! $synced && ( ! is_array( $result ) || 'uncertain' === $outcome || 'confirmed' === $outcome || is_string( $id ) );
		$next['error']                       = $synced ? null : 'provider_create_failed';
		$next['operation']['provider_id']    = $next['provider_id'];
		$next['operation']['dispatch_state'] = $synced ? 'confirmed' : 'dispatch_possible';
		$bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( (int) $staged['post_id'], $staged['lock'], $token, 'create', 'pending', $version + 1, $possible, $next, $read, $update, $affected );
		if ( ! is_string( $bytes ) ) { return null; }
		$staged['envelope'] = $bytes;
		return 'synced' === $next['state'] ? self::finish_synced( $staged, $token, $topic, $course, $can ) : $staged;
	}

	/** Release the exact token, then fire one provider save hook. A crash after release is not replayed. */
	private static function finish_synced( array $staged, $token, array $topic, array $course, $can ) {
		$released = TutorPress_Live_Lesson_Sync_Repository::release( (int) $staged['post_id'], $token, $staged['lock'], static function ( $name, $prior ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior ) );
			return 1 === (int) $wpdb->rows_affected;
		} );
		if ( true !== $released ) {
			return $staged;
		}
		try {
			do_action( 'zoom' === $staged['provider'] ? 'tutor_zoom_after_save_meeting' : 'tutor_google_meet_after_save_meeting', (int) $staged['post_id'] );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
			return self::transition_error( $staged, $topic, $course, $can );
		}
		return $staged;
	}

	/** Hook failure keeps the synced record. The exception text is discarded. */
	private static function transition_error( array $staged, array $topic, array $course, $can ) {
		$decoded   = json_decode( $staged['envelope'], true );
		$operation = is_array( $decoded ) && isset( $decoded['operation'] ) && is_array( $decoded['operation'] ) ? $decoded['operation'] : array();
		$projected = is_array( $decoded ) && isset( $topic['ID'] ) ? TutorPress_Live_Lesson_Response_Projector::project_item( self::publish_item( $staged['post_id'], $staged, $topic, $operation ), $topic, $course, $can, $decoded, null ) : null;
		if ( ! is_array( $projected ) ) {
			return null;
		}
		return new WP_Error( 'live_lesson_persisted_transition', 'Live lesson transition failed.', array( 'status' => 500, 'record_id' => $projected['id'], 'course_id' => $projected['courseId'], 'topic_id' => $projected['topicId'], 'operation_id' => is_array( $projected['operation'] ) ? $projected['operation']['operation_id'] : null, 'sync_state' => $projected['sync_state'], 'sync_version' => $projected['sync_version'], 'persisted' => true, 'refetch_required' => true, 'projected_record' => $projected ) );
	}

	/** Zoom keeps a nested provider_target. Google scalars are copied onto that same target. */
	private static function dispatch_target( array $operation ) {
		if ( 'google_meet' === $operation['provider'] && isset( $operation['provider_target'] ) && is_array( $operation['provider_target'] ) ) {
			return array_merge( $operation, $operation['provider_target'] );
		}
		return $operation;
	}

	/** Write every mapped value and require each re-read to match. An empty map is incomplete. */
	private static function map_stored( $post_id, array $map, $write, $read ) {
		foreach ( $map as $key => $value ) {
			$wrote = is_string( $key ) ? $write( $post_id, $key, $value ) : false;
			$rows  = is_int( $wrote ) ? $read( $post_id, $key ) : null;
			if ( ! is_array( $rows ) || 1 !== count( $rows ) || ! isset( $rows[0]['meta_value'] ) || $rows[0]['meta_value'] !== $value ) { return false; }
		}
		return array() !== $map;
	}

	/** Local publish row for a pre-dispatch cleanup projection. Credentials stay out. */
	private static function publish_item( $post_id, array $valid, array $topic, array $input ) {
		return array(
			'ID'           => (int) $post_id,
			'post_type'    => 'google_meet' === $valid['provider'] ? 'tutor-google-meet' : 'tutor_zoom_meeting',
			'post_status'  => 'publish',
			'post_parent'  => (int) $topic['ID'],
			'post_title'   => isset( $input['title'] ) && is_string( $input['title'] ) ? $input['title'] : '',
			'post_content' => isset( $input['content'] ) && is_string( $input['content'] ) ? $input['content'] : '',
		);
	}

	/** Force-delete before dispatch. An acquired lock is released after that attempt. */
	private static function cleanup_before_dispatch( $post_id, array $item, array $topic, array $course, $can, $token, $lock ) {
		$removed = false;
		try {
			$removed = true === wp_delete_post( (int) $post_id, true );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
			$removed = false;
		} finally {
			if ( is_string( $lock ) && is_string( $token ) ) {
				TutorPress_Live_Lesson_Sync_Repository::release(
					(int) $post_id,
					$token,
					$lock,
					static function ( $name, $prior ) {
						global $wpdb;
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior ) );
						return 1 === (int) $wpdb->rows_affected;
					}
				);
			}
		}
		if ( $removed ) {
			return null;
		}
		$item['post_status'] = 'publish';
		return TutorPress_Live_Lesson_Response_Projector::persisted_result( $item, $topic, $course, $can, null, null, array( 'persisted' => false, 'status' => 500 ) );
	}

	/** Google duration is the whole-minute span. Zoom end is the start plus the typed duration. */
	private static function create_schedule( array $valid ) {
		$start = TutorPress_Live_Lesson_Datetime::parse_wall_clock( $valid['start'], $valid['timezone'] );
		if ( ! $start instanceof DateTimeImmutable ) {
			return null;
		}
		if ( 'google_meet' === $valid['provider'] ) {
			$end  = TutorPress_Live_Lesson_Datetime::parse_wall_clock( $valid['end'], $valid['timezone'] );
			$span = $end instanceof DateTimeImmutable ? $end->getTimestamp() - $start->getTimestamp() : 0;
			if ( $span < 60 || 0 !== $span % 60 ) {
				return null;
			}
			return array( 'start_local' => $valid['start'], 'end_local' => $valid['end'], 'timezone' => $valid['timezone'], 'duration' => array( 'value' => (int) ( $span / 60 ), 'unit' => 'minutes' ) );
		}
		$minutes = 'hours' === $valid['duration']['unit'] ? $valid['duration']['value'] * 60 : $valid['duration']['value'];
		return array( 'start_local' => $valid['start'], 'end_local' => $start->modify( '+' . $minutes . ' minutes' )->format( 'Y-m-d H:i:s' ), 'timezone' => $valid['timezone'], 'duration' => $valid['duration'] );
	}

	/** Sanitized provider settings. Attendees, passwords, and credentials stay out. */
	private static function create_target( array $valid, array $input ) {
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		if ( 'zoom' === $valid['provider'] ) {
			$recording = isset( $settings['auto_recording'] ) && in_array( $settings['auto_recording'], array( 'cloud', 'local', 'none' ), true ) ? $settings['auto_recording'] : 'none';
			return array( 'auto_recording' => $recording, 'enforce_login' => false, 'host_id' => $valid['host_id'], 'host_video' => false, 'join_before_host' => isset( $settings['join_before_host'] ) && true === $settings['join_before_host'], 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => isset( $settings['waiting_room'] ) && true === $settings['waiting_room'] );
		}
		$enrolled = isset( $settings['add_enrolled_students'] ) && 'Yes' === $settings['add_enrolled_students'] ? 'Yes' : 'No';
		$minutes  = isset( $settings['reminder_minutes'] ) && is_int( $settings['reminder_minutes'] ) ? $settings['reminder_minutes'] : 30;
		return array( 'add_enrolled_students' => $enrolled, 'reminder_minutes' => $minutes, 'send_updates' => isset( $settings['send_updates'] ) && is_string( $settings['send_updates'] ) ? $settings['send_updates'] : 'all', 'status' => isset( $settings['status'] ) && is_string( $settings['status'] ) ? $settings['status'] : 'confirmed', 'transparency' => isset( $settings['transparency'] ) && is_string( $settings['transparency'] ) ? $settings['transparency'] : 'transparent', 'visibility' => isset( $settings['visibility'] ) && is_string( $settings['visibility'] ) ? $settings['visibility'] : 'public' );
	}

	/** CAS one pending create onto an existing unlinked publish copy. The post is not replaced. */
	public static function stage_unlinked( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected ) {
		if ( array_key_exists( 'provider_id', $input ) || array_key_exists( 'id', $input ) ) {
			return null;
		}
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$provider  = isset( $item['post_type'] ) && 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ? 'google_meet' : '' );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$author    = isset( $course['post_author'] ) ? (int) $course['post_author'] : 0;
		if ( null === $course_id || '' === $provider || $post_id < 1 || ! isset( $item['post_status'] ) || 'publish' !== $item['post_status'] || $author < 1 || ! is_string( $token ) || '' === $token || ! is_callable( $read ) || ! is_callable( $update ) || ! is_callable( $affected ) || ( isset( $input['provider'] ) && $input['provider'] !== $provider ) ) {
			return null;
		}
		$rows   = $read( $post_id, '_tutorpress_live_lesson_sync' );
		$prior  = is_array( $rows ) && 1 === count( $rows ) && isset( $rows[0]['meta_id'], $rows[0]['meta_value'], $rows[0]['post_id'] ) && (int) $rows[0]['post_id'] === $post_id && is_string( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : null;
		$stored = is_string( $prior ) ? json_decode( $prior, true ) : null;
		if ( ! is_array( $stored ) || ! isset( $stored['version'], $stored['state'], $stored['provider'] ) || 1 !== $stored['version'] || 'unlinked' !== $stored['state'] || ! array_key_exists( 'provider_id', $stored ) || null !== $stored['provider_id'] || ! array_key_exists( 'operation', $stored ) || null !== $stored['operation'] || $stored['provider'] !== $provider ) {
			return null;
		}
		$input['provider'] = $provider;
		$valid             = self::validate_create( $topic, $course, $can, $input, array(), $adapter, $now );
		if ( ! is_array( $valid ) || ! isset( $item['menu_order'] ) || ! is_int( $item['menu_order'] ) || $item['menu_order'] < 0 ) {
			return null;
		}
		$valid['menu_order'] = $item['menu_order'];
		$schedule            = self::create_schedule( $valid );
		$target              = is_array( $schedule ) ? self::create_target( $valid, $input ) : null;
		if ( null === $target ) {
			return null;
		}
		$expiry = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
		$lock   = TutorPress_Live_Lesson_Sync_Repository::acquire( $post_id, 'create', $token, $expiry, static function ( $name, $bytes ) { return add_option( $name, $bytes, '', false ); } );
		if ( ! is_string( $lock ) ) {
			return null;
		}
		$operation = array( 'operation_id' => bin2hex( random_bytes( 16 ) ), 'kind' => 'create', 'started_at' => $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), 'base_version' => 1, 'provider' => $provider, 'owner_id' => $valid['owner_id'], 'provider_id' => null, 'dispatch_state' => 'not_dispatched', 'course_id' => $valid['course_id'], 'topic_id' => $valid['topic_id'], 'post_author' => $author, 'menu_order' => $valid['menu_order'], 'title' => isset( $input['title'] ) && is_string( $input['title'] ) ? $input['title'] : ( isset( $item['post_title'] ) && is_string( $item['post_title'] ) ? $item['post_title'] : '' ), 'content' => isset( $input['content'] ) && is_string( $input['content'] ) ? $input['content'] : ( isset( $item['post_content'] ) && is_string( $item['post_content'] ) ? $item['post_content'] : '' ), 'start_local' => $schedule['start_local'], 'end_local' => $schedule['end_local'], 'timezone' => $schedule['timezone'], 'duration' => $schedule['duration'], 'provider_target' => $target, 'delete_hook' => null );
		$next      = array( 'schema_version' => 1, 'version' => 2, 'state' => 'pending', 'provider' => $provider, 'owner_id' => $valid['owner_id'], 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation );
		$bytes     = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'create', 'unlinked', 1, $prior, $next, $read, $update, $affected );
		if ( ! is_string( $bytes ) ) {
			TutorPress_Live_Lesson_Sync_Repository::release( $post_id, $token, $lock, static function ( $name, $prior_lock ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior_lock ) );
				return 1 === (int) $wpdb->rows_affected;
			} );
			return null;
		}
		$valid = array_merge( $valid, array( 'post_id' => $post_id, 'lock' => $lock, 'dispatch_state' => 'not_dispatched', 'envelope' => $bytes ) );
		return $valid;
	}

	/** CAS one pending update onto an existing synced publish post. The post is not replaced. */
	public static function stage_update( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected ) {
		if ( array_key_exists( 'provider_id', $input ) || array_key_exists( 'id', $input ) ) {
			return null;
		}
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$provider  = isset( $item['post_type'] ) && 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ? 'google_meet' : '' );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$author    = isset( $course['post_author'] ) ? (int) $course['post_author'] : 0;
		if ( null === $course_id || '' === $provider || $post_id < 1 || 'publish' !== ( isset( $item['post_status'] ) ? $item['post_status'] : '' ) || $author < 1 || ! is_string( $token ) || '' === $token || ! is_callable( $read ) || ! is_callable( $update ) || ! is_callable( $affected ) || ! $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter ) {
			return null;
		}
		$rows    = $read( $post_id, '_tutorpress_live_lesson_sync' );
		$prior   = is_array( $rows ) && 1 === count( $rows ) && isset( $rows[0]['meta_id'], $rows[0]['meta_value'], $rows[0]['post_id'] ) && (int) $rows[0]['post_id'] === $post_id && is_string( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : null;
		$stored  = is_string( $prior ) ? json_decode( $prior, true ) : null;
		$version = is_array( $stored ) && isset( $stored['version'] ) && is_int( $stored['version'] ) ? $stored['version'] : 0; $known = is_array( $stored ) && isset( $stored['provider_id'] ) && is_string( $stored['provider_id'] ) ? $stored['provider_id'] : ''; $previous = is_array( $stored ) && isset( $stored['operation'] ) && is_array( $stored['operation'] ) ? $stored['operation'] : null;
		if ( ! is_array( $stored ) || $version < 1 || ! isset( $stored['state'], $stored['provider'] ) || 'synced' !== $stored['state'] || '' === $known || $stored['provider'] !== $provider || ! is_array( $previous ) ) {
			return null;
		}
		$owner_id = 'google_meet' === $provider ? TutorPress_Live_Lesson_Owner_Resolver::google( $course_id, isset( $input['acting_user_id'] ) ? $input['acting_user_id'] : 0 ) : self::zoom_input_owner( $course_id, $input );
		if ( null === $owner_id || true !== $adapter->preflight( $owner_id ) ) {
			return null;
		}
		$host_id = null;
		if ( 'zoom' === $provider ) {
			$host = TutorPress_Live_Lesson_Owner_Resolver::host_input( $course_id, $owner_id, isset( $input['host_id'] ) ? $input['host_id'] : '' );
			if ( null === $host || true !== $adapter->validate_host( $owner_id, $host['host_id'] ) ) { return null; }
			$host_id = $host['host_id'];
		}
		$start = array_key_exists( 'start', $input ) ? $input['start'] : ( isset( $previous['start_local'] ) ? $previous['start_local'] : '' ); $zone = array_key_exists( 'timezone', $input ) ? $input['timezone'] : ( isset( $previous['timezone'] ) ? $previous['timezone'] : '' );
		$end = array_key_exists( 'end', $input ) ? $input['end'] : ( isset( $previous['end_local'] ) ? $previous['end_local'] : '' ); $typed = array_key_exists( 'duration', $input ) ? $input['duration'] : ( isset( $previous['duration'] ) ? $previous['duration'] : array() );
		$duration = 'zoom' === $provider && is_array( $typed ) ? TutorPress_Live_Lesson_Datetime::zoom_duration( isset( $typed['value'] ) ? $typed['value'] : null, isset( $typed['unit'] ) ? $typed['unit'] : '' ) : null; $changed = $start !== ( isset( $previous['start_local'] ) ? $previous['start_local'] : null ) || $zone !== ( isset( $previous['timezone'] ) ? $previous['timezone'] : null ) || ( 'google_meet' === $provider ? $end !== ( isset( $previous['end_local'] ) ? $previous['end_local'] : null ) : $typed !== ( isset( $previous['duration'] ) ? $previous['duration'] : null ) );
		if ( ( $changed && ! TutorPress_Live_Lesson_Datetime::is_future_wall_clock( $start, $zone, $now ) ) || ( 'google_meet' === $provider && ! TutorPress_Live_Lesson_Datetime::google_end_after_start( $start, $end, $zone ) ) || ( 'zoom' === $provider && null === $duration ) ) { return null; }
		$valid    = array( 'provider' => $provider, 'host_id' => $host_id, 'start' => $start, 'timezone' => $zone, 'end' => 'google_meet' === $provider ? $end : null, 'duration' => $duration );
		$schedule = self::create_schedule( $valid );
		$target   = is_array( $schedule ) ? self::create_target( $valid, $input ) : null;
		if ( null === $target || ! isset( $item['menu_order'] ) || ! is_int( $item['menu_order'] ) || $item['menu_order'] < 0 ) { return null; }
		$expiry = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
		$lock   = TutorPress_Live_Lesson_Sync_Repository::acquire( $post_id, 'update', $token, $expiry, static function ( $name, $bytes ) { return add_option( $name, $bytes, '', false ); } );
		if ( ! is_string( $lock ) ) { return null; }
		$operation = array( 'operation_id' => bin2hex( random_bytes( 16 ) ), 'kind' => 'update', 'started_at' => $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), 'base_version' => $version, 'provider' => $provider, 'owner_id' => $owner_id, 'provider_id' => $known, 'dispatch_state' => 'not_dispatched', 'course_id' => $course_id, 'topic_id' => (int) $topic['ID'], 'post_author' => $author, 'menu_order' => $item['menu_order'], 'title' => isset( $input['title'] ) && is_string( $input['title'] ) ? $input['title'] : ( isset( $item['post_title'] ) && is_string( $item['post_title'] ) ? $item['post_title'] : '' ), 'content' => isset( $input['content'] ) && is_string( $input['content'] ) ? $input['content'] : ( isset( $item['post_content'] ) && is_string( $item['post_content'] ) ? $item['post_content'] : '' ), 'start_local' => $schedule['start_local'], 'end_local' => $schedule['end_local'], 'timezone' => $schedule['timezone'], 'duration' => $schedule['duration'], 'provider_target' => $target, 'delete_hook' => null );
		$next      = array( 'schema_version' => 1, 'version' => $version + 1, 'state' => 'pending', 'provider' => $provider, 'owner_id' => $owner_id, 'provider_id' => $known, 'provenance' => isset( $stored['provenance'] ) && is_string( $stored['provenance'] ) ? $stored['provenance'] : null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation );
		$bytes     = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'update', 'synced', $version, $prior, $next, $read, $update, $affected );
		if ( ! is_string( $bytes ) ) {
			TutorPress_Live_Lesson_Sync_Repository::release( $post_id, $token, $lock, static function ( $name, $prior_lock ) { global $wpdb; $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior_lock ) ); return 1 === (int) $wpdb->rows_affected; } );
			return null;
		}
		return array( 'post_id' => $post_id, 'lock' => $lock, 'dispatch_state' => 'not_dispatched', 'envelope' => $bytes, 'provider' => $provider, 'owner_id' => $owner_id, 'course_id' => $course_id );
	}

	/** Dispatch one staged pending update. Confirmation writes metadata. Error and uncertain outcomes do not fire a save hook. */
	public static function dispatch_update( array $staged, $token, $adapter, $read, $update, $affected, $write ) {
		$prior     = isset( $staged['envelope'] ) && is_string( $staged['envelope'] ) ? json_decode( $staged['envelope'], true ) : null;
		$operation = is_array( $prior ) && isset( $prior['operation'] ) && is_array( $prior['operation'] ) ? $prior['operation'] : null;
		$version   = is_array( $prior ) && isset( $prior['version'] ) && is_int( $prior['version'] ) ? $prior['version'] : 0;
		$stored_id = is_array( $prior ) && isset( $prior['provider_id'] ) && is_string( $prior['provider_id'] ) ? $prior['provider_id'] : '';
		$owner_id  = is_array( $operation ) && isset( $operation['owner_id'] ) ? (int) $operation['owner_id'] : 0;
		$same_id    = '' !== $stored_id && is_array( $operation ) && isset( $operation['provider_id'] ) && $operation['provider_id'] === $stored_id;
		$same_owner = isset( $staged['owner_id'] ) && (int) $staged['owner_id'] === $owner_id && $owner_id > 0;
		if ( ! is_array( $prior ) || $version < 2 || 'pending' !== ( isset( $prior['state'] ) ? $prior['state'] : '' ) || ! $same_id || ! $same_owner || ! is_array( $operation ) || 'update' !== $operation['kind'] || 'not_dispatched' !== $operation['dispatch_state'] || ! isset( $staged['post_id'], $staged['lock'] ) || ! is_string( $token ) || ! TutorPress_Live_Lesson_Sync_Repository::owns( $staged['lock'], $token ) || ! $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter || ! is_callable( $read ) || ! is_callable( $update ) || ! is_callable( $affected ) || ! is_callable( $write ) ) { return null; }
		$marked                                = $prior;
		$marked['version']                     = $version + 1;
		$marked['operation']['dispatch_state'] = 'dispatch_possible';
		$possible                              = TutorPress_Live_Lesson_Sync_Repository::exchange( (int) $staged['post_id'], $staged['lock'], $token, 'update', 'pending', $version, $staged['envelope'], $marked, $read, $update, $affected );
		if ( ! is_string( $possible ) ) { return null; }
		$target = self::dispatch_target( $operation );
		$result = null;
		try {
			$result = TutorPress_Live_Lesson_Provider_Result::from( $adapter->update( $owner_id, $stored_id, $target ) );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
		}
		$outcome  = is_array( $result ) ? $result['outcome'] : 'uncertain';
		$compared = 'confirmed' === $outcome ? $adapter->compare_target( $result, $target ) : null;
		$matched  = is_array( $compared ) && true === ( isset( $compared['match'] ) ? $compared['match'] : null ) && $stored_id === TutorPress_Live_Lesson_Provider_Result::identity( $result );
		$map      = $matched ? $adapter->metadata_map( $result, $target ) : null;
		$synced   = is_array( $map ) && self::map_stored( (int) $staged['post_id'], $map, $write, $read );
		$next     = json_decode( $possible, true );
		$next['version']                     = $version + 2;
		$next['state']                       = $synced ? 'synced' : 'error';
		$next['provider_id']                 = $stored_id;
		$next['provenance']                  = $synced ? 'update' : ( isset( $prior['provenance'] ) ? $prior['provenance'] : null );
		$next['reconciliation_required']     = ! $synced && ( ! is_array( $result ) || 'uncertain' === $outcome || 'confirmed' === $outcome || 'known_id_not_found' === $outcome );
		$next['error']                       = $synced ? null : 'provider_update_failed';
		$next['operation']['provider_id']    = $stored_id;
		$next['operation']['dispatch_state'] = $synced ? 'confirmed' : 'dispatch_possible';
		$bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( (int) $staged['post_id'], $staged['lock'], $token, 'update', 'pending', $version + 1, $possible, $next, $read, $update, $affected );
		if ( ! is_string( $bytes ) ) { return null; }
		$staged['envelope'] = $bytes;
		return $staged;
	}

	/** CAS one pending delete, then delete locally only after normalized convergence. */
	public static function stage_delete( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected ) {
		if ( array_key_exists( 'provider_id', $input ) || array_key_exists( 'id', $input ) ) { return null; }
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$provider  = isset( $item['post_type'] ) && 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ? 'google_meet' : '' );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$author    = isset( $course['post_author'] ) ? (int) $course['post_author'] : 0;
		if ( null === $course_id || '' === $provider || $post_id < 1 || 'publish' !== ( isset( $item['post_status'] ) ? $item['post_status'] : '' ) || $author < 1 || ! is_string( $token ) || '' === $token || ! is_callable( $read ) || ! is_callable( $update ) || ! is_callable( $affected ) || ! $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter ) { return null; }
		$caps = $adapter->capabilities();
		if ( ! is_array( $caps ) || true !== ( isset( $caps['delete_convergence'] ) ? $caps['delete_convergence'] : null ) ) { return null; }
		$rows = $read( $post_id, '_tutorpress_live_lesson_sync' );
		$prior = is_array( $rows ) && 1 === count( $rows ) && isset( $rows[0]['meta_id'], $rows[0]['meta_value'], $rows[0]['post_id'] ) && (int) $rows[0]['post_id'] === $post_id && is_string( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : null;
		$stored = is_string( $prior ) ? json_decode( $prior, true ) : null;
		$version = is_array( $stored ) && isset( $stored['version'] ) && is_int( $stored['version'] ) ? $stored['version'] : 0; $known = is_array( $stored ) && isset( $stored['provider_id'] ) && is_string( $stored['provider_id'] ) ? $stored['provider_id'] : ''; $previous = is_array( $stored ) && isset( $stored['operation'] ) && is_array( $stored['operation'] ) ? $stored['operation'] : null;
		if ( ! is_array( $stored ) || $version < 1 || ! isset( $stored['state'], $stored['provider'] ) || 'synced' !== $stored['state'] || '' === $known || $stored['provider'] !== $provider || ! is_array( $previous ) ) { return null; }
		$owner_id = 'google_meet' === $provider ? TutorPress_Live_Lesson_Owner_Resolver::google( $course_id, isset( $input['acting_user_id'] ) ? $input['acting_user_id'] : 0 ) : self::zoom_input_owner( $course_id, $input );
		if ( null === $owner_id || true !== $adapter->preflight( $owner_id ) ) { return null; }
		$title = isset( $item['post_title'] ) && is_string( $item['post_title'] ) ? $item['post_title'] : '';
		$operation = array_merge( $previous, array( 'operation_id' => bin2hex( random_bytes( 16 ) ), 'kind' => 'delete', 'started_at' => $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), 'base_version' => $version, 'provider' => $provider, 'owner_id' => $owner_id, 'provider_id' => $known, 'dispatch_state' => 'not_dispatched', 'course_id' => $course_id, 'topic_id' => (int) $topic['ID'], 'post_author' => $author, 'menu_order' => isset( $item['menu_order'] ) && is_int( $item['menu_order'] ) ? $item['menu_order'] : -1, 'title' => $title, 'content' => isset( $item['post_content'] ) && is_string( $item['post_content'] ) ? $item['post_content'] : '', 'delete_hook' => array( 'lesson_id' => $post_id, 'title' => $title, 'topic_id' => (int) $topic['ID'], 'type' => $provider ) ) );
		if ( $operation['menu_order'] < 0 ) { return null; }
		$expiry = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
		$lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $post_id, 'delete', $token, $expiry, static function ( $name, $bytes ) { return add_option( $name, $bytes, '', false ); } );
		if ( ! is_string( $lock ) ) { return null; }
		$next = array( 'schema_version' => 1, 'version' => $version + 1, 'state' => 'pending', 'provider' => $provider, 'owner_id' => $owner_id, 'provider_id' => $known, 'provenance' => isset( $stored['provenance'] ) && is_string( $stored['provenance'] ) ? $stored['provenance'] : null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation );
		$bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'delete', 'synced', $version, $prior, $next, $read, $update, $affected );
		if ( ! is_string( $bytes ) ) {
			TutorPress_Live_Lesson_Sync_Repository::release( $post_id, $token, $lock, static function ( $name, $prior_lock ) { global $wpdb; $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior_lock ) ); return 1 === (int) $wpdb->rows_affected; } );
			return null;
		}
		$marked = json_decode( $bytes, true );
		$marked['version'] = $version + 2;
		$marked['operation']['dispatch_state'] = 'dispatch_possible';
		$possible = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'delete', 'pending', $version + 1, $bytes, $marked, $read, $update, $affected );
		if ( ! is_string( $possible ) ) { return null; }
		$returned = null;
		try { $returned = $adapter->delete( $owner_id, $known ); } catch ( Throwable $thrown ) { unset( $thrown ); }
		$staged = array( 'post_id' => $post_id, 'lock' => $lock, 'dispatch_state' => 'dispatch_possible', 'envelope' => $possible, 'provider' => $provider, 'owner_id' => $owner_id, 'course_id' => $course_id );
		$result = TutorPress_Live_Lesson_Provider_Result::from( $returned );
		if ( ! is_array( $result ) ) { return $staged; }
		$identity = TutorPress_Live_Lesson_Provider_Result::identity( $result );
		$same     = $identity === $known && 'delete' === $result['provenance'] && null === $result['item'];
		$closed   = $same && ( 'confirmed' === $result['outcome'] || ( 'google_meet' === $provider && 'known_id_not_found' === $result['outcome'] ) );
		if ( $closed ) {
			$current = $read( $post_id, '_tutorpress_live_lesson_sync' );
			$fresh   = is_array( $current ) && 1 === count( $current ) && isset( $current[0]['meta_value'] ) && $current[0]['meta_value'] === $possible;
			$removed = false;
			if ( $fresh ) {
				try { $removed = true === wp_delete_post( $post_id, true ); } catch ( Throwable $thrown ) { unset( $thrown ); }
			}
			if ( $removed ) {
				$released = TutorPress_Live_Lesson_Sync_Repository::release( $post_id, $token, $lock, static function ( $name, $prior_lock ) { global $wpdb; $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior_lock ) ); return 1 === (int) $wpdb->rows_affected; } );
				if ( true !== $released ) { $staged['dispatch_state'] = 'confirmed'; return $staged; }
				$hook = $marked['operation']['delete_hook'];
				try {
					do_action( 'tutorpress_live_lesson_deleted', (int) $hook['lesson_id'], array( 'id' => (int) $hook['lesson_id'], 'title' => $hook['title'], 'type' => $hook['type'], 'topicId' => (int) $hook['topic_id'] ) );
				} catch ( Throwable $thrown ) {
					unset( $thrown );
					return self::transition_error( array( 'post_id' => $post_id, 'provider' => $provider, 'envelope' => $possible ), $topic, $course, $can );
				}
				$staged['dispatch_state'] = 'confirmed';
				return $staged;
			}
			if ( ! $fresh ) { return null; }
		}
		$failed = json_decode( $possible, true );
		$failed['version']                   = $version + 3;
		$failed['state']                     = 'error';
		$failed['provider_id']               = $known;
		$failed['error']                     = 'provider_delete_failed';
		$failed['reconciliation_required']   = in_array( $result['outcome'], array( 'uncertain', 'confirmed', 'known_id_not_found' ), true );
		$failed['operation']['dispatch_state'] = 'dispatch_possible';
		$error_bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'delete', 'pending', $version + 2, $possible, $failed, $read, $update, $affected );
		if ( ! is_string( $error_bytes ) ) { return null; }
		$staged['envelope'] = $error_bytes;
		return $staged;
	}

	/** Fetch the stored provider id and compare it. The envelope and provider meta stay unchanged. */
	public static function inspect( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $option, $takeover ) {
		if ( array_key_exists( 'provider_id', $input ) || array_key_exists( 'id', $input ) ) {
			return null;
		}
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$guard     = null !== $course_id && $post_id > 0 && is_string( $token ) && '' !== $token ? self::snapshot_guard( $post_id, isset( $input['version'] ) ? $input['version'] : null, isset( $input['operation_id'] ) ? $input['operation_id'] : null, $read ) : null;
		$stored_id = is_array( $guard ) && array_key_exists( 'provider_id', $guard ) ? $guard['provider_id'] : false;
		$operation = is_array( $guard ) && isset( $guard['operation'] ) && is_array( $guard['operation'] ) ? $guard['operation'] : null;
		$same_id   = is_array( $operation ) && array_key_exists( 'provider_id', $operation ) && $operation['provider_id'] === $stored_id;
		if ( null === $course_id || ! $same_id || ! is_string( $stored_id ) || '' === $stored_id || ! $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter || ! is_callable( $option ) || ! is_callable( $takeover ) ) {
			return null;
		}
		$lock    = null;
		$release = false;
		try {
			$lock = self::hold( $post_id, $operation['kind'], $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$provider    = isset( $guard['provider'] ) && is_string( $guard['provider'] ) ? $guard['provider'] : '';
			$owner_id = 'google_meet' === $provider ? TutorPress_Live_Lesson_Owner_Resolver::google( $course_id, isset( $input['acting_user_id'] ) ? $input['acting_user_id'] : 0 ) : ( 'zoom' === $provider ? self::zoom_input_owner( $course_id, $input ) : null );
			$stored_owner    = isset( $guard['owner_id'] ) ? (int) $guard['owner_id'] : 0;
			$operation_owner = isset( $operation['owner_id'] ) ? (int) $operation['owner_id'] : 0;
			if ( null === $owner_id || $owner_id !== $stored_owner || $owner_id !== $operation_owner || true !== $adapter->preflight( $owner_id ) ) {
				return null;
			}
			try {
				$normalized = TutorPress_Live_Lesson_Provider_Result::from( $adapter->fetch( $owner_id, $stored_id ) );
				return is_array( $normalized ) ? $adapter->compare_target( $normalized, self::dispatch_target( $operation ) ) : null;
			} catch ( Throwable $thrown ) {
				unset( $thrown );
				return null;
			}
		} finally {
			if ( false === $release ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** One sync row whose version, operation id, and canonical bytes match. */
	private static function snapshot_guard( $post_id, $version, $operation_id, $read ) {
		$rows      = is_callable( $read ) ? $read( (int) $post_id, '_tutorpress_live_lesson_sync' ) : null;
		$bytes     = is_array( $rows ) && 1 === count( $rows ) && isset( $rows[0]['meta_value'], $rows[0]['post_id'] ) && (int) $rows[0]['post_id'] === (int) $post_id && is_string( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : null;
		$stored    = is_string( $bytes ) ? json_decode( $bytes, true ) : null;
		$canonical = is_array( $stored ) ? TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $stored ) : null;
		$operation = is_array( $stored ) && isset( $stored['operation'] ) && is_array( $stored['operation'] ) ? $stored['operation'] : null;
		if ( ! is_string( $bytes ) || $bytes !== $canonical || ! is_array( $operation ) || ! isset( $stored['version'], $operation['operation_id'], $operation['kind'] ) || $stored['version'] !== $version || $operation['operation_id'] !== $operation_id || ! in_array( $operation['kind'], array( 'create', 'update', 'delete' ), true ) ) {
			return null;
		}
		return $stored;
	}

	/** Acquire a missing lock, or take over one whose expiry is at or before the injected instant. */
	private static function hold( $post_id, $operation, $token, DateTimeImmutable $now, $read, $update ) {
		$name  = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		$prior = is_callable( $read ) ? $read( $name ) : false;
		if ( null === $prior ) {
			$expiry = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
			return TutorPress_Live_Lesson_Sync_Repository::acquire( (int) $post_id, $operation, $token, $expiry, static function ( $option_name, $bytes ) { return add_option( $option_name, $bytes, '', false ); } );
		}
		$clock = $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		return is_string( $prior ) ? TutorPress_Live_Lesson_Sync_Repository::takeover( (int) $post_id, $operation, $token, $prior, $clock, $update ) : null;
	}

	/** Delete this exact token with one prepared binary compare. This method does not set a release flag. */
	private static function release_exact( $post_id, $token, $lock ) {
		return TutorPress_Live_Lesson_Sync_Repository::release( (int) $post_id, $token, $lock, static function ( $name, $prior ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior ) );
			return 1 === (int) $wpdb->rows_affected;
		} );
	}

	/** Retry the stored snapshot. Request fields are not copied onto the adapter target. */
	public static function retry( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected, $write, $option, $takeover ) {
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$ready     = null !== $course_id && $post_id > 0 && is_string( $token ) && '' !== $token && $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter && is_callable( $read ) && is_callable( $update ) && is_callable( $affected ) && is_callable( $write ) && is_callable( $option ) && is_callable( $takeover );
		$guarded   = $ready ? self::guarded_prior( $post_id, isset( $input['version'] ) ? $input['version'] : null, isset( $input['operation_id'] ) ? $input['operation_id'] : null, $read ) : null;
		$guard     = is_array( $guarded ) ? $guarded[0] : null;
		$prior     = is_array( $guarded ) ? $guarded[1] : null;
		$operation = is_array( $guard ) && isset( $guard['operation'] ) && is_array( $guard['operation'] ) ? $guard['operation'] : null;
		if ( ! is_string( $prior ) || ! is_array( $operation ) ) { return null; }
		$kind = $operation['kind']; $state = isset( $guard['state'] ) ? $guard['state'] : ''; $dispatch = isset( $operation['dispatch_state'] ) ? $operation['dispatch_state'] : '';
		$recon = true === ( isset( $guard['reconciliation_required'] ) ? $guard['reconciliation_required'] : null );
		$env_id = array_key_exists( 'provider_id', $guard ) ? $guard['provider_id'] : false; $op_id = array_key_exists( 'provider_id', $operation ) ? $operation['provider_id'] : false;
		if ( 'create' === $kind && null === $env_id && null === $op_id && $recon ) { return null; }
		$matched = ( 'create' === $kind && null === $env_id ) || ( 'update' === $kind && is_string( $env_id ) && '' !== $env_id && $env_id === $op_id );
		$pending = $matched && 'pending' === $state && 'not_dispatched' === $dispatch;
		$errored = $matched && 'error' === $state && ! $recon && in_array( $dispatch, array( 'not_dispatched', 'dispatch_possible' ), true );
		$delete  = 'delete' === $kind && is_string( $env_id ) && '' !== $env_id;
		if ( $delete ) { $caps = $adapter->capabilities(); if ( ! is_array( $caps ) || true !== ( isset( $caps['delete_convergence'] ) ? $caps['delete_convergence'] : null ) ) { return null; } }
		elseif ( ! $pending && ! $errored ) { return null; }
		$release_invoked = false; $lock = null;
		try {
			if ( ! self::same_prior( $post_id, $guard['version'], $operation['operation_id'], $prior, $read ) ) { return null; }
			$lock = self::hold( $post_id, $kind, $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) { return null; }
			if ( $delete ) { return self::retry_delete( $post_id, $token, $lock, $prior, $guard, $operation, $adapter, $topic, $course, $can, $read, $release_invoked ); }
			$bytes = $prior;
			if ( $errored ) {
				if ( ! self::same_prior( $post_id, $guard['version'], $operation['operation_id'], $prior, $read ) ) { return null; }
				$next = $guard; $next['version'] = $guard['version'] + 1; $next['state'] = 'pending'; $next['error'] = null; $next['reconciliation_required'] = false; $next['operation']['dispatch_state'] = 'not_dispatched';
				$bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, $kind, 'error', $guard['version'], $prior, $next, $read, $update, $affected );
				$rows = is_string( $bytes ) ? $read( $post_id, '_tutorpress_live_lesson_sync' ) : null;
				$seen = is_array( $rows ) && 1 === count( $rows ) && isset( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : null;
				if ( ! is_string( $bytes ) || $seen !== $bytes ) { return null; }
			}
			$staged = array( 'post_id' => $post_id, 'lock' => $lock, 'owner_id' => $guard['owner_id'], 'provider' => $guard['provider'], 'envelope' => $bytes );
			$helper = 'create' === $kind ? self::dispatch_create( $staged, $token, $adapter, $read, $update, $affected, $write, $topic, $course, $can ) : self::dispatch_update( $staged, $token, $adapter, $read, $update, $affected, $write );
			$code = $helper instanceof WP_Error ? $helper->get_error_code() : '';
			$done = is_array( $helper ) && isset( $helper['envelope'] ) && is_string( $helper['envelope'] ) ? json_decode( $helper['envelope'], true ) : null;
			$synced = is_array( $done ) && 'synced' === ( isset( $done['state'] ) ? $done['state'] : '' );
			if ( 'live_lesson_persisted_transition' === $code || ( $synced && 'create' === $kind ) ) { $release_invoked = true; }
			elseif ( $synced && 'update' === $kind ) { self::release_exact( $post_id, $token, $lock ); $release_invoked = true; }
			return $helper;
		} finally {
			if ( false === $release_invoked ) { self::release_exact( $post_id, $token, $lock ); }
		}
	}

	/** Snapshot guard plus one fresh meta read. A mismatch is not the guarded row. */
	private static function guarded_prior( $post_id, $version, $operation_id, $read ) {
		$guard = self::snapshot_guard( $post_id, $version, $operation_id, $read );
		$rows  = is_array( $guard ) ? $read( (int) $post_id, '_tutorpress_live_lesson_sync' ) : null;
		$bytes = is_array( $rows ) && 1 === count( $rows ) && isset( $rows[0]['meta_value'], $rows[0]['post_id'] ) && (int) $rows[0]['post_id'] === (int) $post_id && is_string( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : null;
		return is_string( $bytes ) && $bytes === TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $guard ) ? array( $guard, $bytes ) : null;
	}

	/** True when the guarded version and operation id still match these exact bytes. */
	private static function same_prior( $post_id, $version, $operation_id, $prior, $read ) {
		$fresh = self::guarded_prior( $post_id, $version, $operation_id, $read );
		return is_array( $fresh ) && $fresh[1] === $prior;
	}

	/** Confirmed delete, or Google known-id absence returned by delete(). */
	private static function converged_delete( $adapter, $owner_id, $stored_id, $provider ) {
		$returned = null;
		try { $returned = $adapter->delete( $owner_id, $stored_id ); } catch ( Throwable $thrown ) { unset( $thrown ); return false; }
		$result = TutorPress_Live_Lesson_Provider_Result::from( $returned );
		$same   = is_array( $result ) && TutorPress_Live_Lesson_Provider_Result::identity( $result ) === $stored_id && 'delete' === $result['provenance'] && null === $result['item'];
		return $same && ( 'confirmed' === $result['outcome'] || ( 'google_meet' === $provider && 'known_id_not_found' === $result['outcome'] ) );
	}

	/** Release this token before the delete hook. A false release does not fire the hook. */
	private static function retry_delete( $post_id, $token, $lock, $prior, array $guard, array $operation, $adapter, array $topic, array $course, $can, $read, &$release_invoked ) {
		$stored_id = $guard['provider_id']; $owner_id = isset( $operation['owner_id'] ) ? (int) $operation['owner_id'] : 0;
		$provider = isset( $guard['provider'] ) && is_string( $guard['provider'] ) ? $guard['provider'] : '';
		$dispatch = isset( $operation['dispatch_state'] ) ? $operation['dispatch_state'] : ''; $recon = true === ( isset( $guard['reconciliation_required'] ) ? $guard['reconciliation_required'] : null );
		$state = isset( $guard['state'] ) ? $guard['state'] : ''; $version = $guard['version']; $op_id = $operation['operation_id'];
		$no_side = 'not_dispatched' === $dispatch || ( 'error' === $state && ! $recon ); $found_only = 'dispatch_possible' === $dispatch && $recon; $closed = false;
		if ( $owner_id < 1 || '' === $provider || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) { return null; }
		if ( ! $no_side ) {
			try { $normalized = TutorPress_Live_Lesson_Provider_Result::from( $adapter->fetch( $owner_id, $stored_id ) ); } catch ( Throwable $thrown ) { unset( $thrown ); return null; }
			$identity = is_array( $normalized ) ? TutorPress_Live_Lesson_Provider_Result::identity( $normalized ) : null;
			$outcome  = is_array( $normalized ) ? $normalized['outcome'] : '';
			if ( 'known_id_not_found' === $outcome && $identity === $stored_id && 'google_meet' === $provider && ! $found_only && self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) {
				$closed = true;
			} elseif ( 'confirmed' === $outcome && $identity === $stored_id ) {
				$compared = $adapter->compare_target( $normalized, self::dispatch_target( $operation ) );
				if ( ! is_array( $compared ) || true !== ( isset( $compared['match'] ) ? $compared['match'] : null ) || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) { return null; }
				$closed = self::converged_delete( $adapter, $owner_id, $stored_id, $provider );
			} else { return null; }
		} else {
			$closed = self::converged_delete( $adapter, $owner_id, $stored_id, $provider );
		}
		if ( ! $closed || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) { return null; }
		$removed = false;
		try { $removed = true === wp_delete_post( (int) $post_id, true ); } catch ( Throwable $thrown ) { unset( $thrown ); }
		$released        = self::release_exact( $post_id, $token, $lock );
		$release_invoked = true;
		if ( ! $removed || true !== $released ) { return null; }
		$hook = isset( $operation['delete_hook'] ) && is_array( $operation['delete_hook'] ) ? $operation['delete_hook'] : array();
		try {
			do_action( 'tutorpress_live_lesson_deleted', (int) $hook['lesson_id'], array( 'id' => (int) $hook['lesson_id'], 'title' => $hook['title'], 'type' => $hook['type'], 'topicId' => (int) $hook['topic_id'] ) );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
			return self::transition_error( array( 'post_id' => $post_id, 'provider' => $provider, 'envelope' => $prior ), $topic, $course, $can );
		}
		return array( 'post_id' => $post_id, 'provider' => $provider, 'envelope' => $prior, 'dispatch_state' => 'confirmed' );
	}

	/** Leave an ID-less uncertain create unlinked. The warning is an orphan acknowledgment, not convergence. */
	public static function abandon_unknown( array $item, array $topic, array $course, $can, array $input, DateTimeImmutable $now, $token, $read, $update, $affected, $option, $takeover ) {
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$ready     = null !== $course_id && $post_id > 0 && is_string( $token ) && '' !== $token && is_callable( $read ) && is_callable( $update ) && is_callable( $affected ) && is_callable( $option ) && is_callable( $takeover );
		$guard     = $ready ? self::snapshot_guard( $post_id, isset( $input['version'] ) ? $input['version'] : null, isset( $input['operation_id'] ) ? $input['operation_id'] : null, $read ) : null;
		$operation = is_array( $guard ) && isset( $guard['operation'] ) && is_array( $guard['operation'] ) ? $guard['operation'] : null;
		$kind      = is_array( $operation ) ? $operation['kind'] : '';
		$state     = is_array( $guard ) && isset( $guard['state'] ) ? $guard['state'] : '';
		$env_id    = is_array( $guard ) && array_key_exists( 'provider_id', $guard ) ? $guard['provider_id'] : false;
		$op_id     = is_array( $operation ) && array_key_exists( 'provider_id', $operation ) ? $operation['provider_id'] : false;
		$uncertain = is_array( $guard ) && true === ( isset( $guard['reconciliation_required'] ) ? $guard['reconciliation_required'] : null );
		if ( 'error' !== $state || 'create' !== $kind || null !== $env_id || null !== $op_id || ! $uncertain ) {
			return null;
		}
		$release_invoked = false;
		$lock            = null;
		try {
			$lock = self::hold( $post_id, 'create', $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$prior = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $guard );
			if ( ! is_string( $prior ) || ! self::same_prior( $post_id, $guard['version'], $operation['operation_id'], $prior, $read ) ) {
				return null;
			}
			$next                            = $guard;
			$next['version']                 = $guard['version'] + 1;
			$next['state']                   = 'unlinked';
			$next['provider_id']             = null;
			$next['operation']               = null;
			$next['reconciliation_required'] = false;
			$next['warning']                 = 'orphan_may_remain';
			$bytes                           = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'create', 'error', $guard['version'], $prior, $next, $read, $update, $affected );
			return is_string( $bytes ) ? array( 'post_id' => $post_id, 'provider' => $guard['provider'], 'envelope' => $bytes ) : null;
		} finally {
			if ( false === $release_invoked && is_string( $lock ) ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** Recompute a legacy owner from this request. No version-1 envelope is initialized. */
	public static function resolve_owner( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $option, $takeover ) {
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$provider  = isset( $item['post_type'] ) && 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ? 'google' : '' );
		$ready     = null !== $course_id && $post_id > 0 && '' !== $provider && is_string( $token ) && '' !== $token && $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter && is_callable( $read ) && is_callable( $option ) && is_callable( $takeover );
		$rows      = $ready ? $read( $post_id, '_tutorpress_live_lesson_sync' ) : null;
		$version   = isset( $input['version'] ) ? $input['version'] : null;
		if ( ! $ready || ! is_array( $rows ) || array() !== $rows || 0 !== $version ) {
			return null;
		}
		$evidence = array();
		foreach ( isset( $input['evidence'] ) && is_array( $input['evidence'] ) ? $input['evidence'] : array() as $row ) {
			$source = is_array( $row ) && isset( $row['source'] ) && is_string( $row['source'] ) ? $row['source'] : '';
			$label  = is_array( $row ) && isset( $row['label'] ) && is_string( $row['label'] ) ? $row['label'] : '';
			if ( '' !== $label && in_array( $source, array( 'acting_user', 'course_author', 'acting_instructor', 'post_author' ), true ) ) {
				$evidence[] = array( 'source' => $source, 'id' => isset( $row['id'] ) ? $row['id'] : 0, 'label' => $label );
			}
		}
		$resolved = TutorPress_Live_Lesson_Owner_Resolver::legacy_candidates( $course_id, $provider, $evidence, null );
		$owner    = is_array( $resolved ) ? $resolved['owner_id'] : false;
		$chosen   = array_key_exists( 'selected_owner_id', $input ) ? $input['selected_owner_id'] : null;
		$selected = is_int( $chosen ) || ( is_string( $chosen ) && ctype_digit( $chosen ) ) ? (int) $chosen : null;
		$listed   = is_array( $resolved ) ? array_column( $resolved['candidates'], 'id', 'id' ) : array();
		$accepted = is_int( $owner ) ? $owner : ( null === $owner && is_int( $selected ) && isset( $listed[ $selected ] ) ? $selected : null );
		if ( ! is_array( $resolved ) || null === $accepted || ( is_int( $owner ) && null !== $chosen && $selected !== $owner ) ) {
			return null;
		}
		$release_invoked = false;
		$lock            = null;
		try {
			$lock = self::hold( $post_id, 'create', $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$passes = 'google' === $provider ? true === $adapter->preflight( $accepted ) : ( array_key_exists( 'credentials', $input ) ? $accepted === self::zoom_input_owner( $course_id, $input ) : ( $accepted === self::zoom_input_owner( $course_id, $input ) && true === $adapter->preflight( $accepted ) ) );
			if ( ! $passes ) {
				return null;
			}
			return array( 'owner_id' => $accepted, 'candidates' => $resolved['candidates'] );
		} finally {
			if ( false === $release_invoked && is_string( $lock ) ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** Initialize one synced version-1 envelope from a hydrated legacy identity. No provider mutation. */
	public static function initialize_legacy( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $option, $takeover ) {
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$provider  = isset( $item['post_type'] ) && 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ? 'google' : '' );
		$ready     = null !== $course_id && $post_id > 0 && '' !== $provider && is_string( $token ) && '' !== $token && $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter && is_callable( $read ) && is_callable( $option ) && is_callable( $takeover );
		$rows      = $ready ? $read( $post_id, '_tutorpress_live_lesson_sync' ) : null;
		$version   = isset( $input['version'] ) ? $input['version'] : null;
		if ( ! $ready || ! is_array( $rows ) || array() !== $rows || 0 !== $version ) {
			return null;
		}
		$evidence = array();
		foreach ( isset( $input['evidence'] ) && is_array( $input['evidence'] ) ? $input['evidence'] : array() as $row ) {
			$source = is_array( $row ) && isset( $row['source'] ) && is_string( $row['source'] ) ? $row['source'] : '';
			$label  = is_array( $row ) && isset( $row['label'] ) && is_string( $row['label'] ) ? $row['label'] : '';
			if ( '' !== $label && in_array( $source, array( 'acting_user', 'course_author', 'acting_instructor', 'post_author' ), true ) ) {
				$evidence[] = array( 'source' => $source, 'id' => isset( $row['id'] ) ? $row['id'] : 0, 'label' => $label );
			}
		}
		$resolved = TutorPress_Live_Lesson_Owner_Resolver::legacy_candidates( $course_id, $provider, $evidence, null );
		$owner    = is_array( $resolved ) ? $resolved['owner_id'] : false;
		$chosen   = array_key_exists( 'selected_owner_id', $input ) ? $input['selected_owner_id'] : null;
		$selected = is_int( $chosen ) || ( is_string( $chosen ) && ctype_digit( $chosen ) ) ? (int) $chosen : null;
		$listed   = is_array( $resolved ) ? array_column( $resolved['candidates'], 'id', 'id' ) : array();
		$accepted = is_int( $owner ) ? $owner : ( null === $owner && is_int( $selected ) && isset( $listed[ $selected ] ) ? $selected : null );
		if ( ! is_array( $resolved ) || null === $accepted || ( is_int( $owner ) && null !== $chosen && $selected !== $owner ) ) {
			return null;
		}
		$local    = isset( $input['local'] ) && is_array( $input['local'] ) ? $input['local'] : array();
		$hydrated = $adapter->hydrate_legacy( $local );
		$identity = is_array( $hydrated ) && isset( $hydrated['provider_id'] ) && is_string( $hydrated['provider_id'] ) && '' !== $hydrated['provider_id'] ? $hydrated['provider_id'] : null;
		if ( null === $identity ) {
			return null;
		}
		$release_invoked = false;
		$lock            = null;
		try {
			$lock = self::hold( $post_id, 'create', $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$passes = 'google' === $provider ? true === $adapter->preflight( $accepted ) : ( array_key_exists( 'credentials', $input ) ? $accepted === self::zoom_input_owner( $course_id, $input ) : ( $accepted === self::zoom_input_owner( $course_id, $input ) && true === $adapter->preflight( $accepted ) ) );
			if ( ! $passes ) {
				return null;
			}
			$next  = array( 'schema_version' => 1, 'version' => 1, 'state' => 'synced', 'provider' => 'zoom' === $provider ? 'zoom' : 'google_meet', 'owner_id' => $accepted, 'provider_id' => $identity, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null );
			$bytes = TutorPress_Live_Lesson_Sync_Repository::initialize( $post_id, $lock, $token, 0, $next, $read, static function ( $id, $key, $value ) { return add_post_meta( $id, $key, wp_slash( $value ) ); }, static function () { return true; } );
			return is_string( $bytes ) ? array( 'post_id' => $post_id, 'provider' => $next['provider'], 'envelope' => $bytes ) : null;
		} finally {
			if ( false === $release_invoked && is_string( $lock ) ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** Confirm an ID-less create. Writers then store the metadata map before sync, release, and one save hook. */
	public static function attach_provider_identity( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $option, $takeover, $write = null, $update = null, $affected = null ) {
		$supplied  = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'provider_id' => array_key_exists( 'provider_id', $input ) ? $input['provider_id'] : null, 'operation_id' => array_key_exists( 'operation_id', $input ) ? $input['operation_id'] : null, 'version' => array_key_exists( 'version', $input ) ? $input['version'] : null ) );
		$accepted  = is_array( $supplied ) && is_string( $supplied['provider_id'] ) && '' !== $supplied['provider_id'] && is_string( $supplied['operation_id'] ) && '' !== $supplied['operation_id'] && is_int( $supplied['version'] ) && $supplied['version'] >= 1;
		$course_id = $accepted ? TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can ) : null;
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$ready     = null !== $course_id && $post_id > 0 && is_string( $token ) && '' !== $token && $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter && is_callable( $read ) && is_callable( $option ) && is_callable( $takeover );
		$guard     = $ready ? self::snapshot_guard( $post_id, $supplied['version'], $supplied['operation_id'], $read ) : null;
		$operation = is_array( $guard ) && isset( $guard['operation'] ) && is_array( $guard['operation'] ) ? $guard['operation'] : null;
		$env_id    = is_array( $guard ) && array_key_exists( 'provider_id', $guard ) ? $guard['provider_id'] : false;
		$op_id     = is_array( $operation ) && array_key_exists( 'provider_id', $operation ) ? $operation['provider_id'] : false;
		$state     = is_array( $guard ) && isset( $guard['state'] ) ? $guard['state'] : '';
		if ( ! is_array( $operation ) || 'error' !== $state || 'create' !== $operation['kind'] || null !== $env_id || null !== $op_id ) {
			return null;
		}
		$release_invoked = false;
		$lock            = null;
		try {
			$lock = self::hold( $post_id, 'create', $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$provider    = isset( $guard['provider'] ) && is_string( $guard['provider'] ) ? $guard['provider'] : '';
			$owner_id = 'google_meet' === $provider ? TutorPress_Live_Lesson_Owner_Resolver::google( $course_id, isset( $input['acting_user_id'] ) ? $input['acting_user_id'] : 0 ) : ( 'zoom' === $provider ? self::zoom_input_owner( $course_id, $input ) : null );
			$stored_owner = isset( $guard['owner_id'] ) ? (int) $guard['owner_id'] : 0;
			if ( null === $owner_id || $owner_id !== $stored_owner || true !== $adapter->preflight( $owner_id ) ) {
				return null;
			}
			try {
				$normalized = TutorPress_Live_Lesson_Provider_Result::from( $adapter->fetch( $owner_id, $supplied['provider_id'] ) );
				$compared   = is_array( $normalized ) ? $adapter->compare_target( $normalized, self::dispatch_target( $operation ) ) : null;
				$matched    = is_array( $compared ) && isset( $compared['match'] ) && true === $compared['match'];
				$confirmed  = is_array( $normalized ) && 'confirmed' === $normalized['outcome'] && $supplied['provider_id'] === TutorPress_Live_Lesson_Provider_Result::identity( $normalized ) && $matched;
				if ( ! $confirmed || ! is_callable( $write ) || ! is_callable( $update ) || ! is_callable( $affected ) ) {
					return $confirmed ? $normalized : null;
				}
				return self::settle_attach( $post_id, $token, $lock, $guard, $operation, $adapter, $normalized, $topic, $course, $can, $read, $write, $update, $affected, $release_invoked );
			} catch ( Throwable $thrown ) {
				unset( $thrown );
				return null;
			}
		} finally {
			if ( false === $release_invoked && is_string( $lock ) ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** Attach a supplied id only when local metadata has none. A full match stores the compatible map, then one synced version-1 row. */
	public static function attach_legacy_identity( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $write, $option, $takeover ) {
		$supplied  = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'provider_id' => array_key_exists( 'provider_id', $input ) ? $input['provider_id'] : null, 'operation_id' => array_key_exists( 'operation_id', $input ) ? $input['operation_id'] : null, 'version' => array_key_exists( 'version', $input ) ? $input['version'] : null ) );
		$accepted  = is_array( $supplied ) && is_string( $supplied['provider_id'] ) && '' !== $supplied['provider_id'] && null === $supplied['operation_id'] && is_int( $supplied['version'] ) && 0 === $supplied['version'];
		$course_id = $accepted ? TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can ) : null;
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$provider  = isset( $item['post_type'] ) && 'tutor_zoom_meeting' === $item['post_type'] ? 'zoom' : ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ? 'google' : '' );
		$ready     = null !== $course_id && $post_id > 0 && '' !== $provider && is_string( $token ) && '' !== $token && $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter && is_callable( $read ) && is_callable( $write ) && is_callable( $option ) && is_callable( $takeover );
		$rows      = $ready ? $read( $post_id, '_tutorpress_live_lesson_sync' ) : null;
		if ( ! $ready || ! is_array( $rows ) || array() !== $rows ) {
			return null;
		}
		$evidence = array();
		foreach ( isset( $input['evidence'] ) && is_array( $input['evidence'] ) ? $input['evidence'] : array() as $row ) {
			$source = is_array( $row ) && isset( $row['source'] ) && is_string( $row['source'] ) ? $row['source'] : '';
			$label  = is_array( $row ) && isset( $row['label'] ) && is_string( $row['label'] ) ? $row['label'] : '';
			if ( '' !== $label && in_array( $source, array( 'acting_user', 'course_author', 'acting_instructor', 'post_author' ), true ) ) {
				$evidence[] = array( 'source' => $source, 'id' => isset( $row['id'] ) ? $row['id'] : 0, 'label' => $label );
			}
		}
		$resolved = TutorPress_Live_Lesson_Owner_Resolver::legacy_candidates( $course_id, $provider, $evidence, null );
		$owner    = is_array( $resolved ) ? $resolved['owner_id'] : false;
		$chosen   = array_key_exists( 'selected_owner_id', $input ) ? $input['selected_owner_id'] : null;
		$selected = is_int( $chosen ) || ( is_string( $chosen ) && ctype_digit( $chosen ) ) ? (int) $chosen : null;
		$listed   = is_array( $resolved ) ? array_column( $resolved['candidates'], 'id', 'id' ) : array();
		$owner_id = is_int( $owner ) ? $owner : ( null === $owner && is_int( $selected ) && isset( $listed[ $selected ] ) ? $selected : null );
		if ( ! is_array( $resolved ) || null === $owner_id || ( is_int( $owner ) && null !== $chosen && $selected !== $owner ) ) {
			return null;
		}
		$local    = isset( $input['local'] ) && is_array( $input['local'] ) ? $input['local'] : array();
		$hydrated = $adapter->hydrate_legacy( $local );
		$existing = is_array( $hydrated ) && array_key_exists( 'provider_id', $hydrated ) ? $hydrated['provider_id'] : false;
		if ( null !== $existing ) {
			return null;
		}
		$target = $adapter->hydrate_item( $local );
		if ( ! is_array( $target ) ) {
			return null;
		}
		$target['provider_id'] = $supplied['provider_id'];
		$release_invoked       = false;
		$lock                  = null;
		try {
			$lock = self::hold( $post_id, 'create', $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$passes = 'google' === $provider ? true === $adapter->preflight( $owner_id ) : ( array_key_exists( 'credentials', $input ) ? $owner_id === self::zoom_input_owner( $course_id, $input ) : ( $owner_id === self::zoom_input_owner( $course_id, $input ) && true === $adapter->preflight( $owner_id ) ) );
			if ( ! $passes ) {
				return null;
			}
			try {
				$normalized = TutorPress_Live_Lesson_Provider_Result::from( $adapter->fetch( $owner_id, $supplied['provider_id'] ) );
			} catch ( Throwable $thrown ) {
				unset( $thrown );
				return null;
			}
			$identity = is_array( $normalized ) ? TutorPress_Live_Lesson_Provider_Result::identity( $normalized ) : null;
			if ( ! is_array( $normalized ) || 'confirmed' !== $normalized['outcome'] || 'fetch' !== $normalized['provenance'] || $supplied['provider_id'] !== $identity ) {
				return null;
			}
			$compared = $adapter->compare_target( $normalized, $target );
			$matched  = is_array( $compared ) && isset( $compared['match'] ) && true === $compared['match'];
			if ( ! $matched ) {
				return null;
			}
			$map = $adapter->metadata_map( $normalized, $target );
			if ( ! is_array( $map ) || ! self::map_stored( $post_id, $map, $write, $read ) ) {
				return null;
			}
			$next  = array( 'schema_version' => 1, 'version' => 1, 'state' => 'synced', 'provider' => 'zoom' === $provider ? 'zoom' : 'google_meet', 'owner_id' => $owner_id, 'provider_id' => $identity, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null );
			$bytes = TutorPress_Live_Lesson_Sync_Repository::initialize( $post_id, $lock, $token, 0, $next, $read, static function ( $id, $key, $value ) { return add_post_meta( $id, $key, wp_slash( $value ) ); }, static function () { return true; } );
			return is_string( $bytes ) ? array( 'post_id' => $post_id, 'provider' => $next['provider'], 'envelope' => $bytes ) : null;
		} finally {
			if ( false === $release_invoked && is_string( $lock ) ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** Store the verified map, then CAS the guarded create error. Release once before the save hook. */
	private static function settle_attach( $post_id, $token, $lock, array $guard, array $operation, $adapter, array $normalized, array $topic, array $course, $can, $read, $write, $update, $affected, &$release_invoked ) {
		$version  = $guard['version'];
		$op_id    = $operation['operation_id'];
		$prior    = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $guard );
		$identity = TutorPress_Live_Lesson_Provider_Result::identity( $normalized );
		if ( 'fetch' !== $normalized['provenance'] || ! is_string( $identity ) || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) {
			return null;
		}
		$target = self::dispatch_target( $operation );
		$map    = $adapter->metadata_map( $normalized, $target );
		if ( ! is_array( $map ) || ! self::map_stored( $post_id, $map, $write, $read ) || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) {
			return null;
		}
		$next = array_merge( $guard, array( 'version' => $version + 1, 'state' => 'synced', 'provider_id' => $identity, 'provenance' => 'fetch', 'error' => null, 'reconciliation_required' => false ) );
		$next['operation']['provider_id'] = $identity;
		$next['operation']['dispatch_state'] = 'confirmed';
		$bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'create', 'error', $version, $prior, $next, $read, $update, $affected );
		if ( ! is_string( $bytes ) ) {
			return null;
		}
		$released        = self::release_exact( $post_id, $token, $lock );
		$release_invoked = true;
		if ( true !== $released ) {
			return null;
		}
		try {
			do_action( 'zoom' === $guard['provider'] ? 'tutor_zoom_after_save_meeting' : 'tutor_google_meet_after_save_meeting', (int) $post_id );
		} catch ( Throwable $thrown ) {
			unset( $thrown );
			return self::transition_error( array( 'post_id' => $post_id, 'provider' => $guard['provider'], 'envelope' => $bytes ), $topic, $course, $can );
		}
		return array( 'post_id' => $post_id, 'provider' => $guard['provider'], 'envelope' => $bytes );
	}

	/** Confirm a stored update by its stored id and target. Sync and release once. Do not fire a save hook. */
	public static function finalize_update( array $item, array $topic, array $course, $can, array $input, $adapter, DateTimeImmutable $now, $token, $read, $update, $affected, $write, $option, $takeover ) {
		$course_id = TutorPress_Live_Lesson_Authorization::authorize( $item, $topic, $course, $can );
		$post_id   = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
		$ready     = null !== $course_id && $post_id > 0 && is_string( $token ) && '' !== $token && $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter && is_callable( $read ) && is_callable( $update ) && is_callable( $affected ) && is_callable( $write ) && is_callable( $option ) && is_callable( $takeover );
		$version   = isset( $input['version'] ) ? $input['version'] : null;
		$op_id     = isset( $input['operation_id'] ) ? $input['operation_id'] : null;
		$guard     = $ready ? self::snapshot_guard( $post_id, $version, $op_id, $read ) : null;
		$operation = is_array( $guard ) && isset( $guard['operation'] ) && is_array( $guard['operation'] ) ? $guard['operation'] : null;
		$stored_id = is_array( $guard ) && isset( $guard['provider_id'] ) && is_string( $guard['provider_id'] ) ? $guard['provider_id'] : '';
		$state     = is_array( $guard ) && isset( $guard['state'] ) ? $guard['state'] : '';
		$kind      = is_array( $operation ) ? $operation['kind'] : '';
		$op_pid    = is_array( $operation ) && isset( $operation['provider_id'] ) ? $operation['provider_id'] : null;
		if ( '' === $stored_id || $stored_id !== $op_pid || 'update' !== $kind || ! in_array( $state, array( 'pending', 'error' ), true ) ) {
			return null;
		}
		$release_invoked = false;
		$lock            = null;
		try {
			$lock = self::hold( $post_id, 'update', $token, $now, $option, $takeover );
			if ( ! is_string( $lock ) ) {
				return null;
			}
			$provider     = isset( $guard['provider'] ) && is_string( $guard['provider'] ) ? $guard['provider'] : '';
			$owner_id = 'google_meet' === $provider ? TutorPress_Live_Lesson_Owner_Resolver::google( $course_id, isset( $input['acting_user_id'] ) ? $input['acting_user_id'] : 0 ) : ( 'zoom' === $provider ? self::zoom_input_owner( $course_id, $input ) : null );
			$stored_owner = isset( $operation['owner_id'] ) ? (int) $operation['owner_id'] : 0;
			$prior        = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $guard );
			if ( null === $owner_id || $owner_id !== $stored_owner || true !== $adapter->preflight( $owner_id ) || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) {
				return null;
			}
			try {
				$normalized = TutorPress_Live_Lesson_Provider_Result::from( $adapter->fetch( $owner_id, $stored_id ) );
			} catch ( Throwable $thrown ) {
				unset( $thrown );
				return null;
			}
			$target   = self::dispatch_target( $operation );
			$identity = is_array( $normalized ) ? TutorPress_Live_Lesson_Provider_Result::identity( $normalized ) : null;
			$compared = is_array( $normalized ) && 'confirmed' === $normalized['outcome'] && $stored_id === $identity ? $adapter->compare_target( $normalized, $target ) : null;
			$matched  = is_array( $compared ) && isset( $compared['match'] ) && true === $compared['match'];
			if ( ! $matched || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) {
				return null;
			}
			$map = $adapter->metadata_map( $normalized, $target );
			if ( ! is_array( $map ) || ! self::map_stored( $post_id, $map, $write, $read ) || ! self::same_prior( $post_id, $version, $op_id, $prior, $read ) ) {
				return null;
			}
			$next = array_merge( $guard, array( 'version' => $version + 1, 'state' => 'synced', 'provenance' => 'update', 'error' => null, 'reconciliation_required' => false ) );
			$next['operation']['dispatch_state'] = 'confirmed';
			$bytes = TutorPress_Live_Lesson_Sync_Repository::exchange( $post_id, $lock, $token, 'update', $state, $version, $prior, $next, $read, $update, $affected );
			if ( ! is_string( $bytes ) ) {
				return null;
			}
			$released        = self::release_exact( $post_id, $token, $lock );
			$release_invoked = true;
			return true === $released ? array( 'post_id' => $post_id, 'provider' => $provider, 'envelope' => $bytes ) : null;
		} finally {
			if ( false === $release_invoked && is_string( $lock ) ) {
				self::release_exact( $post_id, $token, $lock );
			}
		}
	}

	/** Zoom owner from eligibility when the credentials key is present, otherwise course author then acting instructor. */
	private static function zoom_input_owner( $course_id, array $input ) {
		$author = isset( $input['course_author_id'] ) ? $input['course_author_id'] : 0;
		$acting = isset( $input['acting_instructor_id'] ) ? $input['acting_instructor_id'] : 0;
		if ( ! array_key_exists( 'credentials', $input ) ) {
			return TutorPress_Live_Lesson_Owner_Resolver::zoom( $course_id, $author, $acting );
		}
		$credentials = isset( $input['credentials'] ) && is_array( $input['credentials'] ) ? $input['credentials'] : array();
		return TutorPress_Live_Lesson_Owner_Resolver::zoom_eligible( $course_id, $author, $acting, $credentials );
	}
}
