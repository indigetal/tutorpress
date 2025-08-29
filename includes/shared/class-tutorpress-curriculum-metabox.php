<?php
/**
 * Class TutorPress_Curriculum_Metabox
 *
 * Shared curriculum metabox display logic for TutorPress.
 * Provides shared curriculum metabox functionality for courses, lessons, and assignments.
 * The display logic is handled in PHP while interactive functionality is provided via React/TypeScript.
 *
 * @package TutorPress
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * TutorPress_Curriculum_Metabox class.
 *
 * @since 0.1.0
 */
class TutorPress_Curriculum_Metabox {

    /**
     * The nonce action for the metabox.
     *
     * @since 0.1.0
     * @var string
     */
    const NONCE_ACTION = 'tutorpress_curriculum_nonce';

    /**
     * Initialize the shared curriculum metabox functionality.
     *
     * @since 0.1.0
     * @return void
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_editor_assets' ) );
    }

    /**
     * Register the Curriculum Metabox for Courses, Lessons, and Assignments.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_metabox() {
        add_meta_box(
            'tutorpress_curriculum_metabox',  // Unique ID
            __( 'Course Curriculum', 'tutorpress' ),  // Title
            array( __CLASS__, 'render_curriculum_metabox' ),    // Callback (renamed for clarity)
            array( 'courses', 'lesson', 'tutor_assignments' ),     // Post types (matching Tutor LMS post types)
            'normal',                        // Context
            'high'                           // Priority
        );
    }

    /**
     * Conditionally enqueue editor assets when on course, lesson, or assignment edit screen.
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public function maybe_enqueue_editor_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'courses', 'lesson', 'tutor_assignments' ), true ) ) {
            return;
        }

        // Enqueue is handled in TutorPress_Scripts class
    }

    /**
     * Render the curriculum metabox content.
     * 
     * Shared method for rendering curriculum metabox across all supported post types.
     * Renders the PHP-based UI structure that will be enhanced with React/TypeScript
     * for interactive functionality.
     *
     * @since 0.1.0
     * @param WP_Post $post Current post object.
     * @return void
     */
    public static function render_curriculum_metabox( $post ) {
        // Check Freemius permissions first (UI-only gating)
        if ( ! tutorpress_fs_can_use_premium() ) {
            echo tutorpress_promo_html();
            return;
        }

        wp_nonce_field( self::NONCE_ACTION, 'tutorpress_curriculum_nonce' );

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
            return;
        }
        ?>
        <div 
            id="tutorpress-curriculum-builder" 
            class="tutorpress-curriculum-metabox"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-post-type="<?php echo esc_attr( $post->post_type ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
            data-rest-url="<?php echo esc_url( get_rest_url() ); ?>"
            data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
        >
            <div class="tutorpress-curriculum-container">
                <div class="tutorpress-curriculum-content">
                    <div id="tutorpress-curriculum-root">
                        <?php esc_html_e( 'Loading curriculum builder...', 'tutorpress' ); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display the metabox content (legacy method for backward compatibility).
     * 
     * @deprecated Use render_curriculum_metabox() instead.
     * @since 0.1.0
     * @param WP_Post $post Current post object.
     * @return void
     */
    public static function display_metabox( $post ) {
        self::render_curriculum_metabox( $post );
    }
}
