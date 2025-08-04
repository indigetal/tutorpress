<?php
/**
 * TutorPress Main Class
 *
 * Responsible for loading TutorPress and setting up the main WordPress hooks.
 * Follows Sensei LMS patterns for clean, maintainable architecture.
 *
 * @package TutorPress
 * @since 1.13.17
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main TutorPress class
 *
 * @package TutorPress
 * @since 1.13.17
 */
class TutorPress_Main {

    /**
     * The main TutorPress Instance.
     *
     * @var TutorPress_Main
     * @since 1.13.17
     */
    protected static $_instance = null;

    /**
     * Main reference to the plugin's current version
     *
     * @var string
     */
    public $version;

    /**
     * Plugin URL and path for use when accessing resources.
     *
     * @var string
     */
    public $plugin_url;
    public $plugin_path;

    /**
     * Constructor function.
     *
     * @param string $main_plugin_file_name The main plugin file name.
     * @param array  $args                  Arguments to pass to the constructor.
     */
    private function __construct( $main_plugin_file_name, $args = array() ) {
        $this->version = isset( $args['version'] ) ? $args['version'] : '1.13.17';
        
        // Use the main file from args if provided, otherwise use the passed file
        $main_file = isset( $args['main_file'] ) ? $args['main_file'] : $main_plugin_file_name;
        
        $this->plugin_url = plugin_dir_url( $main_file );
        $this->plugin_path = trailingslashit( dirname( $main_file ) );

        $this->init();
    }

    /**
     * Initialize the plugin
     *
     * @since 1.13.17
     */
    private function init() {
        // Check dependencies first
        $this->check_dependencies();
        
        // Load core components
        $this->load_core_components();
        
        // Set up hooks
        $this->load_hooks();
    }

    /**
     * Check plugin dependencies
     *
     * @since 1.13.17
     */
    private function check_dependencies() {
        require_once $this->plugin_path . 'includes/class-tutorpress-dependency-checker.php';
        
        $errors = TutorPress_Dependency_Checker::check_all_requirements();
        if ( ! empty( $errors ) ) {
            TutorPress_Dependency_Checker::display_errors( $errors );
            return;
        }
    }

    /**
     * Load core components
     *
     * @since 1.13.17
     */
    private function load_core_components() {
        // Load all required files
        $this->load_required_files();
        
        // Initialize core components
        $this->init_core_components();
    }

    /**
     * Load all required files
     *
     * @since 1.13.17
     */
    private function load_required_files() {
        // Core classes
        require_once $this->plugin_path . 'includes/class-settings.php';
        require_once $this->plugin_path . 'includes/class-tutorpress-templates.php';
        require_once $this->plugin_path . 'includes/class-metadata-handler.php';
        require_once $this->plugin_path . 'includes/class-admin-customizations.php';
        require_once $this->plugin_path . 'includes/class-scripts.php';
        require_once $this->plugin_path . 'includes/class-rest.php';
        require_once $this->plugin_path . 'includes/class-frontend-customizations.php';
        
        // Gutenberg components
        require_once $this->plugin_path . 'includes/gutenberg/utilities/class-addon-checker.php';
        require_once $this->plugin_path . 'includes/gutenberg/metaboxes/class-curriculum-metabox.php';
        require_once $this->plugin_path . 'includes/gutenberg/metaboxes/class-certificate-metabox.php';
        require_once $this->plugin_path . 'includes/gutenberg/metaboxes/class-additional-content-metabox.php';
        require_once $this->plugin_path . 'includes/gutenberg/metaboxes/class-bundle-courses-metabox.php';
        require_once $this->plugin_path . 'includes/gutenberg/metaboxes/class-bundle-benefits-metabox.php';
        
        // Settings panels
        require_once $this->plugin_path . 'includes/gutenberg/settings/class-assignment-settings.php';
        require_once $this->plugin_path . 'includes/gutenberg/settings/class-lesson-settings.php';
        require_once $this->plugin_path . 'includes/gutenberg/settings/class-course-settings.php';
        require_once $this->plugin_path . 'includes/gutenberg/settings/class-content-drip-helpers.php';
        require_once $this->plugin_path . 'includes/gutenberg/settings/class-bundle-settings.php';
        
        // REST controllers
        require_once $this->plugin_path . 'includes/rest/class-rest-controller.php';
        require_once $this->plugin_path . 'includes/rest/class-lessons-controller.php';
        require_once $this->plugin_path . 'includes/rest/class-topics-controller.php';
        require_once $this->plugin_path . 'includes/rest/class-assignments-controller.php';
        require_once $this->plugin_path . 'includes/rest/class-quizzes-controller.php';
    }

    /**
     * Initialize core components
     *
     * @since 1.13.17
     */
    private function init_core_components() {
        // Initialize core classes using their static init methods
        TutorPress_Settings::init();
        TutorPress_Admin_Customizations::init();
        TutorPress_Scripts::init();
        TutorPress_Frontend_Customizations::init();
        
        // Initialize components that use static init pattern
        Bundle_Courses_Metabox::init();
        Bundle_Benefits_Metabox::init();
        TutorPress_Assignment_Settings::init();
        TutorPress_Lesson_Settings::init();
        TutorPress_Course_Settings::init();
        TutorPress_Content_Drip_Helpers::init();
        TutorPress_Bundle_Settings::init();
    }

    /**
     * Load WordPress hooks
     *
     * @since 1.13.17
     */
    private function load_hooks() {
        // Initialize components on appropriate hooks
        add_action( 'init', array( $this, 'init_rest_api' ) );
        add_action( 'init', array( $this, 'init_metadata_handler' ) );
    }

    /**
     * Initialize REST API
     *
     * @since 1.13.17
     */
    public function init_rest_api() {
        // Initialize REST controllers
        TutorPress_REST_Lessons_Controller::init();
        TutorPress_REST_Assignments_Controller::init();
        TutorPress_REST_Quizzes_Controller::init();
        
        // Initialize REST API
        new TutorPress_REST();
    }

    /**
     * Initialize metadata handler
     *
     * @since 1.13.17
     */
    public function init_metadata_handler() {
        Tutor_LMS_Metadata_Handler::init();
    }

    /**
     * Get the main TutorPress Instance.
     *
     * @param array $args Arguments to pass to the constructor.
     * @return TutorPress_Main
     * @since 1.13.17
     */
    public static function instance( $args = array() ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( __FILE__, $args );
        }
        return self::$_instance;
    }

    /**
     * Get the plugin version
     *
     * @return string
     * @since 1.13.17
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get the plugin URL
     *
     * @return string
     * @since 1.13.17
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }

    /**
     * Get the plugin path
     *
     * @return string
     * @since 1.13.17
     */
    public function get_plugin_path() {
        return $this->plugin_path;
    }

    /**
     * Check if Tutor LMS is available
     *
     * @return bool
     * @since 1.13.17
     */
    public function is_tutor_lms_available() {
        return function_exists( 'tutor' );
    }

    /**
     * Prevent cloning
     *
     * @since 1.13.17
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'tutorpress' ), '1.13.17' );
    }

    /**
     * Prevent unserializing
     *
     * @since 1.13.17
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is forbidden.', 'tutorpress' ), '1.13.17' );
    }
} 