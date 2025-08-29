<?php
/**
 * TutorPress Bundle Post Type Class
 *
 * Handles bundle meta registration, REST exposure, and admin metabox wiring.
 *
 * @package TutorPress
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_Bundle {
    /**
     * Post type token for bundles
     *
     * @var string
     */
    protected $token = 'course-bundle';

    /**
     * Constructor
     */
    public function __construct() {
        // Register meta fields and REST fields on init
        add_action( 'init', [ $this, 'set_up_meta_fields' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );

        // Register metaboxes for legacy compatibility
        add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
        
        // Handle admin asset enqueuing
        add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_editor_assets' ] );
        
        // Handle metabox saves (traditional form posts)
        add_action( 'save_post', [ $this, 'meta_box_save' ] );

        // Handle REST API updates (Gutenberg uses REST API, not traditional meta updates)
        add_action( 'rest_after_insert_course-bundle', [ $this, 'handle_rest_bundle_update' ], 10, 3 );
        
        // Also handle traditional form saves for compatibility
        add_action( 'save_post_course-bundle', [ $this, 'sync_on_bundle_save' ], 999, 3 );
    }

    /**
     * Register post meta fields for bundles
     *
     * @return void
     */
    public function set_up_meta_fields() {
        if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( $this->token ) ) {
            return;
        }

        // Price type
        register_post_meta( $this->token, '_tutor_course_price_type', [
            'type'              => 'string',
            'single'            => true,
            'default'           => 'free',
            'sanitize_callback' => [ __CLASS__, 'sanitize_price_type' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Regular price
        register_post_meta( $this->token, 'tutor_course_price', [
            'type'              => 'number',
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => [ __CLASS__, 'sanitize_price' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Sale price
        register_post_meta( $this->token, 'tutor_course_sale_price', [
            'type'              => 'number',
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => [ __CLASS__, 'sanitize_price' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Selling option
        register_post_meta( $this->token, 'tutor_course_selling_option', [
            'type'              => 'string',
            'single'            => true,
            'default'           => 'one_time',
            'sanitize_callback' => [ __CLASS__, 'sanitize_selling_option' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Product id
        register_post_meta( $this->token, '_tutor_course_product_id', [
            'type'              => 'integer',
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Ribbon type
        register_post_meta( $this->token, 'tutor_bundle_ribbon_type', [
            'type'              => 'string',
            'single'            => true,
            'default'           => 'none',
            'sanitize_callback' => [ __CLASS__, 'sanitize_ribbon_type' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Course IDs (managed via dedicated REST endpoints, exclude from Gutenberg meta save)
        register_post_meta( $this->token, 'bundle-course-ids', [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [ __CLASS__, 'sanitize_course_ids' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => false, // Exclude from Gutenberg auto-save to prevent conflicts
        ] );

        // Benefits
        register_post_meta( $this->token, '_tutor_course_benefits', [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post',
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );

        // Bundle Ribbon Type
        register_post_meta( $this->token, 'tutor_bundle_ribbon_type', [
            'type'              => 'string',
            'single'            => true,
            'default'           => 'none',
            'sanitize_callback' => [ $this, 'sanitize_ribbon_type' ],
            'auth_callback'     => [ $this, 'post_meta_auth_callback' ],
            'show_in_rest'      => true,
        ] );
    }

    /**
     * Register REST fields (lightweight helpers)
     */
    public function register_rest_fields() {
        // Optional: read-only bundle_settings composite for convenience
        register_rest_field( $this->token, 'bundle_settings', [
            'get_callback'    => [ $this, 'get_bundle_settings' ],
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'Bundle settings', 'tutorpress' ),
                'type'        => 'object',
            ],
        ] );

        // Optional: read-only bundle_instructors composite for convenience
        register_rest_field( $this->token, 'bundle_instructors', [
            'get_callback'    => [ $this, 'get_bundle_instructors_for_rest' ],
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'Bundle instructors (computed from course relationships)', 'tutorpress' ),
                'type'        => 'object',
                'readonly'    => true,
                'properties'  => [
                    'instructors'       => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'           => [ 'type' => 'integer' ],
                                'display_name' => [ 'type' => 'string' ],
                                'user_email'   => [ 'type' => 'string' ],
                                'user_login'   => [ 'type' => 'string' ],
                                'avatar_url'   => [ 'type' => 'string' ],
                                'role'         => [ 'type' => 'string' ],
                                'designation'  => [ 'type' => 'string' ],
                            ],
                        ],
                    ],
                    'total_instructors' => [ 'type' => 'integer' ],
                    'total_courses'     => [ 'type' => 'integer' ],
                ],
            ],
        ] );
    }

    /**
     * Conditionally enqueue editor assets when on bundle edit screen.
     *
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public function maybe_enqueue_editor_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== $this->token ) {
            return;
        }

        // Assets handled by TutorPress_Assets class
    }

    /**
     * Get bundle settings composite (read-only convenience)
     */
    public function get_bundle_settings( $object ) {
        $post_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
        if ( ! $post_id ) {
            return [];
        }

        return [
            'price_type'     => get_post_meta( $post_id, '_tutor_course_price_type', true ),
            'price'          => (float) get_post_meta( $post_id, 'tutor_course_price', true ),
            'sale_price'     => (float) get_post_meta( $post_id, 'tutor_course_sale_price', true ),
            'selling_option' => get_post_meta( $post_id, 'tutor_course_selling_option', true ),
            'product_id'     => (int) get_post_meta( $post_id, '_tutor_course_product_id', true ),
            'ribbon_type'    => get_post_meta( $post_id, 'tutor_bundle_ribbon_type', true ),
            'course_ids'     => get_post_meta( $post_id, 'bundle-course-ids', true ),
            'benefits'       => get_post_meta( $post_id, '_tutor_course_benefits', true ),
        ];
    }

    /**
     * Get bundle instructors for REST API (computed field)
     * Delegates to existing REST controller logic for consistency
     */
    public function get_bundle_instructors_for_rest( $object ) {
        $post_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
        if ( ! $post_id ) {
            return [
                'instructors'       => [],
                'total_instructors' => 0,
                'total_courses'     => 0,
            ];
        }

        // Delegate to existing REST controller logic to avoid code duplication
        if ( ! class_exists( 'TutorPress_REST_Course_Bundles_Controller' ) ) {
            return [
                'instructors'       => [],
                'total_instructors' => 0,
                'total_courses'     => 0,
            ];
        }

        $controller = new TutorPress_REST_Course_Bundles_Controller();
        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', $post_id );
        
        $response = $controller->get_bundle_instructors( $request );
        
        if ( is_wp_error( $response ) ) {
            return [
                'instructors'       => [],
                'total_instructors' => 0,
                'total_courses'     => 0,
            ];
        }
        
        $data = $response->get_data();
        return [
            'instructors'       => $data['data'] ?? [],
            'total_instructors' => $data['total_instructors'] ?? 0,
            'total_courses'     => $data['total_courses'] ?? 0,
        ];
    }

    /**
     * Register metaboxes (delegates to existing metabox classes)
     */
    public function register_metaboxes() {
        // Courses metabox (render a React root container)
        add_meta_box(
            'tutorpress_bundle_courses_metabox',
            __( 'Courses', 'tutorpress' ),
            [ $this, 'display_bundle_courses_metabox' ],
            $this->token,
            'normal',
            'high'
        );

        // Benefits metabox
        add_meta_box(
            'tutorpress_bundle_benefits_metabox',
            __( 'Bundle Benefits', 'tutorpress' ),
            [ $this, 'display_bundle_benefits_metabox' ],
            $this->token,
            'normal',
            'default'
        );


    }

    /**
     * Display the bundle courses metabox (renders a React root container)
     * Enhanced with curriculum metabox patterns for better frontend integration
     */
    public function display_bundle_courses_metabox( $post ) {
        // UI-only Freemius gating: show promo when premium not available
        if ( ! tutorpress_fs_can_use_premium() ) {
            echo tutorpress_promo_html();
            return;
        }

        wp_nonce_field( 'tutorpress_bundle_courses_nonce', 'tutorpress_bundle_courses_nonce' );

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
            return;
        }
        ?>
        <div
            id="tutorpress-bundle-courses-root"
            data-bundle-id="<?php echo esc_attr( $post->ID ); ?>"
            data-post-type="<?php echo esc_attr( $post->post_type ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'tutorpress_bundle_courses_nonce' ) ); ?>"
            data-rest-url="<?php echo esc_url( get_rest_url() ); ?>"
            data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
            class="tutorpress-metabox-container"
        >
            <!-- React component will be rendered here -->
        </div>
        <?php
    }

    /**
     * Display the bundle benefits metabox (renders a React root container)
     */
    public function display_bundle_benefits_metabox( $post ) {
        // UI-only Freemius gating: show promo when premium not available
        if ( ! tutorpress_fs_can_use_premium() ) {
            echo tutorpress_promo_html();
            return;
        }

        wp_nonce_field( 'tutorpress_bundle_benefits_nonce', 'tutorpress_bundle_benefits_nonce' );

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
            return;
        }
        ?>
        <div
            id="tutorpress-bundle-benefits-root"
            class="tutorpress-metabox-container"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-rest-url="<?php echo esc_url( get_rest_url() ); ?>"
            data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
        >
            <!-- Hidden fallback for legacy meta-box-loader/form POSTs -->
            <input type="hidden" name="tutorpress_bundle_benefits" value="<?php echo esc_attr( get_post_meta( $post->ID, '_tutor_course_benefits', true ) ); ?>" />
            <!-- React component will be rendered here -->
        </div>
        <?php
    }

    /**
     * Auth callback for post meta (simple permission check)
     */
    public function post_meta_auth_callback( $allowed, $meta_key, $post_id ) {
        return current_user_can( 'edit_post', $post_id );
    }

    /**
     * Handle REST API updates for bundles (Gutenberg saves)
     *
     * @param WP_Post $post Post object.
     * @param WP_REST_Request $request Request object.
     * @param bool $creating Whether this is a new post.
     * @return void
     */
    public function handle_rest_bundle_update( $post, $request, $creating ) {
        if ( $post->post_type !== $this->token ) {
            return;
        }

        // Extract meta from REST request (Gutenberg saves provide meta in the request payload)
        $meta = $request->get_param( 'meta' );
        if ( ! is_array( $meta ) ) {
            // No meta provided, nothing to sync
            return;
        }

        // Protect against rapid loops by setting a sync flag
        update_post_meta( $post->ID, '_tutorpress_syncing_to_tutor', true );

        // Handle benefits specifically
        if ( array_key_exists( '_tutor_course_benefits', $meta ) ) {
            $benefits = sanitize_textarea_field( $meta['_tutor_course_benefits'] );
            update_post_meta( $post->ID, '_tutor_course_benefits', $benefits );
            // Mark last rest sync time for this field so save_post handlers can skip
            update_post_meta( $post->ID, '_tutorpress_bundle_benefits_last_sync', time() );
            // Persist last REST value so we can re-apply if a legacy path overwrites with empty
            update_post_meta( $post->ID, '_tutorpress_bundle_benefits_last_value', $benefits );
        }

        // If there are other canonical bundle meta fields included in meta, write them as well
        $other_keys = array( '_tutor_course_price_type', 'tutor_course_price', 'tutor_course_sale_price', 'tutor_course_selling_option', '_tutor_course_product_id', 'tutor_bundle_ribbon_type' );
        foreach ( $other_keys as $k ) {
            if ( array_key_exists( $k, $meta ) ) {
                // Basic sanitization: strings -> sanitize_text_field, numbers -> floatval/absint where appropriate
                $val = $meta[ $k ];
                if ( in_array( $k, array( 'tutor_course_price', 'tutor_course_sale_price' ), true ) ) {
                    update_post_meta( $post->ID, $k, (float) $val );
                } elseif ( $k === '_tutor_course_product_id' ) {
                    update_post_meta( $post->ID, $k, absint( $val ) );
                } else {
                    update_post_meta( $post->ID, $k, sanitize_text_field( (string) $val ) );
                }
            }
        }

        // Clear syncing flag
        delete_post_meta( $post->ID, '_tutorpress_syncing_to_tutor' );
    }

    /**
     * Handle traditional form saves for bundles
     *
     * @param int $post_id Post ID.
     * @param WP_Post $post Post object.
     * @param bool $update Whether this is an update.
     * @return void
     */
    public function sync_on_bundle_save( $post_id, $post, $update ) {
        if ( ! $post || $post->post_type !== $this->token ) {
            return;
        }

        // Skip autosave and revisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Skip if we recently synced via REST to avoid overwrite races with meta-box-loader
        $last_rest_sync = get_post_meta( $post_id, '_tutorpress_bundle_benefits_last_sync', true );
        if ( $last_rest_sync && ( time() - (int) $last_rest_sync ) < 5 ) {
            return;
        }

        // No additional sync needed for bundles currently
        // Meta fields are already saved by WordPress core via register_post_meta
        // This hook is reserved for future bidirectional sync with Tutor LMS if needed
    }

    /**
     * Meta box save handler (matches Course's meta_box_save pattern).
     *
     * @param int $post_id The post ID.
     * @return void
     */
    public function meta_box_save( $post_id ) {
        // Only process course bundles
        if ( ! $post_id || get_post_type( $post_id ) !== $this->token ) {
            return;
        }

        // Check if this is an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Verify nonce for bundle benefits metabox
        if ( isset( $_POST['tutorpress_bundle_benefits_nonce'] ) && 
             wp_verify_nonce( $_POST['tutorpress_bundle_benefits_nonce'], 'tutorpress_bundle_benefits_nonce' ) ) {
            
            // Check user permissions
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            // Get data from hidden form field created by React component
            $benefits = isset( $_POST['tutorpress_bundle_benefits'] ) ? 
                sanitize_textarea_field( $_POST['tutorpress_bundle_benefits'] ) : '';

            // Save bundle benefits to Tutor LMS compatible meta field
            update_post_meta( $post_id, '_tutor_course_benefits', $benefits );
        }
    }



    /* ----------------- Sanitizers (copied from legacy settings class) ----------------- */
    public static function sanitize_course_ids( $value ) {
        return sanitize_text_field( $value );
    }

    public static function sanitize_ribbon_type( $value ) {
        $allowed_types = array( 'in_percentage', 'in_amount', 'none' );
        $value = sanitize_text_field( $value );
        return in_array( $value, $allowed_types, true ) ? $value : 'none';
    }

    public static function sanitize_price_type( $value ) {
        $allowed_types = array( 'free', 'paid' );
        $value = sanitize_text_field( $value );
        return in_array( $value, $allowed_types, true ) ? $value : 'free';
    }

    public static function sanitize_price( $value ) {
        $price = floatval( $value );
        return $price >= 0 ? $price : 0;
    }

    public static function sanitize_selling_option( $value ) {
        $allowed_options = array( 'one_time', 'subscription', 'both', 'membership', 'all' );
        $value = sanitize_text_field( $value );
        return in_array( $value, $allowed_options, true ) ? $value : 'one_time';
    }
}

// Instantiate so hooks are registered
new TutorPress_Bundle();


