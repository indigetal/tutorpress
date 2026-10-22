<?php
/**
 * Deterministic live-lesson credential owner.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dormant user IDs. Google is the acting user. Zoom is the course author, then the acting instructor. */
class TutorPress_Live_Lesson_Owner_Resolver {
	public static function google( $authorized, $acting_user_id ) {
		$id = self::user_id( $acting_user_id );
		return is_int( $authorized ) && $authorized > 0 ? $id : null;
	}

	public static function zoom( $authorized, $course_author_id, $acting_instructor_id ) {
		$author = self::user_id( $course_author_id );
		$id     = null !== $author ? $author : self::user_id( $acting_instructor_id );
		return is_int( $authorized ) && $authorized > 0 ? $id : null;
	}

	public static function zoom_eligible( $authorized, $course_author_id, $acting_instructor_id, array $credentials ) {
		$owner = self::zoom( $authorized, $course_author_id, $acting_instructor_id );
		return null !== $owner && self::filled( $credentials ) ? $owner : null;
	}

	public static function host_input( $authorized, $owner_id, $host_id ) {
		$owner = is_int( $authorized ) && $authorized > 0 ? self::user_id( $owner_id ) : null;
		$host  = is_string( $host_id ) && '' !== $host_id ? $host_id : null;
		return null !== $owner && null !== $host ? array( 'owner_id' => $owner, 'host_id' => $host ) : null;
	}

	public static function legacy_candidates( $authorized, $provider, array $evidence, $identity ) {
		if ( ! is_int( $authorized ) || $authorized < 1 || ( 'google' !== $provider && 'zoom' !== $provider ) ) {
			return null;
		}
		$candidates = array();
		$roles      = array();
		foreach ( $evidence as $row ) {
			$source = is_array( $row ) && isset( $row['source'] ) ? $row['source'] : '';
			$id     = is_array( $row ) ? self::user_id( isset( $row['id'] ) ? $row['id'] : 0 ) : null;
			$label  = is_array( $row ) && isset( $row['label'] ) && is_string( $row['label'] ) && '' !== $row['label'] ? $row['label'] : '';
			if ( null === $id || '' === $label || ! in_array( $source, array( 'acting_user', 'course_author', 'acting_instructor', 'post_author' ), true ) ) {
				continue;
			}
			$candidates[ $id ]       = isset( $candidates[ $id ] ) ? $candidates[ $id ] : array( 'id' => $id, 'label' => $label );
			$roles[ $source ][ $id ] = $id;
		}
		$acting      = isset( $roles['acting_user'] ) ? $roles['acting_user'] : array();
		$authors     = isset( $roles['course_author'] ) ? $roles['course_author'] : array();
		$instructors = isset( $roles['acting_instructor'] ) ? $roles['acting_instructor'] : array();
		$owner       = 'google' === $provider && 1 === count( $acting ) ? reset( $acting ) : null;
		if ( 'zoom' === $provider && count( $authors ) < 2 && ! ( array() === $authors && count( $instructors ) > 1 ) ) {
			$owner = self::zoom( $authorized, array() === $authors ? 0 : reset( $authors ), array() === $instructors ? 0 : reset( $instructors ) );
		}
		return array( 'candidates' => array_values( $candidates ), 'owner_id' => $owner, 'code' => null === $owner ? 'legacy_owner_resolution_required' : 'legacy_identity_resolution_required' );
	}

	private static function user_id( $id ) {
		$id = is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ? (int) $id : 0;
		return $id > 0 ? $id : null;
	}

	private static function filled( array $credentials ) {
		foreach ( array( 'account_id', 'api_key', 'api_secret' ) as $key ) {
			if ( ! isset( $credentials[ $key ] ) || ! is_string( $credentials[ $key ] ) || '' === $credentials[ $key ] ) {
				return false;
			}
		}
		return true;
	}
}
