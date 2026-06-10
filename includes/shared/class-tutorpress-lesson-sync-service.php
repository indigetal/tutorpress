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
		return $this->call( 'get_lesson_settings', array( $post ) );
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
		return $this->call( 'update_lesson_settings', array( $value, $post ) );
	}

	/**
	 * Sanitize lesson settings.
	 *
	 * @since 1.14.3
	 * @param array $settings Lesson settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_lesson_settings( $settings ) {
		return $this->call( 'sanitize_lesson_settings', array( $settings ) );
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
