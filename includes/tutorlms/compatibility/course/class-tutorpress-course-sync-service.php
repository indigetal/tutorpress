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
