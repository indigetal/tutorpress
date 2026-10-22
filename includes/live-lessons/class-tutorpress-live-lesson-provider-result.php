<?php
/**
 * Provider-neutral live-lesson result.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Confirmed, definite rejection, known-ID not-found, pre-dispatch failure, or uncertain. */
class TutorPress_Live_Lesson_Provider_Result {
	/** An exact five-key array is the only accepted call. */
	public static function from( $outcome ) {
		return is_array( $outcome ) ? self::normalized( $outcome ) : null;
	}

	/** Identity is the top-level provider_id, never a diagnostics copy. */
	public static function identity( array $result ) {
		return isset( $result['provider_id'] ) && is_string( $result['provider_id'] ) && '' !== $result['provider_id'] ? $result['provider_id'] : null;
	}

	/** Exact outcome, provider_id, item, diagnostics, and provenance. */
	private static function normalized( array $input ) {
		$keys = array_keys( $input );
		sort( $keys );
		$outcome = isset( $input['outcome'] ) ? $input['outcome'] : null;
		$provenance = isset( $input['provenance'] ) ? $input['provenance'] : null;
		$id = array_key_exists( 'provider_id', $input ) ? $input['provider_id'] : false;
		$diagnostics = self::diagnostics( isset( $input['diagnostics'] ) ? $input['diagnostics'] : null );
		$item = self::item( array_key_exists( 'item', $input ) ? $input['item'] : false, $outcome, $provenance );
		if ( array( 'diagnostics', 'item', 'outcome', 'provenance', 'provider_id' ) !== $keys || self::unsafe( $input ) || ! in_array( $outcome, array( 'confirmed', 'definite_rejection', 'known_id_not_found', 'pre_dispatch_failure', 'uncertain' ), true ) || ! in_array( $provenance, array( 'create', 'fetch', 'update', 'delete' ), true ) || ( null !== $id && ( ! is_string( $id ) || '' === $id ) ) || false === $diagnostics || false === $item ) {
			return null;
		}
		return array( 'outcome' => $outcome, 'provider_id' => $id, 'item' => $item, 'diagnostics' => $diagnostics, 'provenance' => $provenance );
	}

	/** Diagnostics keep only sanitized error and warning strings. */
	private static function diagnostics( $value ) {
		if ( ! is_array( $value ) || array_key_exists( 'provider_id', $value ) ) { return false; }
		$safe = array();
		foreach ( array( 'error', 'warning' ) as $key ) {
			if ( ! array_key_exists( $key, $value ) ) { continue; }
			if ( ! is_string( $value[ $key ] ) ) { return false; }
			$safe[ $key ] = $value[ $key ];
		}
		return $safe;
	}

	/** Confirmed create, fetch, and update items use the exact schedule shape. */
	private static function item( $item, $outcome, $provenance ) {
		if ( 'confirmed' !== $outcome || ! in_array( $provenance, array( 'create', 'fetch', 'update' ), true ) ) { return null === $item ? null : false; }
		if ( ! is_array( $item ) ) { return false; }
		$keys = array_keys( $item );
		sort( $keys );
		$instant = function ( $value ) { return null === $value || ( is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value ) ); };
		return array( 'details', 'duration_minutes', 'end_at', 'join_url', 'start_at', 'timezone' ) === $keys && $instant( $item['start_at'] ) && $instant( $item['end_at'] ) && ( null === $item['timezone'] || is_string( $item['timezone'] ) ) && ( null === $item['duration_minutes'] || is_int( $item['duration_minutes'] ) ) && ( null === $item['join_url'] || is_string( $item['join_url'] ) ) && self::details( $item['details'] ) ? $item : false;
	}

	/** Detail values stay scalar or null. Nested lists use the same rule. */
	private static function details( $value ) {
		if ( ! is_array( $value ) ) { return false; }
		foreach ( $value as $item ) {
			if ( is_array( $item ) && ! self::details( $item ) ) { return false; }
			if ( ! is_array( $item ) && null !== $item && ! is_scalar( $item ) ) { return false; }
		}
		return true;
	}

	/** Raw request, response, exception, credential, token, header, and cookie keys are unsafe. */
	private static function unsafe( $value ) {
		if ( is_object( $value ) || ! is_array( $value ) ) { return is_object( $value ); }
		foreach ( $value as $key => $item ) {
			if ( ( is_string( $key ) && 1 === preg_match( '/request|response|exception|credential|token|header|cookie/i', $key ) ) || self::unsafe( $item ) ) { return true; }
		}
		return false;
	}
}
