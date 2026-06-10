<?php
/**
 * TutorPress course sync service.
 *
 * Provides the shared synchronization surface for course setting compatibility.
 *
 * @package TutorPress
 * @since 1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TutorPress_Course_Sync_Service class.
 *
 * @since 1.14.3
 */
class TutorPress_Course_Sync_Service {

	/**
	 * Course sync context and intent helper.
	 *
	 * @since 1.14.3
	 * @var TutorPress_Course_Sync_Context
	 */
	private $sync_context;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 * @param TutorPress_Course_Sync_Context $sync_context Course sync context helper.
	 */
	public function __construct( $sync_context ) {
		$this->sync_context = $sync_context;
	}

	/**
	 * Get course settings for REST API.
	 *
	 * @since 1.14.3
	 * @param array $post Post data.
	 * @return array Course settings.
	 */
	public function get_course_settings( $post ) {
		return TutorPress_Course::get_canonical_course_settings( $post['id'] );
	}

	/**
	 * Update course settings via REST API.
	 *
	 * @since 1.14.3
	 * @param array   $value New settings values.
	 * @param WP_Post $post  Post object.
	 * @return bool True on success.
	 */
	public function update_course_settings( $value, $post ) {
		return false !== TutorPress_Course::save_canonical_course_settings( $post->ID, $value );
	}

	/**
	 * Read course details and materials from Tutor LMS-backed meta.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Course details and materials settings.
	 */
	public static function get_core_details_and_material_settings( $post_id ) {
		$course_duration = get_post_meta( $post_id, '_course_duration', true );

		if ( ! is_array( $course_duration ) ) {
			$course_duration = array(
				'hours'   => 0,
				'minutes' => 0,
			);
		}

		return array(
			'course_level'              => get_post_meta( $post_id, '_tutor_course_level', true ) ?: 'all_levels',
			'is_public_course'          => 'yes' === get_post_meta( $post_id, '_tutor_is_public_course', true ),
			'enable_qna'                => 'no' !== get_post_meta( $post_id, '_tutor_enable_qa', true ),
			'course_duration'           => $course_duration,
			'course_material_includes'  => get_post_meta( $post_id, '_tutor_course_material_includes', true ) ?: '',
		);
	}

	/**
	 * Normalize course details and materials for canonical saves.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Normalized settings payload.
	 */
	public static function normalize_core_details_and_materials_for_save( array $settings ) {
		$normalized = array();

		if ( array_key_exists( 'course_level', $settings ) ) {
			$allowed_levels = array( 'beginner', 'intermediate', 'expert', 'all_levels' );
			$course_level   = sanitize_text_field( (string) $settings['course_level'] );
			$normalized['course_level'] = in_array( $course_level, $allowed_levels, true ) ? $course_level : 'all_levels';
		}

		if ( array_key_exists( 'is_public_course', $settings ) ) {
			$normalized['is_public_course'] = (bool) $settings['is_public_course'];
		}

		if ( array_key_exists( 'enable_qna', $settings ) ) {
			$normalized['enable_qna'] = (bool) $settings['enable_qna'];
		}

		if ( array_key_exists( 'course_duration', $settings ) ) {
			$normalized['course_duration'] = self::normalize_course_duration_for_save( $settings['course_duration'] );
		}

		if ( array_key_exists( 'course_material_includes', $settings ) ) {
			$normalized['course_material_includes'] = sanitize_textarea_field( (string) $settings['course_material_includes'] );
		}

		return $normalized;
	}

	/**
	 * Sanitize course details and materials for direct shadow meta writes.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Sanitized settings payload.
	 */
	public static function sanitize_core_details_and_materials( array $settings ) {
		$sanitized = array();

		if ( isset( $settings['course_level'] ) ) {
			$allowed_levels = array( 'beginner', 'intermediate', 'expert', 'all_levels' );
			$sanitized['course_level'] = in_array( $settings['course_level'], $allowed_levels ) ? $settings['course_level'] : 'all_levels';
		}

		if ( isset( $settings['is_public_course'] ) ) {
			$sanitized['is_public_course'] = (bool) $settings['is_public_course'];
		}

		if ( isset( $settings['enable_qna'] ) ) {
			$sanitized['enable_qna'] = (bool) $settings['enable_qna'];
		}

		if ( isset( $settings['course_duration'] ) ) {
			$sanitized['course_duration'] = self::normalize_course_duration_for_save( $settings['course_duration'] );
		}

		if ( isset( $settings['course_material_includes'] ) ) {
			$sanitized['course_material_includes'] = sanitize_textarea_field( $settings['course_material_includes'] );
		}

		return $sanitized;
	}

	/**
	 * Save course details and materials to Tutor LMS-backed meta.
	 *
	 * @since 1.14.3
	 * @param int   $post_id                 Course post ID.
	 * @param array $normalized_settings     Normalized settings payload.
	 * @param array $existing_tutor_settings Existing Tutor settings blob.
	 * @return void
	 */
	public static function save_core_details_and_materials( $post_id, array $normalized_settings, array &$existing_tutor_settings ) {
		if ( array_key_exists( 'course_level', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_course_level', $normalized_settings['course_level'] );
			$existing_tutor_settings['course_level'] = $normalized_settings['course_level'];
		}

		if ( array_key_exists( 'is_public_course', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_is_public_course', $normalized_settings['is_public_course'] ? 'yes' : 'no' );
			$existing_tutor_settings['is_public_course'] = $normalized_settings['is_public_course'];
		}

		if ( array_key_exists( 'enable_qna', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_enable_qa', $normalized_settings['enable_qna'] ? 'yes' : 'no' );
			$existing_tutor_settings['enable_qna'] = $normalized_settings['enable_qna'];
		}

		if ( array_key_exists( 'course_duration', $normalized_settings ) ) {
			update_post_meta( $post_id, '_course_duration', $normalized_settings['course_duration'] );
			$existing_tutor_settings['course_duration'] = $normalized_settings['course_duration'];
		}

		if ( array_key_exists( 'course_material_includes', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_course_material_includes', $normalized_settings['course_material_includes'] );
			$existing_tutor_settings['course_material_includes'] = $normalized_settings['course_material_includes'];
		}
	}

	/**
	 * Read access, enrollment, prerequisite, and schedule settings.
	 *
	 * @since 1.14.3
	 * @param int   $post_id        Course post ID.
	 * @param array $tutor_settings Existing Tutor settings blob.
	 * @return array Access, enrollment, prerequisite, and schedule settings.
	 */
	public static function get_access_enrollment_prerequisite_and_schedule_settings( $post_id, array $tutor_settings ) {
		return array(
			'maximum_students'         => self::get_maximum_students_for_read( $post_id, $tutor_settings ),
			'course_prerequisites'     => get_post_meta( $post_id, '_tutor_course_prerequisites_ids', true ) ?: array(),
			'schedule'                 => $tutor_settings['schedule'] ?? array(
				'enabled'          => false,
				'start_date'       => '',
				'start_time'       => '',
				'show_coming_soon' => false,
			),
			'course_enrollment_period' => $tutor_settings['course_enrollment_period'] ?? 'no',
			'enrollment_starts_at'     => $tutor_settings['enrollment_starts_at'] ?? '',
			'enrollment_ends_at'       => $tutor_settings['enrollment_ends_at'] ?? '',
			'pause_enrollment'         => self::get_pause_enrollment_for_read( $post_id, $tutor_settings ),
		);
	}

	/**
	 * Normalize access, enrollment, prerequisite, and schedule settings for canonical saves.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Normalized settings payload.
	 */
	public static function normalize_access_enrollment_prerequisite_and_schedule_for_save( array $settings ) {
		$normalized = array();

		if ( array_key_exists( 'maximum_students', $settings ) ) {
			if ( '' === $settings['maximum_students'] || null === $settings['maximum_students'] ) {
				$normalized['maximum_students'] = null;
			} elseif ( '0' === $settings['maximum_students'] || 0 === $settings['maximum_students'] ) {
				$normalized['maximum_students'] = 0;
			} else {
				$normalized['maximum_students'] = max( 0, (int) $settings['maximum_students'] );
			}
		}

		if ( array_key_exists( 'pause_enrollment', $settings ) ) {
			if ( is_bool( $settings['pause_enrollment'] ) ) {
				$normalized['pause_enrollment'] = $settings['pause_enrollment'] ? 'yes' : 'no';
			} else {
				$pause_enrollment = sanitize_text_field( (string) $settings['pause_enrollment'] );
				$normalized['pause_enrollment'] = 'yes' === $pause_enrollment ? 'yes' : 'no';
			}
		}

		if ( array_key_exists( 'course_enrollment_period', $settings ) ) {
			$course_enrollment_period = sanitize_text_field( (string) $settings['course_enrollment_period'] );
			$normalized['course_enrollment_period'] = 'yes' === $course_enrollment_period ? 'yes' : 'no';
		}

		if ( array_key_exists( 'enrollment_starts_at', $settings ) ) {
			$normalized['enrollment_starts_at'] = sanitize_text_field( (string) $settings['enrollment_starts_at'] );
		}

		if ( array_key_exists( 'enrollment_ends_at', $settings ) ) {
			$normalized['enrollment_ends_at'] = sanitize_text_field( (string) $settings['enrollment_ends_at'] );
		}

		if ( array_key_exists( 'course_prerequisites', $settings ) ) {
			$normalized['course_prerequisites'] = is_array( $settings['course_prerequisites'] ) ? array_map( 'absint', $settings['course_prerequisites'] ) : array();
		}

		if ( array_key_exists( 'schedule', $settings ) ) {
			$schedule = is_array( $settings['schedule'] ) ? $settings['schedule'] : array();
			$normalized['schedule'] = array(
				'enabled'          => ! empty( $schedule['enabled'] ),
				'start_date'       => sanitize_text_field( (string) ( $schedule['start_date'] ?? '' ) ),
				'start_time'       => sanitize_text_field( (string) ( $schedule['start_time'] ?? '' ) ),
				'show_coming_soon' => ! empty( $schedule['show_coming_soon'] ),
			);
		}

		return $normalized;
	}

	/**
	 * Sanitize access, enrollment, prerequisite, and schedule settings for shadow writes.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Sanitized settings payload.
	 */
	public static function sanitize_access_enrollment_prerequisite_and_schedule( array $settings ) {
		$sanitized = array();

		if ( isset( $settings['maximum_students'] ) ) {
			$max_students = $settings['maximum_students'];
			$sanitized['maximum_students'] = ( '' === $max_students || null === $max_students ) ? null : max( 0, intval( $max_students ) );
			$sanitized['maximum_students_allowed'] = $sanitized['maximum_students'];
		}

		if ( isset( $settings['pause_enrollment'] ) ) {
			$pause_enrollment = $settings['pause_enrollment'];
			$sanitized['pause_enrollment'] = is_bool( $pause_enrollment ) ? ( $pause_enrollment ? 'yes' : 'no' ) : ( in_array( $pause_enrollment, array( 'yes', 'no' ) ) ? $pause_enrollment : 'no' );
			$sanitized['enrollment_status'] = $sanitized['pause_enrollment'];
		}

		if ( isset( $settings['course_enrollment_period'] ) ) {
			$sanitized['course_enrollment_period'] = in_array( $settings['course_enrollment_period'], array( 'yes', 'no' ) ) ? $settings['course_enrollment_period'] : 'no';
		}

		if ( isset( $settings['enrollment_starts_at'] ) ) {
			$sanitized['enrollment_starts_at'] = sanitize_text_field( $settings['enrollment_starts_at'] );
		}

		if ( isset( $settings['enrollment_ends_at'] ) ) {
			$sanitized['enrollment_ends_at'] = sanitize_text_field( $settings['enrollment_ends_at'] );
		}

		if ( isset( $settings['course_prerequisites'] ) && is_array( $settings['course_prerequisites'] ) ) {
			$sanitized['course_prerequisites'] = array_map( 'absint', $settings['course_prerequisites'] );
		}

		if ( isset( $settings['schedule'] ) && is_array( $settings['schedule'] ) ) {
			$sanitized['schedule'] = array(
				'enabled'          => isset( $settings['schedule']['enabled'] ) ? (bool) $settings['schedule']['enabled'] : false,
				'start_date'       => isset( $settings['schedule']['start_date'] ) ? sanitize_text_field( $settings['schedule']['start_date'] ) : '',
				'start_time'       => isset( $settings['schedule']['start_time'] ) ? sanitize_text_field( $settings['schedule']['start_time'] ) : '',
				'show_coming_soon' => isset( $settings['schedule']['show_coming_soon'] ) ? (bool) $settings['schedule']['show_coming_soon'] : false,
			);
		}

		if ( isset( $sanitized['course_enrollment_period'] ) && 'no' === $sanitized['course_enrollment_period'] ) {
			$sanitized['enrollment_starts_at'] = '';
			$sanitized['enrollment_ends_at'] = '';
		}

		return $sanitized;
	}

	/**
	 * Save access, enrollment, prerequisite, and schedule settings.
	 *
	 * @since 1.14.3
	 * @param int   $post_id                 Course post ID.
	 * @param array $normalized_settings     Normalized settings payload.
	 * @param array $existing_tutor_settings Existing Tutor settings blob.
	 * @return void
	 */
	public static function save_access_enrollment_prerequisite_and_schedule( $post_id, array $normalized_settings, array &$existing_tutor_settings ) {
		if ( array_key_exists( 'maximum_students', $normalized_settings ) ) {
			$legacy_max = null === $normalized_settings['maximum_students'] ? '' : (int) $normalized_settings['maximum_students'];
			update_post_meta( $post_id, '_tutor_maximum_students', $legacy_max );
			$existing_tutor_settings['maximum_students'] = $normalized_settings['maximum_students'];
			$existing_tutor_settings['maximum_students_allowed'] = $normalized_settings['maximum_students'];
		}

		if ( array_key_exists( 'course_enrollment_period', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_course_enrollment_period', $normalized_settings['course_enrollment_period'] );
			$existing_tutor_settings['course_enrollment_period'] = $normalized_settings['course_enrollment_period'];
		}

		if ( array_key_exists( 'enrollment_starts_at', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_enrollment_starts_at', $normalized_settings['enrollment_starts_at'] );
			$existing_tutor_settings['enrollment_starts_at'] = $normalized_settings['enrollment_starts_at'];
		}

		if ( array_key_exists( 'enrollment_ends_at', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_enrollment_ends_at', $normalized_settings['enrollment_ends_at'] );
			$existing_tutor_settings['enrollment_ends_at'] = $normalized_settings['enrollment_ends_at'];
		}

		if ( array_key_exists( 'pause_enrollment', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_enrollment_status', $normalized_settings['pause_enrollment'] );
			$existing_tutor_settings['pause_enrollment'] = $normalized_settings['pause_enrollment'];
			$existing_tutor_settings['enrollment_status'] = $normalized_settings['pause_enrollment'];
		}

		if ( array_key_exists( 'course_prerequisites', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_course_prerequisites_ids', $normalized_settings['course_prerequisites'] );
			$existing_tutor_settings['course_prerequisites'] = $normalized_settings['course_prerequisites'];
		}

		if ( array_key_exists( 'schedule', $normalized_settings ) ) {
			$existing_tutor_settings['schedule'] = $normalized_settings['schedule'];
		}
	}

	/**
	 * Read maximum students with Tutor settings blob precedence.
	 *
	 * @since 1.14.3
	 * @param int   $post_id        Course post ID.
	 * @param array $tutor_settings Existing Tutor settings blob.
	 * @return int|null Maximum students value.
	 */
	private static function get_maximum_students_for_read( $post_id, array $tutor_settings ) {
		if ( array_key_exists( 'maximum_students', $tutor_settings ) ) {
			$value = $tutor_settings['maximum_students'];
			if ( null === $value || 0 === $value || is_numeric( $value ) ) {
				return null === $value ? null : (int) $value;
			}
		}

		$legacy_max = get_post_meta( $post_id, '_tutor_maximum_students', true );
		if ( '' === $legacy_max || null === $legacy_max ) {
			return null;
		}

		return (int) $legacy_max;
	}

	/**
	 * Read pause enrollment with Tutor settings blob precedence.
	 *
	 * @since 1.14.3
	 * @param int   $post_id        Course post ID.
	 * @param array $tutor_settings Existing Tutor settings blob.
	 * @return string Pause enrollment status.
	 */
	private static function get_pause_enrollment_for_read( $post_id, array $tutor_settings ) {
		if ( array_key_exists( 'pause_enrollment', $tutor_settings ) ) {
			$value = $tutor_settings['pause_enrollment'];
			if ( 'yes' === $value || 'no' === $value ) {
				return $value;
			}
		}

		$status = get_post_meta( $post_id, '_tutor_enrollment_status', true );
		if ( 'yes' === $status || 'no' === $status ) {
			return $status;
		}

		return 'no';
	}

	/**
	 * Normalize course duration while preserving explicit empty-string members.
	 *
	 * @since 1.14.3
	 * @param mixed $duration Raw duration payload.
	 * @return array{hours:int|string,minutes:int|string}
	 */
	public static function normalize_course_duration_for_save( $duration ) {
		if ( ! is_array( $duration ) ) {
			return array(
				'hours'   => 0,
				'minutes' => 0,
			);
		}

		return array(
			'hours'   => self::normalize_course_duration_value( $duration['hours'] ?? 0 ),
			'minutes' => self::normalize_course_duration_value( $duration['minutes'] ?? 0, 59 ),
		);
	}

	/**
	 * Normalize a duration member while preserving explicit empty strings.
	 *
	 * @since 1.14.3
	 * @param mixed    $value Raw duration member.
	 * @param int|null $max   Optional upper bound.
	 * @return int|string
	 */
	private static function normalize_course_duration_value( $value, $max = null ) {
		if ( '' === $value ) {
			return '';
		}

		$normalized = absint( $value );

		if ( is_int( $max ) ) {
			return min( $max, $normalized );
		}

		return $normalized;
	}
}
