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
        $this->rest_base = 'courses/(?P<course_id>[\d]+)/settings';
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
                '/' . $this->rest_base,
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

                    // Add endpoint for course selection (prerequisites dropdown)
        register_rest_route($this->namespace, '/courses/for-prerequisites', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_courses_for_prerequisites'),
                'permission_callback' => array($this, 'check_prerequisites_permission'),
                    'args'     => array(
                        'exclude' => array(
                            'description' => __('Course ID to exclude from results (typically the current course).', 'tutorpress'),
                            'type'        => 'integer',
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
                    ),
                ),
            ));
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
        
        // Future sections: Get from _tutor_course_settings
        $future_settings = get_post_meta($course_id, '_tutor_course_settings', true);
        if (!is_array($future_settings)) {
            $future_settings = array();
        }

        // Build course settings structure
        $course_settings = array(
            // Course Details Section (individual meta fields)
            'course_level' => $course_level ?: 'all_levels',
            'is_public_course' => $is_public_course === 'yes',
            'enable_qna' => $enable_qna !== 'no',
            'course_duration' => $course_duration,
            
            // Course Access & Enrollment Section (Tutor LMS Pro field names)
            'course_prerequisites' => get_post_meta($course_id, '_tutor_course_prerequisites_ids', true) ?: array(),
            'maximum_students' => $future_settings['maximum_students'] ?? 0,
            'course_enrollment_period' => $future_settings['course_enrollment_period'] ?? 'no',
            'enrollment_starts_at' => $future_settings['enrollment_starts_at'] ?? '',
            'enrollment_ends_at' => $future_settings['enrollment_ends_at'] ?? '',
            'pause_enrollment' => $future_settings['pause_enrollment'] ?? 'no',
            
            // Course Media Section
            'featured_video' => $future_settings['featured_video'] ?? array(
                'source' => '',
                'source_youtube' => '',
                'source_vimeo' => '',
                'source_external_url' => '',
                'source_embedded' => '',
            ),
            'attachments' => $future_settings['attachments'] ?? array(),
            'materials_included' => $future_settings['materials_included'] ?? '',
            
            // Pricing Model Section
            'is_free' => $future_settings['is_free'] ?? true,
            'pricing_model' => $future_settings['pricing_model'] ?? '',
            'price' => $future_settings['price'] ?? 0,
            'sale_price' => $future_settings['sale_price'] ?? 0,
            'subscription_enabled' => $future_settings['subscription_enabled'] ?? false,
            
            // Instructors Section
            'instructors' => $future_settings['instructors'] ?? array(),
            'additional_instructors' => $future_settings['additional_instructors'] ?? array(),
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

        $results = array();

        // Course Details Section: Update individual Tutor LMS meta fields
        if ($request->has_param('course_level')) {
            $level = sanitize_text_field($request->get_param('course_level'));
            $results[] = update_post_meta($course_id, '_tutor_course_level', $level);
        }
        if ($request->has_param('is_public_course')) {
            $public_value = $request->get_param('is_public_course') ? 'yes' : 'no';
            $results[] = update_post_meta($course_id, '_tutor_is_public_course', $public_value);
        }
        if ($request->has_param('enable_qna')) {
            $qna_value = $request->get_param('enable_qna') ? 'yes' : 'no';
            $results[] = update_post_meta($course_id, '_tutor_enable_qa', $qna_value);
        }
        if ($request->has_param('course_duration')) {
            $duration = $request->get_param('course_duration');
            if (is_array($duration)) {
                $duration_data = array(
                    'hours' => isset($duration['hours']) ? max(0, (int) $duration['hours']) : 0,
                    'minutes' => isset($duration['minutes']) ? max(0, min(59, (int) $duration['minutes'])) : 0,
                );
                $results[] = update_post_meta($course_id, '_course_duration', $duration_data);
            }
        }
        
        // Handle prerequisites separately (stored in _tutor_course_prerequisites_ids)
        if ($request->has_param('course_prerequisites')) {
            $prerequisite_ids = $request->get_param('course_prerequisites');
            if (is_array($prerequisite_ids) && !empty($prerequisite_ids)) {
                // Filter to ensure all values are valid integers
                $prerequisite_ids = array_filter(array_map('intval', $prerequisite_ids));
                $results[] = update_post_meta($course_id, '_tutor_course_prerequisites_ids', $prerequisite_ids);
            } else {
                // Delete the meta if empty
                $results[] = delete_post_meta($course_id, '_tutor_course_prerequisites_ids');
            }
        }
        
        // Future sections: Update _tutor_course_settings for non-Course Details fields
        // Note: course_prerequisites is handled separately via _tutor_course_prerequisites_ids
        $future_updates = array();
        $future_fields = array('maximum_students', 'course_enrollment_period', 'enrollment_starts_at', 'enrollment_ends_at', 'pause_enrollment', 'featured_video', 
                              'attachments', 'materials_included', 'is_free', 'pricing_model', 
                              'price', 'sale_price', 'subscription_enabled', 'instructors', 
                              'additional_instructors');
        
        foreach ($future_fields as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                
                // Sanitize specific fields
                switch ($field) {
                    case 'maximum_students':
                        $future_updates[$field] = max(0, (int) $value);
                        break;
                    case 'course_enrollment_period':
                    case 'pause_enrollment':
                        $future_updates[$field] = in_array($value, ['yes', 'no']) ? $value : 'no';
                        break;
                    case 'enrollment_starts_at':
                    case 'enrollment_ends_at':
                        $future_updates[$field] = sanitize_text_field($value);
                        break;
                    default:
                        $future_updates[$field] = $value;
                        break;
                }
            }
        }
        
        if (!empty($future_updates)) {
            $existing_future_settings = get_post_meta($course_id, '_tutor_course_settings', true);
            if (!is_array($existing_future_settings)) {
                $existing_future_settings = array();
            }
            
            $updated_future_settings = array_merge($existing_future_settings, $future_updates);
            $results[] = update_post_meta($course_id, '_tutor_course_settings', $updated_future_settings);
        }

        // Also update the Gutenberg course_settings field to ensure sync
        try {
            $post_data = array('id' => $course_id);
            $gutenberg_settings = TutorPress_Course_Settings::get_course_settings($post_data);
            update_post_meta($course_id, 'course_settings', $gutenberg_settings);
        } catch (Exception $e) {
            error_log('TutorPress: Error syncing course settings: ' . $e->getMessage());
            return new WP_Error(
                'sync_failed',
                __('Failed to sync course settings: ', 'tutorpress') . $e->getMessage(),
                array('status' => 500)
            );
        }
        
        // Check if any updates failed
        if (!empty($results) && in_array(false, $results, true)) {
            error_log('TutorPress: Some course setting updates failed for course ID: ' . $course_id);
            return new WP_Error(
                'save_failed',
                __('Failed to save some course settings', 'tutorpress'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Course settings saved successfully', 'tutorpress'),
            'data' => $gutenberg_settings,
            'course_id' => $course_id,
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

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
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
     * Get courses for prerequisites dropdown
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_courses_for_prerequisites($request) {
        $exclude = $request->get_param('exclude');
        $search = $request->get_param('search');
        $per_page = $request->get_param('per_page') ?: 20;

        $args = array(
            'post_type' => 'courses',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'meta_query' => array(
                array(
                    'key' => '_tutor_course_price_type',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        // Exclude specific course (typically the current course)
        if ($exclude) {
            $args['post__not_in'] = array((int) $exclude);
        }

        // Add search functionality
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        $courses = get_posts($args);
        $formatted_courses = array();

        foreach ($courses as $course) {
            $formatted_courses[] = array(
                'id' => $course->ID,
                'title' => $course->post_title,
                'permalink' => get_permalink($course->ID),
                'featured_image' => get_the_post_thumbnail_url($course->ID, 'thumbnail'),
                'author' => get_the_author_meta('display_name', $course->post_author),
                'date_created' => $course->post_date,
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => $formatted_courses,
            'total_found' => count($formatted_courses),
            'search_term' => $search,
        ));
    }
} 