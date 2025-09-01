<?php
/**
 * Handles script and style enqueuing for TutorPress.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Assets {

    /**
     * Initialize the class.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_common_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_script_data']);
        
        // Add course selling option to Tutor LMS's course details response
        add_filter('tutor_course_details_response', [__CLASS__, 'add_course_selling_option_to_response']);
        
        // Sync data from Tutor LMS frontend to TutorPress
        add_action('save_post', [__CLASS__, 'sync_from_tutor_lms'], 10, 3);

		// Hook into Tutor LMS's course update process to handle selling_option
		add_action('tutor_after_prepare_update_post_meta', [__CLASS__, 'save_course_selling_option_from_tutor_update'], 10, 2);
        
        // Override Tutor Pro H5P addon filtering for frontend display
        add_action('init', [TutorPress_Addon_Checker::class, 'override_h5p_addon_filtering'], 100);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_common_assets() {
        // Localize addon checker data for frontend use
        wp_localize_script('jquery', 'tutorpressAddonChecker', 
            TutorPress_Addon_Checker::get_comprehensive_status()
        );
        
        // Also localize to window.tutorpress for compatibility
        wp_localize_script('jquery', 'tutorpress', [
            'addonChecker' => TutorPress_Addon_Checker::get_comprehensive_status()
        ]);
    }

    /**
     * Enqueue JavaScript for the Tutor LMS frontend dashboard.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_dashboard_assets() {
        // Only load if setting is enabled (use wrapper to respect Freemius gating)
        $options = get_option('tutorpress_settings', []);
        $enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_dashboard_redirects', false) : (!empty($options['enable_dashboard_redirects']));
        if (!$enabled) {
            return;
        }

        // Enqueue the standalone override script for frontend "New Course" button
        wp_enqueue_script(
            'tutorpress-override-tutorlms',
            TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
            true
        );

        // Add TutorPressData for overrides
        $enabledExtraLinks = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_extra_dashboard_links', false) : (!empty($options['enable_extra_dashboard_links']));
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'enableDashboardRedirects' => $enabled,
            'enableExtraDashboardLinks' => $enabledExtraLinks,
            'adminUrl' => admin_url(),
        ]);
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }
        
        $options = get_option('tutorpress_settings', []);
        // Use Freemius-aware wrapper to decide if sidebar tabs should be enabled
        $enabledSidebar = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_sidebar_tabs', false) : (!empty($options['enable_sidebar_tabs']));
        if (!$enabledSidebar) {
            return;
        }

        wp_enqueue_style(
            'tutorpress-comments-style',
            TUTORPRESS_URL . 'assets/css/tutor-comments.css',
            [],
            filemtime(TUTORPRESS_PATH . 'assets/css/tutor-comments.css'),
            'all'
        );

        wp_enqueue_script(
            'tutorpress-sidebar-tabs',
            TUTORPRESS_URL . 'assets/js/sidebar-tabs.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/sidebar-tabs.js'),
            true
        );

        // Localize a small object specifically for sidebar tabs to avoid coupling with other flags
        wp_localize_script('tutorpress-sidebar-tabs', 'TutorPressSidebar', [
            'enableSidebarTabs' => $enabledSidebar,
        ]);
    }

    /**
     * Enqueue admin-specific assets.
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public static function enqueue_admin_assets($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['courses', 'lesson', 'tutor_assignments', 'course-bundle'], true)) {
            return;
        }

        // Get the asset file for dependencies and version
        $asset_file = include TUTORPRESS_PATH . 'assets/js/build/index.asset.php';

        // Enqueue the bundled CSS (generated by webpack)
        wp_enqueue_style(
            'tutorpress-gutenberg',
            TUTORPRESS_URL . 'assets/js/build/index.css',
            ['wp-components'],
            $asset_file['version'],
            'all'
        );

        // Enqueue the built admin script
        wp_enqueue_script(
            'tutorpress-curriculum-metabox',
            TUTORPRESS_URL . 'assets/js/build/index.js',
            array_merge(['jquery', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-plugins', 'wp-edit-post', 'wp-i18n'], $asset_file['dependencies']),
            $asset_file['version'],
            true
        );

        // Get settings for localization
        $options = get_option('tutorpress_settings', []);

        // Add TutorPressData for overrides (use wrapper to respect Freemius gating)
        $dashboard_enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_dashboard_redirects', false) : !empty($options['enable_dashboard_redirects']);
        $admin_enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_admin_redirects', false) : !empty($options['enable_admin_redirects']);
        wp_localize_script('tutorpress-curriculum-metabox', 'TutorPressData', [
            'enableDashboardRedirects' => $dashboard_enabled,
            'enableAdminRedirects' => $admin_enabled,
            'adminUrl' => admin_url(),
        ]);

        // Localize script with necessary data
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorPressCurriculum', [
            'restUrl' => rest_url(),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isLesson' => 'lesson' === $screen->post_type,
            'isAssignment' => 'tutor_assignments' === $screen->post_type,
            'adminUrl' => admin_url(),
        ]);

        // Expose comprehensive addon and payment engine data to frontend
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorpressAddons', 
            TutorPress_Addon_Checker::get_comprehensive_status()
        );

        // Localize Freemius license status for feature gating
        // Pass structured data instead of raw HTML for better security
        $upgrade_url = '#';
        if (function_exists('tutorpress_fs')) {
            $upgrade_url = tutorpress_fs()->get_upgrade_url();
        } elseif (function_exists('my_fs')) {
            $upgrade_url = my_fs()->get_upgrade_url();
        }
        
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorpress_fs', [
            'canUsePremium' => tutorpress_fs_can_use_premium(),
            'upgradeUrl'    => $upgrade_url,
            'promo' => [
                'title'   => __('Unlock TutorPress Pro', 'tutorpress'),
                'message' => __('Activate to continue using this feature.', 'tutorpress'),
                'button'  => __('Upgrade', 'tutorpress')
            ],
        ]);
    }

    /**
     * Localize script data to pass settings to JavaScript.
     *
     * @since 0.1.0
     * @return void
     */
    public static function localize_script_data() {
        // Moved to enqueue_common_assets()
    }

    /**
     * Add course selling option to Tutor LMS's course details response.
     *
     * @since 0.1.0
     * @param array $response The Tutor LMS course details response.
     * @return array The modified response.
     */
    public static function add_course_selling_option_to_response($response) {
        // Get the course ID from the response
        $post_id = isset($response['ID']) ? (int) $response['ID'] : 0;
        
        if ($post_id > 0) {
            $selling_option = get_post_meta($post_id, '_tutor_course_selling_option', true);
            $response['course_selling_option'] = $selling_option ?: 'one_time'; // Default to one_time if empty
        }
        
        return $response;
    }
    
    /**
     * Sync data from Tutor LMS frontend to TutorPress.
     *
     * @since 0.1.0
     * @param int $post_id The post ID.
     * @param WP_Post $post The post object.
     * @param bool $update Whether this is an update.
     * @return void
     */
    public static function sync_from_tutor_lms($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Only handle course posts
        if (get_post_type($post_id) !== 'courses') {
            return;
        }
        
        // Check for selling option in various POST formats
        $selling_option = null;
        
        if (isset($_POST['tutor_course_selling_option'])) {
            $selling_option = sanitize_text_field($_POST['tutor_course_selling_option']);
        } elseif (isset($_POST['course_selling_option'])) {
            $selling_option = sanitize_text_field($_POST['course_selling_option']);
        } else {
            // Handle JSON payload from React frontend
            $json_input = file_get_contents('php://input');
            if (!empty($json_input)) {
                $json_data = json_decode($json_input, true);
                if ($json_data && isset($json_data['course_selling_option'])) {
                    $selling_option = sanitize_text_field($json_data['course_selling_option']);
                }
            }
        }
        
        if ($selling_option) {
            update_post_meta($post_id, '_tutor_course_selling_option', $selling_option);
        }
    }

	/**
	 * Save course selling option when Tutor LMS updates a course
	 */
	public static function save_course_selling_option_from_tutor_update($post_id, $params) {
		if (isset($params['course_selling_option'])) {
			$selling_option = sanitize_text_field($params['course_selling_option']);
			update_post_meta($post_id, '_tutor_course_selling_option', $selling_option);
		}
	}
}

// Initialize the class
TutorPress_Assets::init();
