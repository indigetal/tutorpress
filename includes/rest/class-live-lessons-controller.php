<?php
/**
 * Live Lessons REST Controller Class
 *
 * Handles REST API functionality for Live Lessons (Google Meet and Zoom integration).
 *
 * @package TutorPress
 * @since 1.5.0
 */

defined('ABSPATH') || exit;

// Import DateTime class for date formatting
use DateTime;

class TutorPress_REST_Live_Lessons_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 1.5.0
     */
    public function __construct() {
        $this->rest_base = 'live-lessons';
    }

    /**
     * Register REST API routes.
     *
     * @since 1.5.0
     * @return void
     */
    public function register_routes() {
        // Get live lessons for a topic or course
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'course_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get live lessons for.', 'tutorpress'),
                        ],
                        'topic_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic to get live lessons for.', 'tutorpress'),
                        ],
                        'type' => [
                            'type'              => 'string',
                            'enum'              => ['google_meet', 'zoom'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Filter by live lesson type.', 'tutorpress'),
                        ],
                        'status' => [
                            'type'              => 'string',
                            'enum'              => ['scheduled', 'live', 'ended', 'cancelled'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Filter by live lesson status.', 'tutorpress'),
                        ],
                        'per_page' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'default'           => 10,
                            'minimum'           => 1,
                            'maximum'           => 100,
                            'description'       => __('Number of results per page.', 'tutorpress'),
                        ],
                        'page' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'default'           => 1,
                            'minimum'           => 1,
                            'description'       => __('Page number for pagination.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_item'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'course_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course for the live lesson.', 'tutorpress'),
                        ],
                        'topic_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic for the live lesson.', 'tutorpress'),
                        ],
                        'title' => [
                            'required'          => true,
                            'type'             => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the live lesson.', 'tutorpress'),
                        ],
                        'description' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The description of the live lesson.', 'tutorpress'),
                            'default'           => '',
                        ],
                        'type' => [
                            'required'          => true,
                            'type'             => 'string',
                            'enum'              => ['google_meet', 'zoom'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The type of live lesson.', 'tutorpress'),
                        ],
                        'start_date_time' => [
                            'required'          => true,
                            'type'             => 'string',
                            'format'           => 'date-time',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Start date and time in ISO 8601 format.', 'tutorpress'),
                        ],
                        'end_date_time' => [
                            'required'          => true,
                            'type'             => 'string',
                            'format'           => 'date-time',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('End date and time in ISO 8601 format.', 'tutorpress'),
                        ],
                        'settings' => [
                            'type'              => 'object',
                            'description'       => __('Live lesson settings.', 'tutorpress'),
                            'properties'        => [
                                'timezone' => [
                                    'type'    => 'string',
                                    'default' => 'UTC',
                                ],
                                'duration' => [
                                    'type'    => 'integer',
                                    'minimum' => 1,
                                    'default' => 60,
                                ],
                                'allow_early_join' => [
                                    'type'    => 'boolean',
                                    'default' => true,
                                ],
                                'auto_record' => [
                                    'type'    => 'boolean',
                                    'default' => false,
                                ],
                                'require_password' => [
                                    'type'    => 'boolean',
                                    'default' => false,
                                ],
                                'waiting_room' => [
                                    'type'    => 'boolean',
                                    'default' => false,
                                ],
                            ],
                            'default'           => [],
                        ],
                        'provider_config' => [
                            'type'              => 'object',
                            'description'       => __('Provider-specific configuration.', 'tutorpress'),
                            'default'           => [],
                        ],
                    ],
                ],
            ]
        );

        // Zoom users endpoint for Meeting Host dropdown
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/zoom/users',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_zoom_users'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'course_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Course ID for collaborative instructor Zoom access.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Google Meet settings endpoint
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/google-meet/settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_google_meet_settings'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

        // Single live lesson operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                    'args'               => [
                        'id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the live lesson.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => 'PATCH',
                    'callback'            => [$this, 'update_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                    'args'               => [
                        'title' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the live lesson.', 'tutorpress'),
                        ],
                        'description' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The description of the live lesson.', 'tutorpress'),
                        ],
                        'start_date_time' => [
                            'type'             => 'string',
                            'format'           => 'date-time',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Start date and time in ISO 8601 format.', 'tutorpress'),
                        ],
                        'end_date_time' => [
                            'type'             => 'string',
                            'format'           => 'date-time',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('End date and time in ISO 8601 format.', 'tutorpress'),
                        ],
                        'status' => [
                            'type'              => 'string',
                            'enum'              => ['scheduled', 'live', 'ended', 'cancelled'],
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The status of the live lesson.', 'tutorpress'),
                        ],
                        'settings' => [
                            'type'              => 'object',
                            'description'       => __('Live lesson settings.', 'tutorpress'),
                        ],
                        'provider_config' => [
                            'type'              => 'object',
                            'description'       => __('Provider-specific configuration.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                ],
            ]
        );

        // Duplicate live lesson
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'duplicate_item'],
                    'permission_callback' => [$this, 'check_lesson_permission'],
                    'args'               => [
                        'topic_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Target topic ID for the duplicated lesson.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Check permission for live lesson operations.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_lesson_permission($request) {
        // For now, use the base permission check
        // In future, this could include addon-specific permissions
        return $this->check_permission($request);
    }

    /**
     * Get live lessons.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_items($request) {
        // For now, return mock data that matches our TypeScript interfaces
        $course_id = $request->get_param('course_id');
        $topic_id = $request->get_param('topic_id');
        $type = $request->get_param('type');
        $status = $request->get_param('status');
        $per_page = $request->get_param('per_page') ?: 10;
        $page = $request->get_param('page') ?: 1;

        // Mock live lessons data
        $mock_lessons = $this->get_mock_live_lessons();

        // Apply basic filtering
        $filtered_lessons = array_filter($mock_lessons, function($lesson) use ($course_id, $topic_id, $type, $status) {
            if ($course_id && $lesson['courseId'] !== $course_id) {
                return false;
            }
            if ($topic_id && $lesson['topicId'] !== $topic_id) {
                return false;
            }
            if ($type && $lesson['type'] !== $type) {
                return false;
            }
            if ($status && $lesson['status'] !== $status) {
                return false;
            }
            return true;
        });

        // Apply pagination
        $total = count($filtered_lessons);
        $offset = ($page - 1) * $per_page;
        $paged_lessons = array_slice($filtered_lessons, $offset, $per_page);

        $response_data = [
            'data' => array_values($paged_lessons),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page),
            ],
        ];

        return rest_ensure_response($this->format_response($response_data, __('Live lessons retrieved successfully.', 'tutorpress')));
    }

    /**
     * Get course instructor IDs (including co-instructors)
     * Reused from H5P controller for collaborative access
     *
     * @param int $course_id Course ID
     * @return array Array of instructor user IDs
     */
    private function get_course_instructor_ids($course_id) {
        if (!function_exists('tutor_utils')) {
            return [];
        }

        // Get course instructors (includes main instructor and co-instructors)
        $instructors = tutor_utils()->get_instructors_by_course($course_id);
        
        if (empty($instructors)) {
            return [];
        }

        $instructor_ids = [];
        foreach ($instructors as $instructor) {
            if (isset($instructor->ID)) {
                $instructor_ids[] = (int) $instructor->ID;
            }
        }

        return array_unique($instructor_ids);
    }

    /**
     * Get Zoom users for Meeting Host dropdown.
     * 
     * Integrates with Tutor LMS Zoom addon to fetch available Zoom users
     * using the same API credentials and methods that Tutor LMS uses.
     *
     * @since 1.5.6
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_zoom_users($request) {
        // Check if Tutor LMS Zoom addon is available
        if (!class_exists('TUTOR_ZOOM\Zoom')) {
            return new WP_Error(
                'zoom_addon_not_available',
                __('Zoom addon is not available. Please ensure Tutor LMS Pro with Zoom addon is installed and activated.', 'tutorpress'),
                ['status' => 503]
            );
        }

        try {
            $course_id = $request->get_param('course_id') ?? 0;
            
            // Determine which instructors' Zoom configurations to check
            $instructor_ids = [get_current_user_id()]; // Start with current user
            
            // If course context is provided, add collaborative access
            if ($course_id > 0) {
                $course_instructors = $this->get_course_instructor_ids($course_id);
                if (!empty($course_instructors)) {
                    $instructor_ids = array_unique(array_merge($instructor_ids, $course_instructors));
                }
            }
            
            // Collect Zoom users from all instructors' configurations
            $all_zoom_users = [];
            $working_instructor_count = 0;
            
            foreach ($instructor_ids as $instructor_id) {
                // Get instructor's Zoom API credentials
                $api_data = json_decode(get_user_meta($instructor_id, 'tutor_zoom_api', true), true);
                
                // Skip instructors without Zoom configuration
                if (empty($api_data) || empty($api_data['api_key']) || empty($api_data['api_secret'])) {
                    continue;
                }
                
                // Initialize Zoom instance with this instructor's credentials
                $zoom_instance = new \TUTOR_ZOOM\Zoom(false);
                
                // Temporarily set the API credentials for this instructor
                $original_user_id = get_current_user_id();
                wp_set_current_user($instructor_id);
                
                // Get Zoom users for this instructor
                $instructor_zoom_users = $zoom_instance->tutor_zoom_get_users();
                
                // Restore original user
                wp_set_current_user($original_user_id);
                
                if (!empty($instructor_zoom_users)) {
                    $working_instructor_count++;
                    
                    // Add instructor context to each user
                    foreach ($instructor_zoom_users as &$user) {
                        $user['instructor_id'] = $instructor_id;
                        $instructor_info = get_userdata($instructor_id);
                        $user['instructor_name'] = $instructor_info ? $instructor_info->display_name : '';
                    }
                    
                    $all_zoom_users = array_merge($all_zoom_users, $instructor_zoom_users);
                }
            }
            
            // Check if any instructors have working Zoom configurations
            if ($working_instructor_count === 0) {
                $error_message = $course_id > 0 
                    ? __('No Zoom API credentials configured for course instructors. At least one instructor needs to configure Zoom API settings.', 'tutorpress')
                    : __('Zoom API credentials are not configured. Please configure your Zoom API settings in Tutor LMS.', 'tutorpress');
                    
                return new WP_Error(
                    'zoom_api_not_configured',
                    $error_message,
                    ['status' => 400]
                );
            }
            
            if (empty($all_zoom_users)) {
                return new WP_Error(
                    'no_zoom_users',
                    __('No Zoom users found. Please check Zoom API credentials and account settings.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Remove duplicates based on Zoom user ID and format for frontend
            $formatted_users = [];
            $seen_user_ids = [];
            
            foreach ($all_zoom_users as $user) {
                $first_name = $user['first_name'] ?? '';
                $last_name = $user['last_name'] ?? '';
                $email = $user['email'] ?? '';
                $id = $user['id'] ?? '';
                $instructor_name = $user['instructor_name'] ?? '';
                
                // Skip users with missing essential data
                if (empty($id) || empty($email)) {
                    continue;
                }
                
                // Skip duplicates (same Zoom user from multiple instructor accounts)
                if (in_array($id, $seen_user_ids)) {
                    continue;
                }
                $seen_user_ids[] = $id;
                
                $formatted_users[] = [
                    'id' => $id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'display_name' => trim($first_name . ' ' . $last_name),
                    'instructor_name' => $instructor_name, // For potential future use
                ];
            }

            $response_data = [
                'users' => $formatted_users,
                'total' => count($formatted_users),
                'api_configured' => true,
            ];

            return rest_ensure_response($this->format_response($response_data, __('Zoom users retrieved successfully.', 'tutorpress')));

        } catch (Exception $e) {
            error_log('TutorPress: Zoom API Error: ' . $e->getMessage());
            
            return new WP_Error(
                'zoom_api_error',
                sprintf(
                    __('Failed to retrieve Zoom users: %s', 'tutorpress'),
                    $e->getMessage()
                ),
                ['status' => 500]
            );
        }
    }

    /**
     * Get Google Meet settings and authorization status.
     * 
     * Checks if user has configured Google Meet and returns relevant settings
     * for the frontend Live Lessons form.
     *
     * @since 1.5.6
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_google_meet_settings($request) {
        // Check if Google Meet addon is available
        if (!class_exists('TutorPro\GoogleMeet\GoogleEvent\GoogleEvent')) {
            return new WP_Error(
                'google_meet_addon_not_available',
                __('Google Meet addon is not available. Please ensure Tutor LMS Pro with Google Meet addon is installed and activated.', 'tutorpress'),
                ['status' => 503]
            );
        }

        try {
            // Initialize Google Meet client (same as Tutor LMS)
            $google_client = new \TutorPro\GoogleMeet\GoogleEvent\GoogleEvent();
            
            // Check authorization status
            $is_authorized = $google_client->is_app_permitted();
            $has_credentials = $google_client->is_credential_loaded();
            
            // Get user settings
            $user_settings = maybe_unserialize(get_user_meta(get_current_user_id(), \TutorPro\GoogleMeet\Settings\Settings::META_KEY, true));
            if (!$user_settings) {
                $user_settings = [];
            }
            
            // Get default settings structure
            $default_settings = \TutorPro\GoogleMeet\Settings\Settings::default_settings();
            
            // Merge user settings with defaults
            $formatted_settings = [];
            foreach ($default_settings as $setting) {
                $key = $setting['name'];
                $value = $user_settings[$key] ?? $setting['default_value'];
                
                $formatted_settings[$key] = [
                    'value' => $value,
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'options' => $setting['options'] ?? null,
                ];
            }

            $response_data = [
                'is_authorized' => $is_authorized,
                'has_credentials' => $has_credentials,
                'settings' => $formatted_settings,
                'authorization_url' => $is_authorized ? null : ($has_credentials ? $google_client->get_consent_screen_url() : null),
                'setup_url' => admin_url('admin.php?page=google-meet&tab=set-api'),
            ];

            return rest_ensure_response($this->format_response($response_data, __('Google Meet settings retrieved successfully.', 'tutorpress')));

        } catch (Exception $e) {
            error_log('TutorPress: Google Meet Settings Error: ' . $e->getMessage());
            
            return new WP_Error(
                'google_meet_settings_error',
                sprintf(
                    __('Failed to retrieve Google Meet settings: %s', 'tutorpress'),
                    $e->getMessage()
                ),
                ['status' => 500]
            );
        }
    }

    /**
     * Get a single live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request) {
        // Check Tutor LMS availability
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        $lesson_id = (int) $request->get_param('id');
        
        // Get the post
        $post = get_post($lesson_id);
        if (!$post) {
            return new WP_Error(
                'live_lesson_not_found',
                __('Live lesson not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Verify this is a live lesson post type
        if (!in_array($post->post_type, ['tutor-google-meet', 'tutor_zoom_meeting'])) {
            return new WP_Error(
                'invalid_post_type',
                __('Invalid post type. Must be a live lesson.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Check permissions - user must be able to edit the course
        $topic_id = $post->post_parent;
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return new WP_Error(
                'invalid_topic',
                __('Invalid topic for this live lesson.', 'tutorpress'),
                ['status' => 404]
            );
        }

        $course_id = $topic->post_parent;
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_cannot_read',
                __('Sorry, you are not allowed to view this live lesson.', 'tutorpress'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Determine live lesson type
        $type = $post->post_type === 'tutor-google-meet' ? 'google_meet' : 'zoom';

        // Prepare base response data
        $response_data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'type' => $type,
            'topicId' => $topic_id,
            'courseId' => $course_id,
            'status' => 'scheduled', // Default status
            'createdAt' => $post->post_date_gmt,
            'updatedAt' => $post->post_modified_gmt,
        ];

        // Get provider-specific data
        if ($type === 'google_meet') {
            // Get Google Meet meta fields
            $start_datetime = get_post_meta($post->ID, 'tutor-google-meet-start-datetime', true);
            $end_datetime = get_post_meta($post->ID, 'tutor-google-meet-end-datetime', true);
            $event_details_json = get_post_meta($post->ID, 'tutor-google-meet-event-details', true);
            $meet_link = get_post_meta($post->ID, 'tutor-google-meet-link', true);

            // Parse event details
            $event_details = [];
            if ($event_details_json) {
                $event_details = json_decode($event_details_json, true) ?: [];
            }

            // Set datetime fields
            $response_data['startDateTime'] = $start_datetime ?: '';
            $response_data['endDateTime'] = $end_datetime ?: '';

            // Extract settings from event details
            $response_data['settings'] = [
                'timezone' => $event_details['timezone'] ?? 'UTC',
                'add_enrolled_students' => $event_details['attendees'] ?? 'No',
            ];

            // Add provider config
            $response_data['providerConfig'] = [
                'meetingUrl' => $meet_link ?: '',
                'eventId' => $event_details['id'] ?? '',
            ];

            // Add meet link if available
            if ($meet_link) {
                $response_data['meetingUrl'] = $meet_link;
            }

        } else {
            // Get Zoom meta fields
            $start_date = get_post_meta($post->ID, '_tutor_zm_start_date', true);
            $start_datetime = get_post_meta($post->ID, '_tutor_zm_start_datetime', true);
            $duration = get_post_meta($post->ID, '_tutor_zm_duration', true);
            $duration_unit = get_post_meta($post->ID, '_tutor_zm_duration_unit', true);
            $zoom_data_json = get_post_meta($post->ID, '_tutor_zm_data', true);
            $course_id_meta = get_post_meta($post->ID, '_tutor_zm_for_course', true);
            $topic_id_meta = get_post_meta($post->ID, '_tutor_zm_for_topic', true);

            // Parse Zoom meeting data
            $zoom_data = [];
            if ($zoom_data_json) {
                $zoom_data = json_decode($zoom_data_json, true) ?: [];
            }

            // Calculate end datetime from start + duration
            $end_datetime = '';
            if ($start_datetime && $duration) {
                try {
                    $start_obj = new DateTime($start_datetime);
                    $duration_minutes = $duration_unit === 'hr' ? $duration * 60 : $duration;
                    $start_obj->add(new DateInterval('PT' . $duration_minutes . 'M'));
                    $end_datetime = $start_obj->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    error_log('TutorPress: Error calculating end datetime: ' . $e->getMessage());
                }
            }

            // Set datetime fields
            $response_data['startDateTime'] = $start_datetime ?: '';
            $response_data['endDateTime'] = $end_datetime;

            // Extract settings from Zoom data
            $zoom_settings = $zoom_data['settings'] ?? [];
            $response_data['settings'] = [
                'timezone' => $zoom_data['timezone'] ?? 'UTC',
                'duration' => (int) $duration ?: 60,
                'allow_early_join' => $zoom_settings['join_before_host'] ?? false,
                'waiting_room' => $zoom_settings['waiting_room'] ?? false,
                'require_password' => !empty($zoom_data['password']),
            ];

            // Add provider config
            $response_data['providerConfig'] = [
                'host' => $zoom_data['host_id'] ?? '',
                'password' => $zoom_data['password'] ?? '',
                'autoRecording' => $zoom_settings['auto_recording'] ?? 'none',
                'meetingId' => $zoom_data['id'] ?? '',
                'joinUrl' => $zoom_data['join_url'] ?? '',
            ];

            // Add meeting URL if available
            if (!empty($zoom_data['join_url'])) {
                $response_data['meetingUrl'] = $zoom_data['join_url'];
            }
        }

        return rest_ensure_response($this->format_response($response_data, __('Live lesson retrieved successfully.', 'tutorpress')));
    }

    /**
     * Create a new live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function create_item($request) {
        // Check Tutor LMS availability
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        // Extract and validate parameters
        $course_id = (int) $request->get_param('course_id');
        $topic_id = (int) $request->get_param('topic_id');
        $title = $request->get_param('title');
        $description = $request->get_param('description') ?: '';
        $type = $request->get_param('type');
        $start_date_time = $request->get_param('start_date_time');
        $end_date_time = $request->get_param('end_date_time');
        $settings = $request->get_param('settings') ?: [];
        $provider_config = $request->get_param('provider_config') ?: [];

        // Validate live lesson type first
        if (!in_array($type, ['google_meet', 'zoom'])) {
            return new WP_Error(
                'invalid_live_lesson_type',
                __('Invalid live lesson type. Must be either "google_meet" or "zoom".', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Check if the requested addon is available and enabled
        if ($type === 'google_meet' && !TutorPress_Addon_Checker::is_google_meet_enabled()) {
            return new WP_Error(
                'google_meet_addon_disabled',
                __('Google Meet addon is not available or disabled. Please enable the Google Meet addon to create Google Meet live lessons.', 'tutorpress'),
                ['status' => 400]
            );
        }

        if ($type === 'zoom' && !TutorPress_Addon_Checker::is_zoom_enabled()) {
            return new WP_Error(
                'zoom_addon_disabled',
                __('Zoom addon is not available or disabled. Please enable the Zoom addon to create Zoom live lessons.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Verify topic exists and belongs to the course
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics' || $topic->post_parent !== $course_id) {
            return new WP_Error(
                'invalid_topic',
                __('Invalid topic ID or topic does not belong to the specified course.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check if user has permission to edit the course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_cannot_create',
                __('Sorry, you are not allowed to create live lessons in this course.', 'tutorpress'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Default settings
        $default_settings = [
            'timezone' => 'UTC',
            'duration' => 60,
            'allow_early_join' => true,
            'auto_record' => false,
            'require_password' => false,
            'waiting_room' => false,
        ];
        $settings = array_merge($default_settings, $settings);

        // Get next menu order
        $existing_lessons = get_posts([
            'post_type'      => $type === 'google_meet' ? 'tutor-google-meet' : 'tutor_zoom_meeting',
            'post_parent'    => $topic_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $menu_order = count($existing_lessons);

        // Prepare post data
        $post_data = [
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_type'    => $type === 'google_meet' ? 'tutor-google-meet' : 'tutor_zoom_meeting',
            'post_parent'  => $topic_id,
            'menu_order'   => $menu_order,
        ];

        // Insert the post
        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new WP_Error(
                'live_lesson_creation_failed',
                __('Failed to create live lesson.', 'tutorpress'),
                ['status' => 500]
            );
        }

        // Store meta data based on type
        if ($type === 'google_meet') {
            // Google Meet meta fields based on EventsModel::POST_META_KEYS
            // Frontend now sends datetime in Y-m-d H:i:s format exactly as user entered it
            // No timezone conversion needed - store exactly what user selected
            $formatted_start_datetime = $start_date_time;
            $formatted_end_datetime = $end_date_time;
            
            update_post_meta($post_id, 'tutor-google-meet-start-datetime', $formatted_start_datetime);
            update_post_meta($post_id, 'tutor-google-meet-end-datetime', $formatted_end_datetime);
            
            // Integrate with Google Calendar API exactly like Tutor LMS does
            // Check if user has Google Meet configured in Tutor LMS
            if (!class_exists('TutorPro\GoogleMeet\GoogleEvent\GoogleEvent')) {
                return new WP_Error(
                    'google_meet_addon_not_available',
                    __('Google Meet addon is not available. Please ensure Tutor LMS Pro with Google Meet addon is installed and activated.', 'tutorpress'),
                    ['status' => 503]
                );
            }
            
            try {
                // Initialize Google Meet client (same as Tutor LMS)
                $google_client = new \TutorPro\GoogleMeet\GoogleEvent\GoogleEvent();
                
                // Check if user has authorized Google Meet
                if (!$google_client->is_app_permitted()) {
                    return new WP_Error(
                        'google_meet_not_authorized',
                        __('Google Meet is not authorized. Please configure Google Meet API credentials in Tutor LMS.', 'tutorpress'),
                        ['status' => 400]
                    );
                }
                
                // Get user's Google Meet settings (same as Tutor LMS)
                $user_settings = maybe_unserialize(get_user_meta(get_current_user_id(), \TutorPro\GoogleMeet\Settings\Settings::META_KEY, true));
                if (!$user_settings) {
                    $user_settings = [];
                }
                
                // Prepare attendees if requested
                $attendees = [];
                if (!empty($settings['add_enrolled_students']) && $settings['add_enrolled_students'] === 'Yes') {
                    $students = tutor_utils()->get_students_data_by_course_id($course_id, 'ID', true);
                    foreach ($students as $student) {
                        $attendees[] = [
                            'displayName' => $student->display_name ?: $student->user_login,
                            'email' => $student->user_email,
                            'responseStatus' => 'needsAction',
                        ];
                    }
                }
                
                // Create timezone object
                $timezone = new \DateTimeZone($settings['timezone']);
                $start_datetime_obj = new \DateTime($start_date_time, $timezone);
                $end_datetime_obj = new \DateTime($end_date_time, $timezone);
                
                // Create Google Calendar Event (exactly like Tutor LMS)
                $event = new \Google_Service_Calendar_Event([
                    'summary' => $title,
            'description' => $description,
                    'start' => [
                        'dateTime' => $start_datetime_obj->format('c'),
                        'timeZone' => $settings['timezone'],
                    ],
                    'end' => [
                        'dateTime' => $end_datetime_obj->format('c'),
                        'timeZone' => $settings['timezone'],
                    ],
                    'attendees' => $attendees,
                    'reminders' => [
                        'useDefault' => false,
                        'overrides' => [
                            [
                                'method' => 'email',
                                'minutes' => $user_settings['reminder_time'] ?? 30,
                            ],
                            [
                                'method' => 'popup',
                                'minutes' => $user_settings['reminder_time'] ?? 30,
                            ],
                        ],
                    ],
                    'sendUpdates' => $user_settings['send_updates'] ?? 'all',
                    'transparency' => $user_settings['transparency'] ?? 'transparent',
                    'visibility' => $user_settings['event_visibility'] ?? 'public',
                    'status' => $user_settings['event_status'] ?? 'confirmed',
                    'conferenceData' => [
                        'createRequest' => [
                            'requestId' => 'tutorpress_meet_' . microtime(true),
                        ],
                    ],
                ]);
                
                // Create the event via Google Calendar API
                $created_event = $google_client->service->events->insert(
                    $google_client->current_calendar, 
                    $event, 
                    ['conferenceDataVersion' => 1]
                );
                
                // Store event details (same structure as Tutor LMS)
                $event_details = [
                    'id' => $created_event->id,
                    'kind' => $created_event->kind,
                    'event_type' => $created_event->eventType,
                    'html_link' => $created_event->htmlLink,
                    'organizer' => $created_event->organizer,
                    'recurrence' => $created_event->recurrence,
                    'reminders' => $created_event->reminders,
                    'status' => $created_event->status,
                    'transparency' => $created_event->transparency,
                    'visibility' => $created_event->visibility,
                    'meet_link' => $created_event->hangoutLink,
                    'start_datetime' => $start_datetime_obj->format('Y-m-d H:i:s'),
                    'end_datetime' => $end_datetime_obj->format('Y-m-d H:i:s'),
                    'attendees' => !empty($settings['add_enrolled_students']) ? $settings['add_enrolled_students'] : 'No',
                    'timezone' => $settings['timezone'],
                ];
                
                // Store meta data using Tutor LMS structure
                update_post_meta($post_id, 'tutor-google-meet-event-details', json_encode($event_details));
                update_post_meta($post_id, 'tutor-google-meet-link', $created_event->hangoutLink);
                
                // Fire the same action as Tutor LMS (if it exists)
                do_action('tutor_google_meet_after_save_meeting', $post_id);
                
            } catch (Exception $e) {
                // If Google Calendar API fails, delete the post and return error
                wp_delete_post($post_id, true);
                
                return new WP_Error(
                    'google_meet_api_error',
                    sprintf(
                        __('Failed to create Google Meet event: %s', 'tutorpress'),
                        $e->getMessage()
                    ),
                    ['status' => 500]
                );
            }
        } else {
            // Zoom meta fields based on the Zoom class implementation
            // Frontend now sends datetime in Y-m-d H:i:s format exactly as user entered it
            // No timezone conversion needed - store exactly what user selected
            $formatted_start_datetime = $start_date_time;
            
            // Extract date part for _tutor_zm_start_date field
            $start_datetime_obj = new DateTime($start_date_time);
            $formatted_start_date = $start_datetime_obj->format('Y-m-d');
            
            update_post_meta($post_id, '_tutor_zm_start_date', $formatted_start_date);
            update_post_meta($post_id, '_tutor_zm_start_datetime', $formatted_start_datetime);
            update_post_meta($post_id, '_tutor_zm_duration', $settings['duration']);
            update_post_meta($post_id, '_tutor_zm_duration_unit', 'min');
            update_post_meta($post_id, '_tutor_zm_for_course', $course_id);
            update_post_meta($post_id, '_tutor_zm_for_topic', $topic_id);
            
            // Integrate with Zoom API exactly like Tutor LMS does
            // Get current user's Zoom API credentials (same as Tutor LMS)
            $user_id = get_current_user_id();
            if (current_user_can('administrator')) {
                $course = get_post($course_id);
                $user_id = $course->post_author;
            }
            
            $zoom_settings = json_decode(get_user_meta($user_id, 'tutor_zoom_api', true), true);
            $api_key = (!empty($zoom_settings['api_key'])) ? $zoom_settings['api_key'] : '';
            $api_secret = (!empty($zoom_settings['api_secret'])) ? $zoom_settings['api_secret'] : '';
            
            // Check if API credentials are configured
            if (empty($api_key) || empty($api_secret)) {
                return new WP_Error(
                    'zoom_api_not_configured',
                    __('Zoom API credentials are not configured. Please configure your Zoom API settings in Tutor LMS.', 'tutorpress'),
                    ['status' => 400]
                );
            }
            
            // Validate required Zoom fields (same as Tutor LMS)
            if (empty($provider_config['host'])) {
                return new WP_Error(
                    'zoom_host_required',
                    __('Meeting host is required for Zoom meetings.', 'tutorpress'),
                    ['status' => 400]
                );
            }
            
            $host_id = sanitize_text_field($provider_config['host']);
            $auto_recording = !empty($provider_config['autoRecording']) 
                ? $provider_config['autoRecording'] 
                : 'none';
            $password = ($settings['require_password'] && !empty($provider_config['password'])) 
                ? sanitize_text_field($provider_config['password']) 
                : '';
            
            // Prepare Zoom meeting data (same structure as Tutor LMS)
            $zoom_meeting_data = [
                'topic' => $title,
                'type' => 2, // Scheduled meeting
                'start_time' => $start_datetime_obj->format('Y-m-d\TH:i:s'),
                'timezone' => $settings['timezone'],
                'duration' => $settings['duration'],
                'password' => $password,
                'settings' => [
                    'join_before_host' => $settings['allow_early_join'],
                    'host_video' => false,
                    'participant_video' => false,
                    'mute_upon_entry' => false,
                    'auto_recording' => $auto_recording,
                    'enforce_login' => false,
                    'waiting_room' => $settings['waiting_room'],
                ],
            ];
            
            try {
                // Create Zoom meeting using Tutor LMS Zoom endpoint (exactly like Tutor LMS)
                $zoom_endpoint = tutor_utils()->get_package_object(true, '\Zoom\Endpoint\Meetings', $api_key, $api_secret);
                $saved_meeting = $zoom_endpoint->create($host_id, $zoom_meeting_data);
                
                // Normalize the Zoom API response to ensure consistent data structure
                // The Zoom API response might not match exactly what we sent
                if (is_array($saved_meeting)) {
                    // Ensure settings array exists and contains our values
                    if (!isset($saved_meeting['settings'])) {
                        $saved_meeting['settings'] = [];
                    }
                    
                    // Ensure auto_recording is set correctly from our input
                    $saved_meeting['settings']['auto_recording'] = $auto_recording;
                    
                    // Ensure other settings match our input
                    $saved_meeting['settings']['join_before_host'] = $settings['allow_early_join'];
                    $saved_meeting['settings']['waiting_room'] = $settings['waiting_room'];
                    
                    // Ensure password is set if provided
                    if (!empty($password)) {
                        $saved_meeting['password'] = $password;
                    }
                }
                
                // Store the normalized Zoom meeting data
                update_post_meta($post_id, '_tutor_zm_data', json_encode($saved_meeting));
                
                // Fire the same action as Tutor LMS
                do_action('tutor_zoom_after_save_meeting', $post_id);
                
            } catch (Exception $e) {
                // If Zoom API fails, delete the post and return error
                wp_delete_post($post_id, true);
                
                return new WP_Error(
                    'zoom_api_error',
                    sprintf(
                        __('Failed to create Zoom meeting: %s', 'tutorpress'),
                        $e->getMessage()
                    ),
                    ['status' => 500]
                );
            }
        }

        // Get the created live lesson
        $live_lesson = get_post($post_id);
        

        
        // Format response data
        $response_data = [
            'id' => $live_lesson->ID,
            'title' => $live_lesson->post_title,
            'description' => $live_lesson->post_content,
            'type' => $type,
            'topicId' => (int) $live_lesson->post_parent,
            'courseId' => $course_id,
            'startDateTime' => $start_date_time,
            'endDateTime' => $end_date_time,
            'settings' => $settings,
            'status' => 'scheduled',
            'createdAt' => $live_lesson->post_date_gmt,
            'updatedAt' => $live_lesson->post_modified_gmt,
        ];

        // Add provider config if provided
        if (!empty($provider_config)) {
            $response_data['providerConfig'] = $provider_config;
        }

        return rest_ensure_response($this->format_response($response_data, __('Live lesson created successfully.', 'tutorpress')));
    }

    /**
     * Update an existing live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function update_item($request) {
        // Check Tutor LMS availability
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        $lesson_id = (int) $request->get_param('id');
        
        // Verify lesson exists
        $post = get_post($lesson_id);
        if (!$post || !in_array($post->post_type, ['tutor-google-meet', 'tutor_zoom_meeting'])) {
            return new WP_Error(
                'invalid_live_lesson',
                __('Invalid live lesson ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check permissions - use general capability instead of post-specific
        // This fixes the issue where instructors can't edit their own Google Meet lessons
        // due to custom post type capability mapping issues
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'cannot_edit_live_lesson',
                __('You do not have permission to edit live lessons.', 'tutorpress'),
                ['status' => 403]
            );
        }

        try {
            // Prepare update data for core fields
            $update_data = ['ID' => $lesson_id];

            // Update post title if provided
            $title = $request->get_param('title');
            if ($title !== null) {
                $update_data['post_title'] = sanitize_text_field($title);
            }

            // Update post content/description if provided
            $description = $request->get_param('description');
            if ($description !== null) {
                $update_data['post_content'] = wp_kses_post($description);
            }

            // Update core post fields if any were provided
            if (count($update_data) > 1) {
                $result = wp_update_post($update_data, true);
                if (is_wp_error($result)) {
            return new WP_Error(
                        'live_lesson_update_failed',
                        __('Failed to update live lesson.', 'tutorpress'),
                        ['status' => 500]
                    );
                }
            }

            // Handle provider-specific updates
            // The frontend sends camelCase field names, so we need to check both camelCase and snake_case
            $provider_config = $request->get_param('providerConfig') ?: $request->get_param('provider_config');
            $start_date_time = $request->get_param('startDateTime') ?: $request->get_param('start_date_time');
            $end_date_time = $request->get_param('endDateTime') ?: $request->get_param('end_date_time');
            $settings = $request->get_param('settings');
            


            if ($post->post_type === 'tutor-google-meet') {
                $this->update_google_meet_lesson($lesson_id, $provider_config, $start_date_time, $end_date_time, $settings);
            } elseif ($post->post_type === 'tutor_zoom_meeting') {
                $this->update_zoom_lesson($lesson_id, $provider_config, $start_date_time, $end_date_time, $settings);
            }

            // Get updated lesson data for response
            $updated_lesson_response = $this->get_item($request);
            
            // Ensure the response includes success status and proper formatting
            if ($updated_lesson_response instanceof WP_REST_Response) {
                $response_data = $updated_lesson_response->get_data();
                if (isset($response_data['data'])) {
                    // Add updated timestamp
                    $response_data['data']['updatedAt'] = current_time('mysql', true);
                    $updated_lesson_response->set_data($response_data);
                }
            }
            
            return $updated_lesson_response;

        } catch (Exception $e) {
            return new WP_Error(
                'live_lesson_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function delete_item($request) {
        // Check Tutor LMS availability
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        $lesson_id = (int) $request->get_param('id');
        
        // Verify lesson exists
        $post = get_post($lesson_id);
        if (!$post || !in_array($post->post_type, ['tutor-google-meet', 'tutor_zoom_meeting'])) {
            return new WP_Error(
                'invalid_live_lesson',
                __('Invalid live lesson ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check permissions - use general capability instead of post-specific
        // This fixes the issue where instructors can't delete their own Google Meet lessons
        // due to custom post type capability mapping issues
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'cannot_delete_live_lesson',
                __('You do not have permission to delete live lessons.', 'tutorpress'),
                ['status' => 403]
            );
        }

        try {
            // Get lesson data before deletion for cleanup and response
            $lesson_data = [
                'id' => $lesson_id,
                'title' => $post->post_title,
                'type' => $post->post_type === 'tutor-google-meet' ? 'google_meet' : 'zoom',
                'topicId' => (int) $post->post_parent,
            ];

            // Perform provider-specific cleanup before deletion
            if ($post->post_type === 'tutor-google-meet') {
                $this->cleanup_google_meet_lesson($lesson_id);
            } elseif ($post->post_type === 'tutor_zoom_meeting') {
                $this->cleanup_zoom_lesson($lesson_id);
            }

            // Delete the WordPress post and all associated meta data
            $deleted = wp_delete_post($lesson_id, true); // true = force delete (bypass trash)

            if (!$deleted) {
                return new WP_Error(
                    'live_lesson_delete_failed',
                    __('Failed to delete live lesson.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Fire action for other plugins to hook into
            do_action('tutorpress_live_lesson_deleted', $lesson_id, $lesson_data);

            // Return success response
            $response_data = [
                'id' => $lesson_id,
                'deleted' => true,
                'type' => $lesson_data['type'],
                'title' => $lesson_data['title'],
            ];

            return rest_ensure_response($this->format_response($response_data, __('Live lesson deleted successfully.', 'tutorpress')));

        } catch (Exception $e) {
            return new WP_Error(
                'live_lesson_delete_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Clean up Google Meet lesson data before deletion.
     * 
     * This method handles provider-specific cleanup for Google Meet lessons.
     * Note: For now, this only cleans up local data. Future versions could
     * integrate with Google Calendar API to delete events if needed.
     *
     * @since 1.5.7
     * @param int $lesson_id The lesson post ID.
     */
    private function cleanup_google_meet_lesson($lesson_id) {
        // Get existing Google Meet data for logging/cleanup
        $event_details = get_post_meta($lesson_id, 'tutor-google-meet-event-details', true);
        $start_datetime = get_post_meta($lesson_id, 'tutor-google-meet-start-datetime', true);
        $end_datetime = get_post_meta($lesson_id, 'tutor-google-meet-end-datetime', true);

        // Log deletion for debugging (optional)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("TutorPress: Deleting Google Meet lesson {$lesson_id}");
            if ($event_details) {
                error_log("TutorPress: Google Meet event details: " . $event_details);
            }
        }

        // Note: All post meta will be automatically deleted by wp_delete_post()
        // This method is reserved for future provider API cleanup if needed
        
        // Future enhancement: Delete Google Calendar event
        // if (class_exists('TutorPress_Google_Meet_API') && $event_details) {
        //     $event_data = json_decode($event_details, true);
        //     if ($event_data && isset($event_data['event_id'])) {
        //         // Delete from Google Calendar
        //     }
        // }
    }

    /**
     * Clean up Zoom lesson data before deletion.
     * 
     * This method handles provider-specific cleanup for Zoom lessons.
     * Note: For now, this only cleans up local data. Future versions could
     * integrate with Zoom API to delete meetings if needed.
     *
     * @since 1.5.7
     * @param int $lesson_id The lesson post ID.
     */
    private function cleanup_zoom_lesson($lesson_id) {
        // Get existing Zoom data for logging/cleanup
        $zoom_data = get_post_meta($lesson_id, '_tutor_zm_data', true);
        $start_date = get_post_meta($lesson_id, '_tutor_zm_start_date', true);
        $start_datetime = get_post_meta($lesson_id, '_tutor_zm_start_datetime', true);
        $duration = get_post_meta($lesson_id, '_tutor_zm_duration', true);

        // Log deletion for debugging (optional)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("TutorPress: Deleting Zoom lesson {$lesson_id}");
            if ($zoom_data) {
                $zoom_info = is_string($zoom_data) ? json_decode($zoom_data, true) : $zoom_data;
                if ($zoom_info && isset($zoom_info['meeting_id'])) {
                    error_log("TutorPress: Zoom meeting ID: " . $zoom_info['meeting_id']);
                }
            }
        }

        // Note: All post meta will be automatically deleted by wp_delete_post()
        // This method is reserved for future provider API cleanup if needed
        
        // Future enhancement: Delete Zoom meeting
        // if (class_exists('TutorPress_Zoom_API') && $zoom_data) {
        //     $zoom_info = is_string($zoom_data) ? json_decode($zoom_data, true) : $zoom_data;
        //     if ($zoom_info && isset($zoom_info['meeting_id'])) {
        //         // Delete from Zoom via API
        //     }
        // }
    }

    /**
     * Duplicate a live lesson.
     *
     * Currently only supports Google Meet lessons as Zoom lessons don't have 
     * duplicate functionality in Tutor LMS frontend course builder.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function duplicate_item($request) {
        // Check Tutor LMS availability
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        $lesson_id = (int) $request->get_param('id');
        $target_topic_id = $request->get_param('topic_id');
        
        // Verify lesson exists
        $original_post = get_post($lesson_id);
        if (!$original_post || !in_array($original_post->post_type, ['tutor-google-meet', 'tutor_zoom_meeting'])) {
            return new WP_Error(
                'invalid_live_lesson',
                __('Invalid live lesson ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Only allow duplication of Google Meet lessons
        if ($original_post->post_type !== 'tutor-google-meet') {
            return new WP_Error(
                'duplication_not_supported',
                __('Duplication is only supported for Google Meet lessons.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Check permissions - use general capability instead of post-specific
        // This fixes the issue where instructors can't duplicate their own Google Meet lessons
        // due to custom post type capability mapping issues
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'cannot_duplicate_live_lesson',
                __('You do not have permission to duplicate live lessons.', 'tutorpress'),
                ['status' => 403]
            );
        }

        try {
            // Create duplicate post
            $duplicate_post_data = [
                'post_title' => $original_post->post_title . ' (Copy)',
                'post_content' => $original_post->post_content,
                'post_status' => 'publish',
                'post_type' => $original_post->post_type,
                'post_parent' => $target_topic_id ?: $original_post->post_parent,
                'menu_order' => $original_post->menu_order,
            ];

            $duplicate_id = wp_insert_post($duplicate_post_data);

            if (is_wp_error($duplicate_id)) {
                return new WP_Error(
                    'duplicate_creation_failed',
                    __('Failed to create duplicate live lesson.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Copy Google Meet specific meta data
            $this->duplicate_google_meet_meta($lesson_id, $duplicate_id);

            // Get the duplicated lesson data for response
            $duplicate_lesson_data = $this->get_live_lesson_data_for_response($duplicate_id);

            if (!$duplicate_lesson_data) {
                return new WP_Error(
                    'duplicate_data_error',
                    __('Duplicate created but failed to retrieve data.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Fire action for other plugins to hook into
            do_action('tutorpress_live_lesson_duplicated', $duplicate_id, $lesson_id, $duplicate_lesson_data);

            return rest_ensure_response($this->format_response($duplicate_lesson_data, __('Google Meet lesson duplicated successfully.', 'tutorpress')));

        } catch (Exception $e) {
            return new WP_Error(
                'live_lesson_duplicate_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get live lesson data formatted for API response.
     * 
     * Extracts and formats live lesson data from the database for API responses.
     * This is used by duplicate_item() to return the newly created lesson data.
     *
     * @since 1.5.7
     * @param int $lesson_id The lesson post ID.
     * @return array|null Formatted lesson data or null if not found.
     */
    private function get_live_lesson_data_for_response($lesson_id) {
        $post = get_post($lesson_id);
        if (!$post) {
            return null;
        }

        // Verify this is a live lesson post type
        if (!in_array($post->post_type, ['tutor-google-meet', 'tutor_zoom_meeting'])) {
            return null;
        }

        $topic_id = $post->post_parent;
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return null;
        }

        $course_id = $topic->post_parent;

        // Determine live lesson type
        $type = $post->post_type === 'tutor-google-meet' ? 'google_meet' : 'zoom';

        // Prepare base response data
        $response_data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'type' => $type,
            'topicId' => $topic_id,
            'courseId' => $course_id,
            'status' => 'scheduled',
            'createdAt' => $post->post_date_gmt,
            'updatedAt' => $post->post_modified_gmt,
        ];

        // Get provider-specific data
        if ($type === 'google_meet') {
            // Get Google Meet meta fields
            $start_datetime = get_post_meta($post->ID, 'tutor-google-meet-start-datetime', true);
            $end_datetime = get_post_meta($post->ID, 'tutor-google-meet-end-datetime', true);
            $event_details_json = get_post_meta($post->ID, 'tutor-google-meet-event-details', true);
            $meet_link = get_post_meta($post->ID, 'tutor-google-meet-link', true);

            // Parse event details
            $event_details = [];
            if ($event_details_json) {
                $event_details = json_decode($event_details_json, true) ?: [];
            }

            // Set datetime fields
            $response_data['startDateTime'] = $start_datetime ?: '';
            $response_data['endDateTime'] = $end_datetime ?: '';

            // Extract settings from event details
            $response_data['settings'] = [
                'timezone' => $event_details['timezone'] ?? 'UTC',
                'add_enrolled_students' => $event_details['attendees'] ?? 'No',
            ];

            // Add provider config
            $response_data['providerConfig'] = [
                'meetingUrl' => $meet_link ?: '',
                'eventId' => $event_details['id'] ?? '',
            ];

            // Add meet link if available
            if ($meet_link) {
                $response_data['meetingUrl'] = $meet_link;
            }

        } else {
            // Get Zoom meta fields
            $start_date = get_post_meta($post->ID, '_tutor_zm_start_date', true);
            $start_datetime = get_post_meta($post->ID, '_tutor_zm_start_datetime', true);
            $duration = get_post_meta($post->ID, '_tutor_zm_duration', true);
            $duration_unit = get_post_meta($post->ID, '_tutor_zm_duration_unit', true);
            $zoom_data_json = get_post_meta($post->ID, '_tutor_zm_data', true);

            // Parse Zoom meeting data
            $zoom_data = [];
            if ($zoom_data_json) {
                $zoom_data = json_decode($zoom_data_json, true) ?: [];
            }

            // Calculate end datetime from start + duration
            $end_datetime = '';
            if ($start_datetime && $duration) {
                try {
                    $start_obj = new DateTime($start_datetime);
                    $duration_minutes = $duration_unit === 'hr' ? $duration * 60 : $duration;
                    $start_obj->add(new DateInterval('PT' . $duration_minutes . 'M'));
                    $end_datetime = $start_obj->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    error_log('TutorPress: Error calculating end datetime: ' . $e->getMessage());
                }
            }

            // Set datetime fields
            $response_data['startDateTime'] = $start_datetime ?: '';
            $response_data['endDateTime'] = $end_datetime;

            // Extract settings from Zoom data
            $zoom_settings = $zoom_data['settings'] ?? [];
            $response_data['settings'] = [
                'timezone' => $zoom_data['timezone'] ?? 'UTC',
                'duration' => (int) $duration ?: 60,
                'allow_early_join' => $zoom_settings['join_before_host'] ?? false,
                'waiting_room' => $zoom_settings['waiting_room'] ?? false,
                'require_password' => !empty($zoom_data['password']),
            ];

            // Add provider config
            $response_data['providerConfig'] = [
                'host' => $zoom_data['host_id'] ?? '',
                'password' => $zoom_data['password'] ?? '',
                'autoRecording' => $zoom_settings['auto_recording'] ?? 'none',
                'meetingId' => $zoom_data['id'] ?? '',
                'joinUrl' => $zoom_data['join_url'] ?? '',
            ];

            // Add meeting URL if available
            if (!empty($zoom_data['join_url'])) {
                $response_data['meetingUrl'] = $zoom_data['join_url'];
            }
        }

        return $response_data;
    }

    /**
     * Duplicate Google Meet lesson meta data.
     * 
     * Copies all Google Meet specific meta fields from source to duplicate lesson.
     *
     * @since 1.5.7
     * @param int $source_id The source lesson post ID.
     * @param int $duplicate_id The duplicate lesson post ID.
     */
    private function duplicate_google_meet_meta($source_id, $duplicate_id) {
        // Copy Google Meet specific meta fields
        $meta_fields = [
            'tutor-google-meet-start-datetime',
            'tutor-google-meet-end-datetime',
            'tutor-google-meet-event-details',
        ];

        foreach ($meta_fields as $meta_key) {
            $meta_value = get_post_meta($source_id, $meta_key, true);
            if ($meta_value) {
                // For event details, we need to decode, modify, and re-encode
                if ($meta_key === 'tutor-google-meet-event-details') {
                    $event_details = json_decode($meta_value, true);
                    if ($event_details) {
                        // Clear any existing event IDs since this is a new lesson
                        unset($event_details['event_id']);
                        unset($event_details['calendar_id']);
                        
                        // Update the event title to indicate it's a copy
                        if (isset($event_details['title'])) {
                            $event_details['title'] = $event_details['title'] . ' (Copy)';
                        }
                        
                        $meta_value = json_encode($event_details);
                    }
                }
                
                update_post_meta($duplicate_id, $meta_key, $meta_value);
            }
        }

        // Copy any additional meta that might be relevant
        $additional_meta = [
            '_tutor_gmt_data', // If using this pattern
        ];

        foreach ($additional_meta as $meta_key) {
            $meta_value = get_post_meta($source_id, $meta_key, true);
            if ($meta_value) {
                update_post_meta($duplicate_id, $meta_key, $meta_value);
            }
        }
    }

    /**
     * Update Google Meet lesson with database updates only.
     * 
     * For compatibility and simplicity, this method only updates the database fields.
     * Provider API updates can be handled separately if needed.
     *
     * @since 1.5.7
     * @param int $lesson_id The lesson post ID.
     * @param array|null $provider_config Provider-specific configuration.
     * @param string|null $start_date_time Start date/time in MySQL format.
     * @param string|null $end_date_time End date/time in MySQL format.
     * @param array|null $settings Lesson settings.
     */
    private function update_google_meet_lesson($lesson_id, $provider_config, $start_date_time, $end_date_time, $settings) {
        // Update start datetime meta if provided
        if ($start_date_time) {
            update_post_meta($lesson_id, 'tutor-google-meet-start-datetime', $start_date_time);
        }

        // Update end datetime meta if provided  
        if ($end_date_time) {
            update_post_meta($lesson_id, 'tutor-google-meet-end-datetime', $end_date_time);
        }

        // Get existing Google Meet event details and update them
        $existing_event_details = get_post_meta($lesson_id, 'tutor-google-meet-event-details', true);
        if ($existing_event_details) {
            $event_details = json_decode($existing_event_details, true);
            
            if ($start_date_time && $event_details) {
                $event_details['start_datetime'] = $start_date_time;
            }
            
            if ($end_date_time && $event_details) {
                $event_details['end_datetime'] = $end_date_time;
            }
            
            if ($settings && isset($settings['timezone']) && $event_details) {
                $event_details['timezone'] = sanitize_text_field($settings['timezone']);
            }
            
            // Update add_enrolled_students setting if provided
            if ($settings && isset($settings['add_enrolled_students']) && $event_details) {
                $event_details['attendees'] = sanitize_text_field($settings['add_enrolled_students']);
            }
            
            // Update the stored event details
            update_post_meta($lesson_id, 'tutor-google-meet-event-details', json_encode($event_details));
        }
    }

    /**
     * Update Zoom lesson with database updates only.
     * 
     * For compatibility and simplicity, this method only updates the database fields.
     * Provider API updates can be handled separately if needed.
     *
     * @since 1.5.7
     * @param int $lesson_id The lesson post ID.
     * @param array|null $provider_config Provider-specific configuration.
     * @param string|null $start_date_time Start date/time in MySQL format.
     * @param string|null $end_date_time End date/time in MySQL format.
     * @param array|null $settings Lesson settings.
     */
    private function update_zoom_lesson($lesson_id, $provider_config, $start_date_time, $end_date_time, $settings) {
        // Update start datetime meta if provided
        if ($start_date_time) {
            $start_datetime_obj = new DateTime($start_date_time);
            $formatted_start_date = $start_datetime_obj->format('Y-m-d');
            
            update_post_meta($lesson_id, '_tutor_zm_start_date', $formatted_start_date);
            update_post_meta($lesson_id, '_tutor_zm_start_datetime', $start_date_time);
        }

        // Update duration if provided
        if ($start_date_time && $end_date_time) {
            $start_datetime_obj = new DateTime($start_date_time);
            $end_datetime_obj = new DateTime($end_date_time);
            $duration = ($end_datetime_obj->getTimestamp() - $start_datetime_obj->getTimestamp()) / 60;
            
            update_post_meta($lesson_id, '_tutor_zm_duration', (int) $duration);
            update_post_meta($lesson_id, '_tutor_zm_duration_unit', 'min');
        } elseif ($settings && isset($settings['duration'])) {
            update_post_meta($lesson_id, '_tutor_zm_duration', (int) $settings['duration']);
            update_post_meta($lesson_id, '_tutor_zm_duration_unit', 'min');
        }

        // Get existing Zoom data and update it
        $existing_data = get_post_meta($lesson_id, '_tutor_zm_data', true);
        if ($existing_data) {
            // Decode JSON string to array if needed
            if (is_string($existing_data)) {
                $updated_data = json_decode($existing_data, true);
                if (!$updated_data) {
                    return; // Exit if we can't decode the data
                }
            } else {
                $updated_data = $existing_data;
            }
            
            // Update basic fields
            if ($start_date_time) {
                $updated_data['start_time'] = $start_date_time;
            }
            
            if ($settings && isset($settings['timezone'])) {
                $updated_data['timezone'] = sanitize_text_field($settings['timezone']);
            }
            
            if ($start_date_time && $end_date_time) {
                $start_datetime_obj = new DateTime($start_date_time);
                $end_datetime_obj = new DateTime($end_date_time);
                $duration = ($end_datetime_obj->getTimestamp() - $start_datetime_obj->getTimestamp()) / 60;
                $updated_data['duration'] = (int) $duration;
            }
            
            // Update provider-specific config
            if ($provider_config) {
                // Update password
                if (isset($provider_config['password'])) {
                    $updated_data['password'] = sanitize_text_field($provider_config['password']);
                }

                // Update auto recording setting - store in settings array to match read operation
                if (isset($provider_config['autoRecording'])) {
                    $auto_recording = sanitize_text_field($provider_config['autoRecording']);
                    
                    if (in_array($auto_recording, ['local', 'cloud', 'none'])) {
                        if (!isset($updated_data['settings'])) {
                            $updated_data['settings'] = [];
                        }
                        $updated_data['settings']['auto_recording'] = $auto_recording;
                    }
                }

                // Update waiting room
                if (isset($provider_config['waitingRoom'])) {
                    if (!isset($updated_data['settings'])) {
                        $updated_data['settings'] = [];
                    }
                    $updated_data['settings']['waiting_room'] = (bool) $provider_config['waitingRoom'];
                }
            }
            
            // Update local meta data - store as JSON to match create operation
            update_post_meta($lesson_id, '_tutor_zm_data', json_encode($updated_data));
        }
    }
} 