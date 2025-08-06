<?php
/**
 * TutorPress Course Class
 *
 * Handles course-specific metaboxes and settings for TutorPress.
 * This class manages Gutenberg metaboxes and settings for the 'courses' post type
 * registered by Tutor LMS.
 *
 * @package TutorPress
 * @since 1.14.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * TutorPress_Course class.
 *
 * Manages course metaboxes and settings for TutorPress addon functionality.
 * Following Sensei LMS patterns for clean, organized architecture.
 *
 * @since 1.14.2
 */
class TutorPress_Course {

    /**
     * The post type token for courses.
     *
     * @since 1.14.2
     * @var string
     */
    public $token;

    /**
     * Constructor.
     *
     * Following Sensei LMS patterns for post type class initialization.
     *
     * @since 1.14.2
     */
    public function __construct() {
        $this->token = 'courses';

        // Initialize meta fields and REST API support
        add_action( 'init', [ $this, 'set_up_meta_fields' ] );
        add_action( 'rest_api_init', [ $this, 'add_author_support' ] );

        // Admin actions (following Sensei LMS pattern)
        if ( is_admin() ) {
            // Metabox functions
            add_action( 'add_meta_boxes', [ $this, 'meta_box_setup' ], 20 );
            add_action( 'save_post', [ $this, 'meta_box_save' ] );

            // Enqueue scripts
            add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_scripts' ] );
        }

        // Bidirectional sync hooks for Tutor LMS compatibility
        add_action( 'updated_post_meta', [ $this, 'handle_tutor_individual_field_update' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'handle_tutor_course_settings_update' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'handle_tutor_attachments_meta_update' ], 10, 4 );
        
        // Sync our fields to Tutor LMS when updated
        add_action( 'updated_post_meta', [ $this, 'handle_course_settings_update' ], 10, 4 );
        
        // Also hook into REST API updates (Gutenberg uses REST API, not traditional meta updates)
        add_action( 'rest_after_insert_courses', [ $this, 'handle_rest_course_update' ], 10, 3 );
        
        // Sync on course save
        add_action( 'save_post_courses', [ $this, 'sync_on_course_save' ], 999, 3 );
    }

    /**
     * Set up meta fields for courses.
     *
     * Following Sensei LMS pattern for meta field registration.
     *
     * @since 1.14.2
     * @return void
     */
    public function set_up_meta_fields() {
        // Register the course_settings meta field for Gutenberg editor
        register_post_meta( $this->token, 'course_settings', [
            'type'              => 'object',
            'description'       => __( 'Course settings for TutorPress Gutenberg integration', 'tutorpress' ),
            'single'            => true,
            'default'           => [],
            'sanitize_callback' => [ $this, 'sanitize_course_settings' ],
            'show_in_rest'      => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'course_level' => [
                            'type' => 'string',
                            'enum' => [ 'beginner', 'intermediate', 'expert', 'all_levels' ],
                        ],
                        'is_public_course' => [
                            'type' => 'boolean',
                        ],
                        'enable_qna' => [
                            'type' => 'boolean',
                        ],
                        'course_duration' => [
                            'type'       => 'object',
                            'properties' => [
                                'hours'   => [ 'type' => 'integer', 'minimum' => 0 ],
                                'minutes' => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 59 ],
                            ],
                        ],
                        'maximum_students' => [
                            'type'    => 'integer',
                            'minimum' => 0,
                        ],
                        'course_prerequisites' => [
                            'type'  => 'array',
                            'items' => [ 'type' => 'integer' ],
                        ],
                        'schedule' => [
                            'type'       => 'object',
                            'properties' => [
                                'enabled'          => [ 'type' => 'boolean' ],
                                'start_date'       => [ 'type' => 'string' ],
                                'start_time'       => [ 'type' => 'string' ],
                                'show_coming_soon' => [ 'type' => 'boolean' ],
                            ],
                        ],
                        'course_enrollment_period' => [
                            'type' => 'string',
                            'enum' => [ 'yes', 'no' ],
                        ],
                        'enrollment_starts_at' => [
                            'type' => 'string',
                        ],
                        'enrollment_ends_at' => [
                            'type' => 'string',
                        ],
                        'pause_enrollment' => [
                            'type' => 'string',
                            'enum' => [ 'yes', 'no' ],
                        ],
                        'intro_video' => [
                            'type'       => 'object',
                            'properties' => [
                                'source'               => [ 'type' => 'string' ],
                                'source_video_id'      => [ 'type' => 'integer' ],
                                'source_youtube'       => [ 'type' => 'string' ],
                                'source_vimeo'         => [ 'type' => 'string' ],
                                'source_external_url'  => [ 'type' => 'string' ],
                                'source_embedded'      => [ 'type' => 'string' ],
                                'source_shortcode'     => [ 'type' => 'string' ],
                                'poster'               => [ 'type' => 'string' ],
                            ],
                        ],
                        'attachments' => [
                            'type'  => 'array',
                            'items' => [ 'type' => 'integer' ],
                        ],
                        'course_material_includes' => [
                            'type' => 'string',
                        ],
                        'is_free' => [
                            'type' => 'boolean',
                        ],
                        'pricing_model' => [
                            'type' => 'string',
                        ],
                        'price' => [
                            'type'    => 'number',
                            'minimum' => 0,
                        ],
                        'sale_price' => [
                            'type'    => 'number',
                            'minimum' => 0,
                        ],
                        'selling_option' => [
                            'type' => 'string',
                            'enum' => [ 'one_time', 'subscription', 'both', 'membership', 'all' ],
                        ],
                        'woocommerce_product_id' => [
                            'type' => 'string',
                            'description' => __( 'WooCommerce product ID for product linking', 'tutorpress' ),
                        ],
                        'edd_product_id' => [
                            'type' => 'string',
                            'description' => __( 'EDD product ID for product linking', 'tutorpress' ),
                        ],
                        'instructors' => [
                            'type'  => 'array',
                            'items' => [ 'type' => 'integer' ],
                        ],
                        'additional_instructors' => [
                            'type'  => 'array',
                            'items' => [ 'type' => 'integer' ],
                        ],
                    ],
                ],
            ],
        ] );

        // Register individual meta fields for Gutenberg access
        $individual_meta_fields = [
            '_tutor_course_level' => [
                'type' => 'string',
                'description' => __( 'Course difficulty level', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_is_public_course' => [
                'type' => 'string',
                'description' => __( 'Whether the course is public', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_enable_qa' => [
                'type' => 'string',
                'description' => __( 'Whether Q&A is enabled', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_course_duration' => [
                'type' => 'object',
                'description' => __( 'Course duration', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_price_type' => [
                'type' => 'string',
                'description' => __( 'Course pricing type', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            'tutor_course_price' => [
                'type' => 'number',
                'description' => __( 'Course price', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            'tutor_course_sale_price' => [
                'type' => 'number',
                'description' => __( 'Course sale price', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_selling_option' => [
                'type' => 'string',
                'description' => __( 'Course selling option', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_product_id' => [
                'type' => 'string',
                'description' => __( 'WooCommerce product ID for product linking', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_prerequisites_ids' => [
                'type' => 'array',
                'description' => __( 'Course prerequisites', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_material_includes' => [
                'type' => 'string',
                'description' => __( 'Course materials', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_video' => [
                'type' => 'object',
                'description' => __( 'Course intro video', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_attachments' => [
                'type' => 'array',
                'description' => __( 'Course attachments', 'tutorpress' ),
                'single' => true,
                'show_in_rest' => true,
            ],
        ];

        foreach ( $individual_meta_fields as $meta_key => $config ) {
            register_post_meta( $this->token, $meta_key, $config );
        }
    }

    /**
     * Add author support when it's a REST request to allow save teacher via the Rest API.
     *
     * Following Sensei LMS pattern for REST API support.
     *
     * @since 1.14.2
     * @return void
     */
    public function add_author_support() {
        add_post_type_support( $this->token, 'author' );

        // Register REST API fields for course settings
        register_rest_field( $this->token, 'course_settings', [
            'get_callback'    => [ $this, 'get_course_settings' ],
            'update_callback' => [ $this, 'update_course_settings' ],
            'schema'          => [
                'description' => __( 'Course settings', 'tutorpress' ),
                'type'        => 'object',
            ],
        ] );
    }

    /**
     * Register admin scripts.
     *
     * Following Sensei LMS pattern for admin script registration.
     * Conditionally enqueue editor assets when on course edit screen.
     *
     * @since 1.14.2
     * @return void
     */
    public function register_admin_scripts() {
        $hook_suffix = get_current_screen() ? get_current_screen()->id : '';
        
        if ( ! in_array( $hook_suffix, array( 'post', 'post-new' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'courses' ), true ) ) {
            return;
        }

        // Enqueue is handled in TutorPress_Scripts class
        // Certificate-specific scripts will be loaded when certificate addon is enabled
    }

    /**
     * Meta box setup.
     *
     * Following Sensei LMS pattern for metabox registration.
     *
     * @since 1.14.2
     * @return void
     */
    public function meta_box_setup() {
        // Certificate Metabox (addon-dependent)
        if ( TutorPress_Addon_Checker::is_certificate_enabled() ) {
            add_meta_box(
                'tutorpress_certificate_metabox', // Keep original ID for compatibility
                __( 'Certificate', 'tutorpress' ),
                [ $this, 'certificate_metabox_content' ],
                $this->token,
                'normal',
                'default'
            );
        }

        // Additional Content Metabox (always available)
        add_meta_box(
            'tutorpress_additional_content_metabox', // Keep original ID for compatibility
            __( 'Additional Course Content', 'tutorpress' ),
            [ $this, 'additional_content_metabox_content' ],
            $this->token,
            'normal',
            'default'
        );
    }

    /**
     * Meta box save.
     *
     * Following Sensei LMS pattern for metabox save handling.
     *
     * @since 1.14.2
     * @param int $post_id The post ID.
     * @return void
     */
    public function meta_box_save( $post_id ) {
        // Metabox save logic will be implemented in future phases
        // This method exists for Sensei LMS pattern compliance
    }

    /**
     * Certificate metabox content.
     *
     * Extracted from Certificate_Metabox::display_metabox().
     * Renders the PHP-based UI structure that will be enhanced with React/TypeScript
     * for interactive functionality. The display logic is premium-only (controlled via
     * Freemius's @fs_premium_only directory exclusion).
     *
     * @since 1.14.2
     * @param WP_Post $post Current post object.
     * @return void
     */
    public function certificate_metabox_content( $post ) {
        // Nonce action for the metabox
        $nonce_action = 'tutorpress_certificate_nonce';
        
        wp_nonce_field( $nonce_action, 'tutorpress_certificate_nonce' );

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
            return;
        }
        ?>
        <div 
            id="tutorpress-certificate-builder" 
            class="tutorpress-certificate-metabox"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-post-type="<?php echo esc_attr( $post->post_type ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( $nonce_action ) ); ?>"
            data-rest-url="<?php echo esc_url( get_rest_url() ); ?>"
            data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
        >
            <div class="tutorpress-certificate-container">
                <div class="tutorpress-certificate-content">
                    <div id="tutorpress-certificate-root">
                        <?php esc_html_e( 'Loading certificate builder...', 'tutorpress' ); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Additional content metabox content.
     *
     * Extracted from Additional_Content_Metabox::display_metabox().
     *
     * @since 1.14.2
     * @param WP_Post $post Current post object.
     * @return void
     */
    public function additional_content_metabox_content( $post ) {
        // TODO: Extract logic from Additional_Content_Metabox::display_metabox()
        // This will be implemented in Phase 2, Step 2.2
    }

    /**
     * Get course settings for REST API.
     *
     * Extracted from TutorPress_Course_Settings::get_course_settings().
     *
     * @since 1.14.2
     * @param array $post Post data.
     * @return array Course settings.
     */
    public function get_course_settings( $post ) {
        // TODO: Extract logic from TutorPress_Course_Settings::get_course_settings()
        // This will be implemented in Phase 3, Step 3.2
        return [];
    }

    /**
     * Update course settings.
     *
     * Extracted from TutorPress_Course_Settings::update_course_settings().
     *
     * @since 1.14.2
     * @param array $value Settings to update.
     * @param WP_Post $post Post object.
     * @return bool Whether the update was successful.
     */
    public function update_course_settings( $value, $post ) {
        // TODO: Extract logic from TutorPress_Course_Settings::update_course_settings()
        // This will be implemented in Phase 3, Step 3.2
        return false;
    }

    /**
     * Sanitize course settings.
     *
     * Extracted from TutorPress_Course_Settings::sanitize_course_settings().
     *
     * @since 1.14.2
     * @param array $settings Course settings to sanitize.
     * @return array Sanitized settings.
     */
    public function sanitize_course_settings( $settings ) {
        // TODO: Extract logic from TutorPress_Course_Settings::sanitize_course_settings()
        // This will be implemented in Phase 3, Step 3.1
        return [];
    }

    /**
     * Handle Tutor LMS individual field updates.
     *
     * Extracted from TutorPress_Course_Settings::handle_tutor_individual_field_update().
     *
     * @since 1.14.2
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public function handle_tutor_individual_field_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        // TODO: Extract logic from TutorPress_Course_Settings::handle_tutor_individual_field_update()
        // This will be implemented in Phase 3, Step 3.3
    }

    /**
     * Handle Tutor LMS course settings updates.
     *
     * Extracted from TutorPress_Course_Settings::handle_tutor_course_settings_update().
     *
     * @since 1.14.2
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public function handle_tutor_course_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        // TODO: Extract logic from TutorPress_Course_Settings::handle_tutor_course_settings_update()
        // This will be implemented in Phase 3, Step 3.3
    }

    /**
     * Handle Tutor LMS attachments meta updates.
     *
     * Extracted from TutorPress_Course_Settings::handle_tutor_attachments_meta_update().
     *
     * @since 1.14.2
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public function handle_tutor_attachments_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        // TODO: Extract logic from TutorPress_Course_Settings::handle_tutor_attachments_meta_update()
        // This will be implemented in Phase 3, Step 3.3
    }

    /**
     * Handle course settings updates.
     *
     * Extracted from TutorPress_Course_Settings::handle_course_settings_update().
     *
     * @since 1.14.2
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public function handle_course_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        // TODO: Extract logic from TutorPress_Course_Settings::handle_course_settings_update()
        // This will be implemented in Phase 3, Step 3.3
    }

    /**
     * Handle REST API course updates.
     *
     * Extracted from TutorPress_Course_Settings::handle_rest_course_update().
     *
     * @since 1.14.2
     * @param WP_Post $post Post object.
     * @param WP_REST_Request $request Request object.
     * @param bool $creating Whether this is a new post.
     * @return void
     */
    public function handle_rest_course_update( $post, $request, $creating ) {
        // TODO: Extract logic from TutorPress_Course_Settings::handle_rest_course_update()
        // This will be implemented in Phase 3, Step 3.3
    }

    /**
     * Sync on course save.
     *
     * Extracted from TutorPress_Course_Settings::sync_on_course_save().
     *
     * @since 1.14.2
     * @param int $post_id Post ID.
     * @param object $post Post object.
     * @param bool $update Whether this is an update.
     * @return void
     */
    public function sync_on_course_save( $post_id, $post, $update ) {
        // TODO: Extract logic from TutorPress_Course_Settings::sync_on_course_save()
        // This will be implemented in Phase 3, Step 3.3
    }
} 