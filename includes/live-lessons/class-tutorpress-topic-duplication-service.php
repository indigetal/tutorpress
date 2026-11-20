<?php
/**
 * Source snapshot for dormant topic duplication.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Authorized child order only. No write and no provider call. */
final class TutorPress_Topic_Duplication_Service {
	/** Null unless the source topic and destination course are authorized. */
	public static function snapshot( array $source, array $course, array $destination, array $children, $can ) {
		if ( null === TutorPress_Live_Lesson_Authorization::authorize_duplicate( $source, $course, $destination, $can ) ) {
			return null;
		}
		$owned = array();
		foreach ( $children as $child ) {
			if ( is_array( $child ) && isset( $child['post_parent'] ) && (int) $child['post_parent'] === (int) $source['ID'] ) {
				$owned[] = $child;
			}
		}
		$plan = array();
		$next = 1;
		foreach ( TutorPress_Live_Lesson_Ordering::sequence( $owned ) as $child ) {
			$child['menu_order'] = $next;
			$plan[]              = $child;
			++$next;
		}
		return $plan;
	}

	/** Insert authorized non-live children and their meta. Live rows stay uncopied. */
	public static function copy_non_live( array $source, array $course, array $destination, array $target, array $children, $can ) {
		$plan = self::snapshot( $source, $course, $destination, $children, $can );
		if ( null === $plan || ! isset( $target['ID'], $target['post_type'], $target['post_parent'] ) || 'topics' !== $target['post_type'] || (int) $target['post_parent'] !== (int) $destination['ID'] ) {
			return null;
		}
		$live   = array( 'tutor-google-meet' => true, 'tutor_zoom_meeting' => true );
		$copies = array();
		foreach ( $plan as $child ) {
			if ( isset( $live[ $child['post_type'] ] ) ) {
				continue;
			}
			$copy     = array(
				'post_type'    => $child['post_type'],
				'post_title'   => isset( $child['post_title'] ) && is_string( $child['post_title'] ) ? $child['post_title'] : '',
				'post_content' => isset( $child['post_content'] ) && is_string( $child['post_content'] ) ? $child['post_content'] : '',
				'post_status'  => isset( $child['post_status'] ) && is_string( $child['post_status'] ) ? $child['post_status'] : '',
				'post_parent'  => (int) $target['ID'],
				'menu_order'   => (int) $child['menu_order'],
			);
			$inserted = wp_insert_post( $copy, true );
			if ( $inserted instanceof WP_Error || ! is_int( $inserted ) || $inserted < 1 ) {
				return null;
			}
			if ( isset( $child['meta'] ) && is_array( $child['meta'] ) ) {
				foreach ( $child['meta'] as $key => $values ) {
					if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) || ! is_array( $values ) ) {
						continue;
					}
					foreach ( $values as $value ) {
						add_post_meta( $inserted, $key, wp_slash( $value ) );
					}
				}
			}
			$copy['ID'] = $inserted;
			$copies[]   = $copy;
		}
		return $copies;
	}

	/** Publish live copies. No provider call. */
	public static function copy_live( array $source, array $course, array $destination, array $target, array $children, $can ) {
		$plan   = self::snapshot( $source, $course, $destination, $children, $can );
		$author = isset( $destination['post_author'] ) ? (int) $destination['post_author'] : 0;
		if ( null === $plan || $author < 1 || ! isset( $target['ID'], $target['post_type'], $target['post_parent'] ) || 'topics' !== $target['post_type'] || (int) $target['post_parent'] !== (int) $destination['ID'] ) {
			return null;
		}
		$safe   = array(
			'tutor-google-meet'  => array( 'tutor-google-meet-start-datetime', 'tutor-google-meet-end-datetime' ),
			'tutor_zoom_meeting' => array( '_tutor_zm_start_date', '_tutor_zm_start_datetime', '_tutor_zm_duration', '_tutor_zm_duration_unit' ),
		);
		$copies = array();
		foreach ( $plan as $child ) {
			if ( ! isset( $safe[ $child['post_type'] ] ) ) {
				continue;
			}
			$copy     = array(
				'post_type'    => $child['post_type'],
				'post_title'   => isset( $child['post_title'] ) && is_string( $child['post_title'] ) ? $child['post_title'] : '',
				'post_content' => isset( $child['post_content'] ) && is_string( $child['post_content'] ) ? $child['post_content'] : '',
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_parent'  => (int) $target['ID'],
				'menu_order'   => (int) $child['menu_order'],
			);
			$inserted = wp_insert_post( $copy, true );
			if ( $inserted instanceof WP_Error || ! is_int( $inserted ) || $inserted < 1 ) {
				return null;
			}
			$kept  = array_fill_keys( $safe[ $child['post_type'] ], true );
			$wrote = false;
			if ( isset( $child['meta'] ) && is_array( $child['meta'] ) ) {
				foreach ( $child['meta'] as $key => $values ) {
					if ( ! isset( $kept[ $key ] ) || ! is_array( $values ) ) {
						continue;
					}
					foreach ( $values as $value ) {
						add_post_meta( $inserted, $key, wp_slash( $value ) );
						$wrote = true;
					}
				}
			}
			if ( 'tutor_zoom_meeting' === $child['post_type'] ) {
				add_post_meta( $inserted, '_tutor_zm_for_course', (int) $destination['ID'] );
				add_post_meta( $inserted, '_tutor_zm_for_topic', (int) $target['ID'] );
			}
			$form = self::form_blob( $child );
			if ( is_string( $form ) ) {
				add_post_meta( $inserted, 'tutor_zoom_meeting' === $child['post_type'] ? '_tutor_zm_data' : 'tutor-google-meet-event-details', wp_slash( $form ) );
			}
			if ( ! $wrote ) {
				self::copy_operation( $inserted, $child, is_string( $form ) );
			}
			$copy['ID'] = $inserted;
			$copies[] = $copy;
		}
		return $copies;
	}

	/** Form fields for one live copy. No meeting id and no join link. */
	private static function form_blob( array $child ) {
		$zoom = 'tutor_zoom_meeting' === $child['post_type'];
		$key  = $zoom ? '_tutor_zm_data' : 'tutor-google-meet-event-details';
		$data = self::object_meta( $child, $key );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$blob = array();
		foreach ( ( $zoom ? array( 'timezone', 'host_id', 'password' ) : array( 'timezone', 'attendees' ) ) as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$blob[ $field ] = $data[ $field ];
			}
		}
		if ( $zoom ) {
			$source   = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
			$settings = array();
			if ( isset( $source['auto_recording'] ) && is_string( $source['auto_recording'] ) && in_array( $source['auto_recording'], array( 'local', 'cloud', 'none' ), true ) ) {
				$settings['auto_recording'] = $source['auto_recording'];
			}
			if ( array_key_exists( 'waiting_room', $source ) && is_bool( $source['waiting_room'] ) ) {
				$settings['waiting_room'] = $source['waiting_room'];
			}
			if ( array() !== $settings ) {
				$blob['settings'] = $settings;
			}
		}
		return self::encode_blob( $blob );
	}

	/** Pending or error schedule when the copy wrote no schedule scalar. */
	private static function copy_operation( $post_id, array $child, $has_blob ) {
		$stored    = self::object_meta( $child, '_tutorpress_live_lesson_sync' );
		$operation = is_array( $stored ) && isset( $stored['state'], $stored['operation'] ) && in_array( $stored['state'], array( 'pending', 'error' ), true ) && is_array( $stored['operation'] ) ? $stored['operation'] : null;
		if ( ! is_array( $operation ) ) { return; }
		$zoom     = 'tutor_zoom_meeting' === $child['post_type'];
		$target   = isset( $operation['provider_target'] ) && is_array( $operation['provider_target'] ) ? $operation['provider_target'] : array();
		$start    = isset( $operation['start_local'] ) && is_string( $operation['start_local'] ) ? $operation['start_local'] : '';
		$end      = isset( $operation['end_local'] ) && is_string( $operation['end_local'] ) ? $operation['end_local'] : '';
		$zone     = isset( $operation['timezone'] ) && is_string( $operation['timezone'] ) ? $operation['timezone'] : '';
		$duration = isset( $operation['duration'] ) && is_array( $operation['duration'] ) ? $operation['duration'] : array();
		$unit     = isset( $duration['unit'] ) && is_string( $duration['unit'] ) ? $duration['unit'] : '';
		$minutes  = 'minutes' === $unit ? 'min' : ( 'hours' === $unit ? 'hr' : '' );
		$meta     = array();
		if ( $zoom && '' !== $start ) { $meta['_tutor_zm_start_datetime'] = $start; if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})/', $start, $found ) ) { $meta['_tutor_zm_start_date'] = $found[1]; } }
		if ( $zoom && '' !== $minutes && isset( $duration['value'] ) && is_int( $duration['value'] ) ) { $meta['_tutor_zm_duration'] = $duration['value']; $meta['_tutor_zm_duration_unit'] = $minutes; }
		if ( ! $zoom && '' !== $start ) { $meta['tutor-google-meet-start-datetime'] = $start; }
		if ( ! $zoom && '' !== $end ) { $meta['tutor-google-meet-end-datetime'] = $end; }
		if ( ! $has_blob ) {
			$blob = array();
			if ( '' !== $zone ) { $blob['timezone'] = $zone; }
			if ( $zoom && isset( $target['host_id'] ) && is_string( $target['host_id'] ) && '' !== $target['host_id'] ) { $blob['host_id'] = $target['host_id']; }
			$attendees = ! $zoom && isset( $target['add_enrolled_students'] ) && is_string( $target['add_enrolled_students'] ) ? $target['add_enrolled_students'] : '';
			if ( in_array( $attendees, array( 'Yes', 'No' ), true ) ) { $blob['attendees'] = $attendees; }
			$settings  = array();
			$recording = $zoom && isset( $target['auto_recording'] ) && is_string( $target['auto_recording'] ) ? $target['auto_recording'] : '';
			if ( in_array( $recording, array( 'local', 'cloud', 'none' ), true ) ) { $settings['auto_recording'] = $recording; }
			if ( $zoom && array_key_exists( 'waiting_room', $target ) && is_bool( $target['waiting_room'] ) ) { $settings['waiting_room'] = $target['waiting_room']; }
			if ( array() !== $settings ) { $blob['settings'] = $settings; }
			$encoded = self::encode_blob( $blob );
			if ( is_string( $encoded ) && array() !== $blob ) { $meta[ $zoom ? '_tutor_zm_data' : 'tutor-google-meet-event-details' ] = $encoded; }
		}
		foreach ( $meta as $key => $value ) { add_post_meta( $post_id, $key, wp_slash( $value ) ); }
	}

	/** JSON object for one copied form blob. */
	private static function encode_blob( array $blob ) {
		$encoded = json_encode( array() === $blob ? new stdClass() : $blob, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT );
		return is_string( $encoded ) ? $encoded : null;
	}

	/** First child meta value when it is an array or a JSON object. */
	private static function object_meta( array $child, $key ) {
		if ( ! isset( $child['meta'][ $key ] ) || ! is_array( $child['meta'][ $key ] ) ) {
			return null;
		}
		$value = reset( $child['meta'][ $key ] );
		if ( is_array( $value ) ) {
			return $value;
		}
		$decoded = is_string( $value ) && '' !== $value ? json_decode( $value, true ) : null;
		return is_array( $decoded ) ? $decoded : null;
	}

	/** One transaction around both child-copy groups. */
	public static function copy_topic( array $source, array $course, array $destination, array $target, array $children, $can ) {
		$plan   = self::snapshot( $source, $course, $destination, $children, $can );
		$author = isset( $destination['post_author'] ) ? (int) $destination['post_author'] : 0;
		if ( null === $plan || $author < 1 || ! isset( $target['ID'], $target['post_type'], $target['post_parent'] ) || 'topics' !== $target['post_type'] || (int) $target['post_parent'] !== (int) $destination['ID'] ) {
			return null;
		}
		return self::boundary( static function () use ( $source, $course, $destination, $target, $children, $can ) {
			$non_live = self::copy_non_live( $source, $course, $destination, $target, $children, $can );
			if ( ! is_array( $non_live ) ) {
				return $non_live;
			}
			$live = self::copy_live( $source, $course, $destination, $target, $children, $can );
			if ( ! is_array( $live ) ) {
				return $live;
			}
			return array( 'non_live' => $non_live, 'live' => $live );
		} );
	}

	/** Commit an array result. Roll back a throwable, failed commit, or other non-array result. */
	private static function boundary( $work ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$result = is_callable( $work ) ? $work() : null;
		} catch ( Throwable $thrown ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
		if ( ! is_array( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result instanceof WP_Error ? $result : null;
		}
		$committed = $wpdb->query( 'COMMIT' );
		if ( $committed instanceof WP_Error || false === $committed ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
		return $result;
	}
}
