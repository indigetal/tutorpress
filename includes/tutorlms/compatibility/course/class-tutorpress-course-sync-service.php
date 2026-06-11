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
	 * Get canonical course settings by course ID.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Canonical course settings.
	 */
	public static function get_course_settings_for_course( $post_id ) {
		return TutorPress_Course::get_canonical_course_settings( $post_id );
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
	 * Get the raw Tutor LMS course settings blob.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Tutor LMS course settings blob.
	 */
	public static function get_raw_tutor_course_settings( $post_id ) {
		$tutor_settings = get_post_meta( $post_id, '_tutor_course_settings', true );

		if ( ! is_array( $tutor_settings ) ) {
			return array();
		}

		return $tutor_settings;
	}

	/**
	 * Save course settings through the canonical fan-out path.
	 *
	 * @since 1.14.3
	 * @param int   $post_id  Course post ID.
	 * @param array $settings Settings payload.
	 * @return array|false Canonical settings on success, false on failure.
	 */
	public static function save_canonical_course_settings( $post_id, array $settings ) {
		return TutorPress_Course::save_canonical_course_settings( $post_id, $settings );
	}

	/**
	 * Run a write while a TutorPress sync guard is set.
	 *
	 * @since 1.14.3
	 * @param int      $post_id  Course post ID.
	 * @param string   $meta_key Guard meta key.
	 * @param callable $callback Guarded callback.
	 * @return mixed Callback return value.
	 */
	public static function run_with_sync_guard( $post_id, $meta_key, $callback ) {
		update_post_meta( $post_id, $meta_key, true );

		try {
			return $callback();
		} finally {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Refresh the compatibility shadow from canonical course settings.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return void
	 */
	public static function refresh_course_settings_shadow_from_canonical( $post_id ) {
		self::run_with_sync_guard(
			$post_id,
			'_tutorpress_syncing_from_tutor',
			static function () use ( $post_id ) {
				update_post_meta( $post_id, 'course_settings', TutorPress_Course::get_canonical_course_settings( $post_id ) );
			}
		);
	}

	/**
	 * Refresh shadow storage after a canonical TutorPress save.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Canonical settings written to shadow storage.
	 */
	public static function refresh_course_settings_shadow_after_canonical_save( $post_id ) {
		update_post_meta( $post_id, '_tutorpress_course_settings_last_sync', time() );

		$shadow_settings = TutorPress_Course::get_canonical_course_settings( $post_id );
		update_post_meta( $post_id, 'course_settings', $shadow_settings );

		return $shadow_settings;
	}

	/**
	 * Refresh shadow storage after direct Tutor LMS field writes.
	 *
	 * @since 1.14.3
	 * @param int    $post_id  Course post ID.
	 * @param string $meta_key Updated meta key.
	 * @return void
	 */
	public function handle_tutor_individual_field_update( $post_id, $meta_key ) {
		$tutor_fields = array(
			'_tutor_course_level', '_tutor_is_public_course', '_tutor_enable_qa', '_course_duration',
			'_tutor_course_prerequisites_ids', '_tutor_maximum_students', '_tutor_enrollment_status',
			'_tutor_course_enrollment_period', '_tutor_enrollment_starts_at', '_tutor_enrollment_ends_at',
			'_tutor_course_material_includes', '_tutor_course_price_type', 'tutor_course_price', 'tutor_course_sale_price',
			'tutor_course_selling_option',
		);

		if ( ! $this->sync_context->is_direct_course_meta_update( $post_id, $meta_key, $tutor_fields ) ) {
			return;
		}

		if ( $this->sync_context->is_syncing_to_tutor( $post_id ) ) {
			return;
		}

		self::refresh_course_settings_shadow_from_canonical( $post_id );
	}

	/**
	 * Refresh shadow storage after direct Tutor LMS settings blob writes.
	 *
	 * @since 1.14.3
	 * @param int    $post_id  Course post ID.
	 * @param string $meta_key Updated meta key.
	 * @return void
	 */
	public function handle_tutor_course_settings_update( $post_id, $meta_key ) {
		if ( ! $this->sync_context->is_direct_course_meta_update( $post_id, $meta_key, array( '_tutor_course_settings' ) ) ) {
			return;
		}

		if ( $this->sync_context->is_syncing_to_tutor( $post_id ) ) {
			return;
		}

		if ( $this->sync_context->is_recent_post_meta_timestamp( $post_id, '_tutorpress_tutor_settings_last_sync' ) ) {
			return;
		}

		update_post_meta( $post_id, '_tutorpress_tutor_settings_last_sync', time() );
		self::refresh_course_settings_shadow_from_canonical( $post_id );
	}

	/**
	 * Refresh shadow storage after upstream course save hooks have run.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return void
	 */
	public function sync_on_course_save( $post_id ) {
		if ( $this->sync_context->should_skip_save_boundary_sync( $post_id ) ) {
			return;
		}

		self::refresh_course_settings_shadow_from_canonical( $post_id );
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
	 * Read intro video settings with _video as the final canonical overlay.
	 *
	 * @since 1.14.3
	 * @param int   $post_id        Course post ID.
	 * @param array $tutor_settings Existing Tutor settings blob.
	 * @return array Intro video settings.
	 */
	public static function get_intro_video_settings( $post_id, array $tutor_settings ) {
		$intro_video = get_post_meta( $post_id, '_video', true );

		return array(
			'intro_video' => array_merge(
				self::get_default_intro_video(),
				$tutor_settings['featured_video'] ?? array(),
				$tutor_settings['intro_video'] ?? array(),
				is_array( $intro_video ) ? $intro_video : array()
			),
		);
	}

	/**
	 * Normalize intro video settings for canonical saves.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Normalized settings payload.
	 */
	public static function normalize_intro_video_for_save( array $settings ) {
		$normalized = array();

		if ( array_key_exists( 'intro_video', $settings ) ) {
			$intro_video = is_array( $settings['intro_video'] ) ? $settings['intro_video'] : array();
			$normalized['intro_video'] = array(
				'source'              => sanitize_text_field( (string) ( $intro_video['source'] ?? '' ) ),
				'source_video_id'     => absint( $intro_video['source_video_id'] ?? 0 ),
				'source_youtube'      => sanitize_text_field( (string) ( $intro_video['source_youtube'] ?? '' ) ),
				'source_vimeo'        => sanitize_text_field( (string) ( $intro_video['source_vimeo'] ?? '' ) ),
				'source_external_url' => sanitize_text_field( (string) ( $intro_video['source_external_url'] ?? '' ) ),
				'source_embedded'     => sanitize_text_field( (string) ( $intro_video['source_embedded'] ?? '' ) ),
				'source_shortcode'    => sanitize_text_field( (string) ( $intro_video['source_shortcode'] ?? '' ) ),
				'poster'              => sanitize_text_field( (string) ( $intro_video['poster'] ?? '' ) ),
			);
		}

		return $normalized;
	}

	/**
	 * Sanitize intro video settings for direct shadow meta writes.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Sanitized settings payload.
	 */
	public static function sanitize_intro_video( array $settings ) {
		if ( ! isset( $settings['intro_video'] ) ) {
			return array();
		}

		$sanitized = self::normalize_intro_video_for_save( $settings );
		$allowed_sources = array( '', 'html5', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode' );

		if ( ! in_array( $sanitized['intro_video']['source'], $allowed_sources, true ) ) {
			$sanitized['intro_video']['source'] = '';
		}

		switch ( $sanitized['intro_video']['source'] ) {
			case 'html5':
				if ( $sanitized['intro_video']['source_video_id'] <= 0 ) {
					$sanitized['intro_video']['source'] = '';
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				} else {
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array( 'source_video_id' ) );
				}
				break;
			case 'youtube':
				if ( '' === $sanitized['intro_video']['source_youtube'] ) {
					$sanitized['intro_video']['source'] = '';
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				} else {
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array( 'source_youtube' ) );
				}
				break;
			case 'vimeo':
				if ( '' === $sanitized['intro_video']['source_vimeo'] ) {
					$sanitized['intro_video']['source'] = '';
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				} else {
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array( 'source_vimeo' ) );
				}
				break;
			case 'external_url':
				if ( '' === $sanitized['intro_video']['source_external_url'] ) {
					$sanitized['intro_video']['source'] = '';
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				} else {
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array( 'source_external_url' ) );
				}
				break;
			case 'embedded':
				if ( '' === $sanitized['intro_video']['source_embedded'] ) {
					$sanitized['intro_video']['source'] = '';
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				} else {
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array( 'source_embedded' ) );
				}
				break;
			case 'shortcode':
				if ( '' === $sanitized['intro_video']['source_shortcode'] ) {
					$sanitized['intro_video']['source'] = '';
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				} else {
					self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array( 'source_shortcode' ) );
				}
				break;
			default:
				self::clear_intro_video_non_applicable_fields( $sanitized['intro_video'], array() );
				break;
		}

		return $sanitized;
	}

	/**
	 * Save intro video settings.
	 *
	 * @since 1.14.3
	 * @param int   $post_id                 Course post ID.
	 * @param array $normalized_settings     Normalized settings payload.
	 * @param array $existing_tutor_settings Existing Tutor settings blob.
	 * @return void
	 */
	public static function save_intro_video( $post_id, array $normalized_settings, array &$existing_tutor_settings ) {
		if ( array_key_exists( 'intro_video', $normalized_settings ) ) {
			update_post_meta( $post_id, '_video', $normalized_settings['intro_video'] );
			$existing_tutor_settings['intro_video'] = $normalized_settings['intro_video'];
		}
	}

	/**
	 * Read attachment settings from the TutorPress editor mirror.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Attachment settings.
	 */
	public static function get_attachment_settings( $post_id ) {
		return array(
			'attachments' => get_post_meta( $post_id, '_tutor_course_attachments', true ) ?: array(),
		);
	}

	/**
	 * Normalize attachment IDs for canonical saves.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Normalized settings payload.
	 */
	public static function normalize_attachments_for_save( array $settings ) {
		if ( ! array_key_exists( 'attachments', $settings ) ) {
			return array();
		}

		return array(
			'attachments' => is_array( $settings['attachments'] ) ? array_map( 'absint', $settings['attachments'] ) : array(),
		);
	}

	/**
	 * Sanitize attachment IDs for direct shadow meta writes.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Sanitized settings payload.
	 */
	public static function sanitize_attachments( array $settings ) {
		if ( ! isset( $settings['attachments'] ) ) {
			return array();
		}

		return array(
			'attachments' => is_array( $settings['attachments'] ) ? array_map( 'absint', $settings['attachments'] ) : array(),
		);
	}

	/**
	 * Save attachments to TutorPress and Tutor LMS attachment mirrors.
	 *
	 * @since 1.14.3
	 * @param int   $post_id                 Course post ID.
	 * @param array $normalized_settings     Normalized settings payload.
	 * @param array $existing_tutor_settings Existing Tutor settings blob.
	 * @return void
	 */
	public static function save_attachments( $post_id, array $normalized_settings, array &$existing_tutor_settings ) {
		if ( array_key_exists( 'attachments', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_course_attachments', $normalized_settings['attachments'] );
			update_post_meta( $post_id, '_tutor_attachments', $normalized_settings['attachments'] );
			$existing_tutor_settings['attachments'] = $normalized_settings['attachments'];
		}
	}

	/**
	 * Read pricing, selling option, and product link settings.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Pricing and product settings.
	 */
	public static function get_pricing_product_settings( $post_id ) {
		$sale_price = get_post_meta( $post_id, 'tutor_course_sale_price', true );

		return array(
			'is_free'                => 'free' === get_post_meta( $post_id, '_tutor_course_price_type', true ),
			'pricing_model'          => get_post_meta( $post_id, '_tutor_course_price_type', true ) ?: 'free',
			'price'                  => (float) get_post_meta( $post_id, 'tutor_course_price', true ) ?: 0,
			'sale_price'             => ( '' === $sale_price || null === $sale_price ) ? null : (float) $sale_price,
			'selling_option'         => get_post_meta( $post_id, 'tutor_course_selling_option', true ) ?: 'one_time',
			'woocommerce_product_id' => TutorPress_Addon_Checker::is_woocommerce_monetization() ? get_post_meta( $post_id, '_tutor_course_product_id', true ) ?: '' : '',
			'edd_product_id'         => TutorPress_Addon_Checker::is_edd_monetization() ? get_post_meta( $post_id, '_tutor_course_product_id', true ) ?: '' : '',
			'subscription_enabled'   => 'subscription' === get_post_meta( $post_id, 'tutor_course_selling_option', true ),
		);
	}

	/**
	 * Normalize pricing, selling option, and product link settings.
	 *
	 * @since 1.14.3
	 * @param array $settings   Raw settings payload.
	 * @param array $normalized Existing normalized settings payload.
	 * @return array Normalized settings payload.
	 */
	public static function normalize_pricing_product_for_save( array $settings, array $normalized ) {
		if ( array_key_exists( 'pricing_model', $settings ) ) {
			$pricing_model = sanitize_text_field( (string) $settings['pricing_model'] );
			$normalized['pricing_model'] = 'free' === $pricing_model ? 'free' : 'paid';
		}

		if ( array_key_exists( 'price', $settings ) ) {
			$normalized['price'] = round( max( 0, (float) $settings['price'] ), 2 );
		}

		if ( array_key_exists( 'sale_price', $settings ) ) {
			$normalized['sale_price'] = ( null === $settings['sale_price'] || '' === $settings['sale_price'] ) ? null : round( max( 0, (float) $settings['sale_price'] ), 2 );
		}

		if ( array_key_exists( 'selling_option', $settings ) ) {
			$selling_option = sanitize_text_field( (string) $settings['selling_option'] );
			$valid_options = array( 'one_time', 'subscription', 'both', 'membership', 'all' );
			$normalized['selling_option'] = in_array( $selling_option, $valid_options, true ) ? $selling_option : 'one_time';
		} elseif ( array_key_exists( 'subscription_enabled', $settings ) ) {
			$normalized['selling_option'] = ! empty( $settings['subscription_enabled'] ) ? 'subscription' : 'one_time';
		}

		if ( array_key_exists( 'woocommerce_product_id', $settings ) ) {
			$normalized['woocommerce_product_id'] = sanitize_text_field( (string) $settings['woocommerce_product_id'] );
		}

		if ( array_key_exists( 'edd_product_id', $settings ) ) {
			$normalized['edd_product_id'] = sanitize_text_field( (string) $settings['edd_product_id'] );
		}

		if ( array_key_exists( 'is_public_course', $normalized ) && array_key_exists( 'pricing_model', $normalized ) && $normalized['is_public_course'] && 'paid' === $normalized['pricing_model'] ) {
			$normalized['pricing_model'] = 'free';
			$normalized['price'] = 0;
			$normalized['sale_price'] = 0;
		}

		return $normalized;
	}

	/**
	 * Sanitize pricing, selling option, and product link settings for shadow writes.
	 *
	 * @since 1.14.3
	 * @param array $settings  Raw settings payload.
	 * @param array $sanitized Existing sanitized settings payload.
	 * @return array Sanitized settings payload.
	 */
	public static function sanitize_pricing_product( array $settings, array $sanitized ) {
		if ( isset( $settings['pricing_model'] ) ) {
			$sanitized['pricing_model'] = in_array( $settings['pricing_model'], array( 'free', 'paid' ), true ) ? $settings['pricing_model'] : 'free';
		}

		$is_public = isset( $sanitized['is_public_course'] ) ? $sanitized['is_public_course'] : ( isset( $settings['is_public_course'] ) ? $settings['is_public_course'] : false );
		$pricing_model = isset( $sanitized['pricing_model'] ) ? $sanitized['pricing_model'] : ( isset( $settings['pricing_model'] ) ? $settings['pricing_model'] : 'free' );
		if ( $is_public && 'paid' === $pricing_model ) {
			$sanitized['pricing_model'] = 'free';
			$sanitized['is_free'] = true;
			$sanitized['price'] = 0;
			$sanitized['sale_price'] = 0;
		}

		if ( isset( $settings['price'] ) ) {
			$sanitized['price'] = round( max( 0, (float) $settings['price'] ), 2 );
		}

		if ( array_key_exists( 'sale_price', $settings ) ) {
			$sanitized['sale_price'] = ( null === $settings['sale_price'] || '' === $settings['sale_price'] ) ? null : round( max( 0, (float) $settings['sale_price'] ), 2 );
		}

		if ( isset( $settings['selling_option'] ) ) {
			$sanitized['selling_option'] = in_array( $settings['selling_option'], array( 'one_time', 'subscription', 'both', 'membership', 'all' ), true ) ? $settings['selling_option'] : 'one_time';
		}

		if ( isset( $settings['woocommerce_product_id'] ) ) {
			$sanitized['woocommerce_product_id'] = sanitize_text_field( $settings['woocommerce_product_id'] );
		}

		if ( isset( $settings['edd_product_id'] ) ) {
			$sanitized['edd_product_id'] = sanitize_text_field( $settings['edd_product_id'] );
		}

		return $sanitized;
	}

	/**
	 * Save pricing, selling option, and product link settings.
	 *
	 * @since 1.14.3
	 * @param int   $post_id                 Course post ID.
	 * @param array $normalized_settings     Normalized settings payload.
	 * @param array $existing_tutor_settings Existing Tutor settings blob.
	 * @return void
	 */
	public static function save_pricing_product( $post_id, array $normalized_settings, array &$existing_tutor_settings ) {
		if ( array_key_exists( 'pricing_model', $normalized_settings ) ) {
			update_post_meta( $post_id, '_tutor_course_price_type', 'free' === $normalized_settings['pricing_model'] ? 'free' : 'paid' );
		}

		if ( array_key_exists( 'price', $normalized_settings ) ) {
			update_post_meta( $post_id, 'tutor_course_price', (float) $normalized_settings['price'] );
		}

		if ( array_key_exists( 'sale_price', $normalized_settings ) ) {
			update_post_meta( $post_id, 'tutor_course_sale_price', null === $normalized_settings['sale_price'] ? '' : (float) $normalized_settings['sale_price'] );
		}

		if ( array_key_exists( 'selling_option', $normalized_settings ) ) {
			update_post_meta( $post_id, 'tutor_course_selling_option', $normalized_settings['selling_option'] );
		}

		if ( array_key_exists( 'woocommerce_product_id', $normalized_settings ) || array_key_exists( 'edd_product_id', $normalized_settings ) ) {
			$active_product_id = '';
			if ( array_key_exists( 'woocommerce_product_id', $normalized_settings ) && TutorPress_Addon_Checker::is_woocommerce_monetization() ) {
				$active_product_id = $normalized_settings['woocommerce_product_id'];
			} elseif ( array_key_exists( 'edd_product_id', $normalized_settings ) && TutorPress_Addon_Checker::is_edd_monetization() ) {
				$active_product_id = $normalized_settings['edd_product_id'];
			}
			update_post_meta( $post_id, '_tutor_course_product_id', $active_product_id );
		}

		foreach ( array( 'pricing_model', 'price', 'sale_price', 'selling_option', 'woocommerce_product_id', 'edd_product_id' ) as $key ) {
			if ( array_key_exists( $key, $normalized_settings ) ) {
				$existing_tutor_settings[ $key ] = $normalized_settings[ $key ];
			}
		}
	}

	/**
	 * Read co-instructor settings from Tutor LMS usermeta.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Instructor settings.
	 */
	public static function get_instructor_settings( $post_id ) {
		$instructor_ids = self::get_course_instructor_ids_from_usermeta( $post_id );

		return array(
			'instructors'            => $instructor_ids,
			'additional_instructors' => $instructor_ids,
		);
	}

	/**
	 * Read Tutor LMS co-instructor IDs from upstream usermeta.
	 *
	 * @since 1.14.3
	 * @param int $post_id Course post ID.
	 * @return array Co-instructor user IDs.
	 */
	public static function get_course_instructor_ids_from_usermeta( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'courses' !== $post->post_type ) {
			return array();
		}

		$instructor_ids = get_users(
			array(
				'fields'     => 'ID',
				'meta_key'   => '_tutor_instructor_course_id',
				'meta_value' => $post_id,
			)
		);

		$instructor_ids = array_values( array_unique( array_map( 'absint', $instructor_ids ) ) );

		return array_values(
			array_filter(
				$instructor_ids,
				static function ( $instructor_id ) use ( $post ) {
					return $instructor_id && (int) $post->post_author !== (int) $instructor_id;
				}
			)
		);
	}

	/**
	 * Normalize co-instructor settings for canonical saves.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Normalized settings payload.
	 */
	public static function normalize_instructors_for_save( array $settings ) {
		$normalized = array();

		if ( array_key_exists( 'instructors', $settings ) ) {
			$normalized['instructors'] = is_array( $settings['instructors'] ) ? array_map( 'absint', $settings['instructors'] ) : array();
		}

		if ( array_key_exists( 'additional_instructors', $settings ) ) {
			$normalized['additional_instructors'] = is_array( $settings['additional_instructors'] ) ? array_map( 'absint', $settings['additional_instructors'] ) : array();
		}

		return $normalized;
	}

	/**
	 * Sanitize co-instructor settings for shadow writes.
	 *
	 * @since 1.14.3
	 * @param array $settings Raw settings payload.
	 * @return array Sanitized settings payload.
	 */
	public static function sanitize_instructors( array $settings ) {
		$sanitized = array();

		if ( isset( $settings['instructors'] ) && is_array( $settings['instructors'] ) ) {
			$sanitized['instructors'] = array_map( 'absint', $settings['instructors'] );
		}

		if ( isset( $settings['additional_instructors'] ) && is_array( $settings['additional_instructors'] ) ) {
			$sanitized['additional_instructors'] = array_map( 'absint', $settings['additional_instructors'] );
		}

		return $sanitized;
	}

	/**
	 * Save co-instructor settings and Tutor LMS usermeta mirrors.
	 *
	 * @since 1.14.3
	 * @param int   $post_id                 Course post ID.
	 * @param array $normalized_settings     Normalized settings payload.
	 * @param array $existing_tutor_settings Existing Tutor settings blob.
	 * @return void
	 */
	public static function save_instructors( $post_id, array $normalized_settings, array &$existing_tutor_settings ) {
		$resolved_instructor_ids = self::resolve_instructor_ids_for_save( $normalized_settings );
		if ( null === $resolved_instructor_ids ) {
			return;
		}

		update_post_meta( $post_id, '_tutor_course_instructors', $resolved_instructor_ids );
		self::sync_instructors_to_tutor_lms( $post_id, $resolved_instructor_ids );
		$existing_tutor_settings['instructors'] = $resolved_instructor_ids;
		$existing_tutor_settings['additional_instructors'] = $resolved_instructor_ids;
	}

	/**
	 * Validate route instructor IDs against the current permission rules.
	 *
	 * @since 1.14.3
	 * @param mixed $instructor_ids Raw instructor IDs.
	 * @return array Valid instructor IDs.
	 */
	public static function validate_course_instructor_ids( $instructor_ids ) {
		if ( ! is_array( $instructor_ids ) ) {
			$instructor_ids = array();
		}

		$valid_instructor_ids = array();
		foreach ( $instructor_ids as $instructor_id ) {
			$user = get_user_by( 'id', $instructor_id );
			if ( $user && ( user_can( $instructor_id, 'edit_posts' ) || user_can( $instructor_id, 'tutor_instructor' ) ) ) {
				$valid_instructor_ids[] = $instructor_id;
			}
		}

		return $valid_instructor_ids;
	}

	/**
	 * Save route-validated co-instructors with the existing fallback behavior.
	 *
	 * @since 1.14.3
	 * @param int   $course_id      Course post ID.
	 * @param array $instructor_ids Valid instructor IDs.
	 * @return bool Whether the save succeeded.
	 */
	public static function save_route_instructors( $course_id, array $instructor_ids ) {
		$result = update_post_meta( $course_id, '_tutor_course_instructors', $instructor_ids );

		if ( false === $result ) {
			delete_post_meta( $course_id, '_tutor_course_instructors' );
			$result = add_post_meta( $course_id, '_tutor_course_instructors', $instructor_ids, true );
		}

		if ( false === $result ) {
			return false;
		}

		try {
			self::sync_instructors_to_tutor_lms( $course_id, $instructor_ids );
		} catch ( Exception $e ) {
			// Preserve the route behavior: instructor meta save succeeds even if usermeta sync fails.
		}

		return true;
	}

	/**
	 * Save the REST after-insert intro video subset behavior.
	 *
	 * @since 1.14.3
	 * @param int   $post_id  Course post ID.
	 * @param array $settings REST course settings payload.
	 * @return void
	 */
	public function save_rest_after_insert_intro_video( $post_id, array $settings ) {
		if ( ! $this->sync_context->has_rest_after_insert_settings_key( $settings, 'intro_video' ) ) {
			return;
		}

		$intro_video = $settings['intro_video'];
		if ( is_array( $intro_video ) ) {
			update_post_meta( $post_id, '_video', $intro_video );
		}
	}

	/**
	 * Save the REST after-insert attachment subset behavior.
	 *
	 * @since 1.14.3
	 * @param int   $post_id  Course post ID.
	 * @param array $settings REST course settings payload.
	 * @return void
	 */
	public function save_rest_after_insert_attachments( $post_id, array $settings ) {
		if ( ! $this->sync_context->has_rest_after_insert_settings_key( $settings, 'attachments' ) ) {
			return;
		}

		$attachment_ids = is_array( $settings['attachments'] ) ? array_map( 'absint', $settings['attachments'] ) : array();
		update_post_meta( $post_id, '_tutor_course_attachments', $attachment_ids );
		update_post_meta( $post_id, '_tutor_attachments', $attachment_ids );
	}

	/**
	 * Mirror external Tutor LMS attachment updates into the TutorPress editor mirror.
	 *
	 * @since 1.14.3
	 * @param int    $post_id    Course post ID.
	 * @param string $meta_key   Updated meta key.
	 * @param mixed  $meta_value Updated meta value.
	 * @return void
	 */
	public function handle_tutor_attachments_meta_update( $post_id, $meta_key, $meta_value ) {
		if ( ! $this->sync_context->is_direct_course_meta_update( $post_id, $meta_key, array( '_tutor_attachments' ) ) ) {
			return;
		}

		if ( $this->sync_context->is_recent_post_meta_timestamp( $post_id, '_tutorpress_attachments_last_sync' ) ) {
			return;
		}

		update_post_meta( $post_id, '_tutorpress_attachments_last_sync', time() );
		$attachment_ids = is_array( $meta_value ) ? array_map( 'absint', $meta_value ) : array();
		update_post_meta( $post_id, '_tutor_course_attachments', $attachment_ids );
	}

	/**
	 * Mirror external Tutor LMS attachment deletions into the TutorPress editor mirror.
	 *
	 * @since 1.14.3
	 * @param int    $post_id  Course post ID.
	 * @param string $meta_key Deleted meta key.
	 * @return void
	 */
	public function handle_tutor_attachments_meta_delete( $post_id, $meta_key ) {
		if ( ! $this->sync_context->is_direct_course_meta_update( $post_id, $meta_key, array( '_tutor_attachments' ) ) ) {
			return;
		}

		if ( $this->sync_context->is_recent_post_meta_timestamp( $post_id, '_tutorpress_attachments_last_sync' ) ) {
			return;
		}

		update_post_meta( $post_id, '_tutorpress_attachments_last_sync', time() );
		update_post_meta( $post_id, '_tutor_course_attachments', array() );
	}

	/**
	 * Resolve the instructor ID list for canonical saves.
	 *
	 * When both instructor keys are present, additional_instructors wins.
	 *
	 * @since 1.14.3
	 * @param array $normalized_settings Normalized settings payload.
	 * @return array|null Resolved instructor IDs, or null when omitted.
	 */
	private static function resolve_instructor_ids_for_save( array $normalized_settings ) {
		$resolved_instructor_ids = null;

		if ( array_key_exists( 'instructors', $normalized_settings ) ) {
			$resolved_instructor_ids = $normalized_settings['instructors'];
		}

		if ( array_key_exists( 'additional_instructors', $normalized_settings ) ) {
			$resolved_instructor_ids = $normalized_settings['additional_instructors'];
		}

		return $resolved_instructor_ids;
	}

	/**
	 * Sync co-instructors to Tutor LMS compatibility usermeta.
	 *
	 * @since 1.14.3
	 * @param int   $course_id      Course post ID.
	 * @param array $instructor_ids Instructor user IDs.
	 * @return void
	 */
	public static function sync_instructors_to_tutor_lms( $course_id, array $instructor_ids ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->usermeta,
			array(
				'meta_key'   => '_tutor_instructor_course_id',
				'meta_value' => $course_id,
			)
		);

		foreach ( $instructor_ids as $instructor_id ) {
			add_user_meta( $instructor_id, '_tutor_instructor_course_id', $course_id );
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
	 * Get the default intro video shape.
	 *
	 * @since 1.14.3
	 * @return array Default intro video data.
	 */
	private static function get_default_intro_video() {
		return array(
			'source'              => '',
			'source_video_id'     => 0,
			'source_youtube'      => '',
			'source_vimeo'        => '',
			'source_external_url' => '',
			'source_embedded'     => '',
			'source_shortcode'    => '',
			'poster'              => '',
		);
	}

	/**
	 * Clear intro video fields outside the selected source.
	 *
	 * @since 1.14.3
	 * @param array $intro_video Intro video data.
	 * @param array $keep_keys   Keys to keep.
	 * @return void
	 */
	private static function clear_intro_video_non_applicable_fields( array &$intro_video, array $keep_keys ) {
		$keys = array( 'source_video_id', 'source_youtube', 'source_vimeo', 'source_external_url', 'source_embedded', 'source_shortcode' );

		foreach ( $keys as $key ) {
			if ( in_array( $key, $keep_keys, true ) ) {
				continue;
			}

			$intro_video[ $key ] = 'source_video_id' === $key ? 0 : '';
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
