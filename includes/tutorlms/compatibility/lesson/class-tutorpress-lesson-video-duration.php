<?php
/**
 * Lesson video duration helper.
 *
 * @package TutorPress
 * @since 1.13.17
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared duration and curriculum row summary helpers for lesson video data.
 *
 * @since 1.13.17
 */
class TutorPress_Lesson_Video_Duration {

	/**
	 * Normalize duration data to non-negative integer parts.
	 *
	 * @since 1.13.17
	 *
	 * @param array $duration Raw duration data.
	 * @return array{hours:int,minutes:int,seconds:int}
	 */
	public static function normalize_duration( $duration ) {
		$duration = is_array( $duration ) ? $duration : array();

		return array(
			'hours'   => absint( $duration['hours'] ?? 0 ),
			'minutes' => absint( $duration['minutes'] ?? 0 ),
			'seconds' => absint( $duration['seconds'] ?? 0 ),
		);
	}

	/**
	 * Check whether duration data contains any non-zero value.
	 *
	 * @since 1.13.17
	 *
	 * @param array $duration Duration data.
	 * @return bool
	 */
	public static function has_non_zero_duration( $duration ) {
		$duration = self::normalize_duration( $duration );

		return $duration['hours'] > 0 || $duration['minutes'] > 0 || $duration['seconds'] > 0;
	}

	/**
	 * Format duration data as a Tutor LMS playtime string.
	 *
	 * @since 1.13.17
	 *
	 * @param array $duration Duration data.
	 * @return string
	 */
	public static function format_playtime( $duration ) {
		$duration = self::normalize_duration( $duration );

		return sprintf( '%02d:%02d:%02d', $duration['hours'], $duration['minutes'], $duration['seconds'] );
	}

	/**
	 * Format duration for display using Tutor LMS when available.
	 *
	 * @since 1.13.17
	 *
	 * @param array $duration Duration data.
	 * @return string
	 */
	public static function format_display_duration( $duration ) {
		if ( ! self::has_non_zero_duration( $duration ) ) {
			return '';
		}

		$playtime = self::format_playtime( $duration );

		if ( function_exists( 'tutor_utils' ) ) {
			$tutor_utils = tutor_utils();
			if ( is_object( $tutor_utils ) && method_exists( $tutor_utils, 'get_optimized_duration' ) ) {
				return (string) $tutor_utils->get_optimized_duration( $playtime );
			}
		}

		return $playtime;
	}

	/**
	 * Get TutorPress duration fields for a lesson.
	 *
	 * @since 1.13.17
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return array{hours:int,minutes:int,seconds:int}
	 */
	public static function get_lesson_duration( $lesson_id ) {
		$lesson_id = absint( $lesson_id );

		return self::normalize_duration(
			array(
				'hours'   => get_post_meta( $lesson_id, '_lesson_video_duration_hours', true ),
				'minutes' => get_post_meta( $lesson_id, '_lesson_video_duration_minutes', true ),
				'seconds' => get_post_meta( $lesson_id, '_lesson_video_duration_seconds', true ),
			)
		);
	}

	/**
	 * Get video summary data for curriculum rows and compatibility payloads.
	 *
	 * The synced _video row state decides whether a lesson has video. Source-
	 * specific validation remains owned by TutorPress lesson sync code.
	 *
	 * @since 1.13.17
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return array{has_video:bool,source:string,duration:array,playtime:string,display_duration:string}
	 */
	public static function get_lesson_video_summary( $lesson_id ) {
		$video     = get_post_meta( absint( $lesson_id ), '_video', true );
		$source    = is_array( $video ) ? sanitize_key( $video['source'] ?? '' ) : '';
		$has_video = is_array( $video ) && '' !== $source && '-1' !== $source;
		$duration  = $has_video ? self::get_lesson_duration( $lesson_id ) : self::normalize_duration( array() );

		if ( $has_video && 'html5' === $source && ! self::has_non_zero_duration( $duration ) ) {
			$duration = self::get_html5_attachment_duration( absint( $video['source_video_id'] ?? 0 ) );
		}

		$has_duration = self::has_non_zero_duration( $duration );

		return array(
			'has_video'        => $has_video,
			'source'           => $has_video ? $source : '',
			'duration'         => $duration,
			'playtime'         => $has_duration ? self::format_playtime( $duration ) : '',
			'display_duration' => $has_duration ? self::format_display_duration( $duration ) : '',
		);
	}

	/**
	 * Read HTML5 attachment duration from metadata without mutating it.
	 *
	 * @since 1.13.17
	 *
	 * @param int $attachment_id Attachment ID from _video.source_video_id.
	 * @return array{hours:int,minutes:int,seconds:int}
	 */
	private static function get_html5_attachment_duration( $attachment_id ) {
		$metadata = $attachment_id ? wp_get_attachment_metadata( absint( $attachment_id ) ) : false;
		if ( ! is_array( $metadata ) ) {
			return self::normalize_duration( array() );
		}

		if ( ! empty( $metadata['length_formatted'] ) ) {
			return self::parse_playtime( (string) $metadata['length_formatted'] );
		}

		if ( isset( $metadata['length'] ) ) {
			$total_seconds = absint( $metadata['length'] );

			return array(
				'hours'   => (int) floor( $total_seconds / HOUR_IN_SECONDS ),
				'minutes' => (int) floor( ( $total_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ),
				'seconds' => (int) ( $total_seconds % MINUTE_IN_SECONDS ),
			);
		}

		return self::normalize_duration( array() );
	}

	/**
	 * Parse HH:MM:SS or MM:SS playtime into duration parts.
	 *
	 * @since 1.13.17
	 *
	 * @param string $playtime Playtime string.
	 * @return array{hours:int,minutes:int,seconds:int}
	 */
	private static function parse_playtime( $playtime ) {
		$parts = array_map( 'absint', array_reverse( explode( ':', $playtime ) ) );

		return self::normalize_duration(
			array(
				'hours'   => $parts[2] ?? 0,
				'minutes' => $parts[1] ?? 0,
				'seconds' => $parts[0] ?? 0,
			)
		);
	}
}
