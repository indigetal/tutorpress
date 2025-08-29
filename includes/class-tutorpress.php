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
     * Feature flags service instance.
     *
     * @var TutorPress_Feature_Flags_Interface
     * @since 1.13.17
     */
    private $feature_flags;

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
        // All files are now loaded automatically by Composer autoloader
        // No manual require_once statements needed
    }

    /**
     * Initialize core components
     *
     * @since 1.13.17
     */
    private function init_core_components() {
        // Initialize service container
        $container = TutorPress_Service_Container::instance();

        // Register core services (interface-based)
        $this->feature_flags = new TutorPress_Feature_Flags();
        $container->register('feature_flags', $this->feature_flags);

        // Register Phase 3 data providers and permissions
        $container->register('course_provider', new TutorPress_Course_Provider());
        $container->register('permissions', new TutorPress_Permissions());

        // Register post type services
        $course = new TutorPress_Course();
        $lesson = new TutorPress_Lesson();
        $assignment = new TutorPress_Assignment();
        $bundle = new TutorPress_Bundle();
        
        $container->register('course', $course);
        $container->register('lesson', $lesson);
        $container->register('assignment', $assignment);
        $container->register('bundle', $bundle);

        // Register existing services using factories for lazy loading
        $container->register_factory('settings', function() {
            TutorPress_Settings::init();
            return new TutorPress_Settings();
        });

        $container->register_factory('assets', function() {
            TutorPress_Assets::init();
            return new TutorPress_Assets();
        });
        
        // Initialize Scripts first (for H5P filtering)
        TutorPress_Assets::init();
        
        // Initialize core classes using their static init methods
        TutorPress_Settings::init();
        TutorPress_Admin_Customizations::init();
        TutorPress_Dashboard_Overrides::init();
        TutorPress_Sidebar_Tabs::init();
        
        // Initialize template overrides (self-initializing class)
        TutorPress_Template_Overrides::init();
        
        // Initialize metaboxes using constructor pattern (following Sensei LMS)
        new TutorPress_Curriculum_Metabox(); // Shared curriculum metabox for all post types

        // Initialize settings panels that use static init pattern
        TutorPress_Content_Drip_Helpers::init();
        
        // Initialize Freemius integration
        TutorPress_Freemius::init();
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
        new TutorPress_REST_API();
    }

    /**
     * Initialize metadata handler
     *
     * @since 1.13.17
     */
    public function init_metadata_handler() {
        TutorPress_Metadata_Handler::init();
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
     * Get the feature flags service instance.
     *
     * @return TutorPress_Feature_Flags_Interface
     * @since 1.13.17
     */
    public function get_feature_flags(): TutorPress_Feature_Flags_Interface {
        return $this->feature_flags;
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