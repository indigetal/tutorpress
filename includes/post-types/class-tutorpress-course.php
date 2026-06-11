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
     * Course sync context helper.
     *
     * @since 1.14.3
     * @var TutorPress_Course_Sync_Context
     */
    private $sync_context;

    /**
     * Course sync service.
     *
     * @since 1.14.3
     * @var TutorPress_Course_Sync_Service
     */
    private $sync_service;

    /**
     * Constructor.
     *
     * @since 1.14.2
     */
    public function __construct() {
        $this->token = 'courses';
        $this->sync_context = new TutorPress_Course_Sync_Context( $this->token );
        $this->sync_service = new TutorPress_Course_Sync_Service( $this->sync_context );

        // Initialize meta fields and REST API support
        add_action( 'init', [ $this, 'set_up_meta_fields' ] );
        add_action( 'rest_api_init', [ $this, 'add_author_support' ] );

        // Admin actions
        if ( is_admin() ) {
            // Metabox functions
            add_action( 'add_meta_boxes', [ $this, 'meta_box_setup' ], 20 );
            add_action( 'save_post', [ $this, 'meta_box_save' ] );

            // Enqueue scripts
            add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_scripts' ] );
        }

        // Ensure map_meta_cap is present at registration time for courses
        add_filter( 'register_post_type_args', [ $this, 'add_map_meta_cap_to_course' ], 5, 2 );

        // Remove Tutor meta caps from the global lookup table after post types are registered
        add_action( 'init', [ $this, 'remove_tutor_meta_caps_from_global' ], 20 );
        
        // Allow instructors to edit their own published courses
        add_filter( 'map_meta_cap', [ $this, 'map_course_meta_cap' ], 10, 4 );

        // Conditionally add "Edit Course" admin-bar link based on dashboard redirects setting (priority 71 for top positioning)
        add_action( 'admin_bar_menu', [ $this, 'conditionally_add_edit_course_link' ], 71 );
        // Remove Tutor LMS's "Edit with Course Builder" link when dashboard redirects are enabled (priority 101, after Tutor LMS's 100)
        add_action( 'admin_bar_menu', [ $this, 'remove_tutor_edit_link_if_redirects_enabled' ], 101 );
        add_action( 'wp_head', [ $this, 'output_admin_bar_course_icon_css' ] );

        // Compatibility shadow refresh hooks for Tutor LMS-backed storage
        add_action( 'updated_post_meta', [ $this, 'handle_tutor_individual_field_update' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'handle_tutor_course_settings_update' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'handle_tutor_attachments_meta_update' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'handle_tutor_attachments_meta_update' ], 10, 4 );
        add_action( 'deleted_post_meta', [ $this, 'handle_tutor_attachments_meta_delete' ], 10, 4 );
        
        // Also hook into REST API updates (Gutenberg uses REST API, not traditional meta updates)
        add_action( 'rest_after_insert_courses', [ $this, 'handle_rest_course_update' ], 10, 3 );
        
        // Sync on course save
        add_action( 'save_post_courses', [ $this, 'sync_on_course_save' ], 999, 3 );
    }

    /**
     * Get the authoritative course settings REST schema.
     *
     * The top-level course_settings field is the contract the editor hydrates from.
     * Compatibility-only fields remain explicit in schema so the rollout shape stays stable.
     *
     * @since 1.14.2
     * @return array REST schema.
     */
    private function get_course_settings_rest_schema() {
        return [
            'type'       => 'object',
            'properties' => $this->get_course_settings_schema_properties(),
        ];
    }

    /**
     * Get course settings schema properties.
     *
     * @since 1.14.2
     * @return array<string, array<string, mixed>> REST schema properties.
     */
    private function get_course_settings_schema_properties() {
        return [
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
                    // Explicit clears stay as empty strings; missing/non-array meta still uses backend fallback.
                    'hours'   => [
                        'oneOf' => [
                            [
                                'type'    => 'integer',
                                'minimum' => 0,
                            ],
                            [
                                'type' => 'string',
                                'enum' => [ '' ],
                            ],
                        ],
                    ],
                    'minutes' => [
                        'oneOf' => [
                            [
                                'type'    => 'integer',
                                'minimum' => 0,
                                'maximum' => 59,
                            ],
                            [
                                'type' => 'string',
                                'enum' => [ '' ],
                            ],
                        ],
                    ],
                ],
            ],
            'maximum_students' => [
                'type'    => [ 'integer', 'null' ],
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
                    'source'              => [ 'type' => 'string' ],
                    'source_video_id'     => [ 'type' => 'integer' ],
                    'source_youtube'      => [ 'type' => 'string' ],
                    'source_vimeo'        => [ 'type' => 'string' ],
                    'source_external_url' => [ 'type' => 'string' ],
                    'source_embedded'     => [ 'type' => 'string' ],
                    'source_shortcode'    => [ 'type' => 'string' ],
                    'poster'              => [ 'type' => 'string' ],
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
                'type'    => [ 'number', 'null' ],
                'minimum' => 0,
            ],
            'selling_option' => [
                'type' => 'string',
                'enum' => [ 'one_time', 'subscription', 'both', 'membership', 'all' ],
            ],
            'woocommerce_product_id' => [
                'type' => 'string',
            ],
            'edd_product_id' => [
                'type' => 'string',
            ],
            'subscription_enabled' => [
                'type' => 'boolean',
            ],
            'instructors' => [
                'type'  => 'array',
                'items' => [ 'type' => 'integer' ],
            ],
            'additional_instructors' => [
                'type'  => 'array',
                'items' => [ 'type' => 'integer' ],
            ],
        ];
    }

    /**
     * Set up meta fields for courses.
     *
     * @since 1.14.2
     * @return void
     */
    public function set_up_meta_fields() {
        // Keep the course_settings meta registered for compatibility shadow storage only.
        register_post_meta( $this->token, 'course_settings', [
            'type'              => 'object',
            'description'       => __( 'Course settings for TutorPress Gutenberg integration', 'tutorpress' ),
            'single'            => true,
            'default'           => [],
            'sanitize_callback' => [ $this, 'sanitize_course_settings' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => false,
        ] );

        // Register individual meta fields with auth callbacks for comprehensive security
        $individual_meta_fields = [
            '_tutor_course_level' => [
                'type' => 'string',
                'description' => __( 'Course difficulty level', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_is_public_course' => [
                'type' => 'string',
                'description' => __( 'Whether the course is public', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_enable_qa' => [
                'type' => 'string',
                'description' => __( 'Whether Q&A is enabled', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_course_duration' => [
                'type' => 'object',
                'description' => __( 'Course duration', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_course_price_type' => [
                'type' => 'string',
                'description' => __( 'Course pricing type', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            'tutor_course_price' => [
                'type' => 'number',
                'description' => __( 'Course price', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            'tutor_course_sale_price' => [
                'type' => 'number',
                'description' => __( 'Course sale price', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            'tutor_course_selling_option' => [
                'type' => 'string',
                'description' => __( 'Course selling option', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_course_product_id' => [
                'type' => 'string',
                'description' => __( 'WooCommerce product ID for product linking', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_course_prerequisites_ids' => [
                'type' => 'array',
                'description' => __( 'Course prerequisites', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ],
            '_tutor_course_material_includes' => [
                'type' => 'string',
                'description' => __( 'Course materials', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_video' => [
                'type' => 'object',
                'description' => __( 'Course intro video', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                // Expose full schema so REST API returns meta._video reliably
                'show_in_rest' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'source'              => [ 'type' => 'string' ],
                            'source_video_id'     => [ 'type' => 'integer' ],
                            'source_youtube'      => [ 'type' => 'string' ],
                            'source_vimeo'        => [ 'type' => 'string' ],
                            'source_external_url' => [ 'type' => 'string' ],
                            'source_embedded'     => [ 'type' => 'string' ],
                            'source_shortcode'    => [ 'type' => 'string' ],
                            'poster'              => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
            '_tutor_course_attachments' => [
                'type' => 'array',
                'description' => __( 'Course attachments', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ],
            // Additional Content Metabox fields
            '_tutor_course_benefits' => [
                'type' => 'string',
                'description' => __( 'Course benefits (What Will I Learn)', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_course_target_audience' => [
                'type' => 'string',
                'description' => __( 'Course target audience', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_course_requirements' => [
                'type' => 'string',
                'description' => __( 'Course requirements', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => true,
            ],
            '_tutor_course_instructors' => [
                'type' => 'array',
                'description' => __( 'Course instructors', 'tutorpress' ),
                'single' => true,
                'auth_callback' => [ $this, 'post_meta_auth_callback' ],
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
            ],
        ];

        // Register all individual meta fields
        foreach ( $individual_meta_fields as $meta_key => $config ) {
            register_post_meta( $this->token, $meta_key, $config );
        }
    }

    /**
     * Auth callback for post meta fields.
     *
     * Follows Sensei LMS pattern for permission checking.
     * Ensures users can only edit course settings if they have permission to edit the post.
     *
     * @since 1.14.2
     * @param bool   $allowed  Whether the user can add the meta.
     * @param string $meta_key The meta key.
     * @param int    $post_id  The post ID where the meta key is being edited.
     * @return bool Whether the user can edit the meta key.
     */
    public function post_meta_auth_callback( $allowed, $meta_key, $post_id ) {
        return current_user_can( 'edit_post', $post_id );
    }

    /**
     * Add author support when it's a REST request to allow save teacher via the Rest API.
     *
     * @since 1.14.2
     * @return void
     */
    public function add_author_support() {
        add_post_type_support( $this->token, 'author' );
        // Keep custom-fields support enabled for legacy/meta compatibility.
        add_post_type_support( $this->token, 'custom-fields' );

        // Register REST API fields for course settings
        register_rest_field( $this->token, 'course_settings', [
            'get_callback'    => [ $this, 'get_course_settings' ],
            'update_callback' => [ $this, 'update_course_settings' ],
            'schema'          => array_merge(
                [
                    'description' => __( 'Course settings', 'tutorpress' ),
                ],
                $this->get_course_settings_rest_schema()
            ),
        ] );
    }

    /**
     * Register admin scripts.
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
     * @since 1.14.2
     * @return void
     */
    public function meta_box_setup() {
        // Certificate Metabox (addon-dependent)
        if ( tutorpress_feature_flags()->can_user_access_feature('certificates') ) {
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
     * @since 1.14.2
     * @param int $post_id The post ID.
     * @return void
     */
    public function meta_box_save( $post_id ) {
        if ( $this->sync_context->should_skip_metabox_save( $post_id ) ) {
            return;
        }

        // Verify nonce for additional content metabox
        if ( isset( $_POST['tutorpress_additional_content_nonce'] ) && 
             wp_verify_nonce( $_POST['tutorpress_additional_content_nonce'], 'tutorpress_additional_content_metabox' ) ) {
            
            // Check user permissions
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            // Get data from hidden form fields created by React component
            $what_will_learn = isset( $_POST['tutorpress_what_will_learn'] ) ? 
                sanitize_textarea_field( $_POST['tutorpress_what_will_learn'] ) : '';
            $target_audience = isset( $_POST['tutorpress_target_audience'] ) ? 
                sanitize_textarea_field( $_POST['tutorpress_target_audience'] ) : '';
            $requirements = isset( $_POST['tutorpress_requirements'] ) ? 
                sanitize_textarea_field( $_POST['tutorpress_requirements'] ) : '';
            $content_drip_enabled = isset( $_POST['tutorpress_content_drip_enabled'] ) ? 
                (bool) $_POST['tutorpress_content_drip_enabled'] : false;
            $content_drip_type = isset( $_POST['tutorpress_content_drip_type'] ) ? 
                sanitize_text_field( $_POST['tutorpress_content_drip_type'] ) : 'unlock_by_date';

            // Validate content drip type
            $valid_drip_types = array( 'unlock_by_date', 'specific_days', 'unlock_sequentially', 'after_finishing_prerequisites' );
            if ( ! in_array( $content_drip_type, $valid_drip_types ) ) {
                $content_drip_type = 'unlock_by_date';
            }

            // Save additional content fields to Tutor LMS compatible meta fields
            update_post_meta( $post_id, '_tutor_course_benefits', $what_will_learn );
            update_post_meta( $post_id, '_tutor_course_target_audience', $target_audience );
            update_post_meta( $post_id, '_tutor_course_requirements', $requirements );

            // Save content drip settings (only if content drip addon is enabled)
            if ( tutorpress_feature_flags()->can_user_access_feature('content_drip') ) {
                tutorpress_course_provider()->save_content_drip_settings( $post_id, $content_drip_enabled, $content_drip_type );
            }
        }
    }

    /**
     * Certificate metabox content.
     *
     * Renders the PHP-based UI structure that will be enhanced with React/TypeScript
     * for interactive functionality.
     *
     * @since 1.14.2
     * @param WP_Post $post Current post object.
     * @return void
     */
    public function certificate_metabox_content( $post ) {
        // Check Freemius permissions first
        if ( ! tutorpress_fs_can_use_premium() ) {
            // Display promo content for non-premium users
            echo tutorpress_promo_html();
            return;
        }

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
     * Provides additional course content fields (What Will I Learn, Target Audience, Requirements)
     * and Content Drip settings in the WordPress course editor.
     *
     * @since 1.14.2
     * @param WP_Post $post Current post object.
     * @return void
     */
    public function additional_content_metabox_content( $post ) {
        // Ensure we have a valid course post
        if ( ! $post || $post->post_type !== 'courses' ) {
            return;
        }

        // Check Freemius permissions first
        if ( ! tutorpress_fs_can_use_premium() ) {
            // Display promo content for non-premium users
            echo tutorpress_promo_html();
            return;
        }

        // Add nonce for security
        wp_nonce_field( 'tutorpress_additional_content_metabox', 'tutorpress_additional_content_nonce' );

        // Get current addon status for JavaScript
        $addon_status = array(
            'content_drip' => tutorpress_feature_flags()->can_user_access_feature('content_drip'),
        );

        ?>
        <div 
            id="tutorpress-additional-content-root" 
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-rest-url="<?php echo esc_url( get_rest_url() ); ?>"
            data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
            data-addon-status="<?php echo esc_attr( json_encode( $addon_status ) ); ?>"
        >
            <!-- React component will mount here -->
            <div class="tutorpress-loading">
                <p><?php _e( 'Loading additional content settings...', 'tutorpress' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Build canonical course settings from Tutor meta storage.
     *
     * Shared by both the top-level courses REST field and the compatibility shim.
     *
     * @since 1.14.2
     * @param int $post_id Course post ID.
     * @return array Course settings.
     */
    public static function get_canonical_course_settings( $post_id ) {
        $tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($tutor_settings)) {
            $tutor_settings = [];
        }

        $core_settings = TutorPress_Course_Sync_Service::get_core_details_and_material_settings( $post_id );
        $access_settings = TutorPress_Course_Sync_Service::get_access_enrollment_prerequisite_and_schedule_settings( $post_id, $tutor_settings );
        $intro_video_settings = TutorPress_Course_Sync_Service::get_intro_video_settings( $post_id, $tutor_settings );
		$attachment_settings = TutorPress_Course_Sync_Service::get_attachment_settings( $post_id );
		$pricing_product_settings = TutorPress_Course_Sync_Service::get_pricing_product_settings( $post_id );
		$instructor_settings = TutorPress_Course_Sync_Service::get_instructor_settings( $post_id );
        
        // Build settings structure (preserving Tutor LMS compatibility)
		$settings = array_merge( $core_settings, $access_settings, $intro_video_settings, $attachment_settings, $pricing_product_settings, $instructor_settings );

        // Do not override from stored course_settings here; rely on canonical Tutor meta + computed values
        return $settings;
    }

    /**
     * Get course settings for REST API.
     *
     * @since 1.14.2
     * @param array $post Post data.
     * @return array Course settings.
     */
    public function get_course_settings( $post ) {
        return $this->sync_service->get_course_settings( $post );
    }

    /**
     * Save canonical course settings through the shared fan-out path.
     *
     * @since 1.14.2
     * @param int   $post_id Course post ID.
     * @param array $value   Partial course settings payload.
     * @return array|false Final canonical course settings on success, false on invalid input.
     */
    public static function save_canonical_course_settings( $post_id, $value ) {
        if ( ! is_array( $value ) ) {
            return false;
        }

        $normalized_settings = self::normalize_course_settings_for_save( $value );
        $existing_tutor_settings = get_post_meta( $post_id, '_tutor_course_settings', true );
        if ( ! is_array( $existing_tutor_settings ) ) {
            $existing_tutor_settings = [];
        }

        return TutorPress_Course_Sync_Service::run_with_sync_guard(
            $post_id,
            '_tutorpress_syncing',
            static function () use ( $post_id, $normalized_settings, &$existing_tutor_settings ) {
                TutorPress_Course_Sync_Service::save_core_details_and_materials( $post_id, $normalized_settings, $existing_tutor_settings );
                TutorPress_Course_Sync_Service::save_intro_video( $post_id, $normalized_settings, $existing_tutor_settings );
                TutorPress_Course_Sync_Service::save_attachments( $post_id, $normalized_settings, $existing_tutor_settings );
                TutorPress_Course_Sync_Service::save_pricing_product( $post_id, $normalized_settings, $existing_tutor_settings );

                TutorPress_Course_Sync_Service::save_access_enrollment_prerequisite_and_schedule( $post_id, $normalized_settings, $existing_tutor_settings );
                TutorPress_Course_Sync_Service::save_instructors( $post_id, $normalized_settings, $existing_tutor_settings );

                update_post_meta( $post_id, '_tutor_course_settings', $existing_tutor_settings );
                return TutorPress_Course_Sync_Service::refresh_course_settings_shadow_after_canonical_save( $post_id );
            }
        );
    }

    /**
     * Normalize incoming course settings for the shared saver.
     *
     * @since 1.14.2
     * @param array $settings Raw settings payload.
     * @return array Normalized settings payload.
     */
    private static function normalize_course_settings_for_save( array $settings ) {
        $normalized = TutorPress_Course_Sync_Service::normalize_core_details_and_materials_for_save( $settings );
        $normalized = array_merge( $normalized, TutorPress_Course_Sync_Service::normalize_intro_video_for_save( $settings ) );
		$normalized = array_merge( $normalized, TutorPress_Course_Sync_Service::normalize_attachments_for_save( $settings ) );
		$normalized = TutorPress_Course_Sync_Service::normalize_pricing_product_for_save( $settings, $normalized );

        $normalized = array_merge( $normalized, TutorPress_Course_Sync_Service::normalize_access_enrollment_prerequisite_and_schedule_for_save( $settings ) );
        $normalized = array_merge( $normalized, TutorPress_Course_Sync_Service::normalize_instructors_for_save( $settings ) );

        return $normalized;
    }

    /**
     * Update course settings.
     *
     * Foundation implementation for Phase 3.1.
     * Preserves Tutor LMS compatibility while following Sensei LMS patterns.
     *
     * @since 1.14.2
     * @param array $value Settings to update.
     * @param WP_Post $post Post object.
     * @return bool Whether the update was successful.
     */
    public function update_course_settings($value, $post) {
        return $this->sync_service->update_course_settings( $value, $post );
    }

    /**
     * Sanitize course settings.
     *
     * Foundation implementation for Phase 3.1.
     * Preserves Tutor LMS compatibility while following Sensei LMS patterns.
     *
     * @since 1.14.2
     * @param array $settings Course settings to sanitize.
     * @return array Sanitized settings.
     */
    public function sanitize_course_settings( $settings ) {
        if (!is_array($settings)) {
            return [];
        }
        
        $sanitized = TutorPress_Course_Sync_Service::sanitize_core_details_and_materials( $settings );
        $sanitized = array_merge( $sanitized, TutorPress_Course_Sync_Service::sanitize_intro_video( $settings ) );
		$sanitized = array_merge( $sanitized, TutorPress_Course_Sync_Service::sanitize_attachments( $settings ) );
		$sanitized = TutorPress_Course_Sync_Service::sanitize_pricing_product( $settings, $sanitized );
        
        $sanitized = array_merge( $sanitized, TutorPress_Course_Sync_Service::sanitize_access_enrollment_prerequisite_and_schedule( $settings ) );
        $sanitized = array_merge( $sanitized, TutorPress_Course_Sync_Service::sanitize_instructors( $settings ) );
        
        // All settings panels have been migrated
        // Course Details, Course Media, Pricing Model, Course Access & Enrollment, and Course Instructors panels
        
        return $sanitized;
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
        $this->sync_service->handle_tutor_individual_field_update( $post_id, $meta_key );
    }

    /**
     * Save content drip settings through a deliberate shadow-refresh path.
     *
     * @since 1.14.2
     * @param int    $post_id              Post ID.
     * @param bool   $content_drip_enabled Whether content drip is enabled.
     * @param string $content_drip_type    Content drip type.
     * @return bool True on success.
     */
    public static function save_content_drip_settings( $post_id, $content_drip_enabled, $content_drip_type ) {
        $course_settings = get_post_meta( $post_id, '_tutor_course_settings', true );
        if ( ! is_array( $course_settings ) ) {
            $course_settings = [];
        }

        $course_settings['enable_content_drip'] = (bool) $content_drip_enabled;

        if ( $content_drip_enabled ) {
            $course_settings['content_drip_type'] = $content_drip_type;
        } else {
            unset( $course_settings['content_drip_type'] );
        }

        return (bool) TutorPress_Course_Sync_Service::run_with_sync_guard(
            $post_id,
            '_tutorpress_syncing_to_tutor',
            static function () use ( $post_id, $course_settings ) {
                update_post_meta( $post_id, '_tutor_course_settings', $course_settings );
                TutorPress_Course_Sync_Service::refresh_course_settings_shadow_from_canonical( $post_id );
                return true;
            }
        );
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
        $this->sync_service->handle_tutor_course_settings_update( $post_id, $meta_key );
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
		$this->sync_service->handle_tutor_attachments_meta_update( $post_id, $meta_key, $meta_value );
    }

    /**
     * Handle Tutor LMS attachments meta deletion.
     *
     * Tutor Pro deletes _tutor_attachments when all attachments are removed.
     *
     * @since 1.14.3
     * @param array  $meta_ids   Deleted meta IDs.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Deleted meta value.
     * @return void
     */
    public function handle_tutor_attachments_meta_delete( $meta_ids, $post_id, $meta_key, $meta_value ) {
		$this->sync_service->handle_tutor_attachments_meta_delete( $post_id, $meta_key );
    }

    /**
     * Handle course settings updates.
     *
     * Stored course_settings is compatibility shadow only after Step 8, so direct shadow
     * writes must never fan back into canonical Tutor data.
     *
     * @since 1.14.2
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public function handle_course_settings_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        return;
    }

    /**
     * Handle REST API course updates.
     *
     * This method is called when courses are updated via REST API (Gutenberg saves).
     * When using useEntityProp, the data goes through REST API, so we need to handle intro video sync here.
     *
     * @since 1.14.2
     * @param WP_Post $post Post object.
     * @param WP_REST_Request $request Request object.
     * @param bool $creating Whether this is a new post.
     * @return void
     */
    public function handle_rest_course_update( $post, $request, $creating ) {
        if ( ! $this->sync_context->is_rest_after_insert_course( $post ) ) {
            return;
        }

        // Get course settings from request
        $settings = $request->get_param('course_settings');
        if (!is_array($settings)) {
            return;
        }

        $this->sync_service->save_rest_after_insert_intro_video( $post->ID, $settings );

        // Handle other course media fields
        if ( $this->sync_context->has_rest_after_insert_settings_key( $settings, 'course_material_includes' ) ) {
            update_post_meta($post->ID, '_tutor_course_material_includes', $settings['course_material_includes']);
        }

		$this->sync_service->save_rest_after_insert_attachments( $post->ID, $settings );
    }

    /**
     * Sync course_settings to _tutor_course_settings on post save
     * This uses the simple merge strategy from the working implementations
     */
    public function sync_on_course_save($post_id, $post, $update) {
        $this->sync_service->sync_on_course_save( $post_id );
    }

    /**
     * Get supported additional content fields.
     *
     * Extracted from Additional_Content_Metabox::get_supported_fields().
     *
     * @since 1.14.2
     * @return array Array of field configurations.
     */
    public static function get_supported_fields() {
        return array(
            'what_will_learn' => array(
                'label' => __( 'What Will I Learn', 'tutorpress' ),
                'description' => __( 'List what students will learn from this course', 'tutorpress' ),
                'type' => 'textarea',
                'meta_key' => '_tutor_course_benefits',
            ),
            'target_audience' => array(
                'label' => __( 'Target Audience', 'tutorpress' ),
                'description' => __( 'Who is this course for?', 'tutorpress' ),
                'type' => 'textarea',
                'meta_key' => '_tutor_course_target_audience',
            ),
            'requirements' => array(
                'label' => __( 'Requirements/Instructions', 'tutorpress' ),
                'description' => __( 'What do students need to know or have before taking this course?', 'tutorpress' ),
                'type' => 'textarea',
                'meta_key' => '_tutor_course_requirements',
            ),
        );
    }

    /**
     * Get content drip field configurations.
     *
     * @since 1.14.2
     * @return array Array of content drip field configurations.
     */
    public static function get_content_drip_fields() {
        return array(
            'enable_content_drip' => array(
                'label' => __( 'Enable Content Drip', 'tutorpress' ),
                'description' => __( 'Control when course content becomes available to students', 'tutorpress' ),
                'type' => 'checkbox',
                'meta_key' => '_tutor_course_settings',
                'meta_subkey' => 'enable_content_drip',
            ),
            'content_drip_type' => array(
                'label' => __( 'Content Drip Type', 'tutorpress' ),
                'description' => __( 'Choose how content should be released to students', 'tutorpress' ),
                'type' => 'radio',
                'meta_key' => '_tutor_course_settings',
                'meta_subkey' => 'content_drip_type',
                'options' => array(
                    'unlock_by_date' => __( 'Schedule course contents by date', 'tutorpress' ),
                    'specific_days' => __( 'Content available after X days from enrollment', 'tutorpress' ),
                    'unlock_sequentially' => __( 'Course content available sequentially', 'tutorpress' ),
                    'after_finishing_prerequisites' => __( 'Course content unlocked after finishing prerequisites', 'tutorpress' ),
                ),
                'default' => 'unlock_by_date',
            ),
        );
    }

    /**
     * Add map meta cap to course
     *
     * @param array $args Array of arguments for registering a post type.
     * @param string $post_type Post type key.
     * @return array
     */
    public function add_map_meta_cap_to_course( $args, $post_type ) {
        if ( $post_type === 'courses' ) {
            $args['map_meta_cap'] = true;
        }
        return $args;
    }

    /**
     * Remove Tutor meta caps from the $post_type_meta_caps global.
     *
     * TutorPress enables map_meta_cap = true on courses (add_map_meta_cap_to_course() in this
     * file) and assignments (tutorpress.php). This adds entries like edit_tutor_course => edit_post
     * to the $post_type_meta_caps global. In WP 6.9+, map_meta_cap() uses this global in its
     * default case to recursively call map_meta_cap('edit_post', ...) — which returns do_not_allow
     * when no post ID is provided.
     *
     * Tutor LMS/Pro checks these meta caps without a post ID as general permission gates (e.g.
     * the export handler). Removing the entries prevents the recursive call, so the caps are
     * treated as primitive/direct capabilities. The $post_type->map_meta_cap property remains
     * true, so Gutenberg's edit_post path still does proper author/status resolution.
     *
     * If TutorPress adds map_meta_cap = true to another Tutor post type, extend this list.
     *
     * @see add_map_meta_cap_to_course() in this file (courses)
     * @see tutorpress.php:127 (assignments)
     */
    public function remove_tutor_meta_caps_from_global() {
        global $post_type_meta_caps;

        unset(
            $post_type_meta_caps['edit_tutor_course'],
            $post_type_meta_caps['read_tutor_course'],
            $post_type_meta_caps['delete_tutor_course'],
            $post_type_meta_caps['edit_tutor_assignment'],
            $post_type_meta_caps['read_tutor_assignment'],
            $post_type_meta_caps['delete_tutor_assignment']
        );
    }

    /**
     * Map meta capabilities for courses to allow instructors to edit their own published courses.
     *
     * When map_meta_cap is enabled, WordPress maps edit_post to edit_published_posts for published posts.
     * This filter ensures instructors can edit their own published courses even if they don't have
     * the edit_published_posts capability.
     *
     * @param array  $caps    Required capabilities.
     * @param string $cap     Capability being checked.
     * @param int    $user_id User ID.
     * @param array  $args    Additional arguments (typically contains post ID).
     * @return array Modified capabilities array.
     */
    public function map_course_meta_cap( $caps, $cap, $user_id, $args ) {
        // Only handle course-related capabilities
        if ( ! in_array( $cap, [ 'edit_post', 'delete_post', 'publish_post' ], true ) ) {
            return $caps;
        }

        // Need a post ID to check
        if ( empty( $args[0] ) ) {
            return $caps;
        }

        $post_id = (int) $args[0];
        $post = get_post( $post_id );

        // Only apply to courses post type
        if ( ! $post || $post->post_type !== 'courses' ) {
            return $caps;
        }

        // Check if user is instructor
        if ( ! user_can( $user_id, 'tutor_instructor' ) ) {
            return $caps;
        }

        // Check if user is the author of the course
        if ( (int) $post->post_author !== (int) $user_id ) {
            return $caps;
        }

        // For published courses, allow editing if user is the author and is an instructor
        if ( $post->post_status === 'publish' && $cap === 'edit_post' ) {
            // Remove edit_published_posts requirement and replace with edit_posts
            $caps = array_diff( $caps, [ 'edit_published_posts' ] );
            if ( ! in_array( 'edit_posts', $caps, true ) ) {
                $caps[] = 'edit_posts';
            }
        }

        // For draft/pending courses, ensure edit_posts is present
        if ( in_array( $post->post_status, [ 'draft', 'pending', 'auto-draft' ], true ) && $cap === 'edit_post' ) {
            if ( ! in_array( 'edit_posts', $caps, true ) ) {
                $caps[] = 'edit_posts';
            }
        }

        return $caps;
    }

    /**
     * Conditionally manage "Edit Course" admin-bar link based on dashboard redirects setting
     *
     * When "Redirect Frontend Dashboard Editing to Gutenberg" is enabled:
     * - Adds our "Edit Course" link at priority 71 (early positioning)
     * - Removes Tutor LMS's "Edit with Course Builder" link at priority 101 (after Tutor LMS adds it)
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function conditionally_add_edit_course_link( $wp_admin_bar ) {
        // Check if dashboard redirects setting is enabled
        $options = get_option( 'tutorpress_settings', [] );
        $enable_redirects = isset( $options['enable_dashboard_redirects'] ) && '1' === $options['enable_dashboard_redirects'];

        if ( ! $enable_redirects ) {
            return; // Leave Tutor LMS's link intact if setting is disabled
        }

        // Only apply on frontend course pages
        if ( is_admin() || ! is_singular( 'courses' ) ) {
            return;
        }

        $course_id = get_the_ID();
        if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
            return;
        }

        // Add our own "Edit Course" link (positioned early at priority 71, like Lessons/Assignments)
        $wp_admin_bar->add_menu( array(
            'id'    => 'tutorpress-edit-course',
            'title' => '<span class="ab-icon dashicons dashicons-edit"></span>' . __( 'Edit Course', 'tutorpress' ),
            'href'  => get_edit_post_link( $course_id ),
        ) );
    }

    /**
     * Remove Tutor LMS's "Edit with Course Builder" link when dashboard redirects are enabled (priority 101, after Tutor LMS's 100)
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function remove_tutor_edit_link_if_redirects_enabled( $wp_admin_bar ) {
        // Check if dashboard redirects setting is enabled
        $options = get_option( 'tutorpress_settings', [] );
        $enable_redirects = isset( $options['enable_dashboard_redirects'] ) && '1' === $options['enable_dashboard_redirects'];

        if ( ! $enable_redirects ) {
            return; // Leave Tutor LMS's link intact if setting is disabled
        }

        // Only apply on frontend course pages
        if ( is_admin() || ! is_singular( 'courses' ) ) {
            return;
        }

        $course_id = get_the_ID();
        if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
            return;
        }

        // Remove Tutor LMS's "Edit with Course Builder" link
        $wp_admin_bar->remove_menu( 'edit' );
    }

    /**
     * Output admin bar course icon CSS
     */
    public function output_admin_bar_course_icon_css() {
        ?>
        <style>
            #wpadminbar #wp-admin-bar-edit .ab-item .ab-icon:before {
                top: 2px;
            }
        </style>
        <?php
    }
} 