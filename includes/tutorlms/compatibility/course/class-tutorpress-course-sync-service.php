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
}
