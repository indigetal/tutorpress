<?php
/**
 * Class Bundle_Benefits_Metabox
 *
 * Registers the Bundle Benefits Metabox for TutorPress Course Bundles.
 * This metabox is displayed in the Gutenberg editor for Course Bundle posts.
 * The display logic is handled in PHP while interactive functionality will be
 * provided via React/TypeScript.
 *
 * @package TutorPress
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Bundle_Benefits_Metabox class.
 *
 * @since 1.0.0
 */
class Bundle_Benefits_Metabox {

    /**
     * The nonce action for the metabox.
     *
     * @since 1.0.0
     * @var string
     */
    const NONCE_ACTION = 'tutorpress_bundle_benefits_nonce';

    /**
     * Initialize the metabox registration.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_editor_assets' ) );
        add_action( 'save_post', array( __CLASS__, 'save_bundle_benefits' ), 10, 2 );
    }

    /**
     * Register the Benefits Metabox for Course Bundles.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_metabox() {
        add_meta_box(
            'tutorpress_bundle_benefits_metabox',  // Unique ID
            __( 'Bundle Benefits', 'tutorpress' ),  // Title
            array( __CLASS__, 'display_metabox' ),    // Callback
            'course-bundle',     // Post type (Tutor LMS Course Bundle post type)
            'normal',                        // Context
            'default'                         // Priority
        );
    }

    /**
     * Conditionally enqueue editor assets when on course bundle edit screen.
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public static function maybe_enqueue_editor_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'course-bundle' ) {
            return;
        }

        // Enqueue is handled in TutorPress_Scripts class
    }

    /**
     * Display the metabox content.
     * 
     * Renders the PHP-based UI structure that will be enhanced with React/TypeScript
     * for interactive functionality.
     *
     * @since 1.0.0
     * @param WP_Post $post Current post object.
     * @return void
     */
    public static function display_metabox( $post ) {
        wp_nonce_field( self::NONCE_ACTION, 'tutorpress_bundle_benefits_nonce' );

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
            return;
        }
        ?>
        <div 
            id="tutorpress-bundle-benefits-root" 
            class="tutorpress-metabox-container"
            data-post-id="<?php echo esc_attr($post->ID); ?>"
            data-rest-url="<?php echo esc_url(get_rest_url()); ?>"
            data-rest-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
        >
            <!-- React component will be rendered here -->
        </div>
        <?php
    }

    /**
     * Save bundle benefits when post is saved
     *
     * @since 1.0.0
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @return void
     */
    public static function save_bundle_benefits($post_id, $post) {
        // Only process course bundles
        if (!$post || $post->post_type !== 'course-bundle') {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['tutorpress_bundle_benefits_nonce']) || 
            !wp_verify_nonce($_POST['tutorpress_bundle_benefits_nonce'], self::NONCE_ACTION)) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Get data from hidden form field created by React component
        $benefits = isset($_POST['tutorpress_bundle_benefits']) ? 
            sanitize_textarea_field($_POST['tutorpress_bundle_benefits']) : '';

        // Save to Tutor LMS compatible meta field
        update_post_meta($post_id, '_tutor_course_benefits', $benefits);
    }
}