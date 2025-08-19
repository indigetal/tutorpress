<?php
/**
 * Assignment Settings Meta Fields
 *
 * Handles registration of assignment settings meta fields for Gutenberg editor.
 *
 * @package TutorPress
 * @since 1.3.0
 */

defined('ABSPATH') || exit;

class TutorPress_Assignment_Settings {

    /**
     * Initialize the assignment settings.
     *
     * @since 1.3.0
     * @return void
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_meta_fields']);
        // REST field registration - MIGRATED TO TutorPress_Assignment class
        // add_action('rest_api_init', [__CLASS__, 'register_rest_fields']);
        
        // Lightweight bidirectional sync hooks
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_meta_update'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_content_drip_meta_update'], 10, 4);
    }

    /**
     * Register assignment settings meta fields.
     *
     * @since 1.3.0
     * @return void
     */
    public static function register_meta_fields() {
        // NOTE: "Available after days" is not part of Tutor LMS native functionality
        // Tutor LMS calculates deadlines dynamically based on enrollment date + time duration
        
        // Time duration value
        register_post_meta('tutor_assignments', '_assignment_time_duration_value', [
            'type'              => 'integer',
            'description'       => __('Time limit value for assignment completion', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);

        // Time duration unit
        register_post_meta('tutor_assignments', '_assignment_time_duration_unit', [
            'type'              => 'string',
            'description'       => __('Time limit unit for assignment completion', 'tutorpress'),
            'single'            => true,
            'default'           => 'hours',
            'sanitize_callback' => [__CLASS__, 'sanitize_time_unit'],
            'show_in_rest'      => true,
        ]);

        // Total points - MIGRATED TO TutorPress_Assignment class (Field 1)
        // register_post_meta('tutor_assignments', '_assignment_total_points', [
        //     'type'              => 'integer',
        //     'description'       => __('Total points for assignment', 'tutorpress'),
        //     'single'            => true,
        //     'default'           => 10,
        //     'sanitize_callback' => function($value) { return max(0, absint($value)); },
        //     'show_in_rest'      => true,
        // ]);

        // Minimum pass points - MIGRATED TO TutorPress_Assignment class (Field 2)
        // register_post_meta('tutor_assignments', '_assignment_pass_points', [
        //     'type'              => 'integer',
        //     'description'       => __('Minimum points required to pass assignment', 'tutorpress'),
        //     'single'            => true,
        //     'default'           => 5,
        //     'sanitize_callback' => 'absint',
        //     'show_in_rest'      => true,
        // ]);

        // File upload limit - MIGRATED TO TutorPress_Assignment class (Field 3)
        // register_post_meta('tutor_assignments', '_assignment_file_upload_limit', [
        //     'type'              => 'integer',
        //     'description'       => __('Maximum number of files student can upload', 'tutorpress'),
        //     'single'            => true,
        //     'default'           => 1,
        //     'sanitize_callback' => 'absint',
        //     'show_in_rest'      => true,
        // ]);

        // File size limit (in MB) - MIGRATED TO TutorPress_Assignment class (Field 4)
        // register_post_meta('tutor_assignments', '_assignment_file_size_limit', [
        //     'type'              => 'integer',
        //     'description'       => __('Maximum file size limit in MB', 'tutorpress'),
        //     'single'            => true,
        //     'default'           => 2,
        //     'sanitize_callback' => function($value) { return max(1, absint($value)); },
        //     'show_in_rest'      => true,
        // ]);

        // Attachments enabled
        register_post_meta('tutor_assignments', '_assignment_attachments_enabled', [
            'type'              => 'boolean',
            'description'       => __('Whether file attachments are enabled for this assignment', 'tutorpress'),
            'single'            => true,
            'default'           => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'show_in_rest'      => true,
        ]);

        // Content Drip integration (Tutor Pro addon)
        register_post_meta('tutor_assignments', '_assignment_available_after_days', [
            'type'              => 'integer',
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Handle Tutor LMS assignment_option updates to sync back to individual fields.
     * This maintains WordPress-first priority while ensuring compatibility.
     *
     * @since 1.3.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle assignment_option updates
        if ($meta_key !== 'assignment_option') {
            return;
        }

        // Only handle assignment posts
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tutor_assignments') {
            return;
        }

        // Avoid infinite loops - check if this update came from our sync
        $our_last_update = get_post_meta($post_id, '_tutorpress_last_sync', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return; // Skip if we just synced within the last 5 seconds
        }

        // Sync from Tutor LMS format to our individual fields
        self::sync_from_tutor_format($post_id, $meta_value);
    }

    /**
     * Sync from Tutor LMS native format to individual meta fields.
     *
     * @since 1.3.0
     * @param int $post_id Assignment post ID.
     * @param array $assignment_option Assignment option data.
     * @return void
     */
    private static function sync_from_tutor_format($post_id, $assignment_option = null) {
        if (!$assignment_option) {
            $assignment_option = get_post_meta($post_id, 'assignment_option', true);
        }
        
        if (empty($assignment_option) || !is_array($assignment_option)) {
            return;
        }

        // Sync time duration
        if (isset($assignment_option['time_duration'])) {
            $time_duration = $assignment_option['time_duration'];
            if (isset($time_duration['value'])) {
                update_post_meta($post_id, '_assignment_time_duration_value', absint($time_duration['value']));
            }
            if (isset($time_duration['time'])) {
                update_post_meta($post_id, '_assignment_time_duration_unit', self::sanitize_time_unit($time_duration['time']));
            }
        }

        // Sync points
        if (isset($assignment_option['total_mark'])) {
            update_post_meta($post_id, '_assignment_total_points', max(0, absint($assignment_option['total_mark'])));
        }
        if (isset($assignment_option['pass_mark'])) {
            update_post_meta($post_id, '_assignment_pass_points', absint($assignment_option['pass_mark']));
        }

        // Sync file settings
        if (isset($assignment_option['upload_files_limit'])) {
            update_post_meta($post_id, '_assignment_file_upload_limit', absint($assignment_option['upload_files_limit']));
        }
        if (isset($assignment_option['upload_file_size_limit'])) {
            update_post_meta($post_id, '_assignment_file_size_limit', max(1, absint($assignment_option['upload_file_size_limit'])));
        }
    }

    /**
     * Sync individual meta fields to Tutor LMS native assignment_option format.
     *
     * @since 1.3.0
     * @param int $post_id Assignment post ID.
     * @return void
     */
    private static function sync_to_tutor_format($post_id) {
        // Mark that we're syncing to avoid infinite loops
        update_post_meta($post_id, '_tutorpress_last_sync', time());

        // Get current values from individual meta fields
        $time_duration_value = (int) get_post_meta($post_id, '_assignment_time_duration_value', true);
        $time_duration_unit = get_post_meta($post_id, '_assignment_time_duration_unit', true) ?: 'hours';
        $total_points = (int) get_post_meta($post_id, '_assignment_total_points', true);
        // If total_points is 0, use 10 for Tutor LMS compatibility (Tutor LMS default)
        $total_points = $total_points === 0 ? 10 : $total_points;
        $pass_points = (int) get_post_meta($post_id, '_assignment_pass_points', true) ?: 5;
        $file_upload_limit = (int) get_post_meta($post_id, '_assignment_file_upload_limit', true) ?: 1;
        $file_size_limit = (int) get_post_meta($post_id, '_assignment_file_size_limit', true) ?: 2;

        // Build Tutor LMS compatible assignment_option array
        $assignment_option = array(
            'time_duration' => array(
                'value' => $time_duration_value,
                'time' => $time_duration_unit,
            ),
            'total_mark' => $total_points,
            'pass_mark' => $pass_points,
            'upload_files_limit' => $file_upload_limit,
            'upload_file_size_limit' => $file_size_limit,
        );

        // Update the Tutor LMS native meta field
        update_post_meta($post_id, 'assignment_option', $assignment_option);
    }

    /**
     * Get assignment settings for REST API.
     *
     * @since 1.3.0
     * @param array $post Post data.
     * @return array Assignment settings.
     */
    public static function get_assignment_settings($post) {
        $post_id = $post['id'];

        // Get instructor attachments (Tutor LMS compatible)
        $instructor_attachments = get_post_meta($post_id, '_tutor_assignment_attachments', true);
        if (!is_array($instructor_attachments)) {
            $instructor_attachments = array();
        }

        // Get course ID for Content Drip settings
        $course_id = tutor_utils()->get_course_id_by_content($post_id);
        $content_drip_enabled = false;
        $content_drip_type = '';
        
        if ($course_id) {
            $content_drip_enabled = (bool) get_tutor_course_settings($course_id, 'enable_content_drip');
            $content_drip_type = get_tutor_course_settings($course_id, 'content_drip_type', 'unlock_by_date');
        }

        return [
            'time_duration' => [
                'value' => (int) get_post_meta($post_id, '_assignment_time_duration_value', true),
                'unit'  => get_post_meta($post_id, '_assignment_time_duration_unit', true) ?: 'hours',
            ],
            'total_points'         => (int) get_post_meta($post_id, '_assignment_total_points', true) ?: 10,
            'pass_points'          => (int) get_post_meta($post_id, '_assignment_pass_points', true) ?: 5,
            'file_upload_limit'    => (int) get_post_meta($post_id, '_assignment_file_upload_limit', true) ?: 1,
            'file_size_limit'      => (int) get_post_meta($post_id, '_assignment_file_size_limit', true) ?: 2,
            'attachments_enabled'  => (bool) get_post_meta($post_id, '_assignment_attachments_enabled', true),
            'instructor_attachments' => array_map('intval', $instructor_attachments),
            // Content Drip settings
            'content_drip' => [
                'enabled' => $content_drip_enabled,
                'type' => $content_drip_type,
                'available_after_days' => (int) get_post_meta($post_id, '_assignment_available_after_days', true),
                'show_days_field' => $content_drip_enabled && $content_drip_type === 'specific_days',
            ],
        ];
    }

    /**
     * Update assignment settings via REST API.
     *
     * @since 1.3.0
     * @param array $value New settings values.
     * @param WP_Post $post Post object.
     * @return bool True on success.
     */
    public static function update_assignment_settings($value, $post) {
        $post_id = $post->ID;

        // Validate and update time duration
        if (isset($value['time_duration'])) {
            $time_duration = $value['time_duration'];
            
            if (isset($time_duration['value'])) {
                update_post_meta($post_id, '_assignment_time_duration_value', absint($time_duration['value']));
            }
            
            if (isset($time_duration['unit'])) {
                update_post_meta($post_id, '_assignment_time_duration_unit', self::sanitize_time_unit($time_duration['unit']));
            }
        }

        // Validate and update points
        if (isset($value['total_points'])) {
            $total_points = max(0, absint($value['total_points']));
            update_post_meta($post_id, '_assignment_total_points', $total_points);
        }

        if (isset($value['pass_points'])) {
            $pass_points = absint($value['pass_points']);
            $total_points = (int) get_post_meta($post_id, '_assignment_total_points', true) ?: 10;
            
            // If total_points is 0, allow any pass_points value
            if ($total_points === 0) {
                $pass_points = $pass_points; // Allow any value
            } else {
                // Ensure pass points don't exceed total points
                $pass_points = min($pass_points, $total_points);
            }
            update_post_meta($post_id, '_assignment_pass_points', $pass_points);
        }

        // Validate and update file settings
        if (isset($value['file_upload_limit'])) {
            update_post_meta($post_id, '_assignment_file_upload_limit', absint($value['file_upload_limit']));
        }

        if (isset($value['file_size_limit'])) {
            update_post_meta($post_id, '_assignment_file_size_limit', max(1, absint($value['file_size_limit'])));
        }

        if (isset($value['attachments_enabled'])) {
            update_post_meta($post_id, '_assignment_attachments_enabled', rest_sanitize_boolean($value['attachments_enabled']));
        }

        // Handle instructor attachments (Tutor LMS compatible)
        if (isset($value['instructor_attachments'])) {
            $attachment_ids = array_map('absint', (array) $value['instructor_attachments']);
            update_post_meta($post_id, '_tutor_assignment_attachments', $attachment_ids);
        }

        // Handle Content Drip settings
        if (isset($value['content_drip']['available_after_days'])) {
            $days = absint($value['content_drip']['available_after_days']);
            update_post_meta($post_id, '_assignment_available_after_days', $days);
            
            // Sync to Content Drip addon format
            self::sync_content_drip_settings($post_id, $days);
        }

        // Sync to Tutor LMS native format for compatibility
        self::sync_to_tutor_format($post_id);

        return true;
    }

    /**
     * Sync Content Drip settings to the addon's expected format.
     *
     * @since 1.3.0
     * @param int $post_id Assignment post ID.
     * @param int $days Number of days after enrollment.
     * @return void
     */
    private static function sync_content_drip_settings($post_id, $days) {
        // Get existing content drip settings
        $content_drip_settings = get_post_meta($post_id, '_content_drip_settings', true);
        if (!is_array($content_drip_settings)) {
            $content_drip_settings = array();
        }

        // Update the after_xdays_of_enroll value
        $content_drip_settings['after_xdays_of_enroll'] = $days;

        // Save back to the Content Drip addon's meta field
        update_post_meta($post_id, '_content_drip_settings', $content_drip_settings);
    }

    /**
     * Handle updates from Content Drip addon to keep our fields in sync.
     *
     * @since 1.3.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_content_drip_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only process content drip settings for assignments
        if ($meta_key !== '_content_drip_settings' || get_post_type($post_id) !== 'tutor_assignments') {
            return;
        }

        // Prevent infinite loops
        $last_sync = get_post_meta($post_id, '_tutorpress_content_drip_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 2) {
            return;
        }

        // Update our individual meta field
        if (is_array($meta_value) && isset($meta_value['after_xdays_of_enroll'])) {
            update_post_meta($post_id, '_tutorpress_content_drip_last_sync', time());
            update_post_meta($post_id, '_assignment_available_after_days', absint($meta_value['after_xdays_of_enroll']));
        }
    }

    /**
     * Register REST API fields for assignment settings.
     *
     * @since 1.3.0
     * @return void
     */
    public static function register_rest_fields() {
        register_rest_field('tutor_assignments', 'assignment_settings', [
            'get_callback'    => [__CLASS__, 'get_assignment_settings'],
            'update_callback' => [__CLASS__, 'update_assignment_settings'],
            'schema'          => [
                'description' => __('Assignment settings', 'tutorpress'),
                'type'        => 'object',
                'properties'  => [
                    'time_duration' => [
                        'type'        => 'object',
                        'description' => __('Time limit for assignment completion', 'tutorpress'),
                        'properties'  => [
                            'value' => [
                                'type'    => 'integer',
                                'minimum' => 0,
                            ],
                            'unit' => [
                                'type' => 'string',
                                'enum' => ['weeks', 'days', 'hours'],
                            ],
                        ],
                    ],
                    'total_points' => [
                        'type'        => 'integer',
                        'description' => __('Total points for assignment', 'tutorpress'),
                        'minimum'     => 0,
                    ],
                    'pass_points' => [
                        'type'        => 'integer',
                        'description' => __('Minimum points required to pass assignment', 'tutorpress'),
                        'minimum'     => 0,
                    ],
                    'file_upload_limit' => [
                        'type'        => 'integer',
                        'description' => __('Maximum number of files student can upload', 'tutorpress'),
                        'minimum'     => 0,
                    ],
                    'file_size_limit' => [
                        'type'        => 'integer',
                        'description' => __('Maximum file size limit in MB', 'tutorpress'),
                        'minimum'     => 1,
                    ],
                    'attachments_enabled' => [
                        'type'        => 'boolean',
                        'description' => __('Whether file attachments are enabled', 'tutorpress'),
                    ],
                    'instructor_attachments' => [
                        'type'        => 'array',
                        'description' => __('Array of attachment IDs provided by instructor', 'tutorpress'),
                        'items'       => [
                            'type' => 'integer',
                        ],
                    ],
                    'content_drip' => [
                        'type'        => 'object',
                        'description' => __('Content Drip settings', 'tutorpress'),
                        'properties'  => [
                            'enabled' => [
                                'type'        => 'boolean',
                                'description' => __('Whether content drip is enabled for the course', 'tutorpress'),
                            ],
                            'type' => [
                                'type'        => 'string',
                                'description' => __('Content drip type set at course level', 'tutorpress'),
                            ],
                            'available_after_days' => [
                                'type'        => 'integer',
                                'description' => __('Number of days after enrollment when assignment becomes available', 'tutorpress'),
                            ],
                            'show_days_field' => [
                                'type'        => 'boolean',
                                'description' => __('Whether to show the days field based on course settings', 'tutorpress'),
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Sanitize time unit value.
     *
     * @since 1.3.0
     * @param string $unit Time unit.
     * @return string Sanitized time unit.
     */
    public static function sanitize_time_unit($unit) {
        $allowed_units = ['weeks', 'days', 'hours'];
        return in_array($unit, $allowed_units, true) ? $unit : 'hours';
    }
} 