<?php
/**
 * Assignments REST Controller Class
 *
 * Handles REST API functionality for assignments.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Assignments_Controller extends TutorPress_REST_Controller {

    /**
     * Initialize the controller.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        // Hook into post save, but after autosave processing
        add_action('save_post_tutor_assignments', [__CLASS__, 'handle_assignment_save'], 999, 3);
    }

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'assignments';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        // Get assignments for a topic
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'topic_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic to get assignments for.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_item'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'topic_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic to create the assignment in.', 'tutorpress'),
                        ],
                        'title' => [
                            'required'          => true,
                            'type'             => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the assignment.', 'tutorpress'),
                        ],
                        'content' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The content of the assignment.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Single assignment operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => 'PATCH',
                    'callback'            => [$this, 'update_item'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'title' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the assignment.', 'tutorpress'),
                        ],
                        'content' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The content of the assignment.', 'tutorpress'),
                        ],
                        'menu_order' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Order of the assignment in the topic.', 'tutorpress'),
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_item'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

        // Reorder assignments
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/reorder',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'reorder_items'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'topic_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic to reorder assignments in.', 'tutorpress'),
                        ],
                        'assignment_orders' => [
                            'required'          => true,
                            'type'             => 'array',
                            'items'            => [
                                'type'       => 'object',
                                'required'   => ['id', 'order'],
                                'properties' => [
                                    'id'    => [
                                        'type'    => 'integer',
                                        'minimum' => 1,
                                    ],
                                    'order' => [
                                        'type'    => 'integer',
                                        'minimum' => 0,
                                    ],
                                ],
                            ],
                            'description'       => __('Array of assignment IDs and their new order positions.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Duplicate assignment
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'duplicate_item'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'topic_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the topic to duplicate the assignment to.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Get parent info (topic and course) for an assignment
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/parent-info',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_parent_info'],
                    'permission_callback' => function($request) {
                        $assignment_id = (int) $request->get_param('id');
                        return current_user_can('edit_post', $assignment_id);
                    },
                    'args'               => [
                        'id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the assignment to get parent info for.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Get the public schema for a single assignment item.
     *
     * @since 0.1.0
     * @return array
     */
    public function get_public_item_schema() {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'assignment',
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'description' => __('Unique identifier for the assignment.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
                'title' => [
                    'description' => __('The title for the assignment.', 'tutorpress'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
                'content' => [
                    'description' => __('The content for the assignment.', 'tutorpress'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
                'topic_id' => [
                    'description' => __('The ID of the topic this assignment belongs to.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                ],
                'course_id' => [
                    'description' => __('The ID of the course this assignment belongs to.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                ],
                'order' => [
                    'description' => __('The order of the assignment within the topic.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                ],
                'status' => [
                    'description' => __('The status of the assignment.', 'tutorpress'),
                    'type'        => 'string',
                    'enum'        => ['publish', 'draft', 'private'],
                    'context'     => ['view', 'edit'],
                ],
                'created_at' => [
                    'description' => __('The date the assignment was created.', 'tutorpress'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
                'updated_at' => [
                    'description' => __('The date the assignment was last updated.', 'tutorpress'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
            ],
        ];

        return $schema;
    }

    /**
     * Get the query params for collections.
     *
     * @since 0.1.0
     * @return array
     */
    public function get_collection_params() {
        return [
            'topic_id' => [
                'description'       => __('Limit result set to assignments for a specific topic.', 'tutorpress'),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Get assignments for a topic.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items($request) {
        $topic_id = $request->get_param('topic_id');

        if (!$topic_id) {
            return new WP_Error(
                'missing_topic_id',
                __('Topic ID is required.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Verify topic exists
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return new WP_Error(
                'invalid_topic',
                __('Invalid topic ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Get course ID from topic hierarchy
        $course = get_post($topic->post_parent);
        $course_id = ($course && $course->post_type === 'courses') ? $course->ID : 0;

        // Get assignments for this topic
        $assignments = get_posts([
            'post_type'      => 'tutor_assignments',
            'post_parent'    => $topic_id,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        $data = [];
        foreach ($assignments as $assignment) {
            $data[] = [
                'id'         => $assignment->ID,
                'title'      => $assignment->post_title,
                'content'    => $assignment->post_content,
                'topic_id'   => $assignment->post_parent,
                'course_id'  => $course_id,
                'order'      => $assignment->menu_order,
                'status'     => $assignment->post_status,
                'created_at' => $assignment->post_date,
                'updated_at' => $assignment->post_modified,
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Create a new assignment.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function create_item($request) {
        $topic_id = $request->get_param('topic_id');
        $title = $request->get_param('title');
        $content = $request->get_param('content') ?: '';

        // Verify topic exists
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return new WP_Error(
                'invalid_topic',
                __('Invalid topic ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Get course ID from topic hierarchy
        $course = get_post($topic->post_parent);
        $course_id = ($course && $course->post_type === 'courses') ? $course->ID : 0;

        // Get next menu order
        $existing_assignments = get_posts([
            'post_type'      => 'tutor_assignments',
            'post_parent'    => $topic_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $menu_order = count($existing_assignments);

        // Create assignment
        $assignment_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'tutor_assignments',
            'post_parent'  => $topic_id,
            'menu_order'   => $menu_order,
        ];

        $assignment_id = wp_insert_post($assignment_data);

        if (is_wp_error($assignment_id)) {
            return new WP_Error(
                'assignment_creation_failed',
                __('Failed to create assignment.', 'tutorpress'),
                ['status' => 500]
            );
        }

        // Get the created assignment
        $assignment = get_post($assignment_id);

        $data = [
            'id'         => $assignment->ID,
            'title'      => $assignment->post_title,
            'content'    => $assignment->post_content,
            'topic_id'   => $assignment->post_parent,
            'course_id'  => $course_id,
            'order'      => $assignment->menu_order,
            'status'     => $assignment->post_status,
            'created_at' => $assignment->post_date,
            'updated_at' => $assignment->post_modified,
        ];

        return rest_ensure_response([
            'success' => true,
            'message' => __('Assignment created successfully.', 'tutorpress'),
            'data'    => $data,
        ]);
    }

    /**
     * Update an assignment.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_item($request) {
        $assignment_id = (int) $request->get_param('id');
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $menu_order = $request->get_param('menu_order');

        // Verify assignment exists
        $assignment = get_post($assignment_id);
        if (!$assignment || $assignment->post_type !== 'tutor_assignments') {
            return new WP_Error(
                'invalid_assignment',
                __('Invalid assignment ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Prepare update data
        $update_data = ['ID' => $assignment_id];

        if ($title !== null) {
            $update_data['post_title'] = $title;
        }

        if ($content !== null) {
            $update_data['post_content'] = $content;
        }

        if ($menu_order !== null) {
            $update_data['menu_order'] = $menu_order;
        }

        // Update assignment
        $result = wp_update_post($update_data);

        if (is_wp_error($result)) {
            return new WP_Error(
                'assignment_update_failed',
                __('Failed to update assignment.', 'tutorpress'),
                ['status' => 500]
            );
        }

        // Get updated assignment
        $assignment = get_post($assignment_id);
        
        // Get course ID from topic hierarchy
        $topic = get_post($assignment->post_parent);
        $course = ($topic && $topic->post_type === 'topics') ? get_post($topic->post_parent) : null;
        $course_id = ($course && $course->post_type === 'courses') ? $course->ID : 0;

        $data = [
            'id'         => $assignment->ID,
            'title'      => $assignment->post_title,
            'content'    => $assignment->post_content,
            'topic_id'   => $assignment->post_parent,
            'course_id'  => $course_id,
            'order'      => $assignment->menu_order,
            'status'     => $assignment->post_status,
            'created_at' => $assignment->post_date,
            'updated_at' => $assignment->post_modified,
        ];

        return rest_ensure_response([
            'success' => true,
            'message' => __('Assignment updated successfully.', 'tutorpress'),
            'data'    => $data,
        ]);
    }

    /**
     * Delete an assignment.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function delete_item($request) {
        $assignment_id = (int) $request->get_param('id');

        // Verify assignment exists
        $assignment = get_post($assignment_id);
        if (!$assignment || $assignment->post_type !== 'tutor_assignments') {
            return new WP_Error(
                'invalid_assignment',
                __('Invalid assignment ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Delete assignment
        $result = wp_delete_post($assignment_id, true);

        if (!$result) {
            return new WP_Error(
                'assignment_deletion_failed',
                __('Failed to delete assignment.', 'tutorpress'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Assignment deleted successfully.', 'tutorpress'),
        ]);
    }

    /**
     * Reorder assignments within a topic.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function reorder_items($request) {
        $topic_id = $request->get_param('topic_id');
        $assignment_orders = $request->get_param('assignment_orders');

        // Verify topic exists
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return new WP_Error(
                'invalid_topic',
                __('Invalid topic ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Update menu order for each assignment
        foreach ($assignment_orders as $order_data) {
            $assignment_id = (int) $order_data['id'];
            $order = (int) $order_data['order'];

            // Verify assignment exists and belongs to this topic
            $assignment = get_post($assignment_id);
            if (!$assignment || $assignment->post_type !== 'tutor_assignments' || $assignment->post_parent != $topic_id) {
                continue; // Skip invalid assignments
            }

            wp_update_post([
                'ID'         => $assignment_id,
                'menu_order' => $order,
            ]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Assignments reordered successfully.', 'tutorpress'),
        ]);
    }

    /**
     * Duplicate an assignment.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function duplicate_item($request) {
        $assignment_id = (int) $request->get_param('id');
        $target_topic_id = $request->get_param('topic_id');

        // Verify source assignment exists
        $source_assignment = get_post($assignment_id);
        if (!$source_assignment || $source_assignment->post_type !== 'tutor_assignments') {
            return new WP_Error(
                'invalid_assignment',
                __('Invalid assignment ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Verify target topic exists
        $target_topic = get_post($target_topic_id);
        if (!$target_topic || $target_topic->post_type !== 'topics') {
            return new WP_Error(
                'invalid_topic',
                __('Invalid target topic ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Get course ID from target topic hierarchy
        $course = get_post($target_topic->post_parent);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'invalid_course',
                __('Target topic is not associated with a valid course.', 'tutorpress'),
                ['status' => 400]
            );
        }
        $course_id = $course->ID;

        // Get next menu order in target topic
        $existing_assignments = get_posts([
            'post_type'      => 'tutor_assignments',
            'post_parent'    => $target_topic_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $menu_order = count($existing_assignments);

        // Create duplicate assignment
        $duplicate_data = [
            'post_title'   => $source_assignment->post_title . ' (Copy)',
            'post_content' => $source_assignment->post_content,
            'post_status'  => 'draft',
            'post_type'    => 'tutor_assignments',
            'post_parent'  => $target_topic_id,
            'menu_order'   => $menu_order,
        ];

        $duplicate_id = wp_insert_post($duplicate_data);

        if (is_wp_error($duplicate_id)) {
            return new WP_Error(
                'assignment_duplication_failed',
                __('Failed to duplicate assignment.', 'tutorpress'),
                ['status' => 500]
            );
        }

        // Copy meta data
        $meta_data = get_post_meta($assignment_id);
        foreach ($meta_data as $key => $values) {
            // Skip internal meta
            if (in_array($key, ['_edit_lock', '_edit_last'])) {
                continue;
            }
            foreach ($values as $value) {
                add_post_meta($duplicate_id, $key, maybe_unserialize($value));
            }
        }

        // Get the duplicated assignment
        $duplicate_assignment = get_post($duplicate_id);

        $data = [
            'id'         => $duplicate_assignment->ID,
            'title'      => $duplicate_assignment->post_title,
            'content'    => $duplicate_assignment->post_content,
            'topic_id'   => $duplicate_assignment->post_parent,
            'course_id'  => $course_id,
            'order'      => $duplicate_assignment->menu_order,
            'status'     => $duplicate_assignment->post_status,
            'created_at' => $duplicate_assignment->post_date,
            'updated_at' => $duplicate_assignment->post_modified,
        ];

        return rest_ensure_response([
            'success' => true,
            'message' => __('Assignment duplicated successfully.', 'tutorpress'),
            'data'    => $data,
        ]);
    }

    /**
     * Get parent info (topic and course) for an assignment.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     * 
     * @throws WP_Error {
     *     @type string $code    Error code.
     *     @type string $message Error message.
     *     @type int    $status  HTTP status code.
     * }
     * 
     * Error codes:
     * - 'invalid_assignment': The assignment ID is invalid or the assignment doesn't exist
     * - 'invalid_topic': The assignment's parent topic is invalid or doesn't exist
     * - 'invalid_course': The topic's parent course is invalid or doesn't exist
     * - 'parent_info_fetch_error': An unexpected error occurred while fetching parent info
     */
    public function get_parent_info($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $assignment_id = (int) $request->get_param('id');

            // Validate assignment
            $assignment = get_post($assignment_id);
            if (!$assignment || $assignment->post_type !== 'tutor_assignments') {
                return new WP_Error(
                    'invalid_assignment',
                    __('The assignment could not be found. Please check the assignment ID and try again.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get parent topic
            $topic = get_post($assignment->post_parent);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('This assignment is not associated with a valid topic. Please check the assignment\'s parent topic.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get parent course
            $course = get_post($topic->post_parent);
            if (!$course || $course->post_type !== 'courses') {
                return new WP_Error(
                    'invalid_course',
                    __('The topic is not associated with a valid course. Please check the topic\'s parent course.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            return rest_ensure_response(
                $this->format_response(
                    [
                        'course_id' => $course->ID,
                        'topic_id'  => $topic->ID,
                    ],
                    __('Parent info retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'parent_info_fetch_error',
                __('An unexpected error occurred while retrieving the assignment\'s parent information. Please try again.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Handle assignment save to set the post_parent if topic_id is present.
     * This runs after autosave processing to avoid premature parent setting.
     *
     * @since 0.1.0
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * @return void
     */
    public static function handle_assignment_save($post_id, $post, $update) {
        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Only proceed if this is a new assignment (not an update)
        if ($update) {
            return;
        }

        // Check if we have a topic_id in the URL
        if (!isset($_GET['topic_id'])) {
            return;
        }

        $topic_id = absint($_GET['topic_id']);
        if (!$topic_id) {
            return;
        }

        // Verify the topic exists and is the correct post type
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_type !== 'topics') {
            return;
        }

        // Set the post_parent directly in the database to avoid infinite loops
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            ['post_parent' => $topic_id],
            ['ID' => $post_id],
            ['%d'],
            ['%d']
        );

        // Clear the post cache
        clean_post_cache($post_id);
    }
} 