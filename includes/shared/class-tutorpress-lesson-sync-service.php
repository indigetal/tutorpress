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
	 * Lesson sync context and intent helper.
	 *
	 * @since 1.14.3
	 * @var TutorPress_Lesson_Sync_Context
	 */
	private $sync_context;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 * @param array<string,callable>            $callbacks    Pass-through behavior callbacks.
	 * @param TutorPress_Lesson_Sync_Context    $sync_context Lesson sync context helper.
	 */
	public function __construct( $callbacks, $sync_context ) {
		$this->callbacks    = $callbacks;
		$this->sync_context = $sync_context;
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
				update_post_meta( $post_id, '_lesson_video_source', $this->sanitize_video_source( $video['source'] ) );
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
				update_post_meta( $post_id, '_lesson_video_embedded', $this->sanitize_embedded_code( $video['source_embedded'] ) );
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

		$this->sync_to_tutor_video_format( $post_id );

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
		if ( '_video' !== $meta_key || 'lesson' !== get_post_type( $post_id ) ) {
			return;
		}

		$our_last_update = get_post_meta( $post_id, '_tutorpress_video_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return;
		}

		$this->sync_from_tutor_video_format( $post_id, $meta_value );
	}

	/**
	 * Sync Tutor LMS video data into TutorPress lesson video mirror meta.
	 *
	 * @since 1.14.3
	 * @param int        $post_id    Lesson post ID.
	 * @param array|null $video_data Tutor LMS video data.
	 * @return void
	 */
	private function sync_from_tutor_video_format( $post_id, $video_data = null ) {
		if ( ! $video_data ) {
			$video_data = get_post_meta( $post_id, '_video', true );
		}

		if ( empty( $video_data ) || ! is_array( $video_data ) ) {
			return;
		}

		update_post_meta( $post_id, '_tutorpress_video_last_sync', time() );

		if ( array_key_exists( 'source', $video_data ) ) {
			$incoming_source = sanitize_text_field( $video_data['source'] );
			if ( '' === $incoming_source || '-1' === $incoming_source ) {
				delete_post_meta( $post_id, '_video' );
				$this->clear_lesson_video_mirror_meta( $post_id );
				return;
			}
		}

		if ( isset( $video_data['source'] ) ) {
			update_post_meta( $post_id, '_lesson_video_source', $this->sanitize_video_source( $video_data['source'] ) );
		}
		if ( isset( $video_data['source_video_id'] ) ) {
			update_post_meta( $post_id, '_lesson_video_source_id', absint( $video_data['source_video_id'] ) );
		}
		if ( isset( $video_data['source_external_url'] ) ) {
			update_post_meta( $post_id, '_lesson_video_external_url', esc_url_raw( $video_data['source_external_url'] ) );
		}
		if ( isset( $video_data['source_html5'] ) ) {
			update_post_meta( $post_id, '_lesson_video_external_url', esc_url_raw( $video_data['source_html5'] ) );
		} elseif ( isset( $video_data['source_video_id'] ) && $video_data['source_video_id'] ) {
			$attachment_url = wp_get_attachment_url( $video_data['source_video_id'] );
			if ( $attachment_url ) {
				update_post_meta( $post_id, '_lesson_video_external_url', $attachment_url );
			}
		}
		if ( isset( $video_data['source_youtube'] ) ) {
			update_post_meta( $post_id, '_lesson_video_youtube', sanitize_text_field( $video_data['source_youtube'] ) );
		}
		if ( isset( $video_data['source_vimeo'] ) ) {
			update_post_meta( $post_id, '_lesson_video_vimeo', sanitize_text_field( $video_data['source_vimeo'] ) );
		}
		if ( isset( $video_data['source_embedded'] ) ) {
			update_post_meta( $post_id, '_lesson_video_embedded', $this->sanitize_embedded_code( $video_data['source_embedded'] ) );
		}
		if ( isset( $video_data['source_shortcode'] ) ) {
			update_post_meta( $post_id, '_lesson_video_shortcode', sanitize_text_field( $video_data['source_shortcode'] ) );
		}
		if ( isset( $video_data['poster'] ) ) {
			update_post_meta( $post_id, '_lesson_video_poster', esc_url_raw( $video_data['poster'] ) );
		}
		if ( isset( $video_data['runtime'] ) && is_array( $video_data['runtime'] ) ) {
			$runtime            = TutorPress_Lesson_Video_Duration::normalize_duration( $video_data['runtime'] );
			$runtime['minutes'] = min( 59, $runtime['minutes'] );
			$runtime['seconds'] = min( 59, $runtime['seconds'] );

			if ( TutorPress_Lesson_Video_Duration::has_non_zero_duration( $runtime ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_hours', $runtime['hours'] );
				update_post_meta( $post_id, '_lesson_video_duration_minutes', $runtime['minutes'] );
				update_post_meta( $post_id, '_lesson_video_duration_seconds', $runtime['seconds'] );

				$video_data['runtime']  = $runtime;
				$video_data['playtime'] = TutorPress_Lesson_Video_Duration::format_playtime( $runtime );
				update_post_meta( $post_id, '_video', $video_data );
				return;
			}

			$existing_duration = TutorPress_Lesson_Video_Duration::get_lesson_duration( $post_id );
			if ( TutorPress_Lesson_Video_Duration::has_non_zero_duration( $existing_duration ) ) {
				$video_data['runtime']  = $existing_duration;
				$video_data['playtime'] = TutorPress_Lesson_Video_Duration::format_playtime( $existing_duration );
				update_post_meta( $post_id, '_video', $video_data );
				return;
			}

			update_post_meta( $post_id, '_lesson_video_duration_hours', $runtime['hours'] );
			update_post_meta( $post_id, '_lesson_video_duration_minutes', $runtime['minutes'] );
			update_post_meta( $post_id, '_lesson_video_duration_seconds', $runtime['seconds'] );
		}
	}

	/**
	 * Sync TutorPress lesson video mirror meta into Tutor LMS video data.
	 *
	 * @since 1.14.3
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	public function sync_to_tutor_video_format( $post_id ) {
		update_post_meta( $post_id, '_tutorpress_video_last_sync', time() );

		if ( $this->sync_context->is_frontend_builder_no_video_save() ) {
			delete_post_meta( $post_id, '_video' );
			$this->clear_lesson_video_mirror_meta( $post_id );
			return;
		}

		$source          = get_post_meta( $post_id, '_lesson_video_source', true );
		$source_video_id = (int) get_post_meta( $post_id, '_lesson_video_source_id', true );
		$external_url    = get_post_meta( $post_id, '_lesson_video_external_url', true );
		$youtube         = get_post_meta( $post_id, '_lesson_video_youtube', true );
		$vimeo           = get_post_meta( $post_id, '_lesson_video_vimeo', true );
		$embedded        = get_post_meta( $post_id, '_lesson_video_embedded', true );
		$shortcode       = get_post_meta( $post_id, '_lesson_video_shortcode', true );
		$poster          = get_post_meta( $post_id, '_lesson_video_poster', true );
		$duration        = TutorPress_Lesson_Video_Duration::get_lesson_duration( $post_id );

		if ( empty( $source ) || '-1' === $source ) {
			delete_post_meta( $post_id, '_video' );
			return;
		}

		$video_data               = array( 'source' => $source );
		$has_required_source_data = false;

		if ( 'html5' === $source && $source_video_id ) {
			$video_data['source_video_id'] = $source_video_id;
			$attachment_url                = wp_get_attachment_url( $source_video_id );
			if ( $attachment_url ) {
				$video_data['source_html5'] = $attachment_url;
			}
			$has_required_source_data = true;
		} elseif ( 'external_url' === $source && $external_url ) {
			$video_data['source_external_url'] = $external_url;
			$has_required_source_data          = true;
		} elseif ( 'youtube' === $source && $youtube ) {
			$video_data['source_youtube'] = $youtube;
			$has_required_source_data     = true;
		} elseif ( 'vimeo' === $source && $vimeo ) {
			$video_data['source_vimeo'] = $vimeo;
			$has_required_source_data   = true;
		} elseif ( 'embedded' === $source && $embedded ) {
			$video_data['source_embedded'] = $embedded;
			$has_required_source_data      = true;
		} elseif ( 'shortcode' === $source && $shortcode ) {
			$video_data['source_shortcode'] = $shortcode;
			$has_required_source_data       = true;
		}

		if ( ! $has_required_source_data ) {
			delete_post_meta( $post_id, '_video' );
			return;
		}

		if ( $poster ) {
			$video_data['poster'] = $poster;
		}

		$video_data['runtime'] = array(
			'hours'   => $duration['hours'],
			'minutes' => $duration['minutes'],
			'seconds' => $duration['seconds'],
		);
		if ( TutorPress_Lesson_Video_Duration::has_non_zero_duration( $duration ) ) {
			$video_data['playtime'] = TutorPress_Lesson_Video_Duration::format_playtime( $duration );
		}

		update_post_meta( $post_id, '_video', $video_data );
	}

	/**
	 * Clear TutorPress lesson video mirror meta keys.
	 *
	 * @since 1.14.3
	 * @param int $post_id Lesson post ID.
	 * @return void
	 */
	private function clear_lesson_video_mirror_meta( $post_id ) {
		$mirror_keys = array(
			'_lesson_video_source',
			'_lesson_video_source_id',
			'_lesson_video_external_url',
			'_lesson_video_youtube',
			'_lesson_video_vimeo',
			'_lesson_video_embedded',
			'_lesson_video_shortcode',
			'_lesson_video_poster',
			'_lesson_video_duration_hours',
			'_lesson_video_duration_minutes',
			'_lesson_video_duration_seconds',
		);

		foreach ( $mirror_keys as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}
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
	 * Sanitize a Tutor LMS/TutorPress video source.
	 *
	 * @since 1.14.3
	 * @param string $source Video source.
	 * @return string
	 */
	public function sanitize_video_source( $source ) {
		$allowed_sources = array( '', 'html5', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode' );
		return in_array( $source, $allowed_sources, true ) ? $source : '';
	}

	/**
	 * Sanitize embedded video markup.
	 *
	 * @since 1.14.3
	 * @param string $code Embedded video markup.
	 * @return string
	 */
	public function sanitize_embedded_code( $code ) {
		$allowed_tags = array(
			'iframe' => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allowfullscreen' => true,
				'allow'           => true,
			),
			'video'  => array(
				'src'      => true,
				'width'    => true,
				'height'   => true,
				'controls' => true,
				'preload'  => true,
			),
			'source' => array(
				'src'  => true,
				'type' => true,
			),
		);

		return wp_kses( $code, $allowed_tags );
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
