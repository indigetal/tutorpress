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
            // Check if Tutor LMS is available
            if (!function_exists('tutor')) {
                return;
            }

            // Load base controller
            require_once TUTORPRESS_PATH . 'includes/rest/class-rest-controller.php';

            // Load and initialize specific controllers
            require_once TUTORPRESS_PATH . 'includes/rest/class-topics-controller.php';
            require_once TUTORPRESS_PATH . 'includes/rest/class-lessons-controller.php';
            require_once TUTORPRESS_PATH . 'includes/rest/class-assignments-controller.php';
            require_once TUTORPRESS_PATH . 'includes/rest/class-quizzes-controller.php';

            // Initialize core controllers
            $controllers = [
                'topics'      => new TutorPress_REST_Topics_Controller(),
                'lessons'     => new TutorPress_REST_Lessons_Controller(),
                'assignments' => new TutorPress_REST_Assignments_Controller(),
                'quizzes'     => new TutorPress_REST_Quizzes_Controller(),
            ];

            // Conditionally load Certificate controller only if Certificate addon is available
            if (TutorPress_Addon_Checker::is_certificate_enabled()) {
                require_once TUTORPRESS_PATH . 'includes/rest/class-certificate-controller.php';
                $controllers['certificate'] = new TutorPress_Certificate_Controller();
            }

            // Always load Additional Content controller (core fields always available)
            require_once TUTORPRESS_PATH . 'includes/rest/class-additional-content-controller.php';
            $controllers['additional_content'] = new TutorPress_Additional_Content_Controller();

            // Always load Course Settings controller (core course settings always available)
            require_once TUTORPRESS_PATH . 'includes/rest/class-course-settings-controller.php';
            $controllers['course_settings'] = new TutorPress_Course_Settings_Controller();

            // Conditionally load H5P controller only if H5P addon is available
            if (TutorPress_Addon_Checker::is_h5p_enabled()) {
                require_once TUTORPRESS_PATH . 'includes/rest/class-h5p-controller.php';
                $controllers['h5p'] = new TutorPress_REST_H5P_Controller();
            }

            // Conditionally load Live Lessons controller only if either Live Lessons addon is available
            if (TutorPress_Addon_Checker::is_google_meet_enabled() || TutorPress_Addon_Checker::is_zoom_enabled()) {
                require_once TUTORPRESS_PATH . 'includes/rest/class-live-lessons-controller.php';
                $controllers['live_lessons'] = new TutorPress_REST_Live_Lessons_Controller();
            }

            // Conditionally load Content Drip controller only if Content Drip addon is available
            if (TutorPress_Addon_Checker::is_content_drip_enabled()) {
                require_once TUTORPRESS_PATH . 'includes/rest/class-content-drip-controller.php';
                $controllers['content_drip'] = new TutorPress_REST_Content_Drip_Controller();
            }

            // Conditionally load Subscriptions controller only if Subscription addon is available
            if (TutorPress_Addon_Checker::is_subscription_enabled()) {
                require_once TUTORPRESS_PATH . 'includes/rest/class-subscriptions-controller.php';
                $controllers['subscriptions'] = new TutorPress_REST_Subscriptions_Controller();
            }

            // Conditionally load product controllers (WooCommerce and EDD)
            if (TutorPress_Addon_Checker::is_woocommerce_enabled() || TutorPress_Addon_Checker::is_edd_enabled()) {
                require_once TUTORPRESS_PATH . 'includes/rest/class-product-controller.php';
                
                // Load WooCommerce controller if enabled
                if (TutorPress_Addon_Checker::is_woocommerce_enabled()) {
                    $controllers['woocommerce'] = new TutorPress_WooCommerce_Controller();
                }
                
                // Load EDD controller if enabled
                if (TutorPress_Addon_Checker::is_edd_enabled()) {
                    $controllers['edd'] = new TutorPress_EDD_Controller();
                }
            }

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