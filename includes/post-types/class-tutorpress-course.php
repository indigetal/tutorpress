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
     * Constructor.
     *
     * @since 1.14.2
     */
    public function __construct() {
        $this->token = 'courses';

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
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
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
                            'type'    => ['integer', 'null'],
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
                    ],
                ],
            ],
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
            '_tutor_course_selling_option' => [
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
                'show_in_rest' => true,
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
                'show_in_rest' => true,
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
                'show_in_rest' => true,
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
        // Ensure meta appears under the REST 'meta' field for this post type
        add_post_type_support( $this->token, 'custom-fields' );

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
        // Only process courses
        if ( ! $post_id || get_post_type( $post_id ) !== 'courses' ) {
            return;
        }

        // Check if this is an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
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
                // Get existing course settings
                $course_settings = get_post_meta( $post_id, '_tutor_course_settings', true );
                if ( ! is_array( $course_settings ) ) {
                    $course_settings = array();
                }

                // Update content drip settings
                $course_settings['enable_content_drip'] = $content_drip_enabled;
                
                // Only save content drip type if content drip is enabled
                if ( $content_drip_enabled ) {
                    $course_settings['content_drip_type'] = $content_drip_type;
                } else {
                    // When disabled, remove the content drip type or set to default
                    // This ensures "None" behavior - no content drip type is active
                    unset( $course_settings['content_drip_type'] );
                }

                update_post_meta( $post_id, '_tutor_course_settings', $course_settings );
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
     * Get course settings for REST API.
     *
     * @since 1.14.2
     * @param array $post Post data.
     * @return array Course settings.
     */
    /**
     * Get course settings.
     *
     * Foundation implementation for Phase 3.1.
     * Preserves Tutor LMS compatibility while following Sensei LMS patterns.
     *
     * @since 1.14.2
     * @param array $post Post data.
     * @return array Course settings.
     */
    public function get_course_settings( $post ) {
        $post_id = $post['id'];
        
        // Course Details Section: Read from individual Tutor LMS meta fields
        $course_level = get_post_meta($post_id, '_tutor_course_level', true);
        $is_public_course = get_post_meta($post_id, '_tutor_is_public_course', true);
        $enable_qna = get_post_meta($post_id, '_tutor_enable_qa', true);
        $course_duration = get_post_meta($post_id, '_course_duration', true);
        
        // Course Media Section: Read from individual Tutor LMS meta fields
        $course_material_includes = get_post_meta($post_id, '_tutor_course_material_includes', true);
        $intro_video = get_post_meta($post_id, '_video', true);
        
        // Validate and set defaults for Course Details fields
        if (!is_array($course_duration)) {
            $course_duration = ['hours' => 0, 'minutes' => 0];
        }
        
        // Future sections: Read from _tutor_course_settings (when we implement them)
        $tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($tutor_settings)) {
            $tutor_settings = [];
        }
        
        // Build settings structure (preserving Tutor LMS compatibility)
        $settings = [
            // Course Details Section (individual meta fields)
            'course_level' => $course_level ?: 'all_levels',
            'is_public_course' => $is_public_course === 'yes',
            'enable_qna' => $enable_qna !== 'no',
            'course_duration' => $course_duration,
            
            // Access & Enrollment (prefer canonical Tutor meta over _tutor_course_settings to avoid stale data)
            'maximum_students' => (function() use ($post_id, $tutor_settings) {
                // Prioritize _tutor_course_settings if it explicitly contains null or 0 (recent save via REST)
                if (array_key_exists('maximum_students', $tutor_settings)) {
                    $val = $tutor_settings['maximum_students'];
                    if ($val === null || $val === 0 || is_numeric($val)) {
                        return $val === null ? null : (int) $val;
                    }
                }
                $legacy_max = get_post_meta($post_id, '_tutor_maximum_students', true);
                if ($legacy_max === '' || $legacy_max === null) {
                    return null;
                }
                return (int) $legacy_max;
            })(),
            'course_prerequisites' => get_post_meta($post_id, '_tutor_course_prerequisites_ids', true) ?: [],
            'schedule' => $tutor_settings['schedule'] ?? [
                'enabled' => false,
                'start_date' => '',
                'start_time' => '',
                'show_coming_soon' => false,
            ],
            'course_enrollment_period' => $tutor_settings['course_enrollment_period'] ?? 'no',
            'enrollment_starts_at' => $tutor_settings['enrollment_starts_at'] ?? '',
            'enrollment_ends_at' => $tutor_settings['enrollment_ends_at'] ?? '',
            'pause_enrollment' => (function() use ($post_id, $tutor_settings) {
                if (array_key_exists('pause_enrollment', $tutor_settings)) {
                    $val = $tutor_settings['pause_enrollment'];
                    if ($val === 'yes' || $val === 'no') {
                        return $val;
                    }
                }
                $status = get_post_meta($post_id, '_tutor_enrollment_status', true);
                if ($status === 'yes' || $status === 'no') {
                    return $status;
                }
                return 'no';
            })(),
            // Prefer the fresh _video meta over any stale copies in _tutor_course_settings
            'intro_video' => array_merge([
                'source' => '',
                'source_video_id' => 0,
                'source_youtube' => '',
                'source_vimeo' => '',
                'source_external_url' => '',
                'source_embedded' => '',
                'source_shortcode' => '',
                'poster' => '',
            ], $tutor_settings['featured_video'] ?? [], $tutor_settings['intro_video'] ?? [], is_array($intro_video) ? $intro_video : []),
            'attachments' => get_post_meta($post_id, '_tutor_course_attachments', true) ?: [],
            'course_material_includes' => $course_material_includes ?: '',
            
            // Pricing Model Section: Read from individual Tutor LMS meta fields
            'is_free' => get_post_meta($post_id, '_tutor_course_price_type', true) === 'free',
            'pricing_model' => get_post_meta($post_id, '_tutor_course_price_type', true) ?: 'free',
            'price' => (float) get_post_meta($post_id, 'tutor_course_price', true) ?: 0,
			'sale_price' => (function() use ($post_id) {
				$raw = get_post_meta($post_id, 'tutor_course_sale_price', true);
				if ($raw === '' || $raw === null) {
					return null;
				}
				return (float) $raw;
			})(),
            'selling_option' => get_post_meta($post_id, '_tutor_course_selling_option', true) ?: 'one_time',
            'woocommerce_product_id' => TutorPress_Addon_Checker::is_woocommerce_monetization() ? get_post_meta($post_id, '_tutor_course_product_id', true) ?: '' : '',
            'edd_product_id' => TutorPress_Addon_Checker::is_edd_monetization() ? get_post_meta($post_id, '_tutor_course_product_id', true) ?: '' : '',
            'subscription_enabled' => get_post_meta($post_id, '_tutor_course_selling_option', true) === 'subscription',
            
            // Course Instructors Section: Read from individual Tutor LMS meta fields
            'instructors' => get_post_meta($post_id, '_tutor_course_instructors', true) ?: [],
            'additional_instructors' => get_post_meta($post_id, '_tutor_course_instructors', true) ?: [], // Alias for compatibility
        ];

        // Do not override from stored course_settings here; rely on canonical Tutor meta + computed values
        return $settings;
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
        $post_id = $post->ID;
        
        if (!is_array($value)) {
            return false;
        }
        
        // Set a flag to prevent infinite loops during sync
        update_post_meta($post_id, '_tutorpress_syncing', true);
        
        $results = [];
        
        // Update the course_settings meta field (normalize maximum_students empty/0 → null at source of truth)
            if (array_key_exists('maximum_students', $value)) {
                $v = $value['maximum_students'];
                // Explicitly normalize: '' => null; '0' => 0; 0 => 0; positive numbers kept; anything else -> null
                if ($v === '' || $v === null) {
                    $value['maximum_students'] = null;
                } elseif ($v === '0' || $v === 0) {
                    $value['maximum_students'] = 0;
                } else {
                    $value['maximum_students'] = max(0, (int) $v);
                }
            }
        $results[] = update_post_meta($post_id, 'course_settings', $value);
        
        // Course Details Section: Update individual Tutor LMS meta fields
        if (isset($value['course_level'])) {
            $results[] = update_post_meta($post_id, '_tutor_course_level', $value['course_level']);
        }
        
        if (isset($value['is_public_course'])) {
            $public_value = $value['is_public_course'] ? 'yes' : 'no';
            $results[] = update_post_meta($post_id, '_tutor_is_public_course', $public_value);
        }
        
        if (isset($value['enable_qna'])) {
            $qna_value = $value['enable_qna'] ? 'yes' : 'no';
            $results[] = update_post_meta($post_id, '_tutor_enable_qa', $qna_value);
        }
        
        if (isset($value['course_duration'])) {
            $results[] = update_post_meta($post_id, '_course_duration', $value['course_duration']);
        }
        
        // Course Media Section: Update individual Tutor LMS meta fields
        if (isset($value['course_material_includes'])) {
            $results[] = update_post_meta($post_id, '_tutor_course_material_includes', $value['course_material_includes']);
        }
        
        // Handle Video Intro field (stored in _video meta field like Tutor LMS)
        if (isset($value['intro_video'])) {
            $intro_video = $value['intro_video'];
            if (is_array($intro_video)) {
                $results[] = update_post_meta($post_id, '_video', $intro_video);
            }
        }
        
        if (isset($value['attachments'])) {
            $attachment_ids = is_array($value['attachments']) ? array_map('absint', $value['attachments']) : [];
            $results[] = update_post_meta($post_id, '_tutor_course_attachments', $attachment_ids);
            $results[] = update_post_meta($post_id, '_tutor_attachments', $attachment_ids);
        }
        
        // Handle pricing fields separately (Tutor LMS stores these as individual meta fields)
        if (isset($value['pricing_model'])) {
            $pricing_type = $value['pricing_model'] === 'free' ? 'free' : 'paid';
            $results[] = update_post_meta($post_id, '_tutor_course_price_type', $pricing_type);
        }
        
        if (isset($value['price'])) {
            $results[] = update_post_meta($post_id, 'tutor_course_price', (float) $value['price']);
        }
        
		if (array_key_exists('sale_price', $value)) {
			if ($value['sale_price'] === null || $value['sale_price'] === '') {
				// Remove or set empty to avoid $0 being considered on front-end
				$results[] = update_post_meta($post_id, 'tutor_course_sale_price', '');
			} else {
				$results[] = update_post_meta($post_id, 'tutor_course_sale_price', (float) $value['sale_price']);
			}
		}
        
        if (isset($value['selling_option'])) {
            $selling_option = $value['selling_option'];
            $results[] = update_post_meta($post_id, '_tutor_course_selling_option', $selling_option);
        }
        
        // Handle product IDs - Save the active product ID to _tutor_course_product_id based on monetization engine
        if (isset($value['woocommerce_product_id']) || isset($value['edd_product_id'])) {
            $active_product_id = '';
            
            // Determine which product ID should be active based on monetization engine
            if (isset($value['woocommerce_product_id']) && TutorPress_Addon_Checker::is_woocommerce_monetization()) {
                $active_product_id = $value['woocommerce_product_id'];
            } elseif (isset($value['edd_product_id']) && TutorPress_Addon_Checker::is_edd_monetization()) {
                $active_product_id = $value['edd_product_id'];
            }
            
            // Save the active product ID to the shared meta field
            $results[] = update_post_meta($post_id, '_tutor_course_product_id', $active_product_id);
        }
        
        // --- Ensure Tutor settings mirror for Access & Enrollment even if updated_post_meta paths don't fire ---
        $existing_tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($existing_tutor_settings)) {
            $existing_tutor_settings = [];
        }

        // maximum_students → _tutor_maximum_students and _tutor_course_settings
        if (array_key_exists('maximum_students', $value)) {
            $max_students_in = $value['maximum_students'];
            if ($max_students_in === '' || $max_students_in === null) {
                $legacy_max = '';
            } elseif ($max_students_in === 0 || $max_students_in === '0') {
                $legacy_max = 0;
            } else {
                $legacy_max = max(0, intval($max_students_in));
            }
            update_post_meta($post_id, '_tutor_maximum_students', $legacy_max);
            $existing_tutor_settings['maximum_students'] = ($legacy_max === '') ? null : intval($legacy_max);
            $existing_tutor_settings['maximum_students_allowed'] = $existing_tutor_settings['maximum_students'];
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[TP] update_course_settings mirror maximum_students=' . var_export($existing_tutor_settings['maximum_students'], true) . ' legacy_max=' . var_export($legacy_max, true));
            }
        }

        // pause_enrollment → _tutor_enrollment_status and _tutor_course_settings
            if (array_key_exists('pause_enrollment', $value)) {
            $pause_val_in = $value['pause_enrollment'];
            $pause_str = is_bool($pause_val_in) ? ($pause_val_in ? 'yes' : 'no') : (in_array($pause_val_in, ['yes', 'no'], true) ? $pause_val_in : 'no');
            update_post_meta($post_id, '_tutor_enrollment_status', $pause_str);
            $existing_tutor_settings['pause_enrollment'] = $pause_str;
            $existing_tutor_settings['enrollment_status'] = $pause_str;
        }

        // course_prerequisites (ids array) → _tutor_course_prerequisites_ids and _tutor_course_settings
        if (array_key_exists('course_prerequisites', $value)) {
            $ids = is_array($value['course_prerequisites']) ? array_map('absint', $value['course_prerequisites']) : [];
            update_post_meta($post_id, '_tutor_course_prerequisites_ids', $ids);
            $existing_tutor_settings['course_prerequisites'] = $ids;
        }

        // Persist merged Tutor settings
        update_post_meta($post_id, '_tutor_course_settings', $existing_tutor_settings);
        update_post_meta($post_id, '_tutorpress_course_settings_last_sync', time());

        // Course Instructors Section: Handle individual Tutor LMS meta fields
        if (isset($value['instructors'])) {
            $instructor_ids = is_array($value['instructors']) ? array_map('absint', $value['instructors']) : [];
            $results[] = update_post_meta($post_id, '_tutor_course_instructors', $instructor_ids);
            // Sync to Tutor LMS compatibility (user meta)
            $this->sync_instructors_to_tutor_lms($post_id, $instructor_ids);
        }
        
        if (isset($value['additional_instructors'])) {
            $additional_instructor_ids = is_array($value['additional_instructors']) ? array_map('absint', $value['additional_instructors']) : [];
            $results[] = update_post_meta($post_id, '_tutor_course_instructors', $additional_instructor_ids);
            // Sync to Tutor LMS compatibility (user meta)
            $this->sync_instructors_to_tutor_lms($post_id, $additional_instructor_ids);
        }
        
        // Clear the sync flag
        delete_post_meta($post_id, '_tutorpress_syncing');
        
        return !in_array(false, $results, true);
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
        
        $sanitized = [];
        
        // Course Details Section: Sanitize individual fields
        if (isset($settings['course_level'])) {
            $allowed_levels = ['beginner', 'intermediate', 'expert', 'all_levels'];
            $sanitized['course_level'] = in_array($settings['course_level'], $allowed_levels) ? $settings['course_level'] : 'all_levels';
        }
        
        if (isset($settings['is_public_course'])) {
            $sanitized['is_public_course'] = (bool) $settings['is_public_course'];
        }
        
        if (isset($settings['enable_qna'])) {
            $sanitized['enable_qna'] = (bool) $settings['enable_qna'];
        }
        
        if (isset($settings['course_duration'])) {
            $duration = $settings['course_duration'];
            if (is_array($duration)) {
                $sanitized['course_duration'] = [
                    'hours' => absint($duration['hours'] ?? 0),
                    'minutes' => absint($duration['minutes'] ?? 0),
                ];
                // Ensure minutes don't exceed 59
                if ($sanitized['course_duration']['minutes'] > 59) {
                    $sanitized['course_duration']['minutes'] = 59;
                }
            } else {
                $sanitized['course_duration'] = ['hours' => 0, 'minutes' => 0];
            }
        }
        
        // Course Media Section: Sanitize individual fields
        if (isset($settings['course_material_includes'])) {
            $sanitized['course_material_includes'] = sanitize_textarea_field($settings['course_material_includes']);
        }
        
        if (isset($settings['intro_video'])) {
            $intro_video = $settings['intro_video'];
            if (is_array($intro_video)) {
                $sanitized['intro_video'] = [
                    'source' => sanitize_text_field($intro_video['source'] ?? ''),
                    'source_video_id' => absint($intro_video['source_video_id'] ?? 0),
                    'source_youtube' => sanitize_text_field($intro_video['source_youtube'] ?? ''),
                    'source_vimeo' => sanitize_text_field($intro_video['source_vimeo'] ?? ''),
                    'source_external_url' => sanitize_text_field($intro_video['source_external_url'] ?? ''),
                    'source_embedded' => sanitize_text_field($intro_video['source_embedded'] ?? ''),
                    'source_shortcode' => sanitize_text_field($intro_video['source_shortcode'] ?? ''),
                    'poster' => sanitize_text_field($intro_video['poster'] ?? ''),
                ];

                // Per-source normalization to prevent stale/cross-source data
                $allowed_sources = array('', 'html5', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode');
                if (!in_array($sanitized['intro_video']['source'], $allowed_sources, true)) {
                    $sanitized['intro_video']['source'] = '';
                }

                $src = $sanitized['intro_video']['source'];

                // Helper to clear all non-applicable fields
                $clear_non_applicable = function(array &$iv, array $keep_keys) {
                    $keys = array('source_video_id','source_youtube','source_vimeo','source_external_url','source_embedded','source_shortcode');
                    foreach ($keys as $key) {
                        if (!in_array($key, $keep_keys, true)) {
                            if ($key === 'source_video_id') {
                                $iv[$key] = 0;
                            } else {
                                $iv[$key] = '';
                            }
                        }
                    }
                };

                switch ($src) {
                    case 'html5':
                        // Require a valid attachment ID; otherwise treat as no video
                        if ($sanitized['intro_video']['source_video_id'] <= 0) {
                            $sanitized['intro_video']['source'] = '';
                            $clear_non_applicable($sanitized['intro_video'], array());
                        } else {
                            // Keep only video_id; clear URL-based fields
                            $clear_non_applicable($sanitized['intro_video'], array('source_video_id'));
                        }
                        break;
                    case 'youtube':
                        if ($sanitized['intro_video']['source_youtube'] === '') {
                            $sanitized['intro_video']['source'] = '';
                            $clear_non_applicable($sanitized['intro_video'], array());
                        } else {
                            $clear_non_applicable($sanitized['intro_video'], array('source_youtube'));
                        }
                        break;
                    case 'vimeo':
                        if ($sanitized['intro_video']['source_vimeo'] === '') {
                            $sanitized['intro_video']['source'] = '';
                            $clear_non_applicable($sanitized['intro_video'], array());
                        } else {
                            $clear_non_applicable($sanitized['intro_video'], array('source_vimeo'));
                        }
                        break;
                    case 'external_url':
                        if ($sanitized['intro_video']['source_external_url'] === '') {
                            $sanitized['intro_video']['source'] = '';
                            $clear_non_applicable($sanitized['intro_video'], array());
                        } else {
                            $clear_non_applicable($sanitized['intro_video'], array('source_external_url'));
                        }
                        break;
                    case 'embedded':
                        if ($sanitized['intro_video']['source_embedded'] === '') {
                            $sanitized['intro_video']['source'] = '';
                            $clear_non_applicable($sanitized['intro_video'], array());
                        } else {
                            $clear_non_applicable($sanitized['intro_video'], array('source_embedded'));
                        }
                        break;
                    case 'shortcode':
                        if ($sanitized['intro_video']['source_shortcode'] === '') {
                            $sanitized['intro_video']['source'] = '';
                            $clear_non_applicable($sanitized['intro_video'], array());
                        } else {
                            $clear_non_applicable($sanitized['intro_video'], array('source_shortcode'));
                        }
                        break;
                    default:
                        // Empty or unsupported: fully clear non-applicable fields
                        $clear_non_applicable($sanitized['intro_video'], array());
                        break;
                }
            } else {
                $sanitized['intro_video'] = [
                    'source' => '',
                    'source_video_id' => 0,
                    'source_youtube' => '',
                    'source_vimeo' => '',
                    'source_external_url' => '',
                    'source_embedded' => '',
                    'source_shortcode' => '',
                    'poster' => '',
                ];
            }
        }
        
        if (isset($settings['attachments'])) {
            $attachments = $settings['attachments'];
            if (is_array($attachments)) {
                $sanitized['attachments'] = array_map('absint', $attachments);
            } else {
                $sanitized['attachments'] = [];
            }
        }
        
        // Pricing Model Section: Sanitize individual fields
        if (isset($settings['pricing_model'])) {
            $allowed_models = ['free', 'paid'];
            $sanitized['pricing_model'] = in_array($settings['pricing_model'], $allowed_models) ? $settings['pricing_model'] : 'free';
        }
        
        // Business Rule: Public courses cannot be paid - enforce at backend level
        // Check both new settings and existing settings to handle partial updates
        $is_public = isset($sanitized['is_public_course']) ? $sanitized['is_public_course'] : (isset($settings['is_public_course']) ? $settings['is_public_course'] : false);
        $pricing_model = isset($sanitized['pricing_model']) ? $sanitized['pricing_model'] : (isset($settings['pricing_model']) ? $settings['pricing_model'] : 'free');
        
        if ($is_public && $pricing_model === 'paid') {
            // Force to free pricing if public course is enabled
            $sanitized['pricing_model'] = 'free';
            $sanitized['is_free'] = true;
            $sanitized['price'] = 0;
            $sanitized['sale_price'] = 0;
        }
        
        if (isset($settings['price'])) {
            $sanitized['price'] = round(max(0, (float) $settings['price']), 2);
        }
        
		if (array_key_exists('sale_price', $settings)) {
			// Allow null to represent no sale; otherwise coerce to non-negative number
			if ($settings['sale_price'] === null || $settings['sale_price'] === '') {
				$sanitized['sale_price'] = null;
			} else {
				$sanitized['sale_price'] = round(max(0, (float) $settings['sale_price']), 2);
			}
		}
        
        if (isset($settings['selling_option'])) {
            $allowed_options = ['one_time', 'subscription', 'both', 'membership', 'all'];
            $sanitized['selling_option'] = in_array($settings['selling_option'], $allowed_options) ? $settings['selling_option'] : 'one_time';
        }
        
        // Handle product IDs
        if (isset($settings['woocommerce_product_id'])) {
            $sanitized['woocommerce_product_id'] = sanitize_text_field($settings['woocommerce_product_id']);
        }
        
        if (isset($settings['edd_product_id'])) {
            $sanitized['edd_product_id'] = sanitize_text_field($settings['edd_product_id']);
        }
        
        // Course Access & Enrollment Section: Sanitize mixed storage fields
        if (isset($settings['maximum_students'])) {
            // Treat '' or null as unlimited (null), but preserve 0 as 0
            $max_students = $settings['maximum_students'];
            if ($max_students === '' || $max_students === null) {
                $sanitized['maximum_students'] = null;
            } else {
                $sanitized['maximum_students'] = max(0, intval($max_students));
            }
            // Also set the legacy helper field to mirror value (null or non-negative int)
            $sanitized['maximum_students_allowed'] = $sanitized['maximum_students'];
        }
        
        if (isset($settings['pause_enrollment'])) {
            // Convert to 'yes'/'no' string
            $pause_enrollment = $settings['pause_enrollment'];
            if (is_bool($pause_enrollment)) {
                $sanitized['pause_enrollment'] = $pause_enrollment ? 'yes' : 'no';
            } else {
                $sanitized['pause_enrollment'] = in_array($pause_enrollment, ['yes', 'no']) ? $pause_enrollment : 'no';
            }
            // Also set the legacy field
            $sanitized['enrollment_status'] = $sanitized['pause_enrollment'];
        }
        
        if (isset($settings['course_enrollment_period'])) {
            $sanitized['course_enrollment_period'] = in_array($settings['course_enrollment_period'], ['yes', 'no']) ? $settings['course_enrollment_period'] : 'no';
        }
        
        if (isset($settings['enrollment_starts_at'])) {
            $sanitized['enrollment_starts_at'] = sanitize_text_field($settings['enrollment_starts_at']);
        }
        
        if (isset($settings['enrollment_ends_at'])) {
            $sanitized['enrollment_ends_at'] = sanitize_text_field($settings['enrollment_ends_at']);
        }
        
        // Course Prerequisites
        if (isset($settings['course_prerequisites']) && is_array($settings['course_prerequisites'])) {
            $sanitized['course_prerequisites'] = array_map('absint', $settings['course_prerequisites']);
        }
        
        // Schedule
        if (isset($settings['schedule']) && is_array($settings['schedule'])) {
            $sanitized['schedule'] = [
                'enabled' => isset($settings['schedule']['enabled']) ? (bool) $settings['schedule']['enabled'] : false,
                'start_date' => isset($settings['schedule']['start_date']) ? sanitize_text_field($settings['schedule']['start_date']) : '',
                'start_time' => isset($settings['schedule']['start_time']) ? sanitize_text_field($settings['schedule']['start_time']) : '',
                'show_coming_soon' => isset($settings['schedule']['show_coming_soon']) ? (bool) $settings['schedule']['show_coming_soon'] : false,
            ];
        }
        
        // Course Instructors Section: Sanitize individual fields
        if (isset($settings['instructors']) && is_array($settings['instructors'])) {
            $sanitized['instructors'] = array_map('absint', $settings['instructors']);
        }
        
        if (isset($settings['additional_instructors']) && is_array($settings['additional_instructors'])) {
            $sanitized['additional_instructors'] = array_map('absint', $settings['additional_instructors']);
        }

        // Enforce dependent field clearing when enrollment period is disabled
        // If course_enrollment_period is 'no', both dates must be empty to prevent stale data
        if (
            isset($sanitized['course_enrollment_period'])
            && $sanitized['course_enrollment_period'] === 'no'
        ) {
            $sanitized['enrollment_starts_at'] = '';
            $sanitized['enrollment_ends_at'] = '';
        }
        
        // All settings panels have been migrated
        // Course Details, Course Media, Pricing Model, Course Access & Enrollment, and Course Instructors panels
        
        return $sanitized;
    }

    /**
     * Sync instructors to Tutor LMS compatibility format
     *
     * @param int $course_id The course ID
     * @param array $instructor_ids Array of instructor user IDs
     * @return void
     */
    private function sync_instructors_to_tutor_lms($course_id, $instructor_ids) {
        // Remove old instructor associations
        global $wpdb;
        $wpdb->delete(
            $wpdb->usermeta,
            [
                'meta_key' => '_tutor_instructor_course_id',
                'meta_value' => $course_id,
            ]
        );

        // Add new instructor associations
        foreach ($instructor_ids as $instructor_id) {
            add_user_meta($instructor_id, '_tutor_instructor_course_id', $course_id);
        }
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
        // Only handle individual Tutor LMS fields for courses
        $tutor_fields = [
            '_tutor_course_level', '_tutor_is_public_course', '_tutor_enable_qa', '_course_duration',
            '_tutor_course_prerequisites_ids', '_tutor_maximum_students', '_tutor_enrollment_status',
            '_tutor_course_enrollment_period', '_tutor_enrollment_starts_at', '_tutor_enrollment_ends_at',
            '_tutor_course_material_includes', '_tutor_course_price_type', 'tutor_course_price', 'tutor_course_sale_price',
            '_tutor_course_selling_option'
        ];
        
        if (!in_array($meta_key, $tutor_fields) || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Skip if we're currently syncing to Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Get current course_settings
        $current_settings = get_post_meta($post_id, 'course_settings', true);
        if (!is_array($current_settings)) {
            $current_settings = [];
        }

        // Update the specific field in our settings
        switch ($meta_key) {
            case '_tutor_course_level':
                $current_settings['course_level'] = $meta_value ?: 'all_levels';
                break;
            case '_tutor_is_public_course':
                $current_settings['is_public_course'] = $meta_value === 'yes';
                break;
            case '_tutor_enable_qa':
                $current_settings['enable_qna'] = $meta_value !== 'no';
                break;
            case '_course_duration':
                if (is_array($meta_value)) {
                    $current_settings['course_duration'] = $meta_value;
                } else {
                    $current_settings['course_duration'] = ['hours' => 0, 'minutes' => 0];
                }
                break;
            case '_tutor_course_prerequisites_ids':
                $current_settings['course_prerequisites'] = is_array($meta_value) ? $meta_value : [];
                break;
            case '_tutor_maximum_students':
                $current_settings['maximum_students'] = $meta_value;
                $current_settings['maximum_students_allowed'] = $meta_value;
                break;
            case '_tutor_enrollment_status':
                $current_settings['pause_enrollment'] = $meta_value;
                $current_settings['enrollment_status'] = $meta_value;
                break;
            case '_tutor_course_enrollment_period':
                $current_settings['course_enrollment_period'] = $meta_value;
                break;
            case '_tutor_enrollment_starts_at':
                $current_settings['enrollment_starts_at'] = $meta_value;
                break;
            case '_tutor_enrollment_ends_at':
                $current_settings['enrollment_ends_at'] = $meta_value;
                break;
            case '_tutor_course_material_includes':
                $current_settings['course_material_includes'] = $meta_value;
                break;
            case '_tutor_course_price_type':
                $current_settings['pricing_model'] = $meta_value ?: 'free';
                $current_settings['is_free'] = $meta_value === 'free';
                break;
            case 'tutor_course_price':
                $current_settings['price'] = (float) $meta_value ?: 0;
                break;
            case 'tutor_course_sale_price':
                $current_settings['sale_price'] = (float) $meta_value ?: 0;
                break;
            case '_tutor_course_selling_option':
                $current_settings['selling_option'] = $meta_value;
                break;
        }

        // Update our course_settings field
        update_post_meta($post_id, 'course_settings', $current_settings);
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
        // Only handle _tutor_course_settings updates for courses
        if ($meta_key !== '_tutor_course_settings' || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Skip if we're currently syncing to Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Avoid rapid updates
        $last_sync = get_post_meta($post_id, '_tutorpress_tutor_settings_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 5) {
            return;
        }

        // Set sync flag to prevent infinite loops
        update_post_meta($post_id, '_tutorpress_syncing_from_tutor', true);
        update_post_meta($post_id, '_tutorpress_tutor_settings_last_sync', time());

        // Update our course_settings field to match
        update_post_meta($post_id, 'course_settings', $meta_value);

        // Clear sync flag
        delete_post_meta($post_id, '_tutorpress_syncing_from_tutor');
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
        // Only handle _tutor_attachments updates for courses
        if ($meta_key !== '_tutor_attachments' || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Avoid infinite loops
        $our_last_update = get_post_meta($post_id, '_tutorpress_attachments_last_sync', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return;
        }

        // Sync course attachments
        update_post_meta($post_id, '_tutorpress_attachments_last_sync', time());
        $attachment_ids = is_array($meta_value) ? array_map('absint', $meta_value) : [];
        update_post_meta($post_id, '_tutor_course_attachments', $attachment_ids);
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
        // Only handle course_settings updates for courses
        if ($meta_key !== 'course_settings' || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Skip if we're currently syncing from Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_from_tutor', true)) {
            return;
        }

        // Avoid rapid updates
        $last_sync = get_post_meta($post_id, '_tutorpress_course_settings_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 5) {
            return;
        }

        // Get existing Tutor LMS settings
        $existing_tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($existing_tutor_settings)) {
            $existing_tutor_settings = [];
        }

        // Sync to individual Tutor LMS meta fields and _tutor_course_settings
        if (is_array($meta_value)) {
            // Set sync flag to prevent infinite loops
            update_post_meta($post_id, '_tutorpress_syncing_to_tutor', true);
            update_post_meta($post_id, '_tutorpress_course_settings_last_sync', time());
            
            // Update individual Tutor LMS meta fields for core settings
            if (isset($meta_value['course_level'])) {
                update_post_meta($post_id, '_tutor_course_level', $meta_value['course_level']);
            }
            
            if (isset($meta_value['is_public_course'])) {
                $public_value = $meta_value['is_public_course'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_is_public_course', $public_value);
            }
            
            if (isset($meta_value['enable_qna'])) {
                $qna_value = $meta_value['enable_qna'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_enable_qa', $qna_value);
            }
            
            // Handle course_duration separately (Tutor LMS stores this in _course_duration meta field)
            if (isset($meta_value['course_duration'])) {
                update_post_meta($post_id, '_course_duration', $meta_value['course_duration']);
            }
            
            // Course Media Section: Handle individual Tutor LMS meta fields
            if (isset($meta_value['course_material_includes'])) {
                update_post_meta($post_id, '_tutor_course_material_includes', $meta_value['course_material_includes']);
            }
            
            if (isset($meta_value['intro_video'])) {
                update_post_meta($post_id, '_video', $meta_value['intro_video']);
            }
            
            if (isset($meta_value['attachments'])) {
                $attachment_ids = is_array($meta_value['attachments']) ? array_map('absint', $meta_value['attachments']) : [];
                update_post_meta($post_id, '_tutor_course_attachments', $attachment_ids);
                update_post_meta($post_id, '_tutor_attachments', $attachment_ids);
            }
            
            // Handle pricing fields separately (Tutor LMS stores these as individual meta fields)
            if (isset($meta_value['pricing_model'])) {
                $pricing_type = $meta_value['pricing_model'] === 'free' ? 'free' : 'paid';
                update_post_meta($post_id, '_tutor_course_price_type', $pricing_type);
            }
            
            if (isset($meta_value['price'])) {
                update_post_meta($post_id, 'tutor_course_price', (float) $meta_value['price']);
            }
            
			if (array_key_exists('sale_price', $meta_value)) {
				if ($meta_value['sale_price'] === null || $meta_value['sale_price'] === '') {
					update_post_meta($post_id, 'tutor_course_sale_price', '');
				} else {
					update_post_meta($post_id, 'tutor_course_sale_price', (float) $meta_value['sale_price']);
				}
			}
            
            if (isset($meta_value['selling_option'])) {
                $selling_option = $meta_value['selling_option'];
                update_post_meta($post_id, '_tutor_course_selling_option', $selling_option);
            }
            
            // Handle product IDs - Save the active product ID to _tutor_course_product_id based on monetization engine
            if (isset($meta_value['woocommerce_product_id']) || isset($meta_value['edd_product_id'])) {
                $active_product_id = '';
                
                // Determine which product ID should be active based on monetization engine
                if (isset($meta_value['woocommerce_product_id']) && TutorPress_Addon_Checker::is_woocommerce_monetization()) {
                    $active_product_id = $meta_value['woocommerce_product_id'];
                } elseif (isset($meta_value['edd_product_id']) && TutorPress_Addon_Checker::is_edd_monetization()) {
                    $active_product_id = $meta_value['edd_product_id'];
                }
                
                // Save the active product ID to the shared meta field
                update_post_meta($post_id, '_tutor_course_product_id', $active_product_id);
            }
            
            // Course Instructors Section: Handle individual Tutor LMS meta fields
            if (isset($meta_value['instructors'])) {
                $instructor_ids = is_array($meta_value['instructors']) ? array_map('absint', $meta_value['instructors']) : [];
                update_post_meta($post_id, '_tutor_course_instructors', $instructor_ids);
                // Sync to Tutor LMS compatibility (user meta)
                $this->sync_instructors_to_tutor_lms($post_id, $instructor_ids);
            }
            
            if (isset($meta_value['additional_instructors'])) {
                $additional_instructor_ids = is_array($meta_value['additional_instructors']) ? array_map('absint', $meta_value['additional_instructors']) : [];
                update_post_meta($post_id, '_tutor_course_instructors', $additional_instructor_ids);
                // Sync to Tutor LMS compatibility (user meta)
                $this->sync_instructors_to_tutor_lms($post_id, $additional_instructor_ids);
            }

            // Access & Enrollment: maximum_students (nullable) and pause_enrollment (yes/no)
            if (array_key_exists('maximum_students', $meta_value)) {
                $max_students_in = $meta_value['maximum_students'];
                // Legacy Tutor stores empty string for unlimited; preserve 0 explicitly
                if ($max_students_in === '' || $max_students_in === null) {
                    $legacy_max = '';
                } elseif ($max_students_in === 0 || $max_students_in === '0') {
                    $legacy_max = 0;
                } else {
                    $legacy_max = max(0, intval($max_students_in));
                }
                update_post_meta($post_id, '_tutor_maximum_students', $legacy_max);
                $existing_tutor_settings['maximum_students'] = ($legacy_max === '') ? null : intval($legacy_max);
                $existing_tutor_settings['maximum_students_allowed'] = $existing_tutor_settings['maximum_students'];
            }

            if (array_key_exists('pause_enrollment', $meta_value)) {
                $pause_val_in = $meta_value['pause_enrollment'];
                $pause_str = is_bool($pause_val_in)
                    ? ($pause_val_in ? 'yes' : 'no')
                    : (in_array($pause_val_in, ['yes', 'no'], true) ? $pause_val_in : 'no');
                update_post_meta($post_id, '_tutor_enrollment_status', $pause_str);
                $existing_tutor_settings['pause_enrollment'] = $pause_str;
                $existing_tutor_settings['enrollment_status'] = $pause_str;
            }

            // Persist merged Tutor settings to keep get_course_settings() authoritative
            $merged_settings = array_merge($existing_tutor_settings, is_array($meta_value) ? $meta_value : []);
            update_post_meta($post_id, '_tutor_course_settings', $merged_settings);
            // Mark last REST sync time to prevent immediate override on save_post hook
            update_post_meta($post_id, '_tutorpress_course_settings_last_sync', time());
            
            // Clear sync flag
            delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        }
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
        if ($post->post_type !== 'courses') {
            return;
        }

        // Get course settings from request
        $settings = $request->get_param('course_settings');
        if (!is_array($settings)) {
            return;
        }

        // Handle intro video sync
        if (isset($settings['intro_video'])) {
            $intro_video = $settings['intro_video'];
            if (is_array($intro_video)) {
                update_post_meta($post->ID, '_video', $intro_video);
            }
        }

        // Handle other course media fields
        if (isset($settings['course_material_includes'])) {
            update_post_meta($post->ID, '_tutor_course_material_includes', $settings['course_material_includes']);
        }

        if (isset($settings['attachments'])) {
            $attachment_ids = is_array($settings['attachments']) ? array_map('absint', $settings['attachments']) : [];
            update_post_meta($post->ID, '_tutor_course_attachments', $attachment_ids);
            update_post_meta($post->ID, '_tutor_attachments', $attachment_ids);
        }
    }

    /**
     * Sync course_settings to _tutor_course_settings on post save
     * This uses the simple merge strategy from the working implementations
     */
    public function sync_on_course_save($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // If we just synced course_settings via REST (handle_course_settings_update), skip this pass
        $last_rest_sync = get_post_meta($post_id, '_tutorpress_course_settings_last_sync', true);
        if ($last_rest_sync && (time() - (int) $last_rest_sync) < 5) {
            return;
        }

        // Ensure both meta fields are in sync
        $course_settings = get_post_meta($post_id, 'course_settings', true);
        $tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);

        if (is_array($course_settings) && !empty($course_settings)) {
            if (!is_array($tutor_settings)) {
                $tutor_settings = [];
            }
            
            $merged_settings = array_merge($tutor_settings, $course_settings);
            update_post_meta($post_id, '_tutor_course_settings', $merged_settings);
        }
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
} 