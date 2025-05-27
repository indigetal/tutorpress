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
        try {
            // Load base controller
            require_once TUTORPRESS_PATH . 'includes/rest/class-rest-controller.php';

            // Load and initialize specific controllers
            require_once TUTORPRESS_PATH . 'includes/rest/class-topics-controller.php';
            require_once TUTORPRESS_PATH . 'includes/rest/class-lessons-controller.php';
            require_once TUTORPRESS_PATH . 'includes/rest/class-assignments-controller.php';

            // Initialize controllers
            $controllers = [
                'topics'      => new TutorPress_REST_Topics_Controller(),
                'lessons'     => new TutorPress_REST_Lessons_Controller(),
                'assignments' => new TutorPress_REST_Assignments_Controller(),
            ];

            // Register routes for each controller
            foreach ($controllers as $name => $controller) {
                try {
                    $controller->register_routes();
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            '[TutorPress] Error registering %s routes: %s',
                            $name,
                            $e->getMessage()
                        ));
                    }
                }
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[TutorPress] Error initializing REST controllers: %s',
                    $e->getMessage()
                ));
            }
        }
    }
} 