<?php
/**
 * TutorPress lesson sync service.
 *
 * Provides the shared synchronization surface while behavior is moved out of
 * TutorPress_Lesson in small, verified steps.
 *
 * @package TutorPress
 * @since 1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TutorPress_Lesson_Sync_Service class.
 *
 * @since 1.14.3
 */
class TutorPress_Lesson_Sync_Service {

	/**
	 * Existing behavior callbacks owned by TutorPress_Lesson during migration.
	 *
	 * @since 1.14.3
	 * @var array<string,callable>
	 */
	private $callbacks;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 * @param array<string,callable> $callbacks Pass-through behavior callbacks.
	 */
	public function __construct( $callbacks ) {
		$this->callbacks = $callbacks;
	}

	/**
	 * Get lesson settings for REST API.
	 *
	 * @since 1.14.3
	 * @param array $post Post data.
	 * @return array Lesson settings.
	 */
	public function get_lesson_settings( $post ) {
		$post_id = $post['id'];

		$course_preview_available = tutorpress_feature_flags()->can_user_access_feature( 'course_preview' );

		$gutenberg_preview = get_post_meta( $post_id, '_lesson_is_preview', true );
		$tutor_preview     = get_post_meta( $post_id, '_is_preview', true );

		if ( $course_preview_available ) {
			$tutor_preview_bool     = ! empty( $tutor_preview );
			$gutenberg_preview_bool = ! empty( $gutenberg_preview );
			if ( $tutor_preview_bool !== $gutenberg_preview_bool ) {
				update_post_meta( $post_id, '_tutorpress_syncing_from_tutor', time() );
				update_post_meta( $post_id, '_lesson_is_preview', $tutor_preview_bool );
				delete_post_meta( $post_id, '_tutorpress_syncing_from_tutor' );
				$gutenberg_preview = $tutor_preview_bool;
			}
		}

		return [
			'video'          => [
				'source'              => get_post_meta( $post_id, '_lesson_video_source', true ),
				'source_video_id'     => (int) get_post_meta( $post_id, '_lesson_video_source_id', true ),
				'source_external_url' => get_post_meta( $post_id, '_lesson_video_external_url', true ),
				'source_youtube'      => get_post_meta( $post_id, '_lesson_video_youtube', true ),
				'source_vimeo'        => get_post_meta( $post_id, '_lesson_video_vimeo', true ),
				'source_embedded'     => get_post_meta( $post_id, '_lesson_video_embedded', true ),
				'source_shortcode'    => get_post_meta( $post_id, '_lesson_video_shortcode', true ),
				'poster'              => get_post_meta( $post_id, '_lesson_video_poster', true ),
			],
			'duration'       => [
				'hours'   => (int) get_post_meta( $post_id, '_lesson_video_duration_hours', true ),
				'minutes' => (int) get_post_meta( $post_id, '_lesson_video_duration_minutes', true ),
				'seconds' => (int) get_post_meta( $post_id, '_lesson_video_duration_seconds', true ),
			],
			'exercise_files' => array_map( 'intval', get_post_meta( $post_id, '_lesson_exercise_files', true ) ?: [] ),
			'lesson_preview' => [
				'enabled'         => (bool) $gutenberg_preview,
				'addon_available' => $course_preview_available,
			],
		];
	}

	/**
	 * Update lesson settings via REST API.
	 *
	 * @since 1.14.3
	 * @param array   $value New settings values.
	 * @param WP_Post $post  Post object.
	 * @return bool True on success.
	 */
	public function update_lesson_settings( $value, $post ) {
		$post_id = $post->ID;
		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( isset( $value['video'] ) && is_array( $value['video'] ) ) {
			$video = $value['video'];
			if ( isset( $video['source'] ) ) {
				update_post_meta( $post_id, '_lesson_video_source', $this->call( 'sanitize_video_source', array( $video['source'] ) ) );
			}
			if ( isset( $video['source_video_id'] ) ) {
				update_post_meta( $post_id, '_lesson_video_source_id', absint( $video['source_video_id'] ) );
			}
			if ( isset( $video['source_external_url'] ) ) {
				update_post_meta( $post_id, '_lesson_video_external_url', esc_url_raw( $video['source_external_url'] ) );
			}
			if ( isset( $video['source_youtube'] ) ) {
				update_post_meta( $post_id, '_lesson_video_youtube', sanitize_text_field( $video['source_youtube'] ) );
			}
			if ( isset( $video['source_vimeo'] ) ) {
				update_post_meta( $post_id, '_lesson_video_vimeo', sanitize_text_field( $video['source_vimeo'] ) );
			}
			if ( isset( $video['source_embedded'] ) ) {
				update_post_meta( $post_id, '_lesson_video_embedded', $this->call( 'sanitize_embedded_code', array( $video['source_embedded'] ) ) );
			}
			if ( isset( $video['source_shortcode'] ) ) {
				update_post_meta( $post_id, '_lesson_video_shortcode', sanitize_text_field( $video['source_shortcode'] ) );
			}
			if ( isset( $video['poster'] ) ) {
				update_post_meta( $post_id, '_lesson_video_poster', esc_url_raw( $video['poster'] ) );
			}
		}

		if ( isset( $value['duration'] ) && is_array( $value['duration'] ) ) {
			$duration = $value['duration'];
			if ( isset( $duration['hours'] ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_hours', absint( $duration['hours'] ) );
			}
			if ( isset( $duration['minutes'] ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_minutes', min( 59, absint( $duration['minutes'] ) ) );
			}
			if ( isset( $duration['seconds'] ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_seconds', min( 59, absint( $duration['seconds'] ) ) );
			}
		}

		if ( isset( $value['exercise_files'] ) ) {
			$ids = $this->call( 'sanitize_attachment_ids', array( $value['exercise_files'] ) );
			update_post_meta( $post_id, '_lesson_exercise_files', $ids );
			$this->call( 'sync_exercise_files', array( $post_id ) );
		}

		if ( isset( $value['lesson_preview']['enabled'] ) && tutorpress_feature_flags()->can_user_access_feature( 'course_preview' ) ) {
			$is_preview = rest_sanitize_boolean( $value['lesson_preview']['enabled'] );
			update_post_meta( $post_id, '_lesson_is_preview', $is_preview );
			$this->call( 'sync_lesson_preview', array( $post_id ) );
		}

		$this->call( 'sync_to_tutor_video_format', array( $post_id ) );

		return true;
	}

	/**
	 * Sanitize lesson settings.
	 *
	 * @since 1.14.3
	 * @param array $settings Lesson settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_lesson_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$sanitized = [];

		if ( isset( $settings['video'] ) && is_array( $settings['video'] ) ) {
			$video              = $settings['video'];
			$sanitized['video'] = [
				'source'              => sanitize_text_field( $video['source'] ?? '' ),
				'source_video_id'     => absint( $video['source_video_id'] ?? 0 ),
				'source_external_url' => esc_url_raw( $video['source_external_url'] ?? '' ),
				'source_youtube'      => sanitize_text_field( $video['source_youtube'] ?? '' ),
				'source_vimeo'        => sanitize_text_field( $video['source_vimeo'] ?? '' ),
				'source_embedded'     => wp_kses_post( $video['source_embedded'] ?? '' ),
				'source_shortcode'    => sanitize_text_field( $video['source_shortcode'] ?? '' ),
				'poster'              => esc_url_raw( $video['poster'] ?? '' ),
			];
		}

		if ( isset( $settings['duration'] ) && is_array( $settings['duration'] ) ) {
			$duration              = $settings['duration'];
			$sanitized['duration'] = [
				'hours'   => absint( $duration['hours'] ?? 0 ),
				'minutes' => min( 59, absint( $duration['minutes'] ?? 0 ) ),
				'seconds' => min( 59, absint( $duration['seconds'] ?? 0 ) ),
			];
		}

		if ( isset( $settings['exercise_files'] ) ) {
			$ids                         = $settings['exercise_files'];
			$sanitized['exercise_files'] = is_array( $ids ) ? array_map( 'absint', $ids ) : [];
		}

		if ( isset( $settings['lesson_preview'] ) && is_array( $settings['lesson_preview'] ) ) {
			$lp                          = $settings['lesson_preview'];
			$sanitized['lesson_preview'] = [
				'enabled'         => (bool) ( $lp['enabled'] ?? false ),
				'addon_available' => (bool) ( $lp['addon_available'] ?? false ),
			];
		}

		return $sanitized;
	}

	/**
	 * Handle Tutor LMS video meta updates.
	 *
	 * @since 1.14.3
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function handle_tutor_video_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->call( 'handle_tutor_video_meta_update', array( $meta_id, $post_id, $meta_key, $meta_value ) );
	}

	/**
	 * Handle Tutor LMS attachment meta updates.
	 *
	 * @since 1.14.3
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function handle_tutor_attachments_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->call( 'handle_tutor_attachments_meta_update', array( $meta_id, $post_id, $meta_key, $meta_value ) );
	}

	/**
	 * Handle Tutor Pro preview meta updates.
	 *
	 * @since 1.14.3
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function handle_tutor_preview_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->call( 'handle_tutor_preview_meta_update', array( $meta_id, $post_id, $meta_key, $meta_value ) );
	}

	/**
	 * Handle direct TutorPress lesson setting meta updates.
	 *
	 * @since 1.14.3
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function handle_lesson_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->call( 'handle_lesson_settings_update', array( $meta_id, $post_id, $meta_key, $meta_value ) );
	}

	/**
	 * Sync lesson fields on the save boundary.
	 *
	 * @since 1.14.3
	 * @param int     $post_id Lesson post ID.
	 * @param WP_Post $post    Lesson post object.
	 * @param bool    $update  Whether this is an existing post update.
	 * @return void
	 */
	public function sync_on_lesson_save( $post_id, $post, $update ) {
		$this->call( 'sync_on_lesson_save', array( $post_id, $post, $update ) );
	}

	/**
	 * Call a migration callback.
	 *
	 * @since 1.14.3
	 * @param string $name Callback name.
	 * @param array  $args Callback arguments.
	 * @return mixed Callback result.
	 */
	private function call( $name, $args ) {
		return call_user_func_array( $this->callbacks[ $name ], $args );
	}
}
