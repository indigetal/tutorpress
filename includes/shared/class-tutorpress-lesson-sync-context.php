<?php
/**
 * TutorPress lesson sync context helper.
 *
 * Centralizes request/context and intent detection for lesson synchronization.
 *
 * @package TutorPress
 * @since 1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TutorPress_Lesson_Sync_Context class.
 *
 * @since 1.14.3
 */
class TutorPress_Lesson_Sync_Context {

	/**
	 * The post type token for lessons.
	 *
	 * @since 1.14.3
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 *
	 * @since 1.14.3
	 * @param string $post_type Lesson post type token.
	 */
	public function __construct( $post_type = 'lesson' ) {
		$this->post_type = $post_type;
	}

	/**
	 * Whether the current request is a frontend-builder explicit no-video save.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	public function is_frontend_builder_no_video_save() {
		if ( ! $this->is_frontend_builder_lesson_save() ) {
			return false;
		}

		$video_post = array();
		if ( isset( $_POST['video'] ) && is_array( $_POST['video'] ) ) {
			$video_post = wp_unslash( $_POST['video'] );
		}

		$video_source = isset( $video_post['source'] ) ? sanitize_text_field( $video_post['source'] ) : '';

		return '-1' === $video_source;
	}

	/**
	 * Whether the current request is a frontend-builder lesson save with attachments.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	public function is_frontend_builder_attachment_save() {
		return $this->is_frontend_builder_lesson_save() && array_key_exists( 'tutor_attachments', $_POST );
	}

	/**
	 * Whether the current request is a frontend-builder delete-all attachment save.
	 *
	 * @since 1.14.3
	 * @param int $post_id Lesson post ID.
	 * @return bool
	 */
	public function is_frontend_builder_delete_all_attachment_save( $post_id ) {
		if ( ! $this->is_frontend_builder_lesson_save() ) {
			return false;
		}

		if ( array_key_exists( 'tutor_attachments', $_POST ) ) {
			return false;
		}

		return empty( get_post_meta( $post_id, '_tutor_attachments', true ) );
	}

	/**
	 * Whether a request is an existing lesson REST update that omitted image fields.
	 *
	 * @since 1.14.3
	 * @param stdClass|WP_Post $prepared_post Prepared post object.
	 * @param mixed            $request       Possible REST request.
	 * @return bool
	 */
	public function is_omitted_image_core_rest_lesson_update( $prepared_post, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return false;
		}

		$post_id = isset( $prepared_post->ID ) ? absint( $prepared_post->ID ) : 0;
		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || $this->post_type !== $post->post_type ) {
			return false;
		}

		if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return false;
		}

		$route_pattern = '#^/wp/v2/' . preg_quote( $this->post_type, '#' ) . '/' . $post_id . '$#';
		if ( ! preg_match( $route_pattern, $request->get_route() ) ) {
			return false;
		}

		if ( $request->has_param( 'featured_media' ) || $request->has_param( 'thumbnail_id' ) ) {
			return false;
		}

		return ! $this->php_request_has_thumbnail_id();
	}

	/**
	 * Whether PHP request globals include Tutor LMS's thumbnail field.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	public function php_request_has_thumbnail_id() {
		return array_key_exists( 'thumbnail_id', $_POST ) || array_key_exists( 'thumbnail_id', $_REQUEST );
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
	public function is_gutenberg_meta_box_loader_lesson_save( $post_id, $post, $update ) {
		if ( ! $update || ! is_admin() || wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== strtoupper( $request_method ) ) {
			return false;
		}

		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
		if ( 'post.php' !== basename( $script_name ) ) {
			return false;
		}

		$meta_box_loader = isset( $_GET['meta-box-loader'] ) ? sanitize_text_field( wp_unslash( $_GET['meta-box-loader'] ) ) : '';
		$action          = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$request_post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( '1' !== $meta_box_loader || 'edit' !== $action || $request_post_id !== absint( $post_id ) ) {
			return false;
		}

		if ( ! $post instanceof WP_Post || $this->post_type !== $post->post_type ) {
			return false;
		}

		if ( $this->php_request_has_thumbnail_id() || $this->php_request_has_core_featured_image_field() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether PHP request globals include WordPress core featured-image fields.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	public function php_request_has_core_featured_image_field() {
		return array_key_exists( 'featured_media', $_POST )
			|| array_key_exists( 'featured_media', $_REQUEST )
			|| array_key_exists( '_thumbnail_id', $_POST )
			|| array_key_exists( '_thumbnail_id', $_REQUEST );
	}

	/**
	 * Whether an attachment ID is a valid image attachment.
	 *
	 * @since 1.14.3
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function is_valid_image_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id );
	}

	/**
	 * Whether the current request is Tutor LMS's frontend-builder lesson save.
	 *
	 * @since 1.14.3
	 * @return bool
	 */
	private function is_frontend_builder_lesson_save() {
		if ( ! isset( $_POST['action'] ) ) {
			return false;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		return 'tutor_save_lesson' === $action;
	}
}
