<?php
/**
 * Course Settings REST Controller Class
 *
 * Handles REST API functionality for course settings.
 * Manages the _tutor_course_settings meta field following Tutor LMS compatibility.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Course_Settings_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'courses';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get course settings
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<course_id>[\d]+)/settings',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_course_settings'],
                        'permission_callback' => [$this, 'check_read_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to get settings for.', 'tutorpress'),
                            ],
                        ],
                    ],
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'save_course_settings'],
                        'permission_callback' => [$this, 'check_write_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to save settings for.', 'tutorpress'),
                            ],
                            'course_level' => [
                                'type'              => 'string',
                                'enum'              => ['beginner', 'intermediate', 'expert', 'all_levels'],
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('The difficulty level of the course.', 'tutorpress'),
                            ],
                            'is_public_course' => [
                                'type'              => 'boolean',
                                'description'       => __('Whether the course is public.', 'tutorpress'),
                            ],
                            'enable_qna' => [
                                'type'              => 'boolean',
                                'description'       => __('Whether Q&A is enabled for the course.', 'tutorpress'),
                            ],
                            'course_duration' => [
                                'type'              => 'object',
                                'properties'        => [
                                    'hours'   => ['type' => 'integer', 'minimum' => 0],
                                    'minutes' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 59],
                                ],
                                'description'       => __('The duration of the course.', 'tutorpress'),
                            ],
                            'maximum_students' => [
                                'type'              => 'integer',
                                'minimum'           => 0,
                                'sanitize_callback' => 'absint',
                                'description'       => __('Maximum number of students (0 for unlimited).', 'tutorpress'),
                            ],
                            'course_enrollment_period' => [
                                'type'              => 'string',
                                'enum'              => ['yes', 'no'],
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Whether enrollment period is enabled.', 'tutorpress'),
                            ],
                            'enrollment_starts_at' => [
                                'type'              => 'string',
                                'format'            => 'date-time',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('When enrollment starts (ISO 8601 format).', 'tutorpress'),
                            ],
                            'enrollment_ends_at' => [
                                'type'              => 'string',
                                'format'            => 'date-time',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('When enrollment ends (ISO 8601 format).', 'tutorpress'),
                            ],
                            'pause_enrollment' => [
                                'type'              => 'string',
                                'enum'              => ['yes', 'no'],
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Whether enrollment is paused.', 'tutorpress'),
                            ],
                            'course_material_includes' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_textarea_field',
                                'description'       => __('A list of materials included in the course.', 'tutorpress'),
                            ],
                            'intro_video' => [
                                'type'              => 'object',
                                'properties'        => [
                                    'source' => ['type' => 'string'],
                                    'source_video_id' => ['type' => 'integer'],
                                    'source_youtube' => ['type' => 'string'],
                                    'source_vimeo' => ['type' => 'string'],
                                    'source_external_url' => ['type' => 'string'],
                                    'source_embedded' => ['type' => 'string'],
                                    'source_shortcode' => ['type' => 'string'],
                                    'poster' => ['type' => 'string'],
                                ],
                                'description'       => __('Course intro video settings.', 'tutorpress'),
                            ],
                            'attachments' => [
                                'type'              => 'array',
                                'items'             => ['type' => 'integer'],
                                'sanitize_callback' => function($ids) {
                                    return array_map('absint', (array) $ids);
                                },
                                'description'       => __('Array of attachment IDs for course materials.', 'tutorpress'),
                            ],
                            // Pricing Model Section
                            'pricing_model' => [
                                'type'              => 'string',
                                'enum'              => ['free', 'paid'],
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('The pricing model for the course (free or paid).', 'tutorpress'),
                            ],
                            'price' => [
                                'type'              => 'number',
                                'minimum'           => 0,
                                'sanitize_callback' => function($value) {
                                    return max(0, (float) $value);
                                },
                                'description'       => __('The regular price of the course.', 'tutorpress'),
                            ],
                            'sale_price' => [
                                'type'              => ['number', 'null'],
                                'minimum'           => 0,
                                'sanitize_callback' => function($value) {
                                    if ($value === null || $value === '') {
                                        return null;
                                    }
                                    return max(0, (float) $value);
                                },
                                'description'       => __('The sale price of the course.', 'tutorpress'),
                            ],
                            'subscription_enabled' => [
                                'type'              => 'boolean',
                                'description'       => __('Whether subscription is enabled for the course.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Test endpoint to verify controller registration
            register_rest_route(
                $this->namespace,
                '/course-settings-test',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => function() {
                            return rest_ensure_response(['message' => 'Course Settings controller is working']);
                        },
                        'permission_callback' => '__return_true',
                    ],
                ]
            );

            // Instructor management endpoints
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<course_id>[\d]+)/settings/instructors',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_course_instructors'],
                        'permission_callback' => [$this, 'check_instructor_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to get instructors for.', 'tutorpress'),
                            ],
                        ],
                    ],
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'update_course_instructors'],
                        'permission_callback' => [$this, 'check_instructor_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to update instructors for.', 'tutorpress'),
                            ],
                            'instructor_ids' => [
                                'type'              => 'array',
                                'items'             => ['type' => 'integer'],
                                'sanitize_callback' => function($ids) {
                                    return array_map('absint', (array) $ids);
                                },
                                'description'       => __('Array of instructor user IDs to assign to the course.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Instructor search endpoint
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<course_id>[\d]+)/settings/instructors/search',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'search_instructors'],
                        'permission_callback' => [$this, 'check_instructor_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to search instructors for.', 'tutorpress'),
                            ],
                            'search' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Search term for instructor names or emails.', 'tutorpress'),
                            ],
                            'per_page' => [
                                'type'              => 'integer',
                                'default'           => 10,
                                'minimum'           => 1,
                                'maximum'           => 50,
                                'sanitize_callback' => 'absint',
                                'description'       => __('Number of results per page.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Update post author endpoint
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<course_id>[\d]+)/settings/author',
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'update_course_author'],
                        'permission_callback' => [$this, 'check_instructor_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to update author for.', 'tutorpress'),
                            ],
                            'author_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The user ID of the new author.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );



            // Add general course search endpoint
        register_rest_route($this->namespace, '/courses/search', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_courses_for_search'),
                'permission_callback' => array($this, 'check_prerequisites_permission'),
                    'args'     => array(
                        'exclude' => array(
                            'description' => __('Course IDs to exclude from results (comma-separated).', 'tutorpress'),
                            'type'        => 'string',
                            'required'    => false,
                        ),
                        'search' => array(
                            'description' => __('Search term to filter courses.', 'tutorpress'),
                            'type'        => 'string',
                            'required'    => false,
                        ),
                        'per_page' => array(
                            'description' => __('Number of courses per page.', 'tutorpress'),
                            'type'        => 'integer',
                            'default'     => 20,
                            'minimum'     => 1,
                            'maximum'     => 100,
                        ),
                        'status' => array(
                            'description' => __('Course status to filter by.', 'tutorpress'),
                            'type'        => 'string',
                            'default'     => 'publish',
                            'enum'        => array('publish', 'draft', 'private'),
                        ),
                    ),
                ),
            ));

            // Add endpoint for fetching attachment metadata
            register_rest_route(
                $this->namespace,
                '/courses/(?P<course_id>[\d]+)/attachments',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_attachment_metadata'],
                        'permission_callback' => [$this, 'check_read_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to get attachment metadata for.', 'tutorpress'),
                            ],
                            'attachment_ids' => [
                                'type'              => 'array',
                                'items'             => ['type' => 'integer'],
                                'sanitize_callback' => function($ids) {
                                    return array_map('absint', (array) $ids);
                                },
                                'description'       => __('Array of attachment IDs to get metadata for.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );
        } catch (Exception $e) {
            // Silently handle any registration errors
            return;
        }
    }

    /**
     * Get course settings
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_course_settings($request) {
        $course_id = (int) $request->get_param('course_id');

        // Validate course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'course_not_found',
                __('Course not found', 'tutorpress'),
                array('status' => 404)
            );
        }

        // Course Details Section: Get from individual Tutor LMS meta fields
        $course_level = get_post_meta($course_id, '_tutor_course_level', true);
        $is_public_course = get_post_meta($course_id, '_tutor_is_public_course', true);
        $enable_qna = get_post_meta($course_id, '_tutor_enable_qa', true);
        $course_duration = get_post_meta($course_id, '_course_duration', true);
        
        // Validate Course Details fields
        if (!is_array($course_duration)) {
            $course_duration = array('hours' => 0, 'minutes' => 0);
        }
        
        // Use Phase 3 Course Provider for centralized settings retrieval
        $tutor_settings = tutorpress_course_provider()->get_course_settings($course_id);

        // Build course settings structure
        $course_settings = array(
            // Course Details Section (individual meta fields)
            'course_level' => $course_level ?: 'all_levels',
            'is_public_course' => $is_public_course === 'yes',
            'enable_qna' => $enable_qna !== 'no',
            'course_duration' => $course_duration,
            
            // Course Access & Enrollment Section
            'course_prerequisites' => get_post_meta($course_id, '_tutor_course_prerequisites_ids', true) ?: array(),
            'maximum_students'          => isset($tutor_settings['maximum_students']) ? $tutor_settings['maximum_students'] : null,
            'course_enrollment_period' => $tutor_settings['course_enrollment_period'] ?? 'no',
            'enrollment_starts_at' => $tutor_settings['enrollment_starts_at'] ?? '',
            'enrollment_ends_at' => $tutor_settings['enrollment_ends_at'] ?? '',
            'pause_enrollment'          => $tutor_settings['pause_enrollment'] ?? 'no',
            
            // Course Media Section
            'course_material_includes' => get_post_meta($course_id, '_tutor_course_material_includes', true) ?: '',
            'intro_video' => get_post_meta($course_id, '_video', true) ?: array(),
            'attachments' => get_post_meta($course_id, '_tutor_attachments', true) ?: array(),
            
            // Pricing Model Section (individual meta fields)
            'pricing_model' => get_post_meta($course_id, '_tutor_course_price_type', true) ?: 'free',
            'price' => (float) get_post_meta($course_id, 'tutor_course_price', true) ?: 0,
            'sale_price' => (float) get_post_meta($course_id, 'tutor_course_sale_price', true) ?: 0,
            'subscription_enabled' => get_post_meta($course_id, '_tutor_course_selling_option', true) === 'subscription',
            'selling_option' => get_post_meta($course_id, '_tutor_course_selling_option', true) ?: 'one_time',
        );

        return rest_ensure_response(array(
            'success' => true,
            'data' => $course_settings,
            'course_id' => $course_id,
        ));
    }

    /**
     * Save course settings
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function save_course_settings($request) {
        $course_id = (int) $request->get_param('course_id');

        // Validate course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'course_not_found',
                __('Course not found', 'tutorpress'),
                array('status' => 404)
            );
        }

        // Get existing settings
        $existing_tutor_settings = get_post_meta($course_id, '_tutor_course_settings', true);
        if (!is_array($existing_tutor_settings)) {
            $existing_tutor_settings = array();
        }

        // Get new settings from request
        $new_settings = array();

        // Course Details Section
        if ($request->has_param('course_level')) {
            $level = sanitize_text_field($request->get_param('course_level'));
            $new_settings['course_level'] = $level;
            update_post_meta($course_id, '_tutor_course_level', $level);
        }

        if ($request->has_param('is_public_course')) {
            $is_public = (bool) $request->get_param('is_public_course');
            $new_settings['is_public_course'] = $is_public;
            update_post_meta($course_id, '_tutor_is_public_course', $is_public ? 'yes' : 'no');
        }

        if ($request->has_param('enable_qna')) {
            $enable_qna = (bool) $request->get_param('enable_qna');
            $new_settings['enable_qna'] = $enable_qna;
            update_post_meta($course_id, '_tutor_enable_qa', $enable_qna ? 'yes' : 'no');
        }

        if ($request->has_param('course_duration')) {
            $duration = $request->get_param('course_duration');
            if (is_array($duration)) {
                $duration_data = array(
                    'hours'   => max(0, (int) ($duration['hours'] ?? 0)),
                    'minutes' => min(59, max(0, (int) ($duration['minutes'] ?? 0))),
                );
                $new_settings['course_duration'] = $duration_data;
                update_post_meta($course_id, '_course_duration', $duration_data);
            }
        }

        // Course Access & Enrollment Section
        if ($request->has_param('maximum_students')) {
            $max_students = $request->get_param('maximum_students');
            // Handle null/unlimited case properly
            if ($max_students === null || $max_students === '' || $max_students === 0) {
                $max_students_value = null;
                $legacy_value = ''; // Tutor LMS uses empty string for unlimited
            } else {
                $max_students_value = max(0, (int) $max_students);
                $legacy_value = $max_students_value;
            }
            $new_settings['maximum_students'] = $max_students_value;
            $new_settings['maximum_students_allowed'] = $legacy_value;
            update_post_meta($course_id, '_tutor_maximum_students', $legacy_value);
        }

        if ($request->has_param('course_enrollment_period')) {
            $period_value = sanitize_text_field($request->get_param('course_enrollment_period'));
            $period_value = $period_value === 'yes' ? 'yes' : 'no';
            $new_settings['course_enrollment_period'] = $period_value;
            update_post_meta($course_id, '_tutor_course_enrollment_period', $period_value);
        }

        if ($request->has_param('enrollment_starts_at')) {
            $starts_at = sanitize_text_field($request->get_param('enrollment_starts_at'));
            $new_settings['enrollment_starts_at'] = $starts_at;
            update_post_meta($course_id, '_tutor_enrollment_starts_at', $starts_at);
        }

        if ($request->has_param('enrollment_ends_at')) {
            $ends_at = sanitize_text_field($request->get_param('enrollment_ends_at'));
            $new_settings['enrollment_ends_at'] = $ends_at;
            update_post_meta($course_id, '_tutor_enrollment_ends_at', $ends_at);
        }

        if ($request->has_param('pause_enrollment')) {
            $pause_value = sanitize_text_field($request->get_param('pause_enrollment'));
            $pause_value = $pause_value === 'yes' ? 'yes' : 'no';
            $new_settings['pause_enrollment'] = $pause_value;
            $new_settings['enrollment_status'] = $pause_value;
            update_post_meta($course_id, '_tutor_enrollment_status', $pause_value);
        }

        // Course Media Section
        if ($request->has_param('course_material_includes')) {
            $materials = sanitize_textarea_field($request->get_param('course_material_includes'));
            $new_settings['course_material_includes'] = $materials;
            update_post_meta($course_id, '_tutor_course_material_includes', $materials);
        }

        if ($request->has_param('intro_video')) {
            $intro_video = $request->get_param('intro_video');
            if (is_array($intro_video)) {
                // Store as individual meta field (matches Tutor LMS pattern)
                update_post_meta($course_id, '_video', $intro_video);
                // Also store in settings array for compatibility
                $new_settings['intro_video'] = $intro_video;
            }
        }

        if ($request->has_param('attachments')) {
            $attachments = $request->get_param('attachments');
            if (is_array($attachments)) {
                $attachment_ids = array_map('absint', $attachments);
                $new_settings['attachments'] = $attachment_ids;
                // Also store in Tutor LMS format for compatibility
                update_post_meta($course_id, '_tutor_attachments', $attachment_ids);
            }
        }

        // Pricing Model Section
        if ($request->has_param('pricing_model')) {
            $pricing_model = sanitize_text_field($request->get_param('pricing_model'));
            $pricing_type = $pricing_model === 'free' ? 'free' : 'paid';
            $new_settings['pricing_model'] = $pricing_model;
            update_post_meta($course_id, '_tutor_course_price_type', $pricing_type);
        }

        if ($request->has_param('price')) {
            $price = max(0, (float) $request->get_param('price'));
            $new_settings['price'] = $price;
            update_post_meta($course_id, 'tutor_course_price', $price);
        }

        if ($request->has_param('sale_price')) {
            $sp = $request->get_param('sale_price');
            if ($sp === null || $sp === '') {
                $new_settings['sale_price'] = null;
                update_post_meta($course_id, 'tutor_course_sale_price', '');
            } else {
                $sale_price = max(0, (float) $sp);
                $new_settings['sale_price'] = $sale_price;
                update_post_meta($course_id, 'tutor_course_sale_price', $sale_price);
            }
        }

        if ($request->has_param('subscription_enabled')) {
            $subscription_enabled = (bool) $request->get_param('subscription_enabled');
            $new_settings['subscription_enabled'] = $subscription_enabled;
            // Only update meta if selling_option is not provided
            if (!$request->has_param('selling_option')) {
                update_post_meta($course_id, '_tutor_course_selling_option', $subscription_enabled ? 'subscription' : 'one_time');
            }
        }

        if ($request->has_param('selling_option')) {
            $selling_option = sanitize_text_field($request->get_param('selling_option'));
            // Validate the selling option
            $valid_options = ['one_time', 'subscription', 'both', 'membership', 'all'];
            if (!in_array($selling_option, $valid_options)) {
                $selling_option = 'one_time'; // Default fallback
            }
            $new_settings['selling_option'] = $selling_option;
            // Always update meta when selling_option is provided
            update_post_meta($course_id, '_tutor_course_selling_option', $selling_option);
        }

        // Merge with existing settings
        $merged_settings = array_merge($existing_tutor_settings, $new_settings);

        // Update course settings
        update_post_meta($course_id, '_tutor_course_settings', $merged_settings);
        update_post_meta($course_id, 'course_settings', $merged_settings);

        // Return updated settings
        return rest_ensure_response(array(
            'success' => true,
            'data'    => $merged_settings,
        ));
    }

    /**
     * Check read permissions for course settings endpoints
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if user has permission, error otherwise
     */
    public function check_read_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Use Phase 3 permissions service for consistent capability checking
        if (!tutorpress_permissions()->can_user_edit_course_settings($course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this course\'s settings.', 'tutorpress'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Check write permissions for course settings endpoints
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if user has permission, error otherwise
     */
    public function check_write_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit this course\'s settings.', 'tutorpress'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Check permissions for prerequisites endpoint
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if user has permission, error otherwise
     */
    public function check_prerequisites_permission($request) {
        // Allow any user who can edit courses to view the prerequisites list
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view courses.', 'tutorpress'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Check permissions for instructor management endpoints
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if user has permission, error otherwise
     */
    public function check_instructor_permission($request) {
        // Allow any user who can edit posts to manage instructors (same as prerequisites)
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to manage course instructors.', 'tutorpress'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Get attachment metadata for course attachments
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_attachment_metadata($request) {
        $course_id = (int) $request->get_param('course_id');
        $attachment_ids = $request->get_param('attachment_ids');

        // Validate course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'course_not_found',
                __('Course not found', 'tutorpress'),
                array('status' => 404)
            );
        }

        // Validate attachment IDs - handle both array and string formats
        if (empty($attachment_ids)) {
            return rest_ensure_response([
                'success' => true,
                'data' => [],
                'message' => __('No attachment IDs provided', 'tutorpress')
            ]);
        }
        
        // Ensure we have an array of integers
        if (!is_array($attachment_ids)) {
            $attachment_ids = [$attachment_ids];
        }
        
        $attachment_ids = array_map('absint', $attachment_ids);
        $attachment_ids = array_filter($attachment_ids); // Remove any 0 values

        $attachments = [];
        foreach ($attachment_ids as $attachment_id) {
            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->post_type === 'attachment') {
                $file_path = get_attached_file($attachment_id);
                $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                
                $attachments[] = [
                    'id' => $attachment_id,
                    'title' => $attachment->post_title,
                    'filename' => basename($file_path),
                    'url' => wp_get_attachment_url($attachment_id),
                    'mime_type' => $attachment->post_mime_type,
                    'filesize' => $file_size,
                ];
            }
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $attachments,
            'message' => sprintf(__('Retrieved metadata for %d attachments', 'tutorpress'), count($attachments))
        ]);
    }



    /**
     * Get courses for general search (reusable for bundles, prerequisites, etc.)
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_courses_for_search($request) {
        $exclude = $request->get_param('exclude');
        $search = $request->get_param('search');
        $per_page = $request->get_param('per_page') ?: 20;
        $status = $request->get_param('status') ?: 'publish';

        $args = array(
            'post_type' => 'courses',
            'post_status' => $status,
            'posts_per_page' => $per_page,
            'meta_query' => array(
                array(
                    'key' => '_tutor_course_price_type',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        // Exclude specific courses (comma-separated string)
        if ($exclude) {
            $exclude_ids = array_map('intval', explode(',', $exclude));
            $exclude_ids = array_filter($exclude_ids); // Remove empty values
            if (!empty($exclude_ids)) {
                $args['post__not_in'] = $exclude_ids;
            }
        }

        // Add search functionality
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        $courses = get_posts($args);
        $formatted_courses = array();
        $current_user_id = get_current_user_id();

        foreach ($courses as $course) {
            // Check if user can bundle this course
            $can_bundle = false;
            
            // Option 1: User is the course author
            if ($course->post_author == $current_user_id) {
                $can_bundle = true;
            }
            // Option 2: Course is free (no profit from other instructors' work)
            else {
                $price_type = get_post_meta($course->ID, '_tutor_course_price_type', true);
                $regular_price = get_post_meta($course->ID, 'tutor_course_price', true);
                
                if ($price_type === 'free' || empty($regular_price) || $regular_price == 0) {
                    $can_bundle = true;
                }
            }
            
            // Option 3: User is admin (can bundle any course)
            if (current_user_can('manage_options')) {
                $can_bundle = true;
            }
            
            // Skip courses the user cannot bundle
            if (!$can_bundle) {
                continue;
            }
            
            // Get additional course data for bundles
            $course_duration = get_post_meta($course->ID, '_course_duration', true);
            $lesson_count = get_post_meta($course->ID, '_lesson_count', true);
            $quiz_count = get_post_meta($course->ID, '_quiz_count', true);
            $resource_count = get_post_meta($course->ID, '_resource_count', true);
            
            // Get course price
            $price_type = get_post_meta($course->ID, '_tutor_course_price_type', true);
            $regular_price = get_post_meta($course->ID, 'tutor_course_price', true);
            $sale_price = get_post_meta($course->ID, 'tutor_course_sale_price', true);
            
            $price = '';
            if ($price_type === 'free') {
                $price = __('Free', 'tutorpress');
            } else {
                // Format prices like Tutor LMS does
                if ($sale_price && $sale_price > 0) {
                    // Show sale price as primary, regular price as crossed out
                    $price = sprintf(
                        '<span class="tutor-course-price-regular" style="text-decoration: line-through; color: #999; margin-right: 8px;">$%s</span><span class="tutor-course-price-sale">$%s</span>',
                        number_format($regular_price, 2),
                        number_format($sale_price, 2)
                    );
                } elseif ($regular_price && $regular_price > 0) {
                    // Show only regular price
                    $price = sprintf('<span class="tutor-course-price-regular">$%s</span>', number_format($regular_price, 2));
                } else {
                    $price = __('Free', 'tutorpress');
                }
            }

            $formatted_courses[] = array(
                'id' => $course->ID,
                'title' => $course->post_title,
                'permalink' => get_permalink($course->ID),
                'featured_image' => get_the_post_thumbnail_url($course->ID, 'thumbnail'),
                'author' => get_the_author_meta('display_name', $course->post_author),
                'date_created' => $course->post_date,
                'price' => $price,
                'duration' => $course_duration ? $course_duration : '',
                'lesson_count' => $lesson_count ? (int) $lesson_count : 0,
                'quiz_count' => $quiz_count ? (int) $quiz_count : 0,
                'resource_count' => $resource_count ? (int) $resource_count : 0,
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => $formatted_courses,
            'total_found' => count($formatted_courses),
            'search_term' => $search,
            'status' => $status,
        ));
    }

    /**
     * Check if a given request has access to get items.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check($request) {
        $course_id = $request->get_param('id');
        return current_user_can('edit_post', $course_id);
    }

    /**
     * Get course settings.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_item($request) {
        $course_id = $request->get_param('id');

        // Use Phase 3 Course Provider for centralized settings retrieval
        $settings = tutorpress_course_provider()->get_course_settings($course_id);

        if (empty($settings)) {
            return new WP_Error(
                'no_settings',
                __('No settings found for this course.', 'tutorpress'),
                ['status' => 404]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $settings,
            'course_id' => $course_id,
        ]);
    }

    /**
     * Update course settings.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_item($request) {
        $course_id = $request->get_param('id');

        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_cannot_edit',
                __('Sorry, you are not allowed to edit this course.', 'tutorpress'),
                ['status' => rest_authorization_required_code()]
            );
        }

        // Get current settings
        $current_settings = get_post_meta($course_id, 'course_settings', true);
        if (!is_array($current_settings)) {
            $current_settings = [];
        }

        // Build settings updates
        $settings_updates = [];

        // Handle maximum_students
        if ($request->has_param('maximum_students')) {
            $max_students = $request->get_param('maximum_students');
            $settings_updates['maximum_students'] = ($max_students === '' || $max_students === null || $max_students === 0) ? '' : absint($max_students);
        }

        // Handle pause_enrollment
        if ($request->has_param('pause_enrollment')) {
            $pause_value = sanitize_text_field($request->get_param('pause_enrollment'));
            $settings_updates['pause_enrollment'] = ($pause_value === 'yes') ? 'yes' : 'no';
        }

        // Handle course_enrollment_period
        if ($request->has_param('course_enrollment_period')) {
            $period_value = sanitize_text_field($request->get_param('course_enrollment_period'));
            $settings_updates['course_enrollment_period'] = ($period_value === 'yes') ? 'yes' : 'no';
        }

        // Handle enrollment dates
        if ($request->has_param('enrollment_starts_at')) {
            $settings_updates['enrollment_starts_at'] = sanitize_text_field($request->get_param('enrollment_starts_at'));
        }

        if ($request->has_param('enrollment_ends_at')) {
            $settings_updates['enrollment_ends_at'] = sanitize_text_field($request->get_param('enrollment_ends_at'));
        }

        // Merge with current settings
        $merged_settings = array_merge($current_settings, $settings_updates);

        // Get post object for update_course_settings
        $post = get_post($course_id);
        if (!$post) {
            return new WP_Error(
                'invalid_post',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Use Phase 3 Course Provider for centralized settings saving
        $result = tutorpress_course_provider()->save_course_settings($course_id, $merged_settings);

        if (!$result) {
            return new WP_Error(
                'save_failed',
                __('Failed to save some course settings', 'tutorpress'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Course settings saved successfully', 'tutorpress'),
            'data' => $merged_settings,
            'course_id' => $course_id,
        ]);
    }

    /**
     * Get the mapping of Tutor LMS meta fields to TutorPress settings
     *
     * @return array The meta field mapping
     */
    private function get_meta_field_mapping(): array {
        return [
            // Pricing fields (exact Tutor LMS format)
            '_tutor_course_price_type' => 'pricing_model',
            'tutor_course_price' => 'price', // NO underscore
            'tutor_course_sale_price' => 'sale_price', // NO underscore
            '_tutor_course_selling_option' => 'selling_option', // WITH underscore
            '_tutor_course_product_id' => 'product_id',
            
            // Course details fields
            '_tutor_course_level' => 'course_level',
            '_tutor_is_public_course' => 'is_public_course',
            '_tutor_enable_qa' => 'enable_qna',
            '_course_duration' => 'course_duration',
            
            // Course access fields
            '_tutor_course_prerequisites_ids' => 'course_prerequisites',
            '_tutor_maximum_students' => 'maximum_students',
            '_tutor_course_enrollment_period' => 'course_enrollment_period',
            '_tutor_enrollment_starts_at' => 'enrollment_starts_at',
            '_tutor_enrollment_ends_at' => 'enrollment_ends_at',
            '_tutor_enrollment_status' => 'pause_enrollment',
            
            // Course media fields
            '_tutor_course_material_includes' => 'course_material_includes',
            '_video' => 'intro_video',
            '_tutor_attachments' => 'attachments',
        ];
    }

    /**
     * Get course instructors (author + co-instructors)
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_course_instructors($request) {
        $course_id = $request->get_param('course_id');

        // Verify course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'invalid_course',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Get course author (main instructor)
        $author = get_user_by('id', $course->post_author);
        $author_data = null;
        if ($author) {
            $author_data = [
                'id' => $author->ID,
                'display_name' => $author->display_name,
                'user_email' => $author->user_email,
                'user_login' => $author->user_login,
                'avatar_url' => get_avatar_url($author->ID, ['size' => 96]),
                'role' => 'author',
            ];
        }

        // Get co-instructors from improved data model
        $co_instructor_ids = get_post_meta($course_id, '_tutor_course_instructors', true);
        if (!is_array($co_instructor_ids)) {
            $co_instructor_ids = [];
        }

        $co_instructors = [];
        foreach ($co_instructor_ids as $instructor_id) {
            $instructor = get_user_by('id', $instructor_id);
            if ($instructor) {
                $co_instructors[] = [
                    'id' => $instructor->ID,
                    'display_name' => $instructor->display_name,
                    'user_email' => $instructor->user_email,
                    'user_login' => $instructor->user_login,
                    'avatar_url' => get_avatar_url($instructor->ID, ['size' => 96]),
                    'role' => 'instructor',
                ];
            }
        }

        return rest_ensure_response([
            'success' => true,
            'data' => [
                'author' => $author_data,
                'co_instructors' => $co_instructors,
                'total_instructors' => count($co_instructors) + ($author_data ? 1 : 0),
            ],
            'course_id' => $course_id,
        ]);
    }

    /**
     * Search for available instructors to add
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function search_instructors($request) {
        $course_id = $request->get_param('course_id');
        $search = $request->get_param('search');
        $per_page = $request->get_param('per_page');

        // Verify course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'invalid_course',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Get current instructors to exclude from search
        $current_instructor_ids = [$course->post_author]; // Include author
        $co_instructor_ids = get_post_meta($course_id, '_tutor_course_instructors', true);
        if (is_array($co_instructor_ids)) {
            $current_instructor_ids = array_merge($current_instructor_ids, $co_instructor_ids);
        }

        // Build user query
        $args = [
            'number' => $per_page,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'exclude' => $current_instructor_ids,
            'role__in' => ['administrator', 'editor', 'author', 'tutor_instructor'], // Include Tutor LMS instructor role
        ];

        // Add search functionality
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $users = get_users($args);
        $instructors = [];

        foreach ($users as $user) {
            $instructors[] = [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login,
                'avatar_url' => get_avatar_url($user->ID, ['size' => 96]),
                'role' => $user->roles[0] ?? 'subscriber',
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $instructors,
            'total_found' => count($instructors),
            'search_term' => $search,
            'course_id' => $course_id,
        ]);
    }

    /**
     * Update course instructors (co-instructors only, not author)
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_course_instructors($request) {
        $course_id = $request->get_param('course_id');
        $instructor_ids = $request->get_param('instructor_ids');

        // Verify course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'invalid_course',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check if user can edit this specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit this course.', 'tutorpress'),
                ['status' => 403]
            );
        }

        // Ensure instructor_ids is an array
        if (!is_array($instructor_ids)) {
            $instructor_ids = [];
        }

        // Validate instructor IDs
        $valid_instructor_ids = [];
        foreach ($instructor_ids as $instructor_id) {
            $user = get_user_by('id', $instructor_id);
            if ($user && (user_can($instructor_id, 'edit_posts') || user_can($instructor_id, 'tutor_instructor'))) {
                $valid_instructor_ids[] = $instructor_id;
            }
        }

        // Try to update the meta field with fallback approach
        $result = update_post_meta($course_id, '_tutor_course_instructors', $valid_instructor_ids);
        
        if ($result === false) {
            // Try alternative approach - delete and add
            delete_post_meta($course_id, '_tutor_course_instructors');
            $result = add_post_meta($course_id, '_tutor_course_instructors', $valid_instructor_ids, true);
            
            if ($result === false) {
                return new WP_Error(
                    'update_failed',
                    __('Failed to update course instructors. Database error.', 'tutorpress'),
                    ['status' => 500]
                );
            }
        }

        // Sync to Tutor LMS compatibility (user meta)
        try {
            $this->sync_instructors_to_tutor_lms($course_id, $valid_instructor_ids);
        } catch (Exception $e) {
            // Don't fail the request if sync fails
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Course instructors updated successfully.', 'tutorpress'),
            'data' => [
                'instructor_ids' => $valid_instructor_ids,
                'total_instructors' => count($valid_instructor_ids),
            ],
            'course_id' => $course_id,
        ]);
    }

    /**
     * Sync instructors to Tutor LMS compatibility format
     *
     * @param int $course_id The course ID
     * @param array $instructor_ids Array of instructor user IDs
     * @return void
     */
    private function sync_instructors_to_tutor_lms($course_id, $instructor_ids) {
        // Remove old instructor associations
        global $wpdb;
        $wpdb->delete(
            $wpdb->usermeta,
            [
                'meta_key' => '_tutor_instructor_course_id',
                'meta_value' => $course_id,
            ]
        );

        // Add new instructor associations
        foreach ($instructor_ids as $instructor_id) {
            add_user_meta($instructor_id, '_tutor_instructor_course_id', $course_id);
        }
    }

    /**
     * Update course author (post_author)
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_course_author($request) {
        $course_id = $request->get_param('course_id');
        $author_id = $request->get_param('author_id');

        // Verify course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'invalid_course',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check if user can edit this specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit this course.', 'tutorpress'),
                ['status' => 403]
            );
        }

        // Verify the new author exists and has appropriate permissions
        $new_author = get_user_by('id', $author_id);
        if (!$new_author) {
            return new WP_Error(
                'invalid_author',
                __('Invalid author ID.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Check if the new author has appropriate capabilities
        if (!user_can($author_id, 'edit_posts') && !user_can($author_id, 'tutor_instructor')) {
            return new WP_Error(
                'invalid_author_capabilities',
                __('The selected user does not have appropriate permissions to be a course author.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Check if the author is already the current author
        if ($course->post_author == $author_id) {
            return new WP_Error(
                'author_already_assigned',
                __('The selected user is already the course author.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Update the post author using wp_update_post
        $result = wp_update_post([
            'ID' => $course_id,
            'post_author' => $author_id,
        ]);

        if (is_wp_error($result)) {
            return new WP_Error(
                'update_failed',
                __('Failed to update course author.', 'tutorpress'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Course author updated successfully.', 'tutorpress'),
            'data' => [
                'course_id' => $course_id,
                'author_id' => $author_id,
                'author_name' => $new_author->display_name,
                'author_email' => $new_author->user_email,
            ],
        ]);
    }
} 