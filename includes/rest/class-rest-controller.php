<?php
/**
 * Base REST Controller Class
 *
 * Provides shared functionality for all REST controllers.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Controller extends WP_REST_Controller {

    /**
     * The namespace for our REST API endpoints.
     *
     * @var string
     */
    protected $namespace = 'tutorpress/v1';

    /**
     * The base for this controller's route.
     *
     * @var string
     */
    protected $rest_base;

    /**
     * Register routes for this controller.
     * Child classes should override this method to register their routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        // Child classes should override this method
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

    /**
     * Generic ownership denial without object details.
     *
     * @since 2.3.0
     * @return WP_Error
     */
    protected function rest_ownership_error() {
        return new WP_Error(
            'rest_forbidden',
            __('You do not have permission to access this endpoint.', 'tutorpress'),
            ['status' => 403]
        );
    }

    /**
     * Normalize a request-derived object ID.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request The request object.
     * @param string          $param   Parameter name.
     * @return int
     */
    protected function get_request_object_id($request, $param = 'id') {
        return absint($request->get_param($param));
    }

    /**
     * Load a post after absint() and require an expected type.
     *
     * @since 2.3.0
     * @param mixed $id         Raw object ID.
     * @param array $post_types Allowed post types.
     * @return WP_Post|null
     */
    protected function get_object_of_type($id, array $post_types) {
        $id = absint($id);
        if (!$id) {
            return null;
        }

        $post = get_post($id);
        if (!$post || !in_array($post->post_type, $post_types, true)) {
            return null;
        }

        return $post;
    }

    /**
     * Authorize a Course by canonical Course policy.
     *
     * @since 2.3.0
     * @param mixed $course_id Course ID.
     * @return true|WP_Error
     */
    protected function authorize_course_object($course_id) {
        $types = ['courses'];
        if (function_exists('tutor') && is_object(tutor()) && !empty(tutor()->course_post_type)) {
            $types[] = tutor()->course_post_type;
        }

        $post = $this->get_object_of_type($course_id, array_unique($types));
        if (!$post || !tutorpress_permissions()->can_user_access_course((int) $post->ID)) {
            return $this->rest_ownership_error();
        }

        return true;
    }

    /**
     * Authorize a Topic via its actual parent Course.
     *
     * @since 2.3.0
     * @param mixed $topic_id Topic ID.
     * @return true|WP_Error
     */
    protected function authorize_topic_object($topic_id) {
        $types = ['topics'];
        if (function_exists('tutor') && is_object(tutor()) && !empty(tutor()->topic_post_type)) {
            $types[] = tutor()->topic_post_type;
        }

        $post = $this->get_object_of_type($topic_id, array_unique($types));
        if (!$post) {
            return $this->rest_ownership_error();
        }

        return $this->authorize_course_object($post->post_parent);
    }

    /**
     * Authorize a Lesson or Assignment via parent-Course policy.
     *
     * @since 2.3.0
     * @param mixed $post_id Child post ID.
     * @return true|WP_Error
     */
    protected function authorize_course_content_object($post_id) {
        $types = ['lesson', 'tutor_assignments'];
        if (function_exists('tutor') && is_object(tutor())) {
            if (!empty(tutor()->lesson_post_type)) {
                $types[] = tutor()->lesson_post_type;
            }
            if (!empty(tutor()->assignment_post_type)) {
                $types[] = tutor()->assignment_post_type;
            }
        }

        $post = $this->get_object_of_type($post_id, array_unique($types));
        if (!$post || !tutorpress_permissions()->can_user_edit_course_content((int) $post->ID)) {
            return $this->rest_ownership_error();
        }

        return true;
    }

    /**
     * Authorize a Bundle by authorship policy.
     *
     * @since 2.3.0
     * @param mixed $bundle_id Bundle ID.
     * @return true|WP_Error
     */
    protected function authorize_bundle_object($bundle_id) {
        $post = $this->get_object_of_type($bundle_id, ['course-bundle']);
        if (!$post || !tutorpress_permissions()->can_user_edit_bundle((int) $post->ID)) {
            return $this->rest_ownership_error();
        }

        return true;
    }

    /**
     * Format response data with consistent structure.
     *
     * @since 0.1.0
     * @param mixed  $data    The data to format.
     * @param string $message Optional message to include.
     * @return array Formatted response data.
     */
    protected function format_response($data, $message = '') {
        return [
            'success' => true,
            'message' => $message ?: __('Request successful.', 'tutorpress'),
            'data'    => $data,
        ];
    }

    /**
     * Ensure Tutor LMS is active and available.
     *
     * @since 0.1.0
     * @return bool|WP_Error True if active, WP_Error if not.
     */
    protected function ensure_tutor_lms() {
        if (!function_exists('tutor')) {
            return new WP_Error(
                'tutor_not_active',
                __('Tutor LMS is not active.', 'tutorpress'),
                ['status' => 500]
            );
        }
        return true;
    }

    /**
     * Get the item schema name for the controller.
     *
     * @return string
     */
    protected function get_schema_title() {
        return $this->rest_base;
    }
} 