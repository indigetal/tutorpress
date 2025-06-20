<?php
/**
 * Class Certificate_Metabox
 *
 * Registers the premium Certificate Metabox for TutorPress.
 * This metabox is displayed in the Gutenberg editor for Course posts only when
 * the Certificate addon is enabled. The display logic is handled in PHP while 
 * interactive functionality will be provided via React/TypeScript.
 *
 * @package TutorPress
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Certificate_Metabox class.
 *
 * @since 0.1.0
 */
class Certificate_Metabox {

    /**
     * The nonce action for the metabox.
     *
     * @since 0.1.0
     * @var string
     */
    const NONCE_ACTION = 'tutorpress_certificate_nonce';

    /**
     * Initialize the metabox registration.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        // Only initialize if Certificate addon is enabled
        if ( ! TutorPress_Addon_Checker::is_certificate_enabled() ) {
            return;
        }

        add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_editor_assets' ) );
    }

    /**
     * Register the Certificate Metabox for Courses only.
     *
     * @since 0.1.0
     * @return void
     */
    public static function register_metabox() {
        add_meta_box(
            'tutorpress_certificate_metabox',  // Unique ID
            __( 'Certificate', 'tutorpress' ), // Title
            array( __CLASS__, 'display_metabox' ),    // Callback
            array( 'courses' ),               // Post types (certificates only apply to courses)
            'normal',                        // Context
            'high'                           // Priority
        );
    }

    /**
     * Conditionally enqueue editor assets when on course edit screen.
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public static function maybe_enqueue_editor_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'courses' ), true ) ) {
            return;
        }

        // Enqueue is handled in TutorPress_Scripts class
    }

    /**
     * Display the metabox content.
     * 
     * Renders the PHP-based UI structure that will be enhanced with React/TypeScript
     * for interactive functionality. The display logic is premium-only (controlled via
     * Freemius's @fs_premium_only directory exclusion).
     *
     * @since 0.1.0
     * @param WP_Post $post Current post object.
     * @return void
     */
    public static function display_metabox( $post ) {
        wp_nonce_field( self::NONCE_ACTION, 'tutorpress_certificate_nonce' );

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
            data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
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
}

// Initialize the class
Certificate_Metabox::init(); 