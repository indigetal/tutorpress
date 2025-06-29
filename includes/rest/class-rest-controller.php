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