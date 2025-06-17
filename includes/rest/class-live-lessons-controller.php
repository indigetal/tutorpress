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
            // Initialize Tutor LMS Zoom class (without hooks to avoid conflicts)
            $zoom_instance = new \TUTOR_ZOOM\Zoom(false);
            
            // Get current user's Zoom API credentials (same as Tutor LMS)
            $user_id = get_current_user_id();
            $api_data = json_decode(get_user_meta($user_id, 'tutor_zoom_api', true), true);
            
            // Check if user has configured Zoom API credentials
            if (empty($api_data) || empty($api_data['api_key']) || empty($api_data['api_secret'])) {
                return new WP_Error(
                    'zoom_api_not_configured',
                    __('Zoom API credentials are not configured. Please configure your Zoom API settings in Tutor LMS.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Get Zoom users using Tutor LMS method (includes caching)
            $zoom_users = $zoom_instance->tutor_zoom_get_users();
            
            if (empty($zoom_users)) {
                return new WP_Error(
                    'no_zoom_users',
                    __('No Zoom users found. Please check your Zoom API credentials and account.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Format users for frontend dropdown (matches Tutor LMS format exactly)
            $formatted_users = [];
            foreach ($zoom_users as $user) {
                $first_name = $user['first_name'] ?? '';
                $last_name = $user['last_name'] ?? '';
                $email = $user['email'] ?? '';
                $id = $user['id'] ?? '';
                
                // Skip users with missing essential data
                if (empty($id) || empty($email)) {
                    continue;
                }
                
                $formatted_users[] = [
                    'id' => $id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'display_name' => trim($first_name . ' ' . $last_name),
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
     * Get a single live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request) {
        $lesson_id = (int) $request->get_param('id');
        
        // Find mock lesson by ID
        $mock_lessons = $this->get_mock_live_lessons();
        $lesson = null;
        
        foreach ($mock_lessons as $mock_lesson) {
            if ($mock_lesson['id'] === $lesson_id) {
                $lesson = $mock_lesson;
                break;
            }
        }

        if (!$lesson) {
            return new WP_Error(
                'live_lesson_not_found',
                __('Live lesson not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        return rest_ensure_response($this->format_response($lesson, __('Live lesson retrieved successfully.', 'tutorpress')));
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
            
            // For Google Meet, we store basic event data without API integration
            // Full Google Calendar API integration requires OAuth setup and is beyond scope
            // Users should configure Google Meet via Tutor LMS addon for full functionality
            $event_details = [
                'start_datetime' => $formatted_start_datetime,
                'end_datetime' => $formatted_end_datetime,
                'attendees' => $settings['add_enrolled_students'] ?? 'No',
                'timezone' => $settings['timezone'],
                // Basic placeholder data - requires Google Calendar API for real meeting links
                'id' => '',
                'kind' => 'calendar#event',
                'event_type' => 'default',
                'html_link' => '',
                'meet_link' => '',
                'status' => 'tentative', // Not confirmed until created via Google Calendar API
                'transparency' => 'transparent',
                'visibility' => 'public',
            ];
            
            // Note: For production Google Meet integration, implement Google Calendar API
            // following the pattern in tutor-pro/addons/google-meet/includes/GoogleEvent/Events.php
            
            update_post_meta($post_id, 'tutor-google-meet-event-details', json_encode($event_details));
            
            // Store empty link until Google Calendar API integration is implemented
            update_post_meta($post_id, 'tutor-google-meet-link', '');
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
                
                // Store the real Zoom meeting data returned from API (same as Tutor LMS)
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
        
        // Debug: Log the meta data that was saved
        error_log('TutorPress Live Lesson Created - Post ID: ' . $post_id);
        error_log('Type: ' . $type);
        if ($type === 'google_meet') {
            error_log('Google Meet Start DateTime: ' . get_post_meta($post_id, 'tutor-google-meet-start-datetime', true));
            error_log('Google Meet End DateTime: ' . get_post_meta($post_id, 'tutor-google-meet-end-datetime', true));
            error_log('Google Meet Event Details: ' . print_r(get_post_meta($post_id, 'tutor-google-meet-event-details', true), true));
        } else {
            error_log('Zoom Start Date: ' . get_post_meta($post_id, '_tutor_zm_start_date', true));
            error_log('Zoom Start DateTime: ' . get_post_meta($post_id, '_tutor_zm_start_datetime', true));
            error_log('Zoom Duration: ' . get_post_meta($post_id, '_tutor_zm_duration', true));
            error_log('Zoom Data: ' . get_post_meta($post_id, '_tutor_zm_data', true));
        }
        
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
        
        // Find mock lesson by ID
        $mock_lessons = $this->get_mock_live_lessons();
        $lesson = null;
        
        foreach ($mock_lessons as $mock_lesson) {
            if ($mock_lesson['id'] === $lesson_id) {
                $lesson = $mock_lesson;
                break;
            }
        }

        if (!$lesson) {
            return new WP_Error(
                'live_lesson_not_found',
                __('Live lesson not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Update fields if provided
        $updatable_fields = ['title', 'description', 'start_date_time', 'end_date_time', 'status', 'settings', 'provider_config'];
        foreach ($updatable_fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                // Convert snake_case to camelCase for response
                $camel_field = str_replace('_', '', lcfirst(ucwords($field, '_')));
                $lesson[$camel_field] = $value;
            }
        }

        // Update timestamp
        $lesson['updatedAt'] = current_time('c', true);

        return rest_ensure_response($this->format_response($lesson, __('Live lesson updated successfully.', 'tutorpress')));
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
        
        // Remove from session lessons if it exists
        $session_lessons = get_transient('tutorpress_mock_live_lessons') ?: [];
        $session_lessons = array_filter($session_lessons, function($lesson) use ($lesson_id) {
            return $lesson['id'] !== $lesson_id;
        });
        set_transient('tutorpress_mock_live_lessons', array_values($session_lessons), HOUR_IN_SECONDS);
        
        // For mock implementation, just return success
        $deleted_lesson = [
            'id' => $lesson_id,
            'deleted' => true,
        ];

        return rest_ensure_response($this->format_response($deleted_lesson, __('Live lesson deleted successfully.', 'tutorpress')));
    }

    /**
     * Duplicate a live lesson.
     *
     * @since 1.5.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function duplicate_item($request) {
        $lesson_id = (int) $request->get_param('id');
        $target_topic_id = $request->get_param('topic_id');
        
        // Find mock lesson by ID
        $mock_lessons = $this->get_mock_live_lessons();
        $original_lesson = null;
        
        foreach ($mock_lessons as $mock_lesson) {
            if ($mock_lesson['id'] === $lesson_id) {
                $original_lesson = $mock_lesson;
                break;
            }
        }

        if (!$original_lesson) {
            return new WP_Error(
                'live_lesson_not_found',
                __('Live lesson not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Create duplicate with new ID and modified title
        $duplicated_lesson = $original_lesson;
        $duplicated_lesson['id'] = rand(1000, 9999); // Mock new ID
        $duplicated_lesson['title'] = $original_lesson['title'] . ' (Copy)';
        $duplicated_lesson['meetingUrl'] = $this->generate_mock_meeting_url($original_lesson['type']);
        $duplicated_lesson['meetingId'] = $this->generate_mock_meeting_id($original_lesson['type']);
        $duplicated_lesson['status'] = 'scheduled';
        $duplicated_lesson['createdAt'] = current_time('c', true);
        $duplicated_lesson['updatedAt'] = current_time('c', true);

        // Update topic ID if provided
        if ($target_topic_id) {
            $duplicated_lesson['topicId'] = (int) $target_topic_id;
        }

        return rest_ensure_response($this->format_response($duplicated_lesson, __('Live lesson duplicated successfully.', 'tutorpress')));
    }

    /**
     * Get mock live lessons data for testing.
     *
     * @since 1.5.0
     * @return array Mock live lessons data.
     */
    private function get_mock_live_lessons() {
        // Get any lessons created during this session
        $session_lessons = get_transient('tutorpress_mock_live_lessons') ?: [];
        
        $default_lessons = [
            [
                'id' => 1,
                'title' => 'Weekly Team Standup',
                'description' => 'Weekly team sync and planning session',
                'type' => 'google_meet',
                'topicId' => 5,
                'courseId' => 10,
                'startDateTime' => '2024-01-22T09:00:00Z',
                'endDateTime' => '2024-01-22T10:00:00Z',
                'meetingUrl' => 'https://meet.google.com/abc-defg-hij',
                'meetingId' => 'abc-defg-hij',
                'password' => null,
                'settings' => [
                    'timezone' => 'America/New_York',
                    'duration' => 60,
                    'allow_early_join' => true,
                    'auto_record' => false,
                    'require_password' => false,
                    'waiting_room' => false,
                ],
                'status' => 'scheduled',
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => '2024-01-01T00:00:00Z',
            ],
            [
                'id' => 2,
                'title' => 'Product Demo Session',
                'description' => 'Demonstrate new features to the team',
                'type' => 'zoom',
                'topicId' => 7,
                'courseId' => 10,
                'startDateTime' => '2024-01-25T15:00:00Z',
                'endDateTime' => '2024-01-25T16:30:00Z',
                'meetingUrl' => 'https://zoom.us/j/123456789',
                'meetingId' => '123456789',
                'password' => 'demo123',
                'settings' => [
                    'timezone' => 'UTC',
                    'duration' => 90,
                    'allow_early_join' => true,
                    'auto_record' => true,
                    'require_password' => true,
                    'waiting_room' => true,
                ],
                'status' => 'scheduled',
                'createdAt' => '2024-01-02T00:00:00Z',
                'updatedAt' => '2024-01-02T00:00:00Z',
            ],
            [
                'id' => 3,
                'title' => 'Client Presentation',
                'description' => 'Final project presentation to client',
                'type' => 'google_meet',
                'topicId' => 8,
                'courseId' => 12,
                'startDateTime' => '2024-01-30T14:00:00Z',
                'endDateTime' => '2024-01-30T15:00:00Z',
                'meetingUrl' => 'https://meet.google.com/xyz-uvwx-yz',
                'meetingId' => 'xyz-uvwx-yz',
                'password' => null,
                'settings' => [
                    'timezone' => 'Europe/London',
                    'duration' => 60,
                    'allow_early_join' => false,
                    'auto_record' => true,
                    'require_password' => false,
                    'waiting_room' => false,
                ],
                'status' => 'ended',
                'createdAt' => '2024-01-03T00:00:00Z',
                'updatedAt' => '2024-01-30T15:05:00Z',
            ],
        ];
        
        // Merge session lessons with default lessons
        return array_merge($default_lessons, $session_lessons);
    }

    /**
     * Generate mock meeting URL for testing.
     *
     * @since 1.5.0
     * @param string $type The meeting type (google_meet or zoom).
     * @return string Mock meeting URL.
     */
    private function generate_mock_meeting_url($type) {
        if ($type === 'google_meet') {
            $id = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3) . '-' . 
                  substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4) . '-' . 
                  substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
            return "https://meet.google.com/{$id}";
        } else {
            $id = rand(100000000, 999999999);
            return "https://zoom.us/j/{$id}";
        }
    }

    /**
     * Generate mock meeting ID for testing.
     *
     * @since 1.5.0
     * @param string $type The meeting type (google_meet or zoom).
     * @return string Mock meeting ID.
     */
    private function generate_mock_meeting_id($type) {
        if ($type === 'google_meet') {
            return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3) . '-' . 
                   substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4) . '-' . 
                   substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
        } else {
            return (string) rand(100000000, 999999999);
        }
    }

    /**
     * Generate mock password for testing.
     *
     * @since 1.5.0
     * @return string Mock password.
     */
    private function generate_mock_password() {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    }
} 