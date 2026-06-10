<?php
/**
 * TutorPress course sync context helper.
 *
 * Centralizes request/context and intent detection for course synchronization.
 *
 * @package TutorPress
 * @since 1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TutorPress_Course_Sync_Context class.
 *
 * @since 1.14.3
 */
class TutorPress_Course_Sync_Context {

	/**
	 * The post type token for courses.
	 *
	 * @since 1.14.3
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 * @param string $post_type Course post type token.
	 */
	public function __construct( $post_type = 'courses' ) {
		$this->post_type = $post_type;
	}

	/**
	 * Whether a post ID belongs to the course post type.
	 *
	 * @since 1.14.3
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_course_post( $post_id ) {
		return $post_id && $this->post_type === get_post_type( $post_id );
	}

	/**
	 * Whether a save should skip course save-boundary synchronization.
	 *
	 * @since 1.14.3
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function should_skip_save_boundary_sync( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return true;
		}

		return $this->is_recent_post_meta_timestamp( $post_id, '_tutorpress_course_settings_last_sync' );
	}

	/**
	 * Whether a metabox save should be skipped by the current guard behavior.
	 *
	 * @since 1.14.3
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function should_skip_metabox_save( $post_id ) {
		if ( ! $this->is_course_post( $post_id ) ) {
			return true;
		}

		return defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
	}

	/**
	 * Whether the current request is a WordPress REST request.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	public function is_rest_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Whether a REST after-insert callback is handling a course post.
	 *
	 * @since 1.14.3
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	public function is_rest_after_insert_course( $post ) {
		return $post instanceof WP_Post && $this->post_type === $post->post_type;
	}

	/**
	 * Whether a course settings array includes an intended canonical save key.
	 *
	 * @since 1.14.3
	 * @param array  $settings Course settings.
	 * @param string $key      Settings key.
	 * @return bool
	 */
	public function has_canonical_settings_key( $settings, $key ) {
		return is_array( $settings ) && array_key_exists( $key, $settings );
	}

	/**
	 * Whether a REST after-insert settings subset includes the current key.
	 *
	 * This preserves existing isset() behavior where null is treated as omitted.
	 *
	 * @since 1.14.3
	 * @param array  $settings Course settings.
	 * @param string $key      Settings key.
	 * @return bool
	 */
	public function has_rest_after_insert_settings_key( $settings, $key ) {
		return is_array( $settings ) && isset( $settings[ $key ] );
	}

	/**
	 * Whether a custom settings route request includes a key as write intent.
	 *
	 * @since 1.14.3
	 * @param mixed  $request Possible REST request.
	 * @param string $key     Request key.
	 * @return bool
	 */
	public function has_custom_settings_route_key( $request, $key ) {
		return $request instanceof WP_REST_Request && $request->has_param( $key );
	}

	/**
	 * Whether a direct meta update is for a known course meta key.
	 *
	 * @since 1.14.3
	 * @param int    $post_id   Post ID.
	 * @param string $meta_key  Meta key.
	 * @param array  $meta_keys Supported meta keys.
	 * @return bool
	 */
	public function is_direct_course_meta_update( $post_id, $meta_key, $meta_keys ) {
		return $this->is_course_post( $post_id ) && in_array( $meta_key, $meta_keys, true );
	}

	/**
	 * Whether the current request is Tutor LMS's frontend-builder course save.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	public function is_frontend_builder_course_save() {
		if ( ! isset( $_POST['action'] ) ) {
			return false;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		return in_array( $action, array( 'tutor_create_course', 'tutor_update_course' ), true );
	}

	/**
	 * Whether a course currently has the sync-to-Tutor guard set.
	 *
	 * @since 1.14.3
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_syncing_to_tutor( $post_id ) {
		return (bool) get_post_meta( $post_id, '_tutorpress_syncing_to_tutor', true );
	}

	/**
	 * Whether a timestamp meta value is within the echo-prevention window.
	 *
	 * @since 1.14.3
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Timestamp meta key.
	 * @param int    $window   Window in seconds.
	 * @return bool
	 */
	public function is_recent_post_meta_timestamp( $post_id, $meta_key, $window = 5 ) {
		$last_sync = get_post_meta( $post_id, $meta_key, true );

		return $last_sync && ( time() - (int) $last_sync ) < $window;
	}
}
