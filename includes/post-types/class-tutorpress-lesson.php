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
	 * Existing lesson thumbnails captured before omitted-image REST saves.
	 *
	 * @since 1.14.3
	 * @var array<int,int>
	 */
	private $omitted_image_rest_thumbnail_snapshots = [];

	/**
	 * Existing lesson thumbnails captured before Gutenberg meta box loader saves.
	 *
	 * @since 1.14.3
	 * @var array<int,int>
	 */
	private $meta_box_loader_thumbnail_snapshots = [];

	/**
	 * Lesson sync context and intent helper.
	 *
	 * @since 1.14.3
	 * @var TutorPress_Lesson_Sync_Context
	 */
	private $sync_context;

	/**
	 * Lesson sync service.
	 *
	 * @since 1.14.3
	 * @var TutorPress_Lesson_Sync_Service
	 */
	private $sync_service;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 */
	public function __construct() {
		$this->token = 'lesson';
		$this->sync_context = new TutorPress_Lesson_Sync_Context( $this->token );
		$this->sync_service = new TutorPress_Lesson_Sync_Service( $this->sync_context );

		// Initialize meta fields and REST API support
		add_action( 'init', [ $this, 'set_up_meta_fields' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );

		// Ensure featured image support for lessons
		add_action( 'init', [ $this, 'ensure_lesson_featured_image_support' ], 20 );
		add_filter( 'rest_pre_insert_lesson', [ $this, 'capture_omitted_image_rest_thumbnail_snapshot' ], 10, 2 );
		add_action( 'rest_after_insert_lesson', [ $this, 'restore_omitted_image_rest_thumbnail_snapshot' ], 10, 3 );
		add_action( 'save_post_lesson', [ $this, 'capture_meta_box_loader_thumbnail_snapshot' ], 1, 3 );
		add_action( 'save_post_lesson', [ $this, 'restore_meta_box_loader_thumbnail_snapshot' ], 1001, 3 );

		// Add failsafe "Edit Lesson" link to admin bar (priority 71 for top positioning)
		add_action( 'admin_bar_menu', [ $this, 'add_edit_lesson_admin_bar' ], 71 );
		// Add CSS to align the icon like WordPress's native edit link
		add_action( 'wp_head', [ $this, 'output_admin_bar_icon_css' ] );

		// Bidirectional sync hooks for Tutor LMS compatibility
		add_action( 'added_post_meta', [ $this, 'handle_tutor_video_meta_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_video_meta_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_attachments_meta_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_preview_meta_update' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'handle_lesson_settings_update' ], 10, 4 );
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
		if ( post_type_exists( $this->token ) ) {
			add_post_type_support( $this->token, 'custom-fields' );
		}

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
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_source_id', [
			'type' => 'integer',
			'description' => __( 'Video attachment ID for uploaded videos', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => 'absint',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_external_url', [
			'type' => 'string',
			'description' => __( 'External video URL', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_youtube', [
			'type' => 'string',
			'description' => __( 'YouTube video URL or ID', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_vimeo', [
			'type' => 'string',
			'description' => __( 'Vimeo video URL or ID', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_embedded', [
			'type' => 'string',
			'description' => __( 'Embedded video code', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => [ $this, 'sanitize_embedded_code' ],
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_shortcode', [
			'type' => 'string',
			'description' => __( 'Video shortcode', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_poster', [
			'type' => 'string',
			'description' => __( 'Video poster/thumbnail URL', 'tutorpress' ),
			'single' => true,
			'default' => '',
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_duration_hours', [
			'type' => 'integer',
			'description' => __( 'Video duration in hours', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => 'absint',
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_duration_minutes', [
			'type' => 'integer',
			'description' => __( 'Video duration in minutes', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => function( $value ) { return min( 59, absint( $value ) ); },
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_video_duration_seconds', [
			'type' => 'integer',
			'description' => __( 'Video duration in seconds', 'tutorpress' ),
			'single' => true,
			'default' => 0,
			'sanitize_callback' => function( $value ) { return min( 59, absint( $value ) ); },
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest' => true,
		] );

		register_post_meta( 'lesson', '_lesson_exercise_files', [
			'type' => 'array',
			'description' => __( 'Exercise file attachment IDs', 'tutorpress' ),
			'single' => true,
			'default' => [],
			'sanitize_callback' => [ $this, 'sanitize_attachment_ids' ],
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
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
			'auth_callback' => [ $this, 'post_meta_auth_callback' ],
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
		return $this->sync_service->get_lesson_settings( $post );
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
		return $this->sync_service->update_lesson_settings( $value, $post );
	}

	/**
	 * Sanitize lesson settings.
	 *
	 * @since 1.14.3
	 * @param array $settings Lesson settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_lesson_settings( $settings ) {
		return $this->sync_service->sanitize_lesson_settings( $settings );
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

	/**
	 * Capture the existing thumbnail before an omitted-image core REST lesson save.
	 *
	 * @since 1.14.3
	 * @param stdClass|WP_Post $prepared_post Prepared post object.
	 * @param WP_REST_Request $request       REST request.
	 * @return stdClass|WP_Post Prepared post object.
	 */
	public function capture_omitted_image_rest_thumbnail_snapshot( $prepared_post, $request ) {
		$post_id = isset( $prepared_post->ID ) ? absint( $prepared_post->ID ) : 0;
		if ( $post_id > 0 ) {
			unset( $this->omitted_image_rest_thumbnail_snapshots[ $post_id ] );
		}

		if ( ! $this->is_omitted_image_core_rest_lesson_update( $prepared_post, $request ) ) {
			return $prepared_post;
		}

		$thumbnail_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( $thumbnail_id > 0 ) {
			$this->omitted_image_rest_thumbnail_snapshots[ $post_id ] = $thumbnail_id;
		}

		return $prepared_post;
	}

	/**
	 * Restore a captured thumbnail after Tutor LMS deletes an omitted image.
	 *
	 * @since 1.14.3
	 * @param WP_Post         $post     Inserted or updated post object.
	 * @param WP_REST_Request $request  REST request.
	 * @param bool            $creating Whether this was a create request.
	 * @return void
	 */
	public function restore_omitted_image_rest_thumbnail_snapshot( $post, $request, $creating ) {
		$post_id = isset( $post->ID ) ? absint( $post->ID ) : 0;
		if ( ! $post_id ) {
			return;
		}

		$thumbnail_id = $this->omitted_image_rest_thumbnail_snapshots[ $post_id ] ?? 0;
		unset( $this->omitted_image_rest_thumbnail_snapshots[ $post_id ] );

		if ( $creating || ! $thumbnail_id || ! $this->is_omitted_image_core_rest_lesson_update( $post, $request ) ) {
			return;
		}

		if ( absint( get_post_thumbnail_id( $post_id ) ) > 0 ) {
			return;
		}

		if ( ! $this->is_valid_image_attachment( $thumbnail_id ) ) {
			return;
		}

		set_post_thumbnail( $post_id, $thumbnail_id );
	}

	/**
	 * Capture the current thumbnail before Gutenberg's meta box loader save.
	 *
	 * @since 1.14.3
	 * @param int     $post_id Lesson post ID.
	 * @param WP_Post $post    Lesson post object.
	 * @param bool    $update  Whether this is an existing post update.
	 * @return void
	 */
	public function capture_meta_box_loader_thumbnail_snapshot( $post_id, $post, $update ) {
		$post_id = absint( $post_id );
		if ( $post_id > 0 ) {
			unset( $this->meta_box_loader_thumbnail_snapshots[ $post_id ] );
		}

		if ( ! $this->is_gutenberg_meta_box_loader_lesson_save( $post_id, $post, $update ) ) {
			return;
		}

		$thumbnail_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( $this->is_valid_image_attachment( $thumbnail_id ) ) {
			$this->meta_box_loader_thumbnail_snapshots[ $post_id ] = $thumbnail_id;
		}
	}

	/**
	 * Restore a thumbnail deleted by Tutor LMS during Gutenberg's meta box loader save.
	 *
	 * @since 1.14.3
	 * @param int     $post_id Lesson post ID.
	 * @param WP_Post $post    Lesson post object.
	 * @param bool    $update  Whether this is an existing post update.
	 * @return void
	 */
	public function restore_meta_box_loader_thumbnail_snapshot( $post_id, $post, $update ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		$thumbnail_id = $this->meta_box_loader_thumbnail_snapshots[ $post_id ] ?? 0;
		unset( $this->meta_box_loader_thumbnail_snapshots[ $post_id ] );

		if ( ! $thumbnail_id || ! $this->is_gutenberg_meta_box_loader_lesson_save( $post_id, $post, $update ) ) {
			return;
		}

		if ( absint( get_post_thumbnail_id( $post_id ) ) > 0 ) {
			return;
		}

		if ( ! $this->is_valid_image_attachment( $thumbnail_id ) ) {
			return;
		}

		set_post_thumbnail( $post_id, $thumbnail_id );
	}

	/**
	 * Whether a request is an existing lesson REST update that omitted image fields.
	 *
	 * @since 1.14.3
	 * @param stdClass|WP_Post $prepared_post Prepared post object.
	 * @param mixed            $request       Possible REST request.
	 * @return bool
	 */
	private function is_omitted_image_core_rest_lesson_update( $prepared_post, $request ) {
		return $this->sync_context->is_omitted_image_core_rest_lesson_update( $prepared_post, $request );
	}

	/**
	 * Whether PHP request globals include Tutor LMS's thumbnail field.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	private function php_request_has_thumbnail_id() {
		return $this->sync_context->php_request_has_thumbnail_id();
	}

	/**
	 * Whether the current request is Gutenberg's classic meta box loader save.
	 *
	 * @since 1.14.3
	 * @param int          $post_id Lesson post ID.
	 * @param WP_Post|null $post    Lesson post object.
	 * @param bool         $update  Whether this is an existing post update.
	 * @return bool
	 */
	private function is_gutenberg_meta_box_loader_lesson_save( $post_id, $post, $update ) {
		return $this->sync_context->is_gutenberg_meta_box_loader_lesson_save( $post_id, $post, $update );
	}

	/**
	 * Whether PHP request globals include WordPress core featured-image fields.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	private function php_request_has_core_featured_image_field() {
		return $this->sync_context->php_request_has_core_featured_image_field();
	}

	/**
	 * Whether an attachment ID is a valid image attachment.
	 *
	 * @since 1.14.3
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_valid_image_attachment( $attachment_id ) {
		return $this->sync_context->is_valid_image_attachment( $attachment_id );
	}

	/**
	 * Add "Edit Lesson" link to admin bar as failsafe.
	 *
	 * If WordPress didn't add an edit node (possibly due to Tutor LMS,
	 * we add our own to ensure instructors can always edit lessons from the frontend.
	 * This runs at priority 71 to appear right after "+ New".
	 *
	 * @since 1.14.3
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 * @return void
	 */
	public function add_edit_lesson_admin_bar( $wp_admin_bar ) {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_singular( 'lesson' ) ) {
			return;
		}

		$lesson_id = get_the_ID();
		if ( ! $lesson_id || ! current_user_can( 'edit_post', $lesson_id ) ) {
			return;
		}

		// Check if WordPress already added an edit node
		if ( $wp_admin_bar->get_node( 'edit' ) ) {
			// WordPress already added it, so don't add a duplicate
			return;
		}

		// Add our own "Edit Lesson" link (opens in current tab, positioned near the top)
		$wp_admin_bar->add_menu( array(
			'id'     => 'tutorpress-edit-lesson',
			'title'  => '<span class="ab-icon dashicons dashicons-edit"></span>' . __( 'Edit Lesson', 'tutorpress' ),
			'href'   => get_edit_post_link( $lesson_id ),
		) );
	}

	/**
	 * Output CSS to align the "Edit Lesson" admin bar icon.
	 *
	 * Outputs minimal CSS to align the pencil icon with top: 2px,
	 * matching WordPress's native edit link alignment.
	 *
	 * @since 1.14.3
	 * @return void
	 */
	public function output_admin_bar_icon_css() {
		?>
		<style>
			#wp-admin-bar-tutorpress-edit-lesson .ab-item .ab-icon:before {
				top: 2px;
			}
		</style>
		<?php
	}

	public function handle_tutor_video_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->sync_service->handle_tutor_video_meta_update( $meta_id, $post_id, $meta_key, $meta_value );
	}

	public function handle_tutor_attachments_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->sync_service->handle_tutor_attachments_meta_update( $meta_id, $post_id, $meta_key, $meta_value );
	}

	public function handle_tutor_preview_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->sync_service->handle_tutor_preview_meta_update( $meta_id, $post_id, $meta_key, $meta_value );
	}

	public function handle_lesson_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->sync_service->handle_lesson_settings_update( $meta_id, $post_id, $meta_key, $meta_value );
	}

	public function sync_on_lesson_save( $post_id, $post, $update ) {
		$this->sync_service->sync_on_lesson_save( $post_id, $post, $update );
	}

	public function sanitize_video_source( $source ) {
		return $this->sync_service->sanitize_video_source( $source );
	}

	public function sanitize_embedded_code( $code ) {
		return $this->sync_service->sanitize_embedded_code( $code );
	}

	public function sanitize_attachment_ids( $ids ) {
		return $this->sync_service->sanitize_attachment_ids( $ids );
	}
}


