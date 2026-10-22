<?php
/**
 * Minute-precision wall-clock grammar, timezone membership, and strict round-trip parse.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dormant YYYY-MM-DD HH:mm:00 and UTC / DateTimeZone::ALL_WITH_BC checks. */
class TutorPress_Live_Lesson_Datetime {
	/** Accept only minute-precision wall-clock text. */
	public static function is_wall_clock( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01]) (?:[01][0-9]|2[0-3]):[0-5][0-9]:00\z/', $value );
	}

	/** Accept UTC or an identifier from DateTimeZone::ALL_WITH_BC. Constructor aliases outside that list are rejected. */
	public static function is_approved_timezone( $timezone ) {
		return 'UTC' === $timezone || ( is_string( $timezone ) && in_array( $timezone, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true ) );
	}

	/** True only when transitions map this wall clock to one instant. */
	private static function has_unique_instant( $value, $timezone ) {
		$naive = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		if ( ! $naive instanceof DateTimeImmutable ) {
			return false;
		}
		$local = $naive->getTimestamp();
		$transitions = ( new DateTimeZone( $timezone ) )->getTransitions( $local - ( 36 * 3600 ), $local + ( 36 * 3600 ) );
		$instants = array();
		$count = count( $transitions );
		for ( $i = 0; $i < $count; $i++ ) {
			$instant = $local - $transitions[ $i ]['offset'];
			$from = $transitions[ $i ]['ts'];
			$until = ( $i + 1 < $count ) ? $transitions[ $i + 1 ]['ts'] : PHP_INT_MAX;
			if ( $instant >= $from && $instant < $until ) {
				$instants[ $instant ] = true;
			}
		}
		return 1 === count( $instants );
	}

	/** Parse in the approved zone. Reject errors, warnings, non-round-trips, gaps, and folds. */
	public static function parse_wall_clock( $value, $timezone ) {
		if ( ! self::is_wall_clock( $value ) || ! self::is_approved_timezone( $timezone ) || ! self::has_unique_instant( $value, $timezone ) ) {
			return null;
		}
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( $timezone ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $parsed instanceof DateTimeImmutable || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return null;
		}
		return $parsed->format( 'Y-m-d H:i:s' ) === $value ? $parsed : null;
	}

	/** True when both wall clocks resolve and the end instant is later. */
	public static function google_end_after_start( $start, $end, $timezone ) {
		$start_instant = self::parse_wall_clock( $start, $timezone );
		$end_instant   = self::parse_wall_clock( $end, $timezone );
		return $start_instant instanceof DateTimeImmutable && $end_instant instanceof DateTimeImmutable && $end_instant->getTimestamp() > $start_instant->getTimestamp();
	}

	/** Selected-zone future check for provider-create and schedule-changing update. */
	public static function is_future_wall_clock( $value, $timezone, DateTimeImmutable $now ) {
		$instant = self::parse_wall_clock( $value, $timezone );
		return $instant instanceof DateTimeImmutable && $instant->getTimestamp() > $now->getTimestamp();
	}

	/** Typed Zoom duration. The submitted unit is retained. */
	public static function zoom_duration( $value, $unit ) {
		if ( is_int( $value ) && ( ( 'minutes' === $unit && $value >= 1 && $value <= 480 ) || ( 'hours' === $unit && $value >= 1 && $value <= 8 ) ) ) {
			return array( 'value' => $value, 'unit' => $unit );
		}
		return null;
	}
}
