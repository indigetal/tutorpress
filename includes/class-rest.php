<?php
/**
 * Handles REST API initialization and endpoint registration.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

// Import WordPress REST dependencies
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

class TutorPress_REST {

    /**
     * The namespace for our REST API endpoints.
     *
     * @var string
     */
    private $namespace = 'tutorpress/v1';

    /**
     * Initialize REST API functionality.
     *
     * @since 0.1.0
     */
    public function __construct() {
        // Register routes on rest_api_init
        add_action('rest_api_init', [$this, 'register_routes']);
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
            '/topics',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_topics'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'course_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get topics for.', 'tutorpress'),
                        ],
                    ],
                ]
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
    public function get_topics($request) {
        try {
            // Ensure Tutor LMS is active
            if (!function_exists('tutor')) {
                return new WP_Error(
                    'tutor_not_active',
                    __('Tutor LMS is not active.', 'tutorpress'),
                    ['status' => 500]
                );
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

            return rest_ensure_response([
                'success' => true,
                'message' => __('Topics retrieved successfully.', 'tutorpress'),
                'data'    => $formatted_topics,
            ]);

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
     * Check if user has permission to access endpoints.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_permission($request) {
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this endpoint.', 'tutorpress'),
                ['status' => 403]
            );
        }
        return true;
    }
} 