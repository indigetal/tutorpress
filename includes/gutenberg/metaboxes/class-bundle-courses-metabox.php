<?php
/**
 * Class Bundle_Courses_Metabox
 *
 * Registers the Bundle Courses Metabox for TutorPress Course Bundles.
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
 * Bundle_Courses_Metabox class.
 *
 * @since 1.0.0
 */
class Bundle_Courses_Metabox {

    /**
     * The nonce action for the metabox.
     *
     * @since 1.0.0
     * @var string
     */
    const NONCE_ACTION = 'tutorpress_bundle_courses_nonce';

    /**
     * Initialize the metabox registration.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_editor_assets' ) );
    }

    /**
     * Register the Course Selection Metabox for Course Bundles.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_metabox() {
        add_meta_box(
            'tutorpress_bundle_courses_metabox',  // Unique ID
            __( 'Courses', 'tutorpress' ),  // Title
            array( __CLASS__, 'display_metabox' ),    // Callback
            'course-bundle',     // Post type (Tutor LMS Course Bundle post type)
            'normal',                        // Context
            'high'                           // Priority
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
        wp_nonce_field( self::NONCE_ACTION, 'tutorpress_bundle_courses_nonce' );

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
            return;
        }
        ?>
        <div 
            id="tutorpress-bundle-courses-root" 
            data-bundle-id="<?php echo esc_attr( $post->ID ); ?>"
            class="tutorpress-metabox-container"
        >
            <!-- React component will be rendered here -->
        </div>
        <?php
    }
} 