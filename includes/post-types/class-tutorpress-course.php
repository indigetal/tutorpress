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
            if ( class_exists( 'TutorPress_Addon_Checker' ) && TutorPress_Addon_Checker::is_content_drip_enabled() ) {
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

        // Add nonce for security
        wp_nonce_field( 'tutorpress_additional_content_metabox', 'tutorpress_additional_content_nonce' );

        // Get current addon status for JavaScript
        $addon_status = array(
            'content_drip' => TutorPress_Addon_Checker::is_content_drip_enabled(),
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
            
            // Future sections (will be implemented in Steps 3.2-3.6)
            'maximum_students' => $tutor_settings['maximum_students'] ?? 0,
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
            'pause_enrollment' => $tutor_settings['pause_enrollment'] ?? 'no',
            'intro_video' => array_merge([
                'source' => '',
                'source_video_id' => 0,
                'source_youtube' => '',
                'source_vimeo' => '',
                'source_external_url' => '',
                'source_embedded' => '',
                'source_shortcode' => '',
                'poster' => '',
            ], is_array($intro_video) ? $intro_video : [], $tutor_settings['featured_video'] ?? [], $tutor_settings['intro_video'] ?? []),
            'attachments' => get_post_meta($post_id, '_tutor_course_attachments', true) ?: [],
            'course_material_includes' => $course_material_includes ?: '',
            
            // Pricing Model Section: Read from individual Tutor LMS meta fields
            'is_free' => get_post_meta($post_id, '_tutor_course_price_type', true) === 'free',
            'pricing_model' => get_post_meta($post_id, '_tutor_course_price_type', true) ?: 'free',
            'price' => (float) get_post_meta($post_id, 'tutor_course_price', true) ?: 0,
            'sale_price' => (float) get_post_meta($post_id, 'tutor_course_sale_price', true) ?: 0,
            'selling_option' => get_post_meta($post_id, '_tutor_course_selling_option', true) ?: 'one_time',
            'woocommerce_product_id' => get_post_meta($post_id, '_tutor_course_product_id', true) ?: '',
            'edd_product_id' => get_post_meta($post_id, '_tutor_course_product_id', true) ?: '',
            'subscription_enabled' => get_post_meta($post_id, '_tutor_course_selling_option', true) === 'subscription',
        ];
        
        // Update the course_settings meta field with the complete settings structure
        // This ensures Gutenberg can access all settings through the meta field
        update_post_meta($post_id, 'course_settings', $settings);
        
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
    public function update_course_settings( $value, $post ) {
        $post_id = $post->ID;
        
        if (!is_array($value)) {
            return false;
        }
        
        // Set sync flag to prevent infinite loops
        update_post_meta($post_id, '_tutorpress_syncing_to_tutor', true);
        
        $results = [];
        
        // Course Details Section: Update individual Tutor LMS meta fields
        if (isset($value['course_level'])) {
            $results['course_level'] = update_post_meta($post_id, '_tutor_course_level', $value['course_level']);
        }
        
        if (isset($value['is_public_course'])) {
            $results['is_public_course'] = update_post_meta($post_id, '_tutor_is_public_course', $value['is_public_course'] ? 'yes' : 'no');
        }
        
        if (isset($value['enable_qna'])) {
            $results['enable_qna'] = update_post_meta($post_id, '_tutor_enable_qa', $value['enable_qna'] ? 'yes' : 'no');
        }
        
        if (isset($value['course_duration'])) {
            $results['course_duration'] = update_post_meta($post_id, '_course_duration', $value['course_duration']);
        }
        
        // Future sections will be implemented in Steps 3.2-3.6
        // For now, we'll handle the basic Course Details panel
        
        // Clear sync flag
        delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        
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
        
        // Future sections will be implemented in Steps 3.2-3.6
        // For now, we'll handle the basic Course Details panel
        
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