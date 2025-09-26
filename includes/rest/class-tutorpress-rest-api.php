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

class TutorPress_REST_API {

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

            // All controllers are now loaded automatically by Composer autoloader

            // Initialize core controllers
            $controllers = [
                'topics'      => new TutorPress_REST_Topics_Controller(),
                'lessons'     => new TutorPress_REST_Lessons_Controller(),
                'assignments' => new TutorPress_REST_Assignments_Controller(),
                'quizzes'     => new TutorPress_REST_Quizzes_Controller(),
            ];

            // Conditionally load Certificate controller only if certificates feature is available
            if (tutorpress_feature_flags()->can_user_access_feature('certificates')) {
                $controllers['certificate'] = new TutorPress_Certificate_Controller();
            }

            // Always load Additional Content controller (core fields always available)
            $controllers['additional_content'] = new TutorPress_Additional_Content_Controller();

            // Always load Course Settings controller (core course settings always available)
            $controllers['course_settings'] = new TutorPress_Course_Settings_Controller();

            // Conditionally load H5P controller only if H5P plugin is active
            if (TutorPress_Addon_Checker::is_h5p_plugin_active()) {
                $controllers['h5p'] = new TutorPress_REST_H5P_Controller();
            }

            // Conditionally load Live Lessons controller only if live lessons feature is available
            if (tutorpress_feature_flags()->can_user_access_feature('live_lessons')) {
                $controllers['live_lessons'] = new TutorPress_REST_Live_Lessons_Controller();
            }

            // Conditionally load Content Drip controller only if content drip feature is available
            if (tutorpress_feature_flags()->can_user_access_feature('content_drip')) {
                $controllers['content_drip'] = new TutorPress_REST_Content_Drip_Controller();
            }

            // Conditionally load Subscriptions controller only if subscriptions feature is available
            if (tutorpress_feature_flags()->can_user_access_feature('subscriptions')) {
                $controllers['subscriptions'] = new TutorPress_REST_Subscriptions_Controller();
            }

            // Conditionally load Bundle Settings controller only if Course Bundle addon is available
            if (TutorPress_Addon_Checker::is_course_bundle_enabled()) {
                $controllers['course_bundles'] = new TutorPress_REST_Course_Bundles_Controller();
            }

            // Conditionally load product controllers (WooCommerce and EDD)
            if (TutorPress_Addon_Checker::is_woocommerce_enabled() || TutorPress_Addon_Checker::is_edd_enabled()) {
                
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

            /**
             * Allow external integrations to register their own REST controllers.
             *
             * Plugins can hook into `tutorpress_register_rest_controllers` and
             * instantiate/register additional controllers that extend
             * `TutorPress_REST_Controller`. This action is fired after TutorPress
             * has registered its core controllers and is safe to use because the
             * base controller class and feature flags are available at this point.
             *
             * Example usage (in integration plugin):
             *
             * add_action( 'tutorpress_register_rest_controllers', function() {
             *     require_once plugin_dir_path(__FILE__) . 'includes/rest/class-pmpro-subscriptions-controller.php';
             *     if ( class_exists( 'TutorPress_REST_Controller' ) && class_exists( '\\TutorPress_PMPro_Subscriptions_Controller' ) ) {
             *         ( new \\TutorPress_PMPro_Subscriptions_Controller() )->register_routes();
             *     }
             * } );
             *
             * For a filter-based approach, integrations may also add class names to
             * the `tutorpress_rest_controllers` filter which returns an array of
             * fully-qualified class names to instantiate and register.
             */
            do_action( 'tutorpress_register_rest_controllers' );

            // Backward-compatible filter to allow plugins to return controller FQCNs
            $external_controllers = apply_filters( 'tutorpress_rest_controllers', [] );
            if ( is_array( $external_controllers ) && ! empty( $external_controllers ) ) {
                foreach ( $external_controllers as $ext_class ) {
                    if ( is_string( $ext_class ) && class_exists( $ext_class ) ) {
                        try {
                            $inst = new $ext_class();
                            if ( method_exists( $inst, 'register_routes' ) ) {
                                $inst->register_routes();
                            }
                        } catch ( Exception $e ) {
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( sprintf( '[TutorPress] Error registering external controller %s: %s', $ext_class, $e->getMessage() ) );
                            }
                        }
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