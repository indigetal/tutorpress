<?php
/**
 * Dormant per-record option lock.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Non-autoloaded tutorpress_live_lesson_lock_{post_id} with immutable token, operation, and expiry JSON. */
class TutorPress_Live_Lesson_Sync_Repository {
	/** Rows changed by the latest meta compare-and-set. */
	private static $meta_affected = 0;

	/** Insert the lock once. Failure returns null and records no token. */
	public static function acquire( $post_id, $operation, $token, $expiry, $insert ) {
		$bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'token' => $token, 'operation' => $operation, 'expiry' => $expiry ) );
		$name  = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		if ( ! is_string( $bytes ) || ! is_callable( $insert ) || true !== $insert( $name, $bytes ) ) {
			return null;
		}
		return $bytes;
	}

	/** True when the stored JSON token matches. */
	public static function owns( $bytes, $token ) {
		$stored = is_string( $bytes ) ? json_decode( $bytes, true ) : null;
		return is_array( $stored ) && isset( $stored['token'] ) && is_string( $stored['token'] ) && is_string( $token ) && hash_equals( $stored['token'], $token );
	}

	/** Replace an expired lock when the exact prior bytes still match. */
	public static function takeover( $post_id, $operation, $token, $prior, $now, $update ) {
		$stored = is_string( $prior ) ? json_decode( $prior, true ) : null;
		$expiry = is_array( $stored ) && isset( $stored['expiry'] ) ? self::utc( $stored['expiry'] ) : null;
		$clock  = self::utc( $now );
		if ( ! $expiry instanceof DateTimeImmutable || ! $clock instanceof DateTimeImmutable || $expiry > $clock ) {
			return null;
		}
		$bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'token' => $token, 'operation' => $operation, 'expiry' => $clock->modify( '+5 minutes' )->format( 'Y-m-d H:i:s' ) ) );
		$name  = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		if ( ! is_string( $bytes ) || ! is_callable( $update ) || true !== $update( $name, $bytes, $prior ) ) {
			return null;
		}
		return $bytes;
	}

	/** Delete only when the token owns these exact bytes. */
	public static function release( $post_id, $token, $prior, $delete ) {
		$name = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		if ( ! self::owns( $prior, $token ) || ! is_callable( $delete ) ) {
			return false;
		}
		return true === $delete( $name, $prior );
	}

	/** Release this exact token after the callback returns or throws. */
	public static function during( $post_id, $token, $prior, $callback, $delete ) {
		try {
			return is_callable( $callback ) ? $callback() : null;
		} finally {
			self::release( $post_id, $token, $prior, $delete );
		}
	}

	/** Parse one injected UTC clock. A malformed value stays null. */
	private static function utc( $value ) {
		$parsed = is_string( $value ) ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) ) : false;
		$errors = DateTimeImmutable::getLastErrors();
		$valid  = $parsed instanceof DateTimeImmutable && ( ! is_array( $errors ) || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $parsed->format( 'Y-m-d H:i:s' ) === $value;
		return $valid ? $parsed : null;
	}

	/** Insert one version-1 envelope for the locked post when no row exists. */
	public static function initialize( $post_id, $lock, $token, $expected_version, $next, $read, $insert, $cache ) {
		if ( ! is_string( $lock ) || ! self::owns( $lock, $token ) ) {
			return null;
		}
		$key    = '_tutorpress_live_lesson_sync';
		$rows   = is_callable( $read ) ? $read( (int) $post_id, $key ) : null;
		$stored = 0 === $expected_version ? TutorPress_Live_Lesson_Sync_Envelope::initialize_version_one( null, $next ) : null;
		$bytes  = is_array( $stored ) ? TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $stored ) : null;
		if ( ! is_array( $rows ) || array() !== $rows || ! is_string( $bytes ) || ! is_callable( $insert ) || ! is_callable( $cache ) || ! is_int( $insert( (int) $post_id, $key, $bytes ) ) ) {
			return null;
		}
		$after = $read( (int) $post_id, $key );
		if ( ! is_array( $after ) || 1 !== count( $after ) || ! isset( $after[0]['meta_value'] ) || $after[0]['meta_value'] !== $bytes || (int) $after[0]['post_id'] !== (int) $post_id ) {
			return null;
		}
		$cache( $key );
		return $bytes;
	}

	/** Replace one canonical envelope when the lock, state, and version match. */
	public static function exchange( $post_id, $lock, $token, $operation, $state, $version, $prior, $next, $read, $update, $affected ) {
		$held = is_string( $lock ) ? json_decode( $lock, true ) : null;
		if ( ! self::owns( $lock, $token ) || ! is_array( $held ) || ! isset( $held['operation'] ) || $held['operation'] !== $operation ) {
			return null;
		}
		$key     = '_tutorpress_live_lesson_sync';
		$rows    = is_callable( $read ) ? $read( (int) $post_id, $key ) : null;
		$current = is_string( $prior ) ? json_decode( $prior, true ) : null;
		$exact   = is_array( $current ) ? TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $current ) : null;
		$stored  = TutorPress_Live_Lesson_Sync_Envelope::envelope( $next );
		$bytes   = is_array( $stored ) ? TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $stored ) : null;
		if ( ! is_array( $rows ) || 1 !== count( $rows ) || (int) $rows[0]['post_id'] !== (int) $post_id || ! isset( $rows[0]['meta_id'], $rows[0]['meta_value'] ) || $rows[0]['meta_value'] !== $prior || $exact !== $prior || ! isset( $current['state'], $current['version'] ) || $current['state'] !== $state || $current['version'] !== $version || ! is_string( $bytes ) || ! is_callable( $update ) || ! is_callable( $affected ) ) {
			return null;
		}
		if ( true !== $update( (int) $post_id, $key, $rows[0]['meta_id'], $bytes, $prior ) || 1 !== $affected() ) {
			return null;
		}
		return $bytes;
	}

	/** Replace option bytes when one binary prior still matches. */
	public static function compare_option_update( $name, $bytes, $prior ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $bytes, $name, $prior ) );
		return 1 === (int) $wpdb->rows_affected;
	}

	/** Canonical meta_id, meta_value, and post_id rows for one post and key. */
	public static function meta_rows( $post_id, $key ) {
		global $wpdb;
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value, post_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", (int) $post_id, $key ) );
		$rows  = array();
		foreach ( is_array( $found ) ? $found : array() as $row ) {
			$item = (array) $row;
			if ( isset( $item['meta_id'], $item['meta_value'], $item['post_id'] ) ) {
				$rows[] = array( 'meta_id' => (int) $item['meta_id'], 'meta_value' => $item['meta_value'], 'post_id' => (int) $item['post_id'] );
			}
		}
		return $rows;
	}

	/** Replace one meta row when its id and binary prior still match. */
	public static function compare_meta_update( $post_id, $key, $meta_id, $bytes, $prior ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d AND post_id = %d AND meta_key = %s AND CONVERT(meta_value USING BINARY) = CONVERT(%s USING BINARY)", $bytes, (int) $meta_id, (int) $post_id, $key, $prior ) );
		self::$meta_affected = (int) $wpdb->rows_affected;
		return 1 === self::$meta_affected;
	}

	/** Affected-row count from the latest meta compare-and-set. */
	public static function last_affected() {
		return self::$meta_affected;
	}
}
