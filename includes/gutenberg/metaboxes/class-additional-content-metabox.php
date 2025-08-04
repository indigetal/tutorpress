<?php
/**
 * Additional Content Metabox
 *
 * @description Provides additional course content fields (What Will I Learn, Target Audience, Requirements)
 *              and Content Drip settings in the WordPress course editor.
 *              Follows TutorPress metabox architecture patterns for consistency.
 *
 * @package TutorPress
 * @subpackage Gutenberg\Metaboxes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Additional Content Metabox class
 */
class Additional_Content_Metabox {

    /**
     * Initialize the metabox registration.
     *
     * @since 0.1.0
     * @return void
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_metabox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('save_post', array($this, 'save_additional_content'), 10, 2);
    }

    /**
     * Register the metabox for course post type
     *
     * @since 0.1.0
     * @return void
     */
    public function register_metabox() {
        // Always register for courses (no addon dependency for core fields)
        add_meta_box(
            'tutorpress_additional_content_metabox',  // Metabox ID
            __('Additional Course Content', 'tutorpress'), // Title
            array(__CLASS__, 'display_metabox'),      // Callback
            array('courses'),                         // Post types (courses only)
            'normal',                                // Context
            'default'                                // Priority - below curriculum and certificate
        );
    }

    /**
     * Display the metabox content
     *
     * @param WP_Post $post The current post object
     * @return void
     */
    public static function display_metabox($post) {
        // Ensure we have a valid course post
        if (!$post || $post->post_type !== 'courses') {
            return;
        }

        // Add nonce for security
        wp_nonce_field('tutorpress_additional_content_metabox', 'tutorpress_additional_content_nonce');

        // Get current addon status for JavaScript
        $addon_status = array(
            'content_drip' => TutorPress_Addon_Checker::is_content_drip_enabled(),
        );

        ?>
        <div 
            id="tutorpress-additional-content-root" 
            data-post-id="<?php echo esc_attr($post->ID); ?>"
            data-rest-url="<?php echo esc_url(get_rest_url()); ?>"
            data-rest-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
            data-addon-status="<?php echo esc_attr(json_encode($addon_status)); ?>"
        >
            <!-- React component will mount here -->
            <div class="tutorpress-loading">
                <p><?php _e('Loading additional content settings...', 'tutorpress'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue metabox assets
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page hook suffix
     * @return void
     */
    public function enqueue_assets($hook_suffix) {
        // Only load on course edit pages
        if (!in_array($hook_suffix, array('post.php', 'post-new.php'))) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'courses') {
            return;
        }

        // Assets will be loaded by main TutorPress bundle
        // This method exists for potential future asset-specific loading
    }

    /**
     * Get supported additional content fields
     *
     * @return array Array of field configurations
     */
    public static function get_supported_fields() {
        return array(
            'what_will_learn' => array(
                'label' => __('What Will I Learn', 'tutorpress'),
                'description' => __('List what students will learn from this course', 'tutorpress'),
                'type' => 'textarea',
                'meta_key' => '_tutor_course_benefits',
            ),
            'target_audience' => array(
                'label' => __('Target Audience', 'tutorpress'),
                'description' => __('Who is this course for?', 'tutorpress'),
                'type' => 'textarea',
                'meta_key' => '_tutor_course_target_audience',
            ),
            'requirements' => array(
                'label' => __('Requirements/Instructions', 'tutorpress'),
                'description' => __('What do students need to know or have before taking this course?', 'tutorpress'),
                'type' => 'textarea',
                'meta_key' => '_tutor_course_requirements',
            ),
        );
    }

    /**
     * Get content drip field configurations
     *
     * @return array Array of content drip field configurations
     */
    public static function get_content_drip_fields() {
        return array(
            'enable_content_drip' => array(
                'label' => __('Enable Content Drip', 'tutorpress'),
                'description' => __('Control when course content becomes available to students', 'tutorpress'),
                'type' => 'checkbox',
                'meta_key' => '_tutor_course_settings',
                'meta_subkey' => 'enable_content_drip',
            ),
            'content_drip_type' => array(
                'label' => __('Content Drip Type', 'tutorpress'),
                'description' => __('Choose how content should be released to students', 'tutorpress'),
                'type' => 'radio',
                'meta_key' => '_tutor_course_settings',
                'meta_subkey' => 'content_drip_type',
                'options' => array(
                    'unlock_by_date' => __('Schedule course contents by date', 'tutorpress'),
                    'specific_days' => __('Content available after X days from enrollment', 'tutorpress'),
                    'unlock_sequentially' => __('Course content available sequentially', 'tutorpress'),
                    'after_finishing_prerequisites' => __('Course content unlocked after finishing prerequisites', 'tutorpress'),
                ),
                'default' => 'unlock_by_date',
            ),
        );
    }

    /**
     * Save additional content when post is saved
     *
     * @since 0.1.0
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @return void
     */
    public function save_additional_content($post_id, $post) {
        // Only process courses
        if (!$post || $post->post_type !== 'courses') {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['tutorpress_additional_content_nonce']) || 
            !wp_verify_nonce($_POST['tutorpress_additional_content_nonce'], 'tutorpress_additional_content_metabox')) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Get data from hidden form fields created by React component
        $what_will_learn = isset($_POST['tutorpress_what_will_learn']) ? 
            sanitize_textarea_field($_POST['tutorpress_what_will_learn']) : '';
        $target_audience = isset($_POST['tutorpress_target_audience']) ? 
            sanitize_textarea_field($_POST['tutorpress_target_audience']) : '';
        $requirements = isset($_POST['tutorpress_requirements']) ? 
            sanitize_textarea_field($_POST['tutorpress_requirements']) : '';
        $content_drip_enabled = isset($_POST['tutorpress_content_drip_enabled']) ? 
            (bool) $_POST['tutorpress_content_drip_enabled'] : false;
        $content_drip_type = isset($_POST['tutorpress_content_drip_type']) ? 
            sanitize_text_field($_POST['tutorpress_content_drip_type']) : 'unlock_by_date';

        // Validate content drip type
        $valid_drip_types = array('unlock_by_date', 'specific_days', 'unlock_sequentially', 'after_finishing_prerequisites');
        if (!in_array($content_drip_type, $valid_drip_types)) {
            $content_drip_type = 'unlock_by_date';
        }

        // Save additional content fields to Tutor LMS compatible meta fields
        update_post_meta($post_id, '_tutor_course_benefits', $what_will_learn);
        update_post_meta($post_id, '_tutor_course_target_audience', $target_audience);
        update_post_meta($post_id, '_tutor_course_requirements', $requirements);

        // Save content drip settings (only if content drip addon is enabled)
        if (class_exists('TutorPress_Addon_Checker') && TutorPress_Addon_Checker::is_content_drip_enabled()) {
            // Get existing course settings
            $course_settings = get_post_meta($post_id, '_tutor_course_settings', true);
            if (!is_array($course_settings)) {
                $course_settings = array();
            }

            // Update content drip settings
            $course_settings['enable_content_drip'] = $content_drip_enabled;
            
            // Only save content drip type if content drip is enabled
            if ($content_drip_enabled) {
                $course_settings['content_drip_type'] = $content_drip_type;
            } else {
                // When disabled, remove the content drip type or set to default
                // This ensures "None" behavior - no content drip type is active
                unset($course_settings['content_drip_type']);
            }

            update_post_meta($post_id, '_tutor_course_settings', $course_settings);
        }
    }
} 