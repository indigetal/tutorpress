<?php
/**
 * Course Settings REST Controller Class
 *
 * Handles REST API functionality for course settings.
 * Uses WordPress Core functions with API wrapper pattern for maximum compatibility.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Course_Settings_Controller extends TutorPress_REST_Controller {

    /**
     * REST base.
     *
     * @var string
     */
    protected $rest_base = 'courses';

    /**
     * Initialize the controller.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        // Hook into post save to handle Tutor LMS sync
        add_action('save_post_' . tutor()->course_post_type, [__CLASS__, 'handle_course_save'], 999, 3);
    }

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'courses';
        self::init(); // Initialize static hooks
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<course_id>[\d]+)/settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_item'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_item'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

        // Get courses for prerequisites dropdown
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/prerequisites',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_prerequisites_courses'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'exclude' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Course ID to exclude from results.', 'tutorpress'),
                        ],
                        'search' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('Search term to filter courses.', 'tutorpress'),
                        ],
                        'per_page' => [
                            'type'              => 'integer',
                            'default'           => 20,
                            'minimum'           => 1,
                            'maximum'           => 100,
                            'sanitize_callback' => 'absint',
                            'description'       => __('Number of courses per page.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Check if current user has permission to manage course settings.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if permission is granted, WP_Error otherwise.
     */
    public function check_permission($request) {
        // Check Tutor LMS availability
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        // For prerequisites endpoint, just check if user can edit any courses
        if ($request->get_route() === "/{$this->namespace}/{$this->rest_base}/prerequisites") {
            return current_user_can('edit_posts');
        }

        // Get course ID from request
        $course_id = $request->get_param('course_id');
        
        // Validate course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== tutor()->course_post_type) {
            return new WP_Error(
                'course_not_found',
                __('Course not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check if user can edit this specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_cannot_edit',
                __('Sorry, you are not allowed to edit course settings.', 'tutorpress'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    /**
     * Get course settings.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request) {
        try {
            $course_id = $request->get_param('course_id');

            // Course Details Section: Individual meta fields
            $settings = [
                'course_level' => get_post_meta($course_id, '_tutor_course_level', true) ?: 'all_levels',
                'is_public_course' => get_post_meta($course_id, '_tutor_is_public_course', true) === 'yes',
                'enable_qna' => get_post_meta($course_id, '_tutor_enable_qa', true) !== 'no', // Defaults to enabled
                'course_duration' => $this->get_course_duration($course_id),
            ];

            // Course Access & Enrollment Section
            $tutor_settings = get_post_meta($course_id, '_tutor_course_settings', true) ?: [];
            $settings = array_merge($settings, [
                'maximum_students' => $this->get_maximum_students($course_id),
                'pause_enrollment' => $this->get_enrollment_status($course_id),
                'course_enrollment_period' => $tutor_settings['course_enrollment_period'] ?? 'no',
                'enrollment_starts_at' => $tutor_settings['enrollment_starts_at'] ?? '',
                'enrollment_ends_at' => $tutor_settings['enrollment_ends_at'] ?? '',
            ]);

            // Prerequisites (if addon is enabled)
            if (TutorPress_Addon_Checker::is_prerequisites_enabled()) {
                $settings['prerequisites'] = get_post_meta($course_id, '_tutor_course_prerequisites_ids', true) ?: [];
            }

            return rest_ensure_response(
                $this->format_response($settings, __('Course settings retrieved successfully.', 'tutorpress'))
            );

        } catch (Exception $e) {
            return new WP_Error(
                'settings_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update course settings.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function update_item($request) {
        try {
            $course_id = $request->get_param('course_id');
            $params = $request->get_params();

            // Update Course Details Section (individual meta fields)
            if (isset($params['course_level'])) {
                update_post_meta($course_id, '_tutor_course_level', $params['course_level']);
            }
            if (isset($params['is_public_course'])) {
                update_post_meta($course_id, '_tutor_is_public_course', $params['is_public_course'] ? 'yes' : 'no');
            }
            if (isset($params['enable_qna'])) {
                update_post_meta($course_id, '_tutor_enable_qa', $params['enable_qna'] ? 'yes' : 'no');
            }
            if (isset($params['course_duration'])) {
                $this->update_course_duration($course_id, $params['course_duration']);
            }

            // Update Course Access & Enrollment Section
            $tutor_settings = get_post_meta($course_id, '_tutor_course_settings', true) ?: [];

            if (isset($params['maximum_students'])) {
                $this->update_maximum_students($course_id, $params['maximum_students']);
            }
            if (isset($params['pause_enrollment'])) {
                $this->update_enrollment_status($course_id, $params['pause_enrollment']);
            }
            if (isset($params['course_enrollment_period'])) {
                $tutor_settings['course_enrollment_period'] = $params['course_enrollment_period'];
            }
            if (isset($params['enrollment_starts_at'])) {
                $tutor_settings['enrollment_starts_at'] = $params['enrollment_starts_at'];
            }
            if (isset($params['enrollment_ends_at'])) {
                $tutor_settings['enrollment_ends_at'] = $params['enrollment_ends_at'];
            }

            // Update prerequisites if addon is enabled
            if (isset($params['prerequisites']) && TutorPress_Addon_Checker::is_prerequisites_enabled()) {
                update_post_meta($course_id, '_tutor_course_prerequisites_ids', $params['prerequisites']);
            }

            // Save tutor settings array
            update_post_meta($course_id, '_tutor_course_settings', $tutor_settings);

            // Get updated settings for response
            $updated_settings = $this->get_item($request)->get_data();

            return rest_ensure_response(
                $this->format_response(
                    $updated_settings['data'],
                    __('Course settings updated successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'settings_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get courses for prerequisites dropdown.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_prerequisites_courses($request) {
        try {
            // Check if prerequisites addon is enabled
            if (!TutorPress_Addon_Checker::is_prerequisites_enabled()) {
                return new WP_Error(
                    'prerequisites_disabled',
                    __('Prerequisites addon is not enabled.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            $args = [
                'post_type' => 'courses',
                'post_status' => 'publish',
                'posts_per_page' => $request->get_param('per_page'),
                'orderby' => 'title',
                'order' => 'ASC',
            ];

            // Add search if provided
            if ($request->has_param('search')) {
                $args['s'] = $request->get_param('search');
            }

            // Exclude current course if specified
            if ($request->has_param('exclude')) {
                $args['post__not_in'] = [$request->get_param('exclude')];
            }

            $query = new WP_Query($args);
            $courses = array_map(function($post) {
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                ];
            }, $query->posts);

            return rest_ensure_response(
                $this->format_response($courses, __('Courses retrieved successfully.', 'tutorpress'))
            );

        } catch (Exception $e) {
            return new WP_Error(
                'prerequisites_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get course duration from meta.
     *
     * @since 0.1.0
     * @param int $course_id Course ID.
     * @return array Course duration array with hours and minutes.
     */
    private function get_course_duration($course_id) {
        $duration = get_post_meta($course_id, '_course_duration', true);
        if (!is_array($duration)) {
            $duration = [];
        }
        return [
            'hours' => isset($duration['hours']) ? absint($duration['hours']) : 0,
            'minutes' => isset($duration['minutes']) ? min(59, absint($duration['minutes'])) : 0,
        ];
    }

    /**
     * Update course duration.
     *
     * @since 0.1.0
     * @param int $course_id Course ID.
     * @param array $duration Duration array with hours and minutes.
     */
    private function update_course_duration($course_id, $duration) {
        update_post_meta($course_id, '_course_duration', [
            'hours' => isset($duration['hours']) ? absint($duration['hours']) : 0,
            'minutes' => isset($duration['minutes']) ? min(59, absint($duration['minutes'])) : 0,
        ]);
    }

    /**
     * Get maximum students setting.
     *
     * @since 0.1.0
     * @param int $course_id Course ID.
     * @return int|null Maximum students or null for unlimited.
     */
    private function get_maximum_students($course_id) {
        $max_students = get_post_meta($course_id, 'maximum_students', true);
        $max_students_allowed = get_post_meta($course_id, 'maximum_students_allowed', true);
        
        // Use maximum_students_allowed as fallback
        $value = $max_students ?: $max_students_allowed;
        return $value ? absint($value) : null;
    }

    /**
     * Update maximum students setting.
     *
     * @since 0.1.0
     * @param int $course_id Course ID.
     * @param int|null $max_students Maximum students or null for unlimited.
     */
    private function update_maximum_students($course_id, $max_students) {
        $value = $max_students === null ? '' : absint($max_students);
        update_post_meta($course_id, 'maximum_students', $value);
        update_post_meta($course_id, 'maximum_students_allowed', $value);
    }

    /**
     * Get enrollment status.
     *
     * @since 0.1.0
     * @param int $course_id Course ID.
     * @return string 'yes' or 'no'.
     */
    private function get_enrollment_status($course_id) {
        $pause_enrollment = get_post_meta($course_id, 'pause_enrollment', true);
        $enrollment_status = get_post_meta($course_id, 'enrollment_status', true);
        
        // Use enrollment_status as fallback
        return ($pause_enrollment ?: $enrollment_status) === 'yes' ? 'yes' : 'no';
    }

    /**
     * Update enrollment status.
     *
     * @since 0.1.0
     * @param int $course_id Course ID.
     * @param string $status Status ('yes' or 'no').
     */
    private function update_enrollment_status($course_id, $status) {
        $value = $status === 'yes' ? 'yes' : 'no';
        update_post_meta($course_id, 'pause_enrollment', $value);
        update_post_meta($course_id, 'enrollment_status', $value);
    }

    /**
     * Get the schema for course settings.
     *
     * @since 0.1.0
     * @return array Schema array.
     */
    public function get_item_schema() {
        if ($this->schema) {
            return $this->schema;
        }

        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => $this->get_schema_title(),
            'type'       => 'object',
            'properties' => [
                'course_level' => [
                    'description' => __('The difficulty level of the course.', 'tutorpress'),
                    'type'        => 'string',
                    'enum'        => ['beginner', 'intermediate', 'expert', 'all_levels'],
                    'context'     => ['view', 'edit'],
                ],
                'is_public_course' => [
                    'description' => __('Whether the course is public.', 'tutorpress'),
                    'type'        => 'boolean',
                    'context'     => ['view', 'edit'],
                ],
                'enable_qna' => [
                    'description' => __('Whether Q&A is enabled for the course.', 'tutorpress'),
                    'type'        => 'boolean',
                    'context'     => ['view', 'edit'],
                ],
                'course_duration' => [
                    'description' => __('The duration of the course.', 'tutorpress'),
                    'type'        => 'object',
                    'properties'  => [
                        'hours'   => [
                            'type'    => 'integer',
                            'minimum' => 0,
                        ],
                        'minutes' => [
                            'type'    => 'integer',
                            'minimum' => 0,
                            'maximum' => 59,
                        ],
                    ],
                    'context'     => ['view', 'edit'],
                ],
                'maximum_students' => [
                    'description' => __('Maximum number of students (null for unlimited).', 'tutorpress'),
                    'type'        => ['integer', 'null'],
                    'minimum'     => 0,
                    'context'     => ['view', 'edit'],
                ],
                'pause_enrollment' => [
                    'description' => __('Whether enrollment is paused.', 'tutorpress'),
                    'type'        => 'string',
                    'enum'        => ['yes', 'no'],
                    'context'     => ['view', 'edit'],
                ],
                'course_enrollment_period' => [
                    'description' => __('Whether enrollment period is enabled.', 'tutorpress'),
                    'type'        => 'string',
                    'enum'        => ['yes', 'no'],
                    'context'     => ['view', 'edit'],
                ],
                'enrollment_starts_at' => [
                    'description' => __('When enrollment starts (ISO 8601 format).', 'tutorpress'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => ['view', 'edit'],
                ],
                'enrollment_ends_at' => [
                    'description' => __('When enrollment ends (ISO 8601 format).', 'tutorpress'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => ['view', 'edit'],
                ],
                'prerequisites' => [
                    'description' => __('List of prerequisite course IDs.', 'tutorpress'),
                    'type'        => 'array',
                    'items'       => [
                        'type' => 'integer',
                    ],
                    'context'     => ['view', 'edit'],
                ],
            ],
        ];

        $this->schema = $schema;
        return $this->schema;
    }

    /**
     * Handle course save to sync with Tutor LMS.
     *
     * @since 0.1.0
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public static function handle_course_save($post_id, $post, $update) {
        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Ensure it's a course
        if ($post->post_type !== 'courses') {
            return;
        }

        // Sync individual fields with Tutor LMS frontend course builder
        $course_level = get_post_meta($post_id, '_tutor_course_level', true);
        $is_public = get_post_meta($post_id, '_tutor_is_public_course', true);
        $enable_qa = get_post_meta($post_id, '_tutor_enable_qa', true);

        // Update corresponding Tutor LMS fields if they exist
        if ($course_level) {
            update_post_meta($post_id, '_tutor_course_level', $course_level);
        }
        if ($is_public) {
            update_post_meta($post_id, '_tutor_is_public_course', $is_public);
        }
        if ($enable_qa) {
            update_post_meta($post_id, '_tutor_enable_qa', $enable_qa);
        }

        // Sync enrollment fields
        $max_students = get_post_meta($post_id, 'maximum_students', true);
        $enrollment_status = get_post_meta($post_id, 'pause_enrollment', true);

        if ($max_students) {
            update_post_meta($post_id, 'maximum_students_allowed', $max_students);
        }
        if ($enrollment_status) {
            update_post_meta($post_id, 'enrollment_status', $enrollment_status);
        }
    }
} 