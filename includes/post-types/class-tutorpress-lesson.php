<?php
/**
 * TutorPress Lesson Class
 *
 * Settings-only post type class for the Tutor LMS 'lesson' post type.
 *
 * @package TutorPress
 * @since 1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TutorPress_Lesson class.
 *
 * Manages lesson settings for TutorPress addon functionality.
 *
 * @since 1.14.3
 */
class TutorPress_Lesson {

	/**
	 * The post type token for lessons.
	 *
	 * @since 1.14.3
	 * @var string
	 */
	public $token;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 */
	public function __construct() {
		$this->token = 'lesson';

		// Initialize meta fields and REST API support
		add_action( 'init', [ $this, 'set_up_meta_fields' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );

		// Ensure featured image support for lessons
		add_action( 'init', [ $this, 'ensure_lesson_featured_image_support' ], 20 );

		// Bidirectional sync hooks for Tutor LMS compatibility
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_video_meta_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_attachments_meta_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_preview_meta_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_lesson_settings_update' ], 10, 4 );

		// Sync on lesson save
		add_action( 'save_post_lesson', [ $this, 'sync_on_lesson_save' ], 999, 3 );

	}

	/**
	 * Set up meta fields for lessons.
	 *
	 * Registers a composite lesson_settings field for future use.
	 *
	 * @since 1.14.3
	 * @return void
	 */
	public function set_up_meta_fields() {
		// Composite lesson_settings (kept minimal; future panels will extend schema)
		register_post_meta( $this->token, 'lesson_settings', [
			'type'              => 'object',
			'description'       => __( 'Lesson settings for TutorPress Gutenberg integration', 'tutorpress' ),
			'single'            => true,
			'default'           => [],
			'sanitize_callback' => [ $this, 'sanitize_lesson_settings' ],
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => [
				'schema' => [
					'type'       => 'object',
					'properties' => [
						'video' => [
							'type'       => 'object',
							'properties' => [
								'source'             => [ 'type' => 'string' ],
								'source_video_id'    => [ 'type' => 'integer' ],
								'source_external_url' => [ 'type' => 'string' ],
								'source_youtube'     => [ 'type' => 'string' ],
								'source_vimeo'       => [ 'type' => 'string' ],
								'source_embedded'    => [ 'type' => 'string' ],
								'source_shortcode'   => [ 'type' => 'string' ],
								'poster'             => [ 'type' => 'string' ],
							],
						],
						'duration' => [
							'type'       => 'object',
							'properties' => [
								'hours'   => [ 'type' => 'integer', 'minimum' => 0 ],
								'minutes' => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 59 ],
								'seconds' => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 59 ],
							],
						],
						'exercise_files' => [
							'type'  => 'array',
							'items' => [ 'type' => 'integer' ],
						],
						'lesson_preview' => [
							'type'       => 'object',
							'properties' => [
								'enabled'         => [ 'type' => 'boolean' ],
								'addon_available' => [ 'type' => 'boolean' ],
							],
						],
					],
				],
			],
		] );

		// Individual meta fields (unchanged keys)
		register_post_meta( 'lesson', '_lesson_video_source', [
			'type' => 'string',
			'description' => __( 'Video source type', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => [ $this, 'sanitize_video_source' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_source_id', [
			'type' => 'integer',
			'description' => __( 'Video attachment ID for uploaded videos', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_external_url', [
			'type' => 'string',
			'description' => __( 'External video URL', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_youtube', [
			'type' => 'string',
			'description' => __( 'YouTube video URL or ID', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_vimeo', [
			'type' => 'string',
			'description' => __( 'Vimeo video URL or ID', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_embedded', [
			'type' => 'string',
			'description' => __( 'Embedded video code', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => [ $this, 'sanitize_embedded_code' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_shortcode', [
			'type' => 'string',
			'description' => __( 'Video shortcode', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_poster', [
			'type' => 'string',
			'description' => __( 'Video poster/thumbnail URL', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_duration_hours', [
			'type' => 'integer',
			'description' => __( 'Video duration in hours', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_duration_minutes', [
			'type' => 'integer',
			'description' => __( 'Video duration in minutes', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => function( $value ) { return min( 59, absint( $value ) ); },
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_duration_seconds', [
			'type' => 'integer',
			'description' => __( 'Video duration in seconds', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => function( $value ) { return min( 59, absint( $value ) ); },
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_exercise_files', [
			'type' => 'array',
			'description' => __( 'Exercise file attachment IDs', 'tutorpress' ),
			'single' => true,
			'default' => [],
			'sanitize_callback' => [ $this, 'sanitize_attachment_ids' ],
			'show_in_rest' => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
			],
		] );

		register_post_meta( 'lesson', '_lesson_is_preview', [
			'type' => 'boolean',
			'description' => __( 'Whether lesson is available as preview', 'tutorpress' ),
			'single' => true,
			'default' => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'show_in_rest' => true,
		] );
	}

	/**
	 * Auth callback for lesson post meta.
	 *
	 * @since 1.14.3
	 * @param bool   $allowed  Whether the user can add the meta.
	 * @param string $meta_key The meta key.
	 * @param int    $post_id  The post ID where the meta key is being edited.
	 * @return bool Whether the user can edit the meta key.
	 */
	public function post_meta_auth_callback( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Register REST API fields for lesson settings.
	 *
	 * @since 1.14.3
	 * @return void
	 */
	public function register_rest_fields() {
		register_rest_field( $this->token, 'lesson_settings', [
			'get_callback'    => [ $this, 'get_lesson_settings' ],
			'update_callback' => [ $this, 'update_lesson_settings' ],
			'schema'          => [
				'description' => __( 'Lesson settings', 'tutorpress' ),
				'type'        => 'object',
			],
		] );
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

		$course_preview_available = tutorpress_feature_flags()->can_user_access_feature('course_preview');

		$gutenberg_preview = get_post_meta( $post_id, '_lesson_is_preview', true );
		$tutor_preview = get_post_meta( $post_id, '_is_preview', true );

		if ( $course_preview_available ) {
			$tutor_preview_bool = ! empty( $tutor_preview );
			$gutenberg_preview_bool = ! empty( $gutenberg_preview );
			if ( $tutor_preview_bool !== $gutenberg_preview_bool ) {
				update_post_meta( $post_id, '_tutorpress_syncing_from_tutor', time() );
				update_post_meta( $post_id, '_lesson_is_preview', $tutor_preview_bool );
				delete_post_meta( $post_id, '_tutorpress_syncing_from_tutor' );
				$gutenberg_preview = $tutor_preview_bool;
			}
		}

		return [
			'video' => [
				'source' => get_post_meta( $post_id, '_lesson_video_source', true ),
				'source_video_id' => (int) get_post_meta( $post_id, '_lesson_video_source_id', true ),
				'source_external_url' => get_post_meta( $post_id, '_lesson_video_external_url', true ),
				'source_youtube' => get_post_meta( $post_id, '_lesson_video_youtube', true ),
				'source_vimeo' => get_post_meta( $post_id, '_lesson_video_vimeo', true ),
				'source_embedded' => get_post_meta( $post_id, '_lesson_video_embedded', true ),
				'source_shortcode' => get_post_meta( $post_id, '_lesson_video_shortcode', true ),
				'poster' => get_post_meta( $post_id, '_lesson_video_poster', true ),
			],
			'duration' => [
				'hours' => (int) get_post_meta( $post_id, '_lesson_video_duration_hours', true ),
				'minutes' => (int) get_post_meta( $post_id, '_lesson_video_duration_minutes', true ),
				'seconds' => (int) get_post_meta( $post_id, '_lesson_video_duration_seconds', true ),
			],
			'exercise_files' => array_map( 'intval', get_post_meta( $post_id, '_lesson_exercise_files', true ) ?: [] ),
			'lesson_preview' => [
				'enabled' => (bool) $gutenberg_preview,
				'addon_available' => $course_preview_available,
			],
		];
	}

	/**
	 * Update lesson settings via REST API.
	 *
	 * Placeholder implementation. Future steps will persist to canonical meta
	 * and/or Tutor LMS mirrors as needed.
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

		// Video
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

		// Duration
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

		// Exercise files
		if ( isset( $value['exercise_files'] ) ) {
			$ids = $this->sanitize_attachment_ids( $value['exercise_files'] );
			update_post_meta( $post_id, '_lesson_exercise_files', $ids );
			$this->sync_exercise_files( $post_id );
		}

		// Lesson preview (addon-gated)
		if ( isset( $value['lesson_preview']['enabled'] ) && tutorpress_feature_flags()->can_user_access_feature('course_preview') ) {
			$is_preview = rest_sanitize_boolean( $value['lesson_preview']['enabled'] );
			update_post_meta( $post_id, '_lesson_is_preview', $is_preview );
			$this->sync_lesson_preview( $post_id );
		}

		// Sync to Tutor LMS native video format
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
			$video = $settings['video'];
			$sanitized['video'] = [
				'source'             => sanitize_text_field( $video['source'] ?? '' ),
				'source_video_id'    => absint( $video['source_video_id'] ?? 0 ),
				'source_external_url' => esc_url_raw( $video['source_external_url'] ?? '' ),
				'source_youtube'     => sanitize_text_field( $video['source_youtube'] ?? '' ),
				'source_vimeo'       => sanitize_text_field( $video['source_vimeo'] ?? '' ),
				'source_embedded'    => wp_kses_post( $video['source_embedded'] ?? '' ),
				'source_shortcode'   => sanitize_text_field( $video['source_shortcode'] ?? '' ),
				'poster'             => esc_url_raw( $video['poster'] ?? '' ),
			];
		}

		if ( isset( $settings['duration'] ) && is_array( $settings['duration'] ) ) {
			$duration = $settings['duration'];
			$sanitized['duration'] = [
				'hours'   => absint( $duration['hours'] ?? 0 ),
				'minutes' => min( 59, absint( $duration['minutes'] ?? 0 ) ),
				'seconds' => min( 59, absint( $duration['seconds'] ?? 0 ) ),
			];
		}

		if ( isset( $settings['exercise_files'] ) ) {
			$ids = $settings['exercise_files'];
			$sanitized['exercise_files'] = is_array( $ids ) ? array_map( 'absint', $ids ) : [];
		}

		if ( isset( $settings['lesson_preview'] ) && is_array( $settings['lesson_preview'] ) ) {
			$lp = $settings['lesson_preview'];
			$sanitized['lesson_preview'] = [
				'enabled'         => (bool) ( $lp['enabled'] ?? false ),
				'addon_available' => (bool) ( $lp['addon_available'] ?? false ),
			];
		}

		return $sanitized;
	}

	/**
	 * Register admin scripts (placeholder).
	 *
	 * @since 1.14.3
	 * @return void
	 */
	public function register_admin_scripts() {
		$hook_suffix = get_current_screen() ? get_current_screen()->id : '';
		if ( ! in_array( $hook_suffix, array( 'post', 'post-new' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'lesson' ), true ) ) {
			return;
		}
		// Assets are managed in TutorPress_Scripts if needed.
	}

	/**
	 * Ensure featured image support for lessons.
	 */
	public function ensure_lesson_featured_image_support() {
		if ( post_type_exists( 'lesson' ) ) {
			add_post_type_support( 'lesson', 'thumbnail' );
			if ( ! current_theme_supports( 'post-thumbnails' ) ) {
				add_theme_support( 'post-thumbnails', array( 'lesson' ) );
			} else {
				add_theme_support( 'post-thumbnails' );
			}
		}
	}

	public function handle_tutor_video_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( $meta_key !== '_video' || get_post_type( $post_id ) !== 'lesson' ) {
			return;
		}
		$our_last_update = get_post_meta( $post_id, '_tutorpress_video_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return;
		}
		$this->sync_from_tutor_video_format( $post_id, $meta_value );
	}

	public function handle_tutor_attachments_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( $meta_key !== '_tutor_attachments' || get_post_type( $post_id ) !== 'lesson' ) {
			return;
		}
		$our_last_update = get_post_meta( $post_id, '_tutorpress_attachments_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return;
		}
		update_post_meta( $post_id, '_tutorpress_attachments_last_sync', time() );
		$attachment_ids = is_array( $meta_value ) ? array_map( 'absint', $meta_value ) : [];
		update_post_meta( $post_id, '_lesson_exercise_files', $attachment_ids );
	}

	public function handle_tutor_preview_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( $meta_key !== '_is_preview' || get_post_type( $post_id ) !== 'lesson' ) {
			return;
		}
		$our_last_update = get_post_meta( $post_id, '_tutorpress_preview_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return;
		}
		update_post_meta( $post_id, '_tutorpress_preview_last_sync', time() );
		update_post_meta( $post_id, '_lesson_is_preview', rest_sanitize_boolean( $meta_value ) );
	}

	public function handle_lesson_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$non_video_meta_keys = [ '_lesson_exercise_files', '_lesson_is_preview' ];
		if ( ! in_array( $meta_key, $non_video_meta_keys, true ) || get_post_type( $post_id ) !== 'lesson' ) {
			return;
		}
		if ( get_post_meta( $post_id, '_tutorpress_syncing_from_tutor', true ) ) {
			return;
		}
		$our_last_update = get_post_meta( $post_id, '_tutorpress_sync_last_update', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return;
		}
		update_post_meta( $post_id, '_tutorpress_sync_last_update', time() );
		if ( $meta_key === '_lesson_exercise_files' ) {
			$this->sync_exercise_files( $post_id );
		}
		if ( $meta_key === '_lesson_is_preview' ) {
			$this->sync_lesson_preview( $post_id );
		}
	}

	public function sync_on_lesson_save( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$this->sync_to_tutor_video_format( $post_id );
		$this->sync_exercise_files( $post_id );
		$this->sync_lesson_preview( $post_id );
	}

	private function sync_from_tutor_video_format( $post_id, $video_data = null ) {
		if ( ! $video_data ) {
			$video_data = get_post_meta( $post_id, '_video', true );
		}
		if ( empty( $video_data ) || ! is_array( $video_data ) ) {
			return;
		}
		update_post_meta( $post_id, '_tutorpress_video_last_sync', time() );
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
		} else {
			if ( isset( $video_data['source_video_id'] ) && $video_data['source_video_id'] ) {
				$attachment_url = wp_get_attachment_url( $video_data['source_video_id'] );
				if ( $attachment_url ) {
					update_post_meta( $post_id, '_lesson_video_external_url', $attachment_url );
				}
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
			$runtime = $video_data['runtime'];
			if ( isset( $runtime['hours'] ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_hours', absint( $runtime['hours'] ) );
			}
			if ( isset( $runtime['minutes'] ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_minutes', min( 59, absint( $runtime['minutes'] ) ) );
			}
			if ( isset( $runtime['seconds'] ) ) {
				update_post_meta( $post_id, '_lesson_video_duration_seconds', min( 59, absint( $runtime['seconds'] ) ) );
			}
		}
	}

	private function sync_to_tutor_video_format( $post_id ) {
		update_post_meta( $post_id, '_tutorpress_video_last_sync', time() );
		$source = get_post_meta( $post_id, '_lesson_video_source', true );
		$source_video_id = (int) get_post_meta( $post_id, '_lesson_video_source_id', true );
		$external_url = get_post_meta( $post_id, '_lesson_video_external_url', true );
		youtube: $youtube = get_post_meta( $post_id, '_lesson_video_youtube', true );
		$vimeo = get_post_meta( $post_id, '_lesson_video_vimeo', true );
		$embedded = get_post_meta( $post_id, '_lesson_video_embedded', true );
		$shortcode = get_post_meta( $post_id, '_lesson_video_shortcode', true );
		$poster = get_post_meta( $post_id, '_lesson_video_poster', true );
		$hours = (int) get_post_meta( $post_id, '_lesson_video_duration_hours', true );
		$minutes = (int) get_post_meta( $post_id, '_lesson_video_duration_minutes', true );
		$seconds = (int) get_post_meta( $post_id, '_lesson_video_duration_seconds', true );

		if ( empty( $source ) ) {
			update_post_meta( $post_id, '_video', array( 'source' => '-1' ) );
			return;
		}

		$video_data = array( 'source' => $source );
		if ( $source === 'html5' && $source_video_id ) {
			$video_data['source_video_id'] = $source_video_id;
			$attachment_url = wp_get_attachment_url( $source_video_id );
			if ( $attachment_url ) {
				$video_data['source_html5'] = $attachment_url;
			}
		}
		if ( $source === 'external_url' && $external_url ) {
			$video_data['source_external_url'] = $external_url;
		}
		if ( $source === 'youtube' && $youtube ) {
			$video_data['source_youtube'] = $youtube;
		}
		if ( $source === 'vimeo' && $vimeo ) {
			$video_data['source_vimeo'] = $vimeo;
		}
		if ( $source === 'embedded' && $embedded ) {
			$video_data['source_embedded'] = $embedded;
		}
		if ( $source === 'shortcode' && $shortcode ) {
			$video_data['source_shortcode'] = $shortcode;
		}
		if ( $poster ) {
			$video_data['poster'] = $poster;
		}
		$video_data['runtime'] = array(
			'hours' => $hours,
			'minutes' => $minutes,
			'seconds' => $seconds,
		);
		update_post_meta( $post_id, '_video', $video_data );
	}

	private function sync_exercise_files( $post_id ) {
		update_post_meta( $post_id, '_tutorpress_attachments_last_sync', time() );
		$exercise_files = get_post_meta( $post_id, '_lesson_exercise_files', true );
		if ( ! is_array( $exercise_files ) ) {
			$exercise_files = [];
		}
		if ( ! empty( $exercise_files ) ) {
			update_post_meta( $post_id, '_tutor_attachments', $exercise_files );
		} else {
			delete_post_meta( $post_id, '_tutor_attachments' );
		}
	}

	private function sync_lesson_preview( $post_id ) {
		update_post_meta( $post_id, '_tutorpress_preview_last_sync', time() );
		$is_preview = get_post_meta( $post_id, '_lesson_is_preview', true );
		$tutor_value = $is_preview ? 1 : 0;
		update_post_meta( $post_id, '_is_preview', $tutor_value );
	}

	public function sanitize_video_source( $source ) {
		$allowed_sources = [ '', 'html5', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode' ];
		return in_array( $source, $allowed_sources, true ) ? $source : '';
	}

	public function sanitize_embedded_code( $code ) {
		$allowed_tags = [
			'iframe' => [ 'src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'allowfullscreen' => true, 'allow' => true ],
			'video' => [ 'src' => true, 'width' => true, 'height' => true, 'controls' => true, 'preload' => true ],
			'source' => [ 'src' => true, 'type' => true ],
		];
		return wp_kses( $code, $allowed_tags );
	}

	public function sanitize_attachment_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			return [];
		}
		return array_map( 'absint', array_filter( $ids ) );
	}
}


