<?php
/**
 * Lessons REST Controller Class
 *
 * Handles REST API functionality for lessons.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Lessons_Controller extends TutorPress_REST_Controller {

    /**
     * Initialize the controller.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        // Hook into post save, but after autosave processing
        add_action('save_post_lesson', [__CLASS__, 'handle_lesson_save'], 999, 3);
    }

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'lessons';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get lessons for a topic
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
                                'description'       => __('The ID of the topic to get lessons for.', 'tutorpress'),
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
                                'description'       => __('The ID of the topic to create the lesson in.', 'tutorpress'),
                            ],
                            'title' => [
                                'required'          => true,
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('The title of the lesson.', 'tutorpress'),
                            ],
                            'content' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'wp_kses_post',
                                'description'       => __('The content of the lesson.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Single lesson operations
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
                                'description'       => __('The title of the lesson.', 'tutorpress'),
                            ],
                            'content' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'wp_kses_post',
                                'description'       => __('The content of the lesson.', 'tutorpress'),
                            ],
                            'menu_order' => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('Order of the lesson in the topic.', 'tutorpress'),
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

            // Reorder lessons
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
                                'description'       => __('The ID of the topic to reorder lessons in.', 'tutorpress'),
                            ],
                            'lesson_orders' => [
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
                                'description'       => __('Array of lesson IDs and their new order positions.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Duplicate lesson
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
                                'description'       => __('The ID of the topic to duplicate the lesson to.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Get course ID for a lesson
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<id>[\d]+)/parent-info',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_parent_info'],
                        'permission_callback' => function($request) {
                            $lesson_id = (int) $request->get_param('id');
                            return current_user_can('edit_post', $lesson_id);
                        },
                        'args'               => [
                            'id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the lesson to get parent info for.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Get attachment metadata for video duration extraction
            register_rest_route(
                $this->namespace,
                '/attachments/(?P<id>\d+)',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_attachment_metadata'],
                        'permission_callback' => function() {
                            return current_user_can('edit_posts');
                        },
                        'args'                => [
                            'id' => [
                                'validate_callback' => function($param, $request, $key) {
                                    return is_numeric($param);
                                },
                            ],
                        ],
                    ],
                ]
            );

            // Test endpoint to verify controller registration
            register_rest_route(
                $this->namespace,
                '/test',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => function() {
                            return rest_ensure_response(['message' => 'Lessons controller is working']);
                        },
                        'permission_callback' => '__return_true',
                    ],
                ]
            );
        } catch (Exception $e) {
            // Silently handle any registration errors
            return;
        }
    }

    /**
     * Get the item schema for the controller.
     *
     * @return array Item schema data.
     */
    public function get_public_item_schema() {
        if ($this->schema) {
            return $this->schema;
        }

        $this->schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'lesson',
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'description' => __('Unique identifier for the lesson.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit', 'embed'],
                    'readonly'    => true,
                ],
                'title' => [
                    'description' => __('The title of the lesson.', 'tutorpress'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit', 'embed'],
                    'required'    => true,
                ],
                'content' => [
                    'description' => __('The content of the lesson.', 'tutorpress'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                ],
                'topic_id' => [
                    'description' => __('The ID of the topic this lesson belongs to.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                    'required'    => true,
                ],
                'menu_order' => [
                    'description' => __('Order of the lesson in the topic.', 'tutorpress'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                    'default'     => 0,
                ],
                'status' => [
                    'description' => __('Status of the lesson.', 'tutorpress'),
                    'type'        => 'string',
                    'enum'        => ['publish', 'draft', 'private'],
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
            ],
        ];

        return $this->schema;
    }

    /**
     * Get collection parameters for the items.
     *
     * @return array Collection parameters.
     */
    public function get_collection_params() {
        return [
            'topic_id' => [
                'required'          => true,
                'type'             => 'integer',
                'sanitize_callback' => 'absint',
                'description'       => __('The ID of the topic to get lessons for.', 'tutorpress'),
            ],
        ];
    }

    /**
     * Get lessons for a topic.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_items($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $topic_id = $request->get_param('topic_id');

            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('Invalid topic ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get lessons for this topic
            $lessons = get_posts([
                'post_type'      => 'lesson',
                'post_parent'    => $topic_id,
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'post_status'    => ['publish', 'draft', 'private'],
            ]);

            // Format lessons for response
            $formatted_lessons = array_map(function($lesson) {
                return [
                    'id'         => $lesson->ID,
                    'title'      => $lesson->post_title,
                    'content'    => $lesson->post_content,
                    'menu_order' => (int) $lesson->menu_order,
                    'status'     => $lesson->post_status,
                ];
            }, $lessons);

            return rest_ensure_response(
                $this->format_response(
                    $formatted_lessons,
                    __('Lessons retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'lessons_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Create a new lesson.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function create_item($request) {
        try {
            global $wpdb;

            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $topic_id = $request->get_param('topic_id');

            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('Invalid topic ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get the highest menu_order for the topic
            $max_order = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'lesson'",
                $topic_id
            ));

            // Create lesson
            $lesson_data = [
                'post_type'    => 'lesson',
                'post_title'   => $request->get_param('title'),
                'post_content' => $request->get_param('content', ''),
                'post_status'  => 'publish',
                'post_parent'  => $topic_id,
                'menu_order'   => (int) $max_order + 1,
            ];

            $lesson_id = wp_insert_post($lesson_data, true);

            if (is_wp_error($lesson_id)) {
                return $lesson_id;
            }

            // Get the created lesson
            $lesson = get_post($lesson_id);
            $response = rest_ensure_response(
                $this->format_response(
                    [
                        'id'         => $lesson->ID,
                        'title'      => $lesson->post_title,
                        'content'    => $lesson->post_content,
                        'menu_order' => (int) $lesson->menu_order,
                        'status'     => $lesson->post_status,
                    ],
                    __('Lesson created successfully.', 'tutorpress')
                )
            );

            $response->set_status(201);
            $response->header('Location', rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $lesson_id)));

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'lesson_creation_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update a lesson.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function update_item($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $lesson_id = (int) $request->get_param('id');
            
            // Validate lesson
            $lesson = get_post($lesson_id);
            if (!$lesson || $lesson->post_type !== 'lesson') {
                return new WP_Error(
                    'invalid_lesson',
                    __('Invalid lesson ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if user can edit this lesson
            if (!current_user_can('edit_post', $lesson_id)) {
                return new WP_Error(
                    'cannot_edit_lesson',
                    __('You do not have permission to edit this lesson.', 'tutorpress'),
                    ['status' => 403]
                );
            }

            // Update lesson
            $lesson_data = [
                'ID' => $lesson_id,
            ];

            if ($request->has_param('title')) {
                $lesson_data['post_title'] = $request->get_param('title');
            }

            if ($request->has_param('content')) {
                $lesson_data['post_content'] = $request->get_param('content');
            }

            if ($request->has_param('menu_order')) {
                $lesson_data['menu_order'] = $request->get_param('menu_order');
            }

            $updated = wp_update_post($lesson_data, true);

            if (is_wp_error($updated)) {
                return $updated;
            }

            // Get the updated lesson
            $lesson = get_post($lesson_id);

            return rest_ensure_response(
                $this->format_response(
                    [
                        'id'         => $lesson->ID,
                        'title'      => $lesson->post_title,
                        'content'    => $lesson->post_content,
                        'menu_order' => (int) $lesson->menu_order,
                        'status'     => $lesson->post_status,
                    ],
                    __('Lesson updated successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'lesson_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a lesson.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function delete_item($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $lesson_id = (int) $request->get_param('id');
            
            // Validate lesson
            $lesson = get_post($lesson_id);
            if (!$lesson || $lesson->post_type !== 'lesson') {
                return new WP_Error(
                    'invalid_lesson',
                    __('Invalid lesson ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if user can delete this lesson
            if (!current_user_can('delete_post', $lesson_id)) {
                return new WP_Error(
                    'cannot_delete_lesson',
                    __('You do not have permission to delete this lesson.', 'tutorpress'),
                    ['status' => 403]
                );
            }

            // Delete lesson
            $result = wp_delete_post($lesson_id, true);

            if (!$result) {
                return new WP_Error(
                    'lesson_deletion_failed',
                    __('Failed to delete lesson.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            $response = rest_ensure_response(
                $this->format_response(
                    null,
                    __('Lesson deleted successfully.', 'tutorpress')
                )
            );
            $response->set_status(204);

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'lesson_deletion_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Reorder lessons within a topic.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function reorder_items($request) {
        try {
            global $wpdb;

            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $topic_id = $request->get_param('topic_id');
            $lesson_orders = $request->get_param('lesson_orders');

            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('Invalid topic ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            try {
                // Update each lesson's menu_order
                foreach ($lesson_orders as $lesson_order) {
                    $lesson_id = $lesson_order['id'];
                    $order = $lesson_order['order'];

                    // Verify lesson belongs to topic
                    $lesson = get_post($lesson_id);
                    if (!$lesson || $lesson->post_type !== 'lesson' || $lesson->post_parent != $topic_id) {
                        throw new Exception(
                            sprintf(
                                __('Lesson %d does not belong to topic %d.', 'tutorpress'),
                                $lesson_id,
                                $topic_id
                            )
                        );
                    }

                    // Update menu_order
                    $result = $wpdb->update(
                        $wpdb->posts,
                        ['menu_order' => $order],
                        ['ID' => $lesson_id]
                    );

                    if ($result === false) {
                        throw new Exception(
                            sprintf(
                                __('Failed to update order for lesson %d.', 'tutorpress'),
                                $lesson_id
                            )
                        );
                    }
                }

                // Commit transaction
                $wpdb->query('COMMIT');

                // Get updated lessons
                $lessons = get_posts([
                    'post_type'      => 'lesson',
                    'post_parent'    => $topic_id,
                    'posts_per_page' => -1,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                    'post_status'    => ['publish', 'draft', 'private'],
                ]);

                // Format lessons for response
                $formatted_lessons = array_map(function($lesson) {
                    return [
                        'id'         => $lesson->ID,
                        'title'      => $lesson->post_title,
                        'content'    => $lesson->post_content,
                        'menu_order' => (int) $lesson->menu_order,
                        'status'     => $lesson->post_status,
                    ];
                }, $lessons);

                return rest_ensure_response(
                    $this->format_response(
                        $formatted_lessons,
                        __('Lessons reordered successfully.', 'tutorpress')
                    )
                );

            } catch (Exception $e) {
                // Rollback transaction on error
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            return new WP_Error(
                'lessons_reorder_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Duplicate a lesson.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function duplicate_item($request) {
        try {
            global $wpdb;

            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $lesson_id = $request['id'];
            $topic_id = $request->get_param('topic_id');

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Get the source lesson
            $lesson = get_post($lesson_id);
            if (!$lesson || $lesson->post_type !== 'lesson') {
                throw new Exception(__('Invalid lesson ID.', 'tutorpress'));
            }

            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                throw new Exception(__('Invalid topic ID.', 'tutorpress'));
            }

            // Get the highest menu_order for the topic
            $max_order = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'lesson'",
                $topic_id
            ));

            // Create the duplicate lesson
            $new_lesson_data = array(
                'post_type'    => $lesson->post_type,
                'post_title'   => sprintf('%s (copy)', $lesson->post_title),
                'post_content' => $lesson->post_content,
                'post_status'  => $lesson->post_status,
                'post_parent'  => $topic_id,
                'menu_order'   => (int) $max_order + 1,
            );

            // Insert the new lesson
            $new_lesson_id = wp_insert_post($new_lesson_data, true);
            if (is_wp_error($new_lesson_id)) {
                throw new Exception($new_lesson_id->get_error_message());
            }

            // Copy lesson meta
            $lesson_meta = get_post_meta($lesson_id);
            if ($lesson_meta) {
                foreach ($lesson_meta as $meta_key => $meta_values) {
                    // Skip internal meta
                    if (in_array($meta_key, ['_edit_lock', '_edit_last'])) {
                        continue;
                    }
                    foreach ($meta_values as $meta_value) {
                        add_post_meta($new_lesson_id, $meta_key, maybe_unserialize($meta_value));
                    }
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            // Get the newly created lesson
            $new_lesson = get_post($new_lesson_id);
            $formatted_lesson = [
                'id'         => $new_lesson->ID,
                'title'      => $new_lesson->post_title,
                'content'    => $new_lesson->post_content,
                'menu_order' => (int) $new_lesson->menu_order,
                'status'     => $new_lesson->post_status,
            ];

            return rest_ensure_response(
                $this->format_response(
                    $formatted_lesson,
                    __('Lesson duplicated successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');

            return new WP_Error(
                'lesson_duplication_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get parent info (topic and course) for a lesson.
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
     * - 'invalid_lesson': The lesson ID is invalid or the lesson doesn't exist
     * - 'invalid_topic': The lesson's parent topic is invalid or doesn't exist
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

            $lesson_id = (int) $request->get_param('id');

            // Validate lesson
            $lesson = get_post($lesson_id);
            if (!$lesson || $lesson->post_type !== 'lesson') {
                return new WP_Error(
                    'invalid_lesson',
                    __('The lesson could not be found. Please check the lesson ID and try again.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get parent topic
            $topic = get_post($lesson->post_parent);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('This lesson is not associated with a valid topic. Please check the lesson\'s parent topic.', 'tutorpress'),
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
                __('An unexpected error occurred while retrieving the lesson\'s parent information. Please try again.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Handle lesson save to set the post_parent if topic_id is present.
     * This runs after autosave processing to avoid premature parent setting.
     *
     * @since 0.1.0
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * @return void
     */
    public static function handle_lesson_save($post_id, $post, $update) {
        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Only proceed if this is a new lesson (not an update)
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

    /**
     * Get attachment metadata for video duration extraction
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_attachment_metadata($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $attachment_id = $request->get_param('id');

            // Validate attachment
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                return new WP_Error(
                    'invalid_attachment',
                    __('Invalid attachment ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if it's a video file
            $mime_type = get_post_mime_type($attachment_id);
            if (!str_starts_with($mime_type, 'video/')) {
                return new WP_Error(
                    'not_video',
                    __('Attachment is not a video file.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Get WordPress attachment metadata
            $metadata = wp_get_attachment_metadata($attachment_id);
            
            $response_data = [
                'id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'mime_type' => $mime_type,
                'duration' => null,
            ];
            
            // Extract video duration if available
            if ($metadata) {
                $duration = [
                    'hours' => 0,
                    'minutes' => 0,
                    'seconds' => 0,
                ];

                // Check for duration in metadata (WordPress 5.6+)
                if (isset($metadata['length_formatted'])) {
                    // Parse formatted duration like "1:23:45" or "23:45"
                    $parts = explode(':', $metadata['length_formatted']);
                    $parts = array_reverse($parts); // Start from seconds
                    
                    if (isset($parts[0])) {
                        $duration['seconds'] = (int) $parts[0];
                    }
                    if (isset($parts[1])) {
                        $duration['minutes'] = (int) $parts[1];
                    }
                    if (isset($parts[2])) {
                        $duration['hours'] = (int) $parts[2];
                    }
                } elseif (isset($metadata['length'])) {
                    // Duration in seconds
                    $total_seconds = (int) $metadata['length'];
                    $duration['hours'] = floor($total_seconds / 3600);
                    $duration['minutes'] = floor(($total_seconds % 3600) / 60);
                    $duration['seconds'] = $total_seconds % 60;
                }

                $response_data['duration'] = $duration;
            }

            return rest_ensure_response(
                $this->format_response(
                    $response_data,
                    __('Attachment metadata retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'attachment_metadata_fetch_error',
                __('An unexpected error occurred while retrieving the attachment metadata. Please try again.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }
} 