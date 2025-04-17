<?php
/**
 * REST API Class
 *
 * Handles REST API initialization and routing.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_rest_routes() {
        // Load base controller
        require_once TUTORPRESS_PATH . 'includes/rest/class-rest-controller.php';

        // Load and initialize specific controllers
        require_once TUTORPRESS_PATH . 'includes/rest/class-topics-controller.php';
        $topics_controller = new TutorPress_REST_Topics_Controller();
        $topics_controller->register_routes();
    }
} 