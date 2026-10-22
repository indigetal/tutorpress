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
						add_post_meta( $inserted, $key, $value );
					}
				}
			}
			$copy['ID'] = $inserted;
			$copies[]   = $copy;
		}
		return $copies;
	}

	/** Publish live copies, then initialize one unlinked envelope. No provider call. */
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
			$kept = array_fill_keys( $safe[ $child['post_type'] ], true );
			if ( isset( $child['meta'] ) && is_array( $child['meta'] ) ) {
				foreach ( $child['meta'] as $key => $values ) {
					if ( ! isset( $kept[ $key ] ) || ! is_array( $values ) ) {
						continue;
					}
					foreach ( $values as $value ) {
						add_post_meta( $inserted, $key, $value );
					}
				}
			}
			if ( 'tutor_zoom_meeting' === $child['post_type'] ) {
				add_post_meta( $inserted, '_tutor_zm_for_course', (int) $destination['ID'] );
				add_post_meta( $inserted, '_tutor_zm_for_topic', (int) $target['ID'] );
			}
			$copy['ID'] = $inserted;
			$bound      = self::initialize_live( $copy, $destination, $target, $can );
			if ( ! is_string( $bound ) ) {
				return $bound;
			}
			$copies[] = $copy;
		}
		return $copies;
	}

	/** Lock and initialize one unlinked envelope. Delete the copy when initialization fails. */
	private static function initialize_live( array $copy, array $course, array $topic, $can ) {
		$id       = isset( $copy['ID'] ) ? (int) $copy['ID'] : 0;
		$provider = isset( $copy['post_type'] ) && 'tutor_zoom_meeting' === $copy['post_type'] ? 'zoom' : 'google_meet';
		$author   = isset( $copy['post_author'] ) ? (int) $copy['post_author'] : 0;
		$token    = bin2hex( random_bytes( 16 ) );
		$expiry   = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' );
		$next     = array( 'schema_version' => 1, 'version' => 1, 'state' => 'unlinked', 'provider' => $provider, 'owner_id' => $author, 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null );
		$lock     = $id > 0 && $author > 0 ? TutorPress_Live_Lesson_Sync_Repository::acquire( $id, 'initialize', $token, $expiry, static function ( $name, $bytes ) { return add_option( $name, $bytes, '', false ); } ) : null;
		$stored   = is_string( $lock ) ? TutorPress_Live_Lesson_Sync_Repository::during( $id, $token, $lock, static function () use ( $id, $lock, $token, $next ) {
			return TutorPress_Live_Lesson_Sync_Repository::initialize( $id, $lock, $token, 0, $next, static function ( $post_id, $key ) { return self::meta_rows( $post_id, $key ); }, static function ( $post_id, $key, $value ) { return add_post_meta( $post_id, $key, $value ); }, static function () { return true; } );
		}, static function ( $name, $prior ) { return self::delete_lock( $name, $prior ); } ) : null;
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
		if ( $committed instanceof WP_Error || false === $committed || 0 === $committed || '0' === $committed ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}
		return $result;
	}
}
