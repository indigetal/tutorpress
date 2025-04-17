<?php
/**
 * Topics REST Controller Class
 *
 * Handles REST API functionality for topics.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

// Import WordPress REST dependencies
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class TutorPress_REST_Topics_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'topics';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        // Get topics for a course
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
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get topics for.', 'tutorpress'),
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
                            'description'       => __('The ID of the course to create the topic in.', 'tutorpress'),
                        ],
                        'title' => [
                            'required'          => true,
                            'type'             => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The title of the topic.', 'tutorpress'),
                        ],
                        'content' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The content of the topic.', 'tutorpress'),
                        ],
                        'menu_order' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Order of the topic in the course.', 'tutorpress'),
                            'default'           => 0,
                        ],
                    ],
                ],
            ]
        );

        // Single topic operations
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
                            'description'       => __('The title of the topic.', 'tutorpress'),
                        ],
                        'content' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'wp_kses_post',
                            'description'       => __('The content of the topic.', 'tutorpress'),
                        ],
                        'menu_order' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('Order of the topic in the course.', 'tutorpress'),
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
    }

    /**
     * Get topics for a course.
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

            $course_id = $request->get_param('course_id');

            // Validate course
            $validation_result = $this->validate_course_id($course_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Get topics for this course
            $topics = get_posts([
                'post_type'      => 'topics',
                'post_parent'    => $course_id,
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'post_status'    => ['publish', 'draft', 'private'],
            ]);

            // Format topics for response
            $formatted_topics = array_map(function($topic) {
                return [
                    'id'         => $topic->ID,
                    'title'      => $topic->post_title,
                    'content'    => $topic->post_content,
                    'menu_order' => (int) $topic->menu_order,
                    'status'     => $topic->post_status,
                    'contents'   => $this->get_topic_contents($topic->ID),
                ];
            }, $topics);

            return rest_ensure_response(
                $this->format_response(
                    $formatted_topics,
                    __('Topics retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'topics_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get content items (lessons, quizzes, etc.) for a topic.
     *
     * @since 0.1.0
     * @param int $topic_id The topic ID.
     * @return array Array of content items.
     */
    private function get_topic_contents($topic_id) {
        $content_items = get_posts([
            'post_type'      => ['lesson', 'quiz', 'assignment'],
            'post_parent'    => $topic_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => ['publish', 'draft', 'private'],
        ]);

        return array_map(function($item) {
            return [
                'id'         => $item->ID,
                'title'      => $item->post_title,
                'type'       => $item->post_type,
                'menu_order' => (int) $item->menu_order,
                'status'     => $item->post_status,
            ];
        }, $content_items);
    }

    /**
     * Validate a course ID.
     *
     * @since 0.1.0
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

        // Check if user can edit this course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'cannot_edit_course',
                sprintf(__('You do not have permission to edit course %d.', 'tutorpress'), $course_id),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Create a new topic.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function create_item($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $course_id = $request->get_param('course_id');

            // Validate course
            $validation_result = $this->validate_course_id($course_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Create topic
            $topic_data = [
                'post_type'    => 'topics',
                'post_title'   => $request->get_param('title'),
                'post_content' => $request->get_param('content', ''),
                'post_status'  => 'publish',
                'post_parent'  => $course_id,
                'menu_order'   => $request->get_param('menu_order', 0),
            ];

            $topic_id = wp_insert_post($topic_data, true);

            if (is_wp_error($topic_id)) {
                return $topic_id;
            }

            // Get the created topic
            $topic = get_post($topic_id);

            return rest_ensure_response(
                $this->format_response(
                    [
                        'id'         => $topic->ID,
                        'title'      => $topic->post_title,
                        'content'    => $topic->post_content,
                        'menu_order' => (int) $topic->menu_order,
                        'status'     => $topic->post_status,
                        'contents'   => [],  // New topic, so no contents yet
                    ],
                    __('Topic created successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'topic_creation_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Update a topic.
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

            $topic_id = (int) $request->get_param('id');
            
            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('Invalid topic ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if user can edit this topic
            if (!current_user_can('edit_post', $topic_id)) {
                return new WP_Error(
                    'cannot_edit_topic',
                    __('You do not have permission to edit this topic.', 'tutorpress'),
                    ['status' => 403]
                );
            }

            // Update topic
            $topic_data = [
                'ID' => $topic_id,
            ];

            if ($request->has_param('title')) {
                $topic_data['post_title'] = $request->get_param('title');
            }

            if ($request->has_param('content')) {
                $topic_data['post_content'] = $request->get_param('content');
            }

            if ($request->has_param('menu_order')) {
                $topic_data['menu_order'] = $request->get_param('menu_order');
            }

            $updated = wp_update_post($topic_data, true);

            if (is_wp_error($updated)) {
                return $updated;
            }

            // Get the updated topic
            $topic = get_post($topic_id);

            return rest_ensure_response(
                $this->format_response(
                    [
                        'id'         => $topic->ID,
                        'title'      => $topic->post_title,
                        'content'    => $topic->post_content,
                        'menu_order' => (int) $topic->menu_order,
                        'status'     => $topic->post_status,
                        'contents'   => $this->get_topic_contents($topic_id),
                    ],
                    __('Topic updated successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'topic_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a topic.
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

            $topic_id = (int) $request->get_param('id');
            
            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('Invalid topic ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if user can delete this topic
            if (!current_user_can('delete_post', $topic_id)) {
                return new WP_Error(
                    'cannot_delete_topic',
                    __('You do not have permission to delete this topic.', 'tutorpress'),
                    ['status' => 403]
                );
            }

            // Delete topic and its contents
            $result = wp_delete_post($topic_id, true);

            if (!$result) {
                return new WP_Error(
                    'topic_deletion_failed',
                    __('Failed to delete topic.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            return rest_ensure_response(
                $this->format_response(
                    null,
                    __('Topic deleted successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'topic_deletion_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
} 