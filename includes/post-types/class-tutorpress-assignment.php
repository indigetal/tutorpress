<?php
/**
 * TutorPress Assignment Class
 *
 * Settings-only post type class for the Tutor LMS 'tutor_assignments' post type.
 *
 * @package TutorPress
 * @since 1.14.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TutorPress_Assignment class.
 *
 * Manages assignment settings for TutorPress addon functionality.
 * Foundation step: registers hooks and minimal REST field stubs.
 *
 * @since 1.14.4
 */
class TutorPress_Assignment {

	/**
	 * The post type token for assignments.
	 *
	 * @since 1.14.4
	 * @var string
	 */
	public $token;

	/**
	 * Constructor.
	 *
	 * @since 1.14.4
	 */
	public function __construct() {
		// Expected Tutor LMS assignment post type slug
		$this->token = 'tutor_assignments';

		// Initialize meta fields and REST API support
		add_action( 'init', [ $this, 'set_up_meta_fields' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
	}

	/**
	 * Set up meta fields for assignments.
	 *
	 * Foundation step: Intentionally minimal. Composite/meta registration will
	 * be added incrementally in subsequent commits per the project plan.
	 *
	 * @since 1.14.4
	 * @return void
	 */
	public function set_up_meta_fields() {
		// Reserved for Step 2+ (composite registration and individual meta fields)
	}

	/**
	 * Auth callback for assignment post meta.
	 *
	 * @since 1.14.4
	 * @param bool   $allowed  Whether the user can add the meta.
	 * @param string $meta_key The meta key.
	 * @param int    $post_id  The post ID where the meta key is being edited.
	 * @return bool Whether the user can edit the meta key.
	 */
	public function post_meta_auth_callback( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Register REST API fields for assignment settings.
	 *
	 * Foundation step: Registers a minimal stub for `assignment_settings`.
	 * Full schema and mapping will be introduced incrementally.
	 *
	 * @since 1.14.4
	 * @return void
	 */
	public function register_rest_fields() {
		register_rest_field( $this->token, 'assignment_settings', [
			'get_callback'    => [ $this, 'get_assignment_settings' ],
			'update_callback' => [ $this, 'update_assignment_settings' ],
			'schema'          => [
				'description' => __( 'Assignment settings', 'tutorpress' ),
				'type'        => 'object',
			],
		] );
	}

	/**
	 * Get assignment settings for REST API.
	 *
	 * Foundation step: return minimal structure. Will be expanded in Step 2.
	 *
	 * @since 1.14.4
	 * @param array $post Post data.
	 * @return array Assignment settings.
	 */
	public function get_assignment_settings( $post ) {
		return [];
	}

	/**
	 * Update assignment settings via REST API.
	 *
	 * Foundation step: no-op. Will be implemented incrementally.
	 *
	 * @since 1.14.4
	 * @param array   $value New settings values.
	 * @param WP_Post $post  Post object.
	 * @return bool True on success.
	 */
	public function update_assignment_settings( $value, $post ) {
		return true;
	}
}


