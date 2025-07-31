<?php
/**
 * Bundle Settings REST Controller Class
 *
 * Handles REST API functionality for bundle settings.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Course_Bundles_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'bundles';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        // Basic bundle operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_bundles'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'per_page' => [
                            'type'              => 'integer',
                            'default'           => 10,
                            'minimum'           => 1,
                            'maximum'           => 100,
                            'sanitize_callback' => 'absint',
                        ],
                        'page' => [
                            'type'              => 'integer',
                            'default'           => 1,
                            'minimum'           => 1,
                            'sanitize_callback' => 'absint',
                        ],
                        'search' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                ],
            ]
        );

        // Single bundle operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_bundle'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods'             => 'PATCH',
                    'callback'            => [$this, 'update_bundle'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

        // Bundle courses endpoints
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/courses',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_bundle_courses'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The bundle ID.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => 'PATCH',
                    'callback'            => [$this, 'update_bundle_courses'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The bundle ID.', 'tutorpress'),
                        ],
                        'course_ids' => [
                            'required'          => true,
                            'type'             => 'array',
                            'items'            => ['type' => 'integer'],
                            'sanitize_callback' => function($ids) {
                                return array_map('absint', (array) $ids);
                            },
                            'description'       => __('Array of course IDs to assign to the bundle.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Check if user has permission to access endpoints.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_permission($request) {
        // Ensure Tutor LMS is active
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        // Check if user can edit posts (basic requirement)
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this endpoint.', 'tutorpress'),
                ['status' => 403]
            );
        }

        // For bundle-specific operations, check if user can edit the specific bundle
        $bundle_id = $request->get_param('id');
        if ($bundle_id) {
            $bundle = get_post($bundle_id);
            if ($bundle && $bundle->post_type === 'course-bundle') {
                if (!current_user_can('edit_post', $bundle_id)) {
                    return new WP_Error(
                        'rest_forbidden',
                        __('You do not have permission to edit this bundle.', 'tutorpress'),
                        ['status' => 403]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Get bundles list.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_bundles($request) {
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        $search = $request->get_param('search');

        $args = [
            'post_type'      => 'course-bundle',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];

        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $bundles = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bundle_id = get_the_ID();
                $bundles[] = [
                    'id'    => $bundle_id,
                    'title' => get_the_title(),
                    'slug'  => get_post_field('post_name'),
                ];
            }
        }

        wp_reset_postdata();

        return rest_ensure_response([
            'bundles'     => $bundles,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
        ]);
    }

    /**
     * Get single bundle.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_bundle($request) {
        $bundle_id = (int) $request->get_param('id');
        $bundle = get_post($bundle_id);

        if (!$bundle || $bundle->post_type !== 'course-bundle') {
            return new WP_Error(
                'bundle_not_found',
                __('Bundle not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        $response = [
            'id'          => $bundle_id,
            'title'       => $bundle->post_title,
            'content'     => $bundle->post_content,
            'slug'        => $bundle->post_name,
            'status'      => $bundle->post_status,
            'created'     => mysql_to_rfc3339($bundle->post_date),
            'modified'    => mysql_to_rfc3339($bundle->post_modified),
        ];

        return rest_ensure_response($response);
    }

    /**
     * Update bundle.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function update_bundle($request) {
        $bundle_id = (int) $request->get_param('id');
        $bundle = get_post($bundle_id);

        if (!$bundle || $bundle->post_type !== 'course-bundle') {
            return new WP_Error(
                'bundle_not_found',
                __('Bundle not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        $title = $request->get_param('title');
        $content = $request->get_param('content');

        $update_args = [
            'ID' => $bundle_id,
        ];

        if ($title !== null) {
            $update_args['post_title'] = sanitize_text_field($title);
        }

        if ($content !== null) {
            $update_args['post_content'] = wp_kses_post($content);
        }

        $result = wp_update_post($update_args, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->get_bundle($request);
    }

    /**
     * Get courses for a specific bundle.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_bundle_courses($request) {
        $bundle_id = (int) $request->get_param('id');
        
        // Validate bundle exists
        $bundle = get_post($bundle_id);
        if (!$bundle || $bundle->post_type !== 'course-bundle') {
            return new WP_Error(
                'bundle_not_found',
                __('Bundle not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Get bundle course IDs from meta - Tutor LMS uses 'bundle-course-ids'
        $course_ids_meta = get_post_meta($bundle_id, 'bundle-course-ids', true);
        
        // Handle different data formats: comma-separated string or array
        if (is_string($course_ids_meta) && !empty($course_ids_meta)) {
            $course_ids = array_map('intval', explode(',', $course_ids_meta));
        } elseif (is_array($course_ids_meta)) {
            $course_ids = array_map('intval', $course_ids_meta);
        } else {
            $course_ids = [];
        }

        // Get course details for each course ID
        $courses = [];
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            if ($course && $course->post_type === 'courses') {
                // Get additional course data
                $course_duration = get_post_meta($course_id, '_course_duration', true);
                $lesson_count = get_post_meta($course_id, '_lesson_count', true);
                $quiz_count = get_post_meta($course_id, '_quiz_count', true);
                $resource_count = get_post_meta($course_id, '_resource_count', true);
                
                // Get course price
                $price_type = get_post_meta($course_id, '_tutor_course_price_type', true);
                $price = '';
                if ($price_type === 'free') {
                    $price = __('Free', 'tutorpress');
                } else {
                    $regular_price = get_post_meta($course_id, '_tutor_course_price', true);
                    $sale_price = get_post_meta($course_id, '_tutor_course_sale_price', true);
                    $price = $sale_price ? $sale_price : $regular_price;
                    $price = $price ? '$' . $price : '';
                }

                $courses[] = [
                    'id' => $course_id,
                    'title' => $course->post_title,
                    'permalink' => get_permalink($course_id),
                    'featured_image' => get_the_post_thumbnail_url($course_id, 'thumbnail'),
                    'author' => get_the_author_meta('display_name', $course->post_author),
                    'date_created' => $course->post_date,
                    'price' => $price,
                    'duration' => $course_duration ? $course_duration : '',
                    'lesson_count' => $lesson_count ? (int) $lesson_count : 0,
                    'quiz_count' => $quiz_count ? (int) $quiz_count : 0,
                    'resource_count' => $resource_count ? (int) $resource_count : 0,
                ];
            }
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $courses,
            'total_found' => count($courses),
        ]);
    }

    /**
     * Update courses for a specific bundle.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function update_bundle_courses($request) {
        $bundle_id = (int) $request->get_param('id');
        $course_ids = $request->get_param('course_ids');
        
        // Validate bundle exists
        $bundle = get_post($bundle_id);
        if (!$bundle || $bundle->post_type !== 'course-bundle') {
            return new WP_Error(
                'bundle_not_found',
                __('Bundle not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Validate course IDs
        if (!is_array($course_ids)) {
            return new WP_Error(
                'invalid_course_ids',
                __('Course IDs must be an array.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Validate each course exists and is accessible
        $valid_course_ids = [];
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            if ($course && $course->post_type === 'courses') {
                // Check if user has permission to access this course
                if (current_user_can('edit_post', $course_id)) {
                    $valid_course_ids[] = $course_id;
                }
            }
        }

        // Update bundle course IDs meta - Tutor LMS uses 'bundle-course-ids' as comma-separated string
        $course_ids_string = implode(',', $valid_course_ids);
        $result = update_post_meta($bundle_id, 'bundle-course-ids', $course_ids_string);
        
        if ($result === false) {
            return new WP_Error(
                'update_failed',
                __('Failed to update bundle courses.', 'tutorpress'),
                ['status' => 500]
            );
        }

        // Return updated courses
        return $this->get_bundle_courses($request);
    }


} 