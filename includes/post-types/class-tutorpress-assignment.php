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

		// Bidirectional sync hooks for Tutor LMS compatibility
		add_action( 'updated_post_meta', [ $this, 'handle_assignment_settings_update' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'handle_tutor_assignment_option_update' ], 10, 4 );
		// Catch initial creation of assignment_option as well
		add_action( 'added_post_meta', [ $this, 'handle_tutor_assignment_option_update' ], 10, 4 );
		add_action( 'save_post_tutor_assignments', [ $this, 'sync_on_assignment_save' ], 999, 3 );

		// Also hook Tutor Pro's explicit assignment lifecycle events to ensure reverse sync
		add_action( 'tutor_assignment_created', [ $this, 'sync_from_tutor_after_save' ], 10, 1 );
		add_action( 'tutor_assignment_updated', [ $this, 'sync_from_tutor_after_save' ], 10, 1 );
	}

	/**
	 * Set up meta fields for assignments.
	 *
	 * @since 1.14.4
	 * @return void
	 */
	public function set_up_meta_fields() {
		// Register individual meta fields
		register_post_meta( $this->token, '_assignment_total_points', [
			'type'              => 'integer',
			'description'       => __( 'Total points for assignment', 'tutorpress' ),
			'single'            => true,
			'default'           => 10,
			'sanitize_callback' => function( $value ) { return max( 0, absint( $value ) ); },
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => true,
		] );

		// Register pass_points field  
		register_post_meta( $this->token, '_assignment_pass_points', [
			'type'              => 'integer',
			'description'       => __( 'Minimum points required to pass assignment', 'tutorpress' ),
			'single'            => true,
			'default'           => 5,
			'sanitize_callback' => 'absint',
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => true,
		] );

		// Register file_upload_limit field
		register_post_meta( $this->token, '_assignment_file_upload_limit', [
			'type'              => 'integer',
			'description'       => __( 'Maximum number of files student can upload', 'tutorpress' ),
			'single'            => true,
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => true,
		] );

		// Register file_size_limit field
		register_post_meta( $this->token, '_assignment_file_size_limit', [
			'type'              => 'integer',
			'description'       => __( 'Maximum file size limit in MB', 'tutorpress' ),
			'single'            => true,
			'default'           => 2,
			'sanitize_callback' => function($value) { return max(1, absint($value)); },
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => true,
		] );

		// Register time_duration_value field
		register_post_meta( $this->token, '_assignment_time_duration_value', [
			'type'              => 'integer',
			'description'       => __( 'Time limit value for assignment completion', 'tutorpress' ),
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => true,
		] );

		// Register time_duration_unit field
		register_post_meta( $this->token, '_assignment_time_duration_unit', [
			'type'              => 'string',
			'description'       => __( 'Time limit unit for assignment completion', 'tutorpress' ),
			'single'            => true,
			'default'           => 'hours',
			'sanitize_callback' => [ $this, 'sanitize_time_unit' ],
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => true,
		] );

		// Instructor attachments (canonical Tutor LMS meta for assignments)
		register_post_meta( $this->token, '_tutor_assignment_attachments', [
			'type'              => 'array',
			'description'       => __( 'Instructor attachments for the assignment (attachment IDs)', 'tutorpress' ),
			'single'            => true,
			'default'           => [],
			'sanitize_callback' => function( $value ) {
				if ( ! is_array( $value ) ) {
					return [];
				}
				return array_map( 'absint', array_filter( $value ) );
			},
			'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'integer' ],
				],
			],
		] );
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
				'properties'  => [
					'time_duration' => [
						'type'       => 'object',
						'properties' => [
							'value' => [ 'type' => 'integer', 'minimum' => 0 ],
							'unit'  => [ 'type' => 'string', 'enum' => [ 'weeks', 'days', 'hours' ] ],
						],
					],
					'total_points' => [ 'type' => 'integer', 'minimum' => 0 ],
					'pass_points'  => [ 'type' => 'integer', 'minimum' => 0 ],
					'file_upload_limit' => [ 'type' => 'integer', 'minimum' => 0 ],
					'file_size_limit'   => [ 'type' => 'integer', 'minimum' => 1 ],
					'instructor_attachments' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
					'content_drip' => [
						'type'       => 'object',
						'properties' => [
							'enabled' => [ 'type' => 'boolean' ],
							'type'    => [ 'type' => 'string' ],
							'available_after_days' => [ 'type' => 'integer', 'minimum' => 0 ],
							'show_days_field' => [ 'type' => 'boolean' ],
						],
					],
				],
			],
		] );
	}

	/**
	 * Get assignment settings for REST API.
	 *
	 * @since 1.14.4
	 * @param array $post Post data.
	 * @return array Assignment settings.
	 */
	public function get_assignment_settings( $post ) {
		$post_id = $post['id'];

		// Get instructor attachments (Tutor LMS compatible)
		$instructor_attachments = get_post_meta( $post_id, '_tutor_assignment_attachments', true );
		if ( ! is_array( $instructor_attachments ) ) {
			$instructor_attachments = array();
		}

		// Get course ID for Content Drip settings
		$course_id = tutor_utils()->get_course_id_by_content( $post_id );
		$content_drip_enabled = false;
		$content_drip_type = '';
		
		if ( $course_id ) {
			$content_drip_enabled = (bool) get_tutor_course_settings( $course_id, 'enable_content_drip' );
			$content_drip_type = get_tutor_course_settings( $course_id, 'content_drip_type', 'unlock_by_date' );
		}

		return [
			'time_duration' => [
				'value' => (int) get_post_meta( $post_id, '_assignment_time_duration_value', true ),
				'unit'  => get_post_meta( $post_id, '_assignment_time_duration_unit', true ) ?: 'hours',
			],
			'total_points'         => (int) get_post_meta( $post_id, '_assignment_total_points', true ) ?: 10,
			'pass_points'          => (int) get_post_meta( $post_id, '_assignment_pass_points', true ) ?: 5,
			'file_upload_limit'    => (int) get_post_meta( $post_id, '_assignment_file_upload_limit', true ) ?: 1,
			'file_size_limit'      => (int) get_post_meta( $post_id, '_assignment_file_size_limit', true ) ?: 2,
			'instructor_attachments' => array_map( 'intval', $instructor_attachments ),
			// Content Drip settings
			'content_drip' => [
				'enabled' => $content_drip_enabled,
				'type' => $content_drip_type,
				'available_after_days' => (int) get_post_meta( $post_id, '_assignment_available_after_days', true ),
				'show_days_field' => $content_drip_enabled && $content_drip_type === 'specific_days',
			],
		];
	}

	/**
	 * Update assignment settings via REST API.
	 * Write to individual meta fields from composite structure.
	 *
	 * @since 1.14.4
	 * @param array   $value New settings values.
	 * @param WP_Post $post  Post object.
	 * @return bool True on success.
	 */
	public function update_assignment_settings( $value, $post ) {
		$post_id = $post->ID;
		if ( ! is_array( $value ) ) {
			return false;
		}

		// Validate and update time duration
		if ( isset( $value['time_duration'] ) ) {
			$time_duration = $value['time_duration'];
			
			if ( isset( $time_duration['value'] ) ) {
				update_post_meta( $post_id, '_assignment_time_duration_value', absint( $time_duration['value'] ) );
			}
			
			if ( isset( $time_duration['unit'] ) ) {
				update_post_meta( $post_id, '_assignment_time_duration_unit', $this->sanitize_time_unit( $time_duration['unit'] ) );
			}
		}

		// Validate and update points
		if ( isset( $value['total_points'] ) ) {
			$total_points = max( 0, absint( $value['total_points'] ) );
			update_post_meta( $post_id, '_assignment_total_points', $total_points );
		}

		if ( isset( $value['pass_points'] ) ) {
			$pass_points = absint( $value['pass_points'] );
			$total_points = (int) get_post_meta( $post_id, '_assignment_total_points', true ) ?: 10;
			
			// If total_points is 0, allow any pass_points value
			if ( $total_points === 0 ) {
				$pass_points = $pass_points; // Allow any value
			} else {
				// Ensure pass points don't exceed total points
				$pass_points = min( $pass_points, $total_points );
			}
			update_post_meta( $post_id, '_assignment_pass_points', $pass_points );
		}

		// Validate and update file settings
		if ( isset( $value['file_upload_limit'] ) ) {
			update_post_meta( $post_id, '_assignment_file_upload_limit', absint( $value['file_upload_limit'] ) );
		}

		if ( isset( $value['file_size_limit'] ) ) {
			update_post_meta( $post_id, '_assignment_file_size_limit', max( 1, absint( $value['file_size_limit'] ) ) );
		}

		// Handle instructor attachments (Tutor LMS compatible)
		if ( isset( $value['instructor_attachments'] ) ) {
			$attachment_ids = array_map( 'absint', (array) $value['instructor_attachments'] );
			update_post_meta( $post_id, '_tutor_assignment_attachments', $attachment_ids );
		}

		// Handle Content Drip settings
		if ( isset( $value['content_drip']['available_after_days'] ) ) {
			$days = absint( $value['content_drip']['available_after_days'] );
			update_post_meta( $post_id, '_assignment_available_after_days', $days );
			
			// Sync to Content Drip addon format
			$this->sync_content_drip_settings( $post_id, $days );
		}

		return true;
	}

	/**
	 * Sanitize assignment settings.
	 *
	 * @since 1.14.4
	 * @param array $settings Assignment settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_assignment_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$sanitized = [];

		if ( isset( $settings['time_duration'] ) && is_array( $settings['time_duration'] ) ) {
			$time_duration = $settings['time_duration'];
			$sanitized['time_duration'] = [
				'value' => absint( $time_duration['value'] ?? 0 ),
				'unit'  => $this->sanitize_time_unit( $time_duration['unit'] ?? 'hours' ),
			];
		}

		if ( isset( $settings['total_points'] ) ) {
			$sanitized['total_points'] = max( 0, absint( $settings['total_points'] ) );
		}

		if ( isset( $settings['pass_points'] ) ) {
			$sanitized['pass_points'] = max( 0, absint( $settings['pass_points'] ) );
		}

		if ( isset( $settings['file_upload_limit'] ) ) {
			$sanitized['file_upload_limit'] = max( 0, absint( $settings['file_upload_limit'] ) );
		}

		if ( isset( $settings['file_size_limit'] ) ) {
			$sanitized['file_size_limit'] = max( 1, absint( $settings['file_size_limit'] ) );
		}

		if ( isset( $settings['attachments_enabled'] ) ) {
			$sanitized['attachments_enabled'] = (bool) $settings['attachments_enabled'];
		}

		if ( isset( $settings['instructor_attachments'] ) ) {
			$ids = $settings['instructor_attachments'];
			$sanitized['instructor_attachments'] = is_array( $ids ) ? array_map( 'absint', $ids ) : [];
		}

		if ( isset( $settings['content_drip'] ) && is_array( $settings['content_drip'] ) ) {
			$content_drip = $settings['content_drip'];
			$sanitized['content_drip'] = [
				'enabled' => (bool) ( $content_drip['enabled'] ?? false ),
				'type' => sanitize_text_field( $content_drip['type'] ?? '' ),
				'available_after_days' => max( 0, absint( $content_drip['available_after_days'] ?? 0 ) ),
				'show_days_field' => (bool) ( $content_drip['show_days_field'] ?? false ),
			];
		}

		return $sanitized;
	}

	/**
	 * Sanitize time unit value.
	 *
	 * @since 1.14.4
	 * @param string $unit Time unit.
	 * @return string Sanitized time unit.
	 */
	public function sanitize_time_unit( $unit ) {
		$allowed_units = [ 'weeks', 'days', 'hours' ];
		return in_array( $unit, $allowed_units, true ) ? $unit : 'hours';
	}

	/**
	 * Sync Content Drip settings to the addon's expected format.
	 *
	 * @since 1.14.4
	 * @param int $post_id Assignment post ID.
	 * @param int $days Number of days after enrollment.
	 * @return void
	 */
	private function sync_content_drip_settings( $post_id, $days ) {
		// Get existing content drip settings
		$content_drip_settings = get_post_meta( $post_id, '_content_drip_settings', true );
		if ( ! is_array( $content_drip_settings ) ) {
			$content_drip_settings = array();
		}

		// Update the after_xdays_of_enroll value
		$content_drip_settings['after_xdays_of_enroll'] = $days;

		// Save back to the Content Drip addon's meta field
		update_post_meta( $post_id, '_content_drip_settings', $content_drip_settings );
	}

	/**
	 * Handle assignment settings meta updates for bidirectional sync.
	 *
	 * @since 1.14.4
	 * @param int    $meta_id   Meta ID.
	 * @param int    $post_id   Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function handle_assignment_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Only handle assignment settings updates
		$assignment_meta_keys = [ '_assignment_total_points', '_assignment_pass_points', '_assignment_file_upload_limit', '_assignment_file_size_limit', '_assignment_time_duration_value', '_assignment_time_duration_unit' ];
		if ( ! in_array( $meta_key, $assignment_meta_keys, true ) || get_post_type( $post_id ) !== 'tutor_assignments' ) {
			return;
		}

		// Avoid infinite loops - check if this update came from our sync
		$our_last_update = get_post_meta( $post_id, '_tutorpress_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return; // Skip if we just synced within the last 5 seconds
		}

		update_post_meta( $post_id, '_tutorpress_last_sync', time() );

		// Sync to Tutor LMS format
		$this->sync_to_tutor_format( $post_id );
	}

	/**
	 * Handle Tutor LMS assignment_option updates to sync back to individual fields.
	 *
	 * @since 1.14.4
	 * @param int    $meta_id   Meta ID.
	 * @param int    $post_id   Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function handle_tutor_assignment_option_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		// Only handle assignment_option updates
		if ( $meta_key !== 'assignment_option' || get_post_type( $post_id ) !== 'tutor_assignments' ) {
			return;
		}

		// Avoid infinite loops - check if this update came from our sync
		$our_last_update = get_post_meta( $post_id, '_tutorpress_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return; // Skip if we just synced within the last 5 seconds
		}

		update_post_meta( $post_id, '_tutorpress_last_sync', time() );

		// Sync from Tutor LMS format
		$this->sync_from_tutor_format( $post_id, $meta_value );
	}

	/**
	 * Sync on assignment save.
	 *
	 * @since 1.14.4
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public function sync_on_assignment_save( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// If Tutor LMS is saving via AJAX, let their update to assignment_option fire our meta hook
		// to sync back to individual fields. Pushing here would set our sync guard just before
		// Tutor LMS updates assignment_option, preventing the reverse sync.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( $action === 'tutor_assignment_save' ) {
				return;
			}
		}

		$this->sync_to_tutor_format( $post_id );
	}

	/**
	 * Ensure reverse sync after Tutor LMS saves assignments via its own flows.
	 *
	 * @since 1.14.4
	 * @param int $assignment_id Assignment post ID.
	 * @return void
	 */
	public function sync_from_tutor_after_save( $assignment_id ) {
		$assignment_id = absint( $assignment_id );
		if ( ! $assignment_id ) {
			return;
		}
		// Avoid immediate loops if we just synced
		$our_last_update = get_post_meta( $assignment_id, '_tutorpress_last_sync', true );
		if ( $our_last_update && ( time() - $our_last_update ) < 5 ) {
			return;
		}
		$this->sync_from_tutor_format( $assignment_id );
	}

	/**
	 * Sync individual meta fields to Tutor LMS format.
	 *
	 * @since 1.14.4
	 * @param int $post_id Assignment post ID.
	 * @return void
	 */
	private function sync_to_tutor_format( $post_id ) {
		update_post_meta( $post_id, '_tutorpress_last_sync', time() );

		// Get existing assignment_option or create new one
		$assignment_option = get_post_meta( $post_id, 'assignment_option', true );
		if ( ! is_array( $assignment_option ) ) {
			$assignment_option = array();
		}

		// Sync total_points
		$total_points = (int) get_post_meta( $post_id, '_assignment_total_points', true );
		$assignment_option['total_mark'] = $total_points;

		// Sync pass_points
		$pass_points = (int) get_post_meta( $post_id, '_assignment_pass_points', true );
		// Tutor LMS default is 10
		$total_points = $total_points === 0 ? 10 : $total_points;
		$assignment_option['pass_mark'] = $pass_points;

		// Sync file_upload_limit
		$file_upload_limit = (int) get_post_meta( $post_id, '_assignment_file_upload_limit', true );
		$assignment_option['upload_files_limit'] = $file_upload_limit;

		// Sync file_size_limit
		$file_size_limit = (int) get_post_meta( $post_id, '_assignment_file_size_limit', true );
		$assignment_option['upload_file_size_limit'] = $file_size_limit;

		// Sync time_duration
		$time_duration_value = (int) get_post_meta( $post_id, '_assignment_time_duration_value', true );
		$time_duration_unit = get_post_meta( $post_id, '_assignment_time_duration_unit', true ) ?: 'hours';
		
		$assignment_option['time_duration'] = array(
			'value' => $time_duration_value,
			'time' => $time_duration_unit,
		);

		// Save back to Tutor LMS format
		update_post_meta( $post_id, 'assignment_option', $assignment_option );
	}

	/**
	 * Sync from Tutor LMS format to individual meta fields.
	 *
	 * @since 1.14.4
	 * @param int   $post_id Assignment post ID.
	 * @param array $assignment_option Assignment option data.
	 * @return void
	 */
	private function sync_from_tutor_format( $post_id, $assignment_option = null ) {
		if ( ! $assignment_option ) {
			$assignment_option = get_post_meta( $post_id, 'assignment_option', true );
		}

		if ( empty( $assignment_option ) || ! is_array( $assignment_option ) ) {
			return;
		}

		// Sync total_points from Tutor LMS format
		if ( isset( $assignment_option['total_mark'] ) ) {
			$total_points = max( 0, absint( $assignment_option['total_mark'] ) );
			update_post_meta( $post_id, '_assignment_total_points', $total_points );
		}

		// Sync pass_points from Tutor LMS format
		if ( isset( $assignment_option['pass_mark'] ) ) {
			update_post_meta( $post_id, '_assignment_pass_points', absint( $assignment_option['pass_mark'] ) );
		}

		// Sync file_upload_limit from Tutor LMS format
		if ( isset( $assignment_option['upload_files_limit'] ) ) {
			update_post_meta( $post_id, '_assignment_file_upload_limit', absint( $assignment_option['upload_files_limit'] ) );
		}

		// Sync file_size_limit from Tutor LMS format
		if ( isset( $assignment_option['upload_file_size_limit'] ) ) {
			update_post_meta( $post_id, '_assignment_file_size_limit', max(1, absint( $assignment_option['upload_file_size_limit'] ) ) );
		}

		// Sync time_duration from Tutor LMS format
		if ( isset( $assignment_option['time_duration'] ) && is_array( $assignment_option['time_duration'] ) ) {
			$time_duration = $assignment_option['time_duration'];
			
			if ( isset( $time_duration['value'] ) ) {
				update_post_meta( $post_id, '_assignment_time_duration_value', absint( $time_duration['value'] ) );
			}
			
			if ( isset( $time_duration['time'] ) ) {
				update_post_meta( $post_id, '_assignment_time_duration_unit', $this->sanitize_time_unit( $time_duration['time'] ) );
			}
		}
	}
}


