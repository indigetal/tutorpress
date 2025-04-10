<?php
/**
 * Class Curriculum_Metabox
 *
 * Registers the premium Curriculum Metabox for TutorPress.
 * This metabox is displayed in the Gutenberg editor for Course and Lesson posts.
 * The display logic is handled in PHP while interactive functionality will be
 * provided via React/TypeScript.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Curriculum_Metabox {

    /**
     * Initialize the metabox registration.
     *
     * @return void
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
    }

    /**
     * Register the Curriculum Metabox for Courses and Lessons.
     *
     * @return void
     */
    public static function register_metabox() {
        add_meta_box(
            'tutorpress_curriculum_metabox',  // Unique ID
            __( 'Course Curriculum', 'tutorpress' ),  // Title
            array( __CLASS__, 'display_metabox' ),    // Callback
            array( 'courses', 'lesson' ),     // Post types (matching Tutor LMS post types)
            'normal',                        // Context
            'high'                           // Priority
        );
    }

    /**
     * Display the metabox content.
     * 
     * Renders the PHP-based UI structure that will be enhanced with React/TypeScript
     * for interactive functionality. The display logic is premium-only (controlled via
     * Freemius's @fs_premium_only directory exclusion).
     *
     * @param WP_Post $post Current post object.
     * @return void
     */
    public static function display_metabox( $post ) {
        ?>
        <div 
            id="tutorpress-curriculum-builder" 
            class="tutorpress-curriculum-metabox"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-post-type="<?php echo esc_attr( $post->post_type ); ?>"
        >
            <div class="tutorpress-curriculum-container">
                <div class="tutorpress-curriculum-header">
                    <h2><?php _e( 'Course Curriculum', 'tutorpress' ); ?></h2>
                    <p class="description">
                        <?php _e( 'Organize and manage your course curriculum.', 'tutorpress' ); ?>
                    </p>
                </div>
                <div class="tutorpress-curriculum-content">
                    <div id="tutorpress-curriculum-root"></div>
                </div>
            </div>
        </div>
        <?php
    }
}

Curriculum_Metabox::init();
