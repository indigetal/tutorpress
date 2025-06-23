<?php
/**
 * Content Drip REST Controller Class
 *
 * Handles REST API functionality for content drip settings at the individual content level.
 * Provides endpoints for lessons and assignments content drip configuration.
 *
 * @package TutorPress
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Content_Drip_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->rest_base = 'content-drip';
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        // Get content drip settings for a specific post (lesson/assignment)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<post_id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_content_drip_settings'],
                    'permission_callback' => [$this, 'check_read_permission'],
                    'args'               => [
                        'post_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the content item (lesson or assignment).', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'save_content_drip_settings'],
                    'permission_callback' => [$this, 'check_write_permission'],
                    'args'               => [
                        'post_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the content item (lesson or assignment).', 'tutorpress'),
                        ],
                        'settings' => [
                            'required'          => true,
                            'description'       => __('Content drip settings object.', 'tutorpress'),
                            'validate_callback' => function($param, $request, $key) {
                                // Allow any array/object, let our controller handle validation
                                return is_array($param);
                            },
                        ],
                    ],
                ],
            ]
        );

        // Get available prerequisites for a course
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<course_id>\d+)/prerequisites',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_prerequisites'],
                    'permission_callback' => [$this, 'check_course_read_permission'],
                    'args'               => [
                        'course_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get prerequisites for.', 'tutorpress'),
                        ],
                        'exclude_post_id' => [
                            'required'          => false,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Post ID to exclude from prerequisites list.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Get course-level content drip settings (lightweight)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/course/(?P<course_id>\d+)/settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_course_content_drip_settings'],
                    'permission_callback' => [$this, 'check_course_read_permission'],
                    'args'               => [
                        'course_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get content drip settings for.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );


    }

    /**
     * Get content drip settings for a specific content item.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_content_drip_settings($request) {
        $post_id = (int) $request->get_param('post_id');

        // Validate the post
        $validation = $this->validate_content_post($post_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get content drip settings using the helper class with proper defaults
        $settings = [
            'unlock_date' => get_item_content_drip_settings($post_id, 'unlock_date', null),
            'after_xdays_of_enroll' => get_item_content_drip_settings($post_id, 'after_xdays_of_enroll', null),
            'prerequisites' => get_item_content_drip_settings($post_id, 'prerequisites', []),
        ];

        // Get content drip info (course context)
        $drip_info = TutorPress_Content_Drip_Helpers::get_content_drip_info($post_id);

        // Get course ID
        $course_id = null;
        if (function_exists('tutor_utils') && tutor_utils()) {
            $course_id = tutor_utils()->get_course_id_by_content($post_id);
        }

        return rest_ensure_response($this->format_response([
            'settings' => $settings,
            'drip_info' => $drip_info,
            'post_id' => $post_id,
            'course_id' => $course_id,
        ], __('Content drip settings retrieved successfully.', 'tutorpress')));
    }

    /**
     * Save content drip settings for a specific content item.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function save_content_drip_settings($request) {
        $post_id = (int) $request->get_param('post_id');
        $settings = $request->get_param('settings');

        // Validate the post
        $validation = $this->validate_content_post($post_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Validate that settings is provided and is an array
        if (empty($settings) || !is_array($settings)) {
            return new WP_Error(
                'invalid_settings',
                __('Settings must be provided as a non-empty array.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Validate that content drip is enabled for this course
        $drip_info = TutorPress_Content_Drip_Helpers::get_content_drip_info($post_id);
        if (!$drip_info['enabled']) {
            return new WP_Error(
                'content_drip_disabled',
                __('Content drip is not enabled for this course.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Get course-level content drip type to determine what settings are expected
        $course_drip_type = get_tutor_course_settings($drip_info['course_id'], 'content_drip_type', 'unlock_by_date');
        
        // Determine drip type from course settings and validate accordingly
        $drip_type = null;
        switch ($course_drip_type) {
            case 'after_finishing_prerequisites':
                // Allow empty or missing prerequisites (means no prerequisites for this item)
                $drip_type = 'after_finishing_prerequisites';
                break;
                
            case 'unlock_by_date':
                // Always accept unlock_date field (required field but can be empty)
                $drip_type = 'unlock_by_date';
                break;
                
            case 'specific_days':
                // Allow missing or any valid days value (will be sanitized later)
                $drip_type = 'specific_days';
                break;
                
            case 'unlock_sequentially':
                // Sequential drip doesn't require item-level settings
                $drip_type = 'unlock_sequentially';
                break;
                
            default:
                return new WP_Error(
                    'invalid_course_drip_type',
                    __('Invalid course content drip type.', 'tutorpress'),
                    ['status' => 400]
                );
        }

        // Sanitize and validate settings based on drip type
        $sanitized_settings = $this->sanitize_content_drip_settings($settings, $drip_type);
        if (is_wp_error($sanitized_settings)) {
            return $sanitized_settings;
        }

        // Save each setting individually using the helper functions
        $saved_settings = [];
        foreach ($sanitized_settings as $key => $value) {
            if ($value !== null) {
                $result = set_item_content_drip_settings($post_id, $key, $value);
                // Always include the setting in saved_settings if no error occurred
                // update_post_meta() returns false when value hasn't changed, which is not an error
                if ($result !== false || get_item_content_drip_settings($post_id, $key) === $value) {
                    $saved_settings[$key] = $value;
                }
            } else {
                // For null values, remove the setting from the _content_drip_settings array
                $content_drip_settings = get_post_meta($post_id, '_content_drip_settings', true);
                if (!is_array($content_drip_settings)) {
                    $content_drip_settings = array();
                }
                
                // Remove the specific key from the settings array
                unset($content_drip_settings[$key]);
                
                // Update the meta with the modified array
                update_post_meta($post_id, '_content_drip_settings', $content_drip_settings);
                
                // Include null in saved_settings to indicate the field was cleared
                $saved_settings[$key] = null;
            }
        }

        // Get course ID for response
        $course_id = null;
        if (function_exists('tutor_utils') && tutor_utils()) {
            $course_id = tutor_utils()->get_course_id_by_content($post_id);
        }

        return rest_ensure_response($this->format_response([
            'post_id' => $post_id,
            'course_id' => $course_id,
            'settings_saved' => !empty($saved_settings),
            'settings' => $saved_settings,
        ], __('Content drip settings saved successfully.', 'tutorpress')));
    }

    /**
     * Get available prerequisites for a course.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_prerequisites($request) {
        $course_id = (int) $request->get_param('course_id');
        $exclude_post_id = (int) $request->get_param('exclude_post_id');

        // Validate the course
        $validation = $this->validate_course_id($course_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Check if content drip is enabled and uses prerequisites
        $content_drip_enabled = (bool) get_tutor_course_settings($course_id, 'enable_content_drip');
        if (!$content_drip_enabled) {
            return new WP_Error(
                'content_drip_disabled',
                __('Content drip is not enabled for this course.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Get all topics for the course
        $prerequisites_by_topic = [];
        if (function_exists('tutor_utils') && tutor_utils()) {
            $topics = tutor_utils()->get_topics($course_id);
            
            if ($topics && $topics->posts) {
                foreach ($topics->posts as $topic) {
                    $topic_items = tutor_utils()->get_course_contents_by_topic($topic->ID, -1);
                    $items = [];
                    
                    if ($topic_items && $topic_items->posts) {
                        foreach ($topic_items->posts as $item) {
                            // Skip the item we're excluding (current item being edited)
                            if ($exclude_post_id && $item->ID === $exclude_post_id) {
                                continue;
                            }
                            
                            $items[] = [
                                'id' => $item->ID,
                                'title' => $item->post_title,
                                'type' => $item->post_type,
                                'topic_id' => $topic->ID,
                                'topic_title' => $topic->post_title,
                                'type_label' => $this->get_content_type_label($item->post_type),
                            ];
                        }
                    }
                    
                    // Only include topics that have items
                    if (!empty($items)) {
                        $prerequisites_by_topic[] = [
                            'topic_id' => $topic->ID,
                            'topic_title' => $topic->post_title,
                            'items' => $items,
                        ];
                    }
                }
            }
        }

        // Calculate total count
        $total_count = 0;
        foreach ($prerequisites_by_topic as $topic) {
            $total_count += count($topic['items']);
        }

        return rest_ensure_response($this->format_response([
            'course_id' => $course_id,
            'prerequisites' => $prerequisites_by_topic,
            'total_count' => $total_count,
        ], __('Prerequisites retrieved successfully.', 'tutorpress')));
    }

    /**
     * Check read permissions for content drip endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_read_permission($request) {
        $post_id = (int) $request->get_param('post_id');

        // Check if user can edit the specific post
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this content\'s drip settings.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check write permissions for content drip endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_write_permission($request) {
        $post_id = (int) $request->get_param('post_id');

        // Check if user can edit the specific post
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit this content\'s drip settings.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check read permissions for course-based endpoints.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_course_read_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this course\'s content.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Validate a content post (lesson or assignment).
     *
     * @since 1.0.0
     * @param int $post_id The post ID to validate.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    private function validate_content_post($post_id) {
        // Check if post exists
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error(
                'invalid_post_id',
                sprintf(__('Content with ID %d does not exist.', 'tutorpress'), $post_id),
                ['status' => 404]
            );
        }

        // Check if it's a valid content type for content drip
        $valid_types = ['lesson', 'tutor_assignments'];
        if (!in_array($post->post_type, $valid_types, true)) {
            return new WP_Error(
                'invalid_content_type',
                sprintf(
                    __('Post ID %d is not a valid content type for content drip (found: %s, expected: %s).', 'tutorpress'),
                    $post_id,
                    $post->post_type,
                    implode(', ', $valid_types)
                ),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate a course ID.
     *
     * @since 1.0.0
     * @param int $course_id The course ID to validate.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    private function validate_course_id($course_id) {
        // Check if course exists
        $course = get_post($course_id);
        if (!$course) {
            return new WP_Error(
                'invalid_course_id',
                sprintf(__('Course with ID %d does not exist.', 'tutorpress'), $course_id),
                ['status' => 404]
            );
        }

        // Check if it's actually a course
        if ($course->post_type !== tutor()->course_post_type) {
            return new WP_Error(
                'invalid_course_type',
                sprintf(
                    __('Post ID %d exists but is not a course (found type: %s).', 'tutorpress'),
                    $course_id,
                    $course->post_type
                ),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Sanitize content drip settings based on drip type.
     *
     * @since 1.0.0
     * @param array  $settings  The settings to sanitize.
     * @param string $drip_type The content drip type.
     * @return array|WP_Error Sanitized settings or error.
     */
    private function sanitize_content_drip_settings($settings, $drip_type) {
        $sanitized = [];

        switch ($drip_type) {
            case 'unlock_by_date':
                if (isset($settings['unlock_date'])) {
                    $date = sanitize_text_field($settings['unlock_date']);
                    if (empty($date)) {
                        $sanitized['unlock_date'] = null;
                    } else {
                        // Validate date format
                        $parsed_date = strtotime($date);
                        if ($parsed_date === false) {
                            return new WP_Error(
                                'invalid_date_format',
                                __('Invalid date format for unlock_date.', 'tutorpress'),
                                ['status' => 400]
                            );
                        }
                        $sanitized['unlock_date'] = $date;
                    }
                }
                break;

            case 'specific_days':
                if (isset($settings['after_xdays_of_enroll'])) {
                    $days = (int) $settings['after_xdays_of_enroll'];
                    if ($days < 0) {
                        return new WP_Error(
                            'invalid_days_value',
                            __('Days after enrollment must be a non-negative number.', 'tutorpress'),
                            ['status' => 400]
                        );
                    }
                    $sanitized['after_xdays_of_enroll'] = $days;
                } else {
                    // Default to null if not provided (empty field)
                    $sanitized['after_xdays_of_enroll'] = null;
                }
                break;

            case 'after_finishing_prerequisites':
                if (isset($settings['prerequisites'])) {
                    $prerequisites = $settings['prerequisites'];
                    if (!is_array($prerequisites)) {
                        return new WP_Error(
                            'invalid_prerequisites_format',
                            __('Prerequisites must be an array of post IDs.', 'tutorpress'),
                            ['status' => 400]
                        );
                    }
                    
                    // Sanitize and validate each prerequisite ID
                    $sanitized_prerequisites = [];
                    foreach ($prerequisites as $prereq_id) {
                        $prereq_id = (int) $prereq_id;
                        if ($prereq_id > 0) {
                            // Check if post exists (regardless of status or permissions)
                            $post = get_post($prereq_id);
                            if ($post && !is_wp_error($post)) {
                                $sanitized_prerequisites[] = $prereq_id;
                            }
                        }
                    }
                    $sanitized['prerequisites'] = $sanitized_prerequisites;
                } else {
                    // Default to empty array if not provided
                    $sanitized['prerequisites'] = [];
                }
                break;

            case 'unlock_sequentially':
                // No settings needed for sequential unlock
                break;

            default:
                return new WP_Error(
                    'invalid_drip_type',
                    sprintf(__('Invalid content drip type: %s', 'tutorpress'), $drip_type),
                    ['status' => 400]
                );
        }

        return $sanitized;
    }

    /**
     * Get human-readable label for content type.
     *
     * @since 1.0.0
     * @param string $post_type The post type.
     * @return string Human-readable label.
     */
    private function get_content_type_label($post_type) {
        $labels = [
            'lesson' => __('Lesson', 'tutorpress'),
            'tutor_quiz' => __('Quiz', 'tutorpress'),
            'tutor_assignments' => __('Assignment', 'tutorpress'),
            'tutor_zoom_meeting' => __('Zoom Meeting', 'tutorpress'),
            'tutor-google-meet' => __('Google Meet', 'tutorpress'),
        ];

        return isset($labels[$post_type]) ? $labels[$post_type] : ucfirst($post_type);
    }

    /**
     * Get course-level content drip settings (lightweight endpoint).
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_course_content_drip_settings($request) {
        $course_id = (int) $request->get_param('course_id');

        // Basic validation - just check if course ID is positive
        if ($course_id <= 0) {
            return new WP_Error(
                'invalid_course_id',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Direct post meta access for maximum performance (bypass all helper functions)
        $course_settings = get_post_meta($course_id, '_tutor_course_settings', true);
        if (!is_array($course_settings)) {
            $course_settings = [];
        }
        
        $content_drip_enabled = (bool) ($course_settings['enable_content_drip'] ?? false);
        $content_drip_type = $course_settings['content_drip_type'] ?? 'unlock_by_date';
        
        // Default to unlock_by_date if type is not set
        if (empty($content_drip_type)) {
            $content_drip_type = 'unlock_by_date';
        }

        $response_data = [
            'enabled' => $content_drip_enabled,
            'type'    => $content_drip_type,
        ];

                return rest_ensure_response($this->format_response([
            'course_id' => $course_id,
            'content_drip' => $response_data,
        ], __('Course content drip settings retrieved successfully.', 'tutorpress')));
    }
} 