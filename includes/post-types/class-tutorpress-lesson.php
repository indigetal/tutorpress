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
		// Provide an empty yet well-shaped structure for future UI.
		return [
			'video' => [
				'source'             => '',
				'source_video_id'    => 0,
				'source_external_url' => '',
				'source_youtube'     => '',
				'source_vimeo'       => '',
				'source_embedded'    => '',
				'source_shortcode'   => '',
				'poster'             => '',
			],
			'duration' => [
				'hours'   => 0,
				'minutes' => 0,
				'seconds' => 0,
			],
			'exercise_files' => [],
			'lesson_preview' => [
				'enabled'         => false,
				'addon_available' => false,
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
		// Accept shape after sanitize; persistence will be added in later steps.
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
}


